<?php
/**
 * Contacts — Customer 360 profiles + unified timeline.
 *
 * A "contact" is a person identified by their normalized phone number (falling
 * back to email, then name). It aggregates every quote that person appears on,
 * plus a lightweight profile record (company, city, tags, project type, lead
 * source and free-text notes).
 *
 * Privacy wall:
 *   - Osoul staff (admin / branch / rep) see Osoul contacts only. Partner→customer
 *     quotes are excluded through the shared scope meta query, exactly like every
 *     other Osoul list.
 *   - A partner sees only their own customers, and partner profile records are
 *     namespaced ("partner:{id}") so a reseller's private customer notes never
 *     mix with Osoul's — even when two contacts share a phone number.
 *
 * The profile record is a private `osoul_contact` post (one per scope+key). It is
 * created lazily — only when someone actually saves a field or writes a note.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Private post type that stores contact profile fields + notes.
 * No public UI, no front-end query surface — infrastructure only.
 */
add_action( 'init', function () {
	register_post_type( 'osoul_contact', array(
		'public'              => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
	) );
} );

/* =========================================================================
 *  Keys & identity
 * ====================================================================== */

/**
 * Normalize a phone number to a canonical Saudi-local form (05XXXXXXXX).
 *
 * Handles the common shapes: +966 / 00966 / 966 prefixes, spaces, dashes and a
 * missing leading zero. Non-Saudi / unusual numbers fall back to their digits,
 * so they still group consistently with themselves.
 */
function osoul_norm_phone( $phone ) {
	$d = preg_replace( '/[^0-9]/', '', (string) $phone );
	if ( '' === $d ) { return ''; }
	if ( 0 === strpos( $d, '00966' ) ) {
		$d = '0' . substr( $d, 5 );
	} elseif ( 0 === strpos( $d, '966' ) ) {
		$d = '0' . substr( $d, 3 );
	}
	if ( 9 === strlen( $d ) && '5' === $d[0] ) {
		$d = '0' . $d;
	}
	return $d;
}

/** International (wa.me) form of a Saudi number, e.g. 966555123456. */
function osoul_intl_phone( $phone ) {
	$d = osoul_norm_phone( $phone );
	if ( '' === $d ) { return ''; }
	if ( 0 === strpos( $d, '0' ) ) { return '966' . substr( $d, 1 ); }
	return $d;
}

/**
 * Stable grouping key for a customer: phone first, then email, then name.
 * Returns '' when there is nothing to group on.
 */
function osoul_contact_key( $phone, $email = '', $name = '' ) {
	$p = osoul_norm_phone( $phone );
	if ( '' !== $p ) { return 'p:' . $p; }
	$e = strtolower( trim( (string) $email ) );
	if ( '' !== $e ) { return 'e:' . $e; }
	$n = trim( (string) $name );
	return '' !== $n ? 'n:' . $n : '';
}

/** Profile namespace for the viewer: shared "osoul" or private "partner:{id}". */
function osoul_contact_scope( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( 'partner' === osoul_portal_role( $user ) ) {
		return 'partner:' . (int) $user->ID;
	}
	return 'osoul';
}

/* =========================================================================
 *  Quote scanning (scope-aware — the privacy wall lives here)
 * ====================================================================== */

/**
 * The quote posts visible to this viewer for contact aggregation.
 *   - partner  → their own customer quotes only (kind = cust)
 *   - admin    → every Osoul quote EXCEPT partner→customer quotes
 *   - branch/rep → their attributed quotes
 *
 * Capped at a high limit; osoul_contact_scan_cap() logs if the cap is hit.
 */
function osoul_contact_scan_quotes( $user ) {
	if ( 'partner' === osoul_portal_role( $user ) && function_exists( 'osoul_partner_customer_quotes' ) ) {
		return osoul_partner_customer_quotes( (int) $user->ID );
	}
	$args = array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	$scope = osoul_quote_scope_meta_query( $user );
	if ( $scope ) { $args['meta_query'] = array( $scope[0] ); }
	$q = new WP_Query( $args );
	return $q->posts;
}

/* =========================================================================
 *  Profile records (lazily created)
 * ====================================================================== */

/** Find the profile post id for a scope+key, or 0 if none exists yet. */
function osoul_contact_profile_find_id( $scope, $key ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_contact',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_scope', 'value' => $scope ),
			array( 'key' => '_ckey', 'value' => $key ),
		),
	) );
	return empty( $q->posts ) ? 0 : (int) $q->posts[0];
}

