<?php
/**
 * فحص النسخ الاحتياطي — الموضعان اللذان يقتل الخطأ فيهما.
 *
 * النسخة الاحتياطية ملفٌّ يُكتب اليوم ويُقرأ يوم الكارثة، فلا أحد يكتشف
 * عطلها إلا حين يحتاجها. وموضعان فيها لا يحتملان الخطأ:
 *
 *   ① **تقسيم SQL.** الفاصلة المنقوطة داخل نصٍّ ليست نهاية جملة — واسمُ
 *      مدرسةٍ فيه «؛» أو ملاحظةُ معلّمٍ فيها فاصلة منقوطة تكسر التقسيم
 *      الساذج، فتُستعاد قاعدةٌ ناقصة **بلا رسالة خطأ واحدة**.
 *
 *   ② **أمان المسار.** اسمُ الملفّ يأتي من المستخدم، ومسارٌ صاعد فيه
 *      (`../../wp-config.php`) يجعل «تنزيل النسخة» بابًا لقراءة أي ملفّ
 *      على الخادم — وصلاحيةُ الإعدادات لا تعني حقّ قراءة كلمة مرور القاعدة.
 *
 * التشغيل:  php tools/فحص-النسخ/run.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__);

// ═══════════ قشرة ووردبريس ═══════════

function __($t, $d = '') { return $t; }
function _n($s, $p, $n, $d = '') { return $n === 1 ? $s : $p; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_file_name($t) { return (string) $t; }
function get_current_user_id() { return 1; }
function current_time($f) { return $f === 'mysql' ? date('Y-m-d H:i:s') : date($f); }
function sch_now() { return date('Y-m-d H:i:s'); }
function sch_table($t) { return 'wp_sch_' . $t; }
function sch_audit(...$a) {}
function sch_settings($k, $d = '') { return $d; }
function sch_api_error($c, $m, $s = 400) { return new WP_Error($c, $m); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function home_url($p = '/') { return 'https://example.test' . $p; }
function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f | JSON_UNESCAPED_UNICODE); }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir() . '/sch-backup-test']; }
function trailingslashit($s) { return rtrim((string) $s, '/') . '/'; }
function wp_cache_flush() {}

const DAY_IN_SECONDS = 86400;
const SCH_VERSION    = '10.9.0';

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

/** `private_dir()` الحقيقية تحتاج ووردبريس — والمطلوب منها هنا المسار وحده. */
final class SCH_Enrollment
{
    public static function private_dir(): string
    {
        $dir = sys_get_temp_dir() . '/sch-backup-test/sch-private';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}

final class SCH_Activator
{
    public static function table_names(): array { return ['students', 'attendance']; }
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $ran = [];

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $out = ''; $i = 0; $n = strlen($q);
        for ($k = 0; $k < $n; $k++) {
            if ($q[$k] === '%' && $k + 1 < $n) {
                $t = $q[$k + 1];
                if ($t === 'd') { $out .= (int) ($a[$i++] ?? 0); $k++; continue; }
                if ($t === 's') { $out .= "'" . addslashes((string) ($a[$i++] ?? '')) . "'"; $k++; continue; }
                if ($t === '%') { $out .= '%'; $k++; continue; }
            }
            $out .= $q[$k];
        }
        return $out;
    }

    public function get_var($q) { return null; }
    public function get_row($q, $mode = null) { return null; }
    public function get_results($q, $mode = null) { return []; }
    public function query($q) { $this->ran[] = $q; return 1; }
}

$GLOBALS['wpdb'] = new FakeWpdb();

require_once dirname(__DIR__, 2) . '/school-system/modules/system/class-backup.php';

// ═══════════ أداة الدعاوى ═══════════

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $note = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$what}\n"; }
    else       { $fail++; echo "  ✗ {$what}" . ($note !== '' ? "  ← {$note}" : '') . "\n"; }
}

/** `split_statements` خاصّة — تُبلَغ بالانعكاس، فالفحص يختبر الشفرة لا نسخةً منها. */
function split(string $sql): array
{
    $m = new ReflectionMethod('SCH_Backup', 'split_statements');
    $m->setAccessible(true);

    return $m->invoke(null, $sql);
}

echo "\n═══════════════════════════════════════════════\n";
echo "  فحص النسخ الاحتياطي\n";
echo "═══════════════════════════════════════════════\n";

// ── ① تقسيم SQL ──
echo "\n① تقسيم جُمل SQL\n";

ok('جملتان بسيطتان', count(split("SELECT 1; SELECT 2;")) === 2);

ok('الجملة الأخيرة بلا فاصلة منقوطة لا تُفقَد',
   count(split("SELECT 1; SELECT 2")) === 2, 'العدد ' . count(split("SELECT 1; SELECT 2")));

/*
 * الدعوى المركزية: اسمُ مدرسةٍ فيه فاصلة منقوطة. والتقسيم الساذج
 * (`explode(';')`) يُخرج ثلاث جُمل من واحدة، فتفشل الاستعادة أو — أسوأ —
 * تُدرَج صفوفٌ مبتورة.
 */
/* الفاصلة **اللاتينية** لا العربية: `؛` (U+061B) حرفٌ آخر لا يقسمه حتى
   `explode(';')`، فدعوى تستعملها تمرّ على التقسيم الساذج ولا تُثبت شيئًا. */
$sql = "INSERT INTO `t` (`a`) VALUES ('مدرسة أُصول; الابتدائية');";
ok('الفاصلة المنقوطة داخل نصٍّ ليست نهاية جملة',
   count(split($sql)) === 1, 'انقسمت إلى ' . count(split($sql)));

