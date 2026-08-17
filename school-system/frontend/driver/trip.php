<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$trip  = $sch_data['trip'];
$route = SCH_Routes::get((int) $trip->route_id);
?>
<div class="schd-status" id="schd-status">
    <div class="schd-status__row">
        <span class="schd-pill" id="schd-net"><?php esc_html_e('متصل', 'school-system'); ?></span>
        <span class="schd-pill" id="schd-gps"><?php esc_html_e('الموقع متوقف', 'school-system'); ?></span>
    </div>
    <strong class="schd-trip__name">
        <?php echo esc_html(($route->name ?? '') . ' — ' . ($trip->direction === 'morning' ? __('ذهاب', 'school-system') : __('عودة', 'school-system'))); ?>
    </strong>
    <span class="schd-trip__counts" id="schd-counts">—</span>
</div>

<div class="schd-list" id="schd-list">
    <div class="schd-loading"><?php esc_html_e('جارٍ تحميل قائمة الطلاب…', 'school-system'); ?></div>
</div>

<button type="button" class="schd-btn schd-btn--danger" id="schd-end">
    <?php esc_html_e('إنهاء الرحلة', 'school-system'); ?>
</button>

<p class="schd-hint schd-hint--sm">
    <?php esc_html_e('أبقِ الشاشة مفتوحة والجوال على الشاحن أثناء الرحلة. كل تسجيل يُحفظ حتى لو انقطعت الشبكة.', 'school-system'); ?>
</p>
