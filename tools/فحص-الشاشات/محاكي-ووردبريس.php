<?php
/**
 * محاكي ووردبريس لفحص الشاشات — **بأصناف الإضافة الحقيقية**.
 *
 * الفرق عن `tools/معاينة-الداشبورد/`: تلك أداة **مقارنة بصرية** تُزيّف أصناف
 * الوحدات ببيانات تشبه التصميم، فتغطّي أربع عشرة شاشة فقط. وهذه أداة **فحص
 * دخان**: تُحمّل `class-loader.php` كما يُحمّله ووردبريس، فتعمل الأصناف
 * الحقيقية بمنطقها الحقيقي، ولا يُزيَّف إلا ووردبريس نفسه و`$wpdb`.
 *
 * وقاعدة البيانات تُرجع **فراغًا لكل شيء** عمدًا — وهذا هو السيناريو الذي
 * يراه كل عميل جديد في يومه الأول: مدرسةٌ بلا طالب ولا شعبة ولا حصة. أي
 * شاشة تسقط هنا تسقط عنده. وهو أرخص فحصٍ ممكن لأغلى لحظة في عمر المنتج.
 *
 * التشغيل: php tools/فحص-الشاشات/run.php
 */

declare(strict_types=1);

define('ABSPATH', '/tmp/wp/');
define('SCH_PATH', '/home/user/aHMED/school-system/');
define('SCH_URL', '/wp-content/plugins/school-system/');
define('SCH_VERSION', '10.8.0');
define('SCH_API_NS', 'school/v1');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MONTH_IN_SECONDS', 2592000);
define('YEAR_IN_SECONDS', 31536000);
define('ARRAY_A', 'ARRAY_A');
define('OBJECT', 'OBJECT');

// ── جامع الأعطال ────────────────────────────────────────────────────
// التحذير عطلٌ صغير لا ضجيج: «خاصّية غير معرّفة» تعني حقلًا يقرؤه القالب
// ولا تُنتجه الطبقة، وهو بالضبط ما نبحث عنه.
$GLOBALS['SCH_NOTES'] = [];
set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0): bool {
    $GLOBALS['SCH_NOTES'][] = [
        'level' => match (true) {
            (bool) ($no & (E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) => 'warning',
            (bool) ($no & (E_DEPRECATED | E_USER_DEPRECATED))                                => 'deprecated',
            default                                                                          => 'notice',
        },
        'msg'  => $msg,
        'file' => str_replace(SCH_PATH, '', $file),
        'line' => $line,
    ];
    return true;
});
error_reporting(E_ALL);

// ── نواة ووردبريس ───────────────────────────────────────────────────

function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
function _n($one, $many, $n, $d = '') { return $n == 1 ? $one : $many; }
function _x($t, $c, $d = '') { return $t; }
function _nx($one, $many, $n, $c, $d = '') { return $n == 1 ? $one : $many; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_html__($t, $d = '') { return esc_html($t); }
function esc_html_e($t, $d = '') { echo esc_html($t); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr__($t, $d = '') { return esc_attr($t); }
function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function esc_url($u) { return htmlspecialchars((string) $u, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($u) { return (string) $u; }
function esc_textarea($t) { return esc_html($t); }
function esc_js($t) { return addslashes((string) $t); }

function absint($n) { return abs((int) $n); }
function sanitize_key($k) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $k)); }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function sanitize_textarea_field($t) { return trim(strip_tags((string) $t)); }
function sanitize_email($t) { return filter_var((string) $t, FILTER_SANITIZE_EMAIL); }
function sanitize_user($t, $strict = false) { return preg_replace('/[^A-Za-z0-9_.\-@ ]/', '', (string) $t); }
function sanitize_file_name($t) { return preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $t); }
function is_email($t) { return (bool) filter_var((string) $t, FILTER_VALIDATE_EMAIL); }
function trailingslashit($s) { return rtrim((string) $s, '/\\') . '/'; }
function wp_parse_url($u, $c = -1) { return parse_url((string) $u, $c); }
function wp_json_encode($d, $f = 0, $depth = 512) { return json_encode($d, $f | JSON_UNESCAPED_UNICODE, $depth); }
function maybe_unserialize($v) { return is_string($v) && str_starts_with($v, 'a:') ? @unserialize($v) : $v; }
function size_format($b, $d = 0) { return number_format((float) $b / 1024, $d) . ' KB'; }
function human_time_diff($from, $to = 0) { return floor(abs(($to ?: time()) - $from) / 60) . ' دقيقة'; }

