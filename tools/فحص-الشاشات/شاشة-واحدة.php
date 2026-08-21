<?php
/**
 * يُصيّر شاشةً واحدة في عمليّةٍ مستقلّة، ويكتب نتيجتها JSON على stdout.
 *
 * العزل مقصود: شاشةٌ تنادي `exit` (مثل وثيقة A4 المستقلّة) كانت تُنهي
 * الفحص كلّه فتبدو بقيّة الشاشات كأنها لم تُفحَص. والخطأ القاتل الذي لا
 * تلتقطه `Throwable` — نفاد الذاكرة أو حلقةٌ لا تنتهي — يُقتل هنا وحده.
 *
 *   php شاشة-واحدة.php <المجلّد> <الاسم> <المعرّف>
 */

declare(strict_types=1);

require __DIR__ . '/محاكي-ووردبريس.php';

[$dir, $name, $id] = [$argv[1] ?? '', $argv[2] ?? '', (int) ($argv[3] ?? 0)];

$result = ['fatal' => '', 'bytes' => 0, 'notes' => [], 'skip' => ''];

// النتيجة تُكتب حتى لو خرج القالب بـ`exit` أو سقط بخطأٍ قاتل
register_shutdown_function(static function () use (&$result): void {
    $last = error_get_last();
    if ($last !== null && ($last['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        $result['fatal'] = $last['message'] . ' @ '
                         . str_replace(SCH_PATH, '', $last['file']) . ':' . $last['line'];
    }

    $result['notes'] = $GLOBALS['SCH_NOTES'] ?? [];

    while (ob_get_level() > 0) {
        $result['bytes'] = max($result['bytes'], strlen((string) ob_get_clean()));
    }

    fwrite(STDERR, json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}');
});

$_GET  = ['tab' => '', 'step' => '1', 'lens' => 'section', 'm' => '', 'cls' => '0'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/dashboard/' . $name . '/';
$_SERVER['HTTP_HOST']      = 'example.test';

/*
 * كل سطح يُمرّر شكلًا مختلفًا من `$sch_data` — يُقلَّد هنا كما في
 * `SCH_*::render()` تمامًا، وإلا بدت القوالب ساقطةً وهي سليمة.
 */
$sch_data = ['section' => $name, 'id' => $id, 'error' => '', 'areas' => []];

if (str_contains($dir, '/student/')) {
    $sch_data['student'] = SCH_Student::me();
} elseif (str_contains($dir, '/driver/')) {
    $sch_data['trip'] = SCH_Trips::active_for_driver(get_current_user_id());
} elseif (str_contains($dir, '/app/')) {
    $sch_data['children'] = SCH_Students::children_of(get_current_user_id());
}

/*
 * شاشةٌ يضمن موجّهها كيانًا، وقاعدةٌ فارغة لا تُعطيه.
 *
 * `SCH_Student::run()` تُصيّر شاشة الدخول قبل أن تبلغ القالب حين لا سجلّ
 * طالبٍ للحساب، و`SCH_Driver::run()` تُصيّر «home» حين لا رحلة جارية. فلا
 * قالبَ من هذين يُصيَّر بـ`null` **عند مستخدمٍ حقيقيّ أبدًا**.
 *
 * وإعلانها «ساقطة» على قاعدةٍ فارغة كذبٌ على الفاحص: هو يفحص فرعًا لا
 * يبلغه أحد، ويُبقي البناء أحمر في كل دفعة حتى يُهمَل الفحص كلّه. فتُعلَّم
 * «متعذّرة» ويُقال لماذا — والوضع المملوء يفحصها كاملةً.
 */
$sch_needs = [
    // كل شاشات الطالب: `run()` تُصيّر «login» قبلها إن لم يُوجد سجلّ.
    ['/student/', '',     'student', 'لا سجلّ طالب — والموجّه يُصيّر شاشة الدخول قبل القالب'],
    // شاشة الرحلة وحدها: بقيّة شاشات السائق تُصيَّر برحلةٍ أو بلا رحلة.
    ['/driver/',  'trip', 'trip',    'لا رحلة جارية — والموجّه يُصيّر «home» بدلها'],
];

foreach ($sch_needs as [$sch_seg, $sch_only, $sch_key, $sch_why]) {
    if (!str_contains($dir, $sch_seg) || ($sch_only !== '' && $name !== $sch_only)) {
        continue;
    }

    if (array_key_exists($sch_key, $sch_data) && $sch_data[$sch_key] === null) {
        // النتيجة يكتبها مُغلِق العمليّة وحده — كتابتان تُنتجان JSON مزدوجًا
        // لا يُحلَّل، فتُقرأ الشاشة «ماتت بلا نتيجة».
        $result['skip'] = $sch_why;
        exit(0);
    }
}

$sch_view = $name;
$sch_id   = $id;

ob_start();
try {
    include SCH_PATH . $dir . $name . '.php';
} catch (Throwable $e) {
    $result['fatal'] = get_class($e) . ': ' . $e->getMessage()
                     . ' @ ' . str_replace(SCH_PATH, '', $e->getFile()) . ':' . $e->getLine();
}
