<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_user     = wp_get_current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F1720">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php esc_attr_e('البوابة', 'school-system'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-gate-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_Gate::url('manifest.webmanifest')); ?>">

    <title><?php esc_html_e('بوابة المدرسة', 'school-system'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/gate.css')); ?>">
</head>
<body class="schg<?php echo $sch_is_login ? ' schg--auth' : ''; ?>">

<?php if (!$sch_is_login) : ?>
    <header class="schg-top">
        <span class="schg-top__who"><?php echo esc_html($sch_user->display_name); ?></span>
        <span class="schg-top__acts">
            <a class="schg-top__link<?php echo $sch_view === 'visitors' ? ' is-on' : ''; ?>"
               href="<?php echo esc_url(SCH_Gate::url($sch_view === 'visitors' ? '' : 'visitors')); ?>">
                <?php echo $sch_view === 'visitors'
                    ? esc_html__('المسح', 'school-system')
                    : esc_html__('الزوّار', 'school-system'); ?>
            </a>
            <a class="schg-top__out" href="<?php echo esc_url(SCH_Gate::url('logout')); ?>"><?php esc_html_e('خروج', 'school-system'); ?></a>
        </span>
    </header>
<?php endif; ?>

<main class="schg-main">
    <?php require $file; ?>
</main>

<?php if (!$sch_is_login) : ?>
<script>
window.SCH_GATE = <?php echo wp_json_encode([
    'api'         => esc_url_raw(rest_url(SCH_API_NS . '/')),
    'nonce'       => wp_create_nonce('wp_rest'),
    'defaultMode' => SCH_Gate::default_mode(),
    'photoBase'   => esc_url_raw(SCH_Dashboard::url('students')),
    'i18n'        => [
        'offline'   => __('لا يوجد اتصال', 'school-system'),
        'queued'    => __('محفوظ — سيُرسل عند عودة الشبكة', 'school-system'),
        'synced'    => __('متصل', 'school-system'),
        'notFound'  => __('البطاقة غير معروفة', 'school-system'),
        'scanning'  => __('وجّه الكاميرا نحو الباركود', 'school-system'),
        'noCamera'  => __('الكاميرا غير متاحة — استخدم البحث بالاسم', 'school-system'),
        'receiver'  => __('من استلم الطالب؟', 'school-system'),
        'reason'    => __('سبب الخروج المبكر', 'school-system'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo esc_url(sch_asset('assets/field-core.js')); ?>" defer></script>
<script src="<?php echo esc_url(sch_asset('assets/gate.js')); ?>" defer></script>
<?php endif; ?>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?php echo wp_json_encode(SCH_Gate::url('sw.js')); ?>);
  });
}
</script>

<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
