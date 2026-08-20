<?php
/**
 * يُصيّر شاشة داشبورد حقيقية بلا خادم، ليُقارَن مخرَجها بتصميمها.
 *
 * القشرة (الشريط الجانبي والمسار العلوي) لا تُحاكى: المقارنة على
 * **الشاشة نفسها**، وقشرةٌ مُقلَّدة تُدخل فروقًا ليست من الشاشة.
 * والورقتان تُحمَّلان كما تُحمَّلان في `layout.php` وبترتيبهما — فأي
 * تصادم بين الملفّين يظهر هنا كما يظهر هناك.
 */

declare(strict_types=1);

require __DIR__ . '/stub.php';

$SCREEN = $argv[1] ?? 'attendance';
$VIEW   = SCH_PATH . 'frontend/views/' . $SCREEN . '.php';

if (!is_file($VIEW)) {
    fwrite(STDERR, "لا توجد شاشة باسم {$SCREEN}\n");
    exit(1);
}

$sch_data = ['section' => $SCREEN];
$sch_view = $SCREEN;

ob_start();
try {
    include $VIEW;
} catch (Throwable $e) {
    echo '<pre style="color:#b3332c;padding:20px;font:13px monospace;white-space:pre-wrap">'
       . esc_html($SCREEN . '.php — ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine())
       . '</pre>';
}
$body = (string) ob_get_clean();

$theme = getenv('THEME') === 'dark' ? 'dark' : 'light';

echo '<!doctype html><html lang="ar" dir="rtl" data-theme="' . $theme . '"><head><meta charset="utf-8">'
    . '<title>' . esc_html($SCREEN) . '</title>'
    . '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">'
    . '<style>' . file_get_contents(SCH_PATH . 'assets/shared-ui.css') . '</style>'
    . '<style>' . file_get_contents(SCH_PATH . 'assets/dashboard.css') . '</style>'
    . '<style>body{margin:0}.sch-content{padding:26px 32px 40px}</style>'
    . '</head><body class="sch-body">'
    . '<main class="sch-content" id="sch-main-content">' . $body . '</main>'
    . '</body></html>';
