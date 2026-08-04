<?php
/**
 * Employee webmail — role, accounts, credential vault and the admin manager.
 *
 * A fifth dashboard actor beside admin / branch / rep / partner: an internal
 * **employee** whose /dashboard is a full webmail client bound to that person's
 * own Hostinger mailbox (send + receive). Employees are deliberately kept OUT of
 * osoul_portal_role() so they can never reach the sales-portal REST endpoints or
 * see any quote/customer data — their world is only their inbox.
 *
 *   admin (site owner, wp-admin)  → adds/suspends/deletes the employee accounts
 *   employee (role osoul_employee)→ logs in at /dashboard, links their mailbox
 *                                   once (email + password), then reads/sends
 *                                   mail in an elegant Gmail/Outlook-style UI.
 *
 * Mailbox privacy: each mailbox is private to its owner. The site owner manages
 * the *account* (create / suspend / reset the dashboard password / delete) but
 * never reads anyone's mail — there is no admin path into a mailbox.
 *
 * Server settings default to Hostinger, so an employee only ever types their
 * email address and email password; everything else is pre-filled.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  Role + identity
 * ====================================================================== */

/** Capability marker for the employee webmail role. */
function osoul_employee_cap() { return 'osoul_employee_portal'; }

/** Register the employee role. Idempotent; safe to call repeatedly. */
function osoul_employee_register_role() {
	add_role( 'osoul_employee', 'موظف — أصول البناء', array(
		'read'                 => true,
		'upload_files'         => true, // compose attachments
		osoul_employee_cap()   => true,
	) );
	$role = get_role( 'osoul_employee' );
	if ( $role ) {
		if ( ! $role->has_cap( osoul_employee_cap() ) ) { $role->add_cap( osoul_employee_cap() ); }
		if ( ! $role->has_cap( 'upload_files' ) )       { $role->add_cap( 'upload_files' ); }
	}
}
add_action( 'init', function () {
	if ( ! get_role( 'osoul_employee' ) ) {
		osoul_employee_register_role();
	}
}, 6 );

/**
 * Is this user an employee (webmail) account?
 *
 * @param int|WP_User|null $user
 * @return bool
 */
function osoul_is_employee( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', (int) $user ) ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return user_can( $user, osoul_employee_cap() );
}

/**
 * May the given user open the /dashboard at all? True for any sales-portal role
 * OR an employee. Used by the shared dashboard router/gate so both worlds share
 * one login screen while staying isolated everywhere else.
 *
 * @param int|WP_User|null $user
 * @return bool
 */
function osoul_dashboard_can_access( $user = null ) {
	if ( function_exists( 'osoul_portal_can_access' ) && osoul_portal_can_access( $user ) ) {
		return true;
	}
	return osoul_is_employee( $user );
}

/** Whether an employee account is active (reuses the portal's _osoul_active meta). */
function osoul_employee_is_active( $user_id ) {
	if ( function_exists( 'osoul_portal_is_active' ) ) {
		return osoul_portal_is_active( $user_id );
	}
	$v = get_user_meta( (int) $user_id, '_osoul_active', true );
	return ( '' === $v ) ? true : (bool) $v;
}

/* =========================================================================
 *  Credential vault — reversible encryption for the mailbox password
 *
 *  IMAP/SMTP need the *plaintext* password on every connection, so the stored
 *  value must be reversibly encrypted (not a one-way hash). We use AES-256-GCM
 *  (authenticated) with a random 32-byte key generated once and kept in a
 *  non-autoloaded option. The real protection is database access control; this
 *  ensures the password is never stored or logged in the clear.
 * ====================================================================== */

