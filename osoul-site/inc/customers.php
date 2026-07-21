<?php
/**
 * Website customer accounts + login gate.
 *
 * Visitors must register (email/password or Google) before they can request a
 * quote or message the team. Accounts are stored as `osoul_customer` users and
 * listed in the admin dashboard. These are Osoul's OWN direct customers — kept
 * entirely separate from partners' end customers, which stay behind the privacy
 * wall.
 *
 * Google credentials live in Settings (never hard-coded): Osoul → Settings →
 * "ربط جوجل". The Authorized redirect URI in Google Cloud must equal the site
 * home URL, e.g. https://osoulalbinaa.com/ .
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  Role + helpers
 * ====================================================================== */

/** Capability that marks a website customer account. */
function osoul_customer_cap() { return 'osoul_customer_portal'; }

/** True when the user is a registered website customer. */
function osoul_is_customer( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', (int) $user ) ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, osoul_customer_cap() );
}

/** Base URL of the customer area. */
function osoul_customer_url( $args = array() ) {
	$u = home_url( '/customer/' );
	return $args ? add_query_arg( $args, $u ) : $u;
}

/** A customer's stored profile (company + phone). */
function osoul_customer_company( $uid ) { return (string) get_user_meta( $uid, '_osoul_company', true ); }
function osoul_customer_phone( $uid ) { return (string) get_user_meta( $uid, '_osoul_c_phone', true ); }

/* =========================================================================
 *  Registration (email / password)
 * ====================================================================== */

/**
 * Create a website customer from a registration form.
 *
 * @param array $data name, company, email, phone, password, confirm
 * @return int|WP_Error new user id or a validation error.
 */
function osoul_customer_register( $data ) {
	$name    = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
	$company = sanitize_text_field( (string) ( $data['company'] ?? '' ) );
	$email   = sanitize_email( (string) ( $data['email'] ?? '' ) );
	$phone   = sanitize_text_field( (string) ( $data['phone'] ?? '' ) );
	$pass    = (string) ( $data['password'] ?? '' );
	$confirm = (string) ( $data['confirm'] ?? '' );

	if ( '' === $name || '' === $email || '' === $phone ) {
		return new WP_Error( 'osoul_missing', 'الرجاء تعبئة الاسم والبريد ورقم الجوال.' );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_email', 'البريد الإلكتروني غير صحيح.' );
	}
	if ( strlen( $pass ) < 6 ) {
		return new WP_Error( 'osoul_pass', 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.' );
	}
	if ( $pass !== $confirm ) {
		return new WP_Error( 'osoul_confirm', 'كلمتا المرور غير متطابقتين.' );
	}
	if ( email_exists( $email ) ) {
		return new WP_Error( 'osoul_exists', 'هذا البريد مسجّل مسبقاً — سجّل الدخول بدلاً من إنشاء حساب.' );
	}

	$uid = wp_insert_user( array(
		'user_login'   => $email,
		'user_email'   => $email,
		'user_pass'    => $pass,
		'display_name' => $name,
		'first_name'   => $name,
		'role'         => 'osoul_customer',
	) );
	if ( is_wp_error( $uid ) ) {
		return $uid;
	}
	update_user_meta( $uid, '_osoul_company', $company );
	update_user_meta( $uid, '_osoul_c_phone', $phone );
	update_user_meta( $uid, '_osoul_c_registered', current_time( 'mysql' ) );
	update_user_meta( $uid, '_osoul_c_source', 'email' );
	do_action( 'osoul_customer_registered', $uid );
	return $uid;
}

/* =========================================================================
 *  Google Sign-In (OAuth 2.0)
 * ====================================================================== */

/** Google OAuth credentials from Settings. */
function osoul_google_creds() {
	return array(
		'id'     => trim( (string) osoul_opt( 'google_client_id' ) ),
		'secret' => trim( (string) osoul_opt( 'google_client_secret' ) ),
	);
}

/** True when Google login is configured. */
function osoul_google_enabled() {
	$c = osoul_google_creds();
	return '' !== $c['id'] && '' !== $c['secret'];
}

/** The redirect URI — must exactly match the Authorized redirect URI in Google Cloud. */
function osoul_google_redirect_uri() {
	return home_url( '/' );
}

/**
 * A self-verifying (HMAC-signed) anti-CSRF state.
 *
 * Signed instead of stored in a transient so it keeps working even when the
 * login page is served from a page cache (LiteSpeed) — a stored transient would
 * expire while the cached button still points at the old state, which silently
 * broke re-login. The signature can't be forged without the auth salt.
 */
function osoul_google_state() {
	$rand = wp_generate_password( 16, false, false );
	return 'osoulg_' . $rand . '_' . substr( hash_hmac( 'sha256', $rand, wp_salt( 'auth' ) ), 0, 20 );
}
function osoul_google_state_valid( $state ) {
	$parts = explode( '_', (string) $state );
	if ( 3 !== count( $parts ) || 'osoulg' !== $parts[0] ) {
		return false;
	}
	return hash_equals( substr( hash_hmac( 'sha256', $parts[1], wp_salt( 'auth' ) ), 0, 20 ), $parts[2] );
}

/** Build the Google consent URL with a signed anti-CSRF state. */
function osoul_google_auth_url() {
	$c = osoul_google_creds();
	return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
		'client_id'     => $c['id'],
		'redirect_uri'  => osoul_google_redirect_uri(),
		'response_type' => 'code',
		'scope'         => 'openid email profile',
		'state'         => osoul_google_state(),
		'access_type'   => 'online',
		'prompt'        => 'select_account',
	) );
}

