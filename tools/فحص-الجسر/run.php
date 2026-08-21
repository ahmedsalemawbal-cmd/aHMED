<?php
/**
 * إثبات جسر نور — الخادم والإضافة معًا.
 *
 * يُشغّل `SCH_Bridge` و`SCH_Import::run_rows` **الحقيقيّتين** فوق `$wpdb`
 * وخياراتٍ في الذاكرة، ويقرأ ملفّات الإضافة نصًّا. والمحاور الثمانية هي ما
 * يخسره النظام إن انكسر بصمت:
 *
 *   ① المفتاح مخزَّنٌ مجزّأً — تسريب القاعدة لا يُعطي مفتاحًا عاملًا
 *   ② المنتهي والمُلغى يُردّان · والمقارنة ثابتة الزمن
 *   ③ الصلاحية تُفحَص **لحظة الاستخدام** — الموقوف لا يستورد
 *   ④ `dry_run` لا يكتب صفًّا · وما فوق الحدّ يُردّ بعدده
 *   ⑤ المراحل الستّ تُقبَل — الدعوى التي تحرس عطل `normalize_stage`
 *   ⑥ الإضافة بلا `content_scripts` ولا `host_permissions` ثابتة
 *   ⑦ حقول المطابقة من `ping` لا مكتوبةً في اللوح
 *   ⑧ الحزمة تُبنى وتُفتح وفيها ملفّاتها
 */
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
const ROOT = '/home/user/aHMED/school-system/';
define('SCH_PATH', ROOT);
define('SCH_API_NS', 'school/v1');

// ── قشرة ووردبريس ──
$GLOBALS['OPTIONS']  = [];
$GLOBALS['CAPS']     = ['sch_manage_students' => true];
$GLOBALS['USERCAPS'] = [7 => true];
$GLOBALS['AUDIT']    = [];

function __($t, $d = '') { return $t; }
function esc_html($t) { return $t; }
function esc_html__($t, $d = '') { return $t; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function get_current_user_id() { return 7; }
function get_userdata($id) { return (object) ['ID' => $id, 'display_name' => 'وكيل المدرسة']; }
function current_user_can($c) { return $GLOBALS['CAPS'][$c] ?? false; }
function user_can($u, $c) { return $GLOBALS['USERCAPS'][$u] ?? false; }
function current_time($f) { return gmdate($f); }
function sch_now() { return gmdate('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) { $GLOBALS['AUDIT'][] = $a[0] ?? ''; }
function sch_settings($k = null, $d = '') { return $d; }
function get_bloginfo($k) { return 'مدرسة الفلك المنير'; }
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_generate_password($n = 12, $s = true) { return substr(str_repeat('abcdef0123456789', 8), 0, $n); }
function get_option($k, $d = false) { return $GLOBALS['OPTIONS'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['OPTIONS'][$k] = $v; return true; }
function add_action(...$a) {}
function register_rest_route(...$a) {}
function status_header($c) {}

function sch_normalize_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') { return ''; }
    if (str_starts_with($d, '966')) { $d = '0' . substr($d, 3); }
    if (strlen($d) === 9 && str_starts_with($d, '5')) { $d = '0' . $d; }
    return preg_match('/^05\d{8}$/', $d) === 1 ? $d : '';
}

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

class WP_REST_Request
{
    public function __construct(private array $h = [], private array $p = []) {}
    public function get_header($k) { return $this->h[$k] ?? null; }
    public function get_param($k) { return $this->p[$k] ?? null; }
}

class WP_REST_Response
{
    public function __construct(public $data = null, public int $status = 200) {}
    public function get_data() { return $this->data; }
}

final class SCH_Classes
{
    public const STAGES = [
        'nursery' => 'الحضانة', 'kg' => 'الروضة', 'prep' => 'التمهيدي',
        'primary' => 'ابتدائي', 'intermediate' => 'متوسط', 'secondary' => 'ثانوي',
    ];
}

/** `$wpdb` يكفي ما يقرؤه `validate()`: هل الهوية مسجّلة؟ */
final class FakeWpdb
{
    public string $prefix = 'wp_';
    public array $seen = [];
    public array $taken = [];

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[dfs]/', function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? null;
            return $m[0] === '%s' ? "'" . (string) $v . "'" : (string) (int) $v;
        }, (string) $q);
    }

    public function get_var($q, ...$x)
    {
        $this->seen[] = (string) $q;
        if (preg_match("/national_id = '([^']*)'/", (string) $q, $m) === 1) {
            return in_array($m[1], $this->taken, true) ? 1 : null;
        }
        return null;
    }

    public function get_results($q, ...$x) { $this->seen[] = (string) $q; return []; }
    public function get_row($q, ...$x) { $this->seen[] = (string) $q; return null; }
    public function query($q) { $this->seen[] = (string) $q; return 0; }
    public function insert($t, $d) { $this->seen[] = 'INSERT ' . $t; return 1; }
    public function update($t, $d, $w) { $this->seen[] = 'UPDATE ' . $t; return 1; }
}

