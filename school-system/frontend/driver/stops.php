<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$route = SCH_Driver::route_of(get_current_user_id());
$stops = $route ? SCH_Routes::stops((int) $route->id) : [];
?>
<a class="schd-back" href="<?php echo esc_url(SCH_Driver::url()); ?>">‹ <?php esc_html_e('رجوع', 'school-system'); ?></a>
<h1 class="schd-h1"><?php esc_html_e('نقاط المسار', 'school-system'); ?></h1>

<?php if ($stops === []) : ?>
    <div class="schd-empty">
        <strong><?php esc_html_e('لا توجد نقاط توقف', 'school-system'); ?></strong>
        <p><?php esc_html_e('تُضيفها الإدارة من لوحة النقل.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php foreach ($stops as $s) : ?>
        <div class="schd-row">
            <div class="schd-row__name">
                <strong><span class="schd-stat"><?php echo esc_html((string) $s->sequence); ?></span> <?php echo esc_html($s->name); ?></strong>
                <span dir="ltr"><?php echo esc_html($s->pickup_time ? substr((string) $s->pickup_time, 0, 5) : ''); ?></span>
            </div>
            <?php if ($s->lat !== null && $s->lng !== null) : ?>
                <a class="schd-act schd-act--go" target="_blank" rel="noopener"
                   href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . $s->lat . ',' . $s->lng); ?>">
                    <?php esc_html_e('اذهب', 'school-system'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