/**
 * Detect Google's callback. Because the redirect URI is the site home, we
 * recognise it by our own signed `state` token (also stops CSRF replay).
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['state'] ) || empty( $_GET['code'] ) ) {
		return;
	}
	$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
	if ( ! osoul_google_state_valid( $state ) ) {
		return;
	}
	osoul_google_handle_callback( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
}, 0 );

/** Exchange the code, fetch the profile, find-or-create the customer, log in. */
function osoul_google_handle_callback( $code ) {
	$c = osoul_google_creds();

	$res = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'timeout' => 15,
		'body'    => array(
			'code'          => $code,
			'client_id'     => $c['id'],
			'client_secret' => $c['secret'],
			'redirect_uri'  => osoul_google_redirect_uri(),
			'grant_type'    => 'authorization_code',
		),
	) );
	if ( is_wp_error( $res ) ) {
		osoul_customer_redirect_error( 'تعذّر الاتصال بجوجل، حاول مرة أخرى.' );
	}
	$body  = json_decode( wp_remote_retrieve_body( $res ), true );
	$token = is_array( $body ) ? ( $body['access_token'] ?? '' ) : '';
	if ( '' === $token ) {
		osoul_customer_redirect_error( 'فشل تسجيل الدخول عبر جوجل.' );
	}

	$ur = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	) );
	if ( is_wp_error( $ur ) ) {
		osoul_customer_redirect_error( 'تعذّر جلب بيانات جوجل.' );
	}
	$info  = json_decode( wp_remote_retrieve_body( $ur ), true );
	$email = is_array( $info ) ? sanitize_email( (string) ( $info['email'] ?? '' ) ) : '';
	$name  = is_array( $info ) ? sanitize_text_field( (string) ( $info['name'] ?? '' ) ) : '';
	if ( '' === $email || empty( $info['email_verified'] ) ) {
		osoul_customer_redirect_error( 'حساب جوجل بلا بريد موثّق.' );
	}

	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		$uid = (int) $existing->ID;
	} else {
		$uid = wp_insert_user( array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => $name ?: $email,
			'first_name'   => $name,
			'role'         => 'osoul_customer',
		) );
		if ( is_wp_error( $uid ) ) {
			osoul_customer_redirect_error( 'تعذّر إنشاء الحساب.' );
		}
		update_user_meta( $uid, '_osoul_c_registered', current_time( 'mysql' ) );
		update_user_meta( $uid, '_osoul_c_source', 'google' );
		do_action( 'osoul_customer_registered', $uid );
	}

	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true, is_ssl() );
	wp_safe_redirect( osoul_customer_url() );
	exit;
}

/** Redirect back to the customer area with an error message. */
function osoul_customer_redirect_error( $msg ) {
	wp_safe_redirect( osoul_customer_url( array( 'err' => rawurlencode( $msg ) ) ) );
	exit;
}

/* =========================================================================
 *  /customer  — login / register / account
 * ====================================================================== */

add_action( 'template_redirect', function () {
	if ( ! function_exists( 'osoul_request_slug' ) || 'customer' !== osoul_request_slug() ) {
		return;
	}
	nocache_headers();
	// Never cache the customer area: the login button carries a fresh signed
	// state, and the account view must reflect the live logged-in state.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
	}
	status_header( 200 );

	if ( isset( $_GET['logout'] ) ) {
		wp_logout();
		wp_safe_redirect( osoul_customer_url() );
		exit;
	}

	$err = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : '';
	if ( ! empty( $_POST['osoul_c_action'] ) ) {
		$err = osoul_customer_handle_post(); // redirects on success
	}

	if ( osoul_is_customer() ) {
		osoul_customer_account_page();
	} else {
		osoul_customer_auth_page( $err );
	}
	exit;
}, 1 );

