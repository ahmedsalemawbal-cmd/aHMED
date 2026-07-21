<?php
/**
 * B2B portal — REST API.
 *
 * Every screen in the single-page portal (admin / branch / rep) reads and writes
 * through these JSON endpoints, which is what lets the UI update live without a
 * page reload. All routes require a logged-in portal user (cookie auth + the
 * wp_rest nonce that WordPress enforces for cookie-authenticated REST calls);
 * mutating routes additionally check the caller's role.
 *
 * Namespace: osoul/v1/portal/*
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Permission callbacks
 * ---------------------------------------------------------------------- */

/** Any active portal user (admin / branch / rep). */
function osoul_portal_perm_any() {
	if ( ! is_user_logged_in() || ! osoul_portal_can_access() ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	if ( ! osoul_portal_is_active( get_current_user_id() ) ) {
		return new WP_Error( 'osoul_suspended', 'الحساب موقوف.', array( 'status' => 403 ) );
	}
	return true;
}

/** Admin only. */
function osoul_portal_perm_admin() {
	$ok = osoul_portal_perm_any();
	if ( is_wp_error( $ok ) ) { return $ok; }
	return ( 'admin' === osoul_portal_role() ) ? true
		: new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
}

/** Admin or branch (the two roles that may create reps). */
function osoul_portal_perm_manager() {
	$ok = osoul_portal_perm_any();
	if ( is_wp_error( $ok ) ) { return $ok; }
	$role = osoul_portal_role();
	return ( 'admin' === $role || 'branch' === $role ) ? true
		: new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
}

/** Partner only (the reseller workspace endpoints). */
function osoul_portal_perm_partner() {
	$ok = osoul_portal_perm_any();
	if ( is_wp_error( $ok ) ) { return $ok; }
	return ( 'partner' === osoul_portal_role() ) ? true
		: new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
}

/**
 * Never let any cache store the portal's REST responses. A cached /bootstrap
 * would hand back a stale nonce (logging the user out on refresh) or stale brand
 * colours (the partner's saved colours appearing to revert). Applies to the
 * whole osoul/v1/portal namespace + the customer endpoints.
 */
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
	$route = is_object( $request ) ? ltrim( (string) $request->get_route(), '/' ) : '';
	if ( 0 === strpos( $route, 'osoul/v1/portal' ) || 0 === strpos( $route, 'osoul/v1/customer' ) ) {
		nocache_headers();
	}
	return $served;
}, 10, 3 );

/* -------------------------------------------------------------------------
 *  Routes
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	$ns = 'osoul/v1';

	register_rest_route( $ns, '/portal/bootstrap', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_bootstrap',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/quotes', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_quotes',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/quote/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_quote',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/stats', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_stats',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/products', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_products',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/request', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_create_request',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/price', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_price',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/stage', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_stage',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/reps', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_reps',
		'permission_callback' => 'osoul_portal_perm_manager',
	) );
	register_rest_route( $ns, '/portal/rep', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_create_rep',
		'permission_callback' => 'osoul_portal_perm_manager',
	) );
	register_rest_route( $ns, '/portal/branches', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_branches',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/branch', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_create_branch',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/account-toggle', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_account_toggle',
		'permission_callback' => 'osoul_portal_perm_manager',
	) );
	register_rest_route( $ns, '/portal/account-reinvite', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_account_reinvite',
		'permission_callback' => 'osoul_portal_perm_manager',
	) );
	register_rest_route( $ns, '/portal/partners', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_partners',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/partner-approve', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_partner_approve',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/customers', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_customers',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );

	// ── Partner (reseller) workspace ──
	register_rest_route( $ns, '/portal/p-supplies', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_p_supplies',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-supply/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_p_supply',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-supply', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_p_supply_create',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-quote', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_p_quote',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-quotes', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_p_quotes',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-customers', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'osoul_rest_p_customers',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-catalog', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_p_catalog',
		'permission_callback' => 'osoul_portal_perm_partner',
	) );
	register_rest_route( $ns, '/portal/p-profile', array(
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'osoul_rest_p_profile',
			'permission_callback' => 'osoul_portal_perm_partner',
		),
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'osoul_rest_p_profile_save',
			'permission_callback' => 'osoul_portal_perm_partner',
		),
	) );
} );

/* -------------------------------------------------------------------------
 *  Payload builders
 * ---------------------------------------------------------------------- */