function wp_timezone() { return new DateTimeZone('Asia/Riyadh'); }
function wp_timezone_string() { return 'Asia/Riyadh'; }
function get_locale() { return 'ar'; }
function is_admin() { return false; }
function is_ssl() { return true; }
function wp_is_mobile() { return false; }
function wp_doing_ajax() { return false; }
function home_url($p = '') { return 'https://example.test' . $p; }
function site_url($p = '') { return home_url($p); }
function admin_url($p = '') { return home_url('/wp-admin/' . $p); }
function bloginfo($k = '') { echo get_bloginfo($k); }
function get_bloginfo($k = '') { return $k === 'charset' ? 'UTF-8' : 'مدرسة الاختبار'; }

function number_format_i18n($n, $d = 0) {
    return apply_filters('number_format_i18n', number_format((float) $n, $d, '٫', '٬'));
}
function wp_date($f, $ts = null, $tz = null) {
    $ar_m = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $ar_d = ['Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء',
             'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
    $ts   = $ts ?? time();
    $out  = date((string) $f, (int) $ts);
    $out  = strtr($out, $ar_d);
    $out  = strtr($out, [date('M', (int) $ts) => $ar_m[(int) date('n', (int) $ts)], date('F', (int) $ts) => $ar_m[(int) date('n', (int) $ts)]]);
    return apply_filters('wp_date', strtr($out, ['am' => 'ص', 'pm' => 'م', 'AM' => 'ص', 'PM' => 'م']), $f, $ts, $tz);
}
function date_i18n($f, $ts = null, $gmt = false) { return wp_date($f, $ts); }
function current_time($type = 'mysql', $gmt = 0) {
    return $type === 'timestamp' ? time() : date('Y-m-d H:i:s');
}

// ── الخطّافات ───────────────────────────────────────────────────────
$GLOBALS['SCH_FILTERS'] = [];
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['SCH_FILTERS'][$h][] = $cb; return true; }
function add_action($h, $cb, $p = 10, $a = 1) { return add_filter($h, $cb, $p, $a); }
function remove_filter($h, $cb, $p = 10) { return true; }
function do_action($h, ...$a) {}
function apply_filters($h, $v, ...$a) {
    foreach ($GLOBALS['SCH_FILTERS'][$h] ?? [] as $cb) { $v = $cb($v, ...$a); }
    return $v;
}
function add_rewrite_rule(...$a) {}
function add_rewrite_tag(...$a) {}
function flush_rewrite_rules($hard = true) {}
function get_query_var($v, $d = '') { return $d; }
function add_query_arg(...$args) {
    if (is_array($args[0])) {
        [$params, $url] = [$args[0], $args[1] ?? '/'];
    } else {
        [$params, $url] = [[$args[0] => $args[1]], $args[2] ?? '/'];
    }
    $parts = explode('?', (string) $url, 2);
    parse_str($parts[1] ?? '', $q);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || $v === false) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    return $parts[0] . ($q ? '?' . http_build_query($q) : '');
}
function remove_query_arg($k, $url = '') { return add_query_arg([(string) $k => null], $url); }

