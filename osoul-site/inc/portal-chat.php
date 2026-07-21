<?php
/**
 * B2B portal — live chat, profile and uploads.
 *
 * A lightweight WhatsApp-style messaging layer between the admin and each
 * branch/rep account (one continuous thread per account), plus profile editing
 * (name / phone / password / photo) and a shared image-upload endpoint used by
 * both the chat and the profile photo.
 *
 * Threads are keyed by the account's user id. Messages are stored as private
 * `osoul_message` posts. "Live" is achieved with the portal's existing polling.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Data
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	register_post_type( 'osoul_message', array(
		'public'              => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'rewrite'             => false,
		'supports'            => array( 'editor' ),
	) );
} );

/** The thread id (account user id) the current user is allowed to act on. */
function osoul_chat_resolve_thread( $requested = 0 ) {
	$role = osoul_portal_role();
	if ( 'admin' === $role ) {
		$requested   = (int) $requested;
		$target_role = osoul_portal_role( $requested );
		if ( in_array( $target_role, array( 'branch', 'rep', 'partner' ), true ) ) {
			return $requested;
		}
		if ( function_exists( 'osoul_is_customer' ) && osoul_is_customer( $requested ) ) {
			return $requested;
		}
		return 0;
	}
	// branch / rep can only ever touch their own thread.
	return get_current_user_id();
}

/** Unread count for the current user (role-aware) for the nav badge. */
function osoul_chat_unread_total() {
	$role = osoul_portal_role();
	if ( 'admin' === $role ) {
		$total = 0;
		foreach ( osoul_chat_account_ids() as $uid ) {
			$total += osoul_chat_thread_unread( $uid, 'admin' );
		}
		return $total;
	}
	return osoul_chat_thread_unread( get_current_user_id(), 'account' );
}

/** All branch + rep + partner ids, plus any website customers who have messaged. */
function osoul_chat_account_ids() {
	$users = get_users( array( 'role__in' => array( 'osoul_branch', 'osoul_rep', 'osoul_partner' ), 'fields' => array( 'ID' ) ) );
	$ids   = array_map( static function ( $u ) { return (int) $u->ID; }, $users );
	// Customers only surface once they've actually written — keeps the inbox clean.
	if ( function_exists( 'osoul_chat_customer_thread_ids' ) ) {
		$ids = array_merge( $ids, osoul_chat_customer_thread_ids() );
	}
	return $ids;
}

/** Count messages in a thread the given side hasn't read yet. */
function osoul_chat_thread_unread( $thread_id, $side ) {
	$read_key = ( 'admin' === $side ) ? '_osoul_chat_admin_read' : '_osoul_chat_acc_read';
	$from     = ( 'admin' === $side ) ? 'account' : 'admin'; // unread = messages from the OTHER side
	$since    = get_user_meta( $thread_id, $read_key, true );

	$args = array(
		'post_type'      => 'osoul_message',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_thread', 'value' => (int) $thread_id ),
			array( 'key' => '_from', 'value' => $from ),
		),
	);
	if ( $since ) {
		$args['date_query'] = array( array( 'after' => $since, 'inclusive' => false, 'column' => 'post_date' ) );
	}
	$q = new WP_Query( $args );
	return (int) count( $q->posts );
}

/** Mark a thread read by the current side (admin/account). */
function osoul_chat_mark_read( $thread_id ) {
	$side = ( 'admin' === osoul_portal_role() ) ? 'admin' : 'account';
	$key  = ( 'admin' === $side ) ? '_osoul_chat_admin_read' : '_osoul_chat_acc_read';
	update_user_meta( $thread_id, $key, current_time( 'mysql' ) );
}

/** Avatar URL for a user (falls back to '' so the UI shows initials). */
function osoul_user_avatar( $user_id ) {
	return (string) get_user_meta( $user_id, '_osoul_avatar', true );
}

