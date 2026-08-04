<?php
/**
 * Employee webmail — REST API (namespace osoul/v1/mail/*).
 *
 * Every route is scoped to the *logged-in employee's own mailbox*. There is no
 * parameter for "whose mailbox" — the identity is always the current user, so
 * one employee can never read another's mail, and sales-portal roles (which are
 * not employees) are rejected outright. Cookie auth + the wp_rest nonce protect
 * every call, exactly like the sales portal.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Permission
 * ---------------------------------------------------------------------- */

/** The current user must be an active employee (webmail) account. */
function osoul_webmail_perm() {
	if ( ! is_user_logged_in() || ! osoul_is_employee() ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	if ( ! osoul_employee_is_active( get_current_user_id() ) ) {
		return new WP_Error( 'osoul_suspended', 'الحساب موقوف.', array( 'status' => 403 ) );
	}
	return true;
}

/** Never cache mailbox responses. */
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
	$route = is_object( $request ) ? ltrim( (string) $request->get_route(), '/' ) : '';
	if ( 0 === strpos( $route, 'osoul/v1/mail' ) ) { nocache_headers(); }
	return $served;
}, 10, 3 );

/* -------------------------------------------------------------------------
 *  Routes
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	$ns  = 'osoul/v1';
	$get = WP_REST_Server::READABLE;
	$post = WP_REST_Server::CREATABLE;
	$perm = 'osoul_webmail_perm';

	$routes = array(
		array( '/mail/bootstrap',  $get,  'osoul_rest_mail_bootstrap' ),
		array( '/mail/connect',    $post, 'osoul_rest_mail_connect' ),
		array( '/mail/disconnect', $post, 'osoul_rest_mail_disconnect' ),
		array( '/mail/folders',    $get,  'osoul_rest_mail_folders' ),
		array( '/mail/messages',   $get,  'osoul_rest_mail_messages' ),
		array( '/mail/message',    $get,  'osoul_rest_mail_message' ),
		array( '/mail/flag',       $post, 'osoul_rest_mail_flag' ),
		array( '/mail/delete',     $post, 'osoul_rest_mail_delete' ),
		array( '/mail/move',       $post, 'osoul_rest_mail_move' ),
		array( '/mail/batch',      $post, 'osoul_rest_mail_batch' ),
		array( '/mail/quota',      $get,  'osoul_rest_mail_quota' ),
		array( '/mail/folder-create', $post, 'osoul_rest_mail_folder_create' ),
		array( '/mail/send',       $post, 'osoul_rest_mail_send' ),
		array( '/mail/attachment', $get,  'osoul_rest_mail_attachment' ),
		array( '/mail/upload',     $post, 'osoul_rest_mail_upload' ),
		array( '/mail/avatar',     $post, 'osoul_rest_mail_avatar' ),
		array( '/mail/profile',    $post, 'osoul_rest_mail_profile' ),
		array( '/mail/contacts',   $get,  'osoul_rest_mail_contacts' ),
		array( '/mail/password',   $post, 'osoul_rest_mail_password' ),
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
 *  Handlers
 * ---------------------------------------------------------------------- */

/** Initial payload: identity + connection status (+ folders when connected). */
function osoul_rest_mail_bootstrap() {
	$uid = get_current_user_id();
	$cfg = osoul_mail_config( $uid );
	$out = array(
		'name'      => wp_get_current_user()->display_name,
		'connected' => osoul_mail_is_connected( $uid ),
		'email'     => $cfg['email'],
		'from_name' => $cfg['from_name'],
		'signature' => $cfg['signature'],
		'avatar'    => (string) get_user_meta( $uid, '_osoul_mail_avatar', true ),
		'ai'        => function_exists( 'osoul_ai_is_ready' ) && osoul_ai_is_ready(),
		'defaults'  => osoul_mail_server_defaults(),
		'imap_host' => $cfg['imap_host'],
		'imap_port' => $cfg['imap_port'],
		'smtp_host' => $cfg['smtp_host'],
		'smtp_port' => $cfg['smtp_port'],
		'folders'   => array(),
	);
	if ( $out['connected'] ) {
		$imap = osoul_mail_open( $uid );
		if ( is_wp_error( $imap ) ) {
			$out['connected']    = false;
			$out['conn_error']   = $imap->get_error_message();
		} else {
			$out['folders'] = osoul_mail_folder_payload( $imap->folders() );
			$imap->logout();
		}
	}
	return rest_ensure_response( $out );
}

