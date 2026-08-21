<?php
/**
 * دعاوى الشهر والاعتماد — بلا خادم وبلا قاعدة.
 *
 * تُشغّل `SCH_TT::months()` و`year_span()` و`reopen()` **الحقيقيّة** فوق
 * `$wpdb` مزيّف، وتُثبت ثلاثة أشياء لا يكشفها فحص الشاشات:
 *
 * ① **القائمة اثنا عشر شهرًا لا خمسة** — كانت تقف عند تاريخ نهاية السنة،
 *    فمدرسةٌ سجّلت سنتها «سبتمبر ← يناير» لا سبيل لها إلى مارس إطلاقًا.
 *
 * ② **اسم الشهر لا ينزلق** — `wp_date($f, strtotime($ym))` تُضيف إزاحة
 *    الموقع فوق سلسلةٍ محلّيّة أصلًا، فمنتصفُ ليل الأوّل يقع في الشهر
 *    السابق في كل موقعٍ إزاحتُه سالبة. والفحص هنا يُشغّل موقعًا بإزاحة −٥.
 *
 * ③ **فتح المعتمد للتعديل لا يمسّ المنشور** — الخطة تعود مسوّدةً، وصفوف
 *    `timetable` وراية السنة تبقيان كما هما: المعلم ووليّ الأمر يريان آخر
 *    ما اعتُمد حتى يُعتمَد التالي.
 */
declare(strict_types=1);

// ── قشرة ووردبريس: موقعٌ إزاحته سالبة عمدًا ──
define('ABSPATH', __DIR__);
const TZ_OFFSET = -5 * 3600;

function __($t, $d = '') { return $t; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $t)); }
function get_current_user_id() { return 7; }
function get_userdata($id) { return (object) ['ID' => $id]; }
function current_time($f) { return gmdate($f, time() + TZ_OFFSET); }
function sch_now() { return current_time('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) { FakeWpdb::$audit[] = $a[0] ?? ''; }
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }

/** كما في ووردبريس: الطابع يُنسَّق بتوقيت الموقع. */
function wp_date($f, $ts = null) { return gmdate($f, ($ts ?? time()) + TZ_OFFSET); }

/** وكما فيه: السلسلة تُقرأ **بتوقيت الموقع** ثم تُنسَّق به — فلا إزاحة مضاعفة. */
function mysql2date($f, $d, $translate = true)
{
    return wp_date($f, (int) strtotime($d . ' UTC') - TZ_OFFSET);
}

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

final class SCH_Years
{
    /** سنةٌ ضيّقة عمدًا: خمسة أشهر — وهي حال المدرسة التي كشفت العطل. */
    public static function current(): ?object
    {
        return (object) ['id' => 1, 'start_date' => '2026-09-01', 'end_date' => '2027-01-31'];
    }

    public static function current_id(): int { return 1; }
}

final class SCH_Classes
{
    public const STAGES = [
        'nursery' => 'الحضانة', 'kg' => 'الروضة', 'prep' => 'التمهيدي',
        'primary' => 'ابتدائي', 'intermediate' => 'متوسط', 'secondary' => 'ثانوي',
    ];
    public static function get(int $id): ?object { return null; }
    public static function list(): array { return []; }
}

final class SCH_Subjects { public static function get(int $id): ?object { return null; } }
final class SCH_Org { public static function may_touch_class(int $id): bool { return true; } }

final class FakeWpdb
{
    public string $users = 'wp_users';
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $seen = [];
    public static array $audit = [];

    /** حالة الخطة التي يُسأل عنها، وعدد الصفوف التي يُصيبها التحديث. */
    public string $status = 'published';
    public int $affected  = 1;

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[dfs]/', static function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? null;
            return $m[0] === '%s'
                ? ($v === null ? 'NULL' : "'" . str_replace("'", "''", (string) $v) . "'")
                : (string) (int) $v;
        }, $q);
    }

    public function get_row($q, $o = null)
    {
        $this->seen[] = $q;

        if (str_contains($q, 'tt_plans')) {
            return (object) [
                'id' => 1, 'year_id' => 1, 'status' => $this->status,
                'rules' => '', 'max_daily' => 6,
                'effective_from' => '2026-11-01', 'effective_to' => '2026-11-30',
            ];
        }

        return null;
    }

    public function query($q) { $this->seen[] = $q; return $this->affected; }
    public function get_results($q, $o = null) { $this->seen[] = $q; return []; }
    public function get_var($q, ...$x) { $this->seen[] = $q; return null; }
    public function get_col($q, ...$x) { $this->seen[] = $q; return []; }
    public function update($t, $d, $w) { $this->seen[] = 'UPDATE ' . $t; return 1; }
    public function insert($t, $d) { $this->seen[] = 'INSERT ' . $t; return 1; }
}