/** Find-or-create the profile post id for a scope+key. */
function osoul_contact_profile_ensure_id( $scope, $key, $name = '' ) {
	$id = osoul_contact_profile_find_id( $scope, $key );
	if ( $id ) { return $id; }
	$id = wp_insert_post( array(
		'post_type'   => 'osoul_contact',
		'post_status' => 'publish',
		'post_title'  => $name ?: $key,
	), true );
	if ( is_wp_error( $id ) ) { return 0; }
	update_post_meta( $id, '_scope', $scope );
	update_post_meta( $id, '_ckey', $key );
	return (int) $id;
}

/**
 * Batch-load every profile record for a scope, indexed by contact key.
 * Used by the list view so it runs one query instead of one-per-contact.
 * Returns only the list-facing fields (company / city / tags).
 */
function osoul_contact_profiles_map( $scope ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_contact',
		'post_status'    => 'publish',
		'posts_per_page' => 5000,
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_scope', 'value' => $scope ) ),
	) );
	$map = array();
	foreach ( $q->posts as $p ) {
		$ckey = get_post_meta( $p->ID, '_ckey', true );
		if ( '' === $ckey ) { continue; }
		$map[ $ckey ] = array(
			'company' => (string) get_post_meta( $p->ID, '_company', true ),
			'city'    => (string) get_post_meta( $p->ID, '_city', true ),
			'tags'    => array_values( (array) get_post_meta( $p->ID, '_tags', true ) ),
		);
	}
	return $map;
}

/** Read a profile record into a normalized array. */
function osoul_contact_profile_read( $pid ) {
	if ( ! $pid ) {
		return array( 'company' => '', 'city' => '', 'tags' => array(), 'project_type' => '', 'source' => '', 'notes' => array() );
	}
	return array(
		'company'      => (string) get_post_meta( $pid, '_company', true ),
		'city'         => (string) get_post_meta( $pid, '_city', true ),
		'tags'         => array_values( (array) get_post_meta( $pid, '_tags', true ) ),
		'project_type' => (string) get_post_meta( $pid, '_project_type', true ),
		'source'       => (string) get_post_meta( $pid, '_source', true ),
		'notes'        => array_values( (array) get_post_meta( $pid, '_notes', true ) ),
	);
}

/** Persist editable profile fields (only the keys present are touched). */
function osoul_contact_save_fields( $user, $key, $fields ) {
	$scope = osoul_contact_scope( $user );
	$pid   = osoul_contact_profile_ensure_id( $scope, $key, (string) ( $fields['name'] ?? '' ) );
	if ( ! $pid ) { return false; }
	if ( isset( $fields['company'] ) )      { update_post_meta( $pid, '_company', sanitize_text_field( (string) $fields['company'] ) ); }
	if ( isset( $fields['city'] ) )         { update_post_meta( $pid, '_city', sanitize_text_field( (string) $fields['city'] ) ); }
	if ( isset( $fields['project_type'] ) ) { update_post_meta( $pid, '_project_type', sanitize_text_field( (string) $fields['project_type'] ) ); }
	if ( isset( $fields['source'] ) )       { update_post_meta( $pid, '_source', sanitize_text_field( (string) $fields['source'] ) ); }
	if ( isset( $fields['tags'] ) ) {
		$tags = array_filter( array_map( 'sanitize_text_field', (array) $fields['tags'] ), function ( $s ) { return '' !== trim( $s ); } );
		$tags = array_slice( array_values( array_unique( $tags ) ), 0, 12 );
		update_post_meta( $pid, '_tags', $tags );
	}
	return true;
}

/** Append a free-text note to the contact's timeline. */
function osoul_contact_add_note( $user, $key, $text, $name = '' ) {
	$text = sanitize_textarea_field( (string) $text );
	if ( '' === trim( $text ) ) { return false; }
	$scope = osoul_contact_scope( $user );
	$pid   = osoul_contact_profile_ensure_id( $scope, $key, $name );
	if ( ! $pid ) { return false; }
	$notes   = (array) get_post_meta( $pid, '_notes', true );
	$notes[] = array(
		'time' => current_time( 'mysql' ),
		'text' => $text,
		'by'   => wp_get_current_user()->display_name,
	);
	if ( count( $notes ) > 200 ) { $notes = array_slice( $notes, -200 ); }
	update_post_meta( $pid, '_notes', $notes );
	return true;
}

/* =========================================================================
 *  Aggregation — list + 360
 * ====================================================================== */