/** Verify + persist mailbox credentials. */
function osoul_rest_mail_connect( WP_REST_Request $req ) {
	$uid   = get_current_user_id();
	$d     = osoul_mail_server_defaults();
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$pass  = (string) $req->get_param( 'password' );
	$from  = sanitize_text_field( (string) $req->get_param( 'from_name' ) );
	$ihost = sanitize_text_field( (string) ( $req->get_param( 'imap_host' ) ?: $d['imap_host'] ) );
	$iport = (int) ( $req->get_param( 'imap_port' ) ?: $d['imap_port'] );
	$shost = sanitize_text_field( (string) ( $req->get_param( 'smtp_host' ) ?: $d['smtp_host'] ) );
	$sport = (int) ( $req->get_param( 'smtp_port' ) ?: $d['smtp_port'] );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_bad_email', 'أدخل بريداً إلكترونياً صحيحاً.', array( 'status' => 422 ) );
	}
	if ( '' === $pass ) {
		// Allow updating from_name/signature without re-entering the password,
		// but a first-time connect requires it.
		if ( ! osoul_mail_is_connected( $uid ) ) {
			return new WP_Error( 'osoul_no_pass', 'أدخل كلمة مرور البريد.', array( 'status' => 422 ) );
		}
		$cfg  = osoul_mail_config( $uid, true );
		$pass = $cfg['password'];
	}

	$test = osoul_mail_test( $uid, $email, $pass, $ihost, $iport, $shost, $sport );
	if ( is_wp_error( $test ) ) {
		return new WP_Error( $test->get_error_code(), $test->get_error_message(), array( 'status' => 400 ) );
	}

	osoul_mail_save_config( $uid, array(
		'email'     => $email,
		'password'  => $pass,
		'from_name' => ( '' !== $from ) ? $from : null,
		'imap_host' => $ihost,
		'imap_port' => $iport,
		'smtp_host' => $shost,
		'smtp_port' => $sport,
	) );
	if ( '' !== $from ) {
		update_user_meta( $uid, '_osoul_mail_from', $from );
	}
	osoul_mail_set_connected( $uid, true );

	return rest_ensure_response( array( 'ok' => true ) );
}

function osoul_rest_mail_disconnect() {
	osoul_mail_forget( get_current_user_id() );
	return rest_ensure_response( array( 'ok' => true ) );
}

function osoul_rest_mail_folders() {
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$out = osoul_mail_folder_payload( $imap->folders() );
	$imap->logout();
	return rest_ensure_response( array( 'folders' => $out ) );
}

/** Shape folders for the client (drop the raw server name from display). */
function osoul_mail_folder_payload( $folders ) {
	$out = array();
	foreach ( $folders as $f ) {
		$out[] = array(
			'raw'     => $f['raw'],
			'display' => $f['display'],
			'special' => $f['special'],
			'unseen'  => (int) ( $f['unseen'] ?? 0 ),
			'total'   => (int) ( $f['total'] ?? 0 ),
		);
	}
	return $out;
}

function osoul_rest_mail_messages( WP_REST_Request $req ) {
	$folder = (string) ( $req->get_param( 'folder' ) ?: 'INBOX' );
	$page   = max( 0, (int) $req->get_param( 'page' ) );
	$search = (string) $req->get_param( 'search' );
	$filter = sanitize_key( (string) $req->get_param( 'filter' ) ); // '' | unread | read | starred
	$sort   = ( 'oldest' === $req->get_param( 'sort' ) ) ? 'oldest' : 'newest';
	$per    = (int) apply_filters( 'osoul_mail_page_size', 25 );

	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$res  = $imap->list_messages( $folder, $page, $per, $search, $filter, $sort );
	$imap->logout();

	foreach ( $res['messages'] as &$m ) {
		$m['date_fmt'] = osoul_mail_fmt_date( $m['ts'] );
	}
	unset( $m );

	return rest_ensure_response( array(
		'folder'   => $folder,
		'page'     => $page,
		'per'      => $per,
		'total'    => $res['total'],
		'messages' => $res['messages'],
	) );
}