$wpdb = new FakeWpdb();
$GLOBALS['wpdb'] = $wpdb;

require_once ROOT . 'modules/import/class-import.php';
require_once ROOT . 'api/class-bridge.php';

// ── الدعاوى ──
$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $got = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$what}\n"; return; }
    $fail++;
    echo "  ✗ {$what}" . ($got !== '' ? " — {$got}" : '') . "\n";
}

echo "\n① المفتاح مخزَّنٌ مجزّأً\n";

$issued = SCH_Bridge::issue_key('جهاز الوكيل');
ok('الإصدار ينجح ويُعيد المفتاح مرّةً واحدة', is_array($issued) && !empty($issued['key']), json_encode($issued));

$plain = $issued['key'];
$store = json_encode($GLOBALS['OPTIONS']['sch_bridge_keys']);

ok('**المفتاح الصريح ليس في القاعدة**', !str_contains($store, $plain));
ok('والمخزَّن بصمةُ sha256', array_key_exists(hash('sha256', $plain), $GLOBALS['OPTIONS']['sch_bridge_keys']));
ok('ومعه مدّةٌ تنتهي', !empty($issued['expires']));

echo "\n② الردّ على المفتاح غير الصالح\n";

$req = static fn (string $k): WP_REST_Request => new WP_REST_Request(['X-SCH-Key' => $k]);

$good = SCH_Bridge::ping($req($plain));
ok('المفتاح الصحيح يمرّ ويُعيد اسم المدرسة', $good->get_data()['ok'] === true, json_encode($good->get_data()));
ok('ويُعيد حقول المطابقة للإضافة', isset($good->get_data()['fields']['full_name']));

ok('مفتاحٌ ملفَّق يُردّ', SCH_Bridge::ping($req('sch_wrong'))->get_data()['ok'] === false);
ok('وطلبٌ بلا مفتاح يُردّ', SCH_Bridge::ping(new WP_REST_Request())->get_data()['ok'] === false);

// المنتهي
$keys = $GLOBALS['OPTIONS']['sch_bridge_keys'];
$hash = hash('sha256', $plain);
$keys[$hash]['expires'] = '2000-01-01 00:00:00';
$GLOBALS['OPTIONS']['sch_bridge_keys'] = $keys;

ok('**والمنتهي يُردّ ولو كان صحيحًا**', SCH_Bridge::ping($req($plain))->get_data()['ok'] === false);

$keys[$hash]['expires'] = gmdate('Y-m-d H:i:s', time() + 86400);
$GLOBALS['OPTIONS']['sch_bridge_keys'] = $keys;
ok('ويعود بالمدّة الصالحة', SCH_Bridge::ping($req($plain))->get_data()['ok'] === true);

// المُلغى
SCH_Bridge::revoke_key($hash);
ok('**والإلغاء يُوقف الجهاز فورًا**', SCH_Bridge::ping($req($plain))->get_data()['ok'] === false);
ok('ولا يبقى له أثرٌ في القائمة', SCH_Bridge::list_keys() === []);

