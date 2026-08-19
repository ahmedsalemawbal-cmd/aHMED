<?php
/** تقرير الروضة اليومي. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id   = (int) ($sch_data['id'] ?? 0);
$days = SCH_KG::history($id, 14);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('التقرير اليومي', 'school-system'); ?></h1>

<?php if ($days === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('sun', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا تقارير بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('تكتبه المعلمة في نهاية كل يوم.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php foreach ($days as $day) : ?>
        <div class="p-card">
            <b class="p-h2" style="margin:0 0 8px"><?php echo esc_html(wp_date('l · j M', strtotime((string) $day->log_date))); ?></b>
            <div class="p-kgrid">
                <div><span><?php esc_html_e('الوجبة', 'school-system'); ?></span><b><?php echo esc_html(SCH_KG::MEALS[(string) $day->meal] ?? '—'); ?></b></div>
                <div><span><?php esc_html_e('النوم', 'school-system'); ?></span><b><?php echo esc_html($day->nap_minutes ? number_format_i18n((int) $day->nap_minutes) . ' ' . __('دقيقة', 'school-system') : '—'); ?></b></div>
                <div><span><?php esc_html_e('المزاج', 'school-system'); ?></span><b><?php echo esc_html(SCH_KG::MOODS[(string) $day->mood] ?? '—'); ?></b></div>
                <div><span><?php esc_html_e('النشاط', 'school-system'); ?></span><b><?php echo esc_html((string) ($day->activities ?: '—')); ?></b></div>
            </div>
            <?php if ($day->note) : ?>
                <p class="p-sub" style="margin:10px 0 0"><?php echo esc_html((string) $day->note); ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