function osoul_rest_mail_message( WP_REST_Request $req ) {
	$folder = (string) ( $req->get_param( 'folder' ) ?: 'INBOX' );
	$uid    = (int) $req->get_param( 'uid' );
	if ( ! $uid ) {
		return new WP_Error( 'osoul_bad', 'رسالة غير صالحة.', array( 'status' => 422 ) );
	}
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$msg = $imap->get_message( $folder, $uid );
	if ( null === $msg ) {
		$imap->logout();
		return new WP_Error( 'osoul_notfound', 'تعذّر جلب الرسالة.', array( 'status' => 404 ) );
	}
	// Mark as read.
	if ( empty( $msg['seen'] ) ) {
		$imap->set_flag( $folder, $uid, '\\Seen', true );
		$msg['seen'] = true;
	}
	$imap->logout();

	// Remember the sender as a contact.
	if ( ! empty( $msg['from']['email'] ) ) {
		osoul_mail_add_contacts( get_current_user_id(), array( $msg['from'] ) );
	}

	return rest_ensure_response( array(
		'uid'         => $msg['uid'],
		'subject'     => $msg['subject'],
		'from'        => $msg['from'],
		'to'          => $msg['to'],
		'cc'          => $msg['cc'],
		'reply_to'    => $msg['reply_to'],
		'date'        => $msg['date'],
		'date_fmt'    => osoul_mail_fmt_date( $msg['ts'] ),
		'message_id'  => $msg['message_id'],
		'references'  => trim( $msg['references'] . ' ' . $msg['message_id'] ),
		'html'        => $msg['html_safe'],
		'text'        => $msg['text'],
		'seen'        => $msg['seen'],
		'flagged'     => $msg['flagged'],
		'attachments' => $msg['attachments'],
	) );
}

function osoul_rest_mail_flag( WP_REST_Request $req ) {
	$folder = (string) $req->get_param( 'folder' );
	$uid    = (int) $req->get_param( 'uid' );
	$which  = sanitize_key( (string) $req->get_param( 'flag' ) ); // seen | flagged
	$on     = filter_var( $req->get_param( 'on' ), FILTER_VALIDATE_BOOLEAN );
	$map    = array( 'seen' => '\\Seen', 'flagged' => '\\Flagged' );
	if ( ! isset( $map[ $which ] ) || ! $uid ) {
		return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) );
	}
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$ok = $imap->set_flag( $folder, $uid, $map[ $which ], $on );
	$imap->logout();
	return rest_ensure_response( array( 'ok' => $ok ) );
}

function osoul_rest_mail_delete( WP_REST_Request $req ) {
	$folder = (string) $req->get_param( 'folder' );
	$uid    = (int) $req->get_param( 'uid' );
	if ( ! $uid ) { return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) ); }
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }

	$folders = $imap->folders();
	$trash   = '';
	$is_trash = false;
	foreach ( $folders as $f ) {
		if ( 'trash' === $f['special'] ) { $trash = $f['raw']; }
		if ( $f['raw'] === $folder && 'trash' === $f['special'] ) { $is_trash = true; }
	}
	if ( $is_trash || '' === $trash ) {
		$ok = $imap->delete( $folder, $uid );        // permanent
		$mode = 'purged';
	} else {
		$ok = $imap->move( $folder, $uid, $trash );  // to Trash
		$mode = 'trashed';
	}
	$imap->logout();
	return rest_ensure_response( array( 'ok' => $ok, 'mode' => $mode ) );
}

function osoul_rest_mail_move( WP_REST_Request $req ) {
	$folder  = (string) $req->get_param( 'folder' );
	$uid     = (int) $req->get_param( 'uid' );
	$special = sanitize_key( (string) $req->get_param( 'dest' ) );
	$dest_raw = (string) $req->get_param( 'dest_raw' );
	if ( ! $uid ) { return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) ); }
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$dest = $dest_raw;
	if ( '' === $dest && '' !== $special ) {
		foreach ( $imap->folders() as $f ) {
			if ( $f['special'] === $special ) { $dest = $f['raw']; break; }
		}
	}
	if ( '' === $dest ) { $imap->logout(); return new WP_Error( 'osoul_bad', 'وجهة غير صالحة.', array( 'status' => 422 ) ); }
	$ok = $imap->move( $folder, $uid, $dest );
	$imap->logout();
	return rest_ensure_response( array( 'ok' => $ok ) );
}

