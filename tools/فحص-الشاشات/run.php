<?php
/**
 * فحص دخان لكل شاشة في النظام — على قاعدةٍ فارغة.
 *
 * السؤال الذي يجيب عنه: **هل تصمد كل شاشة في اليوم الأول؟** مدرسةٌ ثبّتت
 * الإضافة للتوّ: لا طالب ولا شعبة ولا حصة ولا فاتورة. كل شاشة يجب أن تعرض
 * حالتها الفارغة، لا أن تسقط بخطأ قاتل ولا أن تصرخ بتحذير.
 *
 * وتُصيَّر بالأصناف الحقيقية لا بنسخةٍ منها (`SCH_Loader::load_modules()`
 * كما يُحمّلها ووردبريس) — فما يسقط هنا يسقط عند العميل. ولا يُزيَّف إلا
 * ووردبريس نفسه و`$wpdb` الذي يُرجع الفراغ عن كل استعلام.
 *
 * وكل شاشة في عمليّةٍ مستقلّة: `exit` في وثيقةٍ مستقلّة لا يُنهي الفحص،
 * وخطأٌ قاتل في شاشةٍ لا يُخفي بقيّتها.
 *
 *   php tools/فحص-الشاشات/run.php            # الكلّ
 *   php tools/فحص-الشاشات/run.php students   # شاشة واحدة بتفصيلها
 */

declare(strict_types=1);

const ROOT = '/home/user/aHMED/school-system/';

$only = $argv[1] ?? '';
$one  = __DIR__ . '/شاشة-واحدة.php';

/** [السطح، الاسم، المجلّد، معرّف الكيان] */
$screens = [];
foreach (glob(ROOT . 'frontend/views/*.php') ?: [] as $f) {
    $n = basename($f, '.php');
    if ($n === 'layout') { continue; }
    $screens[] = ['الداشبورد', $n, 'frontend/views/', str_contains($n, '-single') || $n === 'student-print' ? 1 : 0];
}
foreach ([
    ['وليّ الأمر', 'frontend/app/'],
    ['الطالب',     'frontend/student/'],
    ['المعلم',     'frontend/teacher/'],
    ['البوابة',    'frontend/gate/'],
    ['السائق',     'frontend/driver/'],
] as [$label, $dir]) {
    foreach (glob(ROOT . $dir . '*.php') ?: [] as $f) {
        $n = basename($f, '.php');
        if ($n === 'layout') { continue; }
        $screens[] = [$label, $n, $dir, 1];
    }
}

if ($only !== '') {
    $screens = array_values(array_filter($screens, static fn (array $s): bool => $s[1] === $only));
    if ($screens === []) {
        fwrite(STDERR, "لا شاشة باسم {$only}\n");
        exit(1);
    }
}

$fatals = [];
$noisy  = [];
$clean  = 0;

printf("\n══════════════════════════════════════════════════════════════\n");
printf("  فحص الشاشات على قاعدة فارغة — %d شاشة\n", count($screens));
printf("══════════════════════════════════════════════════════════════\n");

$surface = '';
foreach ($screens as [$label, $name, $dir, $id]) {
    if ($label !== $surface) {
        $surface = $label;
        printf("\n── %s ──\n", $label);
    }

    $cmd = sprintf(
        'php %s %s %s %d 2>&1 1>/dev/null',
        escapeshellarg($one),
        escapeshellarg($dir),
        escapeshellarg($name),
        $id
    );

    $raw = (string) shell_exec($cmd);
    $res = json_decode($raw, true);

    if (!is_array($res)) {
        $fatals[] = [$label, $name, 'العمليّة ماتت بلا نتيجة: ' . trim(mb_substr($raw, 0, 200))];
        printf("  ✗ %-22s العمليّة ماتت\n", $name);
        continue;
    }

    if (($res['fatal'] ?? '') !== '') {
        $fatals[] = [$label, $name, (string) $res['fatal']];
        printf("  ✗ %-22s %s\n", $name, (string) $res['fatal']);
        continue;
    }

    $notes = (array) ($res['notes'] ?? []);
    if ($notes !== []) {
        // التحذير الواحد يتكرّر داخل الحلقة — يُطوى بموضعه ويُعدّ
        $uniq = [];
        foreach ($notes as $n) {
            $k = $n['file'] . ':' . $n['line'] . '|' . $n['msg'];
            $uniq[$k] = ($uniq[$k] ?? 0) + 1;
        }
        $noisy[] = [$label, $name, $uniq];
        printf("  ⚠ %-22s %d تحذيرًا · %d موضعًا\n", $name, count($notes), count($uniq));
        if ($only !== '') {
            foreach ($uniq as $k => $c) {
                [$where, $msg] = explode('|', $k, 2);
                printf("      %s ×%d — %s\n", $where, $c, $msg);
            }
        }
        continue;
    }

    $clean++;
    printf("  ✓ %-22s %s بايت\n", $name, number_format((int) ($res['bytes'] ?? 0)));
}

printf("\n══════════════════════════════════════════════════════════════\n");
printf("  نظيفة %d · بتحذيرات %d · ساقطة %d\n", $clean, count($noisy), count($fatals));
printf("══════════════════════════════════════════════════════════════\n");

if ($fatals !== []) {
    printf("\n### الشاشات الساقطة (%d)\n", count($fatals));
    foreach ($fatals as [$l, $n, $m]) { printf("  [%s] %s\n      %s\n", $l, $n, $m); }
}

if ($noisy !== [] && $only === '') {
    printf("\n### مواضع التحذير (%d شاشة)\n", count($noisy));
    foreach ($noisy as [$l, $n, $u]) {
        printf("  [%s] %s\n", $l, $n);
        foreach ($u as $k => $c) {
            [$where, $msg] = explode('|', $k, 2);
            printf("      %s ×%d — %s\n", $where, $c, $msg);
        }
    }
}

exit($fatals === [] ? 0 : 1);