// ── الخيارات والعابر ────────────────────────────────────────────────
$GLOBALS['SCH_OPTIONS'] = [];
function get_option($k, $d = false) { return $GLOBALS['SCH_OPTIONS'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['SCH_OPTIONS'][$k] = $v; return true; }
function add_option($k, $v, $x = '', $a = 'yes') { return update_option($k, $v); }
function delete_option($k) { unset($GLOBALS['SCH_OPTIONS'][$k]); return true; }
function get_transient($k) { return false; }
function set_transient($k, $v, $t = 0) { return true; }
function delete_transient($k) { return true; }
function wp_cache_flush() {}

// ── المستخدمون والصلاحيات ───────────────────────────────────────────
/*
 * الافتراض مستخدمٌ كامل الصلاحية: الغرض تصيير كل شاشة لا اختبار الصلاحيات.
 * ويستطيع اختبارٌ أن يضع مستخدمًا بدورٍ وصلاحياتٍ محدّدة في
 * `$GLOBALS['SCH_TEST_USERS'][$id] = ['roles' => [...], 'caps' => [...]]`
 * فيصير المحاكي أداةَ فحصٍ للتوجيه والصلاحيات كذلك.
 */
$GLOBALS['SCH_TEST_USERS']  = [];
$GLOBALS['SCH_CURRENT_USER'] = 1;

function sch_test_user(int $id): ?array { return $GLOBALS['SCH_TEST_USERS'][$id] ?? null; }

function current_user_can($cap, ...$a) { return user_can(get_current_user_id(), $cap); }
function user_can($user, $cap, ...$a) {
    $id = is_object($user) ? (int) $user->ID : (int) $user;
    $u  = sch_test_user($id);
    return $u === null ? true : in_array($cap, (array) ($u['caps'] ?? []), true);
}
function get_current_user_id() { return (int) $GLOBALS['SCH_CURRENT_USER']; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function wp_get_current_user() { return get_userdata(get_current_user_id()); }
function get_userdata($id) {
    if ((int) $id <= 0) { return false; }
    $t = sch_test_user((int) $id);
    $u = new stdClass();
    $u->ID = (int) $id;
    $u->display_name = 'مستخدم ' . (int) $id;
    $u->user_login = 'user' . (int) $id;
    $u->user_email = 'u' . (int) $id . '@example.test';
    $u->user_registered = date('Y-m-d H:i:s');
    $u->roles = (array) ($t['roles'] ?? ['sch_manager']);
    $u->first_name = 'مستخدم';
    $u->last_name = (string) (int) $id;
    $u->caps = [];
    $u->allcaps = array_fill_keys((array) ($t['caps'] ?? []), true);
    return $u;
}
function get_user_by($f, $v) { return $f === 'id' ? get_userdata((int) $v) : false; }
function get_users($args = []) { return []; }
function get_role($r) { return null; }
function add_role($s, $l, $c = []) { return null; }
function wp_insert_user($d) { return 2; }
function wp_update_user($d) { return 2; }
function wp_delete_user($id, $r = null) { return true; }
function wp_set_password($p, $id) {}
function wp_check_password($p, $h, $id = '') { return false; }
function wp_generate_password($len = 12, $s = true, $x = false) { return str_repeat('a', (int) $len); }
function wp_authenticate($u, $p) { return new WP_Error('bad', 'x'); }
function wp_signon($c = [], $s = '') { return new WP_Error('bad', 'x'); }
function wp_set_auth_cookie($id, $r = false, $s = '') {}
function wp_set_current_user($id, $n = '') { return get_userdata($id); }
function wp_logout() {}
function wp_salt($s = 'auth') { return 'salt-for-tests-only'; }
function wp_create_nonce($a = -1) { return 'nonce'; }
function wp_verify_nonce($n, $a = -1) { return 1; }
function wp_nonce_field($a = -1, $n = '_wpnonce', $r = true, $e = true) {
    $out = '<input type="hidden" name="' . esc_attr($n) . '" value="nonce">';
    if ($e) { echo $out; }
    return $out;
}
function wp_get_referer() { return home_url('/dashboard/'); }
function wp_safe_redirect($l, $s = 302) { return true; }
function status_header($c) {}
function selected($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' selected' : ''; if ($e) { echo $r; } return $r; }
function checked($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' checked' : ''; if ($e) { echo $r; } return $r; }
function disabled($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' disabled' : ''; if ($e) { echo $r; } return $r; }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'baseurl' => '/uploads', 'error' => false]; }
function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); }
function wp_check_filetype_and_ext($f, $n, $m = null) { return ['ext' => 'png', 'type' => 'image/png', 'proper_filename' => false]; }
function wp_mail($to, $s, $m, $h = '', $a = []) { return true; }
function wp_remote_post($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; }
function wp_remote_retrieve_response_code($r) { return 200; }
function wp_next_scheduled($h, $a = []) { return false; }
function wp_schedule_event($t, $r, $h, $a = []) { return true; }
function wp_clear_scheduled_hook($h, $a = []) { return 0; }
function get_header(...$a) {}
function get_footer(...$a) {}
function rest_url($p = '') { return home_url('/wp-json/' . ltrim((string) $p, '/')); }
function register_rest_route(...$a) { return true; }
function rest_ensure_response($d) { return $d; }
function plugins_url($p = '', $f = '') { return SCH_URL . ltrim((string) $p, '/'); }
function plugin_dir_url($f) { return SCH_URL; }
function plugin_dir_path($f) { return SCH_PATH; }

