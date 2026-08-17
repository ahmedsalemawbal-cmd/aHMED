<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="schg-auth">
    <div class="schg-auth__brand">
        <span class="schg-auth__mark"></span>
        <h1><?php esc_html_e('بوابة المدرسة', 'school-system'); ?></h1>
        <p><?php esc_html_e('تسجيل دخول وخروج الطلاب', 'school-system'); ?></p>
    </div>

    <?php if ($sch_error !== '') : ?>
        <div class="schg-alert"><?php echo esc_html($sch_error); ?></div>
    <?php endif; ?>

    <form method="post" class="schg-form">
        <?php wp_nonce_field('sch_gate_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_gate_login" value="1">

        <label class="schg-label" for="g-user"><?php esc_html_e('رقم الجوال أو اسم المستخدم', 'school-system'); ?></label>
        <input class="schg-input" id="g-user" type="text" name="username" dir="ltr" autocomplete="username" required autofocus>

        <label class="schg-label" for="g-pass"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
        <input class="schg-input" id="g-pass" type="password" name="password" autocomplete="current-password" required>

        <button class="schg-btn" type="submit"><?php esc_html_e('دخول', 'school-system'); ?></button>
    </form>
</div>
