<?php
/**
 * Employee webmail — REST extensions for the new client UI.
 *
 * The rebuilt single-page app (assets/js/webmail.js) adds a few screens the
 * original REST surface didn't cover: a saved-clients directory, manual contact
 * entry, mailbox insights (stats), category labels backed by IMAP keywords, and
 * a one-tap snooze. Those endpoints live here so the core mailbox API
 * (webmail-rest.php) stays focused on the raw IMAP plumbing.
 *
 * Everything reuses the same permission callback (osoul_webmail_perm) and the
 * same Osoul_IMAP engine already loaded by mail-engine.php.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Shared label vocabulary
 * ---------------------------------------------------------------------- */

/** The five category slugs mirrored in the client's LABEL_DEFS. */
function osoul_mail_label_slugs() {
	return array( 'projects', 'procurement', 'quality', 'hr', 'finance' );
}

/* -------------------------------------------------------------------------
 *  Routes
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	$ns   = 'osoul/v1';
	$get  = WP_REST_Server::READABLE;
	$post = WP_REST_Server::CREATABLE;
	$perm = 'osoul_webmail_perm';

	$routes = array(
		array( '/mail/clients',       $get,  'osoul_rest_mail_clients' ),
		array( '/mail/client-save',   $post, 'osoul_rest_mail_client_save' ),
		array( '/mail/client-remove', $post, 'osoul_rest_mail_client_remove' ),
		array( '/mail/contact-add',   $post, 'osoul_rest_mail_contact_add' ),
		array( '/mail/stats',         $get,  'osoul_rest_mail_stats' ),
		array( '/mail/labels',        $get,  'osoul_rest_mail_labels' ),
		array( '/mail/label',         $post, 'osoul_rest_mail_label' ),
		array( '/mail/snooze',        $post, 'osoul_rest_mail_snooze' ),
	);
	foreach ( $routes as $r ) {
		register_rest_route( $ns, $r[0], array(
			'methods'             => $r[1],
			'callback'            => $r[2],
			'permission_callback' => $perm,
		) );
	}
} );

/* -------------------------------------------------------------------------
 *  Clients directory (saved from incoming mail)
 * ---------------------------------------------------------------------- */

/** Read the saved-clients map for a user as a clean list. */
function osoul_mail_clients_get( $uid ) {
	$list = get_user_meta( $uid, '_osoul_mail_clients', true );
	return is_array( $list ) ? $list : array();
}

/** GET /mail/clients → { clients: [ {name,email,added,msgs} ] } (newest first). */
function osoul_rest_mail_clients() {
	$uid  = get_current_user_id();
	$list = osoul_mail_clients_get( $uid );
	$out  = array();
	foreach ( $list as $email => $rec ) {
		if ( ! is_array( $rec ) ) { $rec = array( 'name' => (string) $rec ); }
		$out[] = array(
			'email' => (string) $email,
			'name'  => (string) ( $rec['name'] ?? $email ),
			'added' => (string) ( $rec['added'] ?? '' ),
			'msgs'  => (int) ( $rec['msgs'] ?? 1 ),
			'ts'    => (int) ( $rec['ts'] ?? 0 ),
		);
	}
	usort( $out, function ( $a, $b ) { return $b['ts'] <=> $a['ts']; } );
	return rest_ensure_response( array( 'clients' => $out ) );
}

/** POST /mail/client-save { email, name } → save a sender as a client. */
function osoul_rest_mail_client_save( WP_REST_Request $req ) {
	$uid   = get_current_user_id();
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_bad', 'بريد غير صالح.', array( 'status' => 422 ) );
	}
	if ( '' === $name ) { $name = $email; }

	$list = osoul_mail_clients_get( $uid );
	$key  = strtolower( $email );

	// Best-effort: count how many messages this client has in the inbox.
	$msgs = 1;
	$imap = osoul_mail_open( $uid );
	if ( ! is_wp_error( $imap ) ) {
		$inbox = osoul_mail_inbox_folder( $imap );
		if ( '' !== $inbox && $imap->select( $inbox ) ) {
			$found = $imap->search_uids( '', 'FROM ' . osoul_mail_imap_quote_str( $email ) );
			if ( is_array( $found ) && count( $found ) > 0 ) { $msgs = count( $found ); }
		}
		$imap->logout();
	}

	$existing_added = ( isset( $list[ $key ]['added'] ) ) ? (string) $list[ $key ]['added'] : '';
	$list[ $key ] = array(
		'name'  => $name,
		'added' => '' !== $existing_added ? $existing_added : date_i18n( 'Y-m-d' ),
		'msgs'  => $msgs,
		'ts'    => isset( $list[ $key ]['ts'] ) ? (int) $list[ $key ]['ts'] : time(),
	);
	update_user_meta( $uid, '_osoul_mail_clients', $list );

	return rest_ensure_response( array( 'ok' => true ) );
}