/** Handle register / login / message POSTs; returns an error string or exits on success. */
function osoul_customer_handle_post() {
	$action = sanitize_key( (string) $_POST['osoul_c_action'] );
	$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'osoul_customer' ) ) {
		return 'انتهت صلاحية الجلسة، حدّث الصفحة وحاول مرة أخرى.';
	}

	if ( 'register' === $action ) {
		$res = osoul_customer_register( wp_unslash( $_POST ) );
		if ( is_wp_error( $res ) ) {
			return $res->get_error_message();
		}
		wp_set_current_user( $res );
		wp_set_auth_cookie( $res, true, is_ssl() );
		wp_safe_redirect( osoul_customer_url() );
		exit;
	}

	if ( 'login' === $action ) {
		$user = wp_signon( array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ),
			'user_password' => (string) ( $_POST['password'] ?? '' ),
			'remember'      => true,
		), is_ssl() );
		if ( is_wp_error( $user ) ) {
			return 'بيانات الدخول غير صحيحة.';
		}
		wp_safe_redirect( osoul_customer_url() );
		exit;
	}

	if ( 'message' === $action && osoul_is_customer() ) {
		$msg = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
		if ( '' !== $msg ) {
			osoul_customer_send_message( get_current_user_id(), $msg );
		}
		wp_safe_redirect( osoul_customer_url( array( 'sent' => 1 ) ) . '#msg' );
		exit;
	}

	return '';
}

/* =========================================================================
 *  Customer → dashboard messaging (reuses the chat data model)
 * ====================================================================== */

/** Store a customer message as an osoul_message in the customer's own thread. */
function osoul_customer_send_message( $uid, $text ) {
	$mid = wp_insert_post( array(
		'post_type'   => 'osoul_message',
		'post_status' => 'publish',
		'post_content'=> $text,
	) );
	if ( is_wp_error( $mid ) || ! $mid ) {
		return false;
	}
	update_post_meta( $mid, '_thread', (int) $uid );
	update_post_meta( $mid, '_from', 'account' );
	// Notify the team.
	wp_mail(
		apply_filters( 'osoul_quote_notify_email', get_option( 'admin_email' ) ),
		'رسالة جديدة من عميل: ' . wp_get_current_user()->display_name,
		$text . "\n\n" . home_url( '/dashboard/' )
	);
	return true;
}

/** Thread ids that have at least one message AND belong to a website customer. */
function osoul_chat_customer_thread_ids() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_thread' AND p.post_type = 'osoul_message' AND p.post_status = 'publish'"
	);
	$out = array();
	foreach ( (array) $ids as $id ) {
		if ( osoul_is_customer( (int) $id ) ) {
			$out[] = (int) $id;
		}
	}
	return $out;
}

/** The current customer's message thread (their messages + admin replies), oldest first. */
function osoul_customer_thread( $uid ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_message',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_thread', 'value' => (int) $uid ) ),
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		$out[] = array(
			'from' => get_post_meta( $p->ID, '_from', true ) === 'admin' ? 'admin' : 'me',
			'text' => $p->post_content,
			'date' => get_the_date( 'Y-m-d H:i', $p ),
		);
	}
	return $out;
}

/* =========================================================================
 *  Gate the quote request behind a customer login
 * ====================================================================== */

/** Expose the customer's login state + details to the front-end quote form. */
add_action( 'wp_head', function () {
	$logged = osoul_is_customer();
	$u      = wp_get_current_user();
	?>
<script>window.osoulCustomer = <?php echo wp_json_encode( array(
	'loggedIn' => (bool) $logged,
	'loginUrl' => osoul_customer_url(),
	'name'     => $logged ? $u->display_name : '',
	'email'    => $logged ? $u->user_email : '',
	'phone'    => $logged ? osoul_customer_phone( $u->ID ) : '',
) ); ?>;</script>
	<?php
}, 5 );

