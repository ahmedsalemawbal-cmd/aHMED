<?php
/** حساب بلا صلاحية ولي أمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<div class="p-auth">
    <div class="p-auth__mark"><?php echo sch_icon('shield', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
    <h1><?php esc_html_e('لا صلاحية', 'school-system'); ?></h1>
    <p class="p-auth__s"><?php esc_html_e('هذا التطبيق لأولياء الأمور.', 'school-system'); ?></p>

    <div class="p-auth__box">
        <p class="p-auth__hint" style="margin:0 0 14px">
            <?php esc_html_e('حسابك ليس حساب ولي أمر. تواصل مع إدارة المدرسة إن كان هذا خطأ.', 'school-system'); ?>
        </p>
        <a class="p-btn p-btn--quiet p-tap" href="<?php echo esc_url(sch_logout_url(SCH_App::url())); ?>">
            <?php esc_html_e('تسجيل الخروج', 'school-system'); ?>
        </a>
    </div>
</div>