/* -------------------------------------------------------------------------
 *  REST routes
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	$ns = 'osoul/v1';

	register_rest_route( $ns, '/portal/chat/unread', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'osoul_rest_chat_unread',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/chat/threads', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'osoul_rest_chat_threads',
		'permission_callback' => 'osoul_portal_perm_admin',
	) );
	register_rest_route( $ns, '/portal/chat/messages', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'osoul_rest_chat_messages',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/chat/send', array(
		'methods' => WP_REST_Server::CREATABLE, 'callback' => 'osoul_rest_chat_send',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/upload', array(
		'methods' => WP_REST_Server::CREATABLE, 'callback' => 'osoul_rest_upload',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
	register_rest_route( $ns, '/portal/profile', array(
		'methods' => WP_REST_Server::CREATABLE, 'callback' => 'osoul_rest_profile',
		'permission_callback' => 'osoul_portal_perm_any',
	) );
} );

function osoul_rest_chat_unread() {
	return rest_ensure_response( array( 'unread' => osoul_chat_unread_total() ) );
}

/** Admin: list of conversations with last message + unread. */
function osoul_rest_chat_threads() {
	$out = array();
	foreach ( osoul_chat_account_ids() as $uid ) {
		$u = get_user_by( 'id', $uid );
		if ( ! $u ) { continue; }
		$last = new WP_Query( array(
			'post_type' => 'osoul_message', 'post_status' => 'publish',
			'posts_per_page' => 1, 'no_found_rows' => true,
			'meta_query' => array( array( 'key' => '_thread', 'value' => $uid ) ),
		) );
		$lm   = $last->posts ? $last->posts[0] : null;
		$role = osoul_portal_role( $u );
		if ( '' === $role && function_exists( 'osoul_is_customer' ) && osoul_is_customer( $u ) ) {
			$role = 'customer';
		}
		$out[] = array(
			'id'      => $uid,
			'name'    => $u->display_name,
			'role'    => $role,
			'avatar'  => osoul_user_avatar( $uid ),
			'last'    => $lm ? wp_trim_words( wp_strip_all_tags( $lm->post_content ), 8, '…' ) : '',
			'last_ts' => $lm ? get_post_time( 'U', true, $lm ) : 0,
			'unread'  => osoul_chat_thread_unread( $uid, 'admin' ),
		);
	}
	// Most recent conversations first.
	usort( $out, static function ( $a, $b ) { return $b['last_ts'] <=> $a['last_ts']; } );
	return rest_ensure_response( array( 'threads' => $out ) );
}

/** Messages of a thread (marks it read for the current side). */
function osoul_rest_chat_messages( WP_REST_Request $req ) {
	$thread = osoul_chat_resolve_thread( $req->get_param( 'thread' ) );
	if ( ! $thread ) {
		return new WP_Error( 'osoul_bad', 'محادثة غير صالحة.', array( 'status' => 400 ) );
	}
	$q = new WP_Query( array(
		'post_type' => 'osoul_message', 'post_status' => 'publish',
		'posts_per_page' => 200, 'order' => 'ASC', 'orderby' => 'date', 'no_found_rows' => true,
		'meta_query' => array( array( 'key' => '_thread', 'value' => $thread ) ),
	) );
	$myrole = osoul_portal_role();
	$mine   = ( 'admin' === $myrole ) ? 'admin' : 'account';
	$out    = array();
	foreach ( $q->posts as $p ) {
		$from  = get_post_meta( $p->ID, '_from', true );
		$out[] = array(
			'id'   => $p->ID,
			'from' => $from,
			'mine' => ( $from === $mine ),
			'body' => $p->post_content,
			'img'  => get_post_meta( $p->ID, '_img', true ),
			'name' => get_post_meta( $p->ID, '_sender_name', true ),
			'time' => get_the_time( 'Y-m-d H:i', $p ),
		);
	}
	osoul_chat_mark_read( $thread );
	return rest_ensure_response( array( 'messages' => $out, 'thread' => $thread ) );
}

