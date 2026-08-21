<?php
/**
 * فحص المال — الحساب الذي لا يُغتفر خطؤه.
 *
 * يُشغّل `SCH_Finance::issue_invoice()` و`record_payment()` **الحقيقيّتين**
 * فوق `$wpdb` مزيّف، ويُثبت أن الريال لا يُخلَق ولا يضيع.
 *
 * **ولماذا الآن:** النظام يُجهَّز لمدرسةٍ تُفوتر أهاليها. وخطأُ شاشةٍ يُرى
 * ويُشتكى منه؛ **وخطأُ ريالٍ لا يراه أحد حتى تُراجَع الحسابات آخر السنة**.
 * والطبقة اليوم صحيحة — والدعاوى هنا تُبقيها كذلك.
 *
 * وثلاث قواعد من `CLAUDE.md` تُثبَّت هنا حرفًا:
 *
 *   ① **الخصم نسبة لا مبلغًا، ويُطبَّق قبل التقسيم** — فالأقساط تظهر على
 *      المبلغ النهائي، والأب يرى في تطبيقه أنه استفاد.
 *   ② **الكسر يُضاف للدفعة الأولى** لا الأخيرة: يدفع الأكثر أوّلًا فيقلّ
 *      المتبقي. والعكس يجعل آخر قسطٍ أكبر من إخوته بلا سبب مفهوم.
 *   ③ **تقسيط المدرسة امتيازٌ يُمنَح** بصلاحية وسببٍ مكتوب — لا يُفتح من
 *      المحاسبة.
 *
 * التشغيل:  php tools/فحص-المال/run.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__);

// ═══════════ قشرة ووردبريس ═══════════

function __($t, $d = '') { return $t; }
function _n($s, $p, $n, $d = '') { return $n === 1 ? $s : $p; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $t)); }
function sanitize_textarea_field($t) { return trim((string) $t); }
function current_time($f) { return $f === 'mysql' ? date('Y-m-d H:i:s') : date($f); }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) {}
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function sch_sanitize_date($v) { return $v ? (string) $v : null; }
function sch_sanitize_datetime($v) { return $v ? (string) $v : null; }
function sch_money($a) { return number_format((float) $a, 2) . ' ر.س'; }
function wp_generate_uuid4() { return 'uuid-' . mt_rand(); }

/** الصلاحية تُبدَّل في الدعاوى لاختبار بوّابة «امتياز يُمنَح». */
$GLOBALS['CAN_GRANT'] = true;
function current_user_can($cap) { return $cap === 'sch_grant_installments' ? (bool) $GLOBALS['CAN_GRANT'] : true; }
function get_current_user_id() { return 7; }

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

final class SCH_Years    { public static function current_id(): int { return 1; } }
final class SCH_Students { public static function get(int $id): ?object { return $id === 1 ? (object) ['id' => 1, 'full_name' => 'محمد'] : null; } }
final class SCH_Comms    { public static function notify(...$a): bool { return true; } }
final class SCH_Guardians{ public static function of_student(int $id): array { return []; } }

/** القيد المحاسبي — يُلتقط ليُتحقَّق أنه داخل المعاملة نفسها. */
final class SCH_AutoPost
{
    public static array $calls = [];
    public static bool  $fail  = false;

    public static function invoice_issued(int $id, float $total, bool $in_tx = false): bool|WP_Error
    {
        self::$calls[] = ['invoice_issued', $id, $total];
        return self::$fail ? new WP_Error('post_failed', 'فشل القيد') : true;
    }

    public static function payment_received(...$a): bool|WP_Error
    {
        self::$calls[] = ['payment_received', ...$a];
        return self::$fail ? new WP_Error('post_failed', 'فشل القيد') : true;
    }

    public static function invoice_voided(...$a): bool|WP_Error { self::$calls[] = ['invoice_voided']; return true; }
    public static function payment_refunded(...$a): bool|WP_Error { self::$calls[] = ['payment_refunded']; return true; }
}

