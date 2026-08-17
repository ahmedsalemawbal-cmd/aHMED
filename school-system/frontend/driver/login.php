<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="schd-auth">
    <div class="schd-auth__brand">
        <span class="schd-auth__mark"></span>
        <h1><?php esc_html_e('باص المدرسة', 'school-system'); ?></h1>
        <p><?php esc_html_e('تسجيل صعود ونزول الطلاب وبثّ موقع الباص.', 'school-system'); ?></p>
    </div>

    <?php if ($sch_error !== '') : ?>
        <div class="schd-alert"><?php echo esc_html($sch_error); ?></div>
    <?php endif; ?>

    <form method="post" class="schd-form">
        <?php wp_nonce_field('sch_drv_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_drv_login" value="1">

        <label class="schd-label" for="d-user"><?php esc_html_e('رقم الجوال أو اسم المستخدم', 'school-system'); ?></label>
        <input class="schd-input" id="d-user" type="text" name="username" dir="ltr"
               autocomplete="username" required autofocus>

        <label class="schd-label" for="d-pass"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
        <input class="schd-input" id="d-pass" type="password" name="password"
               autocomplete="current-password" required>

        <button class="schd-btn" type="submit"><?php esc_html_e('دخول', 'school-system'); ?></button>
    </form>
</div>
