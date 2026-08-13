<?php
/** لا أبناء مرتبطون بالحساب. */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<div class="p-empty">
    <span class="p-empty__i"><?php echo sch_icon('users', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    <b><?php esc_html_e('لا يوجد أبناء مرتبطون بحسابك', 'school-system'); ?></b>
    <p><?php esc_html_e('تواصل مع إدارة المدرسة لربط أبنائك برقم جوالك.', 'school-system'); ?></p>
</div>