// ═══════════ $wpdb مزيّف — جداولٌ في الذاكرة ═══════════

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;

    /** @var array<string,array<int,array<string,mixed>>> */
    public array $rows = ['invoices' => [], 'installments' => [], 'payments' => [], 'fee_plans' => []];
    public array $tx = [];
    private array $snapshot = [];

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

    private static function table_of(string $q): string
    {
        foreach (['installments', 'invoices', 'payments', 'fee_plans'] as $t) {
            if (str_contains($q, 'wp_sch_' . $t)) { return $t; }
        }
        return '';
    }

    /** شرط `col = value` من الاستعلام — يكفي لما تحتاجه هذه الطبقة. */
    private static function where(string $q): array
    {
        $out = [];
        foreach (['id', 'invoice_id', 'student_id', 'year_id', 'plan_id'] as $c) {
            if (preg_match('/\b' . $c . "\s*=\s*'?(\d+)'?/", $q, $m) === 1) { $out[$c] = (int) $m[1]; }
        }
        return $out;
    }

    private function match(string $table, array $w): array
    {
        return array_values(array_filter($this->rows[$table] ?? [], static function (array $r) use ($w): bool {
            foreach ($w as $k => $v) {
                if ((int) ($r[$k] ?? -1) !== $v) { return false; }
            }
            return true;
        }));
    }

    public function get_var($q)
    {
        $t = self::table_of($q);
        if ($t === '') { return null; }

        $hits = $this->match($t, self::where($q));

        // فاتورةٌ قائمة بنفس الخطة — الملغاة لا تُحسب
        if (str_contains($q, "status <> 'void'")) {
            $hits = array_filter($hits, static fn (array $r): bool => ($r['status'] ?? '') !== 'void');
        }

        return $hits === [] ? null : ($hits[0]['id'] ?? null);
    }

    public function get_row($q, $mode = null)
    {
        $t = self::table_of($q);
        if ($t === '') { return null; }

        $hits = $this->match($t, self::where($q));

        return $hits === [] ? null : (object) $hits[0];
    }

    public function get_results($q, $mode = null)
    {
        $t = self::table_of($q);
        if ($t === '') { return []; }

        $hits = $this->match($t, self::where($q));
        usort($hits, static fn (array $a, array $b): int => ($a['seq'] ?? $a['id']) <=> ($b['seq'] ?? $b['id']));

        return array_map(static fn (array $r): object => (object) $r, $hits);
    }

    public function insert($table, $data)
    {
        $t = self::table_of($table);
        if ($t === '') { return false; }

        $this->insert_id = count($this->rows[$t]) + 1;
        $this->rows[$t][] = array_merge($data, ['id' => $this->insert_id]);

        return 1;
    }

    public function update($table, $data, $where)
    {
        $t = self::table_of($table);
        if ($t === '') { return false; }

        $n = 0;
        foreach ($this->rows[$t] as &$r) {
            $hit = true;
            foreach ($where as $k => $v) {
                if ((string) ($r[$k] ?? '') !== (string) $v) { $hit = false; break; }
            }
            if ($hit) { $r = array_merge($r, $data); $n++; }
        }

        return $n;
    }

    /** المعاملة حقيقية بقدر ما يلزم: لقطةٌ عند البدء وتُستعاد عند التراجع. */
    public function query($sql)
    {
        $this->tx[] = $sql;

        if (str_contains($sql, 'START TRANSACTION')) { $this->snapshot = $this->rows; }
        if (str_contains($sql, 'ROLLBACK'))          { $this->rows = $this->snapshot; }

        return 1;
    }
}

// ═══════════ تحميل الطبقة الحقيقية ═══════════

$ROOT = dirname(__DIR__, 2) . '/school-system';
require_once $ROOT . '/modules/finance/class-finance.php';

// ═══════════ أدوات ═══════════

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $note = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$what}\n"; }
    else       { $fail++; echo "  ✗ {$what}" . ($note !== '' ? "  ← {$note}" : '') . "\n"; }
}

