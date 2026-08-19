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
function sanitize_key($k) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $k)); }
function wp_timezone() { return new DateTimeZone('Asia/Riyadh'); }
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
    // ووردبريس العربي يترجم am/pm إلى ص/م عبر `get_meridiem` — فلا تظهر لاتينية
    $out = strtr($out, ['am' => 'ص', 'pm' => 'م', 'AM' => 'ص', 'PM' => 'م']);
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

function sch_w($n) { return $n; }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }


/* الأيقونات من ملف الإضافة نفسه لا من نسخة هنا: نسختان تتباعدان،
   فتُظهر المعاينة أيقونة والتطبيق أخرى — وهو أسوأ من ألّا تكون معاينة. */
require SCH_PATH . 'includes/icons.php';

// ── بيانات العرض ────────────────────────────────────────────────────

require __DIR__ . '/data.php';
