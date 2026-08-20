<?php
/**
 * إثبات محرّك التوليد — بلا خادم وبلا قاعدة.
 *
 * يُشغّل `SCH_TT::generate()` الحقيقيّة فوق `$wpdb` مزيّف يُنفّذ ما تحتاجه
 * فعلًا (prepare بالاستبدال الحقيقي، وقراءة النصاب، والتقاط صفوف الإدراج)،
 * ثم يفحص المخرَج بدعاوى صريحة.
 *
 * والدعاوى ليست تجميلًا: أوّلها هو العطل الذي رأيناه في النموذج الأوّليّ —
 * الأحد والاثنين والثلاثاء فارغة والأربعاء والخميس ممتلئان.
 */
declare(strict_types=1);

// ── قشرة ووردبريس ──
define('ABSPATH', __DIR__);
function __($t, $d = '') { return $t; }
function esc_html($t) { return $t; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function get_current_user_id() { return 7; }
function get_userdata($id) { return (object) ['ID' => $id]; }
function wp_date($f, $ts = null) { return date($f, $ts ?? time()); }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) {}
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }
class WP_Error {
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
final class SCH_Years { public static function current_id(): int { return 1; } }
final class SCH_Org { public static function may_touch_class(int $id): bool { return true; } }

/** الشعب: ثلاثة صفوف × أربع شعب = ١٢ شعبة تتشارك المعلمين. */
final class SCH_Classes
{
    public const STAGES = ['kg' => 'روضة', 'primary' => 'ابتدائي', 'intermediate' => 'متوسط', 'secondary' => 'ثانوي'];
    public static array $rows = [];
    public static function get(int $id): ?object { return self::$rows[$id] ?? null; }
}

final class SCH_Subjects
{
    public static array $rows = [];
    public static function get(int $id): ?object { return self::$rows[$id] ?? null; }
}

// ── $wpdb مزيّف: prepare حقيقي، والإدراج يُلتقط ──
final class FakeWpdb
{
    public string $users = 'wp_users';
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $inserted = [];
    public array $demand = [];      // class_id => rows
    public array $kept = [];        // صفوف مثبّتة
    public array $quota_teacher = []; // "class|subject" => teacher

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $out = '';
        $i   = 0;
        $n   = strlen($q);
        for ($k = 0; $k < $n; $k++) {
            if ($q[$k] === '%' && $k + 1 < $n) {
                $t = $q[$k + 1];
                if ($t === 'd') { $out .= (int) ($a[$i++] ?? 0); $k++; continue; }
                if ($t === 's') {
                    $v = $a[$i++] ?? null;
                    $out .= $v === null ? 'NULL' : "'" . str_replace("'", "''", (string) $v) . "'";
                    $k++; continue;
                }
                if ($t === '%') { $out .= '%'; $k++; continue; }
            }
            $out .= $q[$k];
        }
        return $out;
    }

    public function get_results($q, $o = null)
    {
        if (str_contains($q, 'tt_slots') && str_contains($q, 'locked = 1')) {
            return $this->kept;
        }
        if (str_contains($q, 'tt_quota') && str_contains($q, 'weekly > 0')) {
            preg_match('/class_id = (\d+)/', $q, $m);
            return $this->demand[(int) ($m[1] ?? 0)] ?? [];
        }
        return [];
    }

    public function get_row($q, $o = null)
    {
        if (str_contains($q, 'tt_plans')) {
            return (object) [
                'id' => 1, 'year_id' => 1, 'status' => 'draft',
                'rules' => 'no_pair,core_early,pe_last,cap', 'max_daily' => 6,
                'effective_from' => '2026-08-01', 'effective_to' => '2026-08-31',
            ];
        }
        return null;
    }

    public function get_var($q)
    {
        if (str_contains($q, 'teacher_user_id FROM wp_sch_tt_quota')) {
            preg_match('/class_id = (\d+).*subject_id = (\d+)/s', $q, $m);
            return $this->quota_teacher[($m[1] ?? '') . '|' . ($m[2] ?? '')] ?? 0;
        }
        return 0;
    }