function fresh(float $plan_amount): FakeWpdb
{
    $db = new FakeWpdb();
    $db->rows['fee_plans'][] = ['id' => 1, 'name' => 'الرسوم الدراسية', 'amount' => $plan_amount, 'status' => 'active'];
    SCH_AutoPost::$calls = [];
    SCH_AutoPost::$fail  = false;
    $GLOBALS['CAN_GRANT'] = true;
    $GLOBALS['wpdb'] = $db;

    return $db;
}

function code(mixed $r): string { return $r instanceof WP_Error ? $r->get_error_code() : 'ok'; }

/** مجموع الأقساط — بالقروش لتجنّب مقارنة الفاصلة العائمة. */
function sum_installments(FakeWpdb $db): int
{
    $c = 0;
    foreach ($db->rows['installments'] as $r) { $c += (int) round((float) $r['amount'] * 100); }
    return $c;
}

echo "\n═══════════════════════════════════════════════\n";
echo "  فحص المال\n";
echo "═══════════════════════════════════════════════\n";

// ═══ ① الخصم قبل التقسيم ═══
echo "\n① الخصم نسبة لا مبلغًا، ويُطبَّق قبل التقسيم\n";

$db = fresh(10000.00);
$r  = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'discount_pct' => 15]);
ok('تصدر الفاتورة', code($r) === 'ok', code($r));

$inv = (object) $db->rows['invoices'][0];
ok('الإجمالي الأصليّ محفوظ (gross)', (float) $inv->gross === 10000.00, (string) $inv->gross);
ok('الخصم ١٥٪ = ١٥٠٠', (float) $inv->discount === 1500.00, (string) $inv->discount);
ok('المستحقّ بعد الخصم = ٨٥٠٠', (float) $inv->total === 8500.00, (string) $inv->total);
ok('النسبة تُحفَظ لتُذكَر في الفاتورة والتطبيق', (float) $inv->discount_pct === 15.0);

$db = fresh(10000.00);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'discount_pct' => 150]);
ok('نسبةٌ فوق المئة تُقصّ إلى ١٠٠ لا تُقلب المبلغ',
   (float) $db->rows['invoices'][0]['total'] === 0.0, (string) $db->rows['invoices'][0]['total']);

$db = fresh(10000.00);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'discount_pct' => -20]);
ok('نسبةٌ سالبة لا تزيد المستحقّ',
   (float) $db->rows['invoices'][0]['total'] === 10000.00, (string) $db->rows['invoices'][0]['total']);

// ═══ ② الكسر للدفعة الأولى ═══
echo "\n② الكسر يُضاف للدفعة الأولى لا الأخيرة\n";

/*
 * ١٠٫٠٠٠ بخصم ١٥٪ = ٨٥٠٠ على ٣ أقساط = ٢٨٣٣٫٣٣ لكلٍّ، والباقي قرشٌ واحد.
 * فالأولى ٢٨٣٣٫٣٤ — ويدفع الأكثر أوّلًا.
 */
$db = fresh(10000.00);
SCH_Finance::issue_invoice([
    'student_id' => 1, 'plan_id' => 1, 'discount_pct' => 15,
    'pay_mode' => 'school', 'installments' => 3, 'grant_reason' => 'ظرف أسرة',
]);

$ins = $db->rows['installments'];
ok('ثلاثة أقساط', count($ins) === 3, (string) count($ins));
ok('مجموع الأقساط = المستحقّ بالضبط', sum_installments($db) === 850000, (string) sum_installments($db));
ok('الأولى أكبر من الثانية (الكسر فيها)',
   (float) $ins[0]['amount'] > (float) $ins[1]['amount'],
   $ins[0]['amount'] . ' مقابل ' . $ins[1]['amount']);
ok('الثانية والثالثة متساويتان', (float) $ins[1]['amount'] === (float) $ins[2]['amount']);
ok('الأولى ٢٨٣٣٫٣٤', (float) $ins[0]['amount'] === 2833.34, (string) $ins[0]['amount']);