/** POST /mail/client-remove { email } → drop a saved client. */
function osoul_rest_mail_client_remove( WP_REST_Request $req ) {
	$uid   = get_current_user_id();
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$list  = osoul_mail_clients_get( $uid );
	$key   = strtolower( $email );
	if ( isset( $list[ $key ] ) ) {
		unset( $list[ $key ] );
		update_user_meta( $uid, '_osoul_mail_clients', $list );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/* -------------------------------------------------------------------------
 *  Manual contact entry
 * ---------------------------------------------------------------------- */

/** POST /mail/contact-add { name, role, email } → add an employee contact. */
function osoul_rest_mail_contact_add( WP_REST_Request $req ) {
	$uid   = get_current_user_id();
	$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$role  = sanitize_text_field( (string) $req->get_param( 'role' ) );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( '' === $name ) {
		return new WP_Error( 'osoul_bad', 'أدخل الاسم.', array( 'status' => 422 ) );
	}
	if ( '' !== (string) $req->get_param( 'email' ) && ! is_email( $email ) ) {
		return new WP_Error( 'osoul_bad', 'بريد غير صالح.', array( 'status' => 422 ) );
	}

	// Name lives in the shared contacts map (email => name); a manual entry with
	// no address is keyed by a synthetic handle so it still shows up.
	$key  = is_email( $email ) ? $email : ( 'name:' . strtolower( $name ) );
	$list = get_user_meta( $uid, '_osoul_mail_contacts', true );
	if ( ! is_array( $list ) ) { $list = array(); }
	$list[ $key ] = $name;
	update_user_meta( $uid, '_osoul_mail_contacts', $list );

	// Roles live in a parallel map so we don't disturb the existing name map.
	if ( '' !== $role ) {
		$roles = get_user_meta( $uid, '_osoul_mail_contact_roles', true );
		if ( ! is_array( $roles ) ) { $roles = array(); }
		$roles[ $key ] = $role;
		update_user_meta( $uid, '_osoul_mail_contact_roles', $roles );
	}

	return rest_ensure_response( array( 'ok' => true ) );
}

/* -------------------------------------------------------------------------
 *  Mailbox insights (stats screen)
 * ---------------------------------------------------------------------- */

/** GET /mail/stats → { received, sent, unread, awaiting, folders:[{name,count}] }. */
function osoul_rest_mail_stats() {
	$uid  = get_current_user_id();
	$imap = osoul_mail_open( $uid );
	if ( is_wp_error( $imap ) ) { return $imap; }

	$folders  = $imap->folders();
	$lang     = function_exists( 'osoul_portal_lang' ) ? osoul_portal_lang() : 'ar';
	$en       = ( 'en' === $lang );
	$received = 0;
	$sent     = 0;
	$unread   = 0;
	$inbox    = '';
	$bars     = array();

	$names = $en
		? array( 'inbox' => 'Inbox', 'sent' => 'Sent', 'drafts' => 'Drafts', 'junk' => 'Spam', 'trash' => 'Trash', 'archive' => 'Archive' )
		: array( 'inbox' => 'الوارد', 'sent' => 'المرسل', 'drafts' => 'المسودات', 'junk' => 'المزعج', 'trash' => 'المهملات', 'archive' => 'المؤرشفة' );

	foreach ( $folders as $f ) {
		$total = (int) ( $f['total'] ?? 0 );
		if ( 'inbox' === $f['special'] ) { $received = $total; $unread = (int) ( $f['unseen'] ?? 0 ); $inbox = $f['raw']; }
		if ( 'sent' === $f['special'] )  { $sent = $total; }
		$label  = isset( $names[ $f['special'] ] ) ? $names[ $f['special'] ] : $f['display'];
		$bars[] = array( 'name' => $label, 'count' => $total, 'special' => $f['special'] );
	}

	// Awaiting reply = unanswered messages still in the inbox.
	$awaiting = 0;
	if ( '' !== $inbox && $imap->select( $inbox ) ) {
		$un = $imap->search_uids( '', 'UNANSWERED' );
		$awaiting = is_array( $un ) ? count( $un ) : 0;
	}
	$imap->logout();

	// Show the busiest folders first.
	usort( $bars, function ( $a, $b ) { return $b['count'] <=> $a['count']; } );

	return rest_ensure_response( array(
		'received' => $received,
		'sent'     => $sent,
		'unread'   => $unread,
		'awaiting' => $awaiting,
		'folders'  => array_values( $bars ),
	) );
}

/* -------------------------------------------------------------------------
 *  Category labels (IMAP keywords)
 * ---------------------------------------------------------------------- */

/** GET /mail/labels → { counts: { slug: n } } counted in the inbox. */
function osoul_rest_mail_labels() {
	$uid  = get_current_user_id();
	$imap = osoul_mail_open( $uid );
	if ( is_wp_error( $imap ) ) { return $imap; }

	$counts = array();
	$inbox  = osoul_mail_inbox_folder( $imap );
	if ( '' !== $inbox && $imap->select( $inbox ) ) {
		foreach ( osoul_mail_label_slugs() as $slug ) {
			$found = $imap->search_uids( '', 'KEYWORD ' . osoul_mail_label_keyword( $slug ) );
			$counts[ $slug ] = is_array( $found ) ? count( $found ) : 0;
		}
	}
	$imap->logout();

	return rest_ensure_response( array( 'counts' => $counts ) );
}

/** POST /mail/label { folder, uid, label } → set (or clear) a message's category. */
function osoul_rest_mail_label( WP_REST_Request $req ) {
	$folder = (string) $req->get_param( 'folder' );
	$uid    = (int) $req->get_param( 'uid' );
	$slug   = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $req->get_param( 'label' ) ) );
	if ( ! $uid || '' === $folder ) {
		return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) );
	}
	if ( '' !== $slug && ! in_array( $slug, osoul_mail_label_slugs(), true ) ) {
		return new WP_Error( 'osoul_bad', 'تصنيف غير معروف.', array( 'status' => 422 ) );
	}

	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }

	// One category per message: clear every known keyword, then set the chosen one.
	$all = array();
	foreach ( osoul_mail_label_slugs() as $s ) { $all[] = osoul_mail_label_keyword( $s ); }
	$imap->set_flag( $folder, $uid, implode( ' ', $all ), false );
	$ok = true;
	if ( '' !== $slug ) {
		$ok = $imap->set_flag( $folder, $uid, osoul_mail_label_keyword( $slug ), true );
	}
	$imap->logout();

	return rest_ensure_response( array( 'ok' => $ok, 'label' => $slug ) );
}