/** Fetch (creating once) the site's mailbox-encryption key (32 raw bytes). */
function osoul_mail_secret_key() {
	static $key = null;
	if ( null !== $key ) { return $key; }
	$stored = get_option( 'osoul_mail_secret' );
	if ( ! $stored ) {
		$raw    = function_exists( 'random_bytes' ) ? random_bytes( 32 ) : wp_generate_password( 64, true, true );
		$stored = base64_encode( $raw );
		add_option( 'osoul_mail_secret', $stored, '', 'no' );
	}
	$key = base64_decode( $stored, true );
	if ( false === $key || strlen( $key ) < 32 ) {
		// Derive a stable 32-byte key from whatever we have as a last resort.
		$key = hash( 'sha256', (string) $stored . wp_salt( 'secure_auth' ), true );
	}
	return $key;
}

/**
 * Encrypt a secret for at-rest storage. Returns an opaque, base64 token
 * (prefix "v1:") or '' on failure.
 *
 * @param string $plain
 * @return string
 */
function osoul_mail_encrypt( $plain ) {
	$plain = (string) $plain;
	if ( '' === $plain ) { return ''; }
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		// Extremely rare. Fall back to a keyed, reversible obfuscation so the
		// feature still works; clearly not as strong as AEAD.
		return 'v0:' . base64_encode( $plain ^ str_pad( '', strlen( $plain ), osoul_mail_secret_key() ) );
	}
	$key    = osoul_mail_secret_key();
	$iv     = random_bytes( 12 );
	$tag    = '';
	$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
	if ( false === $cipher ) { return ''; }
	return 'v1:' . base64_encode( $iv . $tag . $cipher );
}

/**
 * Decrypt a token produced by osoul_mail_encrypt(). Returns '' on any failure.
 *
 * @param string $token
 * @return string
 */
