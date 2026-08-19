<?php
/**
 * مُحاكي ووردبريس والإضافة — لتصيير قوالب التطبيق الحقيقية بلا خادم.
 *
 * الغرض واحد: أن أرى **ما تُخرجه الإضافة فعلًا** لا ما أظنّه. المقارنة
 * البصرية على DOM مصنوع باليد تُثبت أن مكوّنًا يبدو صحيحًا في مختبري،
 * ولا تُثبت شيئًا عمّا سيراه وليّ الأمر. فهذه المحاكاة تُشغّل الملفّات
 * نفسها بأسمائها، ولا تُعيد كتابة سطر منها.
 *
 * البيانات مطابقة لبيانات التصميم عمدًا (أحمد وأبناؤه الثلاثة، ومسار
 * «الياسمين») — فتصير المقارنة صورةً بصورة لا تقريبًا.
 */

declare(strict_types=1);

define('ABSPATH', '/tmp/wp/');
define('SCH_PATH', '/home/user/aHMED/school-system/');
define('SCH_URL', '/wp-content/plugins/school-system/');
define('SCH_VERSION', '9.7.1');
define('MINUTE_IN_SECONDS', 60);
define('SCH_API_NS', 'school/v1');

// ── دوال ووردبريس ───────────────────────────────────────────────────

function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
function _n($one, $many, $n, $d = '') { return $n == 1 ? $one : str_replace('%s', (string) $n, $many); }
function _x($t, $c, $d = '') { return $t; }
function esc_html__($t, $d = '') { return esc_html($t); }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_html_e($t, $d = '') { echo esc_html($t); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function esc_url($u) { return htmlspecialchars((string) $u, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($t) { return esc_html($t); }
function absint($n) { return abs((int) $n); }
/** ووردبريس بلغة عربية يحوّل الأرقام إلى هندية — فتُحاكى كما هي لا كما تسهل */
function sch_ar_digits(string $t): string {
    return strtr($t, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩', ','=>'٬', '.'=>'٫']);
}
function number_format_i18n($n, $d = 0) { return sch_ar_digits(number_format((float) $n, $d)); }
function wp_date($f, $ts = null) {
    $ar_m = [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $ar_d = ['Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    $ts = $ts ?? time();
    $out = date($f, $ts);
    $out = strtr($out, $ar_d);
    $out = strtr($out, [date('M', $ts) => $ar_m[(int) date('n', $ts)], date('F', $ts) => $ar_m[(int) date('n', $ts)]]);
    return sch_ar_digits($out);
}
function current_time($f, $gmt = 0) { return $f === 'timestamp' ? time() : date($f); }
function get_bloginfo($k = '') { return 'مدرسة الملك المنير الأهلية'; }
function get_current_user_id() { return 7; }
function wp_get_current_user() { return (object) ['ID' => 7, 'display_name' => 'أحمد سعد العتيبي']; }
function wp_nonce_field($a = '', $n = '_wpnonce', $r = true, $e = true) { echo '<input type="hidden" name="' . esc_attr($n) . '" value="demo">'; }
function wp_create_nonce($a = '') { return 'demo'; }
function wp_json_encode($d, $o = 0) { return json_encode($d, $o | JSON_UNESCAPED_UNICODE); }
function add_query_arg($a, $b = null, $c = null) {
    $url = is_array($a) ? (string) $b : (string) $c;
    $args = is_array($a) ? $a : [$a => $b];
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
}
function selected($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' selected' : ''; if ($e) { echo $r; } return $r; }
function checked($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' checked' : ''; if ($e) { echo $r; } return $r; }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function home_url($p = '') { return 'https://school.test' . $p; }
function admin_url($p = '') { return 'https://school.test/wp-admin/' . $p; }
function is_rtl() { return true; }
function rest_url($p = '') { return 'https://school.test/wp-json/' . $p; }
function wp_localize_script(...$a) {}
function plugins_url($p = '', $f = '') { return SCH_URL . $p; }
function apply_filters($t, $v) { return $v; }
function do_action($t, ...$a) {}
function wp_kses_post($t) { return $t; }
function get_userdata($id) { return (object) ['display_name' => 'أحمد سعد العتيبي']; }

// ── دوال الإضافة ────────────────────────────────────────────────────

function sch_settings($k, $d = '') {
    return [
        'school_name'          => 'مدرسة الملك المنير الأهلية',
        'attendance_day_end'   => '13:00',
        'attendance_day_start' => '07:00',
        'currency'             => 'ر.س',
    ][$k] ?? $d;
}

function sch_asset($p) { return SCH_URL . $p; }

function sch_icon($name, $size = 20) {
    $p = SCH_ICONS[$name] ?? SCH_ICONS['check'];
    return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true">' . $p . '</svg>';
}

function sch_avatar_svg($letter, $size = 44) {
    return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 44 44" aria-hidden="true">'
        . '<rect width="44" height="44" rx="22" fill="currentColor" opacity=".14"/>'
        . '<text x="22" y="29" text-anchor="middle" font-family="Cairo,sans-serif" font-size="18"'
        . ' font-weight="700" fill="currentColor">' . esc_html($letter) . '</text></svg>';
}

function sch_w($n) { return $n; }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }

const SCH_ICONS = [
    'home'       => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    'bus'        => '<path d="M4 16V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10M4 16h16M4 16v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2M17 16v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2"/><circle cx="7.5" cy="12.5" r="1"/><circle cx="16.5" cy="12.5" r="1"/>',
    'route'      => '<circle cx="6" cy="19" r="3"/><circle cx="18" cy="5" r="3"/><path d="M9 19h6a4 4 0 0 0 0-8H9a4 4 0 0 1 0-8"/>',
    'check'      => '<path d="M20 6 9 17l-5-5"/>',
    'user-check' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.6 3.1-6.5 7-6.5M16 16l2 2 4-4"/>',
    'heart'      => '<path d="M20.8 6.6a5 5 0 0 0-7.1 0L12 8.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/>',
    'pill'       => '<path d="M10.5 20.5a5 5 0 0 1-7-7l6-6a5 5 0 0 1 7 7z"/><path d="m8.5 8.5 7 7"/>',
    'award'      => '<circle cx="12" cy="8" r="5"/><path d="m8.5 12.5-1.5 8 5-3 5 3-1.5-8"/>',
    'wallet'     => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M16 12h3"/>',
    'invoice'    => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    'calendar'   => '<path d="M4 5h16v16H4zM8 3v4M16 3v4M4 10h16"/>',
    'mail'       => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
    'pen'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    'bell'       => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'card'       => '<path d="M3 7h18v12H3zM3 11h18M7 15h4"/>',
    'badge'      => '<path d="M4 5h16v14H4z"/><circle cx="9" cy="11" r="2"/><path d="M14 10h4M14 14h4"/>',
    'list'       => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    'user'       => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c0-4 3.4-7 7.5-7s7.5 3 7.5 7"/>',
    'chat'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'moon'       => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'pin'        => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    'book'       => '<path d="M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3z"/><path d="M18 7h2v13H7"/>',
    'grid'       => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
    'cog'        => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
    'lock'       => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    'shield'     => '<path d="M12 3l8 3v6c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V6z"/>',
    'plus'       => '<path d="M12 5v14M5 12h14"/>',
    'download'   => '<path d="M12 3v12M7 11l5 5 5-5M4 21h16"/>',
    'share'      => '<path d="M12 3v13M8 7l4-4 4 4M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
    'phone'      => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
];

// ── بيانات العرض ────────────────────────────────────────────────────

require __DIR__ . '/data.php';