$src = file_get_contents(ROOT . 'api/class-bridge.php');
ok('والمقارنة بـ`hash_equals` لا بـ`===`', str_contains($src, 'hash_equals('));

echo "\n③ الصلاحية تُفحَص لحظة الاستخدام\n";

$issued = SCH_Bridge::issue_key('جهاز ثانٍ');
$plain  = $issued['key'];

$GLOBALS['USERCAPS'][7] = false;   // الموظّف أُوقف بعد الإصدار
$res = SCH_Bridge::ping($req($plain));

ok('**الموقوف لا يستورد ولو كان مفتاحه ساريًا**', $res->get_data()['ok'] === false, json_encode($res->get_data()));
$GLOBALS['USERCAPS'][7] = true;

$GLOBALS['CAPS']['sch_manage_students'] = false;
ok('ومن لا يملك الصلاحية لا يُصدر مفتاحًا', is_wp_error(SCH_Bridge::issue_key('x')));
ok('ولا يُلغي مفتاحًا', SCH_Bridge::revoke_key(hash('sha256', $plain)) === false);
$GLOBALS['CAPS']['sch_manage_students'] = true;

echo "\n④ الفحص الجافّ لا يكتب\n";

$rows = [
    ['full_name' => 'أحمد سالم', 'national_id' => '1012345678', 'stage' => 'ابتدائي',
     'grade_level' => 'الرابع', 'section' => 'أ', 'guardian_name' => 'سالم', 'guardian_phone' => '0512345678', 'relation' => 'الأب'],
];

$wpdb->seen = [];
$dry = SCH_Import::run_rows($rows, true);

ok('الصفّ السليم يمرّ', $dry['ok'] === true, json_encode($dry['errors']));
ok('**ولا صفَّ يُكتب في الفحص الجافّ**', $dry['imported'] === 0
    && !array_filter($wpdb->seen, static fn (string $q): bool => str_starts_with($q, 'INSERT')));

$bad = SCH_Import::run_rows([['full_name' => '', 'national_id' => '']], true);
ok('وصفٌّ بلا اسمٍ ولا هوية لا يُعَدّ صفًّا', $bad['ok'] === false && str_contains(implode('', $bad['errors']), 'لا صفوف'));

$big = array_fill(0, 501, $rows[0]);
$over = SCH_Import::run_rows($big, true);
ok('**وما فوق الحدّ يُردّ ويُقال حدُّه**', $over['ok'] === false && str_contains(implode('', $over['errors']), '500'),
    implode('', $over['errors']));

$wpdb->taken = ['1012345678'];
$dup = SCH_Import::run_rows($rows, true);
ok('والهوية المسجَّلة مسبقًا تُكشَف', $dup['ok'] === false && str_contains(implode('', $dup['errors']), 'مسبقًا'));
$wpdb->taken = [];

$two = SCH_Import::run_rows([$rows[0], $rows[0]], true);
ok('والمكرّرة داخل الدفعة كذلك', $two['ok'] === false && str_contains(implode('', $two['errors']), 'مكرر'));

echo "\n⑤ المراحل الستّ\n";

foreach (['الحضانة' => 'nursery', 'الروضة' => 'kg', 'التمهيدي' => 'prep',
          'ابتدائي' => 'primary', 'متوسط' => 'intermediate', 'ثانوي' => 'secondary'] as $ar => $key) {
    $one = $rows[0];
    $one['stage'] = $ar;
    $one['national_id'] = '';
    $r = SCH_Import::run_rows([$one], true);
    ok("«{$ar}» تُقبَل", $r['ok'] === true, implode(' · ', $r['errors']));
}

$one = $rows[0];
$one['stage'] = 'جامعي';
$one['national_id'] = '';
$r = SCH_Import::run_rows([$one], true);
ok('ومرحلةٌ لا وجود لها تُردّ', $r['ok'] === false);
ok('**والرسالة تعدّ الستّ لا الأربع**', str_contains(implode('', $r['errors']), 'الحضانة')
    && str_contains(implode('', $r['errors']), 'التمهيدي'), implode('', $r['errors']));

