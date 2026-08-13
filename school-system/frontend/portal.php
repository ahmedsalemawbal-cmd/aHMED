<?php
/**
 * شاشة الدخول الموحّدة.
 *
 * باب واحد للجميع: يكتب المستخدم ما يحفظه — جواله أو رقم ابنه الأكاديمي
 * أو بريده — ويأخذه النظام حيث ينتمي بلا أن يختار تطبيقًا.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$err    = (string) ($sch_data['error'] ?? '');
$school = sch_settings('school_name', get_bloginfo('name'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="robots" content="noindex, nofollow">
    <?php /* منع وميض السمة على بوابة الدخول — تتبع الجهاز/الاختيار المحفوظ */ ?>
    <script>(function(){try{var s=localStorage.getItem('sch-theme');var t=s==='dark'?'dark':'light';document.documentElement.setAttribute('data-theme',t);var m=document.querySelector('meta[name=theme-color]');if(m){m.content=t==='dark'?'#0C0A16':'#FFFFFF';}}catch(e){}})();</script>
    <title><?php echo esc_html($school); ?></title>

    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-192.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/parent.css')); ?>">
</head>

<body class="p-body p-body--auth">

<div class="p-auth">
    <div class="p-auth__mark"><?php echo esc_html(mb_substr($school, 0, 2)); ?></div>
    <h1><?php echo esc_html($school); ?></h1>
    <p class="p-auth__s"><?php esc_html_e('بوابة الدخول', 'school-system'); ?></p>

    <div class="p-auth__box">
        <?php if ($err !== '') : ?>
            <div class="p-auth__err"><?php echo esc_html($err); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('sch_portal_login', '_sch_nonce'); ?>
            <input type="hidden" name="sch_portal_login" value="1">

            <div class="p-field">
                <label for="pt-u"><?php esc_html_e('رقم الجوال أو الرقم الأكاديمي', 'school-system'); ?></label>
                <input class="p-in" id="pt-u" type="text" name="username" dir="ltr"
                       inputmode="numeric" autocomplete="username" required autofocus>
            </div>

            <div class="p-field">
                <label for="pt-p"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
                <input class="p-in" id="pt-p" type="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button class="p-btn p-btn--brand p-tap"><?php esc_html_e('دخول', 'school-system'); ?></button>
        </form>

        <!-- من يدخل يُعرَف تلقائيًا: لا يختار تطبيقًا ولا يحفظ رابطًا -->
        <div class="p-who4">
            <?php foreach ([
                __('ولي أمر', 'school-system'),
                __('طالب', 'school-system'),
                __('معلم', 'school-system'),
                __('سائق', 'school-system'),
                __('حارس', 'school-system'),
            ] as $sch_who) : ?>
                <span><?php echo esc_html($sch_who); ?></span>
            <?php endforeach; ?>
        </div>

        <p class="p-auth__hint">
            <?php esc_html_e('بياناتك تصلك من إدارة المدرسة. وولي الأمر يدخل بجواله، والطالب برقمه الأكاديمي.', 'school-system'); ?>
        </p>
    </div>
</div>

</body>
</html>