/** True once the admin has issued the quote (rep/branch may then see money). */
function osoul_quote_is_issued( $quote_id ) {
	return (bool) get_post_meta( $quote_id, '_osoul_issued', true );
}

/**
 * Compact quote record for the list views.
 *
 * @param WP_Post $post
 * @param string  $role  Viewer role.
 * @return array
 */
function osoul_quote_summary( $post, $role ) {
	$id       = $post->ID;
	$cust     = (array) get_post_meta( $id, '_osoul_customer', true );
	$items    = (array) get_post_meta( $id, '_osoul_items', true );
	$stage    = get_post_meta( $id, '_osoul_stage', true ) ?: 'new';
	$stages   = osoul_quote_stages();
	$st       = $stages[ $stage ] ?? $stages['new'];
	$source   = get_post_meta( $id, '_osoul_source', true ) ?: 'web';
	$issued   = osoul_quote_is_issued( $id );
	$number   = get_post_meta( $id, '_osoul_number', true );
	$token    = get_post_meta( $id, '_osoul_token', true );

	// Money is visible to the admin always; to branch/rep only after issuing.
	$show_money = ( 'admin' === $role ) || $issued;
	$total      = null;
	if ( $show_money ) {
		$totals = osoul_quote_totals( $items, (float) get_post_meta( $id, '_osoul_discount', true ) );
		$total  = $totals['subtotal'] > 0 ? $totals['total'] : null;
	}

	$rep_id    = (int) get_post_meta( $id, '_osoul_rep_id', true );
	$branch_id = (int) get_post_meta( $id, '_osoul_branch_id', true );

	return array(
		'id'           => $id,
		'number'       => $number ?: '',
		'customer'     => $cust['name'] ?? '',
		'phone'        => $cust['phone'] ?? '',
		'items'        => count( $items ),
		'total'        => null === $total ? null : osoul_money( $total ),
		'stage'        => $stage,
		'stage_label'  => $st['ar'],
		'stage_color'  => $st['color'],
		'source'       => $source,
		'source_label' => osoul_quote_source_label( $source ),
		'rep'          => $rep_id ? get_the_author_meta( 'display_name', $rep_id ) : '',
		'branch'       => $branch_id ? get_the_author_meta( 'display_name', $branch_id ) : '',
		'issued'       => $issued,
		'public_url'   => ( $issued && $token ) ? home_url( '/quote/' . $token . '/' ) : '',
		'date'         => get_the_date( 'Y-m-d', $post ),
		'ts'           => get_post_time( 'U', true, $post ),
	);
}

/** Bilingual-ish label for a quote source. */
function osoul_quote_source_label( $source ) {
	$map = array( 'web' => 'الموقع', 'rep' => 'مندوب', 'branch' => 'فرع', 'partner' => 'شريك' );
	return $map[ $source ] ?? 'الموقع';
}

/**
 * Full detail payload for one quote (fields depend on role).
 */
