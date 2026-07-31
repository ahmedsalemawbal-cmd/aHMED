<?php
/**
 * الداش بورد المستقلة — صفحة على الموقع (خارج لوحة ووردبريس) لها بوابة دخول خاصة.
 * الموظف يسجّل دخوله فيرى: التسجيلات + المعرض + المعلمون + التقييمات (+ الموظفون للمدير).
 *
 * الأمان: تعتمد على مصادقة ووردبريس نفسها (wp_signon) لكن بواجهة مخصّصة،
 * وكل عملية محميّة بـ nonce + صلاحية falak_manage + فحص نوع المحتوى.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * إنشاء دور «موظف الفلك» ومنح المدير صلاحية الإدارة.
 */
function falak_create_roles() {
	add_role( 'falak_staff', 'موظف الفلك', array( 'read' => true, 'falak_manage' => true ) );
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( 'falak_manage' ) ) {
		$admin->add_cap( 'falak_manage' );
	}
	// تأكيد الصلاحية للموظف (لو كان الدور موجودًا مسبقًا).
	$staff = get_role( 'falak_staff' );
	if ( $staff && ! $staff->has_cap( 'falak_manage' ) ) {
		$staff->add_cap( 'falak_manage' );
	}
}

/**
 * صلاحية الوصول للداش بورد.
 */
function falak_can_dash() {
	return is_user_logged_in() && current_user_can( 'falak_manage' );
}

/**
 * رابط صفحة الداش بورد.
 */
function falak_dash_url( $args = array() ) {
	$id  = function_exists( 'falak_page_id' ) ? falak_page_id( 'dashboard' ) : 0;
	$url = $id ? get_permalink( $id ) : home_url( '/dashboard/' );
	if ( ! $url ) {
		$url = home_url( '/dashboard/' );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * إخفاء شريط الأدمن وإبعاد الموظفين عن لوحة ووردبريس.
 */
add_action( 'after_setup_theme', function () {
	if ( is_user_logged_in() && current_user_can( 'falak_manage' ) && ! current_user_can( 'manage_options' ) ) {
		show_admin_bar( false );
	}
} );
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() ) {
		return;
	}
	if ( current_user_can( 'falak_manage' ) && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( falak_dash_url() );
		exit;
	}
} );

/**
 * منع فهرسة صفحة الداش بورد.
 */
add_filter( 'wp_robots', function ( $robots ) {
	if ( function_exists( 'falak_current_page_key' ) && 'dashboard' === falak_current_page_key() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

/* ─────────────────────────────────────────
   المتحكّم (يعمل مبكرًا قبل إرسال الرؤوس)
───────────────────────────────────────────*/
add_action( 'template_redirect', 'falak_dashboard_controller' );
function falak_dashboard_controller() {
	if ( ! function_exists( 'falak_current_page_key' ) || 'dashboard' !== falak_current_page_key() ) {
		return;
	}
	$dash = falak_dash_url();

	// تسجيل الخروج.
	if ( isset( $_GET['falak_logout'] ) ) {
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'falak_logout' ) ) {
			wp_logout();
		}
		wp_safe_redirect( $dash );
		exit;
	}

	// تسجيل الدخول.
	if ( isset( $_POST['falak_login'] ) ) {
		falak_dash_do_login( $dash );
		exit;
	}

	// إجراءات الداش بورد.
	if ( isset( $_POST['falak_action'] ) ) {
		falak_dash_do_action( $dash );
		exit;
	}
}

/**
 * معالجة تسجيل الدخول.
 */
function falak_dash_do_login( $dash ) {
	$nonce = isset( $_POST['falak_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['falak_login_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'falak_login' ) ) {
		wp_safe_redirect( add_query_arg( 'login', 'failed', $dash ) );
		exit;
	}
	$creds = array(
		'user_login'    => isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '',
		'user_password' => isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '', // لا يُنقّى — مصادقة ووردبريس.
		'remember'      => true,
	);
	$user = wp_signon( $creds, is_ssl() );
	if ( is_wp_error( $user ) ) {
		wp_safe_redirect( add_query_arg( 'login', 'failed', $dash ) );
		exit;
	}
	if ( ! user_can( $user, 'falak_manage' ) ) {
		wp_logout();
		wp_safe_redirect( add_query_arg( 'login', 'denied', $dash ) );
		exit;
	}
	wp_set_current_user( $user->ID );
	wp_safe_redirect( $dash );
	exit;
}

/**
 * رفع ملف صورة من الداش بورد. يُرجع الرابط أو ''.
 */
function falak_dash_upload( $field ) {
	if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
		return '';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$overrides = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		),
	);
	$file = wp_handle_upload( $_FILES[ $field ], $overrides ); // phpcs:ignore
	if ( isset( $file['url'] ) ) {
		return esc_url_raw( $file['url'] );
	}
	return '';
}

