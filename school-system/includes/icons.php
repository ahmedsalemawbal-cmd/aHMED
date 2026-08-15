<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أيقونات SVG مضمّنة — خطية، بسمك موحّد، وترث لون النص.
 * لا مكتبة خارجية ولا ملفات صور: أخف وأسرع وقابلة للتلوين.
 */
function sch_icon(string $name, int $size = 18): string
{
    $paths = [
        'image'     => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="2"/><path d="m4 17 4.5-4.5 3.5 3.5 3-3L20 17"/>',
        'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'download' => '<path d="M12 3.5v11M7.5 10.5 12 15l4.5-4.5"/><path d="M4.5 17.5v2a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-2"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'chev'     => '<path d="m14 6-6 6 6 6"/>',
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.5a3 3 0 0 1 0 5.6"/><path d="M17.5 14.8c2 .7 3.5 2.5 3.5 5.2"/>',
        'user'     => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
        'grid'     => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'check'    => '<path d="M20 6.5 9.5 17 4 11.5"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'award'    => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M5 5l1.8 1.8M17.2 17.2 19 19M19 5l-1.8 1.8M6.8 17.2 5 19"/>',
        'heart'    => '<path d="M12 20s-7.5-4.7-7.5-9.5A4.2 4.2 0 0 1 12 8a4.2 4.2 0 0 1 7.5 2.5C19.5 15.3 12 20 12 20z"/>',
        'book'     => '<path d="M4 4.5h11a2.5 2.5 0 0 1 2.5 2.5v13H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M17.5 7H20v13H6.5"/>',
        'mail'     => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'bus'      => '<rect x="3.5" y="4" width="17" height="12" rx="2.5"/><path d="M3.5 10.5h17M7 16v2.5M17 16v2.5"/><circle cx="7.5" cy="13.2" r="1"/><circle cx="16.5" cy="13.2" r="1"/>',
        'route'    => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M6 8.5v5a4 4 0 0 0 4 4h5.5"/>',
        'invoice'  => '<path d="M6 3.5h9L19 8v12.5H6z"/><path d="M14 3.5V8h4.5M9 12h6M9 16h4"/>',
        'list'     => '<path d="M8 6.5h12M8 12h12M8 17.5h12M4 6.5h.01M4 12h.01M4 17.5h.01"/>',
        'ledger'   => '<rect x="4" y="3.5" width="16" height="17" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'chart'    => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-4M12.5 16V8M17 16v-6"/>',
        'badge'    => '<rect x="4.5" y="4" width="15" height="16" rx="2.5"/><circle cx="12" cy="10" r="2.5"/><path d="M8 17c.7-1.8 2.2-2.8 4-2.8s3.3 1 4 2.8"/>',
        'wallet'   => '<rect x="3.5" y="6" width="17" height="12.5" rx="2.5"/><path d="M3.5 10h17"/><circle cx="16.5" cy="14" r="1.1"/>',
        'tool'     => '<path d="M14.5 6.5a4 4 0 0 0 5 5l-9 9a2.5 2.5 0 0 1-3.5-3.5z"/><path d="M14.5 6.5 17 4"/>',
        'box'      => '<path d="M12 3 4 7v10l8 4 8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/>',
        'door'     => '<path d="M6 3.5h9V21H6z"/><path d="M3.5 21h17"/><circle cx="12.5" cy="12.5" r=".9"/>',
        'shield'   => '<path d="M12 3.5 20 6v6c0 4.6-3.3 7.7-8 9-4.7-1.3-8-4.4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'upload'   => '<path d="M12 16V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M4 16v2.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V16"/>',
        'cog'      => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'bell'     => '<path d="M6 9.5a6 6 0 0 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'activity' => '<path d="M3 12h4l2.5-7 5 14 2.5-7H21"/>',
        'pen'      => '<path d="m15.5 5 3.5 3.5"/><path d="M4 20l1.2-4.2L16 5a2 2 0 0 1 3 3L8.2 18.8z"/>',
        'clipboard'=> '<path d="M9 4.5H7.5A2 2 0 0 0 5.5 6.5V19a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V6.5a2 2 0 0 0-2-2H15"/><rect x="9" y="3" width="6" height="3.6" rx="1"/><path d="M9 12h6M9 15.5h4"/>',
        'pill'     => '<rect x="3.5" y="9" width="17" height="6" rx="3"/><path d="M12 9v6"/>',
        'leaf'     => '<path d="M6 18C6 9.5 12 5.5 19 5.5c0 8.5-5 12.5-12 12.5z"/><path d="M6 18c2.5-3.8 5.5-6 9.5-7"/>',
        'play'     => '<circle cx="12" cy="12" r="8.5"/><path d="M10.2 8.5v7l5.5-3.5z"/>',
        'user-check'=> '<circle cx="9.5" cy="8" r="3.2"/><path d="M3.5 20c0-3.3 2.7-5.5 6-5.5 1.3 0 2.5.3 3.5.9"/><path d="m15.5 15 2 2 3.5-3.5"/>',
        'flag'     => '<path d="M6 21V4"/><path d="M6 4.5h10.5l-2 3.2 2 3.2H6"/>',
        'lock'     => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><circle cx="12" cy="15.2" r="1.2"/>',
        'dot'      => '<circle cx="12" cy="12" r="3"/>',
    ];

    $body = $paths[$name] ?? $paths['dot'];

    return sprintf(
        '<svg class="sch-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">%2$s</svg>',
        $size,
        $body
    );
}

/**
 * صورة رمزية بالحرف الأول — بديل نظيف حين لا توجد صورة.
 * أفضل من أيقونة شخص عامة: تميّز الأبناء عن بعضهم في الدوّار.
 */
function sch_avatar_svg(string $letter, int $size = 62): string
{
    $letter = trim($letter) !== '' ? mb_substr(trim($letter), 0, 1) : '؟';

    // الألوان تُشتق من السمة عبر متغيّرات CSS، فتتبع هوية كل تطبيق
    // (بنفسجي أميثيست في تطبيق ولي الأمر). القيم الافتراضية محايدة.
    return sprintf(
        '<svg viewBox="0 0 64 64" width="%1$d" height="%1$d" role="img" aria-hidden="true">'
        . '<rect width="64" height="64" fill="var(--sch-av-bg,#E8E8F0)"/>'
        . '<text x="32" y="43" text-anchor="middle" font-family="inherit" '
        . 'font-size="30" font-weight="700" fill="var(--sch-av-ink,#6C6A85)" opacity=".85">%2$s</text></svg>',
        $size,
        esc_html($letter)
    );
}