function osoul_quote_detail( $post, $role ) {
	$id       = $post->ID;
	$cust     = (array) get_post_meta( $id, '_osoul_customer', true );
	$items    = (array) get_post_meta( $id, '_osoul_items', true );
	$discount = (float) get_post_meta( $id, '_osoul_discount', true );
	$stage    = get_post_meta( $id, '_osoul_stage', true ) ?: 'new';
	$issued   = osoul_quote_is_issued( $id );
	$token    = get_post_meta( $id, '_osoul_token', true );
	$number   = get_post_meta( $id, '_osoul_number', true );
	$show_money = ( 'admin' === $role ) || $issued;

	$lines = array();
	foreach ( $items as $i => $it ) {
		$line = array(
			'i'    => $i,
			'name' => $it['name'] ?? '',
			'qty'  => (int) ( $it['qty'] ?? 1 ),
		);
		if ( $show_money ) {
			$line['unit_price'] = (float) ( $it['unit_price'] ?? 0 );
			$line['line']       = osoul_money( $line['unit_price'] * $line['qty'] );
		}
		$lines[] = $line;
	}

	$out = array(
		'id'          => $id,
		'number'      => $number ?: '',
		'stage'       => $stage,
		'source'      => get_post_meta( $id, '_osoul_source', true ) ?: 'web',
		'issued'      => $issued,
		'customer'    => array(
			'name'  => $cust['name'] ?? '',
			'phone' => $cust['phone'] ?? '',
			'email' => $cust['email'] ?? '',
		),
		'items'       => $lines,
		'note'        => get_post_meta( $id, '_osoul_note', true ) ?: '',
		'public_url'  => ( $issued && $token ) ? home_url( '/quote/' . $token . '/' ) : '',
		'date'        => get_the_date( 'Y-m-d H:i', $post ),
		'log'         => (array) get_post_meta( $id, '_osoul_log', true ),
		'can_price'   => ( 'admin' === $role ),
	);

	if ( $show_money ) {
		$totals          = osoul_quote_totals( $items, $discount );
		$out['discount'] = $discount;
		$out['validity'] = (int) ( get_post_meta( $id, '_osoul_validity', true ) ?: osoul_opt( 'validity_days' ) );
		$out['terms_ar'] = get_post_meta( $id, '_osoul_terms_ar', true ) ?: osoul_opt( 'terms_ar' );
		$out['terms_en'] = get_post_meta( $id, '_osoul_terms_en', true ) ?: osoul_opt( 'terms_en' );
		$out['totals']   = array(
			'subtotal' => osoul_money( $totals['subtotal'] ),
			'discount' => osoul_money( $totals['discount'] ),
			'vat'      => osoul_money( $totals['vat'] ),
			'total'    => osoul_money( $totals['total'] ),
		);
	}

	if ( 'admin' === $role ) {
		$rep_id    = (int) get_post_meta( $id, '_osoul_rep_id', true );
		$branch_id = (int) get_post_meta( $id, '_osoul_branch_id', true );
		$out['rep']    = $rep_id ? get_the_author_meta( 'display_name', $rep_id ) : '';
		$out['branch'] = $branch_id ? get_the_author_meta( 'display_name', $branch_id ) : '';
		$out['wa']     = 'https://wa.me/' . preg_replace( '/\D/', '', $cust['phone'] ?? '' );
	}

	return $out;
}

/* -------------------------------------------------------------------------
 *  Read endpoints
 * ---------------------------------------------------------------------- */

function osoul_rest_bootstrap( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	$out = array(
		'role'        => $role,
		'role_label'  => osoul_portal_role_label( $role ),
		'name'        => $user->display_name,
		'email'       => $user->user_email,
		'phone'       => get_user_meta( $user->ID, '_osoul_phone', true ),
		'avatar'      => function_exists( 'osoul_user_avatar' ) ? osoul_user_avatar( $user->ID ) : '',
		'stages'      => osoul_quote_stages(),
		'vat'         => 15,
		'new_count'   => osoul_portal_new_count( $user, $role ),
		'chat_unread' => function_exists( 'osoul_chat_unread_total' ) ? osoul_chat_unread_total() : 0,
	);
	if ( 'admin' === $role && function_exists( 'osoul_admin_export_links' ) ) {
		$out['exports'] = osoul_admin_export_links();
	}
	if ( 'branch' === $role ) {
		$out['branch'] = array(
			'city'    => get_user_meta( $user->ID, '_osoul_city', true ),
			'manager' => get_user_meta( $user->ID, '_osoul_manager', true ),
		);
	}
	if ( 'partner' === $role && function_exists( 'osoul_partner_get' ) ) {
		$out['partner'] = array(
			'company' => osoul_partner_get( $user->ID, 'company_ar' ) ?: $user->display_name,
			'logo'    => osoul_partner_get( $user->ID, 'logo' ),
			'color'   => osoul_partner_sanitize_hex( osoul_partner_get( $user->ID, 'color' ), '#0d1f3c' ),
			'accent'  => osoul_partner_sanitize_hex( osoul_partner_get( $user->ID, 'accent' ), '#D21217' ),
		);
	}
	return rest_ensure_response( $out );
}

