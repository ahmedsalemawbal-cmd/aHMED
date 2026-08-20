<?php
/**
 * مُحاكي ووردبريس والإضافة — لتصيير شاشات الداشبورد الحقيقية بلا خادم.
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
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('SCH_API_NS', 'school/v1');

// ── دوال ووردبريس ───────────────────────────────────────────────────

function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
/* ووردبريس يُرجع النصّ بـ%s كما هو ليملأه sprintf بقيمة مُوطَّنة.
   وضعُ الرقم الخام هنا كان يبتلع %s فيخرج رقمٌ لاتيني في شاشة عربية. */
function _n($one, $many, $n, $d = '') { return $n == 1 ? $one : $many; }
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
/* الفاصلة والعلامة العشرية من ترجمة ووردبريس العربية — والأرقام تبقى
   لاتينية حتى تمرّ بمرشِّح الإضافة، تمامًا كما في الإنتاج. */
function number_format_i18n($n, $d = 0) {
    return apply_filters('number_format_i18n', number_format((float) $n, $d, '٫', '٬'));
}
function wp_date($f, $ts = null, $tz = null) {
    $ar_m = [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $ar_d = ['Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    $ts  = $ts ?? time();
    $out = date($f, $ts);
    $out = strtr($out, $ar_d);
    $out = strtr($out, [date('M', $ts) => $ar_m[(int) date('n', $ts)], date('F', $ts) => $ar_m[(int) date('n', $ts)]]);
    $out = strtr($out, ['am' => 'ص', 'pm' => 'م', 'AM' => 'ص', 'PM' => 'م']);
    return apply_filters('wp_date', $out, $f, $ts, $tz);
}

/* نسخةٌ حرفية من `includes/functions.php` */
function sch_ar_digits(string $text): string {
    return strtr($text, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
}
function sch_localize_output(): void {
    add_filter('number_format_i18n', static function ($formatted): string { return sch_ar_digits((string) $formatted); });
    add_filter('wp_date', static function ($date, $format = '') : string {
        $date = (string) $date;
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $date) === 1) { return $date; }
        return sch_ar_digits($date);
    }, 10, 2);
}
function current_time($f, $gmt = 0) { return $f === 'timestamp' ? time() : date($f); }
function get_locale() { return 'ar'; }
function get_bloginfo($k = '') { return 'مدرسة الملك المنير الأهلية'; }
function get_current_user_id() { return 7; }
function get_user_by($f, $v) { return (object) ['ID' => (int) $v, 'display_name' => 'عبدالله الشمري', 'user_email' => 'driver@example.com']; }
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
$GLOBALS['sch_filters'] = [];
function add_filter($t, $cb, $p = 10, $n = 1) { $GLOBALS['sch_filters'][$t][] = $cb; }
function apply_filters($t, $v, ...$a) {
    foreach ($GLOBALS['sch_filters'][$t] ?? [] as $cb) { $v = $cb($v, ...$a); }
    return $v;
}
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
function human_time_diff($from, $to = 0) {
    $to = $to ?: time(); $d = abs($to - $from);
    if ($d < 3600)  { return sch_ar_digits((string) max(1, (int) round($d / 60))) . ' دقيقة'; }
    if ($d < 86400) { $h = (int) round($d / 3600);
                      return $h === 1 ? 'ساعة' : ($h === 2 ? 'ساعتان' : sch_ar_digits((string) $h) . ' ساعات'); }
    $dd = (int) round($d / 86400);
    return $dd === 1 ? 'يوم' : ($dd === 2 ? 'يومان' : sch_ar_digits((string) $dd) . ' أيام');
}
function sch_now() { return date('Y-m-d H:i:s'); }

/* نسخةٌ حرفية من `includes/functions.php` — نسختان تتباعدان فتُظهر
   المعاينة شريحةً والداشبورد أخرى. */
function sch_wave_pill(string $status, string $label = '', int $count = -1, bool $live = false): string
{
    $defaults = [
        'present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب',
        'excused' => 'غياب بعذر', 'leave' => 'في إجازة', 'none' => 'لم يُرصد',
    ];
    $status = array_key_exists($status, $defaults) ? $status : 'none';
    $text   = $label !== '' ? $label : $defaults[$status];

    $out  = '<span class="sch-wpill sch-wpill--' . $status . ($live ? ' is-live' : '') . '">';
    $out .= '<span class="sch-wpill__dot" aria-hidden="true"></span>';
    $out .= '<span class="sch-wpill__t">' . esc_html($text) . '</span>';
    if ($count >= 0) {
        $out .= '<b class="sch-wpill__n">' . esc_html(number_format_i18n($count)) . '</b>';
    }
    return $out . '</span>';
}

/* نسخةٌ حرفية من `includes/functions.php` — الوقت النصّي يُوطَّن كالأرقام */
function sch_clock($hm) {
    $hm = trim((string) $hm);
    if ($hm === '' || !preg_match('/^(\d{1,2}):(\d{2})/', $hm, $m)) { return ''; }
    return wp_date('H:i', mktime((int) $m[1], (int) $m[2], 0, 1, 1, 2000));
}
function sch_table($t) { return 'wp_sch_' . $t; }


/* الأيقونات من ملف الإضافة نفسه لا من نسخة هنا: نسختان تتباعدان،
   فتُظهر المعاينة أيقونة والتطبيق أخرى — وهو أسوأ من ألّا تكون معاينة. */
require SCH_PATH . 'includes/icons.php';

// ── بيانات العرض ────────────────────────────────────────────────────

require __DIR__ . '/data.php';