    public function get_col($q) { return []; }

    public function query($q)
    {
        if (str_starts_with(ltrim($q), 'INSERT INTO wp_sch_tt_slots')) {
            preg_match_all('/\(([^()]*)\)/', substr($q, strpos($q, 'VALUES')), $m);
            foreach ($m[1] as $tuple) {
                $p = array_map('trim', explode(',', $tuple));
                if (count($p) < 8) { continue; }
                $this->inserted[] = [
                    'class'   => (int) $p[1],
                    'day'     => (int) $p[2],
                    'period'  => (int) $p[3],
                    'subject' => (int) $p[4],
                    'teacher' => $p[5] === 'NULL' ? 0 : (int) trim($p[5], "'"),
                ];
            }
        }
        return 1;
    }

    public function insert($t, $d, $f = null) { return 1; }
    public function update($t, $d, $w, $df = null, $wf = null) { return 1; }
    public function delete($t, $w, $f = null) { return 1; }
    public function esc_like($s) { return $s; }
}

$wpdb = new FakeWpdb();

require dirname(__DIR__, 2) . '/school-system/modules/academic/class-tt.php';

// ═════════════ بذرة الاختبار ═════════════
// ١٢ شعبة ابتدائية · ١٠ حصص × ٥ أيام = ٥٠ خانة لكل شعبة
// ١٠ مواد بنصاب مجموعه ٥٠ · و٢٤ معلمًا.
//
// والعدد ليس اعتباطًا: ٦٠٠ حصة أسبوعية ÷ (٦ حصص يوميًّا × ٥ أيام) = ٢٠ معلمًا
// **حدًّا أدنى رياضيًّا**. ومدرسةٌ بعشرة معلمين لا يمكن أن يكتمل جدولها مهما
// كان المحرّك — وهذا ما يجب أن يقوله للوكيل بدل أن يخرج جدولًا نصفه فارغ.

$SUBJECTS = [
    1 => ['القرآن الكريم', 8], 2 => ['اللغة العربية', 10], 3 => ['الرياضيات', 8],
    4 => ['العلوم', 5], 5 => ['الدراسات الإسلامية', 5], 6 => ['اللغة الإنجليزية', 4],
    7 => ['الاجتماعيات', 3], 8 => ['التربية البدنية', 3], 9 => ['التربية الفنية', 2],
    10 => ['المهارات الرقمية', 2],
];

foreach ($SUBJECTS as $sid => [$name, $n]) {
    SCH_Subjects::$rows[$sid] = (object) ['id' => $sid, 'name' => $name];
}

$CLASSES = [];
for ($c = 1; $c <= 12; $c++) {
    SCH_Classes::$rows[$c] = (object) ['id' => $c, 'stage' => 'primary', 'grade_level' => 'الأول', 'section' => chr(64 + $c), 'capacity' => 15];
    $CLASSES[] = $c;

    $rows = [];
    foreach ($SUBJECTS as $sid => [$name, $n]) {
        // كل شعبة تأخذ معلمًا مختلفًا للمادة نفسها — وإلّا كان التعارض حتميًّا
        $teacher = 100 + ((($sid - 1) * 12 + ($c - 1)) % 24);
        $rows[]  = (object) [
            'subject_id'      => $sid,
            'subject_name'    => $name,
            'weekly'          => $n,
            'teacher_user_id' => $teacher,
        ];
        $wpdb->quota_teacher[$c . '|' . $sid] = $teacher;
    }
    $wpdb->demand[$c] = $rows;
}

// ═════════════ التشغيل ═════════════
$t0  = microtime(true);
$res = SCH_TT::generate(1, $CLASSES);
$ms  = (microtime(true) - $t0) * 1000;

if (is_wp_error($res)) {
    echo "✗ فشل التوليد: " . $res->get_error_message() . "\n";
    exit(1);
}

$rows = $wpdb->inserted;

// ═════════════ الدعاوى ═════════════
$pass = 0;
$fail = 0;
function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