$wpdb = new FakeWpdb();
$GLOBALS['wpdb'] = $wpdb;

require dirname(__DIR__, 2) . '/school-system/modules/academic/class-tt.php';

// ── الدعاوى ──
$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $got = ''): void
{
    global $pass, $fail;

    if ($cond) {
        $pass++;
        echo "  ✓ {$what}\n";
        return;
    }

    $fail++;
    echo "  ✗ {$what}" . ($got !== '' ? " — {$got}" : '') . "\n";
}

echo "\n① الأشهر: دورةٌ ميلادية كاملة\n";

$months = SCH_TT::months();
$span   = SCH_TT::year_span();

ok('اثنا عشر شهرًا لا أشهر السنة وحدها', count($months) === 12, 'العدد ' . count($months));
ok('تبدأ من شهر بداية السنة', array_key_first($months) === '2026-09', (string) array_key_first($months));
ok('مدى السنة يُقرأ كما سُجّل', $span === ['from' => '2026-09', 'to' => '2027-01'], json_encode($span));

$nums = array_map(static fn (string $ym): string => substr($ym, 5, 2), array_keys($months));
ok('كل شهرٍ ميلاديّ مرّةً واحدة', count(array_unique($nums)) === 12, implode(',', $nums));

// وهذا هو الشكوى نفسها: شهرٌ خارج السنة المسجَّلة يجب أن يبقى في القائمة.
ok('مارس حاضرٌ رغم أن السنة تنتهي في يناير', isset($months['2027-03']), implode(' · ', array_keys($months)));
ok('أغسطس التالي حاضر أيضًا', isset($months['2027-08']));

echo "\n② اسم الشهر لا ينزلق في موقعٍ إزاحته سالبة\n";

$slipped = [];
foreach ($months as $ym => $label) {
    // الاسم يُقارَن برقم شهره: `mysql2date` تُخرج «November 2026».
    $want = gmdate('F Y', (int) strtotime($ym . '-01 12:00:00 UTC'));
    if ($label !== $want) {
        $slipped[] = "{$ym}: «{$label}» بدل «{$want}»";
    }
}

ok('لا شهرَ يُسمّى باسم سابقه', $slipped === [], implode(' · ', array_slice($slipped, 0, 3)));

// وإثباتٌ أن الفحص يكشف: الطريقة القديمة تنزلق فعلًا في هذا الموقع نفسه.
$old = wp_date('F', (int) strtotime('2026-11-01'));
ok('الطريقة القديمة كانت تنزلق حقًّا (فالدعوى أعلاه ليست فارغة)', $old === 'October', $old);

echo "\n③ فتح المعتمد للتعديل\n";

$wpdb->status   = 'draft';
$wpdb->affected = 1;
$res = SCH_TT::reopen(1);
ok('خطةٌ مفتوحة أصلًا تُردّ بسببها', is_wp_error($res) && $res->get_error_code() === 'not_published');

$wpdb->status   = 'published';
$wpdb->affected = 0;
$res = SCH_TT::reopen(1);
ok('ضغطتان متزامنتان: الثانية تُردّ ولا تفتح مرّتين', is_wp_error($res) && $res->get_error_code() === 'already');

$wpdb->status   = 'published';
$wpdb->affected = 1;
$wpdb->seen     = [];
FakeWpdb::$audit = [];
$res = SCH_TT::reopen(1);

ok('الفتح ينجح ويُعيد رسالةً للوكيل', is_array($res) && ($res['msg'] ?? '') !== '');
ok('الحالة تعود «مسوّدة»', (bool) array_filter($wpdb->seen, static fn (string $q): bool =>
    str_contains($q, 'tt_plans') && str_contains($q, "status = 'draft'")));

$touched = array_filter($wpdb->seen, static fn (string $q): bool =>
    str_contains($q, 'wp_sch_timetable') || str_contains($q, 'academic_years'));

ok('**المنشور لا يُمَسّ**: لا `timetable` ولا `academic_years`', $touched === [], implode(' | ', $touched));
ok('الفتح يُسجَّل في التدقيق', in_array('tt.reopened', FakeWpdb::$audit, true), implode(',', FakeWpdb::$audit));

echo "\n═══════════════════════════\n";
printf("  ناجح %d · فاشل %d\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