/** Count of unseen "new" requests in the viewer's scope (for the live badge). */
function osoul_portal_new_count( $user, $role ) {
	$args = array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( 'relation' => 'AND', array( 'key' => '_osoul_stage', 'value' => 'new' ) ),
	);
	$scope = osoul_quote_scope_meta_query( $user );
	if ( $scope ) {
		$args['meta_query'][] = $scope[0];
	}
	$q = new WP_Query( $args );
	return (int) count( $q->posts );
}

function osoul_rest_quotes( WP_REST_Request $req ) {
	$user   = wp_get_current_user();
	$role   = osoul_portal_role( $user );
	$stages = osoul_quote_stages();

	$f_stage  = sanitize_key( (string) $req->get_param( 'stage' ) );
	$f_source = sanitize_key( (string) $req->get_param( 'source' ) );
	$search   = sanitize_text_field( (string) $req->get_param( 'search' ) );
	// The archive (trash) view is admin-only.
	$archived = ( 'admin' === $role ) ? (int) $req->get_param( 'archived' ) : 0;

	$meta = array( 'relation' => 'AND' );
	$scope = osoul_quote_scope_meta_query( $user );
	if ( $scope ) { $meta[] = $scope[0]; }
	if ( $f_stage && isset( $stages[ $f_stage ] ) ) {
		$meta[] = array( 'key' => '_osoul_stage', 'value' => $f_stage );
	}
	if ( $f_source && in_array( $f_source, array( 'web', 'rep', 'branch', 'partner' ), true ) ) {
		$meta[] = array( 'key' => '_osoul_source', 'value' => $f_source );
	}

	$args = array(
		'post_type'      => 'osoul_quote',
		'post_status'    => $archived ? 'trash' : 'publish',
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	if ( count( $meta ) > 1 ) { $args['meta_query'] = $meta; }
	if ( $search ) { $args['s'] = $search; }

	$q   = new WP_Query( $args );
	$out = array();
	foreach ( $q->posts as $post ) {
		$out[] = osoul_quote_summary( $post, $role );
	}
	return rest_ensure_response( array( 'quotes' => $out ) );
}

function osoul_rest_quote( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! osoul_quote_visible_to( $id ) ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	$post = get_post( $id );
	if ( ! $post || 'osoul_quote' !== $post->post_type ) {
		return new WP_Error( 'osoul_not_found', 'غير موجود.', array( 'status' => 404 ) );
	}
	return rest_ensure_response( osoul_quote_detail( $post, osoul_portal_role() ) );
}

function osoul_rest_products( WP_REST_Request $req ) {
	$role    = osoul_portal_role();
	$catalog = osoul_catalog();
	$labels  = function_exists( 'osoul_group_labels' ) ? osoul_group_labels() : array();

	$groups = array();
	foreach ( $catalog as $slug => $p ) {
		$g = $p['group'] ?? 'other';
		if ( ! isset( $groups[ $g ] ) ) {
			$groups[ $g ] = array(
				'group' => $g,
				'label' => $labels[ $g ]['ar'] ?? $g,
				'items' => array(),
			);
		}
		$item = array(
			'slug'    => $slug,
			'name'    => $p['name'] ?? '',
			'name_en' => $p['name_en'] ?? '',
			'image'   => $p['img'] ?? '',
			'desc'    => $p['sub'] ?? '',
		);
		// Prices are hidden from reps/branches; only the admin sees them.
		if ( 'admin' === $role && isset( $p['price'] ) ) {
			$item['price'] = (float) $p['price'];
		}
		$groups[ $g ]['items'][] = $item;
	}
	return rest_ensure_response( array( 'groups' => array_values( $groups ) ) );
}

/* -------------------------------------------------------------------------
 *  Stats
 * ---------------------------------------------------------------------- */

function osoul_rest_stats( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	$args = array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 500,
		'no_found_rows'  => true,
	);
	$scope = osoul_quote_scope_meta_query( $user );
	if ( $scope ) { $args['meta_query'] = $scope; }
	$q = new WP_Query( $args );

	$by_stage  = array();
	$by_source = array( 'web' => 0, 'rep' => 0, 'branch' => 0, 'partner' => 0 );
	$sales     = 0.0;
	$total_q   = 0;
	$won       = 0;
	$rep_perf  = array();   // rep_id => [name,total,won,sales]

	foreach ( $q->posts as $post ) {
		$id     = $post->ID;
		$stage  = get_post_meta( $id, '_osoul_stage', true ) ?: 'new';
		$source = get_post_meta( $id, '_osoul_source', true ) ?: 'web';
		$items  = (array) get_post_meta( $id, '_osoul_items', true );
		$tot    = osoul_quote_totals( $items, (float) get_post_meta( $id, '_osoul_discount', true ) );

		$total_q++;
		$by_stage[ $stage ] = ( $by_stage[ $stage ] ?? 0 ) + 1;
		if ( isset( $by_source[ $source ] ) ) { $by_source[ $source ]++; }
		if ( 'won' === $stage ) { $won++; $sales += $tot['total']; }

		$rid = (int) get_post_meta( $id, '_osoul_rep_id', true );
		if ( $rid ) {
			if ( ! isset( $rep_perf[ $rid ] ) ) {
				$rep_perf[ $rid ] = array(
					'name'  => get_the_author_meta( 'display_name', $rid ),
					'total' => 0, 'won' => 0, 'sales' => 0.0,
				);
			}
			$rep_perf[ $rid ]['total']++;
			if ( 'won' === $stage ) { $rep_perf[ $rid ]['won']++; $rep_perf[ $rid ]['sales'] += $tot['total']; }
		}
	}

	$out = array(
		'total'      => $total_q,
		'won'        => $won,
		'sales'      => osoul_money( $sales ),
		'conversion' => $total_q ? round( $won / $total_q * 100 ) : 0,
		'by_stage'   => $by_stage,
		'pending'    => ( $by_stage['new'] ?? 0 ) + ( $by_stage['pricing'] ?? 0 ),
	);

	if ( 'admin' === $role ) {
		$out['by_source'] = $by_source;
		$out['branches']  = count( get_users( array( 'role' => 'osoul_branch', 'fields' => 'ID' ) ) );
		$out['reps']      = count( get_users( array( 'role' => 'osoul_rep', 'fields' => 'ID' ) ) );
		$out['partners']  = count( get_users( array( 'role' => 'osoul_partner', 'fields' => 'ID' ) ) );
		// Leaderboard: best reps by won deals then sales.
		usort( $rep_perf, static function ( $a, $b ) {
			return ( $b['won'] <=> $a['won'] ) ?: ( $b['sales'] <=> $a['sales'] );
		} );
		$out['leaderboard'] = array_map( static function ( $r ) {
			return array(
				'name'  => $r['name'],
				'total' => $r['total'],
				'won'   => $r['won'],
				'sales' => osoul_money( $r['sales'] ),
				'rate'  => $r['total'] ? round( $r['won'] / $r['total'] * 100 ) : 0,
			);
		}, array_slice( array_values( $rep_perf ), 0, 8 ) );
	}

	if ( 'branch' === $role ) {
		usort( $rep_perf, static function ( $a, $b ) { return $b['won'] <=> $a['won']; } );
		$out['leaderboard'] = array_map( static function ( $r ) {
			return array(
				'name'  => $r['name'],
				'total' => $r['total'],
				'won'   => $r['won'],
				'rate'  => $r['total'] ? round( $r['won'] / $r['total'] * 100 ) : 0,
			);
		}, array_values( $rep_perf ) );
		$out['reps'] = count( osoul_branch_rep_ids( $user->ID ) );
	}

	return rest_ensure_response( $out );
}