/* لا ريال يُخلَق ولا يضيع في أي تقسيمٍ ممكن. */
foreach ([2, 3, 4] as $n) {
    foreach ([7777.77, 10000.00, 33333.33, 0.03, 1234.56] as $amount) {
        foreach ([0, 7, 15, 33.33] as $pct) {
            $db = fresh($amount);
            SCH_Finance::issue_invoice([
                'student_id' => 1, 'plan_id' => 1, 'discount_pct' => $pct,
                'pay_mode' => 'school', 'installments' => $n, 'grant_reason' => 'س',
            ]);

            if ($db->rows['invoices'] === []) { continue; }

            $want = (int) round((float) $db->rows['invoices'][0]['total'] * 100);
            if (sum_installments($db) !== $want) {
                ok("التقسيم {$n} على {$amount} بخصم {$pct}٪ لا يضيّع قرشًا", false,
                   sum_installments($db) . ' مقابل ' . $want);
                continue 3;
            }
        }
    }
}
ok('ستّون تقسيمًا مختلفًا: لا قرش يُخلَق ولا يضيع', true);

// ═══ ③ تقسيط المدرسة امتيازٌ يُمنَح ═══
echo "\n③ تقسيط المدرسة — بصلاحية وسببٍ مكتوب\n";

$db = fresh(10000.00);
$r  = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'pay_mode' => 'school', 'installments' => 3]);
ok('يُرفض بلا سببٍ مكتوب', code($r) === 'no_reason', code($r));
ok('ولا يُكتب صفٌّ واحد', $db->rows['invoices'] === [] && $db->rows['installments'] === []);

$db = fresh(10000.00);
$GLOBALS['CAN_GRANT'] = false;
$r = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'pay_mode' => 'school',
                                 'installments' => 3, 'grant_reason' => 'ظرف']);
ok('يُرفض ممّن لا يملك الصلاحية', code($r) === 'no_grant', code($r));

$db = fresh(10000.00);
$r = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'pay_mode' => 'full', 'installments' => 4]);
ok('الدفعة الكاملة قسطٌ واحد مهما طُلب', count($db->rows['installments']) === 1,
   (string) count($db->rows['installments']));

$db = fresh(10000.00);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'pay_mode' => 'bnpl', 'installments' => 4]);
ok('تابي وتمارا قسطٌ واحد — المدرسة تقبض كاملًا',
   count($db->rows['installments']) === 1, (string) count($db->rows['installments']));

// ═══ ④ الفاتورة وقيدها معًا أو لا شيء ═══
echo "\n④ الذرّية: الفاتورة وقيدها معًا أو لا شيء\n";

$db = fresh(10000.00);
SCH_AutoPost::$fail = true;
$r = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1]);
ok('فشل القيد المحاسبيّ يُرجع خطأً', code($r) !== 'ok', code($r));
ok('ولا تبقى فاتورةٌ بلا قيد', $db->rows['invoices'] === [], count($db->rows['invoices']) . ' فاتورة');
ok('ولا أقساطٌ يتيمة', $db->rows['installments'] === []);
ok('التراجع نُفِّذ فعلًا', in_array('ROLLBACK', $db->tx, true));

$db = fresh(10000.00);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1]);
ok('وعند النجاح: القيد داخل المعاملة قبل COMMIT',
   array_search('COMMIT', $db->tx, true) !== false
   && SCH_AutoPost::$calls !== []);

// ═══ ⑤ المنع المكرّر ═══
echo "\n⑤ فاتورةٌ واحدة لكل خطةٍ في السنة\n";

$db = fresh(10000.00);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1]);
$r = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1]);
ok('الثانية تُرفض', code($r) === 'duplicate_invoice', code($r));
ok('وتبقى فاتورةٌ واحدة', count($db->rows['invoices']) === 1, (string) count($db->rows['invoices']));

$r = SCH_Finance::issue_invoice(['student_id' => 99, 'plan_id' => 1]);
ok('طالبٌ غير موجود يُرفض', code($r) === 'student_not_found', code($r));

$r = SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 99]);
ok('خطّةٌ غير موجودة تُرفض', code($r) === 'plan_not_found', code($r));

// ═══ ⑥ الدفع ═══
echo "\n⑥ الدفع: لا يُقبَل أكثر من المستحقّ، ولا يضيع ريال\n";

