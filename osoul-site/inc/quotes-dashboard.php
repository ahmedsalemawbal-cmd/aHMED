<?php
/**
 * Public quote route + shared standalone-page chrome.
 *
 * The staff dashboard that used to live here is now the B2B portal SPA
 * (inc/portal-app.php, served at priority 0). What remains is the public
 * /quote/{token} route and the standalone-page head/foot helpers reused by the
 * public quote view (inc/quotes-public.php).
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Route /dashboard and /quote/{token} before the theme renders.
 */
add_action( 'template_redirect', function () {
	$slug = osoul_request_slug();
	// NOTE: /dashboard is now served by the B2B portal (inc/portal-app.php) at
	// priority 0. This handler only keeps the public token quote route.
	if ( preg_match( '#^quote/([a-f0-9]{8,})$#', $slug, $m ) && function_exists( 'osoul_render_public_quote' ) ) {
		osoul_render_public_quote( $m[1] );
		exit;
	}
}, 1 );

/** Base URL of the dashboard. */
function osoul_dashboard_url( $args = array() ) {
	$url = home_url( '/dashboard/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Shared standalone-page head (Cairo + Inter, base app CSS).
 *
 * @param string $title
 * @param string $lang 'ar'|'en'
 */
function osoul_app_head( $title, $lang = 'ar' ) {
	$dir = ( 'en' === $lang ) ? 'ltr' : 'rtl';
	?><!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--n:#00344F;--n2:#00263B;--o:#0074A4;--o2:#0089BE;--bg:#f4f6f9;--card:#fff;--line:#e6e9ef;--muted:#6b7280}
body{font-family:'Cairo','IBM Plex Sans',sans-serif;background:var(--bg);color:#1f2937;direction:<?php echo esc_attr( $dir ); ?>;line-height:1.6}
html[lang="en"] body{font-family:'IBM Plex Sans','Cairo',sans-serif} /* English → IBM Plex Sans */
a{color:inherit}
.osa-top{background:var(--n);color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.osa-brand{display:flex;align-items:center;gap:12px;font-weight:700;font-size:16px}
.osa-brand img{height:38px;width:auto;background:#fff;border-radius:8px;padding:3px}
.osa-top a{color:rgba(255,255,255,.85);text-decoration:none;font-size:13px}
.osa-top a:hover{color:#fff}
.osa-wrap{max-width:1100px;margin:0 auto;padding:24px}
.osa-card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,52,79,.05)}
.osa-h{font-size:18px;font-weight:700;color:var(--n);margin-bottom:14px}
.osa-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:var(--o);color:#fff;border:none;border-radius:8px;padding:11px 18px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .2s;min-height:42px}
.osa-btn:hover{background:var(--o2)}
.osa-btn.sec{background:#eef1f5;color:var(--n)}
.osa-btn.sec:hover{background:#e2e7ee}
.osa-btn.wa{background:#25D366}.osa-btn.wa:hover{background:#1eb858}
.osa-btn.sm{padding:7px 12px;font-size:12px;min-height:34px;border-radius:6px}
.osa-input,.osa-select{width:100%;border:1.5px solid var(--line);border-radius:8px;padding:10px 13px;font-family:inherit;font-size:14px;background:#fff;outline:none;transition:border-color .2s}
.osa-input:focus,.osa-select:focus{border-color:var(--o)}
.osa-table{width:100%;border-collapse:collapse;font-size:14px}
.osa-table th,.osa-table td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:start}
.osa-table th{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
.osa-table tr:hover td{background:#fafbfc}
.osa-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff}
.osa-num{font-family:'IBM Plex Sans',sans-serif;font-variant-numeric:tabular-nums}
.osa-muted{color:var(--muted);font-size:13px}
.osa-row{display:grid;gap:14px}
.osa-notice{padding:12px 16px;border-radius:8px;font-size:14px;font-weight:600;margin-bottom:16px}
.osa-notice.ok{background:#e7f7ee;color:#1a7e42;border:1px solid #b6e7c9}
.osa-notice.err{background:#fdeaea;color:#b32d2e;border:1px solid #f3c2c2}
.osa-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.osa-filters .osa-input,.osa-filters .osa-select{width:auto;min-width:160px}
@media(max-width:680px){.osa-table thead{display:none}.osa-table tr{display:block;border:1px solid var(--line);border-radius:10px;margin-bottom:10px;padding:6px}.osa-table td{display:flex;justify-content:space-between;border:none;padding:8px 10px}.osa-table td::before{content:attr(data-label);color:var(--muted);font-weight:600}}
</style>
</head>
<body>
	<?php
}

/** Shared standalone-page foot. */
function osoul_app_foot() {
	echo '</body></html>';
}