/* -------------------------------------------------------------------------
 *  Write endpoints
 * ---------------------------------------------------------------------- */

/** Append a timeline entry to a quote. */
function osoul_quote_log( $quote_id, $event ) {
	$log   = (array) get_post_meta( $quote_id, '_osoul_log', true );
	$log[] = array(
		'time'  => current_time( 'mysql' ),
		'event' => $event,
		'by'    => wp_get_current_user()->display_name,
	);
	update_post_meta( $quote_id, '_osoul_log', $log );
}

function osoul_rest_create_request( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	$cust  = (array) $req->get_param( 'customer' );
	$name  = sanitize_text_field( $cust['name'] ?? '' );
	$phone = sanitize_text_field( $cust['phone'] ?? '' );
	$email = sanitize_email( $cust['email'] ?? '' );
	$note  = sanitize_textarea_field( (string) $req->get_param( 'note' ) );

	if ( '' === $name || ( '' === $phone && '' === $email ) ) {
		return new WP_Error( 'osoul_missing', 'اسم العميل ورقم الجوال مطلوبان.', array( 'status' => 422 ) );
	}

	$raw   = (array) $req->get_param( 'items' );
	$items = osoul_sanitize_quote_items( $raw );
	if ( empty( $items ) ) {
		return new WP_Error( 'osoul_no_items', 'أضف منتجاً واحداً على الأقل.', array( 'status' => 422 ) );
	}

	// Enrich names + seed default unit price from the catalog (admin sees it).
	$catalog = osoul_catalog();
	foreach ( $items as &$it ) {
		if ( isset( $catalog[ $it['slug'] ] ) ) {
			$p                = $catalog[ $it['slug'] ];
			$it['name']       = $p['name'] ?? $it['name'];
			$it['name_en']    = $p['name_en'] ?? $it['name_en'];
			$it['unit_price'] = isset( $p['price'] ) ? (float) $p['price'] : 0.0;
		}
	}
	unset( $it );

	$quote_id = wp_insert_post( array(
		'post_type'   => 'osoul_quote',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s — %s', $name, $phone ?: $email ),
	), true );
	if ( is_wp_error( $quote_id ) ) {
		return new WP_Error( 'osoul_store_failed', 'تعذّر الحفظ.', array( 'status' => 500 ) );
	}

	update_post_meta( $quote_id, '_osoul_customer', array( 'name' => $name, 'phone' => $phone, 'email' => $email ) );
	update_post_meta( $quote_id, '_osoul_items', $items );
	update_post_meta( $quote_id, '_osoul_stage', 'new' );
	update_post_meta( $quote_id, '_osoul_token', osoul_quote_token() );
	update_post_meta( $quote_id, '_osoul_discount', 0 );
	update_post_meta( $quote_id, '_osoul_note', $note );
	update_post_meta( $quote_id, '_osoul_lang', 'ar' );
	osoul_quote_set_attribution( $quote_id, $user );
	osoul_quote_log( $quote_id, 'تم إنشاء الطلب (' . osoul_portal_role_label( $role ) . ')' );

	if ( function_exists( 'osoul_notify_new_quote' ) ) {
		osoul_notify_new_quote( $quote_id, $name, $phone, $email, $items );
	}

	return rest_ensure_response( array( 'ok' => true, 'id' => $quote_id ) );
}