echo "\n⑥ و⑦ الإضافة: أقلّ صلاحية وحقولٌ من الخادم\n";

$mf = json_decode((string) file_get_contents(ROOT . 'bridge/manifest.json'), true);

ok('البيان يُحلَّل', is_array($mf));
ok('MV3', ($mf['manifest_version'] ?? 0) === 3);
ok('**لا `content_scripts` — فلا سطر يعمل في أيّ صفحة بلا ضغطة**', !isset($mf['content_scripts']));
ok('**ولا `host_permissions` ثابتة**', !isset($mf['host_permissions']));
ok('والصلاحية للنطاق تُطلَب عند الحاجة', isset($mf['optional_host_permissions']));
ok('والصلاحيات ثلاثٌ لا أكثر', ($mf['permissions'] ?? []) === ['activeTab', 'scripting', 'storage'],
    json_encode($mf['permissions'] ?? []));

$popup = (string) file_get_contents(ROOT . 'bridge/popup.js');
ok('اللوح يطلب الإذن للنطاق المكتوب وحده', str_contains($popup, 'chrome.permissions.request'));
/* الحقول تُبنى من ردّ `ping` — والدليل أن أسماء الحقول الستّة الأخرى لا
   تُذكر في اللوح إطلاقًا. و`full_name`/`national_id` وحدهما مذكوران لأن
   قاعدة «صفٌّ بلا اسمٍ ولا هوية ليس صفًّا» مرآةٌ لما في الخادم. */
$hard = array_values(array_filter(
    ['stage', 'grade_level', 'section', 'guardian_name', 'guardian_phone', 'relation'],
    static fn (string $f): bool => str_contains($popup, $f)
));

ok('**والحقول من `ping` لا مكتوبةً في اللوح**',
    str_contains($popup, 'ping.fields') && str_contains($popup, 'Object.keys(state.fields)') && $hard === [],
    implode(' · ', $hard));
ok('ولا استيراد قبل فحصٍ جافّ', str_contains($popup, "dry_run") && str_contains($popup, "el('commit').hidden = false"));
ok('والحدّ يُقال بعدده لا يُلتَفّ عليه', str_contains($popup, 'LIMIT') && str_contains($popup, 'صفِّ الصفحة في نور'));

$reader = (string) file_get_contents(ROOT . 'bridge/reader.js');
ok('والقارئ عامّ — يجد الجداول ولا يعرف نور',
    str_contains($reader, "querySelectorAll('table')") && !str_contains($reader, 'noor'));

echo "\n⑧ الحزمة\n";

$need = ['manifest.json', 'popup.html', 'popup.css', 'popup.js', 'reader.js',
         'icons/16.png', 'icons/48.png', 'icons/128.png'];

$missing = array_values(array_filter($need, static fn (string $f): bool => !is_readable(ROOT . 'bridge/' . $f)));
ok('الملفّات الثمانية موجودة', $missing === [], implode(' · ', $missing));

$tmp = sys_get_temp_dir() . '/sch-bridge-test.zip';
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

foreach ($need as $f) {
    $zip->addFile(ROOT . 'bridge/' . $f, 'osool-noor-bridge/' . $f);
}

$zip->close();

$back = new ZipArchive();
$open = $back->open($tmp) === true;
$count = $open ? $back->numFiles : 0;
$back->close();
@unlink($tmp);

ok('وتُحزَم وتُفتح ثانيةً بكاملها', $open && $count === count($need), (string) $count);

$dash = (string) file_get_contents(ROOT . 'frontend/class-dashboard.php');
ok('والشاشة تُقدّمها للتنزيل', str_contains($dash, 'sch_bridge_zip'));
ok('والتنزيل يُفحَص بالصلاحية ويُسجَّل', str_contains($src, "current_user_can('sch_manage_students')")
    && str_contains($src, 'bridge.downloaded'));

echo "\n═══════════════════════════\n";
printf("  ناجح %d · فاشل %d\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
