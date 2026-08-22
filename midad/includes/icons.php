<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أيقونات مرسومةٌ في الصفحة — لا خطّ أيقونات ولا ملفّ يُطلَب.
 *
 * خطّ الأيقونات يعني طلبًا إضافيًّا يحجب الرسم، ومربّعاتٍ فارغة حتى يصل.
 * والمرسومة هنا تأخذ لون نصّها بـ`currentColor` — فتصحّ في الوضعين بلا
 * نسخةٍ ثانية.
 */
function mdd_icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = null;

    $paths ??= [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'folder'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'book'     => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v18H5.5A1.5 1.5 0 0 1 4 19.5Z"/><path d="M8 3v18"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'table'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M9 9.5V20"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M17.5 14.8c2.1.7 3.5 2.5 3.5 5.2"/>',
        'user'     => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c0-3.6 3.4-6 7.5-6s7.5 2.4 7.5 6"/>',
        'wallet'   => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a2 2 0 0 1 2 2v1"/><path d="M3 7.5V17a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6H6a3 3 0 0 1-3-3.5Z"/><circle cx="16.5" cy="14" r="1.1"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'logout'   => '<path d="M14 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8"/><path d="M17 8.5 20.5 12 17 15.5M20 12H9"/>',
        'check'    => '<path d="m4.5 12.5 5 5 10-11"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'alert'    => '<path d="M12 3.5 22 20H2Z"/><path d="M12 10v4.5M12 17.2v.3"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.4 2"/>',
        'shield'   => '<path d="M12 3l7.5 3v6c0 4.6-3.1 8.1-7.5 9.5C7.6 20.1 4.5 16.6 4.5 12V6Z"/>',
        'sparkle'  => '<path d="M12 3.5 13.9 9l5.6 1.9-5.6 1.9L12 18.5 10.1 12.8 4.5 10.9 10.1 9Z"/><path d="M18.5 4v3M20 5.5h-3"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'phone'    => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
        'lock'     => '<rect x="4.5" y="10" width="15" height="11" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'school'   => '<path d="M12 3 2.5 8 12 13l9.5-5Z"/><path d="M6 10.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.5"/>',
        'download' => '<path d="M12 3.5v11M7.5 10.5 12 15l4.5-4.5"/><path d="M4 18.5v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/>',
        'file'     => '<path d="M6 3h7l5 5v13H6Z"/><path d="M13 3v5h5"/>',
        'edit'     => '<path d="M4 20h4L20 8l-4-4L4 16Z"/><path d="m14.5 5.5 4 4"/>',
        'trash'    => '<path d="M4.5 6.5h15M9.5 6.5V4h5v2.5"/><path d="M6.5 6.5 7.5 21h9l1-14.5"/>',
        'star'     => '<path d="m12 3.5 2.7 5.7 6.3.9-4.5 4.4 1 6.2L12 17.8 6.5 20.7l1-6.2L3 10.1l6.3-.9Z"/>',
        'left'     => '<path d="M14.5 5.5 8 12l6.5 6.5"/>',
        'right'    => '<path d="M9.5 5.5 16 12l-6.5 6.5"/>',
        'down'     => '<path d="M5.5 9.5 12 16l6.5-6.5"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M3.5 3.5l1.8 1.8M18.7 18.7l1.8 1.8M2 12h2.5M19.5 12H22M3.5 20.5l1.8-1.8M18.7 5.3l1.8-1.8"/>',
        'moon'     => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'pen'      => '<path d="M3 21l1.2-4.2L15.5 5.5a2.1 2.1 0 0 1 3 3L7.2 19.8Z"/><path d="m13.5 7.5 3 3"/>',
        'seal'     => '<circle cx="12" cy="10" r="6"/><path d="m8.5 15.5-1 6L12 19l4.5 2.5-1-6"/>',
    ];

    $d = $paths[$name] ?? '<circle cx="12" cy="12" r="4"/>';

    return sprintf(
        '<svg class="mdd-i%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        $class !== '' ? ' ' . esc_attr($class) : '',
        $size,
        $size,
        $d
    );
}