function osoul_rest_price( WP_REST_Request $req ) {
	$id = (int) $req->get_param( 'id' );
	$post = get_post( $id );
	if ( ! $post || 'osoul_quote' !== $post->post_type ) {
		return new WP_Error( 'osoul_not_found', 'غير موجود.', array( 'status' => 404 ) );
	}
	// Privacy wall: a partner→customer quote is never priced/read by Osoul.
	if ( 'cust' === get_post_meta( $id, '_osoul_kind', true ) ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}

	$items  = (array) get_post_meta( $id, '_osoul_items', true );
	$prices = (array) $req->get_param( 'prices' );
	foreach ( $items as $i => &$it ) {
		if ( isset( $prices[ $i ] ) ) {
			$it['unit_price'] = max( 0, (float) $prices[ $i ] );
		}
	}
	unset( $it );
	update_post_meta( $id, '_osoul_items', $items );
	update_post_meta( $id, '_osoul_discount', max( 0, (float) $req->get_param( 'discount' ) ) );
	update_post_meta( $id, '_osoul_validity', max( 1, (int) $req->get_param( 'validity' ) ) );
	update_post_meta( $id, '_osoul_terms_ar', sanitize_textarea_field( (string) $req->get_param( 'terms_ar' ) ) );
	update_post_meta( $id, '_osoul_terms_en', sanitize_textarea_field( (string) $req->get_param( 'terms_en' ) ) );

	$issue = (bool) $req->get_param( 'issue' );
	if ( $issue ) {
		if ( ! get_post_meta( $id, '_osoul_number', true ) ) {
			update_post_meta( $id, '_osoul_number', osoul_next_quote_number() );
		}
		if ( ! get_post_meta( $id, '_osoul_token', true ) ) {
			update_post_meta( $id, '_osoul_token', osoul_quote_token() );
		}
		update_post_meta( $id, '_osoul_issued', current_time( 'mysql' ) );
		update_post_meta( $id, '_osoul_stage', 'sent' );
		osoul_quote_log( $id, 'تم إصدار العرض' );
	} else {
		$stage = get_post_meta( $id, '_osoul_stage', true ) ?: 'new';
		if ( 'new' === $stage ) {
			update_post_meta( $id, '_osoul_stage', 'pricing' );
		}
		osoul_quote_log( $id, 'تم حفظ الأسعار' );
	}

	return rest_ensure_response( osoul_quote_detail( get_post( $id ), 'admin' ) );
}