/**
 * Apply one action to many messages at once (bulk toolbar).
 * action: read | unread | star | unstar | delete | move (+ dest special)
 */
function osoul_rest_mail_batch( WP_REST_Request $req ) {
	$folder = (string) $req->get_param( 'folder' );
	$action = sanitize_key( (string) $req->get_param( 'action' ) );
	$uids   = array_values( array_filter( array_map( 'intval', (array) $req->get_param( 'uids' ) ) ) );
	if ( ! $uids ) { return new WP_Error( 'osoul_bad', 'لم تُحدَّد رسائل.', array( 'status' => 422 ) ); }

	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }

	$done = 0;
	if ( 'read' === $action || 'unread' === $action ) {
		$done = $imap->set_flag_many( $folder, $uids, '\\Seen', ( 'read' === $action ) ) ? count( $uids ) : 0;
	} elseif ( 'star' === $action || 'unstar' === $action ) {
		$done = $imap->set_flag_many( $folder, $uids, '\\Flagged', ( 'star' === $action ) ) ? count( $uids ) : 0;
	} elseif ( 'delete' === $action || 'move' === $action ) {
		// Resolve destination (Trash for delete, or requested special).
		$special = ( 'delete' === $action ) ? 'trash' : sanitize_key( (string) $req->get_param( 'dest' ) );
		$dest    = '';
		$is_trash_src = false;
		foreach ( $imap->folders() as $f ) {
			if ( $f['special'] === $special ) { $dest = $f['raw']; }
			if ( $f['raw'] === $folder && 'trash' === $f['special'] ) { $is_trash_src = true; }
		}
		foreach ( $uids as $u ) {
			if ( 'delete' === $action && ( $is_trash_src || '' === $dest ) ) {
				$done += $imap->delete( $folder, $u ) ? 1 : 0;
			} elseif ( '' !== $dest ) {
				$done += $imap->move( $folder, $u, $dest ) ? 1 : 0;
			}
		}
	} else {
		$imap->logout();
		return new WP_Error( 'osoul_bad', 'إجراء غير معروف.', array( 'status' => 422 ) );
	}
	$imap->logout();
	return rest_ensure_response( array( 'ok' => true, 'done' => $done ) );
}

/** Mailbox storage usage for the sidebar meter. */
function osoul_rest_mail_quota() {
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$q = $imap->quota();
	$imap->logout();
	return rest_ensure_response( $q );
}

/** Create a custom folder (the sidebar "+"). */
function osoul_rest_mail_folder_create( WP_REST_Request $req ) {
	$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$name = trim( preg_replace( '/[\/"\\\\\r\n]+/', '', $name ) );
	if ( '' === $name ) { return new WP_Error( 'osoul_bad', 'أدخل اسم المجلد.', array( 'status' => 422 ) ); }
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	// Dovecot mailboxes live under the INBOX namespace.
	$ok = $imap->create_folder( 'INBOX.' . $name ) || $imap->create_folder( $name );
	$imap->logout();
	return $ok ? rest_ensure_response( array( 'ok' => true ) ) : new WP_Error( 'osoul_fail', 'تعذّر إنشاء المجلد.', array( 'status' => 400 ) );
}