/** A customer's own quote requests (links into the existing quote/PDF pages). */
function osoul_customer_orders( $uid ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_osoul_customer_uid', 'value' => (int) $uid ) ),
	) );
	$stages = function_exists( 'osoul_quote_stages' ) ? osoul_quote_stages() : array();
	$out    = array();
	foreach ( $q->posts as $p ) {
		$items  = (array) get_post_meta( $p->ID, '_osoul_items', true );
		$totals = osoul_quote_totals( $items, (float) get_post_meta( $p->ID, '_osoul_discount', true ) );
		$stage  = get_post_meta( $p->ID, '_osoul_stage', true ) ?: 'new';
		$st     = $stages[ $stage ] ?? array( 'ar' => $stage, 'color' => '#6b7280' );
		$tok    = get_post_meta( $p->ID, '_osoul_token', true );
		$priced = ( $totals['subtotal'] ?? 0 ) > 0;
		$out[]  = array(
			'number'   => get_post_meta( $p->ID, '_osoul_number', true ) ?: ('#' . $p->ID),
			'items'    => count( $items ),
			'total'    => $priced ? osoul_money( $totals['total'] ) : '',
			'stage'    => $st['ar'],
			'stage_en' => $st['en'] ?? $st['ar'],
			'color'    => $st['color'],
			'url'    => ( $tok && $priced ) ? home_url( '/quote/' . $tok . '/' ) : '',
			'date'   => get_the_date( 'Y-m-d', $p ),
			'priced' => $priced,
		);
	}
	return $out;
}

/** Require a logged-in customer for the public quote-request endpoint. */
function osoul_customer_rfq_perm() {
	if ( osoul_is_customer() || current_user_can( 'manage_options' ) ) {
		return true;
	}
	return new WP_Error( 'osoul_login_required', 'يجب تسجيل الدخول كعميل قبل إرسال طلب عرض السعر.', array( 'status' => 401 ) );
}

/** Stamp the logged-in customer onto their quote request + link it to their account. */
add_action( 'osoul_quote_request_received', function ( $quote_id ) {
	if ( ! osoul_is_customer() ) {
		return;
	}
	$uid = get_current_user_id();
	update_post_meta( $quote_id, '_osoul_customer_uid', $uid );
	// Prefer the account's own phone/company if the form left them blank.
	$cust = (array) get_post_meta( $quote_id, '_osoul_customer', true );
	if ( empty( $cust['phone'] ) ) { $cust['phone'] = osoul_customer_phone( $uid ); }
	$cust['company'] = osoul_customer_company( $uid );
	update_post_meta( $quote_id, '_osoul_customer', $cust );
}, 10, 1 );

/* =========================================================================
 *  Admin dashboard: the registered-customers list
 * ====================================================================== */

/** All registered website customers with their stats (admin dashboard). */
function osoul_customers_list() {
	$users = get_users( array( 'role' => 'osoul_customer', 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500 ) );
	$out   = array();
	foreach ( $users as $u ) {
		$q = new WP_Query( array(
			'post_type'      => 'osoul_quote',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( array( 'key' => '_osoul_customer_uid', 'value' => (int) $u->ID ) ),
		) );
		$out[] = array(
			'id'       => (int) $u->ID,
			'name'     => $u->display_name,
			'company'  => osoul_customer_company( $u->ID ),
			'email'    => $u->user_email,
			'phone'    => osoul_customer_phone( $u->ID ),
			'source'   => get_user_meta( $u->ID, '_osoul_c_source', true ) === 'google' ? 'Google' : 'حساب',
			'quotes'   => (int) $q->found_posts,
			'joined'   => get_user_meta( $u->ID, '_osoul_c_registered', true ) ? gmdate( 'Y-m-d', strtotime( get_user_meta( $u->ID, '_osoul_c_registered', true ) ) ) : '',
		);
	}
	return $out;
}

/** GET /portal/customers — admin-only registered customer list. */
function osoul_rest_customers( WP_REST_Request $req ) {
	return rest_ensure_response( array( 'customers' => osoul_customers_list() ) );
}

/* =========================================================================
 *  Rendered pages (reuse the standalone-page chrome from quotes-dashboard.php)
 * ====================================================================== */

