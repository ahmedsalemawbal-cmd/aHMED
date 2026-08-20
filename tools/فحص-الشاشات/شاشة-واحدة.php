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

$result = ['fatal' => '', 'bytes' => 0, 'notes' => []];

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

$sch_view = $name;
$sch_id   = $id;

ob_start();
try {
    include SCH_PATH . $dir . $name . '.php';
} catch (Throwable $e) {
    $result['fatal'] = get_class($e) . ': ' . $e->getMessage()
                     . ' @ ' . str_replace(SCH_PATH, '', $e->getFile()) . ':' . $e->getLine();
}
