<?php
/**
 * Partner Portal — surfaces (all server-rendered, matching the plugin's login /
 * invite / public-quote pages; no dependency on the admin SPA).
 *
 *   - /partner-register            public join form → pending account
 *   - /dashboard  (role=partner)   the partner workspace (overview, request
 *                                  products, view priced supply, create a
 *                                  white-label customer quote, my quotes, brand)
 *   - WP Admin → الشركاء            approve / suspend / re-invite partners,
 *                                  set numbering prefix + minimum margin
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  Shared chrome
 * ====================================================================== */

/** Partner-surface page head (reuses the portal chrome + a scoped stylesheet). */
function osoul_partner_page_head( $title ) {
	osoul_portal_head( $title );
	echo '<style>' . osoul_partner_css() . '</style>'; // phpcs:ignore — static CSS.
}

/** Inline colour theme for the logged-in partner (drives --pp-primary / --pp-accent). */
function osoul_partner_theme_style( $pid ) {
	$pcol = osoul_partner_sanitize_hex( osoul_partner_get( $pid, 'color' ), '#00344F' );
	$pacc = osoul_partner_sanitize_hex( osoul_partner_get( $pid, 'accent' ), '#0074A4' );
	echo '<style>:root{--pp-primary:' . $pcol . ';--pp-accent:' . $pacc . '}</style>';
}

