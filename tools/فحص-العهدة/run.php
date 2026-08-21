<?php
/**
 * إثبات مصفوفة التسليم — بلا خادم وبلا قاعدة.
 *
 * يُشغّل `SCH_Custody::record()` **الحقيقيّة** فوق `$wpdb` مزيّف، ويُثبت
 * الأسئلة التي يقف عندها طفلٌ عند الباب:
 *
 *   • هل يخرج طالب الروضة نهاية الدوام بمسح الحارس وحده؟
 *   • هل يُرفض الخروج المبكر بلا مستلم من قائمة الأب؟
 *   • هل يُرفض من كتب اسمه وهو غير مخوَّل؟
 *   • هل ينزل الصغير من الباص بلا تأكيد؟
 *   • هل يمرّ نداء الانصراف لطالبٍ مشترك بالنقل **بلا فحص الحقّ**؟
 *
 * والدعوى الأولى والأخيرة هما العطلان اللذان وجدهما الفحص الشامل: الأولى
 * كانت تُرَدّ بـ`picker_required` فلا يخرج طفل صغير من البوابة أبدًا،
 * والأخيرة كانت تمرّ بلا فحصٍ واحد فيصير النداء بابًا خلفيًّا على `can_pickup`.
 *
 * التشغيل:  php tools/فحص-العهدة/run.php
 */
declare(strict_types=1);

// ═══════════ قشرة ووردبريس ═══════════

define('ABSPATH', __DIR__);

function __($t, $d = '') { return $t; }
function _n($s, $p, $n, $d = '') { return $n === 1 ? $s : $p; }
function esc_html($t) { return $t; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $t)); }
function get_current_user_id() { return 9; }
function current_time($f) { return $f === 'mysql' ? date('Y-m-d H:i:s') : date($f); }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) { Log::$audit[] = $a[0] ?? ''; }
function sch_sanitize_datetime($v) { return $v ? (string) $v : null; }
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m, $s); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function number_format_i18n($n) { return (string) $n; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f | JSON_UNESCAPED_UNICODE); }

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '', public int $status = 400) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

final class Log { public static array $audit = []; public static array $notified = []; }

// ═══════════ بدائل الطبقات المجاورة ═══════════

final class SCH_Enrollment
{
    public static function full_name(object $s): string { return (string) ($s->full_name ?? ''); }
}

final class SCH_Students
{
    /** @var array<int,object> */
    public static array $rows = [];
    public static function get(int $id): ?object { return self::$rows[$id] ?? null; }
    public static function current_class(int $id): ?object { return null; }
}

final class SCH_Routes
{
    /** @var array<int,bool> student_id => مشترك؟ */
    public static array $subs = [];
    public static function subscription_of(int $id) { return (self::$subs[$id] ?? false) ? (object) ['id' => 1] : null; }
}

final class SCH_Guardians
{
    /** @var array<int,array<int,object>> */
    public static array $of = [];
    public static function of_student(int $id): array { return self::$of[$id] ?? []; }
}

final class SCH_Comms
{
    public static function notify(int $user, string $title, string $body, string $ref = '', int $rid = 0): void
    {
        Log::$notified[] = ['user' => $user, 'title' => $title, 'body' => $body];
    }
}

final class SCH_Attendance
{
    public static function status_for_arrival(string $when): array { return ['status' => 'present', 'minutes' => 0]; }
    public static function mark(...$a): void {}
}

final class SCH_Alerts { public static function resolve(...$a): void {} }
final class SCH_Pass
{
    public static function looks_like(string $t): bool { return str_starts_with($t, 'P.'); }
    public static function verify(string $t, $at = null): ?object { return null; }
}

// ═══════════ $wpdb مزيّف ═══════════

final class FakeWpdb
{
    public string $users = 'wp_users';
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;

    /** صفوف الجداول التي تقرؤها `pickers()` */
    public array $guardian_student = [];   // [student_id, guardian_user_id, relation, is_primary, can_pickup, display_name]
    public array $delegations = [];        // [id, student_id, person_name, relation, valid_until]