echo "\n═══ محرّك توليد الجداول ═══\n";
printf("  ١٢ شعبة · ٢٤ معلمًا · %d خانة · %.0f ms\n\n", count($rows), $ms);

// ① العطل الأصلي: كل يوم يجب أن يمتلئ
echo "① التوزيع على الأيام\n";
$per_day = [];
foreach ($rows as $r) { $per_day[$r['day']] = ($per_day[$r['day']] ?? 0) + 1; }
ksort($per_day);
ok('الأيام الخمسة كلّها فيها حصص', count($per_day) === 5, 'الموجود: ' . json_encode($per_day));
$min = min($per_day ?: [0]);
$max = max($per_day ?: [0]);
ok('لا يومَ فارغًا بينما غيره ممتلئ', $min > 0 && ($max - $min) <= count($CLASSES),
   "أقلّ يوم {$min} وأكثره {$max}");

// ② التعارض: معلم واحد لا يكون في شعبتين في الحصة نفسها
echo "\n② تعارض المعلمين\n";
$busy = [];
$clash = 0;
foreach ($rows as $r) {
    if ($r['teacher'] <= 0) { continue; }
    $k = $r['teacher'] . '|' . $r['day'] . '|' . $r['period'];
    if (isset($busy[$k])) { $clash++; }
    $busy[$k] = true;
}
ok('صفر تعارض معلم عبر الشعب الاثنتي عشرة', $clash === 0, "وُجد {$clash}");

// ③ الخانة الواحدة لا تحمل مادتين
echo "\n③ سلامة الخانات\n";
$cells = [];
$dupes = 0;
foreach ($rows as $r) {
    $k = $r['class'] . '|' . $r['day'] . '|' . $r['period'];
    if (isset($cells[$k])) { $dupes++; }
    $cells[$k] = true;
}
ok('لا خانة مكرّرة', $dupes === 0, "وُجد {$dupes}");

$bad = 0;
foreach ($rows as $r) { if ($r['period'] < 1 || $r['period'] > 10) { $bad++; } }
ok('كل الحصص داخل إيقاع المرحلة (١–١٠)', $bad === 0, "خارج المدى {$bad}");

// ④ النصاب: لا مادة تتجاوز نصابها
echo "\n④ النصاب\n";
$over = [];
foreach ($CLASSES as $c) {
    $count = [];
    foreach ($rows as $r) {
        if ($r['class'] === $c) { $count[$r['subject']] = ($count[$r['subject']] ?? 0) + 1; }
    }
    foreach ($count as $sid => $n) {
        if ($n > $SUBJECTS[$sid][1]) { $over[] = "شعبة {$c} مادة {$sid}: {$n}>" . $SUBJECTS[$sid][1]; }
    }
}
ok('لا مادة تتجاوز نصابها', $over === [], implode(' · ', array_slice($over, 0, 3)));

$full = 0;
foreach ($CLASSES as $c) {
    $n = count(array_filter($rows, static fn ($r) => $r['class'] === $c));
    if ($n === 50) { $full++; }
}
ok('كل الشعب الاثنتي عشرة اكتملت ٥٠ حصة', $full === 12, "المكتمل {$full}/12");
ok('المولّد أبلغ بـ missing = 0', (int) $res['missing'] === 0, 'أبلغ ' . $res['missing']);

// ⑤ القيود
echo "\n⑤ القيود المفعّلة\n";
$pairs = 0;
foreach ($CLASSES as $c) {
    $by = [];
    foreach ($rows as $r) { if ($r['class'] === $c) { $by[$r['day']][$r['period']] = $r['subject']; } }
    foreach ($by as $day => $ps) {
        ksort($ps);
        // المقارنة بين حصّتين **متجاورتين فعلًا** لا بين متتاليتين في القائمة:
        // يومٌ فيه خانة فارغة يجعل الحصة ٢ والحصة ٥ متجاورتين في المصفوفة.
        $prev  = null;
        $prevP = -9;
        foreach ($ps as $p => $sid) {
            if ($prev !== null && $p === $prevP + 1 && $prev === $sid) { $pairs++; }
            $prev  = $sid;
            $prevP = $p;
        }
    }
}
ok('لا حصتان متتاليتان لنفس المادة', $pairs === 0, "وُجد {$pairs}");