function osoul_rest_mail_send( WP_REST_Request $req ) {
	$uid = get_current_user_id();

	$to   = osoul_mail_split_addrs( $req->get_param( 'to' ) );
	$cc   = osoul_mail_split_addrs( $req->get_param( 'cc' ) );
	$bcc  = osoul_mail_split_addrs( $req->get_param( 'bcc' ) );
	$subj = sanitize_text_field( (string) $req->get_param( 'subject' ) );
	$body = (string) $req->get_param( 'body_html' );
	$body = wp_kses_post( $body );
	$draft = filter_var( $req->get_param( 'draft' ), FILTER_VALIDATE_BOOLEAN );

	if ( ! $draft && ! $to && ! $cc && ! $bcc ) {
		return new WP_Error( 'osoul_no_rcpt', 'أضف مستلماً واحداً على الأقل.', array( 'status' => 422 ) );
	}

	// Resolve compose attachments (temp tokens → file paths).
	$att_ids     = (array) $req->get_param( 'attachments' );
	$attachments = array();
	$tokens_used = array();
	foreach ( $att_ids as $tok ) {
		$meta = osoul_mail_tmp_get( $uid, (string) $tok );
		if ( $meta && file_exists( $meta['path'] ) ) {
			$attachments[]  = array( 'path' => $meta['path'], 'name' => $meta['name'] );
			$tokens_used[]  = (string) $tok;
		}
	}

	$args = array(
		'to'          => $to,
		'cc'          => $cc,
		'bcc'         => $bcc,
		'subject'     => $subj,
		'body_html'   => $body,
		'attachments' => $attachments,
		'in_reply_to' => sanitize_text_field( (string) $req->get_param( 'in_reply_to' ) ),
		'references'  => sanitize_text_field( (string) $req->get_param( 'references' ) ),
	);

	if ( $draft ) {
		$res = osoul_mail_save_draft( $uid, $args );
	} else {
		$res = osoul_mail_send( $uid, $args );
	}

	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
	}

	// Clean up temp attachments + record contacts.
	foreach ( $tokens_used as $tok ) { osoul_mail_tmp_delete( $uid, $tok ); }
	if ( ! $draft ) {
		osoul_mail_add_contacts( $uid, array_map(
			function ( $e ) { return array( 'email' => $e, 'name' => '' ); },
			array_merge( $to, $cc, $bcc )
		) );
	}

	return rest_ensure_response( array_merge( array( 'ok' => true, 'draft' => $draft ), (array) $res ) );
}

/** Download an attachment as base64 (the browser turns it into a file). */
function osoul_rest_mail_attachment( WP_REST_Request $req ) {
	$folder = (string) ( $req->get_param( 'folder' ) ?: 'INBOX' );
	$uid    = (int) $req->get_param( 'uid' );
	$index  = (int) $req->get_param( 'index' );
	if ( ! $uid ) { return new WP_Error( 'osoul_bad', 'طلب غير صالح.', array( 'status' => 422 ) ); }
	$imap = osoul_mail_open( get_current_user_id() );
	if ( is_wp_error( $imap ) ) { return $imap; }
	if ( ! $imap->select( $folder ) ) { $imap->logout(); return new WP_Error( 'osoul_bad', 'مجلد غير صالح.', array( 'status' => 422 ) ); }
	$raw = $imap->fetch_raw( $uid );
	$imap->logout();
	if ( null === $raw ) { return new WP_Error( 'osoul_notfound', 'تعذّر جلب المرفق.', array( 'status' => 404 ) ); }
	$att = Osoul_MIME::attachment( $raw, $index );
	if ( null === $att ) { return new WP_Error( 'osoul_notfound', 'المرفق غير موجود.', array( 'status' => 404 ) ); }
	return rest_ensure_response( array(
		'name' => $att['name'],
		'mime' => $att['mime'],
		'b64'  => base64_encode( $att['content'] ),
	) );
}