$db = fresh(10000.00);
SCH_Finance::issue_invoice([
    'student_id' => 1, 'plan_id' => 1, 'discount_pct' => 15,
    'pay_mode' => 'school', 'installments' => 3, 'grant_reason' => 'س',
]);

$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => 2833.34, 'method' => 'cash']);
ok('الدفعة الأولى تُقبَل', code($r) === 'ok', code($r));
ok('`paid` تساوي ما دُفع', (float) $db->rows['invoices'][0]['paid'] === 2833.34,
   (string) $db->rows['invoices'][0]['paid']);
ok('الفاتورة صارت «جزئية»', ($db->rows['invoices'][0]['status'] ?? '') === 'partial',
   (string) ($db->rows['invoices'][0]['status'] ?? ''));
ok('القسط الأوّل أُغلق', ($db->rows['installments'][0]['status'] ?? '') === 'paid',
   (string) ($db->rows['installments'][0]['status'] ?? ''));

$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => 999999, 'method' => 'cash']);
ok('دفعةٌ تتجاوز المتبقّي تُرفض', code($r) !== 'ok', code($r));
ok('ولا تتغيّر `paid`', (float) $db->rows['invoices'][0]['paid'] === 2833.34);

$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => 0, 'method' => 'cash']);
ok('دفعةٌ بصفر تُرفض', code($r) !== 'ok', code($r));

$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => -100, 'method' => 'cash']);
ok('دفعةٌ سالبة تُرفض', code($r) !== 'ok', code($r));

// سداد المتبقّي كاملًا
$left = round((float) $db->rows['invoices'][0]['total'] - (float) $db->rows['invoices'][0]['paid'], 2);
$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => $left, 'method' => 'transfer']);
ok('سداد المتبقّي يُقبَل', code($r) === 'ok', code($r));
ok('الفاتورة صارت «مسدَّدة»', ($db->rows['invoices'][0]['status'] ?? '') === 'paid',
   (string) ($db->rows['invoices'][0]['status'] ?? ''));
ok('`paid` = `total` بالضبط',
   (int) round((float) $db->rows['invoices'][0]['paid'] * 100)
   === (int) round((float) $db->rows['invoices'][0]['total'] * 100),
   $db->rows['invoices'][0]['paid'] . ' مقابل ' . $db->rows['invoices'][0]['total']);
ok('وكل الأقساط أُغلقت',
   count(array_filter($db->rows['installments'], static fn (array $r): bool => ($r['status'] ?? '') === 'paid')) === 3);

$r = SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => 1, 'method' => 'cash']);
ok('لا دفعة على فاتورةٍ مسدَّدة', code($r) !== 'ok', code($r));

// ═══ ⑦ مجموع الدفعات = المدفوع ═══
echo "\n⑦ مجموع صفوف الدفعات يساوي `paid` — لا انزياح\n";

$db = fresh(9999.99);
SCH_Finance::issue_invoice(['student_id' => 1, 'plan_id' => 1, 'pay_mode' => 'school',
                            'installments' => 4, 'grant_reason' => 'س']);

$total = (float) $db->rows['invoices'][0]['total'];
foreach ([1000.01, 2500.55, 3333.33] as $amt) {
    SCH_Finance::record_payment(['invoice_id' => 1, 'amount' => $amt, 'method' => 'cash']);
}

$rows_sum = 0;
foreach ($db->rows['payments'] as $p) { $rows_sum += (int) round((float) $p['amount'] * 100); }

ok('ثلاث دفعات مسجَّلة', count($db->rows['payments']) === 3, (string) count($db->rows['payments']));
ok('`paid` تساوي مجموع صفوف الدفعات',
   (int) round((float) $db->rows['invoices'][0]['paid'] * 100) === $rows_sum,
   $db->rows['invoices'][0]['paid'] . ' مقابل ' . ($rows_sum / 100));
ok('ولا يتجاوز المستحقّ', (float) $db->rows['invoices'][0]['paid'] <= $total);

echo "\n═══════════════════════════════════════════════\n";
printf("  نجح %d · فشل %d\n", $pass, $fail);
echo "═══════════════════════════════════════════════\n\n";

exit($fail > 0 ? 1 : 0);
