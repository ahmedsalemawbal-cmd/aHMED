<?php
/** الدخول برقم الجوال — الأب يتذكر جواله ولا يتذكر بريده. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$err = (string) ($sch_data['error'] ?? '');
?>
<div class="p-auth">
    <div class="p-auth__mark"><?php echo esc_html(mb_substr(sch_settings('school_name', get_bloginfo('name')), 0, 2)); ?></div>
    <h1><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></h1>
    <p class="p-auth__s"><?php esc_html_e('تطبيق ولي الأمر', 'school-system'); ?></p>

    <div class="p-auth__box">
        <?php if ($err !== '') : ?>
            <div class="p-auth__err"><?php echo esc_html($err); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('sch_app_login', '_sch_nonce'); ?>
            <input type="hidden" name="sch_app_login" value="1">

            <div class="p-field">
                <label for="p-u"><?php esc_html_e('رقم الجوال', 'school-system'); ?></label>
                <input class="p-in" id="p-u" type="tel" name="username" dir="ltr"
                       inputmode="numeric" autocomplete="username" required
                       placeholder="05xxxxxxxx">
            </div>

            <div class="p-field">
                <label for="p-p"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
                <input class="p-in" id="p-p" type="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button class="p-btn p-btn--brand p-tap"><?php esc_html_e('دخول', 'school-system'); ?></button>
        </form>

        <p class="p-auth__hint">
            <?php esc_html_e('بياناتك تصلك من إدارة المدرسة عند تسجيل ابنك.', 'school-system'); ?>
        </p>
    </div>
</div>