/** Store a compose attachment in a private temp dir; return a token. */
function osoul_rest_mail_upload( WP_REST_Request $req ) {
	$uid = get_current_user_id();
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
		return new WP_Error( 'osoul_no_file', 'لا يوجد ملف.', array( 'status' => 422 ) );
	}
	$file = $_FILES['file'];
	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'osoul_upload', 'فشل رفع الملف.', array( 'status' => 422 ) );
	}
	$max = (int) apply_filters( 'osoul_mail_attach_max', 25 * MB_IN_BYTES );
	if ( ( $file['size'] ?? 0 ) > $max ) {
		return new WP_Error( 'osoul_big', 'حجم المرفق كبير (الحد ' . round( $max / MB_IN_BYTES ) . ' ميجابايت).', array( 'status' => 422 ) );
	}
	$name = sanitize_file_name( (string) $file['name'] );
	if ( '' === $name ) { $name = 'attachment'; }
	// Block executable/script types outright.
	if ( preg_match( '/\.(php\d?|phtml|phar|exe|com|bat|cmd|sh|cgi|pl|jsp|asp|aspx)$/i', $name ) ) {
		return new WP_Error( 'osoul_bad_type', 'نوع الملف غير مسموح.', array( 'status' => 422 ) );
	}
	if ( ! is_uploaded_file( $file['tmp_name'] ) && ! defined( 'OSOUL_TESTING' ) ) {
		return new WP_Error( 'osoul_upload', 'رفع غير صالح.', array( 'status' => 422 ) );
	}
	$dir   = osoul_mail_tmp_dir();
	$token = wp_generate_password( 20, false );
	$path  = trailingslashit( $dir ) . $token . '.bin';
	if ( ! @move_uploaded_file( $file['tmp_name'], $path ) && ! @rename( $file['tmp_name'], $path ) ) {
		return new WP_Error( 'osoul_upload', 'تعذّر حفظ الملف.', array( 'status' => 500 ) );
	}
	$type = wp_check_filetype( $name );
	osoul_mail_tmp_set( $uid, $token, array(
		'path' => $path,
		'name' => $name,
		'mime' => $type['type'] ?: 'application/octet-stream',
		'size' => (int) ( $file['size'] ?? filesize( $path ) ),
	) );
	return rest_ensure_response( array(
		'ok'    => true,
		'token' => $token,
		'name'  => $name,
		'size'  => (int) ( $file['size'] ?? filesize( $path ) ),
	) );
}

function osoul_rest_mail_profile( WP_REST_Request $req ) {
	$uid  = get_current_user_id();
	$from = sanitize_text_field( (string) $req->get_param( 'from_name' ) );
	$sig  = wp_kses_post( (string) $req->get_param( 'signature' ) );
	if ( '' !== $from ) {
		update_user_meta( $uid, '_osoul_mail_from', $from );
	}
	update_user_meta( $uid, '_osoul_mail_sig', $sig );

	// Profile photo: accept a URL we host (from /mail/avatar). '' clears it.
	if ( null !== $req->get_param( 'avatar' ) ) {
		$av = esc_url_raw( (string) $req->get_param( 'avatar' ) );
		if ( '' === $av || 0 === strpos( $av, home_url() ) || 0 === strpos( $av, content_url() ) ) {
			update_user_meta( $uid, '_osoul_mail_avatar', $av );
		}
	}
	$avatar = (string) get_user_meta( $uid, '_osoul_mail_avatar', true );

	return rest_ensure_response( array( 'ok' => true, 'from_name' => $from, 'signature' => $sig, 'avatar' => $avatar ) );
}