function osoul_mail_decrypt( $token ) {
	$token = (string) $token;
	if ( '' === $token ) { return ''; }
	$key = osoul_mail_secret_key();
	if ( 0 === strpos( $token, 'v0:' ) ) {
		$raw = base64_decode( substr( $token, 3 ), true );
		if ( false === $raw ) { return ''; }
		return $raw ^ str_pad( '', strlen( $raw ), $key );
	}
	if ( 0 === strpos( $token, 'v1:' ) ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
		$raw = base64_decode( substr( $token, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return ( false === $plain ) ? '' : $plain;
	}
	return '';
}

/* =========================================================================
 *  Mailbox connection settings (per employee)
 * ====================================================================== */

/** Hostinger defaults so employees only ever type email + password. */
function osoul_mail_server_defaults() {
	return array(
		'imap_host' => 'imap.hostinger.com',
		'imap_port' => 993,
		'imap_ssl'  => 'ssl',
		'smtp_host' => 'smtp.hostinger.com',
		'smtp_port' => 465,
		'smtp_ssl'  => 'ssl',
	);
}

/**
 * The saved mailbox configuration for a user, merged over the Hostinger
 * defaults. Password is returned decrypted only when $with_pass is true.
 *
 * @param int  $user_id
 * @param bool $with_pass
 * @return array
 */
function osoul_mail_config( $user_id, $with_pass = false ) {
	$user_id = (int) $user_id;
	$d       = osoul_mail_server_defaults();
	$cfg     = array(
		'email'     => (string) get_user_meta( $user_id, '_osoul_mail_email', true ),
		'from_name' => (string) ( get_user_meta( $user_id, '_osoul_mail_from', true ) ?: get_userdata( $user_id )->display_name ),
		'imap_host' => (string) ( get_user_meta( $user_id, '_osoul_mail_imap_host', true ) ?: $d['imap_host'] ),
		'imap_port' => (int) ( get_user_meta( $user_id, '_osoul_mail_imap_port', true ) ?: $d['imap_port'] ),
		'smtp_host' => (string) ( get_user_meta( $user_id, '_osoul_mail_smtp_host', true ) ?: $d['smtp_host'] ),
		'smtp_port' => (int) ( get_user_meta( $user_id, '_osoul_mail_smtp_port', true ) ?: $d['smtp_port'] ),
		'connected' => (bool) get_user_meta( $user_id, '_osoul_mail_connected', true ),
		'signature' => (string) get_user_meta( $user_id, '_osoul_mail_sig', true ),
	);
	if ( $with_pass ) {
		$cfg['password'] = osoul_mail_decrypt( get_user_meta( $user_id, '_osoul_mail_pass', true ) );
	}
	return $cfg;
}

/** True when the user has stored working mailbox credentials. */
function osoul_mail_is_connected( $user_id ) {
	$user_id = (int) $user_id;
	return '' !== (string) get_user_meta( $user_id, '_osoul_mail_email', true )
		&& '' !== (string) get_user_meta( $user_id, '_osoul_mail_pass', true );
}

/**
 * Persist mailbox credentials + server overrides for a user (password encrypted).
 *
 * @param int   $user_id
 * @param array $data { email, password, from_name?, imap_host?, imap_port?, smtp_host?, smtp_port? }
 */
function osoul_mail_save_config( $user_id, $data ) {
	$user_id = (int) $user_id;
	$d       = osoul_mail_server_defaults();

	if ( isset( $data['email'] ) ) {
		update_user_meta( $user_id, '_osoul_mail_email', sanitize_email( $data['email'] ) );
	}
	if ( isset( $data['password'] ) && '' !== $data['password'] ) {
		update_user_meta( $user_id, '_osoul_mail_pass', osoul_mail_encrypt( (string) $data['password'] ) );
	}
	if ( isset( $data['from_name'] ) ) {
		update_user_meta( $user_id, '_osoul_mail_from', sanitize_text_field( $data['from_name'] ) );
	}
	foreach ( array( 'imap_host', 'smtp_host' ) as $k ) {
		if ( isset( $data[ $k ] ) && '' !== $data[ $k ] ) {
			update_user_meta( $user_id, '_osoul_mail_' . $k, sanitize_text_field( $data[ $k ] ) );
		}
	}
	foreach ( array( 'imap_port', 'smtp_port' ) as $k ) {
		if ( isset( $data[ $k ] ) && (int) $data[ $k ] > 0 ) {
			update_user_meta( $user_id, '_osoul_mail_' . $k, (int) $data[ $k ] );
		}
	}
}

/** Mark the mailbox connection verified (or not). */
function osoul_mail_set_connected( $user_id, $ok ) {
	update_user_meta( (int) $user_id, '_osoul_mail_connected', $ok ? 1 : 0 );
	update_user_meta( (int) $user_id, '_osoul_mail_checked', time() );
}

/** Forget stored mailbox credentials (employee "disconnect"). */
function osoul_mail_forget( $user_id ) {
	$user_id = (int) $user_id;
	foreach ( array( '_osoul_mail_pass', '_osoul_mail_connected', '_osoul_mail_checked' ) as $k ) {
		delete_user_meta( $user_id, $k );
	}
}

/* =========================================================================
 *  Account creation (admin side)
 * ====================================================================== */

/**
 * Create an employee account with a password chosen by the admin.
 *
 * @param array $args { name, email, password, phone? }
 * @return int|WP_Error  new user id
 */
function osoul_employee_create( $args ) {
	$name  = sanitize_text_field( $args['name'] ?? '' );
	$email = sanitize_email( $args['email'] ?? '' );
	$pass  = (string) ( $args['password'] ?? '' );
	$phone = sanitize_text_field( $args['phone'] ?? '' );

	if ( '' === $name ) {
		return new WP_Error( 'osoul_no_name', 'الاسم مطلوب.' );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_bad_email', 'بريد إلكتروني غير صحيح.' );
	}
	if ( email_exists( $email ) || username_exists( $email ) ) {
		return new WP_Error( 'osoul_dupe', 'هذا البريد مسجّل مسبقاً.' );
	}
	if ( strlen( $pass ) < 8 ) {
		return new WP_Error( 'osoul_weak', 'كلمة مرور الدخول يجب أن تكون 8 أحرف على الأقل.' );
	}

	$user_id = wp_insert_user( array(
		'user_login'   => $email,
		'user_email'   => $email,
		'user_pass'    => $pass,
		'display_name' => $name,
		'nickname'     => $name,
		'role'         => 'osoul_employee',
	) );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, '_osoul_phone', $phone );
	update_user_meta( $user_id, '_osoul_active', 1 );
	// Pre-fill the mailbox login with the same address (they usually match).
	update_user_meta( $user_id, '_osoul_mail_email', $email );
	update_user_meta( $user_id, '_osoul_mail_from', $name );

	return (int) $user_id;
}

/** All employee accounts (WP_User objects). */
function osoul_employee_all() {
	return get_users( array(
		'role'    => 'osoul_employee',
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'number'  => 500,
	) );
}

/* =========================================================================
 *  wp-admin management screen — "بريد الموظفين"
 * ====================================================================== */

add_action( 'admin_menu', function () {
	add_menu_page(
		'بريد الموظفين',
		'بريد الموظفين',
		'manage_options',
		'osoul-employees',
		'osoul_employees_admin_page',
		'dashicons-email-alt',
		27
	);
} );

/** Handle add / suspend / reset / delete actions (admin-post). */
add_action( 'admin_post_osoul_employee_action', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'غير مصرح.' );
	}
	check_admin_referer( 'osoul_employee_action' );

	$action  = sanitize_key( $_POST['do'] ?? '' );
	$notice  = '';
	$type    = 'ok';

	if ( 'add' === $action ) {
		$res = osoul_employee_create( array(
			'name'     => wp_unslash( $_POST['name'] ?? '' ),
			'email'    => wp_unslash( $_POST['email'] ?? '' ),
			'password' => (string) ( $_POST['password'] ?? '' ),
			'phone'    => wp_unslash( $_POST['phone'] ?? '' ),
		) );
		if ( is_wp_error( $res ) ) {
			$notice = $res->get_error_message();
			$type   = 'err';
		} else {
			$notice = 'تم إنشاء حساب الموظف. أعطه رابط الدخول: ' . home_url( '/dashboard/' );
		}
	} elseif ( 'bulk' === $action ) {
		$raw   = (string) wp_unslash( $_POST['bulk'] ?? '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$added = 0; $dupe = 0; $err = 0; $errs = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { continue; }
			// Accept tab / comma / pipe separated columns: Name, Email, Password[, Phone]
			$parts = array_map( 'trim', preg_split( '/\t|,|\|/', $line ) );
			$name  = $parts[0] ?? '';
			$email = $parts[1] ?? '';
			$pass  = $parts[2] ?? '';
			$phone = $parts[3] ?? '';
			// Skip a header row if pasted.
			if ( 'name' === strtolower( $name ) || 'email' === strtolower( $email ) || false === strpos( $email, '@' ) ) { continue; }
			if ( strlen( $pass ) < 8 ) { $pass = wp_generate_password( 12, false ); }
			$res = osoul_employee_create( array( 'name' => $name, 'email' => $email, 'password' => $pass, 'phone' => $phone ) );
			if ( is_wp_error( $res ) ) {
				if ( 'osoul_dupe' === $res->get_error_code() ) { $dupe++; }
				else { $err++; if ( count( $errs ) < 6 ) { $errs[] = $email . ' (' . $res->get_error_message() . ')'; } }
			} else {
				$added++;
			}
		}
		$notice = 'تمت إضافة ' . $added . ' موظف — موجودون مسبقاً (تم تخطّيهم): ' . $dupe . ' — أخطاء: ' . $err;
		if ( $errs ) { $notice .= ' | ' . implode( ' ؛ ', $errs ); }
		if ( $err > 0 ) { $type = 'err'; }
	} elseif ( in_array( $action, array( 'suspend', 'activate', 'reset', 'delete', 'unlink' ), true ) ) {
		$uid = (int) ( $_POST['uid'] ?? 0 );
		if ( ! $uid || ! osoul_is_employee( $uid ) ) {
			$notice = 'حساب غير صالح.';
			$type   = 'err';
		} elseif ( 'suspend' === $action ) {
			update_user_meta( $uid, '_osoul_active', 0 );
			$notice = 'تم إيقاف الحساب.';
		} elseif ( 'activate' === $action ) {
			update_user_meta( $uid, '_osoul_active', 1 );
			$notice = 'تم تفعيل الحساب.';
		} elseif ( 'reset' === $action ) {
			$pw = (string) ( $_POST['password'] ?? '' );
			if ( strlen( $pw ) < 8 ) {
				$notice = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
				$type   = 'err';
			} else {
				wp_set_password( $pw, $uid );
				$notice = 'تم تحديث كلمة مرور الدخول.';
			}
		} elseif ( 'unlink' === $action ) {
			osoul_mail_forget( $uid );
			$notice = 'تم فصل ربط البريد. سيعيد الموظف إدخال بيانات بريده.';
		} elseif ( 'delete' === $action ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $uid );
			$notice = 'تم حذف الحساب.';
		}
	} else {
		$notice = 'إجراء غير معروف.';
		$type   = 'err';
	}

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'osoul-employees', 'osoul_notice' => rawurlencode( $notice ), 'osoul_type' => $type ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

