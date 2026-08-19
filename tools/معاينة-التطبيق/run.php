<?php
/**
 * يُصيّر كل شاشة من شاشات التطبيق داخل إطار جوّال، ويكتب صفحة واحدة.
 *
 * القوالب تُشغَّل كما هي — بأسمائها ومساراتها — فما يظهر هنا هو ما
 * سيراه وليّ الأمر، لا نسخة منه.
 */

declare(strict_types=1);

require __DIR__ . '/stub.php';

$APP  = SCH_PATH . 'frontend/app/';
$CSS  = SCH_PATH . 'assets/parent.css';

/** [الملف, العنوان, معرّف الطفل, متغيّرات بيئة] */
$SCREENS = [
    ['child',        'اليوم — في المدرسة',   1, []],
    ['child',        'اليوم — في الباص',     2, []],
    ['child',        'اليوم — نداء منتظر',   1, ['DEMO_CALL' => 'open']],
    ['child',        'اليوم — المشرفة قادمة', 1, ['DEMO_CALL' => 'onway']],
    ['log',          'السجل',                1, []],
    ['invoices',     'الرسوم',               1, []],
    ['card',         'البطاقة',              1, []],
    ['alerts',       'الإشعارات',            1, []],
    ['messages',     'الرسائل',              1, []],
    ['track',        'أين ابني الآن',        2, []],
    ['transport',    'النقل',                1, []],
    ['schedule',     'الجدول الدراسي',       1, []],
    ['clinic',       'الصحة المدرسية',       1, []],
    ['certificates', 'الشهادات',             1, []],
    ['kg',           'التقرير اليومي',       3, []],
    ['account',      'حسابي',                1, []],
    ['pickers',      'من يحقّ له الاستلام',   1, []],
];

function render_screen(string $file, int $kid, array $env): string
{
    global $APP;

    foreach ($env as $k => $v) { putenv("$k=$v"); }

    $sch_data = ['id' => $kid];
    $sch_view = $file;
    $p_kid    = demo_student($kid);
    $p_kid_id = $kid;
    $p_view   = $file;

    ob_start();
    try {
        include $APP . $file . '.php';
    } catch (Throwable $e) {
        echo '<pre style="color:#b3332c;padding:16px;font:12px monospace;white-space:pre-wrap">'
           . esc_html($file . '.php — ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine())
           . '</pre>';
    }
    $html = (string) ob_get_clean();

    foreach ($env as $k => $_) { putenv($k); }

    return $html;
}

// ── القشرة: تُؤخذ من layout.php الحقيقي بقدر ما يمكن ────────────────

function shell(string $body, int $kid, string $view): string
{
    $kids = '';
    foreach (KIDS as $id => $k) {
        $on   = $id === $kid;
        $tone = ['at_school' => 'ok', 'on_bus' => 'warn', 'home' => 'slate'][$k['custody_state']] ?? 'slate';
        $kids .= '<a class="p-kid p-kid--' . $id . ($on ? ' is-on' : '') . '" href="#">'
              . '<span class="p-kid__pic">' . sch_avatar_svg(mb_substr($k['first_name'], 0, 1), $on ? 58 : 46) . '</span>'
              . '<span class="p-kid__n"><i class="p-kid__s is-' . $tone . '"></i>' . esc_html($k['first_name']) . '</span></a>';
    }

    $tabs = '';
    foreach (['child' => ['اليوم', 'home'], 'log' => ['السجل', 'list'],
              'invoices' => ['الرسوم', 'wallet'], 'card' => ['البطاقة', 'badge']] as $slug => [$label, $icon]) {
        $tabs .= '<a class="p-tab' . ($view === $slug ? ' is-on' : '') . '" href="#">'
              . sch_icon($icon, 19) . '<span>' . esc_html($label) . '</span></a>';
    }

    return '<div class="p-app">'
        . '<header class="p-top">'
        . '<a class="p-who" href="#"><span class="p-who__pic">' . sch_avatar_svg('أ', 42) . '</span>'
        . '<span class="p-who__t"><b>مساء الخير، أحمد</b><em>' . esc_html(SCH_App::day_label()) . ' ٢٤ صفر · ١٩ أغسطس</em></span></a>'
        . '<span class="p-icons">'
        . '<a class="p-ico" href="#">' . sch_icon('chat', 18) . '</a>'
        . '<a class="p-ico" href="#">' . sch_icon('bell', 18) . '<i class="p-ico__dot"></i></a>'
        . '</span></header>'
        /* الدوّار لا يظهر إلا في شاشة تخصّ ابنًا — كما في layout.php */
        . (in_array($view, ['account', 'pickers', 'alerts', 'messages'], true) ? '' : '<nav class="p-kids">' . $kids . '</nav>')
        . '<main class="p-main">' . $body . '</main>'
        . '<nav class="p-tabs">' . $tabs . '</nav>'
        . '</div>';
}

// ── الصفحة ──────────────────────────────────────────────────────────

$css = file_get_contents($CSS);
$out = [];

foreach ($SCREENS as $i => [$file, $title, $kid, $env]) {
    $body  = render_screen($file, $kid, $env);
    $inner = in_array($file, ['login', 'install', 'denied'], true) ? $body : shell($body, $kid, $file);

    $out[] = '<figure class="fr"><figcaption>' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)
        . ' · ' . esc_html($title) . '</figcaption>'
        . '<div class="ph" data-shot="' . $i . '"><div class="notch"></div>'
        . '<div class="sb"><span>٩:٤١</span><span class="sbi"><i></i><i></i></span></div>'
        . '<div class="scr">' . $inner . '</div></div></figure>';
}