/** Upload a profile photo to the media library and return its URL. */
function osoul_rest_mail_avatar() {
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
		return new WP_Error( 'osoul_no_file', 'لا يوجد ملف.', array( 'status' => 422 ) );
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	$file = $_FILES['file'];
	$type = wp_check_filetype( sanitize_file_name( (string) $file['name'] ) );
	if ( ! preg_match( '#^image/#', (string) ( $type['type'] ?? '' ) ) && ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', (string) $file['name'] ) ) {
		return new WP_Error( 'osoul_bad_type', 'الملف يجب أن يكون صورة.', array( 'status' => 422 ) );
	}
	if ( ( $file['size'] ?? 0 ) > 6 * MB_IN_BYTES ) {
		return new WP_Error( 'osoul_big', 'حجم الصورة كبير (الحد 6 ميجابايت).', array( 'status' => 422 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$att_id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $att_id ) ) {
		return new WP_Error( 'osoul_upload_fail', $att_id->get_error_message(), array( 'status' => 500 ) );
	}
	$url = wp_get_attachment_url( $att_id );
	update_user_meta( get_current_user_id(), '_osoul_mail_avatar', esc_url_raw( $url ) );
	return rest_ensure_response( array( 'ok' => true, 'id' => $att_id, 'url' => $url ) );
}

function osoul_rest_mail_contacts() {
	$list = get_user_meta( get_current_user_id(), '_osoul_mail_contacts', true );
	$out  = array();
	if ( is_array( $list ) ) {
		foreach ( $list as $email => $name ) {
			$out[] = array( 'email' => $email, 'name' => $name );
		}
	}
	return rest_ensure_response( array( 'contacts' => $out ) );
}

/**
 * Change the employee's own dashboard (login) password.
 *
 * This is the WordPress account password used to sign in to /dashboard — not
 * the Hostinger mailbox password. We verify the current password first, require
 * a reasonable new one, then re-issue the auth cookie so the session survives
 * the change (WordPress invalidates the old cookie on a password reset).
 */
function osoul_rest_mail_password( WP_REST_Request $req ) {
	$uid  = get_current_user_id();
	$cur  = (string) $req->get_param( 'current' );
	$new  = (string) $req->get_param( 'new' );
	$user = get_user_by( 'id', $uid );

	if ( ! $user ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	if ( '' === $cur || ! wp_check_password( $cur, $user->user_pass, $uid ) ) {
		return new WP_Error( 'osoul_badpass', 'كلمة المرور الحالية غير صحيحة.', array( 'status' => 403 ) );
	}
	if ( strlen( $new ) < 8 ) {
		return new WP_Error( 'osoul_weakpass', 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.', array( 'status' => 422 ) );
	}
	if ( $new === $cur ) {
		return new WP_Error( 'osoul_samepass', 'كلمة المرور الجديدة مطابقة للحالية.', array( 'status' => 422 ) );
	}

	wp_set_password( $new, $uid );
	// Keep the current session alive (wp_set_password logs the user out).
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );

	return rest_ensure_response( array( 'ok' => true ) );
}

/* -------------------------------------------------------------------------
 *  Small helpers
 * ---------------------------------------------------------------------- */

/** Normalise a comma/newline-separated recipient string (or array) → emails[]. */
function osoul_mail_split_addrs( $raw ) {
	if ( is_array( $raw ) ) {
		$parts = $raw;
	} else {
		$parts = preg_split( '/[,;\n]+/', (string) $raw );
	}
	$out = array();
	foreach ( (array) $parts as $p ) {
		$p = trim( (string) $p );
		if ( '' === $p ) { continue; }
		if ( preg_match( '/<([^>]+)>/', $p, $m ) ) { $p = trim( $m[1] ); }
		$p = sanitize_email( $p );
		if ( is_email( $p ) ) { $out[] = $p; }
	}
	return array_values( array_unique( $out ) );
}

/**
 * Format a message timestamp in the correct local time.
 *
 * $ts is a true UTC epoch (parsed from the message's Date/INTERNALDATE, which
 * carry their own offset). We convert it into the site's timezone; if WordPress
 * is left on UTC we fall back to the company's zone so times don't read 3h off.
 */
function osoul_mail_fmt_date( $ts ) {
	$ts = (int) $ts;
	if ( $ts <= 0 ) { return ''; }
	$tz = wp_timezone();
	if ( in_array( $tz->getName(), array( 'UTC', '+00:00', 'Z' ), true ) ) {
		$tz = new DateTimeZone( (string) apply_filters( 'osoul_mail_timezone', 'Asia/Riyadh' ) );
	}
	try {
		$dt = new DateTime( '@' . $ts );      // UTC
		$dt->setTimezone( $tz );
		$now = new DateTime( 'now', $tz );
	} catch ( Exception $e ) {
		return date_i18n( 'Y-m-d H:i', $ts );
	}
	$today = ( $dt->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) );
	return $dt->format( $today ? 'H:i' : 'Y-m-d H:i' );
}

/** Contacts store (email → best display name). */
function osoul_mail_add_contacts( $user_id, $addresses ) {
	$list = get_user_meta( $user_id, '_osoul_mail_contacts', true );
	if ( ! is_array( $list ) ) { $list = array(); }
	foreach ( (array) $addresses as $a ) {
		$e = strtolower( trim( (string) ( $a['email'] ?? '' ) ) );
		if ( '' === $e || ! is_email( $e ) ) { continue; }
		$n = trim( (string) ( $a['name'] ?? '' ) );
		if ( ! isset( $list[ $e ] ) || ( '' !== $n && strlen( $n ) > strlen( (string) $list[ $e ] ) ) ) {
			$list[ $e ] = ( '' !== $n ) ? $n : $e;
		}
	}
	if ( count( $list ) > 800 ) { $list = array_slice( $list, -800, null, true ); }
	update_user_meta( $user_id, '_osoul_mail_contacts', $list );
}

/* ---- compose-attachment temp store (private, auto-expiring) ---- */

function osoul_mail_tmp_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'osoul-mail-tmp';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.html', '' );
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}
function osoul_mail_tmp_key( $user_id, $token ) {
	return 'osoul_mailatt_' . (int) $user_id . '_' . preg_replace( '/[^A-Za-z0-9]/', '', $token );
}
function osoul_mail_tmp_set( $user_id, $token, $meta ) {
	set_transient( osoul_mail_tmp_key( $user_id, $token ), $meta, 3 * HOUR_IN_SECONDS );
}
function osoul_mail_tmp_get( $user_id, $token ) {
	$m = get_transient( osoul_mail_tmp_key( $user_id, $token ) );
	return is_array( $m ) ? $m : null;
}
function osoul_mail_tmp_delete( $user_id, $token ) {
	$m = osoul_mail_tmp_get( $user_id, $token );
	if ( $m && ! empty( $m['path'] ) && file_exists( $m['path'] ) ) { @unlink( $m['path'] ); }
	delete_transient( osoul_mail_tmp_key( $user_id, $token ) );
}

