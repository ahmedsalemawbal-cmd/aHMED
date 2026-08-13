<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="scha-auth">
    <div class="scha-auth__brand">
        <span class="scha-auth__mark"></span>
        <h1><?php esc_html_e('فصلي', 'school-system'); ?></h1>
        <p><?php esc_html_e('الحضور والملاحظات من داخل الفصل', 'school-system'); ?></p>
    </div>

    <?php if ($sch_error !== '') : ?>
        <div class="scha-alert scha-alert--error"><?php echo esc_html($sch_error); ?></div>
    <?php endif; ?>

    <form method="post" class="scha-form">
        <?php wp_nonce_field('sch_tch_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_tch_login" value="1">

        <label class="scha-field-label" for="t-user"><?php esc_html_e('اسم المستخدم', 'school-system'); ?></label>
        <input class="scha-input" id="t-user" type="text" name="username" dir="ltr" autocomplete="username" required autofocus>

        <label class="scha-field-label" for="t-pass"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
        <input class="scha-input" id="t-pass" type="password" name="password" autocomplete="current-password" required>

        <button class="scha-btn" type="submit"><?php esc_html_e('دخول', 'school-system'); ?></button>
    </form>
</div>
