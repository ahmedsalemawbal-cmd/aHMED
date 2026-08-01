<?php
/**
 * أداة معاينة (خارج ووردبريس): تُشغّل قوالب الإضافة الحقيقية عبر دوال بديلة
 * لإنتاج ملف HTML ثابت للمعاينة والتصوير. ليست جزءًا من الإضافة نفسها.
 *
 * الاستخدام:
 *   FALAK_PAGE=home|enroll|review|gallery|teachers|dashboard php preview/render.php
 *   FALAK_AUTH=1 لعرض الداش بورد بعد الدخول.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALAK_DIR', dirname( __DIR__ ) . '/' );
define( 'FALAK_URL', '' );
define( 'FALAK_VERSION', 'preview' );

$PAGE = getenv( 'FALAK_PAGE' ) ?: 'home';
$AUTH = getenv( 'FALAK_AUTH' ) === '1';

/* ── دوال ووردبريس البديلة (stubs) ── */
function add_action() {}
function add_filter() {}
function remove_action() {}
function do_action() {}
function apply_filters( $t, $v = null ) { return $v; }
function register_post_type() {}
function register_setting() {}
function register_rest_route() {}
function add_meta_box() {}
function add_submenu_page() {}
function add_role() {}
function get_role() { return null; }
function wp_nonce_field() {}
function wp_nonce_url( $url ) { return $url; }
function wp_create_nonce() { return 'preview'; }
function current_user_can() { return $GLOBALS['FK_AUTH']; }
function is_user_logged_in() { return $GLOBALS['FK_AUTH']; }
function wp_get_current_user() { return (object) array( 'display_name' => 'المدير' ); }
function get_users() { return array(); }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) { echo ' selected'; } }
function get_option( $k, $d = false ) { return $d; }
function update_option() {}
function get_post_meta() { return ''; }
function update_post_meta() {}
function get_posts() { return array(); }
function get_page_by_path() { return null; }
function get_permalink() { return 'https://alfalak-almunir.example/dashboard/'; }
function wp_insert_post() { return 0; }
function get_the_post_thumbnail_url() { return false; }
function is_front_page() { return $GLOBALS['FK_PAGE'] === 'home'; }
function is_page() { return true; }
function is_home() { return false; }
function is_singular() { return false; }
function get_queried_object() { return null; }
function home_url( $p = '' ) { return 'https://alfalak-almunir.example/' . ltrim( (string) $p, '/' ); }
function admin_url( $p = '' ) { return '#'; }
function rest_url( $p = '' ) { return '#'; }
function add_query_arg( $args, $url = '' ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $k ) ); }
function falak_page_id() { return 1; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_js( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_kses( $s ) { return (string) $s; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function sanitize_email( $s ) { return (string) $s; }
function sanitize_textarea_field( $s ) { return (string) $s; }

$GLOBALS['FK_AUTH'] = $AUTH;
$GLOBALS['FK_PAGE'] = $PAGE;
if ( getenv( 'FALAK_TAB' ) ) { $_GET['tab'] = getenv( 'FALAK_TAB' ); }

/* ── تحميل ملفات الإضافة الحقيقية ── */
require FALAK_DIR . 'inc/helpers.php';
require FALAK_DIR . 'inc/settings.php';
require FALAK_DIR . 'inc/programs.php';
require FALAK_DIR . 'inc/content.php';
require FALAK_DIR . 'inc/reviews.php';
require FALAK_DIR . 'inc/dashboard.php';
require FALAK_DIR . 'inc/header.php';
require FALAK_DIR . 'inc/footer.php';
require FALAK_DIR . 'inc/templates.php';

/* ── متغيرات الأنماط + بيانات JS ── */
$vars = ':root{'
	. '--fk-pattern:url("' . falak_pattern_data_uri( '1F9D5A', '0.5' ) . '");'
	. '--fk-pattern-gold:url("' . falak_pattern_data_uri( 'C6A15B', '1' ) . '");'
	. '--fk-pattern-light:url("' . falak_pattern_data_uri( 'FFFFFF', '1' ) . '");'
	. '}';
$css = file_get_contents( FALAK_DIR . 'assets/css/falak.css' );
$js  = file_get_contents( FALAK_DIR . 'assets/js/falak.js' );
$jsdata = json_encode( array(
	'grades'   => array( 'بنين' => falak_grades_boys(), 'بنات' => falak_grades_girls() ),
	'whatsapp' => falak_wa_number(),
), JSON_UNESCAPED_UNICODE );

$body_class = ( 'dashboard' === $PAGE ) ? 'falak-dashboard' : '';
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>مدرسة الفلك المنير</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style><?php echo $css; ?></style>
<style><?php echo $vars; ?></style>
</head>
<body class="<?php echo $body_class; ?>">
<?php
falak_render_header();
echo '<main id="falak-page" class="falak-wrap" role="main">';
switch ( $PAGE ) {
	case 'enroll':    falak_tpl_enroll(); break;
	case 'review':    falak_tpl_review(); break;
	case 'gallery':   falak_tpl_gallery(); break;
	case 'teachers':  falak_tpl_teachers(); break;
	case 'programs':  falak_tpl_programs(); break;
	case 'contact':   falak_tpl_contact(); break;
	case 'about':     falak_tpl_about(); break;
	case 'faq':       falak_tpl_faq(); break;
	case 'dashboard': falak_tpl_dashboard(); break;
	default:          falak_tpl_home();
}
echo '</main>';
falak_render_footer();
?>
<script>window.falakData=<?php echo $jsdata; ?>;</script>
<script><?php echo $js; ?></script>
</body>
</html>
