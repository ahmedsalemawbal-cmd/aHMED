<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$alerts = SCH_Alerts::open_alerts();
$health = SCH_Alerts::health();
?>
<?php $sch_view = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : ''; ?>
<div class="sch-head">
    <div class="sch-head__t">
        <h1 class="sch-title"><?php echo sch_icon('clock', 22); ?><?php esc_html_e('الإنذارات', 'school-system'); ?></h1>
        <p class="sch-sub"><?php esc_html_e('الحارس الآلي يفحص كل خمس دقائق ويبحث عن الحدث الغائب — لا عن الحدث المسجَّل.', 'school-system'); ?></p>
    </div>
    <div class="sch-head__acts"><?php echo SCH_Views::menu('alerts', $sch_view); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
</div>

<div class="sch-pickbar">
    <?php SCH_Bulk::pick_all(); ?>
    <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('حالة الحارس الآلي', 'school-system'); ?></h2>
    <dl class="sch-dl">
        <div><dt><?php esc_html_e('آخر فحص', 'school-system'); ?></dt>
            <dd dir="ltr"><?php echo esc_html($health['last_run'] ?: '—'); ?></dd></div>
        <div><dt><?php esc_html_e('الحالة', 'school-system'); ?></dt>
            <dd><?php echo $health['stale']
                ? '<span class="sch-badge">' . esc_html__('متوقف', 'school-system') . '</span>'
                : '<span class="sch-badge sch-badge--ok">' . esc_html__('يعمل', 'school-system') . '</span>'; ?></dd></div>
        <div><dt><?php esc_html_e('مؤقت حقيقي', 'school-system'); ?></dt>
            <dd><?php echo $health['real_cron']
                ? '<span class="sch-badge sch-badge--ok">' . esc_html__('مفعّل', 'school-system') . '</span>'
                : '<span class="sch-badge">' . esc_html__('غير مفعّل', 'school-system') . '</span>'; ?></dd></div>
    </dl>
    <?php if (!$health['real_cron']) : ?>
        <p class="sch-sub sch-mt">
            <?php esc_html_e('مؤقت ووردبريس يعمل عند زيارة الموقع فقط. شغّل Cron من لوحة الاستضافة كل خمس دقائق على wp-cron.php، وأضف في wp-config.php:', 'school-system'); ?>
            <code class="sch-code" dir="ltr">define('DISABLE_WP_CRON', true);</code>
        </p>
    <?php endif; ?>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('إنذارات مفتوحة', 'school-system'); ?></h2>

    <?php if ($alerts === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد إنذارات', 'school-system'); ?></strong>
            <?php esc_html_e('كل طالب في مكانه المتوقع.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <?php foreach ($alerts as $a) : ?>
            <div class="sch-alert sch-alert--<?php echo esc_attr($a->severity); ?>">
                <div class="sch-alert__head">
                <?php SCH_Bulk::pick((int) $a->id); ?>
                    <strong><?php echo esc_html($a->title); ?></strong>
                    <span class="sch-badge"><?php echo esc_html(SCH_Alerts::SEVERITIES[$a->severity] ?? ''); ?></span>
                </div>

                <span class="sch-sub"><?php echo esc_html($a->detail ?: ''); ?></span>
                <span class="sch-sub" dir="ltr"><?php echo esc_html($a->opened_at); ?></span>

                <?php if ($a->student_id) : ?>
                    <a class="sch-sub" href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $a->student_id)); ?>">
                        <?php esc_html_e('فتح ملف الطالب', 'school-system'); ?>
                    </a>
                <?php endif; ?>

                <div class="sch-toolbar sch-mt">
                    <?php if ($a->status === 'open') : ?>
                        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                            <?php wp_nonce_field('sch_ack_alert', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="ack_alert">
                            <input type="hidden" name="alert_id" value="<?php echo esc_attr((string) $a->id); ?>">
                            <button class="sch-btn sch-btn--quiet"><?php esc_html_e('استلمته', 'school-system'); ?></button>
                        </form>
                    <?php else : ?>
                        <span class="sch-badge sch-badge--muted"><?php esc_html_e('قيد المعالجة', 'school-system'); ?></span>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
                        <?php wp_nonce_field('sch_close_alert', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="close_alert">
                        <input type="hidden" name="alert_id" value="<?php echo esc_attr((string) $a->id); ?>">
                        <input type="text" name="close_reason" required
                               placeholder="<?php esc_attr_e('سبب الإغلاق — إجباري', 'school-system'); ?>">
                        <button class="sch-btn"><?php esc_html_e('إغلاق', 'school-system'); ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php SCH_Bulk::bar('alerts'); ?>