    public array $events = [];             // custody_events المُدرَجة
    public array $updates = [];            // تحديثات الطلاب
    private array $tx = [];

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $out = ''; $i = 0; $n = strlen($q);
        for ($k = 0; $k < $n; $k++) {
            if ($q[$k] === '%' && $k + 1 < $n) {
                $t = $q[$k + 1];
                if ($t === 'd') { $out .= (int) ($a[$i++] ?? 0); $k++; continue; }
                if ($t === 'f') { $out .= (float) ($a[$i++] ?? 0); $k++; continue; }
                if ($t === 's') { $out .= "'" . addslashes((string) ($a[$i++] ?? '')) . "'"; $k++; continue; }
                if ($t === '%') { $out .= '%'; $k++; continue; }
            }
            $out .= $q[$k];
        }
        return $out;
    }

    public function get_results($q)
    {
        // قائمة أولياء الأمر المخوَّلين
        if (str_contains($q, 'guardian_student')) {
            preg_match('/student_id = (\d+)/', $q, $m);
            $sid = (int) ($m[1] ?? 0);
            $out = [];
            foreach ($this->guardian_student as $r) {
                if ((int) $r['student_id'] === $sid && (int) $r['can_pickup'] === 1) {
                    $out[] = (object) [
                        'guardian_user_id' => $r['guardian_user_id'],
                        'relation'         => $r['relation'],
                        'is_primary'       => $r['is_primary'],
                        'display_name'     => $r['display_name'],
                    ];
                }
            }
            return $out;
        }

        // التفويضات السارية
        if (str_contains($q, 'pickup_delegations')) {
            preg_match('/student_id = (\d+)/', $q, $m);
            $sid = (int) ($m[1] ?? 0);
            $out = [];
            foreach ($this->delegations as $r) {
                if ((int) $r['student_id'] === $sid) { $out[] = (object) $r; }
            }
            return $out;
        }

        return [];
    }

    public function get_row($q)
    {
        // فحص التكرار بـclient_uuid
        if (str_contains($q, 'custody_events') && str_contains($q, 'client_uuid')) {
            preg_match("/client_uuid = '([^']*)'/", $q, $m);
            foreach ($this->events as $e) {
                if ((string) $e->client_uuid === (string) ($m[1] ?? '')) { return $e; }
            }
        }
        return null;
    }

    public function get_var($q) { return null; }

    public function insert($table, $data)
    {
        if (str_contains($table, 'custody_events')) {
            $this->insert_id = count($this->events) + 1;
            $this->events[] = (object) array_merge($data, ['id' => $this->insert_id]);
            return 1;
        }
        return 1;
    }

    public function update($table, $data, $where)
    {
        $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

        if (str_contains($table, 'students') && isset($where['id'], SCH_Students::$rows[$where['id']])) {
            foreach ($data as $k => $v) { SCH_Students::$rows[$where['id']]->$k = $v; }
        }
        return 1;
    }

    public function query($sql) { $this->tx[] = $sql; return 1; }
}

// ═══════════ تحميل الصنف الحقيقي ═══════════

$ROOT = dirname(__DIR__, 2) . '/school-system';
require_once $ROOT . '/modules/custody/class-custody.php';

// ═══════════ الدعاوى ═══════════

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $note = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$what}\n"; }
    else       { $fail++; echo "  ✗ {$what}" . ($note !== '' ? "  ← {$note}" : '') . "\n"; }
}

/** يُعيد النظام إلى حالة معلومة قبل كل دعوى. */
function scene(string $stage, string $state, bool $rides = false, bool $withPicker = true): FakeWpdb
{
    SCH_Students::$rows = [
        1 => (object) [
            'id' => 1, 'full_name' => 'محمد أحمد', 'stage' => $stage,
            'custody_state' => $state, 'academic_no' => '14470001', 'status' => 'active',
        ],
    ];
    SCH_Routes::$subs      = [1 => $rides];
    SCH_Guardians::$of     = [1 => [(object) ['user_id' => 50]]];
    Log::$audit            = [];
    Log::$notified         = [];

    $db = new FakeWpdb();

    if ($withPicker) {
        $db->guardian_student[] = [
            'student_id' => 1, 'guardian_user_id' => 50, 'relation' => 'الأب',
            'is_primary' => 1, 'can_pickup' => 1, 'display_name' => 'أحمد سالم',
        ];
        // وليّ أمر مرتبط لكنه **غير مخوَّل** — يجب ألّا يظهر في القائمة
        $db->guardian_student[] = [
            'student_id' => 1, 'guardian_user_id' => 51, 'relation' => 'الأم',
            'is_primary' => 0, 'can_pickup' => 0, 'display_name' => 'سارة',
        ];
    }

    $GLOBALS['wpdb'] = $db;
    return $db;
}

