<?php
/**
 * B2B portal — the /dashboard application shell.
 *
 * One URL serves three experiences through a single-page app:
 *   - not logged in            → unified login screen
 *   - ?invite=<token>          → set-your-password screen (first-time accounts)
 *   - logged-in portal user    → the SPA (admin / branch / rep), driven by REST
 *
 * The page is rendered standalone (theme bypassed) for speed and focus, exactly
 * like the previous dashboard, but the body is now a live app that talks to the
 * portal REST API and refreshes itself without reloading.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Intercept /dashboard before the theme. Priority 0 so it wins over the legacy
 * route in quotes-dashboard.php (which now only serves /quote/{token}).
 */
add_action( 'template_redirect', function () {
	if ( 'dashboard' === osoul_request_slug() ) {
		osoul_portal_route();
		exit;
	}
}, 0 );

/** Decide which screen to show and render it. */
function osoul_portal_route() {
	nocache_headers();
	status_header( 200 );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
	}

	// ── Logout ──
	if ( isset( $_GET['logout'] ) ) {
		wp_logout();
		wp_safe_redirect( home_url( '/dashboard/' ) );
		exit;
	}

	// ── Accept an invite (set password) ──
	if ( isset( $_POST['osoul_setpw'] ) ) {
		osoul_portal_handle_setpw(); // exits on success
		// On validation error, re-render the invite screen from the posted token.
		$u = osoul_portal_find_invite( wp_unslash( $_POST['token'] ?? '' ) );
		if ( $u ) {
			osoul_portal_render_invite( $u );
			return;
		}
	}
	if ( isset( $_GET['invite'] ) ) {
		$user = osoul_portal_find_invite( wp_unslash( $_GET['invite'] ) );
		if ( $user ) {
			osoul_portal_render_invite( $user );
			return;
		}
		// Invalid/expired token → fall through to login with a note.
		$invite_note = osoul_bi( 'انتهت صلاحية رابط الدعوة أو أنه غير صحيح. تواصل مع الإدارة لإصدار رابط جديد.', 'The invite link has expired or is invalid. Contact management to issue a new one.', true );
	}

	// ── Login ──
	$login_error = '';
	if ( isset( $_POST['osoul_login'] ) ) {
		$user = wp_signon( array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
			'user_password' => (string) ( $_POST['pwd'] ?? '' ),
			'remember'      => true,
		), is_ssl() );
		if ( is_wp_error( $user ) ) {
			$login_error = osoul_bi( 'بيانات الدخول غير صحيحة.', 'Incorrect login details.', true );
		} elseif ( ! osoul_portal_can_access( $user ) ) {
			wp_logout();
			$login_error = osoul_bi( 'هذا الحساب لا يملك صلاحية الدخول للوحة.', 'This account is not allowed to access the dashboard.', true );
		} elseif ( ! osoul_portal_is_active( $user->ID ) ) {
			wp_logout();
			$login_error = osoul_bi( 'تم إيقاف هذا الحساب. تواصل مع الإدارة.', 'This account has been suspended. Contact management.', true );
		} else {
			wp_safe_redirect( home_url( '/dashboard/' ) );
			exit;
		}
	}

	// ── Gate ──
	if ( ! is_user_logged_in() || ! osoul_portal_can_access() ) {
		osoul_portal_render_login( $login_error, isset( $invite_note ) ? $invite_note : '' );
		return;
	}
	if ( ! osoul_portal_is_active( get_current_user_id() ) ) {
		wp_logout();
		osoul_portal_render_login( osoul_bi( 'تم إيقاف هذا الحساب. تواصل مع الإدارة.', 'This account has been suspended. Contact management.', true ), '' );
		return;
	}

	osoul_portal_render_app();
}