$theme = getenv('THEME') === 'dark' ? ' data-theme="dark"' : '';
$theme .= ' class="p-body"';

echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
    . '<title>أُصول — تطبيق وليّ الأمر</title>'
    . '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">'
    . '<style>' . $css . '</style>'
    . '<style>
      body{margin:0;background:#e7e9f6;font-family:Cairo,sans-serif;padding:28px;
           display:flex;flex-wrap:wrap;gap:34px;align-items:flex-start;justify-content:center}
      body[data-theme="dark"]{background:#0b0c14}
      .fr{margin:0;display:flex;flex-direction:column;gap:9px;align-items:center}
      figcaption{font:700 12px Cairo,sans-serif;color:#585c78;order:-1}
      body[data-theme="dark"] figcaption{color:#a7abc4}
      .ph{width:393px;height:852px;border-radius:44px;overflow:hidden;position:relative;
          background:var(--bg,#f3f4fb);box-shadow:0 0 0 9px #14151d,0 22px 50px rgba(20,22,50,.22)}
      body[data-theme="dark"] .ph{box-shadow:0 0 0 9px #000,0 22px 50px rgba(0,0,0,.5)}
      .notch{position:absolute;top:11px;left:50%;transform:translateX(-50%);width:116px;height:32px;
             border-radius:18px;background:#0d0e16;z-index:9}
      .sb{padding:17px 30px 2px;font:700 14px Cairo,sans-serif;color:var(--ink,#191b2e);
          display:flex;justify-content:space-between;align-items:center;position:relative;z-index:8}
      .sb span:first-child{direction:ltr}
      .sbi{display:flex;gap:6px;align-items:center}
      .sbi i:first-child{width:17px;height:10px;border-radius:2px;background:currentColor;opacity:.85}
      .sbi i:last-child{width:16px;height:11px;border:1.6px solid currentColor;border-radius:3px;opacity:.85}
      .scr{height:calc(852px - 40px);overflow:auto;position:relative}
      .scr::-webkit-scrollbar{width:0}
      </style></head><body' . $theme . '>' . implode('', $out) . '</body></html>';
