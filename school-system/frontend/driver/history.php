<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$trips = SCH_Driver::trips_today(get_current_user_id());
?>
<a class="schd-back" href="<?php echo esc_url(SCH_Driver::url()); ?>">‹ <?php esc_html_e('رجوع', 'school-system'); ?></a>
<h1 class="schd-h1"><?php esc_html_e('رحلات اليوم', 'school-system'); ?></h1>

<?php if ($trips === []) : ?>
    <div class="schd-empty"><strong><?php esc_html_e('لا توجد رحلات اليوم', 'school-system'); ?></strong></div>
<?php else : ?>
    <?php foreach ($trips as $t) : ?>
        <div class="schd-row">
            <div class="schd-row__name">
                <strong><?php echo esc_html($t->route_name . ' — ' . ($t->direction === 'morning' ? __('ذهاب', 'school-system') : __('عودة', 'school-system'))); ?></strong>
                <span dir="ltr"><?php echo esc_html(substr((string) $t->started_at, 11, 5) . ($t->ended_at ? ' ← ' . substr((string) $t->ended_at, 11, 5) : '')); ?></span>
            </div>
            <span class="schd-pill <?php echo $t->status === 'running' ? 'schd-pill--ok' : ''; ?>">
                <?php echo esc_html($t->status === 'running' ? __('جارية', 'school-system') : sprintf(__('%d صعدوا', 'school-system'), (int) $t->boarded)); ?>
            </span>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