/** Sweep stray temp attachments older than a day (safety net). */
add_action( 'osoul_mail_cleanup', 'osoul_mail_tmp_sweep' );
if ( ! wp_next_scheduled( 'osoul_mail_cleanup' ) && function_exists( 'wp_schedule_event' ) ) {
	add_action( 'init', function () {
		if ( ! wp_next_scheduled( 'osoul_mail_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'osoul_mail_cleanup' );
		}
	}, 20 );
}
function osoul_mail_tmp_sweep() {
	$dir = osoul_mail_tmp_dir();
	foreach ( (array) glob( trailingslashit( $dir ) . '*.bin' ) as $f ) {
		if ( is_file( $f ) && ( time() - filemtime( $f ) ) > DAY_IN_SECONDS ) { @unlink( $f ); }
	}
}

/**
 * Save a draft: build the MIME without sending, then IMAP-APPEND to Drafts.
 *
 * @return array|WP_Error
 */
function osoul_mail_save_draft( $user_id, $args ) {
	$cfg = osoul_mail_config( $user_id, true );
	if ( '' === $cfg['email'] ) {
		return new WP_Error( 'osoul_mail_nocreds', 'لم يتم ربط البريد بعد.' );
	}
	try {
		$mail = osoul_mail_make_phpmailer( $cfg['email'], $cfg['password'], $cfg['smtp_host'], $cfg['smtp_port'] );
		$mail->setFrom( $cfg['email'], $cfg['from_name'] ?: $cfg['email'] );
		foreach ( (array) $args['to'] as $a )  { if ( is_email( $a ) ) { $mail->addAddress( $a ); } }
		foreach ( (array) $args['cc'] as $a )  { if ( is_email( $a ) ) { $mail->addCC( $a ); } }
		foreach ( (array) $args['bcc'] as $a ) { if ( is_email( $a ) ) { $mail->addBCC( $a ); } }
		$mail->Subject = (string) $args['subject'];
		$mail->isHTML( true );
		$mail->Body    = (string) $args['body_html'];
		$mail->AltBody = trim( wp_strip_all_tags( (string) $args['body_html'] ) );
		foreach ( (array) $args['attachments'] as $att ) {
			if ( ! empty( $att['path'] ) && file_exists( $att['path'] ) ) {
				$mail->addAttachment( $att['path'], (string) ( $att['name'] ?? '' ) );
			}
		}
		$mail->preSend();
		$raw = $mail->getSentMIMEMessage();
	} catch ( Exception $e ) {
		return new WP_Error( 'osoul_draft_fail', 'تعذّر حفظ المسودة: ' . $e->getMessage() );
	}
	$imap = osoul_mail_open( $user_id );
	if ( is_wp_error( $imap ) ) { return $imap; }
	$filed = osoul_mail_file_copy( $imap, 'drafts', $raw, '\\Draft \\Seen' );
	$imap->logout();
	return ( '' !== $filed ) ? array( 'ok' => true ) : new WP_Error( 'osoul_draft_fail', 'تعذّر حفظ المسودة في مجلد المسودات.' );
}