final class WP_Error
{
    private array $errors = [];
    public function __construct($code = '', $message = '', $data = '')
    {
        if ($code !== '') { $this->errors[$code] = [$message]; }
    }
    public function get_error_code() { return array_key_first($this->errors) ?? ''; }
    public function get_error_message($c = '') { $c = $c ?: $this->get_error_code(); return $this->errors[$c][0] ?? ''; }
    public function get_error_messages($c = '') { return $this->errors[$c ?: $this->get_error_code()] ?? []; }
    public function get_error_data($c = '') { return null; }
    public function has_errors() { return $this->errors !== []; }
    public function add($c, $m, $d = '') { $this->errors[$c][] = $m; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }

/**
 * قاعدةٌ فارغة تُجيب عن كل شيء بلا شيء.
 *
 * `prepare` تُنفَّذ حقيقةً (استبدالٌ فعليّ) — فلو أخطأ استعلامٌ في عدد
 * الوسائط ظهر هنا كما يظهر في الإنتاج.
 */
final class SCH_EmptyWpdb
{
    public string $prefix     = 'wp_';
    public string $users      = 'wp_users';
    public string $usermeta   = 'wp_usermeta';
    public string $posts      = 'wp_posts';
    public string $last_error = '';
    public int $insert_id     = 1;
    public int $rows_affected = 0;
    public array $seen        = [];

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i   = 0;
        $out = preg_replace_callback('/%[dfs]/', static function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? null;
            return match ($m[0]) {
                '%d'    => (string) (int) $v,
                '%f'    => (string) (float) $v,
                default => $v === null ? 'NULL' : "'" . str_replace("'", "''", (string) $v) . "'",
            };
        }, (string) $q);
        return $out;
    }

    public function get_results($q = null, $o = OBJECT) { $this->note($q); return []; }
    public function get_row($q = null, $o = OBJECT, $y = 0) { $this->note($q); return null; }
    public function get_col($q = null, $x = 0) { $this->note($q); return []; }
    public function get_var($q = null, $x = 0, $y = 0) { $this->note($q); return null; }
    public function query($q) { $this->note($q); return 0; }
    public function insert($t, $d, $f = null) { $this->insert_id++; return 1; }
    public function update($t, $d, $w, $f = null, $wf = null) { return 1; }
    public function delete($t, $w, $f = null) { return 1; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function esc_like($t) { return addcslashes((string) $t, '_%\\'); }
    public function _escape($d) { return $d; }
    public function suppress_errors($s = true) { return false; }
    public function flush() {}

    private function note($q): void
    {
        if (is_string($q)) { $this->seen[] = $q; }
    }
}

/*
 * وضعان: `EMPTY=1` قاعدةٌ فارغة (يوم التثبيت الأول)، والافتراض قاعدةٌ
 * مصنوعة من المخطّط (المدرسة العاملة). كلاهما يكشف عطلًا لا يكشفه الآخر:
 * الأول يكشف السقوط على `null`، والثاني يكشف الحقل الذي يقرؤه القالب ولا
 * يجلبه استعلامه.
 */
if (getenv('EMPTY') === '1') {
    $GLOBALS['wpdb'] = new SCH_EmptyWpdb();
} else {
    require_once __DIR__ . '/قاعدة-مصنوعة.php';
    $GLOBALS['wpdb'] = new SCH_SchemaWpdb();
}

// ── الإضافة نفسها — الأصناف الحقيقية لا نسخةً منها ──────────────────
require_once SCH_PATH . 'includes/functions.php';
require_once SCH_PATH . 'includes/icons.php';
require_once SCH_PATH . 'includes/class-qr.php';
// المُنشِئ يُحمَّل في `school-system.php` لا في المُحمِّل — وشاشة الإعدادات
// تسأله عن الجداول الناقصة، فغيابه هنا كان يُسقطها بلا سبب حقيقي.
require_once SCH_PATH . 'includes/class-activator.php';
require_once SCH_PATH . 'includes/class-loader.php';
SCH_Loader::load_modules();

sch_localize_output();