/** Send a message into a thread. */
function osoul_rest_chat_send( WP_REST_Request $req ) {
	$thread = osoul_chat_resolve_thread( $req->get_param( 'thread' ) );
	if ( ! $thread ) {
		return new WP_Error( 'osoul_bad', 'محادثة غير صالحة.', array( 'status' => 400 ) );
	}
	$body = sanitize_textarea_field( (string) $req->get_param( 'body' ) );
	$img  = esc_url_raw( (string) $req->get_param( 'img' ) );
	if ( '' === $body && '' === $img ) {
		return new WP_Error( 'osoul_empty', 'الرسالة فارغة.', array( 'status' => 422 ) );
	}
	// Only allow images we host (from our own upload endpoint).
	if ( $img && 0 !== strpos( $img, home_url() ) ) { $img = ''; }

	$from = ( 'admin' === osoul_portal_role() ) ? 'admin' : 'account';
	$id   = wp_insert_post( array(
		'post_type'    => 'osoul_message',
		'post_status'  => 'publish',
		'post_content' => $body,
	), true );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'osoul_fail', 'تعذّر الإرسال.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, '_thread', $thread );
	update_post_meta( $id, '_from', $from );
	update_post_meta( $id, '_sender_id', get_current_user_id() );
	update_post_meta( $id, '_sender_name', wp_get_current_user()->display_name );
	if ( $img ) { update_post_meta( $id, '_img', $img ); }

	// The sender has, by definition, read up to now.
	osoul_chat_mark_read( $thread );

	return rest_ensure_response( array(
		'ok'   => true,
		'msg'  => array(
			'id' => $id, 'from' => $from, 'mine' => true, 'body' => $body, 'img' => $img,
			'name' => wp_get_current_user()->display_name, 'time' => current_time( 'Y-m-d H:i' ),
		),
	) );
}

/* -------------------------------------------------------------------------
 *  Uploads + profile
 * ---------------------------------------------------------------------- */

/** Shared image upload (chat images + profile photos). Returns {url,id}. */
function osoul_rest_upload( WP_REST_Request $req ) {
	if ( empty( $_FILES['file'] ) ) {
		return new WP_Error( 'osoul_no_file', 'لا يوجد ملف.', array( 'status' => 422 ) );
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	$file = $_FILES['file'];
	// Images only, up to ~6MB.
	$type = wp_check_filetype( $file['name'] );
	if ( ! preg_match( '#^image/#', (string) ( $type['type'] ?? '' ) ) && ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $file['name'] ) ) {
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
	return rest_ensure_response( array( 'ok' => true, 'id' => $att_id, 'url' => wp_get_attachment_url( $att_id ) ) );
}

/** Update the current user's profile (name / phone / password / avatar). */
function osoul_rest_profile( WP_REST_Request $req ) {
	$uid  = get_current_user_id();
	$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$phone = sanitize_text_field( (string) $req->get_param( 'phone' ) );
	$avatar = esc_url_raw( (string) $req->get_param( 'avatar' ) );
	$pass  = (string) $req->get_param( 'password' );

	if ( '' !== $name ) {
		wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'nickname' => $name ) );
	}
	update_user_meta( $uid, '_osoul_phone', $phone );

	if ( '' !== $avatar && 0 === strpos( $avatar, home_url() ) ) {
		update_user_meta( $uid, '_osoul_avatar', $avatar );
	}

	if ( '' !== $pass ) {
		if ( strlen( $pass ) < 8 ) {
			return new WP_Error( 'osoul_weak', 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.', array( 'status' => 422 ) );
		}
		wp_set_password( $pass, $uid );
		// Keep the current session alive after changing our own password.
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true );
	}

	$u = get_user_by( 'id', $uid );
	return rest_ensure_response( array(
		'ok'      => true,
		'name'    => $u->display_name,
		'phone'   => $phone,
		'avatar'  => osoul_user_avatar( $uid ),
		'pw_changed' => ( '' !== $pass ),
	) );
}