$uuid = 0;
function go(array $d): array|WP_Error
{
    global $uuid;
    return SCH_Custody::record(array_merge(['student_id' => 1, 'client_uuid' => 'u' . (++$uuid)], $d));
}

function code(array|WP_Error $r): string
{
    return $r instanceof WP_Error ? $r->get_error_code() : 'ok';
}

echo "\n═══════════════════════════════════════════════\n";
echo "  فحص العهدة — مصفوفة التسليم كما في WORKFLOW.md\n";
echo "═══════════════════════════════════════════════\n";

// ── ① خروج نهاية الدوام: مسح الحارس وحده، لكل المراحل ──
echo "\n① خروج نهاية الدوام — مسح الحارس وحده\n";

foreach (['kg' => 'الروضة', 'primary' => 'الابتدائي', 'intermediate' => 'المتوسط', 'secondary' => 'الثانوي'] as $stage => $label) {
    scene($stage, 'at_school');
    $r = go(['checkpoint' => 'gate_out']);
    ok("{$label} يخرج بمسح الحارس بلا مستلم", code($r) === 'ok' && $r['state'] === 'home', code($r));
}

// ── ② الخروج المبكر: مستلم من القائمة + لكل المراحل ──
echo "\n② الخروج المبكر — من قائمة المخوَّلين لا من خانة كتابة\n";

scene('kg', 'at_school');
$r = go(['checkpoint' => 'early_out']);
ok('يُرفض بلا مستلم', code($r) === 'picker_required', code($r));

scene('secondary', 'at_school');
$r = go(['checkpoint' => 'early_out']);
ok('يُرفض بلا مستلم للثانوي أيضًا', code($r) === 'picker_required', code($r));

scene('primary', 'at_school');
$r = go(['checkpoint' => 'early_out', 'picker' => 'g:51']);
ok('يُرفض بوليّ أمرٍ غير مخوَّل (can_pickup = 0)', code($r) === 'not_authorized', code($r));

scene('primary', 'at_school');
$r = go(['checkpoint' => 'early_out', 'picker' => 'g:999']);
ok('يُرفض بمفتاحٍ مخترَع', code($r) === 'not_authorized', code($r));

scene('primary', 'at_school');
$r = go(['checkpoint' => 'early_out', 'picker' => 'g:50', 'reason' => 'موعد طبي']);
ok('يمرّ بمخوَّل ويصير «غادر مبكرًا»', code($r) === 'ok' && $r['state'] === 'left_early', code($r));

$ev = end($GLOBALS['wpdb']->events);
ok('اسم المستلم يُشتقّ من مفتاحه فيُكتب في الحدث', ($ev->receiver_name ?? '') === 'أحمد سالم', (string) ($ev->receiver_name ?? '—'));
ok('السبب يُحفظ مع الحدث', ($ev->reason ?? '') === 'موعد طبي', (string) ($ev->reason ?? '—'));

$msg = '';
foreach (Log::$notified as $n) { if (str_contains($n['title'], 'خروج مبكر')) { $msg = $n['body']; } }
ok('إشعار الأب يحمل اسم المستلم لا فراغًا', str_contains($msg, 'أحمد سالم'), $msg ?: '—');

// ── ③ النزول من الباص: تأكيد إجباري للصغار وحدهم ──
echo "\n③ النزول من الباص — التأكيد للروضة والابتدائي\n";