/** Scoped CSS for the partner surfaces. */
function osoul_partner_css() {
	return '
:root,.osp-body{--pp-primary:#00344F;--pp-accent:#0074A4}
*{box-sizing:border-box}
.osp-body{background:linear-gradient(180deg,#eef2f7,#e7ecf3);min-height:100vh;margin:0}
.pp{max-width:1060px;margin:0 auto;padding:0 16px 64px;font-family:"Cairo","IBM Plex Sans Arabic",sans-serif;color:#1f2a3d}
.pp-top{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;margin:0 -16px 22px;padding:12px 20px;box-shadow:0 2px 16px rgba(0,52,79,.08);border-bottom:3px solid var(--pp-accent)}
.pp-brand{display:flex;align-items:center;gap:12px}
.pp-brand .lg{width:48px;height:48px;border-radius:12px;object-fit:contain;background:#f3f5f9;border:1px solid #e6eaf0;padding:4px}
.pp-brand b{color:var(--pp-primary);font-size:17px;font-weight:800;line-height:1.2;display:block}
.pp-brand span{display:block;color:#7a8699;font-size:11.5px;margin-top:2px}
.pp-nav{display:flex;gap:6px;flex-wrap:wrap}
.pp-nav a{font-size:13px;font-weight:700;color:var(--pp-primary);background:#eef1f6;border-radius:22px;padding:9px 15px;text-decoration:none;border:1px solid transparent;transition:.15s}
.pp-nav a:hover{background:#e2e7ef}
.pp-nav a.on{background:var(--pp-primary);color:#fff}
.pp-nav a.exit{color:var(--pp-accent);background:#fff;border-color:#efd6d7}
.pp-hero{background:linear-gradient(120deg,var(--pp-primary),#16305c);color:#fff;border-radius:18px;padding:24px 26px;margin-bottom:18px;position:relative;overflow:hidden}
.pp-hero:before{content:"";position:absolute;inset-inline-end:-40px;top:-40px;width:180px;height:180px;background:var(--pp-accent);opacity:.20;border-radius:50%}
.pp-hero h1{font-size:22px;font-weight:800;margin:0 0 5px;position:relative}
.pp-hero p{opacity:.86;font-size:14px;margin:0;position:relative}
.pp-hero .cta{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;position:relative}
.pp-card{background:#fff;border:1px solid #e6eaf1;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 6px 22px rgba(0,52,79,.06)}
.pp-card h2{font-size:16px;color:var(--pp-primary);margin:0 0 4px;font-weight:800;display:flex;align-items:center;gap:9px}
.pp-card h2:before{content:"";width:4px;height:18px;border-radius:3px;background:var(--pp-accent);display:inline-block}
.pp-card p.sub{color:#7a8699;font-size:13px;margin:0 0 16px}
.pp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px}
.pp-kpi{position:relative;background:#fff;border:1px solid #e6eaf1;border-radius:14px;padding:18px 16px;overflow:hidden}
.pp-kpi:before{content:"";position:absolute;top:0;inset-inline-start:0;width:100%;height:4px;background:var(--pp-accent)}
.pp-kpi b{display:block;font-size:28px;color:var(--pp-primary);font-weight:800;line-height:1.1}
.pp-kpi span{color:#7a8699;font-size:12.5px}
.pp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:none;border-radius:10px;padding:12px 20px;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;color:#fff;background:var(--pp-accent);transition:.15s;box-shadow:0 4px 12px rgba(0,0,0,.10)}
.pp-btn:hover{filter:brightness(.94)}
.pp-btn.nav{background:var(--pp-primary)}
.pp-btn.sec{background:#fff;color:var(--pp-primary);border:1.5px solid #dfe4ec;box-shadow:none}
.pp-btn.sec:hover{background:#f6f8fb}
.pp-btn.ghost{background:rgba(255,255,255,.15);color:#fff;box-shadow:none}
.pp-btn.block{width:100%}
.pp-btn:disabled{opacity:.5;cursor:not-allowed;box-shadow:none}
.pp-alert{padding:13px 16px;border-radius:11px;font-weight:700;font-size:14px;margin-bottom:16px}
.pp-alert.err{background:#fdeaea;color:#b32d2e;border:1px solid #f3c2c2}
.pp-alert.ok{background:#e7f7ee;color:#1a7e42;border:1px solid #b6e7c9}
.pp-alert.warn{background:#fff7e6;color:#8a5a00;border:1px solid #f3e2b6}
label.pp-f{display:block;margin-bottom:14px;font-size:13px;font-weight:700;color:var(--pp-primary)}
label.pp-f input,label.pp-f textarea{width:100%;margin-top:6px;padding:11px 13px;border:1.5px solid #dde3ec;border-radius:10px;font-family:inherit;font-size:14px;background:#fcfdfe;transition:.15s}
label.pp-f input:focus,label.pp-f textarea:focus{outline:none;border-color:var(--pp-accent);box-shadow:0 0 0 3px rgba(0,0,0,.06)}
.pp-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.pp-tw{overflow-x:auto;border-radius:12px;border:1px solid #e9edf3}
table.pp-t{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.pp-t th{background:var(--pp-primary);color:#fff;padding:11px 12px;text-align:start;font-size:12px;font-weight:700}
table.pp-t td{padding:11px 12px;border-bottom:1px solid #eef1f5}
table.pp-t tbody tr:hover{background:#f7f9fc}
table.pp-t tbody tr:last-child td{border-bottom:none}
.pp-num{font-variant-numeric:tabular-nums;white-space:nowrap}
.pp-pill{display:inline-block;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:800;color:#fff}
.pp-prod{display:flex;align-items:center;gap:12px;border:1px solid #e6eaf1;border-radius:12px;padding:11px;margin-bottom:9px;background:#fff;transition:.15s}
.pp-prod:hover{border-color:var(--pp-accent)}
.pp-prod img{width:46px;height:46px;object-fit:cover;border-radius:8px;background:#f0f2f5}
.pp-prod .nm{flex:1;font-size:14px;font-weight:700;color:var(--pp-primary)}
.pp-prod input{width:74px;padding:9px;border:1.5px solid #dde3ec;border-radius:9px;text-align:center;font-family:inherit}
.pp-gh{color:var(--pp-accent);font-size:12px;font-weight:800;letter-spacing:.6px;margin:20px 0 10px;text-transform:uppercase;display:flex;align-items:center;gap:8px}
.pp-gh:before{content:"";width:18px;height:2px;background:var(--pp-accent)}
.pp-muted{color:#7a8699;font-size:13px}
.pp-link{background:#f5f8fc;border:1.5px dashed #b7c1d1;border-radius:10px;padding:12px;word-break:break-all;font-size:13px;margin-top:8px;color:var(--pp-primary);font-weight:600}
.pp-colorrow{display:flex;gap:22px;flex-wrap:wrap;align-items:flex-end;margin:6px 0 14px}
.pp-color{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:var(--pp-primary)}
.pp-color input[type=color]{width:66px;height:44px;border:1px solid #dde3ec;border-radius:10px;padding:3px;background:#fff;cursor:pointer}
.pp-logo-cur{width:74px;height:74px;border-radius:14px;object-fit:contain;background:#f3f5f9;border:1px solid #e6eaf0;padding:6px}
.pp-preview{border-radius:12px;overflow:hidden;border:1px solid #e6eaf1;margin-top:8px;max-width:360px}
.pp-preview .h{background:var(--pp-primary);color:#fff;padding:12px 14px;font-weight:800;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.pp-preview .h .tag{background:var(--pp-accent);padding:3px 11px;border-radius:20px;font-size:11px}
.pp-preview .b{padding:12px 14px;font-size:12px;color:#5a6678;background:#fff}
.pp-preview .b .tot{color:var(--pp-accent);font-weight:800}
@media(max-width:640px){.pp-row2{grid-template-columns:1fr}.pp-top{position:static}.pp-hero h1{font-size:19px}table.pp-t{display:block;overflow-x:auto;white-space:nowrap}}
';
}

/** Compact top bar with brand + navigation. */
function osoul_partner_topbar( $active = 'home' ) {
	$pid  = get_current_user_id();
	$logo = osoul_partner_get( $pid, 'logo' ) ?: ( osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo_url' ) );
	$name = osoul_partner_get( $pid, 'company_ar' ) ?: wp_get_current_user()->display_name;
	$d    = home_url( '/dashboard/' );
	$nav  = array(
		'home'    => array( 'الرئيسية', $d ),
		'request' => array( 'طلب منتجات', add_query_arg( 'v', 'request', $d ) ),
		'quotes'  => array( 'عروضي لعملائي', add_query_arg( 'v', 'quotes', $d ) ),
		'profile' => array( 'هويّتي', add_query_arg( 'v', 'profile', $d ) ),
	);
	echo '<div class="pp-top"><div class="pp-brand">';
	if ( $logo ) { echo '<img class="lg" src="' . esc_url( $logo ) . '" alt="">'; }
	echo '<div><b>' . esc_html( $name ) . '</b><span>' . esc_html__( 'بوابة الشركاء — أصول البناء', 'osoul' ) . '</span></div></div>';
	echo '<div class="pp-nav">';
	foreach ( $nav as $k => $it ) {
		echo '<a class="' . ( $k === $active ? 'on' : '' ) . '" href="' . esc_url( $it[1] ) . '">' . esc_html( $it[0] ) . '</a>';
	}
	echo '<a class="exit" href="' . esc_url( add_query_arg( 'logout', 1, $d ) ) . '">خروج</a>';
	echo '</div></div>';
}

/* =========================================================================
 *  Public registration  (/partner-register)
 * ====================================================================== */

add_action( 'template_redirect', function () {
	if ( 'partner-register' === osoul_request_slug() ) {
		osoul_partner_reg_route();
		exit;
	}
}, 0 );

/** Per-IP throttle for public partner registration (mirrors the RFQ limiter). */
function osoul_partner_reg_rate_limited() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'osoul_prl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return true;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return false;
}

function osoul_partner_reg_route() {
	nocache_headers();
	status_header( 200 );
	if ( is_user_logged_in() && 'partner' === osoul_portal_role() ) {
		wp_safe_redirect( home_url( '/dashboard/' ) );
		exit;
	}

	$errors = array();
	$old    = array();
	if ( isset( $_POST['pp_register'] ) ) {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'osoul_partner_register' ) ) {
			$errors[] = 'انتهت صلاحية الجلسة، حاول مرة أخرى.';
		} elseif ( ! empty( $_POST['website'] ) ) {
			$errors[] = 'تعذّر إرسال الطلب.';
		} elseif ( osoul_partner_reg_rate_limited() ) {
			$errors[] = 'محاولات كثيرة، يرجى المحاولة بعد قليل.';
		} else {
			$old = array(
				'company_ar' => sanitize_text_field( wp_unslash( $_POST['company_ar'] ?? '' ) ),
				'company_en' => sanitize_text_field( wp_unslash( $_POST['company_en'] ?? '' ) ),
				'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
				'phone'      => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
				'vat'        => sanitize_text_field( wp_unslash( $_POST['vat'] ?? '' ) ),
				'cr'         => sanitize_text_field( wp_unslash( $_POST['cr'] ?? '' ) ),
				'bank'       => sanitize_textarea_field( wp_unslash( $_POST['bank'] ?? '' ) ),
				'address_ar' => sanitize_text_field( wp_unslash( $_POST['address_ar'] ?? '' ) ),
			);
			$res = osoul_partner_register( $old );
			if ( is_wp_error( $res ) ) {
				$errors[] = $res->get_error_message();
			} else {
				osoul_partner_render_reg_done();
				return;
			}
		}
	}
	osoul_partner_render_register( $errors, $old );
}

/** Scoped, premium styling for the public partner auth pages (register + done). */
function osoul_partner_auth_css() {
	return '
.ppreg-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:34px 16px;background:radial-gradient(1200px 700px at 50% -10%,#173563 0%,#00344F 48%,#081221 100%)}
.ppreg-card{width:100%;max-width:1000px;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 40px 90px rgba(0,0,0,.45);display:grid;grid-template-columns:.82fr 1.18fr;animation:ppregIn .45s ease}
@keyframes ppregIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.ppreg-aside{background:linear-gradient(160deg,#00344F 0%,#17356a 55%,#00344F 100%);color:#fff;padding:44px 38px;position:relative;overflow:hidden;display:flex;flex-direction:column}
.ppreg-aside:before{content:"";position:absolute;inset-inline-end:-70px;top:-70px;width:230px;height:230px;background:#0074A4;opacity:.16;border-radius:50%}
.ppreg-aside:after{content:"";position:absolute;inset-inline-start:-50px;bottom:-60px;width:180px;height:180px;background:#fff;opacity:.05;border-radius:50%}
.ppreg-logochip{display:inline-flex;align-items:center;background:#fff;border-radius:14px;padding:9px 13px;box-shadow:0 8px 22px rgba(0,0,0,.25);position:relative}
.ppreg-logochip img{height:40px;width:auto;display:block}
.ppreg-h1{font-size:26px;font-weight:800;line-height:1.35;margin:26px 0 10px;position:relative}
.ppreg-lead{font-size:14.5px;line-height:1.75;color:rgba(255,255,255,.82);margin:0 0 26px;position:relative}
.ppreg-benefits{list-style:none;margin:0;padding:0;position:relative;display:flex;flex-direction:column;gap:15px}
.ppreg-benefits li{position:relative;padding-inline-start:34px;font-size:14px;line-height:1.55;color:#eef3fb}
.ppreg-benefits li:before{content:"";position:absolute;inset-inline-start:0;top:1px;width:22px;height:22px;border-radius:50%;background:rgba(0,116,164,.92);box-shadow:0 4px 10px rgba(0,116,164,.35)}
.ppreg-benefits li:after{content:"";position:absolute;inset-inline-start:8px;top:6px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.ppreg-aside-foot{margin-top:auto;padding-top:28px;font-size:12px;font-weight:700;letter-spacing:.5px;color:rgba(255,255,255,.6);position:relative}
.ppreg-form{padding:44px 42px;display:flex;flex-direction:column}
.ppreg-form-h h2{font-size:22px;font-weight:800;color:#00344F;margin:0 0 6px}
.ppreg-form-h p{font-size:13px;color:#6b7280;line-height:1.65;margin:0 0 22px}
.ppreg-alert{background:#fdeaea;color:#b32d2e;border:1px solid #f3c2c2;border-radius:11px;padding:11px 14px;font-size:13.5px;font-weight:600;margin-bottom:16px}
.ppreg-sec{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#0074A4;margin:8px 0 13px;display:flex;align-items:center;gap:9px}
.ppreg-sec:before{content:"";width:16px;height:2px;background:#0074A4;border-radius:2px}
.ppreg-sec.first{margin-top:0}
.ppreg-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:end}
.ppreg-f{display:block;margin-bottom:14px}
.ppreg-f span{display:flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:6px;min-height:18px}
.ppreg-f i{color:#0074A4;font-style:normal;flex:none}
.ppreg-f input,.ppreg-f textarea{width:100%;padding:12px 14px;border:1.5px solid #e0e5ee;border-radius:8px;font-family:inherit;font-size:14px;color:#00344F;background:#fbfcfe;transition:.15s;resize:vertical}
.ppreg-f input:focus,.ppreg-f textarea:focus{outline:none;border-color:#0074A4;background:#fff;box-shadow:0 0 0 3px rgba(0,116,164,.1)}
.ppreg-btn{width:100%;margin-top:10px;background:linear-gradient(135deg,#0089BE,#0074A4);color:#fff;border:none;border-radius:12px;padding:15px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer;transition:.18s;box-shadow:0 12px 26px rgba(0,116,164,.28)}
.ppreg-btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
.ppreg-signin{text-align:center;font-size:13px;color:#6b7280;margin:16px 0 0}
.ppreg-signin a{color:#0074A4;font-weight:800;text-decoration:none}
.ppreg-signin a:hover{text-decoration:underline}
.ppreg-done{text-align:center;max-width:150px}
.ppreg-check{width:74px;height:74px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#12833c);display:grid;place-items:center;margin:0 auto 18px;box-shadow:0 14px 30px rgba(22,163,74,.32)}
.ppreg-check:after{content:"";width:20px;height:38px;border:solid #fff;border-width:0 5px 5px 0;transform:rotate(45deg);margin-top:-6px}
.ppreg-steps{list-style:none;counter-reset:s;margin:18px 0 8px;padding:0;text-align:start;display:flex;flex-direction:column;gap:12px}
.ppreg-steps li{counter-increment:s;position:relative;padding-inline-start:40px;font-size:13.5px;color:#374151;line-height:1.6;min-height:28px;display:flex;align-items:center}
.ppreg-steps li:before{content:counter(s);position:absolute;inset-inline-start:0;top:0;width:28px;height:28px;border-radius:50%;background:#00344F;color:#fff;font-weight:800;font-size:13px;display:grid;place-items:center}
.ppreg-done-btns{margin-top:22px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.ppreg-b2{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:11px;font-family:inherit;font-size:14px;font-weight:800;text-decoration:none;transition:.15s}
.ppreg-b2.primary{background:linear-gradient(135deg,#0089BE,#0074A4);color:#fff;box-shadow:0 10px 22px rgba(0,116,164,.26)}
.ppreg-b2.ghost{background:#fff;color:#00344F;border:1.5px solid #dfe4ec}
.ppreg-b2.ghost:hover{background:#f6f8fb}
@media(max-width:800px){
.ppreg-card{grid-template-columns:1fr;max-width:520px}
.ppreg-aside{padding:30px 26px}
.ppreg-h1{font-size:21px;margin-top:18px}
.ppreg-benefits{gap:11px}
.ppreg-form{padding:30px 24px}
.ppreg-row{grid-template-columns:1fr}
}';
}

function osoul_partner_render_register( $errors = array(), $old = array() ) {
	$v = static function ( $k ) use ( $old ) { return esc_attr( $old[ $k ] ?? '' ); };
	osoul_partner_page_head( 'التسجيل كشريك' );
	$osoul_logo = osoul_opt( 'logo_login' ) ?: ( osoul_opt( 'logo_url' ) ?: osoul_opt( 'logo_icon' ) );
	echo '<style>' . osoul_partner_auth_css() . '</style>'; // phpcs:ignore — static CSS.
	?>
	<div class="ppreg-wrap">
		<div class="ppreg-card">
			<aside class="ppreg-aside">
				<?php if ( $osoul_logo ) : ?><span class="ppreg-logochip"><img src="<?php echo esc_url( $osoul_logo ); ?>" alt="أصول البناء"></span><?php endif; ?>
				<h1 class="ppreg-h1"><?php echo osoul_bi( 'كن شريكاً لأصول البناء', 'Become an Osoul Albinaa partner' ); ?></h1>
				<p class="ppreg-lead"><?php echo osoul_bi( 'انضم لشبكة شركائنا وابدأ ببيع منتجاتنا بعلامتك التجارية الخاصة وبأسعار الجملة.', 'Join our partner network and start selling our products under your own brand, at wholesale prices.', true ); ?></p>
				<ul class="ppreg-benefits">
					<li><?php echo osoul_bi( 'أسعار الجملة على كامل تشكيلة المنتجات', 'Wholesale pricing across the full product range', true ); ?></li>
					<li><?php echo osoul_bi( 'أصدر عروض أسعار بشعارك وهويّتك التجارية', 'Issue quotes with your own logo and brand', true ); ?></li>
					<li><?php echo osoul_bi( 'كتالوج إلكتروني ولوحة تحكم خاصة بك', 'Your own digital catalog and dashboard', true ); ?></li>
					<li><?php echo osoul_bi( 'خصوصية تامة — عملاؤك لك وحدك', 'Full privacy — your customers stay yours', true ); ?></li>
				</ul>
				<div class="ppreg-aside-foot"><?php echo osoul_bi( 'تصنيع سعودي • جودة معتمدة', 'Saudi-made • Certified quality' ); ?></div>
			</aside>
			<div class="ppreg-form">
				<div class="ppreg-form-h">
					<h2><?php echo osoul_bi( 'طلب الانضمام', 'Join request' ); ?></h2>
					<p><?php echo osoul_bi( 'عبّئ بيانات شركتك — بعد اعتماد الإدارة يصلك بريد لتفعيل حسابك.', 'Fill in your company details — after approval you’ll receive an email to activate your account.', true ); ?></p>
				</div>
				<?php foreach ( $errors as $e ) : ?><div class="ppreg-alert"><?php echo esc_html( $e ); ?></div><?php endforeach; ?>
				<form method="post">
					<?php wp_nonce_field( 'osoul_partner_register' ); ?>
					<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
					<div class="ppreg-sec first"><?php echo osoul_bi( 'بيانات الشركة', 'Company details' ); ?></div>
					<div class="ppreg-row">
						<label class="ppreg-f"><span><?php echo osoul_bi( 'اسم الشركة (عربي)', 'Company name (Arabic)', true ); ?> <i>*</i></span><input type="text" name="company_ar" value="<?php echo $v( 'company_ar' ); ?>" required></label>
						<label class="ppreg-f"><span><?php echo osoul_bi( 'اسم الشركة (إنجليزي)', 'Company name (English)', true ); ?></span><input type="text" name="company_en" value="<?php echo $v( 'company_en' ); ?>"></label>
					</div>
					<div class="ppreg-sec"><?php echo osoul_bi( 'التواصل والدخول', 'Contact & login' ); ?></div>
					<div class="ppreg-row">
						<label class="ppreg-f"><span><?php echo osoul_bi( 'البريد الإلكتروني (للدخول)', 'Email (for login)', true ); ?> <i>*</i></span><input type="email" name="email" value="<?php echo $v( 'email' ); ?>" required></label>
						<label class="ppreg-f"><span><?php echo osoul_bi( 'رقم الجوال', 'Phone number', true ); ?> <i>*</i></span><input type="text" name="phone" value="<?php echo $v( 'phone' ); ?>" required></label>
					</div>
					<div class="ppreg-sec"><?php echo osoul_bi( 'البيانات النظامية', 'Legal details' ); ?></div>
					<div class="ppreg-row">
						<label class="ppreg-f"><span><?php echo osoul_bi( 'الرقم الضريبي (VAT)', 'VAT number', true ); ?> <i>*</i></span><input type="text" name="vat" value="<?php echo $v( 'vat' ); ?>" required></label>
						<label class="ppreg-f"><span><?php echo osoul_bi( 'السجل التجاري (CR)', 'Commercial reg. (CR)', true ); ?></span><input type="text" name="cr" value="<?php echo $v( 'cr' ); ?>"></label>
					</div>
					<label class="ppreg-f"><span><?php echo osoul_bi( 'العنوان الوطني', 'National address', true ); ?></span><input type="text" name="address_ar" value="<?php echo $v( 'address_ar' ); ?>"></label>
					<label class="ppreg-f"><span><?php echo osoul_bi( 'الحساب البنكي / الآيبان', 'Bank account / IBAN', true ); ?> <i>*</i></span><textarea name="bank" rows="2" required><?php echo esc_textarea( $old['bank'] ?? '' ); ?></textarea></label>
					<button class="ppreg-btn" type="submit" name="pp_register" value="1"><?php echo osoul_bi( 'إرسال طلب الانضمام', 'Send join request' ); ?></button>
					<p class="ppreg-signin"><?php echo osoul_bi( 'لديك حساب بالفعل؟', 'Already have an account?' ); ?> <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php echo osoul_bi( 'تسجيل الدخول', 'Sign in' ); ?></a></p>
				</form>
			</div>
		</div>
	</div>
	<?php
	osoul_portal_foot();
}

function osoul_partner_render_reg_done() {
	osoul_partner_page_head( 'تم استلام الطلب' );
	$osoul_logo = osoul_opt( 'logo_login' ) ?: ( osoul_opt( 'logo_url' ) ?: osoul_opt( 'logo_icon' ) );
	echo '<style>' . osoul_partner_auth_css() . '</style>'; // phpcs:ignore — static CSS.
	?>
	<div class="ppreg-wrap">
		<div class="ppreg-card" style="grid-template-columns:1fr;max-width:520px">
			<div class="ppreg-form" style="text-align:center;align-items:center">
				<?php if ( $osoul_logo ) : ?><span class="ppreg-logochip" style="box-shadow:0 6px 16px rgba(0,52,79,.12)"><img src="<?php echo esc_url( $osoul_logo ); ?>" alt="أصول البناء"></span><?php endif; ?>
				<div class="ppreg-check" style="margin-top:22px"></div>
				<h2 style="font-size:22px;font-weight:800;color:#00344F;margin:0 0 6px"><?php echo osoul_bi( 'تم استلام طلب الانضمام', 'Your join request was received' ); ?></h2>
				<p style="font-size:13px;color:#6b7280;margin:0"><?php echo osoul_bi( 'خطواتك التالية:', 'Your next steps:', true ); ?></p>
				<ol class="ppreg-steps">
					<li><?php echo osoul_bi( 'تراجع الإدارة بياناتك وتعتمد حسابك.', 'Management reviews your details and approves your account.', true ); ?></li>
					<li><?php echo osoul_bi( 'يصلك بريد فيه رابط تفعيل تختار منه كلمة المرور.', 'You receive an email with an activation link to set your password.', true ); ?></li>
					<li><?php echo osoul_bi( 'بعدها تدخل لوحتك في أي وقت من صفحة الدخول.', 'Then access your dashboard anytime from the sign-in page.', true ); ?></li>
				</ol>
				<p style="font-size:12px;color:#9aa3b2;margin:6px 0 0"><?php echo osoul_bi( 'لم يصلك البريد بعد الاعتماد؟ تواصل مع الإدارة لتزويدك برابط التفعيل مباشرة.', 'Didn’t get the email after approval? Contact management for a direct activation link.', true ); ?></p>
				<div class="ppreg-done-btns">
					<a class="ppreg-b2 primary" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php echo osoul_bi( 'صفحة الدخول', 'Sign-in page' ); ?></a>
					<a class="ppreg-b2 ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo osoul_bi( 'العودة للموقع', 'Back to the site' ); ?></a>
				</div>
			</div>
		</div>
	</div>
	<?php
	osoul_portal_foot();
}

/* =========================================================================
 *  Partner dashboard  (/dashboard when role = partner)
 * ====================================================================== */

/** Entry point, called from osoul_portal_route() when the user is a partner. */
function osoul_partner_render_dashboard() {
	$pid    = get_current_user_id();
	$notice = osoul_partner_dashboard_handle_post( $pid ); // may redirect+exit
	$view   = sanitize_key( (string) ( $_GET['v'] ?? 'home' ) );

	osoul_partner_page_head( 'بوابة الشريك' );
	osoul_partner_theme_style( $pid );
	echo '<div class="pp">';
	osoul_partner_topbar( in_array( $view, array( 'request', 'quotes', 'profile' ), true ) ? $view : 'home' );

	if ( $notice ) {
		echo '<div class="pp-alert ' . esc_attr( $notice[0] ) . '">' . esc_html( $notice[1] ) . '</div>';
	}
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="pp-alert ok">' . esc_html__( 'تم الحفظ.', 'osoul' ) . '</div>';
	}

	switch ( $view ) {
		case 'request':
			osoul_partner_view_request( $pid );
			break;
		case 'supply':
			osoul_partner_view_supply( $pid, (int) ( $_GET['id'] ?? 0 ) );
			break;
		case 'make':
			osoul_partner_view_make_quote( $pid, (int) ( $_GET['supply'] ?? 0 ) );
			break;
		case 'quotes':
			osoul_partner_view_quotes( $pid );
			break;
		case 'profile':
			osoul_partner_view_profile( $pid );
			break;
		default:
			osoul_partner_view_home( $pid );
	}
	echo '</div>';
	osoul_portal_foot();
}

/**
 * Handle a dashboard POST (PRG: redirects on success, returns a notice on error).
 *
 * @return array|null [type, message] shown on the re-rendered page.
 */
function osoul_partner_dashboard_handle_post( $pid ) {
	if ( empty( $_POST['pp_action'] ) ) {
		return null;
	}
	$action = sanitize_key( $_POST['pp_action'] );
	if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'osoul_partner_' . $action ) ) {
		return array( 'err', 'انتهت صلاحية الجلسة، حاول مرة أخرى.' );
	}
	$d = home_url( '/dashboard/' );

	if ( 'request' === $action ) {
		$qty   = (array) ( $_POST['qty'] ?? array() );
		$items = array();
		foreach ( $qty as $slug => $q ) {
			$q = (int) $q;
			if ( $q > 0 ) {
				$items[] = array( 'slug' => sanitize_title( $slug ), 'qty' => $q );
			}
		}
		$res = osoul_partner_create_supply( $pid, $items, (string) ( $_POST['note'] ?? '' ) );
		if ( is_wp_error( $res ) ) {
			return array( 'err', $res->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( array( 'v' => 'supply', 'id' => $res, 'ok' => 'req' ), $d ) );
		exit;
	}

	if ( 'make' === $action ) {
		$supply_id = (int) ( $_POST['supply_id'] ?? 0 );
		$customer  = array(
			'name'  => $_POST['c_name'] ?? '',
			'phone' => $_POST['c_phone'] ?? '',
			'email' => $_POST['c_email'] ?? '',
		);
		$sell = array();
		foreach ( (array) ( $_POST['sell'] ?? array() ) as $i => $val ) {
			$sell[ (int) $i ] = (float) $val;
		}
		$opts = array(
			'discount' => (float) ( $_POST['discount'] ?? 0 ),
			'validity' => (int) ( $_POST['validity'] ?? 0 ),
		);
		$res = osoul_partner_create_customer_quote( $pid, $supply_id, $customer, $sell, $opts );
		if ( is_wp_error( $res ) ) {
			return array( 'err', $res->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( array( 'v' => 'quotes', 'new' => $res['id'] ), $d ) );
		exit;
	}

	if ( 'profile' === $action ) {
		$fields = array(
			'company_ar' => $_POST['company_ar'] ?? '',
			'company_en' => $_POST['company_en'] ?? '',
			'vat'        => $_POST['vat'] ?? '',
			'cr'         => $_POST['cr'] ?? '',
			'address_ar' => $_POST['address_ar'] ?? '',
			'address_en' => $_POST['address_en'] ?? '',
			'bank'       => $_POST['bank'] ?? '',
			'phone'      => $_POST['phone'] ?? '',
			'terms_ar'   => $_POST['terms_ar'] ?? '',
			'terms_en'   => $_POST['terms_en'] ?? '',
			'color'      => $_POST['color'] ?? '',
			'accent'     => $_POST['accent'] ?? '',
		);
		$logo = osoul_partner_handle_logo_upload();
		if ( is_string( $logo ) && '' !== $logo ) {
			$fields['logo'] = $logo;
		}
		osoul_partner_save_profile( $pid, $fields );
		$q = array( 'v' => 'profile', 'saved' => 1 );
		if ( is_wp_error( $logo ) ) { $q['logoerr'] = 1; }
		wp_safe_redirect( add_query_arg( $q, $d ) );
		exit;
	}

	return null;
}

/**
 * Handle a partner logo upload from the profile form.
 *
 * @return string|WP_Error|null Attachment URL, an error, or null when no file was sent.
 */
function osoul_partner_handle_logo_upload() {
	if ( empty( $_FILES['logo_file']['name'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'osoul_p_cap', 'لا تملك صلاحية رفع الملفات.' );
	}
	$type = wp_check_filetype( sanitize_file_name( $_FILES['logo_file']['name'] ) );
	if ( 0 !== strpos( (string) $type['type'], 'image/' ) ) {
		return new WP_Error( 'osoul_p_img', 'ارفع صورة PNG أو JPG.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att = media_handle_upload( 'logo_file', 0 );
	if ( is_wp_error( $att ) ) {
		return $att;
	}
	return wp_get_attachment_url( $att );
}

/** Home / overview. */
function osoul_partner_view_home( $pid ) {
	$supply = osoul_partner_supply_orders( $pid );
	$quotes = osoul_partner_customer_quotes( $pid );
	$won    = 0;
	foreach ( $quotes as $qp ) {
		if ( 'won' === ( get_post_meta( $qp->ID, '_osoul_stage', true ) ?: '' ) ) { $won++; }
	}
	$d = home_url( '/dashboard/' );
	?>
	<div class="pp-hero">
		<h1><?php echo esc_html( sprintf( __( 'أهلاً، %s 👋', 'osoul' ), osoul_partner_get( $pid, 'company_ar' ) ?: wp_get_current_user()->display_name ) ); ?></h1>
		<p><?php esc_html_e( 'اطلب المنتجات من أصول، ثم أصدر عروضاً لعملائك باسم شركتك وهويّتك.', 'osoul' ); ?></p>
		<div class="cta">
			<a class="pp-btn" href="<?php echo esc_url( add_query_arg( 'v', 'request', $d ) ); ?>">＋ <?php esc_html_e( 'طلب منتجات جديد', 'osoul' ); ?></a>
			<a class="pp-btn ghost" href="<?php echo esc_url( add_query_arg( 'v', 'quotes', $d ) ); ?>"><?php esc_html_e( 'عروضي لعملائي', 'osoul' ); ?></a>
		</div>
	</div>
	<div class="pp-grid" style="margin-bottom:18px">
		<div class="pp-kpi"><b class="pp-num"><?php echo (int) count( $supply ); ?></b><span><?php esc_html_e( 'طلبات التوريد', 'osoul' ); ?></span></div>
		<div class="pp-kpi"><b class="pp-num"><?php echo (int) count( $quotes ); ?></b><span><?php esc_html_e( 'عروض لعملائك', 'osoul' ); ?></span></div>
		<div class="pp-kpi"><b class="pp-num"><?php echo (int) $won; ?></b><span><?php esc_html_e( 'عروض مقبولة', 'osoul' ); ?></span></div>
	</div>

	<div class="pp-card">
		<h2><?php esc_html_e( 'طلباتي من أصول', 'osoul' ); ?></h2>
		<?php if ( ! $supply ) : ?>
			<p class="pp-muted"><?php esc_html_e( 'لا توجد طلبات بعد.', 'osoul' ); ?></p>
		<?php else : ?>
			<table class="pp-t"><thead><tr>
				<th><?php esc_html_e( 'التاريخ', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'عدد البنود', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الحالة', 'osoul' ); ?></th>
				<th></th>
			</tr></thead><tbody>
			<?php foreach ( $supply as $sp ) :
				$items  = (array) get_post_meta( $sp->ID, '_osoul_items', true );
				$issued = osoul_quote_is_issued( $sp->ID );
				?>
				<tr>
					<td class="pp-num"><?php echo esc_html( get_the_date( 'Y-m-d', $sp ) ); ?></td>
					<td class="pp-num"><?php echo (int) count( $items ); ?></td>
					<td><?php echo osoul_partner_stage_pill( $issued ? 'sent' : ( get_post_meta( $sp->ID, '_osoul_stage', true ) ?: 'new' ), $issued ); ?></td>
					<td><a class="pp-btn sec" style="padding:6px 12px;font-size:12px" href="<?php echo esc_url( add_query_arg( array( 'v' => 'supply', 'id' => $sp->ID ), $d ) ); ?>"><?php esc_html_e( 'عرض', 'osoul' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

/** New supply request (catalog + quantities). */
function osoul_partner_view_request( $pid ) {
	$catalog = osoul_catalog();
	$labels  = function_exists( 'osoul_group_labels' ) ? osoul_group_labels() : array();
	$groups  = array();
	foreach ( $catalog as $slug => $p ) {
		$g = $p['group'] ?? 'other';
		$groups[ $g ][ $slug ] = $p;
	}
	?>
	<div class="pp-card">
		<h2><?php esc_html_e( 'طلب منتجات من أصول', 'osoul' ); ?></h2>
		<p class="sub"><?php esc_html_e( 'حدّد الكميات المطلوبة. سترسل أصول لك الأسعار (تكلفتك) بعد المراجعة.', 'osoul' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'osoul_partner_request' ); ?>
			<input type="hidden" name="pp_action" value="request">
			<?php foreach ( $groups as $g => $items ) : ?>
				<div class="pp-gh"><?php echo esc_html( $labels[ $g ]['ar'] ?? $g ); ?></div>
				<?php foreach ( $items as $slug => $p ) : ?>
					<div class="pp-prod">
						<?php if ( ! empty( $p['img'] ) ) : ?><img src="<?php echo esc_url( $p['img'] ); ?>" alt=""><?php endif; ?>
						<span class="nm"><?php echo esc_html( $p['name'] ?? $slug ); ?></span>
						<input type="number" min="0" step="1" name="qty[<?php echo esc_attr( $slug ); ?>]" value="0" aria-label="الكمية">
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<label class="pp-f" style="margin-top:14px"><?php esc_html_e( 'ملاحظات (اختياري)', 'osoul' ); ?><textarea name="note" rows="2"></textarea></label>
			<button class="pp-btn block" type="submit"><?php esc_html_e( 'إرسال الطلب لأصول', 'osoul' ); ?></button>
		</form>
	</div>
	<?php
}

/** View one supply order + its Osoul cost (once priced). */
function osoul_partner_view_supply( $pid, $sid ) {
	if ( (int) get_post_meta( $sid, '_osoul_partner_id', true ) !== (int) $pid
		|| 'supply' !== get_post_meta( $sid, '_osoul_kind', true ) ) {
		echo '<div class="pp-card"><p class="pp-muted">' . esc_html__( 'الطلب غير موجود.', 'osoul' ) . '</p></div>';
		return;
	}
	$items  = (array) get_post_meta( $sid, '_osoul_items', true );
	$issued = osoul_quote_is_issued( $sid );
	$d      = home_url( '/dashboard/' );
	?>
	<div class="pp-card">
		<h2><?php esc_html_e( 'طلب توريد', 'osoul' ); ?> #<?php echo (int) $sid; ?></h2>
		<p class="sub"><?php echo osoul_partner_stage_pill( $issued ? 'sent' : ( get_post_meta( $sid, '_osoul_stage', true ) ?: 'new' ), $issued ); ?></p>
		<table class="pp-t"><thead><tr>
			<th><?php esc_html_e( 'المنتج', 'osoul' ); ?></th>
			<th><?php esc_html_e( 'الكمية', 'osoul' ); ?></th>
			<th><?php echo esc_html( $issued ? __( 'تكلفة القطعة (لك)', 'osoul' ) : __( 'السعر', 'osoul' ) ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $items as $it ) : ?>
			<tr>
				<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
				<td class="pp-num"><?php echo (int) ( $it['qty'] ?? 0 ); ?></td>
				<td class="pp-num"><?php echo $issued ? esc_html( osoul_money( (float) ( $it['unit_price'] ?? 0 ) ) . ' ' . __( 'ر.س', 'osoul' ) ) : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( $issued ) : ?>
			<p style="margin-top:16px"><a class="pp-btn" href="<?php echo esc_url( add_query_arg( array( 'v' => 'make', 'supply' => $sid ), $d ) ); ?>">→ <?php esc_html_e( 'أنشئ عرضاً لعميلك', 'osoul' ); ?></a></p>
		<?php else : ?>
			<div class="pp-alert warn" style="margin-top:14px"><?php esc_html_e( 'بانتظار تسعير أصول لهذا الطلب. ستصلك الأسعار قريباً.', 'osoul' ); ?></div>
		<?php endif; ?>
	</div>
	<?php
}

/** Create a white-label customer quote from a priced supply order. */
function osoul_partner_view_make_quote( $pid, $sid ) {
	if ( (int) get_post_meta( $sid, '_osoul_partner_id', true ) !== (int) $pid
		|| 'supply' !== get_post_meta( $sid, '_osoul_kind', true ) || ! osoul_quote_is_issued( $sid ) ) {
		echo '<div class="pp-card"><p class="pp-muted">' . esc_html__( 'الطلب غير متاح.', 'osoul' ) . '</p></div>';
		return;
	}
	$items  = (array) get_post_meta( $sid, '_osoul_items', true );
	$margin = (float) osoul_partner_get( $pid, 'margin_min' );
	?>
	<div class="pp-card">
		<h2><?php esc_html_e( 'إنشاء عرض سعر لعميلك', 'osoul' ); ?></h2>
		<p class="sub"><?php esc_html_e( 'أدخل بيانات عميلك وسعر بيعك لكل بند. يصدر العرض باسم شركتك — ولا تراه أصول.', 'osoul' ); ?></p>
		<form method="post" id="pp-mk">
			<?php wp_nonce_field( 'osoul_partner_make' ); ?>
			<input type="hidden" name="pp_action" value="make">
			<input type="hidden" name="supply_id" value="<?php echo (int) $sid; ?>">
			<div class="pp-gh"><?php esc_html_e( 'بيانات العميل', 'osoul' ); ?></div>
			<div class="pp-row2">
				<label class="pp-f"><?php esc_html_e( 'اسم العميل / الشركة *', 'osoul' ); ?><input type="text" name="c_name" required></label>
				<label class="pp-f"><?php esc_html_e( 'جوال العميل', 'osoul' ); ?><input type="text" name="c_phone"></label>
			</div>
			<label class="pp-f"><?php esc_html_e( 'بريد العميل', 'osoul' ); ?><input type="email" name="c_email"></label>

			<div class="pp-gh"><?php esc_html_e( 'التسعير', 'osoul' ); ?>
				<?php if ( $margin > 0 ) : ?><span class="pp-muted" style="font-weight:600">(<?php echo esc_html( sprintf( __( 'الحد الأدنى للربح %s%%', 'osoul' ), rtrim( rtrim( (string) $margin, '0' ), '.' ) ) ); ?>)</span><?php endif; ?>
			</div>
			<table class="pp-t"><thead><tr>
				<th><?php esc_html_e( 'المنتج', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الكمية', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'تكلفتك', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'سعر بيعك', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'ربحك', 'osoul' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $items as $i => $it ) :
				$cost = (float) ( $it['unit_price'] ?? 0 );
				$qty  = (int) ( $it['qty'] ?? 0 );
				$min  = $cost * ( 1 + $margin / 100 );
				?>
				<tr data-cost="<?php echo esc_attr( $cost ); ?>" data-qty="<?php echo esc_attr( $qty ); ?>">
					<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
					<td class="pp-num"><?php echo (int) $qty; ?></td>
					<td class="pp-num pp-cost"><?php echo esc_html( osoul_money( $cost ) ); ?></td>
					<td><input class="pp-num pp-sell" type="number" min="<?php echo esc_attr( round( $min, 2 ) ); ?>" step="0.01" name="sell[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( round( max( $min, $cost ), 2 ) ); ?>" style="width:110px;padding:7px;border:1px solid #d5dae2;border-radius:6px"></td>
					<td class="pp-num pp-profit">0.00</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<div class="pp-row2" style="margin-top:12px">
				<label class="pp-f"><?php esc_html_e( 'خصم (ر.س، اختياري)', 'osoul' ); ?><input type="number" min="0" step="0.01" name="discount" value="0"></label>
				<label class="pp-f"><?php esc_html_e( 'صلاحية العرض (أيام)', 'osoul' ); ?><input type="number" min="1" step="1" name="validity" value="<?php echo esc_attr( (int) ( osoul_opt( 'validity_days' ) ?: 30 ) ); ?>"></label>
			</div>
			<p class="pp-muted"><?php esc_html_e( 'إجمالي ربحك المتوقع:', 'osoul' ); ?> <b class="pp-num" id="pp-total-profit">0.00</b> <?php esc_html_e( 'ر.س', 'osoul' ); ?></p>
			<button class="pp-btn block" type="submit"><?php esc_html_e( 'إصدار العرض باسم شركتي', 'osoul' ); ?></button>
		</form>
	</div>
	<script>
	(function(){
		var f=document.getElementById('pp-mk');
		function fmt(n){return (Math.round(n*100)/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
		function calc(){
			var tot=0;
			f.querySelectorAll('tbody tr').forEach(function(tr){
				var cost=parseFloat(tr.getAttribute('data-cost'))||0,
				    qty=parseInt(tr.getAttribute('data-qty'))||0,
				    sell=parseFloat(tr.querySelector('.pp-sell').value)||0,
				    p=(sell-cost)*qty;
				tr.querySelector('.pp-profit').textContent=fmt(p);
				tot+=p;
			});
			document.getElementById('pp-total-profit').textContent=fmt(tot);
		}
		f.addEventListener('input',calc); calc();
	})();
	</script>
	<?php
}

/** My customer quotes (private to the partner). */
function osoul_partner_view_quotes( $pid ) {
	$quotes = osoul_partner_customer_quotes( $pid );
	$new    = (int) ( $_GET['new'] ?? 0 );
	?>
	<div class="pp-card">
		<h2><?php esc_html_e( 'عروضي لعملائي', 'osoul' ); ?></h2>
		<?php if ( $new && (int) get_post_meta( $new, '_osoul_partner_id', true ) === (int) $pid ) :
			$tok = get_post_meta( $new, '_osoul_token', true ); ?>
			<div class="pp-alert ok"><?php esc_html_e( 'تم إصدار العرض بنجاح! شارك الرابط مع عميلك:', 'osoul' ); ?>
				<div class="pp-link"><?php echo esc_url( home_url( '/quote/' . $tok . '/' ) ); ?></div>
			</div>
		<?php endif; ?>
		<?php if ( ! $quotes ) : ?>
			<p class="pp-muted"><?php esc_html_e( 'لم تصدر أي عرض بعد.', 'osoul' ); ?></p>
		<?php else : ?>
			<table class="pp-t"><thead><tr>
				<th><?php esc_html_e( 'رقم العرض', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'العميل', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الإجمالي', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الربح', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الحالة', 'osoul' ); ?></th>
				<th></th>
			</tr></thead><tbody>
			<?php foreach ( $quotes as $qp ) :
				$items = (array) get_post_meta( $qp->ID, '_osoul_items', true );
				$cust  = (array) get_post_meta( $qp->ID, '_osoul_customer', true );
				$tot   = osoul_quote_totals( $items, (float) get_post_meta( $qp->ID, '_osoul_discount', true ) );
				$stage = get_post_meta( $qp->ID, '_osoul_stage', true ) ?: 'sent';
				$tok   = get_post_meta( $qp->ID, '_osoul_token', true );
				?>
				<tr>
					<td class="pp-num"><?php echo esc_html( get_post_meta( $qp->ID, '_osoul_number', true ) ); ?></td>
					<td><?php echo esc_html( $cust['name'] ?? '' ); ?></td>
					<td class="pp-num"><?php echo esc_html( osoul_money( $tot['total'] ) ); ?></td>
					<td class="pp-num"><?php echo esc_html( osoul_money( osoul_partner_quote_profit( $items ) ) ); ?></td>
					<td><?php echo osoul_partner_stage_pill( $stage, true ); ?></td>
					<td><a class="pp-btn sec" style="padding:6px 12px;font-size:12px" target="_blank" rel="noopener" href="<?php echo esc_url( home_url( '/quote/' . $tok . '/' ) ); ?>"><?php esc_html_e( 'فتح', 'osoul' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

/** Edit the partner's own brand identity. */
function osoul_partner_view_profile( $pid ) {
	$p = osoul_partner_profile( $pid );
	$g = static function ( $k ) use ( $p ) { return esc_attr( $p[ $k ] ?? '' ); };
	$color  = osoul_partner_sanitize_hex( $p['color'] ?? '', '#00344F' );
	$accent = osoul_partner_sanitize_hex( $p['accent'] ?? '', '#0074A4' );
	?>
	<div class="pp-card">
		<h2><?php esc_html_e( 'هويّة شركتي', 'osoul' ); ?></h2>
		<p class="sub"><?php esc_html_e( 'هذه البيانات والألوان تظهر على عروض الأسعار التي تصدرها لعملائك وعلى لوحتك.', 'osoul' ); ?></p>
		<?php if ( isset( $_GET['logoerr'] ) ) : ?><div class="pp-alert err"><?php esc_html_e( 'تعذّر رفع الشعار — تأكد أنها صورة PNG أو JPG.', 'osoul' ); ?></div><?php endif; ?>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'osoul_partner_profile' ); ?>
			<input type="hidden" name="pp_action" value="profile">
			<div class="pp-row2">
				<label class="pp-f"><?php esc_html_e( 'اسم الشركة (عربي)', 'osoul' ); ?><input type="text" name="company_ar" value="<?php echo $g( 'company_ar' ); ?>"></label>
				<label class="pp-f"><?php esc_html_e( 'اسم الشركة (إنجليزي)', 'osoul' ); ?><input type="text" name="company_en" value="<?php echo $g( 'company_en' ); ?>"></label>
			</div>
			<div class="pp-gh"><?php esc_html_e( 'الشعار والألوان', 'osoul' ); ?></div>
			<div class="pp-colorrow">
				<div class="pp-color"><span><?php esc_html_e( 'الشعار الحالي', 'osoul' ); ?></span>
					<?php if ( ! empty( $p['logo'] ) ) : ?><img class="pp-logo-cur" src="<?php echo esc_url( $p['logo'] ); ?>" alt=""><?php else : ?><span class="pp-muted"><?php esc_html_e( 'لا يوجد بعد', 'osoul' ); ?></span><?php endif; ?>
				</div>
				<label class="pp-f" style="flex:1;min-width:220px;margin:0"><?php esc_html_e( 'رفع شعار الشركة (PNG/JPG)', 'osoul' ); ?><input type="file" name="logo_file" accept="image/png,image/jpeg"></label>
			</div>
			<div class="pp-colorrow">
				<label class="pp-color"><?php esc_html_e( 'اللون الأساسي', 'osoul' ); ?><input type="color" name="color" value="<?php echo esc_attr( $color ); ?>" id="pp-c1"></label>
				<label class="pp-color"><?php esc_html_e( 'لون التمييز', 'osoul' ); ?><input type="color" name="accent" value="<?php echo esc_attr( $accent ); ?>" id="pp-c2"></label>
				<div style="flex:1;min-width:210px">
					<span class="pp-muted"><?php esc_html_e( 'معاينة فورية لعرض سعرك:', 'osoul' ); ?></span>
					<div class="pp-preview" id="pp-prev" style="--pp-primary:<?php echo esc_attr( $color ); ?>;--pp-accent:<?php echo esc_attr( $accent ); ?>">
						<div class="h"><b><?php echo esc_html( $p['company_ar'] ?: 'شركتك' ); ?></b><span class="tag"><?php esc_html_e( 'عرض سعر', 'osoul' ); ?></span></div>
						<div class="b"><?php esc_html_e( 'الإجمالي النهائي', 'osoul' ); ?>: <span class="tot">1,150.00 <?php esc_html_e( 'ر.س', 'osoul' ); ?></span></div>
					</div>
				</div>
			</div>
			<div class="pp-row2">
				<label class="pp-f"><?php esc_html_e( 'الرقم الضريبي (VAT)', 'osoul' ); ?><input type="text" name="vat" value="<?php echo $g( 'vat' ); ?>"></label>
				<label class="pp-f"><?php esc_html_e( 'السجل التجاري (CR)', 'osoul' ); ?><input type="text" name="cr" value="<?php echo $g( 'cr' ); ?>"></label>
			</div>
			<div class="pp-row2">
				<label class="pp-f"><?php esc_html_e( 'العنوان (عربي)', 'osoul' ); ?><input type="text" name="address_ar" value="<?php echo $g( 'address_ar' ); ?>"></label>
				<label class="pp-f"><?php esc_html_e( 'العنوان (إنجليزي)', 'osoul' ); ?><input type="text" name="address_en" value="<?php echo $g( 'address_en' ); ?>"></label>
			</div>
			<label class="pp-f"><?php esc_html_e( 'الحساب البنكي / الآيبان', 'osoul' ); ?><textarea name="bank" rows="2"><?php echo esc_textarea( $p['bank'] ?? '' ); ?></textarea></label>
			<label class="pp-f"><?php esc_html_e( 'جوال التواصل', 'osoul' ); ?><input type="text" name="phone" value="<?php echo $g( 'phone' ); ?>"></label>
			<div class="pp-row2">
				<label class="pp-f"><?php esc_html_e( 'الشروط والأحكام (عربي)', 'osoul' ); ?><textarea name="terms_ar" rows="3"><?php echo esc_textarea( $p['terms_ar'] ?? '' ); ?></textarea></label>
				<label class="pp-f"><?php esc_html_e( 'الشروط والأحكام (إنجليزي)', 'osoul' ); ?><textarea name="terms_en" rows="3"><?php echo esc_textarea( $p['terms_en'] ?? '' ); ?></textarea></label>
			</div>
			<p class="pp-muted"><?php esc_html_e( 'بادئة أرقام عروضك:', 'osoul' ); ?> <b><?php echo esc_html( $p['prefix'] ?: '—' ); ?></b> · <?php esc_html_e( 'الحد الأدنى للربح:', 'osoul' ); ?> <b><?php echo esc_html( rtrim( rtrim( (string) ( $p['margin_min'] ?: '0' ), '0' ), '.' ) ?: '0' ); ?>%</b> <?php esc_html_e( '(تحدّدهما الإدارة)', 'osoul' ); ?></p>
			<button class="pp-btn block" type="submit"><?php esc_html_e( 'حفظ الهوية', 'osoul' ); ?></button>
		</form>
	</div>
	<script>
	(function(){var a=document.getElementById('pp-c1'),b=document.getElementById('pp-c2'),p=document.getElementById('pp-prev');if(!a||!p)return;function u(){p.style.setProperty('--pp-primary',a.value);p.style.setProperty('--pp-accent',b.value);}a.addEventListener('input',u);b.addEventListener('input',u);})();
	</script>
	<?php
}

/** A coloured stage pill. */
function osoul_partner_stage_pill( $stage, $issued = false ) {
	$stages = osoul_quote_stages();
	if ( ! $issued && in_array( $stage, array( 'new', 'pricing' ), true ) ) {
		$st = $stages[ $stage ];
		return '<span class="pp-pill" style="background:' . esc_attr( $st['color'] ) . '">' . esc_html( $st['ar'] ) . '</span>';
	}
	$st = $stages[ $stage ] ?? $stages['sent'];
	return '<span class="pp-pill" style="background:' . esc_attr( $st['color'] ) . '">' . esc_html( $st['ar'] ) . '</span>';
}

/* =========================================================================
 *  WP Admin — partner management (approve / suspend / re-invite / settings)
 * ====================================================================== */

/** Number of partner accounts still awaiting admin approval (for the menu badge). */
function osoul_partner_pending_count() {
	$ids = get_users( array( 'role' => 'osoul_partner', 'fields' => 'ID' ) );
	$n   = 0;
	foreach ( $ids as $uid ) {
		if ( ! osoul_partner_is_approved( $uid ) ) { $n++; }
	}
	return $n;
}

add_action( 'admin_menu', function () {
	$pending = osoul_partner_pending_count();
	$title   = __( 'الشركاء', 'osoul' );
	// WordPress-style count bubble on the menu item when registrations are pending.
	$label = $pending
		? $title . ' <span class="awaiting-mod"><span class="pending-count">' . (int) $pending . '</span></span>'
		: $title;
	add_menu_page(
		$title,
		$label,
		'manage_options',
		'osoul-partners',
		'osoul_partner_admin_page',
		'dashicons-groups',
		27
	);
} );

/** Handle approve / suspend / reinvite / save from the admin page (admin-post). */
add_action( 'admin_post_osoul_partner_action', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'forbidden' );
	}
	check_admin_referer( 'osoul_partner_admin' );
	$pid = (int) ( $_POST['pid'] ?? 0 );
	$do  = sanitize_key( $_POST['do'] ?? '' );
	$msg = 'done';

	if ( $pid && 'partner' === osoul_portal_role( $pid ) ) {
		if ( 'approve' === $do ) {
			$res = osoul_partner_approve( $pid );
			$msg = is_wp_error( $res ) ? 'err' : 'approved';
		} elseif ( 'suspend' === $do ) {
			update_user_meta( $pid, '_osoul_active', 0 );
			$msg = 'suspended';
		} elseif ( 'activate' === $do ) {
			update_user_meta( $pid, '_osoul_active', 1 );
			$msg = 'activated';
		} elseif ( 'reinvite' === $do ) {
			osoul_portal_new_invite( $pid );
			$msg = 'reinvited';
		} elseif ( 'save' === $do ) {
			$prefix = substr( strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_POST['prefix'] ?? '' ) ) ), 0, 8 );
			update_user_meta( $pid, '_osoul_p_prefix', $prefix );
			update_user_meta( $pid, '_osoul_p_margin_min', (string) max( 0, (float) ( $_POST['margin_min'] ?? 0 ) ) );
			$msg = 'saved';
		}
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'osoul-partners', 'msg' => $msg ), admin_url( 'admin.php' ) ) );
	exit;
} );

function osoul_partner_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$partners = get_users( array( 'role' => 'osoul_partner', 'orderby' => 'registered', 'order' => 'DESC' ) );
	$pending  = array();
	$active   = array();
	foreach ( $partners as $u ) {
		if ( osoul_partner_is_approved( $u->ID ) ) {
			$active[] = $u;
		} else {
			$pending[] = $u;
		}
	}
	$action = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'الشركاء (Partner Portal)', 'osoul' ); ?></h1>
		<?php if ( isset( $_GET['msg'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( osoul_partner_admin_msg( sanitize_key( $_GET['msg'] ) ) ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'طلبات انضمام بانتظار الاعتماد', 'osoul' ); ?> (<?php echo (int) count( $pending ); ?>)</h2>
		<?php if ( ! $pending ) : ?>
			<p><?php esc_html_e( 'لا توجد طلبات معلّقة.', 'osoul' ); ?></p>
		<?php else : ?>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'الشركة', 'osoul' ); ?></th><th><?php esc_html_e( 'البريد', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الجوال', 'osoul' ); ?></th><th><?php esc_html_e( 'الرقم الضريبي', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'إجراء', 'osoul' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $pending as $u ) : ?>
				<tr>
					<td><strong><?php echo esc_html( osoul_partner_get( $u->ID, 'company_ar' ) ?: $u->display_name ); ?></strong></td>
					<td><?php echo esc_html( $u->user_email ); ?></td>
					<td><?php echo esc_html( osoul_partner_get( $u->ID, 'phone' ) ); ?></td>
					<td><?php echo esc_html( osoul_partner_get( $u->ID, 'vat' ) ); ?></td>
					<td>
						<form method="post" action="<?php echo $action; ?>" style="display:inline">
							<?php wp_nonce_field( 'osoul_partner_admin' ); ?>
							<input type="hidden" name="action" value="osoul_partner_action">
							<input type="hidden" name="do" value="approve">
							<input type="hidden" name="pid" value="<?php echo (int) $u->ID; ?>">
							<button class="button button-primary"><?php esc_html_e( 'اعتماد + إرسال دعوة', 'osoul' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2 style="margin-top:30px"><?php esc_html_e( 'الشركاء المعتمدون', 'osoul' ); ?> (<?php echo (int) count( $active ); ?>)</h2>
		<?php if ( ! $active ) : ?>
			<p><?php esc_html_e( 'لا يوجد شركاء معتمدون بعد.', 'osoul' ); ?></p>
		<?php else : ?>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'الشركة', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الحالة', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'بادئة الترقيم', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'حد أدنى للربح %', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'إجراءات', 'osoul' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $active as $u ) :
				$act = osoul_portal_is_active( $u->ID );
				$pend_invite = (bool) get_user_meta( $u->ID, '_osoul_invite', true );
				?>
				<tr>
					<td><strong><?php echo esc_html( osoul_partner_get( $u->ID, 'company_ar' ) ?: $u->display_name ); ?></strong><br><span class="description"><?php echo esc_html( $u->user_email ); ?></span></td>
					<td>
						<?php echo $act ? '<span style="color:#1a7e42">● ' . esc_html__( 'نشط', 'osoul' ) . '</span>' : '<span style="color:#b32d2e">● ' . esc_html__( 'موقوف', 'osoul' ) . '</span>'; ?>
						<?php if ( $pend_invite ) :
							$inv_url = osoul_portal_invite_url( get_user_meta( $u->ID, '_osoul_invite', true ) ); ?>
							<br><span class="description"><?php esc_html_e( 'لم يفعّل بعد — رابط التفعيل (أرسله للشريك):', 'osoul' ); ?></span>
							<input type="text" readonly onclick="this.select()" value="<?php echo esc_attr( $inv_url ); ?>" style="width:100%;max-width:320px;font-size:11px;margin-top:3px">
						<?php endif; ?>
					</td>
					<td colspan="2">
						<form method="post" action="<?php echo $action; ?>" style="display:flex;gap:6px;align-items:center">
							<?php wp_nonce_field( 'osoul_partner_admin' ); ?>
							<input type="hidden" name="action" value="osoul_partner_action">
							<input type="hidden" name="do" value="save">
							<input type="hidden" name="pid" value="<?php echo (int) $u->ID; ?>">
							<input type="text" name="prefix" value="<?php echo esc_attr( osoul_partner_get( $u->ID, 'prefix' ) ); ?>" size="8" style="width:90px">
							<input type="number" name="margin_min" value="<?php echo esc_attr( osoul_partner_get( $u->ID, 'margin_min' ) ); ?>" min="0" step="0.5" style="width:80px">
							<button class="button"><?php esc_html_e( 'حفظ', 'osoul' ); ?></button>
						</form>
					</td>
					<td>
						<?php foreach ( array(
							$act ? array( 'suspend', __( 'إيقاف', 'osoul' ) ) : array( 'activate', __( 'تفعيل', 'osoul' ) ),
							array( 'reinvite', __( 'دعوة جديدة', 'osoul' ) ),
						) as $btn ) : ?>
							<form method="post" action="<?php echo $action; ?>" style="display:inline">
								<?php wp_nonce_field( 'osoul_partner_admin' ); ?>
								<input type="hidden" name="action" value="osoul_partner_action">
								<input type="hidden" name="do" value="<?php echo esc_attr( $btn[0] ); ?>">
								<input type="hidden" name="pid" value="<?php echo (int) $u->ID; ?>">
								<button class="button button-small"><?php echo esc_html( $btn[1] ); ?></button>
							</form>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<p class="description" style="margin-top:16px"><?php esc_html_e( 'طلبات توريد الشركاء تظهر في لوحة العروض كالمعتاد (المصدر: شريك) لتسعيرها. عروض الشركاء لعملائهم لا تظهر لكم إطلاقاً.', 'osoul' ); ?></p>
	</div>
	<?php
}

function osoul_partner_admin_msg( $key ) {
	$map = array(
		'approved'  => 'تم اعتماد الشريك وإرسال رابط التفعيل إلى بريده.',
		'suspended' => 'تم إيقاف الحساب.',
		'activated' => 'تم تفعيل الحساب.',
		'reinvite'  => 'تم إنشاء رابط دعوة جديد.',
		'reinvited' => 'تم إنشاء رابط دعوة جديد.',
		'saved'     => 'تم حفظ الإعدادات.',
		'err'       => 'تعذّر تنفيذ الإجراء.',
	);
	return $map[ $key ] ?? 'تم.';
}