function osoul_rest_stage( WP_REST_Request $req ) {
	$id     = (int) $req->get_param( 'id' );
	$stage  = sanitize_key( (string) $req->get_param( 'stage' ) );
	$stages = osoul_quote_stages();
	$post   = get_post( $id );
	if ( ! $post || 'osoul_quote' !== $post->post_type || ! isset( $stages[ $stage ] )
		|| 'cust' === get_post_meta( $id, '_osoul_kind', true ) ) {
		return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 400 ) );
	}
	update_post_meta( $id, '_osoul_stage', $stage );
	osoul_quote_log( $id, 'تغيير الحالة إلى: ' . $stages[ $stage ]['ar'] );
	return rest_ensure_response( array( 'ok' => true, 'stage' => $stage ) );
}

/* -------------------------------------------------------------------------
 *  Account management (branches + reps)
 * ---------------------------------------------------------------------- */

function osoul_account_record( $user_id, $role ) {
	$u = get_user_by( 'id', $user_id );
	if ( ! $u ) { return null; }
	$rec = array(
		'id'      => $user_id,
		'name'    => $u->display_name,
		'email'   => $u->user_email,
		'phone'   => get_user_meta( $user_id, '_osoul_phone', true ),
		'active'  => osoul_portal_is_active( $user_id ),
		'pending' => (bool) get_user_meta( $user_id, '_osoul_invite', true ),
	);
	if ( 'branch' === $role ) {
		$rec['city']    = get_user_meta( $user_id, '_osoul_city', true );
		$rec['manager'] = get_user_meta( $user_id, '_osoul_manager', true );
		$rec['reps']    = count( osoul_branch_rep_ids( $user_id ) );
	} else {
		$bid           = osoul_rep_branch_id( $user_id );
		$rec['branch'] = $bid ? get_the_author_meta( 'display_name', $bid ) : 'مستقل';
	}
	return $rec;
}

