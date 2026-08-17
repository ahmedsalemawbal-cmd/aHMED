<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_user     = wp_get_current_user();
$sch_trip     = $sch_data['trip'] ?? null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2A251E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php esc_attr_e('الباص', 'school-system'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-driver-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_Driver::url('manifest.webmanifest')); ?>">

    <title><?php esc_html_e('باص المدرسة', 'school-system'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/driver.css')); ?>">
</head>
<body class="schd<?php echo $sch_is_login ? ' schd--auth' : ''; ?>">

<?php if (!$sch_is_login) : ?>
    <header class="schd-top">
        <span class="schd-top__who"><?php echo esc_html($sch_user->display_name); ?></span>
        <a class="schd-top__out" href="<?php echo esc_url(SCH_Driver::url('logout')); ?>"><?php esc_html_e('خروج', 'school-system'); ?></a>
    </header>
<?php endif; ?>

<main class="schd-main">
    <?php require $file; ?>
</main>

<?php if (!$sch_is_login) : ?>
    <?php
    $sch_section = sanitize_key(str_replace('.js', '', (string) get_query_var('sch_drv_section')));
    $sch_nav = [
        ''        => [__('الرحلة', 'school-system'), 'bus'],
        'stops'   => [__('المسار', 'school-system'), 'route'],
        'history' => [__('اليوم', 'school-system'), 'clock'],
        'issue'   => [__('بلاغ', 'school-system'), 'tool'],
        'bus'     => [__('باصي', 'school-system'), 'grid'],
    ];
    ?>
    <nav class="schd-tabs">
        <?php foreach ($sch_nav as $slug => $item) : ?>
            <a class="schd-tabs__item<?php echo $slug === $sch_section ? ' is-active' : ''; ?>"
               href="<?php echo esc_url(SCH_Driver::url($slug)); ?>">
                <?php echo sch_icon($item[1], 20); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <span><?php echo esc_html($item[0]); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($sch_trip) : ?>
<script>
window.SCH_DRIVER = <?php echo wp_json_encode([
    'api'    => esc_url_raw(rest_url(SCH_API_NS . '/')),
    'nonce'  => wp_create_nonce('wp_rest'),
    'tripId' => (int) $sch_trip->id,
    'home'   => SCH_Driver::url(),
    'i18n'   => [
        'board'    => __('صعد', 'school-system'),
        'alight'   => __('نزل', 'school-system'),
        'absent'   => __('غائب', 'school-system'),
        'pending'  => __('بانتظار', 'school-system'),
        'queued'   => __('محفوظ — سيُرسل عند عودة الشبكة', 'school-system'),
        'synced'   => __('تمت المزامنة', 'school-system'),
        'offline'  => __('لا يوجد اتصال', 'school-system'),
        'gpsOn'    => __('يُرسل الموقع', 'school-system'),
        'gpsOff'   => __('الموقع متوقف', 'school-system'),
        'gpsDenied'=> __('صلاحية الموقع مرفوضة', 'school-system'),
        'confirm'  => __('إنهاء الرحلة؟', 'school-system'),
        'receiver' => __('من استلم الطالب؟', 'school-system'),
        'blocked'  => __('لا يمكن الإنهاء قبل تسجيل نزول كل من صعد.', 'school-system'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo esc_url(sch_asset('assets/field-core.js')); ?>" defer></script>
<script src="<?php echo esc_url(sch_asset('assets/driver.js')); ?>" defer></script>
<?php endif; ?>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?php echo wp_json_encode(SCH_Driver::url('sw.js')); ?>);
  });
}
</script>

<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