scene('kg', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight']);
ok('الروضة يُرفض نزوله بلا مخوَّل', code($r) === 'picker_required', code($r));

scene('primary', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight']);
ok('الابتدائي يُرفض نزوله بلا مخوَّل', code($r) === 'picker_required', code($r));

scene('intermediate', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight']);
ok('المتوسط ينزل وحده', code($r) === 'ok' && $r['state'] === 'home', code($r));

scene('secondary', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight']);
ok('الثانوي ينزل وحده', code($r) === 'ok' && $r['state'] === 'home', code($r));

scene('kg', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight', 'picker' => 'g:50']);
ok('الروضة ينزل بمخوَّل', code($r) === 'ok' && $r['state'] === 'home', code($r));

scene('secondary', 'on_bus', true);
$r = go(['checkpoint' => 'bus_alight', 'picker' => 'g:51']);
ok('الكبير: إن ذُكر مستلمٌ فلا بدّ أن يكون مخوَّلًا', code($r) === 'not_authorized', code($r));

// ── ④ راكب الباص: الوجهة تتبع من استلمه ──
echo "\n④ راكب الباص عند خروج نهاية الدوام\n";

scene('primary', 'at_school', true);
$r = go(['checkpoint' => 'gate_out']);
ok('مسح الحارس وحده يضعه في الباص', code($r) === 'ok' && $r['state'] === 'on_bus', code($r));

scene('primary', 'at_school', true);
$r = go(['checkpoint' => 'gate_out', 'picker' => 'g:50']);
ok('استلام أبيه يضعه في البيت لا في الباص', code($r) === 'ok' && $r['state'] === 'home', code($r));

/*
 * الدعوى الحاسمة: نداء الانصراف يمرّ من `gate_out`، وكان تحويل الوجهة إلى
 * `on_bus` يُلغي فحص `may_pick_up()` كلّه — فيصير النداء بابًا خلفيًّا
 * على إنفاذ `can_pickup` لكل طالبٍ مشترك بالنقل.
 */
scene('primary', 'at_school', true);
$r = go(['checkpoint' => 'gate_out', 'picker' => 'g:51']);
ok('غير المخوَّل يُرفض ولو كان الطالب مشتركًا بالنقل', code($r) === 'not_authorized', code($r));

// ── ⑤ الانتقالات الممنوعة تُرفض وتُسجَّل ──
echo "\n⑤ الانتقالات الممنوعة\n";

scene('primary', 'home');
$r = go(['checkpoint' => 'gate_out']);
ok('من في بيته لا «يخرج» من البوابة', code($r) === 'bad_transition', code($r));
ok('الرفض يُسجَّل في التدقيق لا يُتجاهل', in_array('custody.rejected', Log::$audit, true));

scene('primary', 'home');
$r = go(['checkpoint' => 'bus_board']);
ok('غير المشترك لا يصعد الباص', code($r) === 'not_subscribed', code($r));

// ── ⑥ منع التكرار ──
echo "\n⑥ منع التكرار\n";

scene('primary', 'at_school');
$a = SCH_Custody::record(['student_id' => 1, 'checkpoint' => 'gate_out', 'client_uuid' => 'same']);
$b = SCH_Custody::record(['student_id' => 1, 'checkpoint' => 'gate_out', 'client_uuid' => 'same']);
ok('المسح المكرّر يُعيد النتيجة الأولى بلا سجلٍّ ثانٍ',
   code($a) === 'ok' && code($b) === 'ok' && ($b['duplicate'] ?? false) === true && count($GLOBALS['wpdb']->events) === 1,
   'events=' . count($GLOBALS['wpdb']->events));

// ── ⑦ البطاقة: الرمز العشوائي وحده ──
echo "\n⑦ البطاقة تحمل رمزًا عشوائيًّا لا رقمًا متسلسلًا\n";

$src = file_get_contents($ROOT . '/modules/custody/class-custody.php');
preg_match('/WHERE \(badge_token[^\']*/', $src, $m);
$clause = $m[0] ?? '';
ok('`find_by_badge` لا تقبل `academic_no`', !str_contains($clause, 'academic_no'), $clause);
ok('`find_by_badge` لا تقبل `student_no`', !str_contains($clause, 'student_no'), $clause);

// ── ⑧ من لا مخوَّل له ──
echo "\n⑧ طالبٌ بلا مخوَّل واحد\n";

scene('primary', 'at_school', false, false);
ok('قائمة المخوَّلين فارغة', SCH_Custody::pickers(1) === []);
$r = go(['checkpoint' => 'early_out']);
ok('خروجه المبكر يُرفض — والواجهة تقول ذلك بدل خانة كتابة', code($r) === 'picker_required', code($r));

echo "\n═══════════════════════════════════════════════\n";
printf("  نجح %d · فشل %d\n", $pass, $fail);
echo "═══════════════════════════════════════════════\n\n";

exit($fail > 0 ? 1 : 0);