/** Handle the set-password POST from the invite screen. */
function osoul_portal_handle_setpw() {
	$token = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['token'] ?? '' ) );
	$user  = osoul_portal_find_invite( $token );
	if ( ! $user || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'osoul_invite' ) ) {
		return; // re-render handled by caller
	}
	$pw  = (string) ( $_POST['pwd'] ?? '' );
	$pw2 = (string) ( $_POST['pwd2'] ?? '' );
	if ( strlen( $pw ) < 8 || $pw !== $pw2 ) {
		$GLOBALS['osoul_setpw_error'] = osoul_bi( 'كلمة المرور يجب أن تكون 8 أحرف على الأقل ومتطابقة.', 'The password must be at least 8 characters and match.', true );
		return;
	}
	osoul_portal_accept_invite( $user->ID, $pw );
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	wp_safe_redirect( home_url( '/dashboard/' ) );
	exit;
}

/* -------------------------------------------------------------------------
 *  Standalone page chrome
 * ---------------------------------------------------------------------- */

/** Chosen UI language for the standalone portal pages (ar default / en / ur — cookie kept in sync). */
function osoul_portal_lang() {
	$l = isset( $_COOKIE['osoul_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['osoul_lang'] ) ) : '';
	return ( 'en' === $l || 'ur' === $l ) ? $l : 'ar';
}

/**
 * Tri-language inline text: Arabic + English + Urdu spans, only the active one shown.
 * Urdu is looked up from the central dictionary by the Arabic source string; a dynamic
 * string can pass its Urdu explicitly as $ur.
 */
function osoul_bi( $ar, $en, $block = false, $ur = null ) {
	$lang = osoul_portal_lang();
	if ( null === $ur ) { $ur = osoul_ur( $ar, $en ); }
	$d   = $block ? 'block' : 'inline';
	$vis = static function ( $on ) use ( $d ) { return $on ? $d : 'none'; };
	return '<span data-lang="ar" style="display:' . $vis( 'ar' === $lang ) . '">' . esc_html( $ar ) . '</span>' .
		'<span data-lang="en" style="display:' . $vis( 'en' === $lang ) . '">' . esc_html( $en ) . '</span>' .
		'<span data-lang="ur" style="display:' . $vis( 'ur' === $lang ) . '">' . esc_html( $ur ) . '</span>';
}

/** Urdu translation for an Arabic UI string used on the standalone pages (falls back to $fallback/$ar). */
function osoul_ur( $ar, $fallback = '' ) {
	$d = osoul_ur_dict();
	if ( isset( $d[ $ar ] ) ) { return $d[ $ar ]; }
	return '' !== $fallback ? $fallback : $ar;
}

/** Central Urdu dictionary for the login / invite / customer / partner standalone pages. */
function osoul_ur_dict() {
	static $d = null;
	if ( null !== $d ) { return $d; }
	$d = array(
		// shared
		'البريد الإلكتروني' => 'ای میل', 'كلمة المرور' => 'پاس ورڈ', 'دخول' => 'لاگ اِن',
		'تسجيل الدخول' => 'لاگ اِن', 'تسجيل الخروج' => 'لاگ آؤٹ', 'رقم الجوال' => 'موبائل نمبر',
		'الاسم' => 'نام', 'تأكيد كلمة المرور' => 'پاس ورڈ کی تصدیق', 'مرحباً' => 'خوش آمدید',
		'المنتجات' => 'مصنوعات', 'الحالة' => 'حالت', 'الإجمالي' => 'کل', 'التاريخ' => 'تاریخ', 'ر.س' => 'ریال',
		'جاري التحميل…' => 'لوڈ ہو رہا ہے…',
		// portal login
		'بوابة أصول البناء' => 'اصول البناء پورٹل',
		'الدخول للمدير والفروع والمناديب والشركاء' => 'ایڈمن، برانچوں، نمائندوں اور پارٹنرز کے لیے لاگ اِن',
		'نسيت كلمة المرور؟' => 'پاس ورڈ بھول گئے؟', 'تسجيل شركة كشريك جديد' => 'نئی کمپنی بطور پارٹنر رجسٹر کریں',
		'انتهت صلاحية رابط الدعوة أو أنه غير صحيح. تواصل مع الإدارة لإصدار رابط جديد.' => 'دعوت لنک کی میعاد ختم ہو گئی یا غلط ہے۔ نیا لنک حاصل کرنے کے لیے انتظامیہ سے رابطہ کریں۔',
		'بيانات الدخول غير صحيحة.' => 'لاگ اِن کی تفصیلات غلط ہیں۔',
		'هذا الحساب لا يملك صلاحية الدخول للوحة.' => 'اس اکاؤنٹ کو ڈیش بورڈ تک رسائی کی اجازت نہیں۔',
		'تم إيقاف هذا الحساب. تواصل مع الإدارة.' => 'یہ اکاؤنٹ معطل کر دیا گیا ہے۔ انتظامیہ سے رابطہ کریں۔',
		// invite
		'كلمة المرور الجديدة' => 'نیا پاس ورڈ', 'تفعيل الحساب والدخول' => 'اکاؤنٹ فعال کر کے لاگ اِن کریں',
		'كلمة المرور يجب أن تكون 8 أحرف على الأقل ومتطابقة.' => 'پاس ورڈ کم از کم 8 حروف کا اور مماثل ہونا چاہیے۔',
		// customer auth
		'بوابة العملاء — سجّل دخولك لطلب عرض سعر ومتابعة طلباتك' => 'کسٹمر پورٹل — کوٹیشن کی درخواست اور اپنے آرڈرز کی پیروی کے لیے لاگ اِن کریں',
		'الدخول عبر Google' => 'Google سے لاگ اِن', 'أو بالبريد الإلكتروني' => 'یا ای میل سے',
		'حساب جديد' => 'نیا اکاؤنٹ', 'اسم الشركة' => 'کمپنی کا نام', 'إنشاء حساب' => 'اکاؤنٹ بنائیں',
		'← العودة للموقع' => '← ویب سائٹ پر واپس',
		// customer account
		'تقدر الآن تطلب عرض سعر وتتابع طلباتك وتراسل الفريق مباشرة.' => 'اب آپ کوٹیشن کی درخواست دے سکتے ہیں، اپنے آرڈرز کی پیروی کر سکتے ہیں اور ٹیم کو براہِ راست پیغام دے سکتے ہیں۔',
		'اطلب عرض سعر' => 'کوٹیشن کی درخواست', 'طلباتي' => 'میرے آرڈرز',
		'ما عندك طلبات بعد. أضف منتجات لقائمة العرض من الموقع وأرسلها.' => 'ابھی آپ کے کوئی آرڈرز نہیں۔ ویب سائٹ سے اپنی کوٹیشن فہرست میں مصنوعات شامل کریں اور بھیجیں۔',
		'رقم الطلب' => 'آرڈر نمبر', 'عرض السعر + PDF ←' => 'کوٹیشن + PDF ←', 'بانتظار التسعير' => 'قیمت کاری کے انتظار میں',
		'مراسلة الإدارة' => 'انتظامیہ کو پیغام',
		'تم إرسال رسالتك، سيتواصل معك الفريق قريباً.' => 'آپ کا پیغام بھیج دیا گیا، ٹیم جلد آپ سے رابطہ کرے گی۔',
		'إرسال' => 'بھیجیں',
		// quote stage labels (looked up by their Arabic text)
		'جديد' => 'نیا', 'قيد التسعير' => 'قیمت کاری', 'عرض مُرسل' => 'کوٹیشن بھیجا گیا',
		'تفاوض' => 'گفت و شنید', 'مقبول' => 'منظور', 'مرفوض' => 'مسترد',
		// partner register
		'الانضمام كشريك لأصول البناء' => 'اصول البناء میں بطور پارٹنر شامل ہوں',
		'سجّل بيانات شركتك. بعد اعتماد الإدارة ستصلك رسالة بريد لتفعيل حسابك والدخول للوحة الشركاء.' => 'اپنی کمپنی کی تفصیلات رجسٹر کریں۔ انتظامیہ کی منظوری کے بعد آپ کو اپنا اکاؤنٹ فعال کرنے اور پارٹنر پورٹل تک رسائی کے لیے ای میل موصول ہوگی۔',
		'اسم الشركة (عربي) *' => 'کمپنی کا نام (عربی) *', 'اسم الشركة (إنجليزي)' => 'کمپنی کا نام (انگریزی)',
		'البريد الإلكتروني (للدخول) *' => 'ای میل (لاگ اِن کے لیے) *', 'رقم الجوال *' => 'موبائل نمبر *',
		'الرقم الضريبي (VAT) *' => 'ٹیکس نمبر (VAT) *', 'السجل التجاري (CR)' => 'کمرشل رجسٹریشن (CR)',
		'العنوان الوطني' => 'قومی پتہ', 'الحساب البنكي / الآيبان *' => 'بینک اکاؤنٹ / IBAN *',
		'إرسال طلب الانضمام' => 'شمولیت کی درخواست بھیجیں', 'لديك حساب بالفعل؟' => 'پہلے سے اکاؤنٹ ہے؟',
		'تم استلام طلب الانضمام' => 'آپ کی شمولیت کی درخواست موصول ہو گئی', 'خطواتك التالية:' => 'آپ کے اگلے اقدامات:',
		'تراجع الإدارة بياناتك وتعتمد حسابك.' => 'انتظامیہ آپ کی تفصیلات کا جائزہ لے کر آپ کا اکاؤنٹ منظور کرتی ہے۔',
		'يصلك بريد إلكتروني فيه رابط تفعيل تختار منه كلمة المرور.' => 'آپ کو ایک ای میل موصول ہوتی ہے جس میں فعال کرنے کا لنک ہوتا ہے جہاں سے آپ پاس ورڈ منتخب کرتے ہیں۔',
		'بعدها تدخل لوحتك في أي وقت من صفحة الدخول.' => 'اس کے بعد آپ کسی بھی وقت لاگ اِن صفحے سے اپنے ڈیش بورڈ میں داخل ہو سکتے ہیں۔',
		'لم يصلك البريد بعد الاعتماد؟ تواصل مع الإدارة لتزويدك برابط التفعيل مباشرة.' => 'منظوری کے بعد ای میل نہیں ملی؟ فعال کرنے کا لنک براہِ راست حاصل کرنے کے لیے انتظامیہ سے رابطہ کریں۔',
		'صفحة الدخول' => 'لاگ اِن صفحہ', 'العودة للموقع' => 'ویب سائٹ پر واپس',
	);
	return $d;
}

/** The inline language switcher (ar/en/ur) shared by the standalone account pages. */
function osoul_portal_lang_switcher_js() {
	static $printed = false;
	if ( $printed ) { return ''; }
	$printed = true;
	ob_start();
	?>
	<script>
	function osoulPortalLang(l){
		l = (l === 'en' || l === 'ur') ? l : 'ar';
		try{ localStorage.setItem('osoul_lang', l); }catch(e){}
		document.cookie = 'osoul_lang=' + l + ';path=/;max-age=31536000;SameSite=Lax';
		var h = document.documentElement;
		h.setAttribute('lang', l); h.setAttribute('dir', l === 'en' ? 'ltr' : 'rtl');
		h.classList.toggle('lang-en', l === 'en');
		document.querySelectorAll('[data-lang]').forEach(function(el){
			var block = /^(DIV|P|H1|H2|H3|H4|SECTION|LABEL)$/.test(el.tagName);
			el.style.display = (el.getAttribute('data-lang') === l) ? (block ? 'block' : 'inline') : 'none';
		});
		document.querySelectorAll('select[data-osoul-lang]').forEach(function(s){ s.value = l; });
	}
	(function(){ try{ var v = localStorage.getItem('osoul_lang'); osoulPortalLang( (v==='en'||v==='ur') ? v : 'ar' ); }catch(e){} })();
	</script>
	<?php
	return ob_get_clean();
}

/** A language dropdown (العربية / English / اردو) for the account pages. */
function osoul_portal_lang_select() {
	$lang  = osoul_portal_lang();
	$names = array( 'ar' => 'العربية', 'en' => 'English', 'ur' => 'اردو' );
	ob_start();
	?>
	<span class="osp-langsel">
		<select data-osoul-lang onchange="osoulPortalLang(this.value)" aria-label="Language">
			<?php foreach ( $names as $code => $label ) : ?>
			<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
	<style>.osp-langsel select{border:1px solid #d8dee9;background:#fff;color:#0d1f3c;border-radius:9px;padding:7px 12px;font-family:inherit;font-weight:700;font-size:13px;cursor:pointer}</style>
	<?php echo osoul_portal_lang_switcher_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline script ?>
	<?php
	return ob_get_clean();
}

function osoul_portal_head( $title ) {
	$lang = osoul_portal_lang();
	$dir  = 'en' === $lang ? 'ltr' : 'rtl';
	?><!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>"<?php echo 'en' === $lang ? ' class="lang-en"' : ''; ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<script>/* apply saved light/dark before first paint (no flash) */(function(){try{var t=localStorage.getItem('osoul_theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&matchMedia('(prefers-color-scheme:dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<title><?php echo esc_html( $title ); ?> — أصول البناء</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
	<?php
	$css = OSOUL_DIR . 'assets/css/portal.css';
	$ver = is_readable( $css ) ? filemtime( $css ) : OSOUL_VERSION;
	echo '<link rel="stylesheet" href="' . esc_url( OSOUL_URL . 'assets/css/portal.css?v=' . $ver ) . '">' . "\n";
	?>
</head>
<body class="osp-body">
	<?php
}

function osoul_portal_foot() {
	echo '</body></html>';
}

/* -------------------------------------------------------------------------
 *  Screens
 * ---------------------------------------------------------------------- */

function osoul_portal_render_login( $error = '', $note = '' ) {
	osoul_portal_head( 'تسجيل الدخول' );
	$logo = osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo_url' );
	?>
	<div class="osp-auth">
		<div class="osp-auth-card">
			<?php if ( $logo ) : ?><img class="osp-auth-logo" src="<?php echo esc_url( $logo ); ?>" alt="أصول البناء"><?php endif; ?>
			<h1 class="osp-auth-title"><?php echo osoul_bi( 'بوابة أصول البناء', 'Osoul Albinaa Portal' ); ?></h1>
			<p class="osp-auth-sub"><?php echo osoul_bi( 'الدخول للمدير والفروع والمناديب والشركاء', 'Sign in for admins, branches, reps and partners', true ); ?></p>
			<?php if ( $note ) : ?><div class="osp-alert warn"><?php echo $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bilingual spans, pre-escaped ?></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="osp-alert err"><?php echo $error; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bilingual spans, pre-escaped ?></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="osp-auth-form">
				<label><?php echo osoul_bi( 'البريد الإلكتروني', 'Email', true ); ?>
					<input type="text" name="log" autocomplete="username" required>
				</label>
				<label><?php echo osoul_bi( 'كلمة المرور', 'Password', true ); ?>
					<input type="password" name="pwd" autocomplete="current-password" required>
				</label>
				<button class="osp-btn primary block" type="submit" name="osoul_login" value="1"><?php echo osoul_bi( 'دخول', 'Sign in' ); ?></button>
			</form>
			<a class="osp-auth-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo osoul_bi( 'نسيت كلمة المرور؟', 'Forgot your password?' ); ?></a>
			<a class="osp-auth-link" href="<?php echo esc_url( home_url( '/partner-register/' ) ); ?>"><?php echo osoul_bi( 'تسجيل شركة كشريك جديد', 'Register a company as a new partner' ); ?></a>
		</div>
	</div>
	<?php
	osoul_portal_foot();
}

function osoul_portal_render_invite( $user, $error = '' ) {
	if ( isset( $GLOBALS['osoul_setpw_error'] ) ) { $error = $GLOBALS['osoul_setpw_error']; }
	osoul_portal_head( 'تفعيل الحساب' );
	$logo  = osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo_url' );
	$token = get_user_meta( $user->ID, '_osoul_invite', true );
	$role  = osoul_portal_role( $user );
	?>
	<div class="osp-auth">
		<div class="osp-auth-card">
			<?php if ( $logo ) : ?><img class="osp-auth-logo" src="<?php echo esc_url( $logo ); ?>" alt="أصول البناء"><?php endif; ?>
			<h1 class="osp-auth-title"><?php echo osoul_bi( 'مرحباً', 'Welcome' ); ?> <?php echo esc_html( $user->display_name ); ?></h1>
			<p class="osp-auth-sub"><?php
				echo osoul_bi(
					'تم إنشاء حسابك كـ«' . osoul_portal_role_label( $role ) . '». اختر كلمة مرور للبدء.',
					'Your account was created as “' . osoul_portal_role_label_en( $role ) . '”. Choose a password to get started.',
					true,
					'آپ کا اکاؤنٹ «' . osoul_portal_role_label_ur( $role ) . '» کے طور پر بنایا گیا۔ شروع کرنے کے لیے پاس ورڈ منتخب کریں۔'
				); ?></p>
			<?php if ( $error ) : ?><div class="osp-alert err"><?php echo $error; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bilingual spans, pre-escaped ?></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="osp-auth-form">
				<?php wp_nonce_field( 'osoul_invite' ); ?>
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<label><?php echo osoul_bi( 'كلمة المرور الجديدة', 'New password', true ); ?>
					<input type="password" name="pwd" autocomplete="new-password" minlength="8" required>
				</label>
				<label><?php echo osoul_bi( 'تأكيد كلمة المرور', 'Confirm password', true ); ?>
					<input type="password" name="pwd2" autocomplete="new-password" minlength="8" required>
				</label>
				<button class="osp-btn primary block" type="submit" name="osoul_setpw" value="1"><?php echo osoul_bi( 'تفعيل الحساب والدخول', 'Activate account & sign in' ); ?></button>
			</form>
		</div>
	</div>
	<?php
	osoul_portal_foot();
}

/** The live single-page app shell (assets + config; JS renders the rest). */
function osoul_portal_render_app() {
	$user = wp_get_current_user();
	$role = osoul_portal_role( $user );

	osoul_portal_head( 'لوحة التحكم' );
	?>
	<div id="osp-app" data-role="<?php echo esc_attr( $role ); ?>">
		<div class="osp-boot"><span class="osp-spin"></span> <?php echo osoul_bi( 'جاري التحميل…', 'Loading…' ); ?></div>
	</div>
	<script>
	window.OSOUL_PORTAL = {
		rest:    <?php echo wp_json_encode( esc_url_raw( rest_url( 'osoul/v1/portal/' ) ) ); ?>,
		nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
		role:    <?php echo wp_json_encode( $role ); ?>,
		name:    <?php echo wp_json_encode( $user->display_name ); ?>,
		lang:    <?php echo wp_json_encode( osoul_portal_lang() ); ?>,
		home:    <?php echo wp_json_encode( home_url( '/' ) ); ?>,
		logout:  <?php echo wp_json_encode( add_query_arg( 'logout', 1, home_url( '/dashboard/' ) ) ); ?>,
		poll:    7000
	};
	</script>
	<?php
	$js  = OSOUL_DIR . 'assets/js/portal.js';
	$ver = is_readable( $js ) ? filemtime( $js ) : OSOUL_VERSION;
	echo '<script src="' . esc_url( OSOUL_URL . 'assets/js/portal.js?v=' . $ver ) . '"></script>' . "\n";
	osoul_portal_foot();
}
