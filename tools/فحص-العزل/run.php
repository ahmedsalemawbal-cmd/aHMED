<?php
/**
 * إثبات عزل المشتركين — بلا خادم وبلا قاعدة.
 *
 * يُشغّل `MDD_DB` **الحقيقيّة** فوق `$wpdb` مزيّف يلتقط كل استعلامٍ نصًّا،
 * ويسأل سؤالًا واحدًا بصيغٍ كثيرة: **هل يستطيع مشتركٌ أن يلمس صفًّا لغيره؟**
 *
 * وهذا الفحص هو أوّل ما يُكتب في مداد وآخر ما يُحذف: العزل لا يُختبر
 * بالنظر إلى الشفرة — كل سطرٍ فيها يبدو صحيحًا — بل بمحاولة الاختراق.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('MDD_PATH', '/home/user/aHMED/midad/');
define('MDD_URL', 'https://midad.test/wp-content/plugins/midad/');
define('MDD_VERSION', 'test');
define('MDD_FILE', MDD_PATH . 'midad.php');
define('MDD_BASE', 'midad');
define('MDD_API_NS', 'midad/v1');

// ── قشرة ووردبريس ──
$GLOBALS['CAPS'] = [];
$GLOBALS['USER'] = 7;

function __($t, $d = '') { return $t; }
function esc_html($t) { return $t; }
function esc_html__($t, $d = '') { return $t; }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $t)); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_email($t) { return trim((string) $t); }
function is_email($t) { return str_contains((string) $t, '@'); }
function get_current_user_id() { return $GLOBALS['USER']; }
function get_userdata($id) { return (object) ['ID' => $id, 'display_name' => 'اسم', 'user_login' => '0500000000']; }
function current_user_can($c) { return $GLOBALS['CAPS'][$c] ?? false; }
function current_time($f) { return gmdate($f === 'mysql' ? 'Y-m-d H:i:s' : $f); }
function get_option($k, $d = false) { return $GLOBALS['OPTIONS'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['OPTIONS'][$k] = $v; return true; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); }
function add_query_arg($k, $v, $u) { return $u . '?' . $k . '=' . $v; }
function home_url($p = '') { return 'https://midad.test' . $p; }
function username_exists($u) { return false; }
function email_exists($e) { return false; }
function wp_insert_user($d) { return 101; }
function wp_delete_user($id) { return true; }
function wp_safe_redirect($u) { throw new RuntimeException('redirect:' . $u); }

/** `wp_die` تُرمى استثناءً — فالدعوى تُمسكها وتُثبت أن الحارس عمل. */
function wp_die($m = '', $t = '', $a = []) { throw new DomainException((string) $m); }

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

/**
 * `$wpdb` يلتقط النصّ ويُرجع ما يُملى عليه.
 *
 * والدعوى تفحص **النصّ المُرسَل** لا النتيجة: العزل يقع في بناء الشرط،
 * وقاعدةٌ مزيّفة تُرجع ما تشاء لا تُثبت شيئًا عن الشرط الذي وصلها.
 */
final class SpyWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public string $last_error = '';
    public array $seen = [];
    public array $writes = [];
    public array $next = [];

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;

        return preg_replace_callback('/%[dfs]/', static function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? null;

            return $m[0] === '%s'
                ? "'" . str_replace("'", "''", (string) $v) . "'"
                : (string) (int) $v;
        }, (string) $q);
    }

    public function get_results($q, $o = null) { $this->seen[] = (string) $q; return array_shift($this->next) ?? []; }
    public function get_row($q, $o = null) { $this->seen[] = (string) $q; $r = array_shift($this->next); return $r[0] ?? null; }
    public function get_var($q, ...$x) { $this->seen[] = (string) $q; return array_shift($this->next) ?? 0; }
    public function query($q) { $this->seen[] = (string) $q; return 1; }

    public function insert($t, $d) { $this->writes[] = ['insert', $t, $d, []]; $this->insert_id = 55; return 1; }
    public function update($t, $d, $w) { $this->writes[] = ['update', $t, $d, $w]; return 1; }
    public function delete($t, $w) { $this->writes[] = ['delete', $t, [], $w]; return 1; }

    public function last(): string { return (string) end($this->seen); }
    public function reset(): void { $this->seen = []; $this->writes = []; $this->next = []; }
}

$wpdb = new SpyWpdb();
$GLOBALS['wpdb'] = $wpdb;