/**
 * معالجة إجراءات الداش بورد (PRG).
 */
function falak_dash_do_action( $dash ) {
	if ( ! falak_can_dash() ) {
		wp_safe_redirect( $dash );
		exit;
	}
	$nonce = isset( $_POST['falak_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['falak_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'falak_dash' ) ) {
		wp_safe_redirect( add_query_arg( 'msg', 'err', $dash ) );
		exit;
	}
	$action = sanitize_key( wp_unslash( $_POST['falak_action'] ) );
	$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'students';
	$msg    = 'ok';
	$P      = wp_unslash( $_POST ); // phpcs:ignore

	switch ( $action ) {
		case 'add_student':
			$name  = sanitize_text_field( $P['student_name'] ?? '' );
			$phone = sanitize_text_field( $P['guardian_phone'] ?? '' );
			if ( '' === $name || '' === $phone ) { $msg = 'err'; break; }
			$id = wp_insert_post( array(
				'post_type'   => 'falak_enroll',
				'post_status' => 'private',
				'post_title'  => $name . ' — ' . $phone,
			), true );
			if ( is_wp_error( $id ) ) { $msg = 'err'; break; }
			$map = array( 'section', 'student_name', 'grade', 'guardian_name', 'guardian_phone', 'guardian_email', 'notes' );
			foreach ( $map as $f ) {
				$v = 'notes' === $f ? sanitize_textarea_field( $P[ $f ] ?? '' ) : ( 'guardian_email' === $f ? sanitize_email( $P[ $f ] ?? '' ) : sanitize_text_field( $P[ $f ] ?? '' ) );
				update_post_meta( $id, '_falak_' . $f, $v );
			}
			update_post_meta( $id, '_falak_status', 'new' );
			$msg = 'added';
			break;

		case 'set_status':
			$id = (int) ( $P['id'] ?? 0 );
			$st = sanitize_key( $P['status'] ?? '' );
			if ( $id && falak_post_is( $id, 'falak_enroll' ) && in_array( $st, array( 'new', 'contacted', 'enrolled', 'closed' ), true ) ) {
				update_post_meta( $id, '_falak_status', $st );
			} else { $msg = 'err'; }
			break;

		case 'del_student':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_enroll' ) ) { wp_delete_post( $id, true ); $msg = 'deleted'; } else { $msg = 'err'; }
			break;

		case 'add_photo':
			$cap = sanitize_text_field( $P['caption'] ?? '' );
			$img = falak_dash_upload( 'photo' );
			if ( ! $img && ! empty( $P['img_url'] ) ) { $img = esc_url_raw( $P['img_url'] ); }
			if ( ! $img ) { $msg = 'err'; break; }
			$id = wp_insert_post( array( 'post_type' => 'falak_photo', 'post_status' => 'publish', 'post_title' => $cap ), true );
			if ( is_wp_error( $id ) ) { $msg = 'err'; break; }
			update_post_meta( $id, '_falak_img', $img );
			$msg = 'added';
			break;

		case 'del_photo':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_photo' ) ) { wp_delete_post( $id, true ); $msg = 'deleted'; } else { $msg = 'err'; }
			break;

		case 'add_teacher':
			$name = sanitize_text_field( $P['name'] ?? '' );
			if ( '' === $name ) { $msg = 'err'; break; }
			$img = falak_dash_upload( 'photo' );
			if ( ! $img && ! empty( $P['img_url'] ) ) { $img = esc_url_raw( $P['img_url'] ); }
			$id = wp_insert_post( array( 'post_type' => 'falak_teacher', 'post_status' => 'publish', 'post_title' => $name ), true );
			if ( is_wp_error( $id ) ) { $msg = 'err'; break; }
			update_post_meta( $id, '_falak_role', sanitize_text_field( $P['role'] ?? '' ) );
			update_post_meta( $id, '_falak_note', sanitize_text_field( $P['note'] ?? '' ) );
			if ( $img ) { update_post_meta( $id, '_falak_img', $img ); }
			$msg = 'added';
			break;

		case 'del_teacher':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_teacher' ) ) { wp_delete_post( $id, true ); $msg = 'deleted'; } else { $msg = 'err'; }
			break;

		case 'approve_review':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_review' ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) ); $msg = 'ok'; } else { $msg = 'err'; }
			break;

		case 'hide_review':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_review' ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) ); $msg = 'ok'; } else { $msg = 'err'; }
			break;

		case 'del_review':
			$id = (int) ( $P['id'] ?? 0 );
			if ( $id && falak_post_is( $id, 'falak_review' ) ) { wp_delete_post( $id, true ); $msg = 'deleted'; } else { $msg = 'err'; }
			break;

		case 'add_staff':
			if ( ! current_user_can( 'manage_options' ) ) { $msg = 'err'; break; }
			$login = sanitize_user( $P['user_login'] ?? '', true );
			$email = sanitize_email( $P['user_email'] ?? '' );
			$pass  = (string) ( $P['user_pass'] ?? '' );
			$disp  = sanitize_text_field( $P['display_name'] ?? $login );
			if ( '' === $login || strlen( $pass ) < 6 ) { $msg = 'staffbad'; break; }
			if ( username_exists( $login ) || ( $email && email_exists( $email ) ) ) { $msg = 'staffdup'; break; }
			$uid = wp_insert_user( array(
				'user_login'   => $login,
				'user_pass'    => $pass,
				'user_email'   => $email,
				'display_name' => $disp,
				'role'         => 'falak_staff',
			) );
			$msg = is_wp_error( $uid ) ? 'err' : 'added';
			break;

		case 'del_staff':
			if ( ! current_user_can( 'manage_options' ) ) { $msg = 'err'; break; }
			$uid = (int) ( $P['id'] ?? 0 );
			$u   = $uid ? get_userdata( $uid ) : false;
			if ( $u && in_array( 'falak_staff', (array) $u->roles, true ) && ! user_can( $u, 'manage_options' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $uid );
				$msg = 'deleted';
			} else { $msg = 'err'; }
			break;

		default:
			$msg = 'err';
	}

	wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'msg' => $msg ), $dash ) );
	exit;
}