/** Login + register (+ Google) page for a not-logged-in visitor. */
function osoul_customer_auth_page( $err = '' ) {
	osoul_app_head( 'دخول العملاء — ' . osoul_opt( 'company_name_ar' ), osoul_portal_lang() );
	$logo    = osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo_url' );
	$nonce   = wp_create_nonce( 'osoul_customer' );
	$act     = osoul_customer_url();
	$reg_tab = ( 'register' === ( $_GET['tab'] ?? '' ) ) || ! empty( $_POST['name'] );
	?>
	<style>
	body{background:radial-gradient(1100px 620px at 50% -12%,#173563 0%,#0d1f3c 46%,#081221 100%)!important;min-height:100vh}
	.osc-video-bg{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;border:0;pointer-events:none}
	.osc-video-ov{position:fixed;inset:0;z-index:1;pointer-events:none;background:linear-gradient(180deg,rgba(8,18,33,.66) 0%,rgba(13,31,60,.74) 52%,rgba(8,18,33,.92) 100%)}
	.osc-shell{position:relative;z-index:2;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px 16px}
	.osc-card{width:100%;max-width:450px;background:#fff;border-radius:22px;box-shadow:0 32px 80px rgba(0,0,0,.4);padding:36px 32px;animation:oscIn .4s ease}
	@keyframes oscIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
	.osc-logo{display:block;height:78px;width:auto;margin:0 auto 10px}
	.osc-head{text-align:center;color:#6b7280;font-size:12.5px;margin-bottom:22px;line-height:1.7}
	.osc-head b{display:block;color:#0d1f3c;font-size:18px;font-weight:800;margin-bottom:3px}
	.osc-g{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;background:#fff;border:1.5px solid #e3e7ee;border-radius:12px;padding:12px 14px;font-weight:700;font-size:14.5px;color:#1f2937;text-decoration:none;transition:.18s;cursor:pointer}
	.osc-g:hover{background:#f7f9fc;border-color:#c9d2df;box-shadow:0 4px 14px rgba(13,31,60,.08)}
	.osc-or{display:flex;align-items:center;gap:12px;color:#9aa3b2;font-size:12px;font-weight:600;margin:16px 0}
	.osc-or:before,.osc-or:after{content:"";flex:1;height:1px;background:#e7ebf1}
	.osc-tabs{display:flex;background:#eef1f5;border-radius:13px;padding:4px;margin-bottom:20px}
	.osc-tab{flex:1;text-align:center;padding:11px;border-radius:10px;font-weight:800;font-size:14px;color:#6b7280;cursor:pointer;border:none;background:none;font-family:inherit;transition:.18s}
	.osc-tab.on{background:#fff;color:#0d1f3c;box-shadow:0 2px 7px rgba(13,31,60,.12)}
	.osc-f{margin-bottom:13px}
	.osc-f label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:600}
	.osc-f input{width:100%;border:1.5px solid #e3e7ee;border-radius:12px;padding:12px 14px;font-size:14.5px;font-family:inherit;color:#0d1f3c;outline:none;transition:.15s;background:#fbfcfe}
	.osc-f input:focus{border-color:#D21217;background:#fff;box-shadow:0 0 0 3px rgba(210,18,23,.09)}
	.osc-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
	.osc-btn{width:100%;background:linear-gradient(135deg,#e8464b,#D21217);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer;transition:.18s;margin-top:8px;box-shadow:0 10px 22px rgba(210,18,23,.26)}
	.osc-btn:hover{filter:brightness(1.06);transform:translateY(-1px)}
	.osc-err{background:#fdeaea;color:#b32d2e;border:1px solid #f3c2c2;border-radius:11px;padding:11px 14px;font-size:13.5px;font-weight:600;margin-bottom:18px;text-align:center}
	.osc-back{display:block;text-align:center;color:rgba(255,255,255,.7);text-decoration:none;font-size:13px;margin-top:20px}
	.osc-back:hover{color:#fff}
	@media(max-width:480px){.osc-grid{grid-template-columns:1fr}.osc-card{padding:28px 22px}}
	</style>
	<?php $login_video = osoul_opt( 'login_video_url' ); ?>
	<?php if ( $login_video ) : ?>
	<video class="osc-video-bg" autoplay muted loop playsinline preload="auto" aria-hidden="true" tabindex="-1">
		<source src="<?php echo esc_url( $login_video ); ?>" type="video/mp4">
	</video>
	<div class="osc-video-ov"></div>
	<?php endif; ?>
	<div class="osc-shell">
		<div class="osc-card">
			<?php if ( $logo ) : ?><img class="osc-logo" src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?>
			<div class="osc-head"><b><?php echo esc_html( osoul_opt( 'company_name_ar' ) ); ?></b><?php echo osoul_bi( 'بوابة العملاء — سجّل دخولك لطلب عرض سعر ومتابعة طلباتك', 'Customer portal — sign in to request a quote and track your orders', true ); ?></div>
			<?php if ( $err ) : ?><div class="osc-err"><?php echo esc_html( $err ); ?></div><?php endif; ?>

			<?php if ( osoul_google_enabled() ) : ?>
			<a class="osc-g" href="<?php echo esc_url( osoul_google_auth_url() ); ?>">
				<svg width="19" height="19" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.7 30.1 0 24 0 14.6 0 6.4 5.4 2.5 13.3l7.9 6.1C12.2 13.7 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.6c0-1.6-.1-2.8-.4-4.1H24v7.8h12.4c-.3 2.1-1.6 5.2-4.6 7.3l7.1 5.5c4.2-3.9 6.7-9.6 6.7-16.5z"/><path fill="#FBBC05" d="M10.4 28.6c-.5-1.5-.8-3-.8-4.6s.3-3.1.8-4.6l-7.9-6.1C.9 16.5 0 20.1 0 24s.9 7.5 2.5 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.1 0 11.3-2 15-5.5l-7.1-5.5c-2 1.3-4.6 2.3-7.9 2.3-6.4 0-11.8-4.2-13.6-10l-7.9 6.1C6.4 42.6 14.6 48 24 48z"/></svg>
				<?php echo osoul_bi( 'الدخول عبر Google', 'Continue with Google' ); ?>
			</a>
			<div class="osc-or"><?php echo osoul_bi( 'أو بالبريد الإلكتروني', 'or with email' ); ?></div>
			<?php endif; ?>

			<div class="osc-tabs">
				<button type="button" class="osc-tab <?php echo $reg_tab ? '' : 'on'; ?>" data-tab="login"><?php echo osoul_bi( 'دخول', 'Sign in' ); ?></button>
				<button type="button" class="osc-tab <?php echo $reg_tab ? 'on' : ''; ?>" data-tab="register"><?php echo osoul_bi( 'حساب جديد', 'New account' ); ?></button>
			</div>

			<form method="post" action="<?php echo esc_url( $act ); ?>" data-form="login" style="display:<?php echo $reg_tab ? 'none' : 'block'; ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="osoul_c_action" value="login">
				<div class="osc-f"><label><?php echo osoul_bi( 'البريد الإلكتروني', 'Email', true ); ?></label><input type="email" name="email" autocomplete="username" required></div>
				<div class="osc-f"><label><?php echo osoul_bi( 'كلمة المرور', 'Password', true ); ?></label><input type="password" name="password" autocomplete="current-password" required></div>
				<button class="osc-btn" type="submit"><?php echo osoul_bi( 'دخول', 'Sign in' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( $act ); ?>" data-form="register" style="display:<?php echo $reg_tab ? 'block' : 'none'; ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="osoul_c_action" value="register">
				<div class="osc-grid">
					<div class="osc-f"><label><?php echo osoul_bi( 'الاسم', 'Name', true ); ?></label><input name="name" required></div>
					<div class="osc-f"><label><?php echo osoul_bi( 'اسم الشركة', 'Company name', true ); ?></label><input name="company"></div>
					<div class="osc-f"><label><?php echo osoul_bi( 'البريد الإلكتروني', 'Email', true ); ?></label><input type="email" name="email" required></div>
					<div class="osc-f"><label><?php echo osoul_bi( 'رقم الجوال', 'Phone number', true ); ?></label><input type="tel" name="phone" required></div>
					<div class="osc-f"><label><?php echo osoul_bi( 'كلمة المرور', 'Password', true ); ?></label><input type="password" name="password" autocomplete="new-password" required></div>
					<div class="osc-f"><label><?php echo osoul_bi( 'تأكيد كلمة المرور', 'Confirm password', true ); ?></label><input type="password" name="confirm" autocomplete="new-password" required></div>
				</div>
				<button class="osc-btn" type="submit"><?php echo osoul_bi( 'إنشاء حساب', 'Create account' ); ?></button>
			</form>
		</div>
		<a class="osc-back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo osoul_bi( '← العودة للموقع', '← Back to the site' ); ?></a>
	</div>
	<script>
	(function () {
		var tabs = document.querySelectorAll('.osc-tab');
		tabs.forEach(function (t) {
			t.addEventListener('click', function () {
				tabs.forEach(function (x) { x.classList.remove('on'); });
				t.classList.add('on');
				var w = t.getAttribute('data-tab');
				document.querySelector('[data-form="login"]').style.display = (w === 'login') ? 'block' : 'none';
				document.querySelector('[data-form="register"]').style.display = (w === 'register') ? 'block' : 'none';
			});
		});
	})();
	</script>
	<?php
	osoul_app_foot();
}

/** Account area for a logged-in customer: quote CTA + direct messaging. */
function osoul_customer_account_page() {
	$uid    = get_current_user_id();
	$u      = wp_get_current_user();
	$sent   = isset( $_GET['sent'] );
	$thread = osoul_customer_thread( $uid );
	$orders = osoul_customer_orders( $uid );
	$logo   = osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo_url' );
	osoul_app_head( 'حسابي — ' . osoul_opt( 'company_name_ar' ), osoul_portal_lang() );
	?>
	<style>
	body{background:#eef1f5!important}
	.osq-top{background:linear-gradient(135deg,#12305e,#0d1f3c);color:#fff;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
	.osq-top .b{display:flex;align-items:center;gap:11px;font-weight:800;font-size:15px;text-decoration:none;color:#fff}
	.osq-top .b img{height:38px;width:auto;background:#fff;border-radius:9px;padding:4px}
	.osq-top a.out{color:rgba(255,255,255,.85);text-decoration:none;font-size:13px;font-weight:600}
	.osq-top a.out:hover{color:#fff}
	.osq-wrap{max-width:780px;margin:0 auto;padding:24px 16px}
	.osq-card{background:#fff;border:1px solid #e7ebf1;border-radius:18px;padding:26px 26px;margin-bottom:18px;box-shadow:0 8px 26px rgba(13,31,60,.06)}
	.osq-hi{font-size:21px;font-weight:800;color:#0d1f3c;margin-bottom:6px}
	.osq-sub{color:#6b7280;font-size:14px}
	.osq-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
	.osq-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 22px;border-radius:12px;font-weight:800;font-size:14.5px;text-decoration:none;cursor:pointer;border:none;font-family:inherit;transition:.18s}
	.osq-btn.p{background:linear-gradient(135deg,#e8464b,#D21217);color:#fff;box-shadow:0 10px 22px rgba(210,18,23,.24)}
	.osq-btn.p:hover{transform:translateY(-1px);filter:brightness(1.05)}
	.osq-btn.s{background:#eef1f5;color:#0d1f3c}
	.osq-btn.s:hover{background:#e3e8ef}
	.osq-h{font-size:16px;font-weight:800;color:#0d1f3c;margin-bottom:14px;display:flex;align-items:center;gap:8px}
	.osq-ok{background:#e7f7ee;color:#1a7e42;border:1px solid #b6e7c9;border-radius:11px;padding:11px 14px;font-size:13.5px;font-weight:600;margin-bottom:14px}
	.osq-thread{max-height:340px;overflow:auto;display:flex;flex-direction:column;gap:9px;margin-bottom:14px;padding:4px}
	.osq-bub{max-width:78%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.6}
	.osq-bub.me{align-self:flex-start;background:#eef1f5;color:#0d1f3c;border-bottom-right-radius:5px}
	.osq-bub.adm{align-self:flex-end;background:linear-gradient(135deg,#12305e,#0d1f3c);color:#fff;border-bottom-left-radius:5px}
	.osq-bub .t{font-size:10.5px;opacity:.65;margin-top:4px}
	.osq-ta{width:100%;border:1.5px solid #e3e7ee;border-radius:12px;padding:12px 14px;font-size:14.5px;font-family:inherit;color:#0d1f3c;outline:none;background:#fbfcfe;resize:vertical}
	.osq-ta:focus{border-color:#D21217;background:#fff;box-shadow:0 0 0 3px rgba(210,18,23,.08)}
	</style>
	<div class="osq-top">
		<a class="b" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?><span><?php echo esc_html( osoul_opt( 'company_name_ar' ) ); ?></span></a>
		<div style="display:flex;align-items:center;gap:12px"><?php echo osoul_portal_lang_select(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from safe helper ?><span style="color:rgba(255,255,255,.7);font-size:13px"><?php echo esc_html( $u->display_name ); ?></span> &nbsp;·&nbsp; <a class="out" href="<?php echo esc_url( osoul_customer_url( array( 'logout' => 1 ) ) ); ?>"><?php echo osoul_bi( 'تسجيل الخروج', 'Log out' ); ?></a></div>
	</div>
	<div class="osq-wrap">
		<div class="osq-card">
			<div class="osq-hi"><?php echo osoul_bi( 'مرحباً', 'Welcome' ); ?> <?php echo esc_html( $u->display_name ); ?> 👋</div>
			<div class="osq-sub"><?php echo osoul_bi( 'تقدر الآن تطلب عرض سعر وتتابع طلباتك وتراسل الفريق مباشرة.', 'You can now request a quote, track your orders and message the team directly.', true ); ?></div>
			<div class="osq-cta">
				<a class="osq-btn p" href="<?php echo esc_url( home_url( '/products/' ) ); ?>">＋ <?php echo osoul_bi( 'اطلب عرض سعر', 'Request a quote' ); ?></a>
				<a class="osq-btn s" href="#orders">📋 <?php echo osoul_bi( 'طلباتي', 'My orders' ); ?></a>
			</div>
		</div>

		<div class="osq-card" id="orders">
			<div class="osq-h">📋 <?php echo osoul_bi( 'طلباتي', 'My orders' ); ?></div>
			<?php if ( ! $orders ) : ?>
				<div class="osq-sub"><?php echo osoul_bi( 'ما عندك طلبات بعد. أضف منتجات لقائمة العرض من الموقع وأرسلها.', 'You have no orders yet. Add products to your quote list on the site and send it.', true ); ?></div>
			<?php else : ?>
				<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:14px">
					<thead><tr style="color:#6b7280;font-size:12px">
						<th style="padding:9px 10px;border-bottom:1px solid #e7ebf1;font-weight:600"><?php echo osoul_bi( 'رقم الطلب', 'Order no.', true ); ?></th>
						<th style="padding:9px 10px;border-bottom:1px solid #e7ebf1;font-weight:600"><?php echo osoul_bi( 'المنتجات', 'Products', true ); ?></th>
						<th style="padding:9px 10px;border-bottom:1px solid #e7ebf1;font-weight:600"><?php echo osoul_bi( 'الحالة', 'Status', true ); ?></th>
						<th style="padding:9px 10px;border-bottom:1px solid #e7ebf1;font-weight:600"><?php echo osoul_bi( 'الإجمالي', 'Total', true ); ?></th>
						<th style="padding:9px 10px;border-bottom:1px solid #e7ebf1;font-weight:600"><?php echo osoul_bi( 'التاريخ', 'Date', true ); ?></th>
						<th style="border-bottom:1px solid #e7ebf1"></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $orders as $o ) : ?>
						<tr>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6;font-weight:700;color:#0d1f3c"><?php echo esc_html( $o['number'] ); ?></td>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6"><?php echo (int) $o['items']; ?></td>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6"><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $o['color'] ); ?>"><?php echo osoul_bi( $o['stage'], $o['stage_en'] ); ?></span></td>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6;font-weight:700"><?php echo $o['total'] ? esc_html( $o['total'] ) . ' ' . osoul_bi( 'ر.س', 'SAR' ) : '—'; ?></td>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6;color:#6b7280"><?php echo esc_html( $o['date'] ); ?></td>
							<td style="padding:11px 10px;border-bottom:1px solid #f0f2f6;text-align:left">
								<?php if ( $o['url'] ) : ?><a href="<?php echo esc_url( $o['url'] ); ?>" target="_blank" style="color:#D21217;font-weight:700;text-decoration:none;white-space:nowrap"><?php echo osoul_bi( 'عرض السعر + PDF ←', 'Quote + PDF ←' ); ?></a>
								<?php else : ?><span style="color:#9aa3b2;font-size:12.5px"><?php echo osoul_bi( 'بانتظار التسعير', 'Awaiting pricing' ); ?></span><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>
		</div>

		<div class="osq-card" id="msg">
			<div class="osq-h">💬 <?php echo osoul_bi( 'مراسلة الإدارة', 'Message management' ); ?></div>
			<?php if ( $sent ) : ?><div class="osq-ok"><?php echo osoul_bi( 'تم إرسال رسالتك، سيتواصل معك الفريق قريباً.', 'Your message was sent — the team will contact you soon.', true ); ?></div><?php endif; ?>
			<?php if ( $thread ) : ?>
			<div class="osq-thread">
				<?php foreach ( $thread as $m ) : ?>
					<div class="osq-bub <?php echo 'me' === $m['from'] ? 'me' : 'adm'; ?>">
						<?php echo esc_html( $m['text'] ); ?>
						<div class="t"><?php echo esc_html( $m['date'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( osoul_customer_url() ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'osoul_customer' ) ); ?>">
				<input type="hidden" name="osoul_c_action" value="message">
				<textarea class="osq-ta" name="message" rows="3" placeholder="<?php
					$osoul_msg_ph = array( 'en' => 'Write your message to the team…', 'ur' => 'ٹیم کو اپنا پیغام لکھیں…', 'ar' => 'اكتب رسالتك للفريق…' );
					echo esc_attr( $osoul_msg_ph[ osoul_portal_lang() ] ?? $osoul_msg_ph['ar'] );
				?>" required></textarea>
				<button class="osq-btn p" type="submit" style="margin-top:12px"><?php echo osoul_bi( 'إرسال', 'Send' ); ?></button>
			</form>
		</div>
	</div>
	<?php
	osoul_app_foot();
}
