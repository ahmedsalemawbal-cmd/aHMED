<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="schs-auth">
    <div class="schs-auth__brand">
        <span class="schs-auth__mark"></span>
        <h1><?php esc_html_e('تعلّم', 'school-system'); ?></h1>
        <p><?php esc_html_e('واجباتك ومكتبتك وألعابك', 'school-system'); ?></p>
    </div>

    <?php if ($sch_error !== '') : ?>
        <div class="schs-flash schs-flash--bad"><?php echo esc_html($sch_error); ?></div>
    <?php endif; ?>

    <form method="post" class="schs-form">
        <?php wp_nonce_field('sch_stu_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_stu_login" value="1">

        <label class="schs-field-label" for="s-user"><?php esc_html_e('اسم المستخدم', 'school-system'); ?></label>
        <input class="schs-input" id="s-user" type="text" name="username" dir="ltr" autocomplete="username" required autofocus>

        <label class="schs-field-label" for="s-pass"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
        <input class="schs-input" id="s-pass" type="password" name="password" autocomplete="current-password" required>

        <button class="schs-btn" type="submit"><?php esc_html_e('دخول', 'school-system'); ?></button>
    </form>

    <p class="schs-auth__note"><?php esc_html_e('اسم المستخدم يبدأ بحرف s متبوعًا برقمك الأكاديمي.', 'school-system'); ?></p>
</div>