/** Which stages count as "open / still in the pipeline". */
function osoul_contact_pending_stages() {
	return array( 'new', 'pricing', 'sent', 'negotiation' );
}

/**
 * Aggregated contact list for the viewer's scope, one row per person.
 * Each row carries lifetime stats + profile decoration (company/city/tags).
 */
function osoul_contacts_list( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	$rows = array();

	foreach ( osoul_contact_scan_quotes( $user ) as $qp ) {
		$c     = (array) get_post_meta( $qp->ID, '_osoul_customer', true );
		$name  = trim( (string) ( $c['name'] ?? '' ) );
		$phone = trim( (string) ( $c['phone'] ?? '' ) );
		$email = trim( (string) ( $c['email'] ?? '' ) );
		$key   = osoul_contact_key( $phone, $email, $name );
		if ( '' === $key ) { continue; }

		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array(
				'key' => $key, 'name' => $name, 'phone' => $phone, 'email' => $email,
				'quotes' => 0, 'won' => 0, 'pending' => 0, 'value' => 0.0, 'last_ts' => 0,
				'uid' => 0, 'registered' => false,
			);
		}
		$r = $rows[ $key ];
		if ( '' === $r['name']  && '' !== $name )  { $r['name']  = $name; }
		if ( '' === $r['email'] && '' !== $email ) { $r['email'] = $email; }
		if ( '' === $r['phone'] && '' !== $phone ) { $r['phone'] = $phone; }

		$stage = get_post_meta( $qp->ID, '_osoul_stage', true ) ?: 'new';
		$r['quotes']++;
		if ( 'won' === $stage ) {
			$r['won']++;
			$items = (array) get_post_meta( $qp->ID, '_osoul_items', true );
			$t     = osoul_quote_totals( $items, (float) get_post_meta( $qp->ID, '_osoul_discount', true ) );
			$r['value'] += (float) $t['total'];
		} elseif ( in_array( $stage, osoul_contact_pending_stages(), true ) ) {
			$r['pending']++;
		}
		$ts = (int) get_post_time( 'U', true, $qp );
		if ( $ts > $r['last_ts'] ) { $r['last_ts'] = $ts; }
		$rows[ $key ] = $r;
	}

	// Admin also sees registered website customers who have not requested a quote yet.
	if ( 'admin' === osoul_portal_role( $user ) && function_exists( 'osoul_customers_list' ) ) {
		foreach ( osoul_customers_list() as $cu ) {
			$key = osoul_contact_key( $cu['phone'], $cu['email'], $cu['name'] );
			if ( '' === $key ) { continue; }
			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = array(
					'key' => $key, 'name' => $cu['name'], 'phone' => $cu['phone'], 'email' => $cu['email'],
					'quotes' => 0, 'won' => 0, 'pending' => 0, 'value' => 0.0, 'last_ts' => 0,
					'uid' => (int) $cu['id'], 'registered' => true,
				);
			} else {
				$rows[ $key ]['uid']        = (int) $cu['id'];
				$rows[ $key ]['registered'] = true;
				if ( '' === $rows[ $key ]['email'] && '' !== $cu['email'] ) { $rows[ $key ]['email'] = $cu['email']; }
			}
		}
	}

	$scope = osoul_contact_scope( $user );
	$pmap  = osoul_contact_profiles_map( $scope );
	$out   = array();
	foreach ( $rows as $r ) {
		$prof  = $pmap[ $r['key'] ] ?? array( 'company' => '', 'city' => '', 'tags' => array() );
		$out[] = array(
			'key'        => $r['key'],
			'name'       => $r['name'] ?: '—',
			'phone'      => $r['phone'],
			'email'      => $r['email'],
			'company'    => $prof['company'],
			'city'       => $prof['city'],
			'tags'       => $prof['tags'],
			'quotes'     => $r['quotes'],
			'won'        => $r['won'],
			'pending'    => $r['pending'],
			'value'      => osoul_money( $r['value'] ),
			'last'       => $r['last_ts'] ? gmdate( 'Y-m-d', $r['last_ts'] ) : '',
			'last_ts'    => $r['last_ts'],
			'uid'        => $r['uid'],
			'registered' => $r['registered'],
		);
	}
	usort( $out, function ( $a, $b ) { return $b['last_ts'] <=> $a['last_ts']; } );
	return $out;
}

/**
 * Full Customer-360 payload for one contact key: identity, profile, lifetime
 * stats, the customer's quotes, and a unified timeline (quote events + notes).
 */