$cap_break = 0;
$load = [];
foreach ($rows as $r) {
    if ($r['teacher'] <= 0) { continue; }
    $k = $r['teacher'] . '|' . $r['day'];
    $load[$k] = ($load[$k] ?? 0) + 1;
}
foreach ($load as $k => $n) { if ($n > 6) { $cap_break++; } }
ok('لا معلم تجاوز حدّه اليومي (٦)', $cap_break === 0, "تجاوز {$cap_break} معلمًا");

// التربية البدنية آخر النهار
$pe_early = 0;
$pe_total = 0;
foreach ($rows as $r) {
    if ($r['subject'] === 8) { $pe_total++; if ($r['period'] <= 8) { $pe_early++; } }
}
ok('الأنشطة الحركية تميل لآخر النهار', $pe_total > 0 && $pe_early < $pe_total,
   "المتأخّر " . ($pe_total - $pe_early) . "/{$pe_total}");

// ⑥ الأداء
echo "\n⑥ الأداء\n";
ok('التوليد أقلّ من ثانيتين لمدرسة كاملة', $ms < 2000, sprintf('%.0f ms', $ms));

// ⑦ الخانات المثبّتة لا تُمسّ
echo "\n⑦ الخانات المثبّتة\n";
$wpdb2 = new FakeWpdb();
$wpdb2->demand        = $wpdb->demand;
$wpdb2->quota_teacher = $wpdb->quota_teacher;
$wpdb2->kept = [(object) ['class_id' => 1, 'day_of_week' => 1, 'period_no' => 1, 'teacher_user_id' => 999]];
$GLOBALS['wpdb'] = $wpdb2;

$res2 = SCH_TT::generate(1, $CLASSES);
$hit  = 0;
foreach ($wpdb2->inserted as $r) {
    if ($r['class'] === 1 && $r['day'] === 1 && $r['period'] === 1) { $hit++; }
}
ok('الخانة المثبّتة لم يُكتب فوقها', $hit === 0, "كُتب عليها {$hit} مرة");

$blocked999 = 0;
foreach ($wpdb2->inserted as $r) {
    if ($r['teacher'] === 999 && $r['day'] === 1 && $r['period'] === 1) { $blocked999++; }
}
ok('معلّم الخانة المثبّتة محجوز في تلك الحصة', $blocked999 === 0, "وُضع {$blocked999} مرة");

// ⑧ الاستحالة تُقال لا تُخفى
echo "\n⑧ حين لا يكفي المعلمون\n";
$wpdb3 = new FakeWpdb();
$wpdb3->quota_teacher = $wpdb->quota_teacher;
foreach ($CLASSES as $c) {
    $rows3 = [];
    foreach ($SUBJECTS as $sid => [$name, $n]) {
        // معلمان اثنان لكل المدرسة — استحالة صريحة
        $rows3[] = (object) ['subject_id' => $sid, 'subject_name' => $name, 'weekly' => $n, 'teacher_user_id' => 100 + ($sid % 2)];
    }
    $wpdb3->demand[$c] = $rows3;
}
$GLOBALS['wpdb'] = $wpdb3;
$res3 = SCH_TT::generate(1, $CLASSES);
ok('أبلغ عن نقصٍ بدل أن يُخرج جدولًا نصفه فارغ بصمت', (int) $res3['missing'] > 0, 'missing=' . $res3['missing']);
ok('ذكر الشعب المتعثّرة بأسبابها', count($res3['blocked']) > 0, 'blocked=' . count($res3['blocked']));
$msg = (string) reset($res3['blocked']);
ok('السبب مكتوب بلغة الوكيل لا بلغة المبرمج', str_contains($msg, 'محجوزون') || str_contains($msg, 'سعة'), $msg);

echo "\n═══════════════════════════\n";
printf("  ناجح %d · فاشل %d\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