require_once MDD_PATH . 'includes/functions.php';
require_once MDD_PATH . 'includes/class-db.php';
require_once MDD_PATH . 'includes/class-tenant.php';
require_once MDD_PATH . 'includes/class-member.php';
require_once MDD_PATH . 'includes/class-access.php';

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

/** يُثبت أن الكتلة أوقفها الحارس. */
function blocked(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (DomainException) {
        return true;
    }
}

/** المشترك الحاليّ في هذا الفحص. */
$as = static fn (int $id, callable $fn): mixed => MDD_DB::as_tenant($id, $fn);

echo "\n① كل قراءةٍ مقيّدة بالمشترك\n";

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('documents'));
ok('القراءة تحمل المشترك ولو لم يُطلَب', str_contains($wpdb->last(), "`tenant_id` = '7'"), $wpdb->last());

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('documents', ['status' => 'draft']));
$sql = $wpdb->last();
ok('ومع شرطٍ آخر يبقى القيدان', str_contains($sql, "`tenant_id` = '7'") && str_contains($sql, "`status` = 'draft'"), $sql);

$wpdb->reset();
$as(7, static fn () => MDD_DB::count('sheets'));
ok('والعدّ كذلك', str_contains($wpdb->last(), "`tenant_id` = '7'"), $wpdb->last());

echo "\n② تزوير المعرّف في الرابط لا يفتح شيئًا\n";

$wpdb->reset();
$wpdb->next = [[]];   // القاعدة لا تُرجع صفًّا لأن الشرط لا يطابق
$row = $as(7, static fn () => MDD_DB::one('documents', 99));

ok('**صفُّ مشتركٍ آخر لا يُرجَع**', $row === null);
ok('والقيد في الاستعلام لا في فحصٍ بعده',
    str_contains($wpdb->last(), "`tenant_id` = '7'") && str_contains($wpdb->last(), "`id` = '99'"),
    $wpdb->last());

echo "\n③ الكتابة مقيّدة كالقراءة\n";

$wpdb->reset();
$as(7, static fn () => MDD_DB::insert('documents', ['title' => 'ملفّ']));
[$kind, $table, $data] = $wpdb->writes[0];

ok('الإدراج يحقن المشترك', ($data['tenant_id'] ?? 0) === 7, json_encode($data, JSON_UNESCAPED_UNICODE));
ok('ويضع created_at بلا أن يُطلَب', ($data['created_at'] ?? '') !== '');

$wpdb->reset();
$wpdb->next = [[(object) ['id' => 5]]];
$as(7, static fn () => MDD_DB::update('documents', 5, ['title' => 'جديد']));
[$kind, $table, $data, $where] = $wpdb->writes[0];

ok('**التحديث يشترط المشترك في WHERE**', ($where['tenant_id'] ?? 0) === 7, json_encode($where));

$wpdb->reset();
$wpdb->next = [[]];   // `one()` لا تجد الصفّ لأنه لغيره
$done = $as(7, static fn () => MDD_DB::delete('documents', 99));

ok('**والحذف يُردّ قبل أن يُرسَل**', $done === false && $wpdb->writes === [], count($wpdb->writes) . ' كتابة');

echo "\n④ المكتبة العامّة بلا قيد\n";

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('templates', ['role' => 'teacher']));
ok('القوالب يقرؤها الجميع', !str_contains($wpdb->last(), 'tenant_id'), $wpdb->last());

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('plans'));
ok('والباقات كذلك', !str_contains($wpdb->last(), 'tenant_id'), $wpdb->last());

$wpdb->reset();
$wpdb->next = [[]];
$as(7, static fn () => MDD_DB::one('tenants', 9));
ok('**وجدول المشتركين يُقيَّد بـ`id` لا بـ`tenant_id`**',
    str_contains($wpdb->last(), "`id` = '7'") && !str_contains($wpdb->last(), 'tenant_id'),
    $wpdb->last());

echo "\n⑤ استعلامٌ بلا مشترك يسقط ولا يمرّ\n";

$GLOBALS['USER'] = 0;
MDD_Tenant::forget();
$wpdb->reset();

ok('**القراءة بلا مشترك تُوقف الطلب**', blocked(static fn () => MDD_DB::rows('documents')));
ok('ولم يُرسَل استعلامٌ أصلًا', $wpdb->seen === [], (string) count($wpdb->seen));

$GLOBALS['USER'] = 7;
MDD_Tenant::forget();

echo "\n⑥ منفذ السوبر أدمن محروس\n";

$GLOBALS['CAPS'] = [];
ok('**العبور بين المشتركين يُردّ بلا صلاحية**', blocked(static fn () => MDD_DB::across('documents')));