/** Render the management page. */
function osoul_employees_admin_page() {
	$notice = isset( $_GET['osoul_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['osoul_notice'] ) ) : '';
	$ntype  = ( isset( $_GET['osoul_type'] ) && 'err' === $_GET['osoul_type'] ) ? 'error' : 'success';
	$emps   = osoul_employee_all();
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px">
			<span class="dashicons dashicons-email-alt" style="font-size:28px;width:28px;height:28px"></span>
			بريد الموظفين — Employee Mailboxes
		</h1>
		<p class="description" style="max-width:760px">
			أضِف حسابات الموظفين هنا (الاسم + البريد + كلمة مرور الدخول). بعدها يدخل الموظف على
			<code><?php echo esc_html( home_url( '/dashboard/' ) ); ?></code> بنفس البريد وكلمة المرور،
			ويربط صندوق بريده من هوستنجر مرة واحدة، ثم يرسل ويستقبل من داخل اللوحة.
			صندوق كل موظف خاص به — لا أحد غيره يطّلع عليه.
		</p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $ntype ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<h2 style="margin-top:24px">إضافة موظف</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="osoul-emp-form" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;max-width:820px">
			<?php wp_nonce_field( 'osoul_employee_action' ); ?>
			<input type="hidden" name="action" value="osoul_employee_action">
			<input type="hidden" name="do" value="add">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="osoul-emp-name">الاسم *</label></th>
					<td><input name="name" id="osoul-emp-name" type="text" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="osoul-emp-email">البريد الإلكتروني *</label></th>
					<td>
						<input name="email" id="osoul-emp-email" type="email" class="regular-text" required placeholder="name@osoulalbinaa.com" dir="ltr">
						<p class="description">نفس بريد الموظف على هوستنجر — يُستخدم للدخول وكذلك للبريد.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="osoul-emp-pass">كلمة مرور الدخول *</label></th>
					<td>
						<input name="password" id="osoul-emp-pass" type="text" class="regular-text" required minlength="8" dir="ltr" value="<?php echo esc_attr( wp_generate_password( 12, false ) ); ?>">
						<button type="button" class="button" onclick="var p=document.getElementById('osoul-emp-pass');p.value=Math.random().toString(36).slice(-4)+Math.random().toString(36).toUpperCase().slice(-4)+Math.floor(Math.random()*90+10);">توليد جديد</button>
						<p class="description">كلمة مرور دخول اللوحة (غير كلمة مرور البريد). سلّمها للموظف.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="osoul-emp-phone">رقم الجوال</label></th>
					<td><input name="phone" id="osoul-emp-phone" type="text" class="regular-text" dir="ltr"></td>
				</tr>
			</table>
			<?php submit_button( 'إضافة الموظف', 'primary', 'submit', false ); ?>
		</form>

		<h2 style="margin-top:26px">إضافة جماعية (دفعة واحدة)</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;max-width:820px">
			<?php wp_nonce_field( 'osoul_employee_action' ); ?>
			<input type="hidden" name="action" value="osoul_employee_action">
			<input type="hidden" name="do" value="bulk">
			<p class="description" style="margin:0 0 8px">
				الصق قائمة الموظفين — كل سطر: <code>الاسم، البريد، كلمة المرور</code> (مفصولة بفاصلة أو Tab).
				الموجودون مسبقاً يُتخطّون تلقائياً، فتقدر تلصق القائمة كاملة بدون قلق. لو تركت كلمة المرور فاضية، تُولَّد تلقائياً.
			</p>
			<textarea name="bulk" rows="10" class="large-text code" dir="ltr" placeholder="AHMED ALI, ahmed@osoulalbinaa.com, StrongPass12@&#10;SARA OMAR, sara@osoulalbinaa.com, StrongPass34@"></textarea>
			<?php submit_button( 'إضافة الكل', 'primary', 'submit', false ); ?>
		</form>

		<h2 style="margin-top:30px">الموظفون (<?php echo count( $emps ); ?>)</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>الاسم</th>
					<th>البريد</th>
					<th>الحالة</th>
					<th>ربط البريد</th>
					<th style="width:340px">إجراءات</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $emps ) : ?>
				<tr><td colspan="5">لا يوجد موظفون بعد.</td></tr>
			<?php else : foreach ( $emps as $u ) :
				$active    = osoul_employee_is_active( $u->ID );
				$connected = osoul_mail_is_connected( $u->ID );
				?>
				<tr>
					<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
					<td dir="ltr"><?php echo esc_html( $u->user_email ); ?></td>
					<td>
						<?php if ( $active ) : ?>
							<span style="color:#1a7e42;font-weight:600">● نشط</span>
						<?php else : ?>
							<span style="color:#b32d2e;font-weight:600">● موقوف</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $connected ) : ?>
							<span style="color:#1a7e42">مربوط</span>
						<?php else : ?>
							<span style="color:#8a8a8a">غير مربوط</span>
						<?php endif; ?>
					</td>
					<td>
						<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
							<?php // Suspend / activate ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'osoul_employee_action' ); ?>
								<input type="hidden" name="action" value="osoul_employee_action">
								<input type="hidden" name="do" value="<?php echo $active ? 'suspend' : 'activate'; ?>">
								<input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
								<button class="button button-small"><?php echo $active ? 'إيقاف' : 'تفعيل'; ?></button>
							</form>

							<?php // Reset dashboard password ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;gap:4px;align-items:center" onsubmit="return this.password.value.length>=8||(alert('كلمة المرور 8 أحرف على الأقل'),false)">
								<?php wp_nonce_field( 'osoul_employee_action' ); ?>
								<input type="hidden" name="action" value="osoul_employee_action">
								<input type="hidden" name="do" value="reset">
								<input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
								<input type="text" name="password" placeholder="كلمة مرور جديدة" style="width:130px" dir="ltr">
								<button class="button button-small">تغيير الدخول</button>
							</form>

							<?php if ( $connected ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('فصل ربط البريد لهذا الموظف؟')">
								<?php wp_nonce_field( 'osoul_employee_action' ); ?>
								<input type="hidden" name="action" value="osoul_employee_action">
								<input type="hidden" name="do" value="unlink">
								<input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
								<button class="button button-small">فصل البريد</button>
							</form>
							<?php endif; ?>

							<?php // Delete ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('حذف حساب الموظف نهائياً؟')">
								<?php wp_nonce_field( 'osoul_employee_action' ); ?>
								<input type="hidden" name="action" value="osoul_employee_action">
								<input type="hidden" name="do" value="delete">
								<input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
								<button class="button button-small button-link-delete" style="color:#b32d2e">حذف</button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
