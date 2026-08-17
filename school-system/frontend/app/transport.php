<?php
/** النقل — المسار ونقطة الالتقاء. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id  = (int) ($sch_data['id'] ?? 0);
$sub = SCH_Routes::subscription_of($id);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('النقل المدرسي', 'school-system'); ?></h1>

<?php if (!$sub) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('bus', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('غير مشترك في النقل', 'school-system'); ?></b>
        <p><?php esc_html_e('تواصل مع الإدارة إن رغبت في الاشتراك.', 'school-system'); ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="p-list">
    <div class="p-row">
        <span class="p-row__i"><?php echo sch_icon('route', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-row__t">
            <b><?php echo esc_html((string) $sub->route_name); ?></b>
            <span><?php esc_html_e('المسار', 'school-system'); ?></span>
        </span>
    </div>
    <div class="p-row">
        <span class="p-row__i"><?php echo sch_icon('bus', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-row__t">
            <b><?php echo esc_html((string) ($sub->stop_name ?: __('غير محددة', 'school-system'))); ?></b>
            <span><?php esc_html_e('نقطة الالتقاء', 'school-system'); ?></span>
        </span>
    </div>
</div>

<a class="p-btn p-btn--brand p-tap" href="<?php echo esc_url(SCH_App::url('track', $id)); ?>">
    <?php esc_html_e('أين ابني الآن', 'school-system'); ?>
</a>