function osoul_contact_360( $user, $key ) {
	$user  = $user instanceof WP_User ? $user : wp_get_current_user();
	$role  = osoul_portal_role( $user );
	$scope = osoul_contact_scope( $user );

	$name = ''; $phone = ''; $email = '';
	$quotes = array(); $timeline = array();
	$stats = array( 'quotes' => 0, 'won' => 0, 'pending' => 0, 'lost' => 0, 'value' => 0.0, 'first_ts' => 0, 'last_ts' => 0 );

	foreach ( osoul_contact_scan_quotes( $user ) as $qp ) {
		$c = (array) get_post_meta( $qp->ID, '_osoul_customer', true );
		$k = osoul_contact_key( $c['phone'] ?? '', $c['email'] ?? '', $c['name'] ?? '' );
		if ( $k !== $key ) { continue; }

		if ( '' === $name  && ! empty( $c['name'] ) )  { $name  = trim( (string) $c['name'] ); }
		if ( '' === $phone && ! empty( $c['phone'] ) ) { $phone = trim( (string) $c['phone'] ); }
		if ( '' === $email && ! empty( $c['email'] ) ) { $email = trim( (string) $c['email'] ); }

		$stage = get_post_meta( $qp->ID, '_osoul_stage', true ) ?: 'new';
		$stats['quotes']++;
		if ( 'won' === $stage ) {
			$stats['won']++;
			$items = (array) get_post_meta( $qp->ID, '_osoul_items', true );
			$t     = osoul_quote_totals( $items, (float) get_post_meta( $qp->ID, '_osoul_discount', true ) );
			$stats['value'] += (float) $t['total'];
		} elseif ( 'lost' === $stage ) {
			$stats['lost']++;
		} else {
			$stats['pending']++;
		}
		$ts = (int) get_post_time( 'U', true, $qp );
		if ( ! $stats['first_ts'] || $ts < $stats['first_ts'] ) { $stats['first_ts'] = $ts; }
		if ( $ts > $stats['last_ts'] ) { $stats['last_ts'] = $ts; }

		$quotes[] = osoul_quote_summary( $qp, $role );

		$number = get_post_meta( $qp->ID, '_osoul_number', true );
		$qref   = $number ?: ( '#' . $qp->ID );
		$log    = (array) get_post_meta( $qp->ID, '_osoul_log', true );
		if ( empty( $log ) ) {
			$timeline[] = array( 'ts' => $ts, 'time' => get_the_date( 'Y-m-d H:i', $qp ), 'text' => 'تم إنشاء الطلب', 'by' => '', 'type' => 'event', 'quote' => $qref, 'qid' => (int) $qp->ID );
		} else {
			foreach ( $log as $ev ) {
				$et = isset( $ev['time'] ) ? strtotime( $ev['time'] ) : 0;
				$timeline[] = array(
					'ts'    => $et ?: $ts,
					'time'  => (string) ( $ev['time'] ?? '' ),
					'text'  => (string) ( $ev['event'] ?? '' ),
					'by'    => (string) ( $ev['by'] ?? '' ),
					'type'  => 'event',
					'quote' => $qref,
					'qid'   => (int) $qp->ID,
				);
			}
		}
	}

	$prof = osoul_contact_profile_read( osoul_contact_profile_find_id( $scope, $key ) );
	foreach ( (array) $prof['notes'] as $n ) {
		$nt = isset( $n['time'] ) ? strtotime( $n['time'] ) : 0;
		$timeline[] = array(
			'ts'    => $nt ?: 0,
			'time'  => (string) ( $n['time'] ?? '' ),
			'text'  => (string) ( $n['text'] ?? '' ),
			'by'    => (string) ( $n['by'] ?? '' ),
			'type'  => 'note',
			'quote' => '',
			'qid'   => 0,
		);
	}

	usort( $timeline, function ( $a, $b ) { return $b['ts'] <=> $a['ts']; } );
	usort( $quotes, function ( $a, $b ) { return (int) ( $b['ts'] ?? 0 ) <=> (int) ( $a['ts'] ?? 0 ); } );

	// Format timeline dates for display (short, localized-ish).
	foreach ( $timeline as &$tl ) {
		$tl['when'] = $tl['ts'] ? gmdate( 'Y-m-d H:i', $tl['ts'] ) : $tl['time'];
	}
	unset( $tl );

	// Is this contact a registered website customer? (admin may delete the account.)
	$uid = 0;
	if ( 'osoul' === $scope && '' !== $email ) {
		$u = get_user_by( 'email', $email );
		if ( $u && in_array( 'osoul_customer', (array) $u->roles, true ) ) { $uid = (int) $u->ID; }
	}

	return array(
		'key'     => $key,
		'name'    => $name ?: '—',
		'phone'   => $phone,
		'intl'    => osoul_intl_phone( $phone ),
		'email'   => $email,
		'uid'     => $uid,
		'registered' => (bool) $uid,
		'profile' => array(
			'company'      => $prof['company'],
			'city'         => $prof['city'],
			'tags'         => $prof['tags'],
			'project_type' => $prof['project_type'],
			'source'       => $prof['source'],
		),
		'stats'   => array(
			'quotes'  => $stats['quotes'],
			'won'     => $stats['won'],
			'pending' => $stats['pending'],
			'lost'    => $stats['lost'],
			'value'   => osoul_money( $stats['value'] ),
			'rate'    => $stats['quotes'] ? (int) round( $stats['won'] / $stats['quotes'] * 100 ) : 0,
			'first'   => $stats['first_ts'] ? gmdate( 'Y-m-d', $stats['first_ts'] ) : '',
			'last'    => $stats['last_ts'] ? gmdate( 'Y-m-d', $stats['last_ts'] ) : '',
		),
		'quotes'   => $quotes,
		'timeline' => $timeline,
	);
}

