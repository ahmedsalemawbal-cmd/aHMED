<?php
/**
 * إثبات طبقة الاختبارات — بلا خادم وبلا قاعدة.
 *
 * يُشغّل `SCH_Exams` **الحقيقيّة** فوق `$wpdb` مزيّف يحمل جداولَ في الذاكرة
 * ويفهم شروط `WHERE` البسيطة التي تكتبها الطبقة فعلًا — فالكتابة تُغيّر
 * الحالة، والقراءة التالية ترى ما كُتب. وهذا ما يجعل الدعاوى تختبر السلوك
 * لا شكل النصّ.
 *
 * والمحاور الثمانية هي ما يخسره النظام إن انكسر بصمت:
 *   ① التواريخ تتبع قناع أيام المرحلة — ومدرسةُ السبت لا تُقفَز عليها
 *   ② التعارض: قاعةٌ أو مراقبٌ في شعبةٍ أخرى في اليوم نفسه
 *   ③ جدولٌ واحد لكل شعبة في الدورة — الحفظ ثانيةً يُحدّث ولا يُكرّر
 *   ④ الأوزان: المجموع مئةٌ دائمًا · والدرجة = النصفان · والنجاح عند ٥٠
 *   ⑤ المعذور خارج المتوسط وحاضرٌ في الدور البديل
 *   ⑥ بوّابة الاعتماد: ما لم يُعتمَد لا يصل الأسرة
 *   ⑦ الترحيل يُعلّم القديم معتمدًا — فلا تختفي درجةٌ يراها أهلُها اليوم
 *   ⑧ الذرّية: ضغطتان على «اعتماد» لا تعتمدان مرّتين
 */
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);

const ROOT = '/home/user/aHMED/school-system/';