/* -------------------------------------------------------------------------
 *  Snooze (move to a Snoozed folder)
 * ---------------------------------------------------------------------- */

/** POST /mail/snooze { folder, uid } → move a message to the Snoozed folder. */
function osoul_rest_mail_snooze( WP_REST_Request $req ) {
	$folder = (string) $req->get_param( 'folder' );
	$uid    = (int) $req->get_param( 'uid' );
	if ( ! $uid || '' === $folder ) {
		return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) );
	}
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }

	// Resolve (or create) a Snoozed folder.
	$dest = '';
	foreach ( $imap->folders() as $f ) {
		if ( 'snoozed' === $f['special'] || preg_match( '/snooz/i', $f['raw'] ) ) { $dest = $f['raw']; break; }
	}
	if ( '' === $dest ) {
		if ( $imap->create_folder( 'INBOX.Snoozed' ) || $imap->create_folder( 'Snoozed' ) ) {
			foreach ( $imap->folders() as $f ) {
				if ( 'INBOX.Snoozed' === $f['raw'] || 'Snoozed' === $f['raw'] ) { $dest = $f['raw']; break; }
			}
			if ( '' === $dest ) { $dest = 'INBOX.Snoozed'; }
		}
	}
	if ( '' === $dest ) { $imap->logout(); return new WP_Error( 'osoul_bad', 'تعذّر التأجيل.', array( 'status' => 422 ) ); }

	$ok = $imap->move( $folder, $uid, $dest );
	$imap->logout();
	return rest_ensure_response( array( 'ok' => $ok ) );
}

/* -------------------------------------------------------------------------
 *  Small helpers
 * ---------------------------------------------------------------------- */

/** Resolve the raw INBOX folder name (special 'inbox', else the literal INBOX). */
function osoul_mail_inbox_folder( $imap ) {
	foreach ( $imap->folders() as $f ) {
		if ( 'inbox' === $f['special'] ) { return $f['raw']; }
	}
	return 'INBOX';
}

/** Quote a string as an IMAP quoted-string for use in a raw SEARCH argument. */
function osoul_mail_imap_quote_str( $s ) {
	return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $s ) . '"';
}