function osoul_rest_reps( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	if ( 'admin' === $role ) {
		$ids = array_map( static function ( $u ) { return (int) $u->ID; },
			get_users( array( 'role' => 'osoul_rep', 'fields' => array( 'ID' ), 'orderby' => 'display_name', 'order' => 'ASC' ) ) );
	} else {
		$ids = osoul_branch_rep_ids( $user->ID );
	}
	$out = array();
	foreach ( $ids as $rid ) {
		$rec = osoul_account_record( $rid, 'rep' );
		if ( $rec ) { $out[] = $rec; }
	}
	return rest_ensure_response( array( 'reps' => $out ) );
}

function osoul_rest_create_rep( WP_REST_Request $req ) {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	$branch_id = ( 'branch' === $role ) ? $user->ID : (int) $req->get_param( 'branch_id' );

	$res = osoul_portal_create_account( array(
		'role'      => 'rep',
		'name'      => $req->get_param( 'name' ),
		'email'     => $req->get_param( 'email' ),
		'phone'     => $req->get_param( 'phone' ),
		'branch_id' => $branch_id,
	), $user->ID );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 422 ) );
	}
	return rest_ensure_response( array(
		'ok'         => true,
		'invite_url' => $res['invite_url'],
		'rep'        => osoul_account_record( $res['user_id'], 'rep' ),
	) );
}

function osoul_rest_branches( WP_REST_Request $req ) {
	$ids = array_map( static function ( $u ) { return (int) $u->ID; },
		get_users( array( 'role' => 'osoul_branch', 'fields' => array( 'ID' ), 'orderby' => 'display_name', 'order' => 'ASC' ) ) );
	$out = array();
	foreach ( $ids as $bid ) {
		$rec = osoul_account_record( $bid, 'branch' );
		if ( $rec ) { $out[] = $rec; }
	}
	return rest_ensure_response( array( 'branches' => $out ) );
}

function osoul_rest_create_branch( WP_REST_Request $req ) {
	$res = osoul_portal_create_account( array(
		'role'    => 'branch',
		'name'    => $req->get_param( 'name' ),
		'email'   => $req->get_param( 'email' ),
		'phone'   => $req->get_param( 'phone' ),
		'city'    => $req->get_param( 'city' ),
		'manager' => $req->get_param( 'manager' ),
	), get_current_user_id() );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 422 ) );
	}
	return rest_ensure_response( array(
		'ok'         => true,
		'invite_url' => $res['invite_url'],
		'branch'     => osoul_account_record( $res['user_id'], 'branch' ),
	) );
}

/** Suspend / re-activate a branch or rep account. */
function osoul_rest_account_toggle( WP_REST_Request $req ) {
	$target = (int) $req->get_param( 'id' );
	$err    = osoul_portal_guard_target( $target );
	if ( is_wp_error( $err ) ) { return $err; }

	$active = osoul_portal_is_active( $target ) ? 0 : 1;
	update_user_meta( $target, '_osoul_active', $active );
	return rest_ensure_response( array( 'ok' => true, 'active' => (bool) $active ) );
}

/** Issue a fresh invite link (e.g. the first one expired / was lost). */
function osoul_rest_account_reinvite( WP_REST_Request $req ) {
	$target = (int) $req->get_param( 'id' );
	$err    = osoul_portal_guard_target( $target );
	if ( is_wp_error( $err ) ) { return $err; }

	$token = osoul_portal_new_invite( $target );
	return rest_ensure_response( array( 'ok' => true, 'invite_url' => osoul_portal_invite_url( $token ) ) );
}

/**
 * Make sure the caller is allowed to manage the target account:
 *   admin  → any branch or rep
 *   branch → only reps that belong to it
 */
function osoul_portal_guard_target( $target_id ) {
	$role        = osoul_portal_role();
	$target_role = osoul_portal_role( $target_id );
	if ( ! $target_id || ! in_array( $target_role, array( 'branch', 'rep', 'partner' ), true ) ) {
		return new WP_Error( 'osoul_bad', 'حساب غير صالح.', array( 'status' => 400 ) );
	}
	if ( 'admin' === $role ) {
		return true;
	}
	if ( 'branch' === $role && 'rep' === $target_role
		&& osoul_rep_branch_id( $target_id ) === get_current_user_id() ) {
		return true;
	}
	return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
}