// ── قشرة ووردبريس ──
function __($t, $d = '') { return $t; }
function esc_html($t) { return $t; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $t)); }
function get_current_user_id() { return 7; }
function get_userdata($id) { return $id > 0 ? (object) ['ID' => $id] : null; }
function current_user_can($cap) { return $GLOBALS['CAPS'][$cap] ?? false; }
function current_time($f) { return gmdate($f); }
function sch_now() { return gmdate('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) { $GLOBALS['AUDIT'][] = $a[0] ?? ''; }
function sch_settings($k = null, $d = '') { return $GLOBALS['SETTINGS'][$k] ?? $d; }
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_date($f, $ts = null) { return gmdate($f, $ts ?? time()); }
function mysql2date($f, $d, $t = true) { return gmdate($f, (int) strtotime($d . ' UTC')); }
function sch_sanitize_date($v)
{
    $v = (string) $v;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
}

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

// ── أضدادُ الاختبار للأصناف المجاورة ──
final class SCH_Years
{
    public static function current_id(): int { return 1; }
    public static function current(): ?object
    {
        return (object) ['id' => 1, 'start_date' => '2026-09-01', 'end_date' => '2027-06-30'];
    }
}

final class SCH_Classes
{
    public const STAGES = [
        'nursery' => 'الحضانة', 'kg' => 'الروضة', 'prep' => 'التمهيدي',
        'primary' => 'ابتدائي', 'intermediate' => 'متوسط', 'secondary' => 'ثانوي',
    ];
    public static array $rows = [];
    public static function get(int $id): ?object { return self::$rows[$id] ?? null; }
    public static function list(?int $y = null): array { return array_values(self::$rows); }
    public static function label(object $c): string { return $c->grade_level . ' / ' . $c->section; }
}

final class SCH_Subjects
{
    public static array $rows = [];
    public static function get(int $id): ?object { return self::$rows[$id] ?? null; }
    public static function all(?string $stage = null): array { return array_values(self::$rows); }
}

final class SCH_Org
{
    public static bool $allow = true;
    public static function may_touch_class(int $id): bool { return self::$allow; }
}

/** قناع الأيام: الأحد..الخميس افتراضًا، ومدرسةُ السبت تُبدّله. */
final class SCH_TT
{
    public static array $open = [1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس'];
    public static function days(string $stage): array { return self::$open; }
    public static function months(): array
    {
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $ym = gmdate('Y-m', gmmktime(12, 0, 0, 9 + $i, 1, 2026));
            $out[$ym] = $ym;
        }
        return $out;
    }
    public static function teachers(): array { return []; }
}

final class SCH_Timetable
{
    public static function school_day(?string $date = null): int
    {
        $w = (int) gmdate('w', (int) strtotime($date . ' 12:00:00 UTC'));
        return match ($w) { 0 => 1, 1, 2, 3, 4 => $w + 1, 6 => 6, default => 0 };
    }
}

require_once __DIR__ . '/قاعدة-الذاكرة.php';

$GLOBALS['CAPS']     = ['sch_manage_exams' => true];
$GLOBALS['SETTINGS'] = [];
$GLOBALS['AUDIT']    = [];

$wpdb = new MemWpdb();
$GLOBALS['wpdb'] = $wpdb;

require_once ROOT . 'modules/academic/class-exams.php';

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

/** بذرةٌ نظيفة: شعبتان في الابتدائي، ثلاث مواد، ثلاثة طلاب في الأولى. */
function seed(): void
{
    global $wpdb;

    SCH_Classes::$rows = [
        1 => (object) ['id' => 1, 'stage' => 'primary', 'grade_level' => 'الأول', 'section' => 'A', 'year_id' => 1],
        2 => (object) ['id' => 2, 'stage' => 'primary', 'grade_level' => 'الأول', 'section' => 'B', 'year_id' => 1],
    ];
    SCH_Subjects::$rows = [
        11 => (object) ['id' => 11, 'name' => 'الرياضيات'],
        12 => (object) ['id' => 12, 'name' => 'العلوم'],
        13 => (object) ['id' => 13, 'name' => 'اللغة العربية'],
    ];
    SCH_Org::$allow = true;

    $wpdb->reset();

    foreach ([1, 2] as $c) {
        foreach ([11, 12, 13] as $s) {
            $wpdb->seed('class_subjects', ['class_id' => $c, 'subject_id' => $s, 'teacher_user_id' => 100 + $s]);
        }
    }

    foreach ([[501, 'أحمد سالم', '1042'], [502, 'باسل سالم', '1043'], [503, 'علي سالم', '1064']] as [$id, $n, $no]) {
        $wpdb->seed('students', ['id' => $id, 'full_name' => $n, 'academic_no' => $no, 'status' => 'active']);
        $wpdb->seed('enrollments', ['student_id' => $id, 'class_id' => 1, 'status' => 'active']);
    }
}

echo "\n① التواريخ تتبع قناع أيام المرحلة\n";

seed();
$GLOBALS['SETTINGS']['exam_start_final'] = '2026-12-10'; // خميس
$days = SCH_Exams::days_for(1, 'final');

ok('يومٌ لكل مادة — لا أكثر ولا أقلّ', count($days) === 3, 'العدد ' . count($days));
ok('يبدأ من اليوم المضبوط', ($days[0]['date'] ?? '') === '2026-12-10', $days[0]['date'] ?? '—');
ok('يقفز الجمعة والسبت حين لا يكونان دوامًا',
    ($days[1]['date'] ?? '') === '2026-12-13', $days[1]['date'] ?? '—');

// ومدرسةٌ تداوم السبت لا يُقفَز عليه
SCH_TT::$open[6] = 'السبت';
$sat = SCH_Exams::days_for(1, 'final');
ok('**ومدرسةُ السبت لا تُقفَز عليها**', ($sat[1]['date'] ?? '') === '2026-12-12', $sat[1]['date'] ?? '—');
unset(SCH_TT::$open[6]);

echo "\n② التعارض: القاعة والمراقب\n";

seed();
$GLOBALS['SETTINGS']['exam_start_final'] = '2026-12-10';
$d = SCH_Exams::days_for(1, 'final');

SCH_Exams::place(1, 11, 'final', null, $d[0]['date']);
SCH_Exams::place(1, 12, 'final', null, $d[1]['date']);

$h = SCH_Exams::conflicts(1, 'final');
ok('شعبةٌ وحدها: لا تعارض مهما تكرّرت قاعتها', $h['room'] === 0 && $h['proctor'] === 0,
    "قاعة {$h['room']} · مراقب {$h['proctor']}");
ok('«مواد بلا موعد» تعدّ الباقي', $h['missing'] === 1, (string) $h['missing']);

// الشعبة الثانية تأخذ القاعة نفسها في اليوم نفسه
SCH_Exams::place(2, 11, 'final', null, $d[0]['date']);
SCH_Exams::set_slot_field(2, 11, 'final', null, 'room', SCH_Exams::rooms()[0]);
SCH_Exams::set_slot_field(1, 11, 'final', null, 'room', SCH_Exams::rooms()[0]);

$h = SCH_Exams::conflicts(1, 'final');
ok('**قاعةٌ واحدة في يومٍ واحد لشعبتين تُكشَف**', $h['room'] === 1, (string) $h['room']);

SCH_Exams::set_slot_field(2, 11, 'final', null, 'proctor', '111');
SCH_Exams::set_slot_field(1, 11, 'final', null, 'proctor', '111');
$h = SCH_Exams::conflicts(1, 'final');
ok('**ومراقبٌ واحد كذلك**', $h['proctor'] === 1, (string) $h['proctor']);
ok('الخانة المتعارضة تُعلَّم باسمها', isset($h['cells'][11]), json_encode(array_keys($h['cells'])));

// وفكّ التعارض يُطفئه
SCH_Exams::set_slot_field(2, 11, 'final', null, 'room', SCH_Exams::rooms()[1]);
SCH_Exams::set_slot_field(2, 11, 'final', null, 'proctor', '');
$h = SCH_Exams::conflicts(1, 'final');
ok('وفكّه يُطفئ العلامة', $h['room'] === 0 && $h['proctor'] === 0, "قاعة {$h['room']} · مراقب {$h['proctor']}");

echo "\n③ جدولٌ واحد لكل شعبة في الدورة\n";

seed();
$GLOBALS['SETTINGS']['exam_start_final'] = '2026-12-10';
$d = SCH_Exams::days_for(1, 'final');
SCH_Exams::place(1, 11, 'final', null, $d[0]['date']);
SCH_Exams::place(1, 12, 'final', null, $d[1]['date']);

$one = SCH_Exams::save_schedule(1, 'final');
ok('الحفظ الأوّل إنشاء', is_array($one) && $one['updated'] === false && $one['count'] === 2, json_encode($one));

$two = SCH_Exams::save_schedule(1, 'final');
ok('**والحفظ الثاني تحديثٌ لا نسخة ثانية**', is_array($two) && $two['updated'] === true, json_encode($two));
ok('عدد الصفوف لم يتضاعف', count(SCH_Exams::schedule(1, 'final')) === 2,
    (string) count(SCH_Exams::schedule(1, 'final')));

$list = SCH_Exams::saved_list();
ok('«الجداول المُنشأة» تُظهر جدولًا واحدًا لا اثنين', count($list) === 1, (string) count($list));

// ووضعُ مادةٍ على يومٍ مشغول يُزيح ساكنه لا يكدّس اختبارين
SCH_Exams::place(1, 13, 'final', null, $d[0]['date']);
$grid  = SCH_Exams::schedule(1, 'final');
$onday = 0;
foreach ($grid as $e) {
    if ((string) $e->exam_date === $d[0]['date']) { $onday++; }
}
ok('**اختبارٌ واحد في اليوم الواحد** — الوضع يُزيح ولا يكدّس', $onday === 1, (string) $onday);

echo "\n④ الأوزان والمجموع والنجاح\n";

seed();
$w = SCH_Exams::weights(1, 11, 'final');
ok('الافتراض ٤٠/٦٠', $w === ['year' => 40, 'final' => 60], json_encode($w));

SCH_Exams::set_weight(1, 11, 'final', null, 55);
$w = SCH_Exams::weights(1, 11, 'final');
ok('**المجموع مئةٌ دائمًا**', $w['year'] + $w['final'] === 100, json_encode($w));
ok('والوزن يُحفظ كما ضُبط', $w['year'] === 55, (string) $w['year']);

SCH_Exams::set_weight(1, 11, 'final', null, 90);
ok('الوزن مقصوصٌ على ستّين', SCH_Exams::weights(1, 11, 'final')['year'] === 60);
SCH_Exams::set_weight(1, 11, 'final', null, 1);
ok('وعلى عشرة من الأسفل', SCH_Exams::weights(1, 11, 'final')['year'] === 10);

SCH_Exams::set_weight(1, 11, 'final', null, 40);
$over = SCH_Exams::save_score(1, 11, 'final', null, 501, 'year', '45');
ok('**درجةٌ فوق وزن نصفها تُرفَض** — وإلا تجاوز المجموع المئة', is_wp_error($over), 'قُبلت');

SCH_Exams::save_score(1, 11, 'final', null, 501, 'year', '35');
SCH_Exams::save_score(1, 11, 'final', null, 501, 'graded', '40');
SCH_Exams::save_score(1, 11, 'final', null, 502, 'year', '10');
SCH_Exams::save_score(1, 11, 'final', null, 502, 'graded', '20');

$roster = SCH_Exams::roster(1, 11, 'final');
$w      = SCH_Exams::weights(1, 11, 'final');
$by     = [];
foreach ($roster as $r) { $by[(int) $r->id] = $r; }

ok('المجموع = النصفان', SCH_Exams::total_of($by[501], $w) === 75, (string) SCH_Exams::total_of($by[501], $w));
ok('والنجاح عند ٥٠', SCH_Exams::total_of($by[501], $w) >= SCH_Exams::PASS_MARK
    && SCH_Exams::total_of($by[502], $w) < SCH_Exams::PASS_MARK);

$st = SCH_Exams::stats($roster, $w);
ok('المرصود اثنان من ثلاثة', $st['entered'] === 2 && $st['total'] === 3, json_encode($st));
ok('والمتوسط ٥٣', $st['avg'] === 53, (string) $st['avg']);
ok('وراسبٌ واحد', $st['fails'] === 1, (string) $st['fails']);

echo "\n⑤ الغياب بعذر\n";

SCH_Exams::toggle_excused(1, 11, 'final', null, 502);
$roster = SCH_Exams::roster(1, 11, 'final');
$st     = SCH_Exams::stats($roster, $w);

$excused = array_values(array_filter($roster, static fn (object $r): bool => (int) $r->excused === 1));
ok('**المعذور خارج المتوسط**', $st['entered'] === 1 && $st['fails'] === 0, json_encode($st));
ok('والمتوسط صار ٧٥', $st['avg'] === 75, (string) $st['avg']);
ok('وحاضرٌ في كشف الدور البديل', count($excused) === 1 && (int) $excused[0]->id === 502);

SCH_Exams::toggle_excused(1, 11, 'final', null, 502);
$roster = SCH_Exams::roster(1, 11, 'final');
ok('وإلغاء العذر يُعيده', SCH_Exams::stats($roster, $w)['entered'] === 2);

echo "\n⑥ و⑧ الإرسال والاعتماد\n";

ok('الحالة تبدأ مفتوحة', SCH_Exams::grade_state(1, 11, 'final') === 'open');

$s1 = SCH_Exams::submit(1, 11, 'final');
ok('الإرسال ينجح', is_array($s1), is_wp_error($s1) ? $s1->get_error_code() : '');
ok('والحالة صارت «مُرسَلة»', SCH_Exams::grade_state(1, 11, 'final') === 'submitted');

$s2 = SCH_Exams::submit(1, 11, 'final');
ok('**وإرسالٌ ثانٍ يُردّ**', is_wp_error($s2) && $s2->get_error_code() === 'already');

$locked = SCH_Exams::save_score(1, 11, 'final', null, 503, 'year', '20');
ok('**والدرجة تُقفل بعد الإرسال**', is_wp_error($locked) && $locked->get_error_code() === 'locked');

$a1 = SCH_Exams::approve(1, 11, 'final');
ok('الاعتماد ينجح', is_array($a1), is_wp_error($a1) ? $a1->get_error_code() : '');
ok('والحالة «معتمدة»', SCH_Exams::grade_state(1, 11, 'final') === 'approved');

$a2 = SCH_Exams::approve(1, 11, 'final');
ok('**وضغطتان متزامنتان لا تعتمدان مرّتين**', is_wp_error($a2), 'مرّت الثانية');
ok('والاعتماد يُسجَّل في التدقيق', in_array('exam.grades_approved', $GLOBALS['AUDIT'], true));

echo "\n⑦ بوّابة الرؤية: ما لم يُعتمَد لا يصل الأسرة\n";

$assess = file_get_contents(ROOT . 'modules/academic/class-assessment.php');
$report = substr($assess, strpos($assess, 'function report_card'), 1800);
ok('`report_card()` تشترط `approved_at`', str_contains($report, 'e.approved_at IS NOT NULL'));

$up = substr($assess, strpos($assess, 'function upcoming_exams'), 900);
ok('و`upcoming_exams()` تشترط `published_at`', str_contains($up, 'e.published_at IS NOT NULL'));

$cert = file_get_contents(ROOT . 'modules/academic/class-certificates.php');
ok('وقائمة التفوّق تُبنى من المعتمد وحده', str_contains($cert, "AND e.approved_at IS NOT NULL"));

$act = file_get_contents(ROOT . 'includes/class-activator.php');
ok('**والترحيل يُعلّم القديم معتمدًا** — فلا تختفي درجةٌ يراها أهلُها اليوم',
    str_contains($act, 'migrate_exams_approved')
    && str_contains($act, 'COALESCE(approved_at, created_at)'));
ok('ويُنادى من الترقية', str_contains($act, 'self::migrate_exams_approved();'));

echo "\n⑨ النطاق: المادة ليست مادة كل أحد\n";

seed();
$GLOBALS['CAPS'] = ['sch_manage_exams' => false];
$deny = SCH_Exams::save_score(1, 11, 'final', null, 501, 'year', '10');
ok('**من ليس معلّم المادة يُردّ ولو أرسل الطلب مباشرةً**',
    is_wp_error($deny) && $deny->get_error_code() === 'forbidden', 'مرّ');

$wpdb->seed('class_subjects', ['class_id' => 1, 'subject_id' => 11, 'teacher_user_id' => 7]);
ok('ومعلّمها يمرّ', SCH_Exams::save_score(1, 11, 'final', null, 501, 'year', '10') === true);

$GLOBALS['CAPS'] = ['sch_manage_exams' => true];
SCH_Org::$allow = false;
$out = SCH_Exams::place(1, 12, 'final', null, '2026-12-10');
ok('وشعبةٌ خارج النطاق تُردّ', is_wp_error($out) && $out->get_error_code() === 'forbidden');

echo "\n═══════════════════════════\n";
printf("  ناجح %d · فاشل %d\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
