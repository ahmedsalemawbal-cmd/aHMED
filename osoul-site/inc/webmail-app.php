<?php
/**
 * Employee webmail — the /dashboard application shell (employee variant).
 *
 * When a logged-in employee opens /dashboard, the shared portal router
 * (inc/portal-app.php) hands off to osoul_webmail_render_app() instead of the
 * sales SPA. This renders a standalone, theme-aware page whose body is a live
 * webmail client (assets/js/webmail.js) talking to the osoul/v1/mail REST API.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Render the employee webmail single-page app. */
function osoul_webmail_render_app() {
	nocache_headers();
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
	}

	$user = wp_get_current_user();
	$lang = function_exists( 'osoul_portal_lang' ) ? osoul_portal_lang() : 'ar';
	if ( 'en' !== $lang && 'ar' !== $lang ) { $lang = 'ar'; }
	$dir  = ( 'en' === $lang ) ? 'ltr' : 'rtl';
	$cfg  = osoul_mail_config( $user->ID );

	// Brand logo shown in the top bar. Prefer Site Settings, then the uploaded
	// Osoul artwork; filter `osoul_webmail_logo` to override.
	$osoul_logo = function_exists( 'osoul_opt' ) ? ( osoul_opt( 'logo_icon' ) ?: osoul_opt( 'logo' ) ) : '';
	if ( '' === $osoul_logo ) {
		$osoul_logo = 'https://osoulalbinaa.com/wp-content/uploads/2026/08/' . rawurlencode( 'تصميم-بدون-عنوان-89' ) . '.png';
	}
	$osoul_logo = apply_filters( 'osoul_webmail_logo', $osoul_logo );

	?><!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<script>/* apply saved light/dark before first paint (no flash) */(function(){try{var t=localStorage.getItem('osoul_theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&matchMedia('(prefers-color-scheme:dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<title><?php echo esc_html( 'ar' === $lang ? 'بريد الموظف — أصول البناء' : 'Employee Mail — Osoul Albinaa' ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..500,0..1,0&display=block" rel="stylesheet">
	<?php
	$css = OSOUL_DIR . 'assets/css/webmail.css';
	$ver = is_readable( $css ) ? filemtime( $css ) : OSOUL_VERSION;
	echo '<link rel="stylesheet" href="' . esc_url( OSOUL_URL . 'assets/css/webmail.css?v=' . $ver ) . '">' . "\n";
	?>
</head>
<body class="om-page-body<?php echo 'en' === $lang ? ' lang-en' : ''; ?>">
	<div id="osoul-mail" class="om-app">
		<div class="om-boot"><span class="om-spin"></span></div>
	</div>
	<script>
	window.OSOUL_MAIL = {
		rest:      <?php echo wp_json_encode( esc_url_raw( rest_url( 'osoul/v1/mail/' ) ) ); ?>,
		nonce:     <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
		name:      <?php echo wp_json_encode( $user->display_name ); ?>,
		email:     <?php echo wp_json_encode( $cfg['email'] ); ?>,
		lang:      <?php echo wp_json_encode( $lang ); ?>,
		home:      <?php echo wp_json_encode( home_url( '/' ) ); ?>,
		logo:      <?php echo wp_json_encode( esc_url_raw( $osoul_logo ) ); ?>,
		mark:      <?php echo wp_json_encode( esc_url_raw( OSOUL_URL . 'assets/img/osoul-mark.png' ) ); ?>,
		logout:    <?php echo wp_json_encode( add_query_arg( 'logout', 1, home_url( '/dashboard/' ) ) ); ?>,
		poll:      12000
	};
	</script>
	<?php
	$js  = OSOUL_DIR . 'assets/js/webmail.js';
	$jver = is_readable( $js ) ? filemtime( $js ) : OSOUL_VERSION;
	echo '<script src="' . esc_url( OSOUL_URL . 'assets/js/webmail.js?v=' . $jver ) . '"></script>' . "\n";
	?>
</body>
</html>
	<?php
}