/* =========================================================================
 *  REST — /portal/contacts, /contact, /contact-save, /contact-note
 * ====================================================================== */

add_action( 'rest_api_init', function () {
	$ns = 'osoul/v1';

	register_rest_route( $ns, '/portal/contacts', array(
		'methods'             => 'GET',
		'callback'            => 'osoul_rest_contacts',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/contact', array(
		'methods'             => 'GET',
		'callback'            => 'osoul_rest_contact',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/contact-save', array(
		'methods'             => 'POST',
		'callback'            => 'osoul_rest_contact_save',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/contact-note', array(
		'methods'             => 'POST',
		'callback'            => 'osoul_rest_contact_note',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
} );

/** GET /portal/contacts — the aggregated contact list for the viewer's scope. */
function osoul_rest_contacts( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$out  = array( 'contacts' => osoul_contacts_list( $user ) );
	// A partner may download its private customer list as Excel.
	if ( 'partner' === osoul_portal_role( $user ) && function_exists( 'osoul_partner_export_url' ) ) {
		$out['export'] = osoul_partner_export_url();
	}
	return rest_ensure_response( $out );
}

/** GET /portal/contact?key=… — the Customer-360 payload for one contact. */
function osoul_rest_contact( WP_REST_Request $req ) {
	$key = (string) $req->get_param( 'key' );
	if ( '' === $key ) {
		return new WP_Error( 'osoul_missing', 'المفتاح مطلوب.', array( 'status' => 422 ) );
	}
	return rest_ensure_response( osoul_contact_360( wp_get_current_user(), $key ) );
}

/** POST /portal/contact-save — save editable profile fields. */
function osoul_rest_contact_save( WP_REST_Request $req ) {
	$key = (string) $req->get_param( 'key' );
	if ( '' === $key ) {
		return new WP_Error( 'osoul_missing', 'المفتاح مطلوب.', array( 'status' => 422 ) );
	}
	$fields = array();
	foreach ( array( 'company', 'city', 'project_type', 'source', 'name' ) as $k ) {
		$v = $req->get_param( $k );
		if ( null !== $v ) { $fields[ $k ] = $v; }
	}
	$tags = $req->get_param( 'tags' );
	if ( null !== $tags ) { $fields['tags'] = (array) $tags; }

	osoul_contact_save_fields( wp_get_current_user(), $key, $fields );
	return rest_ensure_response( osoul_contact_360( wp_get_current_user(), $key ) );
}

/** POST /portal/contact-note — append a note, return the refreshed 360. */
function osoul_rest_contact_note( WP_REST_Request $req ) {
	$key  = (string) $req->get_param( 'key' );
	$text = (string) $req->get_param( 'text' );
	if ( '' === $key ) {
		return new WP_Error( 'osoul_missing', 'المفتاح مطلوب.', array( 'status' => 422 ) );
	}
	if ( '' === trim( $text ) ) {
		return new WP_Error( 'osoul_empty', 'الملاحظة فارغة.', array( 'status' => 422 ) );
	}
	osoul_contact_add_note( wp_get_current_user(), $key, $text, (string) $req->get_param( 'name' ) );
	return rest_ensure_response( osoul_contact_360( wp_get_current_user(), $key ) );
}
