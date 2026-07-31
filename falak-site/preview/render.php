<?php
/**
 * أداة معاينة (خارج ووردبريس): تُشغّل قوالب الإضافة الحقيقية عبر دوال بديلة
 * لإنتاج ملف HTML ثابت للمعاينة والتصوير. ليست جزءًا من الإضافة نفسها.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALAK_DIR', dirname( __DIR__ ) . '/' );
define( 'FALAK_URL', '' );
define( 'FALAK_VERSION', 'preview' );

/* ── دوال ووردبريس البديلة (stubs) ── */
function add_action() {}
function add_filter() {}
function remove_action() {}
function do_action() {}
function apply_filters( $t, $v = null ) { return $v; }
function register_post_type() {}
function register_setting() {}
function add_meta_box() {}
function add_submenu_page() {}
function wp_nonce_field() {}
function current_user_can() { return true; }
function selected() {}
function get_option( $k, $d = false ) { return $d; }
function update_option() {}
function get_post_meta() { return ''; }
function update_post_meta() {}
function get_posts() { return array(); }
function get_page_by_path() { return null; }
function wp_insert_post() { return 0; }
function get_the_post_thumbnail_url() { return false; }
function is_front_page() { return true; }
function is_page() { return false; }
function is_home() { return false; }
function is_singular() { return false; }
function get_queried_object() { return null; }
function wp_create_nonce() { return 'preview'; }
function home_url( $p = '' ) { return 'https://alfalak-almunir.example/' . ltrim( (string) $p, '/' ); }
function admin_url( $p = '' ) { return '#'; }
function rest_url( $p = '' ) { return '#'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_kses( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function sanitize_email( $s ) { return (string) $s; }
function sanitize_textarea_field( $s ) { return (string) $s; }

/* ── تحميل ملفات الإضافة الحقيقية ── */
require FALAK_DIR . 'inc/helpers.php';
require FALAK_DIR . 'inc/settings.php';
require FALAK_DIR . 'inc/programs.php';
require FALAK_DIR . 'inc/header.php';
require FALAK_DIR . 'inc/footer.php';
require FALAK_DIR . 'inc/templates.php';

/* ── متغيرات الأنماط (كما في performance.php) ── */
$vars = ':root{'
	. '--fk-pattern:url("' . falak_pattern_data_uri( '1F9D5A', '0.5' ) . '");'
	. '--fk-pattern-gold:url("' . falak_pattern_data_uri( 'C6A15B', '1' ) . '");'
	. '--fk-pattern-light:url("' . falak_pattern_data_uri( 'FFFFFF', '1' ) . '");'
	. '}';
$css = file_get_contents( FALAK_DIR . 'assets/css/falak.css' );
$js  = file_get_contents( FALAK_DIR . 'assets/js/falak.js' );

/* ── إخراج الصفحة ── */
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>مدرسة الفلك المنير لتحفيظ القرآن الكريم</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style><?php echo $css; ?></style>
<style><?php echo $vars; ?></style>
</head>
<body>
<?php
falak_render_header();
echo '<main id="falak-page" class="falak-wrap" role="main">';
$page = getenv( 'FALAK_PAGE' ) ?: 'home';
switch ( $page ) {
	case 'enroll':   falak_tpl_enroll(); break;
	case 'programs': falak_tpl_programs(); break;
	case 'about':    falak_tpl_about(); break;
	case 'teachers': falak_tpl_teachers(); break;
	case 'gallery':  falak_tpl_gallery(); break;
	case 'faq':      falak_tpl_faq(); break;
	case 'contact':  falak_tpl_contact(); break;
	default:         falak_tpl_home();
}
echo '</main>';
falak_render_footer();
?>
<script><?php echo $js; ?></script>
</body>
</html>