$GLOBALS['CAPS']['manage_options'] = true;
$wpdb->reset();
MDD_DB::across('documents');
ok('ومعها يمرّ بلا قيد', !str_contains($wpdb->last(), 'tenant_id'), $wpdb->last());
$GLOBALS['CAPS'] = [];

echo "\n⑦ النطاق يعود مهما وقع\n";

$before = null;
$as(7, static function () use (&$before) { $before = 'inside'; });

$wpdb->reset();
try {
    $as(9, static function (): void { throw new RuntimeException('كسر'); });
} catch (RuntimeException) {
    // مقصود
}

$as(7, static fn () => MDD_DB::rows('documents'));
ok('**استثناءٌ داخل `as_tenant` لا يترك الطلب بمشتركٍ آخر**',
    str_contains($wpdb->last(), "`tenant_id` = '7'"), $wpdb->last());

echo "\n⑧ الأسماء تُفحَص والقيم تُهرَّب\n";

ok('عمودٌ ملفَّق يُوقف الطلب', blocked(static fn () => $as(7, static fn () => MDD_DB::rows('documents', ['id = 1 OR 1' => 1]))));
ok('وجدولٌ ملفَّق كذلك', blocked(static fn () => MDD_DB::table('documents; DROP TABLE x')));
ok('وترتيبٌ ملفَّق كذلك', blocked(static fn () => $as(7, static fn () => MDD_DB::rows('documents', [], ['order' => 'id; DROP']))));

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('documents', ['title' => "لا' OR '1'='1"]));
ok('والقيمة تُهرَّب فلا تكسر الشرط', str_contains($wpdb->last(), "''"), $wpdb->last());

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('documents', ['id' => [3, 7]]));
ok('وقائمةُ قيمٍ تصير IN مهرَّبة', str_contains($wpdb->last(), "IN ('3','7')"), $wpdb->last());

$wpdb->reset();
$as(7, static fn () => MDD_DB::rows('documents', ['id' => []]));
ok('**وقائمةٌ فارغة تعني «لا شيء» لا «كل شيء»**', str_contains($wpdb->last(), '1 = 0'), $wpdb->last());

echo "\n⑨ العلامة المائية والحالة\n";

$trial = (object) ['status' => 'trial', 'trial_ends_at' => gmdate('Y-m-d H:i:s', time() + 3 * 86400), 'subscription_ends_at' => null];
$over  = (object) ['status' => 'trial', 'trial_ends_at' => '2020-01-01 00:00:00', 'subscription_ends_at' => null];
$paid  = (object) ['status' => 'active', 'trial_ends_at' => null, 'subscription_ends_at' => gmdate('Y-m-d H:i:s', time() + 86400)];
$lapsed = (object) ['status' => 'active', 'trial_ends_at' => null, 'subscription_ends_at' => '2020-01-01 00:00:00'];
$stop  = (object) ['status' => 'suspended', 'trial_ends_at' => null, 'subscription_ends_at' => null];

ok('التجربة السارية «trial»', MDD_Tenant::state($trial) === 'trial');
ok('**والتجربة المنتهية «expired» ولو بقي العمود `trial`**', MDD_Tenant::state($over) === 'expired', MDD_Tenant::state($over));
ok('والمدفوع «active»', MDD_Tenant::state($paid) === 'active');
ok('**والمدفوع الذي انقضى «expired» بلا كرونٍ يُحدّثه**', MDD_Tenant::state($lapsed) === 'expired', MDD_Tenant::state($lapsed));
ok('والموقوف يبقى موقوفًا لا منتهيًا', MDD_Tenant::state($stop) === 'suspended');
ok('وأيّام التجربة تُحسَب', MDD_Tenant::trial_days_left($trial) === 3, (string) MDD_Tenant::trial_days_left($trial));

echo "\n⑩ البوّابة\n";

$GLOBALS['USER'] = 0;
MDD_Tenant::forget();

$reached = static function (string $section): bool {
    try {
        MDD_Access::guard($section);
        return true;
    } catch (RuntimeException) {
        return false;
    }
};

ok('**المنتهي لا يبلغ شاشة الملفّات**', !$reached('files'));
ok('ولا شاشة نور', !$reached('sheets'));
ok('ويبلغ الدفع', $reached('billing'));
ok('ويبلغ حسابه', $reached('account'));
ok('ويبلغ الخروج — فلا يُحبَس بلا مخرج', $reached('logout'));

echo "\n═══════════════════════════\n";
printf("  ناجح %d · فاشل %d\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
