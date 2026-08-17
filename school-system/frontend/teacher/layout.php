<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_user     = wp_get_current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php esc_attr_e('فصلي', 'school-system'); ?>">
    <?php /* منع وميض السمة — تُضبط قبل أول رسم، وتتبع الجهاز افتراضيًا مع خيار التبديل. */ ?>
    <script>(function(){try{var s=localStorage.getItem('sch-theme');var t=s==='dark'?'dark':'light';document.documentElement.setAttribute('data-theme',t);var m=document.querySelector('meta[name=theme-color]');if(m){m.content=t==='dark'?'#0C0A16':'#FFFFFF';}}catch(e){}})();</script>

    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_Teacher::url('manifest.webmanifest')); ?>">

    <title><?php esc_html_e('فصلي', 'school-system'); ?></title>
    <?php $sch_fav = SCH_Brand::favicon(); ?>
    <?php if ($sch_fav !== '') : ?>
        <link rel="icon" href="<?php echo esc_url($sch_fav); ?>">
        <link rel="apple-touch-icon" href="<?php echo esc_url($sch_fav); ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/teacher.css')); ?>">
</head>
<body class="t-body<?php echo $sch_is_login ? ' t-body--auth' : ''; ?>">
<a class="t-skip" href="#sch-main-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>

<?php if ($sch_is_login) : ?>

    <?php require $file; ?>

<?php else : ?>

    <div class="t-app">
        <?php
        $sch_today_p = SCH_Org::today_periods($sch_user->ID);
        $sch_now     = null;
        $sch_hour    = (int) current_time('G');

        // الحصة الجارية تقريبًا: كل حصة ~50 دقيقة من 7:30.
        $sch_idx = max(0, min(7, (int) floor((($sch_hour * 60 + (int) current_time('i')) - 450) / 50)));
        foreach ($sch_today_p as $sch_p) {
            if ((int) $sch_p->period_no >= $sch_idx + 1) { $sch_now = $sch_p; break; }
        }
        ?>
        <header class="t-top">
            <div class="t-top__row">
                <span class="t-who">
                    <b><?php echo esc_html($sch_user->display_name); ?></b>
                    <span><?php echo esc_html(sprintf(
                        /* translators: %d: عدد الحصص */
                        _n('حصة واحدة اليوم', '%d حصص اليوم', count($sch_today_p), 'school-system'),
                        count($sch_today_p)
                    )); ?></span>
                </span>

                <button type="button" class="t-ico t-theme" aria-label="<?php esc_attr_e('تبديل الوضع الفاتح/الداكن', 'school-system'); ?>">
                    <svg class="t-theme__moon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="t-theme__sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>

                <a class="t-ico" href="<?php echo esc_url(SCH_Dashboard::url()); ?>"
                   title="<?php esc_attr_e('الداشبورد — الدرجات والاختبارات', 'school-system'); ?>"
                   aria-label="<?php esc_attr_e('الداشبورد', 'school-system'); ?>">
                    <?php echo sch_icon('grid', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
            </div>

            <?php if ($sch_now) : ?>
                <div class="t-now">
                    <span class="t-now__no"><?php echo esc_html(number_format_i18n((int) $sch_now->period_no)); ?></span>
                    <span class="t-now__t">
                        <b><?php echo esc_html(trim((string) $sch_now->grade_level . ' / ' . (string) $sch_now->section)
                            . ' · ' . (string) ($sch_now->subject_name ?: '')); ?></b>
                        <span><?php esc_html_e('حصتك القادمة', 'school-system'); ?></span>
                    </span>
                </div>
            <?php endif; ?>
        </header>

        <main class="t-main" id="sch-main-content">
            <?php
            if (isset($_GET['ok'])) {
                echo '<div class="t-alert t-alert--ok">' . esc_html__('حُفظت الملاحظة.', 'school-system') . '</div>';
            }
            if (isset($_GET['err'])) {
                echo '<div class="t-alert t-alert--error">' . esc_html(wp_unslash((string) $_GET['err'])) . '</div>';
            }
            require $file;
            ?>
        </main>
    </div>

    <script>
    /* تبديل السمة بالتفويض — يحفظ الاختيار ويحدّث لون شريط المتصفح */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.t-theme');
        if (!b) { return; }
        var r = document.documentElement;
        var n = r.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        r.setAttribute('data-theme', n);
        try { localStorage.setItem('sch-theme', n); } catch (_) {}
        var m = document.querySelector('meta[name=theme-color]');
        if (m) { m.content = n === 'dark' ? '#0C0A16' : '#FFFFFF'; }
    });

    /* عامل الخدمة كان مبنيًّا ولا يُسجَّل — فالمعلم وحده بلا تثبيت ولا إشعار. */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(<?php echo wp_json_encode(SCH_Teacher::url('sw.js')); ?>).catch(function () {});
        });
    }
    </script>

<?php endif; ?>

<?php SCH_Push::boot(); // الإشعارات الفورية — بعد تسجيل عامل الخدمة ?>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
