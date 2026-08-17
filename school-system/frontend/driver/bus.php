<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$bus   = SCH_Driver::bus_of(get_current_user_id());
$route = SCH_Driver::route_of(get_current_user_id());
$today = current_time('Y-m-d');
?>
<a class="schd-back" href="<?php echo esc_url(SCH_Driver::url()); ?>">‹ <?php esc_html_e('رجوع', 'school-system'); ?></a>
<h1 class="schd-h1"><?php esc_html_e('باصي', 'school-system'); ?></h1>

<?php if (!$bus) : ?>
    <div class="schd-empty"><strong><?php esc_html_e('لا يوجد باص مُسنَد لك', 'school-system'); ?></strong></div>
<?php else : ?>
    <div class="schd-route">
        <span class="schd-route__label"><?php esc_html_e('رقم اللوحة', 'school-system'); ?></span>
        <strong class="schd-route__name" dir="ltr"><?php echo esc_html($bus->plate_no); ?></strong>
        <span class="schd-route__bus"><?php echo esc_html($bus->model ?: ''); ?></span>
    </div>

    <div class="schd-info">
        <div class="schd-row">
            <span><?php esc_html_e('السعة', 'school-system'); ?></span>
            <span><?php echo esc_html(number_format_i18n((int) $bus->capacity)); ?></span>
        </div>
        <div class="schd-row">
            <span><?php esc_html_e('المسار', 'school-system'); ?></span>
            <span><?php echo esc_html($route->name ?? '—'); ?></span>
        </div>
        <div class="schd-row">
            <span><?php esc_html_e('انتهاء الاستمارة', 'school-system'); ?></span>
            <span dir="ltr">
                <?php echo esc_html($bus->registration_expiry ?: '—'); ?>
                <?php if ($bus->registration_expiry && $bus->registration_expiry <= gmdate('Y-m-d', time() + 30 * DAY_IN_SECONDS)) : ?>
                    <span class="schd-pill schd-pill--bad"><?php esc_html_e('قاربت الانتهاء', 'school-system'); ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>
<?php endif; ?>
