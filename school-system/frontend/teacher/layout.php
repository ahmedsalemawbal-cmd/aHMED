<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_user     = wp_get_current_user();
?>
<!DOCTYPE html>
<?php /* تطبيق المعلم داكن الهوية — يُثبَّت ليأخذ مكوّنات shared-ui بنسختها الداكنة المتناسقة */ ?>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F1720">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?php esc_attr_e('فصلي', 'school-system'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_Teacher::url('manifest.webmanifest')); ?>">

    <title><?php esc_html_e('فصلي', 'school-system'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/app.css')); ?>">
</head>
<body class="scha<?php echo $sch_is_login ? ' scha--auth' : ''; ?>">
<a class="sch-skip" href="#sch-main-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>


<?php if (!$sch_is_login) : ?>
    <?php
    $sch_today_p = SCH_Org::today_periods($sch_user->ID);
    $sch_now     = null;
    $sch_hour    = (int) current_time('G');

    // الحصة الجارية تقريبًا: كل حصة ~50 دقيقة من 7:30.
    $sch_idx = max(0, min(7, (int) floor((($sch_hour * 60 + (int) current_time('i')) - 450) / 50)));
    foreach ($sch_today_p as $sch_i => $sch_p) {
        if ((int) $sch_p->period_no >= $sch_idx + 1) { $sch_now = $sch_p; break; }
    }
    ?>
    <header class="sch-hd">
        <div class="sch-hd__top">
            <div class="sch-hd__who">
                <b><?php echo esc_html($sch_user->display_name); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: %d: عدد الحصص */
                    _n('حصة واحدة اليوم', '%d حصص اليوم', count($sch_today_p), 'school-system'),
                    count($sch_today_p)
                )); ?></span>
            </div>
            <a class="sch-hd__act" href="<?php echo esc_url(SCH_Dashboard::url()); ?>"
               title="<?php esc_attr_e('الداشبورد — الدرجات والاختبارات', 'school-system'); ?>">
                <?php echo sch_icon('grid', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </a>
        </div>

        <?php if ($sch_now) : ?>
            <div class="sch-now">
                <span class="sch-now__no"><b><?php echo esc_html(number_format_i18n((int) $sch_now->period_no)); ?></b></span>
                <span class="sch-now__txt">
                    <b><?php echo esc_html(trim((string) $sch_now->grade_level . ' / ' . (string) $sch_now->section)
                        . ' · ' . (string) ($sch_now->subject_name ?: '')); ?></b>
                    <span><?php esc_html_e('حصتك القادمة', 'school-system'); ?></span>
                </span>
            </div>
        <?php endif; ?>
    </header>
<?php endif; ?>

<main class="scha-main" id="sch-main-content">
    <?php
    if (isset($_GET['ok'])) {
        echo '<div class="scha-alert scha-alert--ok">' . esc_html__('حُفظت الملاحظة.', 'school-system') . '</div>';
    }
    if (isset($_GET['err'])) {
        echo '<div class="scha-alert scha-alert--error">' . esc_html(wp_unslash((string) $_GET['err'])) . '</div>';
    }
    require $file;
    ?>
</main>

<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