$sql = "INSERT INTO `t` (`a`) VALUES ('a;b'),('c;d'),('e;f');";
ok('ثلاث فواصل داخل نصوصٍ في جملةٍ واحدة',
   count(split($sql)) === 1, 'انقسمت إلى ' . count(split($sql)));

// علامة اقتباس مهروبة — `\'` لا تُنهي النصّ
$sql = "INSERT INTO `t` (`a`) VALUES ('O\\'Brien; Sons');";
ok('علامة الاقتباس المهروبة لا تُنهي النصّ',
   count(split($sql)) === 1, 'انقسمت إلى ' . count(split($sql)));

// اسم عمودٍ بين علامتَي تنصيص خلفيّتين وفيه فاصلة منقوطة
$sql = "INSERT INTO `t` (`a;b`) VALUES (1);";
ok('الفاصلة داخل `` `` لا تُنهي الجملة',
   count(split($sql)) === 1, 'انقسمت إلى ' . count(split($sql)));

$sql = "CREATE TABLE `t` (`a` INT);\nINSERT INTO `t` VALUES (1),(2);\n";
ok('البنية والصفوف جملتان', count(split($sql)) === 2, 'العدد ' . count(split($sql)));

ok('الفراغ لا يُخرج جملةً فارغة', split("  \n  ;  \n ") === []);

// ── ② أمان المسار ──
echo "\n② أمان مسار الملفّ\n";

/*
 * اسمُ الملفّ يأتي من المستخدم. ومسارٌ صاعد يجعل «تنزيل النسخة» بابًا
 * لقراءة أيّ ملفّ على الخادم — وصلاحيةُ الإعدادات ليست حقًّا في قراءة
 * كلمة مرور القاعدة.
 */
foreach ([
    '../../wp-config.php',
    '..%2F..%2Fwp-config.php',
    'sch-2026-01-01_120000.zip/../../../etc/passwd',
    '/etc/passwd',
    'sch-2026-01-01_120000.php',
    'sch-2026-01-01_120000.zip.php',
    'anything.zip',
    '',
] as $bad) {
    ok('يُرفض: ' . ($bad === '' ? '(فارغ)' : $bad), SCH_Backup::path_of($bad) === null);
}

// الشكل الصحيح يُقبل شكلًا (وإن لم يوجد الملفّ فالنتيجة null لعدم وجوده لا لشكله)
$dir  = SCH_Backup::dir();
$good = 'sch-2026-01-01_120000.zip';
file_put_contents($dir . '/' . $good, 'x');
ok('يُقبل الاسم الصحيح حين يوجد الملفّ', SCH_Backup::path_of($good) !== null);
ok('والمسار داخل مجلّد النسخ لا خارجه',
   str_starts_with((string) SCH_Backup::path_of($good), $dir . '/'));
@unlink($dir . '/' . $good);

ok('الاسم الصحيح لملفٍّ غير موجود يُردّ null', SCH_Backup::path_of($good) === null);

// ── ③ مجلّد النسخ داخل المحميّ ──
echo "\n③ موضع النسخ\n";

ok('مجلّد النسخ داخل المجلّد المحميّ لا في الرفع العام',
   str_starts_with(SCH_Backup::dir(), SCH_Enrollment::private_dir()),
   SCH_Backup::dir());

ok('المجلّد يُنشأ إن لم يكن موجودًا', is_dir(SCH_Backup::dir()));

// ── ④ القائمة على مجلّدٍ فارغ ──
echo "\n④ الحالة الفارغة\n";

foreach (glob(SCH_Backup::dir() . '/*') ?: [] as $f) { @unlink($f); }

ok('لا نسخ ← قائمة فارغة لا خطأ', SCH_Backup::all() === []);
ok('لا نسخ ← `latest()` تُرجع null', SCH_Backup::latest() === null);

// ── ⑤ الترتيب: الأحدث أوّلًا ──
echo "\n⑤ الترتيب\n";

if (class_exists('ZipArchive')) {
    foreach ([['sch-2026-01-01_090000.zip', '2026-01-01 09:00:00'],
              ['sch-2026-03-05_140000.zip', '2026-03-05 14:00:00'],
              ['sch-2026-02-02_100000.zip', '2026-02-02 10:00:00']] as [$name, $at]) {
        $z = new ZipArchive();
        $z->open(SCH_Backup::dir() . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('manifest.json', (string) json_encode([
            'format' => 2, 'created_at' => $at, 'why' => 'manual', 'rows' => 10,
        ]));
        $z->close();
    }

    $all = SCH_Backup::all();
    ok('ثلاث نسخ تُقرأ', count($all) === 3, 'العدد ' . count($all));
    ok('الأحدث أوّلًا', ($all[0]['file'] ?? '') === 'sch-2026-03-05_140000.zip', $all[0]['file'] ?? '—');
    ok('`latest()` هي الأحدث', (SCH_Backup::latest()['file'] ?? '') === 'sch-2026-03-05_140000.zip');
    ok('البطاقة تُقرأ من داخل الأرشيف', (SCH_Backup::latest()['rows'] ?? 0) === 10);

    foreach (glob(SCH_Backup::dir() . '/*') ?: [] as $f) { @unlink($f); }
} else {
    echo "  ⚠ ZipArchive غير مُفعَّلة — دعاوى الترتيب مُتخطّاة\n";
}

echo "\n═══════════════════════════════════════════════\n";
printf("  نجح %d · فشل %d\n", $pass, $fail);
echo "═══════════════════════════════════════════════\n\n";

exit($fail > 0 ? 1 : 0);