/**
 * فحص نوع المنشور.
 */
function falak_post_is( $id, $type ) {
	$p = get_post( $id );
	return $p && $p->post_type === $type;
}

/* ─────────────────────────────────────────
   العرض
───────────────────────────────────────────*/
function falak_tpl_dashboard() {
	if ( ! falak_can_dash() ) {
		falak_dash_login_view();
		return;
	}
	falak_dash_app_view();
}

/**
 * شاشة تسجيل الدخول.
 */
function falak_dash_login_view() {
	$err = isset( $_GET['login'] ) ? sanitize_key( $_GET['login'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<section class="fk-login">
		<div class="fk-login-card">
			<div class="fk-login-logo"><?php falak_logo_mark( 'fkh-logo' ); ?></div>
			<h1>لوحة تحكم المدرسة</h1>
			<p>سجّل دخولك للوصول إلى إدارة التسجيلات والمحتوى.</p>
			<?php if ( 'failed' === $err ) : ?>
				<div class="fk-login-msg err">بيانات الدخول غير صحيحة، حاول مرة أخرى.</div>
			<?php elseif ( 'denied' === $err ) : ?>
				<div class="fk-login-msg err">هذا الحساب لا يملك صلاحية الوصول للوحة التحكم.</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>">
				<?php wp_nonce_field( 'falak_login', 'falak_login_nonce' ); ?>
				<input type="hidden" name="falak_login" value="1">
				<div class="fk-field">
					<label>اسم المستخدم أو البريد</label>
					<input type="text" name="log" required autocomplete="username" autofocus>
				</div>
				<div class="fk-field">
					<label>كلمة المرور</label>
					<input type="password" name="pwd" required autocomplete="current-password">
				</div>
				<button type="submit" class="fk-btn fk-btn-p" style="width:100%;justify-content:center"><?php falak_icon( 'shield', 18 ); ?> تسجيل الدخول</button>
			</form>
			<a class="fk-login-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← العودة للموقع</a>
		</div>
	</section>
	<?php
}

/**
 * واجهة الداش بورد بعد الدخول.
 */
function falak_dash_app_view() {
	$user = wp_get_current_user();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'students'; // phpcs:ignore WordPress.Security.NonceVerification
	$msg  = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';         // phpcs:ignore WordPress.Security.NonceVerification
	$is_admin = current_user_can( 'manage_options' );

	$tabs = array(
		'students' => array( 'التسجيلات', 'users' ),
		'gallery'  => array( 'المعرض', 'image' ),
		'teachers' => array( 'المعلمون', 'user' ),
		'reviews'  => array( 'التقييمات', 'star' ),
	);
	if ( $is_admin ) {
		$tabs['staff'] = array( 'الموظفون', 'shield' );
	}
	if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'students'; }
	?>
	<div class="fk-dash">
		<header class="fk-dash-top">
			<div class="fk-dash-brand"><?php falak_logo_mark( 'fkh-logo' ); ?><b>لوحة تحكم الفلك المنير</b></div>
			<div class="fk-dash-user">
				<span>مرحبًا، <?php echo esc_html( $user->display_name ); ?></span>
				<a class="fk-dash-visit" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php falak_icon( 'compass', 16 ); ?> الموقع</a>
				<a class="fk-dash-logout" href="<?php echo esc_url( wp_nonce_url( falak_dash_url( array( 'falak_logout' => 1 ) ), 'falak_logout' ) ); ?>"><?php falak_icon( 'arrow', 16 ); ?> خروج</a>
			</div>
		</header>

		<?php if ( $msg ) : ?>
			<div class="fk-dash-toast <?php echo 'err' === $msg ? 'err' : 'ok'; ?>">
				<?php
				$toasts = array(
					'added'    => 'تمت الإضافة بنجاح.',
					'deleted'  => 'تم الحذف.',
					'ok'       => 'تم الحفظ.',
					'err'      => 'تعذّر تنفيذ العملية، تحقّق من البيانات.',
					'staffbad' => 'تأكد من اسم المستخدم وكلمة مرور لا تقل عن 6 أحرف.',
					'staffdup' => 'اسم المستخدم أو البريد مستخدم مسبقًا.',
				);
				echo esc_html( $toasts[ $msg ] ?? 'تم.' );
				?>
			</div>
		<?php endif; ?>

		<nav class="fk-dash-tabs">
			<?php foreach ( $tabs as $key => $meta ) : ?>
				<a href="<?php echo esc_url( falak_dash_url( array( 'tab' => $key ) ) ); ?>" class="<?php echo $tab === $key ? 'active' : ''; ?>">
					<?php falak_icon( $meta[1], 18 ); ?> <?php echo esc_html( $meta[0] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="fk-dash-body">
			<?php
			switch ( $tab ) {
				case 'gallery':  falak_dash_tab_gallery(); break;
				case 'teachers': falak_dash_tab_teachers(); break;
				case 'reviews':  falak_dash_tab_reviews(); break;
				case 'staff':    if ( $is_admin ) { falak_dash_tab_staff(); } break;
				default:         falak_dash_tab_students();
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * حقل nonce + action مشترك للنماذج.
 */
function falak_dash_form_fields( $action, $tab ) {
	wp_nonce_field( 'falak_dash', 'falak_nonce' );
	echo '<input type="hidden" name="falak_action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
}

/* ---- تبويب: التسجيلات ---- */
function falak_dash_tab_students() {
	$posts = get_posts( array( 'post_type' => 'falak_enroll', 'post_status' => 'any', 'numberposts' => 300, 'orderby' => 'date', 'order' => 'DESC' ) );
	$labels = array( 'new' => 'جديد', 'contacted' => 'تم التواصل', 'enrolled' => 'مُسجّل', 'closed' => 'مغلق' );
	$grades_boys  = wp_json_encode( falak_grades_boys(), JSON_UNESCAPED_UNICODE );
	?>
	<div class="fk-dash-head">
		<h2>التسجيلات <span class="fk-dash-count"><?php echo count( $posts ); ?></span></h2>
		<button class="fk-dash-addbtn" type="button" data-toggle="add-student"><?php falak_icon( 'user', 16 ); ?> تسجيل طالب جديد</button>
	</div>

	<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-dash-form" id="add-student" hidden>
		<?php falak_dash_form_fields( 'add_student', 'students' ); ?>
		<div class="fk-form-row">
			<div class="fk-field"><label>نوع الطالب</label>
				<select name="section" id="dash-section" data-grades='<?php echo esc_attr( $grades_boys ); ?>'>
					<option value="بنين">طالب</option><option value="بنات">طالبة</option>
				</select>
			</div>
			<div class="fk-field"><label>اسم الطالب *</label><input type="text" name="student_name" required></div>
		</div>
		<div class="fk-form-row">
			<div class="fk-field"><label>المرحلة الدراسية</label>
				<select name="grade" id="dash-grade"><?php foreach ( falak_grades_boys() as $g ) : ?><option value="<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $g ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="fk-field"><label>اسم ولي الأمر</label><input type="text" name="guardian_name"></div>
		</div>
		<div class="fk-form-row">
			<div class="fk-field"><label>جوال ولي الأمر *</label><input type="tel" name="guardian_phone" dir="ltr" required></div>
			<div class="fk-field"><label>البريد الإلكتروني</label><input type="email" name="guardian_email" dir="ltr"></div>
		</div>
		<div class="fk-field"><label>ملاحظات</label><textarea name="notes"></textarea></div>
		<button type="submit" class="fk-btn fk-btn-p"><?php falak_icon( 'check', 16 ); ?> حفظ الطالب</button>
	</form>

	<?php if ( empty( $posts ) ) : ?>
		<div class="fk-dash-empty"><?php falak_icon( 'users', 40 ); ?><p>لا توجد تسجيلات بعد.</p></div>
	<?php else : ?>
	<div class="fk-dash-tablewrap">
		<table class="fk-dash-table">
			<thead><tr><th>الطالب</th><th>النوع</th><th>المرحلة</th><th>ولي الأمر</th><th>الجوال</th><th>الحالة</th><th>إجراءات</th></tr></thead>
			<tbody>
			<?php foreach ( $posts as $p ) :
				$sec   = get_post_meta( $p->ID, '_falak_section', true );
				$grade = get_post_meta( $p->ID, '_falak_grade', true );
				$gname = get_post_meta( $p->ID, '_falak_guardian_name', true );
				$phone = get_post_meta( $p->ID, '_falak_guardian_phone', true );
				$sname = get_post_meta( $p->ID, '_falak_student_name', true ) ?: $p->post_title;
				$st    = get_post_meta( $p->ID, '_falak_status', true ) ?: 'new';
				$wa    = preg_replace( '/\D+/', '', (string) $phone );
				?>
				<tr>
					<td data-l="الطالب"><b><?php echo esc_html( $sname ); ?></b></td>
					<td data-l="النوع"><?php echo esc_html( $sec ); ?></td>
					<td data-l="المرحلة"><?php echo esc_html( $grade ); ?></td>
					<td data-l="ولي الأمر"><?php echo esc_html( $gname ); ?></td>
					<td data-l="الجوال" dir="ltr"><?php echo esc_html( $phone ); ?></td>
					<td data-l="الحالة">
						<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-inline">
							<?php falak_dash_form_fields( 'set_status', 'students' ); ?>
							<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
							<select name="status" onchange="this.form.submit()">
								<?php foreach ( $labels as $k => $lab ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st, $k ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</form>
					</td>
					<td data-l="إجراءات" class="fk-dash-actions">
						<?php if ( $wa ) : ?>
							<a class="ic wa" href="https://wa.me/<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener" title="واتساب"><?php falak_icon( 'whatsapp', 16 ); ?></a>
							<a class="ic" href="tel:<?php echo esc_attr( $phone ); ?>" title="اتصال"><?php falak_icon( 'phone', 16 ); ?></a>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-inline" onsubmit="return confirm('حذف هذا الطلب؟')">
							<?php falak_dash_form_fields( 'del_student', 'students' ); ?>
							<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
							<button class="ic del" type="submit" title="حذف"><?php falak_icon( 'close', 16 ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif;
}

/* ---- تبويب: المعرض ---- */
function falak_dash_tab_gallery() {
	$photos = get_posts( array( 'post_type' => 'falak_photo', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	?>
	<div class="fk-dash-head"><h2>المعرض <span class="fk-dash-count"><?php echo count( $photos ); ?></span></h2></div>
	<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-dash-form open" enctype="multipart/form-data">
		<?php falak_dash_form_fields( 'add_photo', 'gallery' ); ?>
		<div class="fk-form-row">
			<div class="fk-field"><label>الصورة (رفع من الجهاز)</label><input type="file" name="photo" accept="image/*"></div>
			<div class="fk-field"><label>أو رابط صورة</label><input type="url" name="img_url" dir="ltr" placeholder="https://..."></div>
		</div>
		<div class="fk-field"><label>وصف الصورة (اختياري)</label><input type="text" name="caption" placeholder="مثال: الأنشطة الرياضية"></div>
		<button type="submit" class="fk-btn fk-btn-p"><?php falak_icon( 'image', 16 ); ?> إضافة صورة</button>
	</form>

	<?php if ( empty( $photos ) ) : ?>
		<div class="fk-dash-empty"><?php falak_icon( 'image', 40 ); ?><p>لا توجد صور بعد.</p></div>
	<?php else : ?>
	<div class="fk-dash-grid">
		<?php foreach ( $photos as $p ) : $img = get_post_meta( $p->ID, '_falak_img', true ); ?>
			<div class="fk-dash-card">
				<div class="thumb"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?></div>
				<div class="meta"><span><?php echo esc_html( $p->post_title ?: 'بدون وصف' ); ?></span>
					<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" onsubmit="return confirm('حذف الصورة؟')">
						<?php falak_dash_form_fields( 'del_photo', 'gallery' ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
						<button class="ic del" type="submit" title="حذف"><?php falak_icon( 'close', 15 ); ?></button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif;
}

/* ---- تبويب: المعلمون ---- */
function falak_dash_tab_teachers() {
	$teachers = get_posts( array( 'post_type' => 'falak_teacher', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	?>
	<div class="fk-dash-head"><h2>المعلمون <span class="fk-dash-count"><?php echo count( $teachers ); ?></span></h2></div>
	<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-dash-form open" enctype="multipart/form-data">
		<?php falak_dash_form_fields( 'add_teacher', 'teachers' ); ?>
		<div class="fk-form-row">
			<div class="fk-field"><label>الاسم *</label><input type="text" name="name" required></div>
			<div class="fk-field"><label>المسمّى / المادة</label><input type="text" name="role" placeholder="مثال: معلم رياضيات"></div>
		</div>
		<div class="fk-form-row">
			<div class="fk-field"><label>المؤهل (اختياري)</label><input type="text" name="note" placeholder="مثال: بكالوريوس"></div>
			<div class="fk-field"><label>الصورة</label><input type="file" name="photo" accept="image/*"></div>
		</div>
		<button type="submit" class="fk-btn fk-btn-p"><?php falak_icon( 'user', 16 ); ?> إضافة معلم</button>
	</form>

	<?php if ( empty( $teachers ) ) : ?>
		<div class="fk-dash-empty"><?php falak_icon( 'user', 40 ); ?><p>لم تُضِف معلمين بعد — تظهر حاليًا قائمة افتراضية في الموقع.</p></div>
	<?php else : ?>
	<div class="fk-dash-grid">
		<?php foreach ( $teachers as $p ) : $img = get_post_meta( $p->ID, '_falak_img', true ); $role = get_post_meta( $p->ID, '_falak_role', true ); ?>
			<div class="fk-dash-card">
				<div class="thumb round"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php else : ?><?php falak_icon( 'user', 30 ); ?><?php endif; ?></div>
				<div class="meta"><span><b><?php echo esc_html( $p->post_title ); ?></b><br><small><?php echo esc_html( $role ); ?></small></span>
					<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" onsubmit="return confirm('حذف المعلم؟')">
						<?php falak_dash_form_fields( 'del_teacher', 'teachers' ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
						<button class="ic del" type="submit" title="حذف"><?php falak_icon( 'close', 15 ); ?></button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif;
}

/* ---- تبويب: التقييمات ---- */
function falak_dash_tab_reviews() {
	$pending  = get_posts( array( 'post_type' => 'falak_review', 'post_status' => 'pending', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$approved = get_posts( array( 'post_type' => 'falak_review', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$link     = home_url( '/review/' );
	?>
	<div class="fk-dash-head"><h2>التقييمات</h2></div>

	<div class="fk-dash-linkbox">
		<span>رابط التقييم لأولياء الأمور:</span>
		<input type="text" readonly value="<?php echo esc_url( $link ); ?>" id="fk-review-link" onclick="this.select()">
		<button class="fk-btn fk-btn-o" type="button" data-copy="#fk-review-link"><?php falak_icon( 'check', 15 ); ?> نسخ</button>
	</div>

	<h3 class="fk-dash-sub">بانتظار الاعتماد <span class="fk-dash-count"><?php echo count( $pending ); ?></span></h3>
	<?php if ( empty( $pending ) ) : ?>
		<p class="fk-dash-muted">لا توجد تقييمات بانتظار الاعتماد.</p>
	<?php else : foreach ( $pending as $p ) : falak_dash_review_row( $p, false ); endforeach; endif; ?>

	<h3 class="fk-dash-sub">المعتمدة (تظهر في الموقع) <span class="fk-dash-count"><?php echo count( $approved ); ?></span></h3>
	<?php if ( empty( $approved ) ) : ?>
		<p class="fk-dash-muted">لا توجد تقييمات معتمدة — تظهر حاليًا تقييمات افتراضية في الموقع.</p>
	<?php else : foreach ( $approved as $p ) : falak_dash_review_row( $p, true ); endforeach; endif;
}

function falak_dash_review_row( $p, $approved ) {
	$role   = get_post_meta( $p->ID, '_falak_role', true );
	$rating = (int) ( get_post_meta( $p->ID, '_falak_rating', true ) ?: 5 );
	?>
	<div class="fk-dash-review">
		<div class="rv-main">
			<div class="rv-head"><b><?php echo esc_html( $p->post_title ); ?></b><small><?php echo esc_html( $role ); ?></small><?php echo falak_stars_html( $rating, 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p><?php echo esc_html( $p->post_content ); ?></p>
		</div>
		<div class="rv-actions">
			<?php if ( ! $approved ) : ?>
				<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>">
					<?php falak_dash_form_fields( 'approve_review', 'reviews' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
					<button class="fk-btn fk-btn-p sm" type="submit"><?php falak_icon( 'check', 15 ); ?> اعتماد</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>">
					<?php falak_dash_form_fields( 'hide_review', 'reviews' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
					<button class="fk-btn fk-btn-o sm" type="submit">إخفاء</button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" onsubmit="return confirm('حذف التقييم؟')">
				<?php falak_dash_form_fields( 'del_review', 'reviews' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
				<button class="fk-btn fk-btn-o sm del" type="submit"><?php falak_icon( 'close', 15 ); ?></button>
			</form>
		</div>
	</div>
	<?php
}

/* ---- تبويب: الموظفون (للمدير) ---- */
function falak_dash_tab_staff() {
	$staff = get_users( array( 'role' => 'falak_staff' ) );
	?>
	<div class="fk-dash-head"><h2>الموظفون <span class="fk-dash-count"><?php echo count( $staff ); ?></span></h2></div>
	<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" class="fk-dash-form open">
		<?php falak_dash_form_fields( 'add_staff', 'staff' ); ?>
		<div class="fk-form-row">
			<div class="fk-field"><label>الاسم الظاهر</label><input type="text" name="display_name"></div>
			<div class="fk-field"><label>اسم المستخدم *</label><input type="text" name="user_login" dir="ltr" required></div>
		</div>
		<div class="fk-form-row">
			<div class="fk-field"><label>البريد الإلكتروني</label><input type="email" name="user_email" dir="ltr"></div>
			<div class="fk-field"><label>كلمة المرور * (6 أحرف فأكثر)</label><input type="text" name="user_pass" dir="ltr" required></div>
		</div>
		<button type="submit" class="fk-btn fk-btn-p"><?php falak_icon( 'shield', 16 ); ?> إضافة موظف</button>
	</form>

	<?php if ( empty( $staff ) ) : ?>
		<div class="fk-dash-empty"><?php falak_icon( 'shield', 40 ); ?><p>لا يوجد موظفون بعد.</p></div>
	<?php else : ?>
	<div class="fk-dash-tablewrap">
		<table class="fk-dash-table">
			<thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>البريد</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $staff as $u ) : ?>
				<tr>
					<td data-l="الاسم"><?php echo esc_html( $u->display_name ); ?></td>
					<td data-l="المستخدم" dir="ltr"><?php echo esc_html( $u->user_login ); ?></td>
					<td data-l="البريد" dir="ltr"><?php echo esc_html( $u->user_email ); ?></td>
					<td class="fk-dash-actions">
						<form method="post" action="<?php echo esc_url( falak_dash_url() ); ?>" onsubmit="return confirm('حذف الموظف؟')">
							<?php falak_dash_form_fields( 'del_staff', 'staff' ); ?>
							<input type="hidden" name="id" value="<?php echo (int) $u->ID; ?>">
							<button class="ic del" type="submit" title="حذف"><?php falak_icon( 'close', 16 ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif;
}
