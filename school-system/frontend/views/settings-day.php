<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// مواعيد الحضور والتنبيهات — منها يُحسب التأخّر آليًا، ومنها تنطلق إنذارات الحارس الآلي.
$s = sch_settings();

/** قراءة إعداد مع افتراضه. */
$val = static fn (string $k, string $def): string => (string) ($s[$k] ?? '') !== '' ? (string) $s[$k] : $def;
?>
<h1 class="sch-title"><?php echo sch_icon('clock', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('مواعيد الحضور والتنبيهات', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('التوقيت هو ما يجعل النظام يحسب التأخّر ويطلق الإنذارات وحده — دون تدخّل يومي.', 'school-system'); ?></p>

<div class="sch-card">
    <header class="sch-set__head">
        <span class="sch-set__ic"><?php echo sch_icon('clock', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div>
            <h2><?php esc_html_e('مواعيد الحضور', 'school-system'); ?></h2>
            <p><?php esc_html_e('من تجاوز الموعد + دقائق التسامح يُسجَّل «متأخرًا» عند المسح تلقائيًا.', 'school-system'); ?></p>
        </div>
    </header>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
        <?php wp_nonce_field('sch_save_timing', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_timing">
        <div class="sch-grid">
            <label class="sch-field"><span><?php esc_html_e('بدء دوام الطالب', 'school-system'); ?></span>
                <input type="time" name="attendance_student_in" dir="ltr" aria-label="<?php esc_attr_e('بدء دوام الطالب', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_student_in', '07:30')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('بدء دوام الموظف', 'school-system'); ?></span>
                <input type="time" name="attendance_staff_in" dir="ltr" aria-label="<?php esc_attr_e('بدء دوام الموظف', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_staff_in', '07:00')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('دقائق التسامح', 'school-system'); ?></span>
                <input type="number" name="attendance_grace" min="0" max="120" aria-label="<?php esc_attr_e('دقائق التسامح', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_grace', '10')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('نهاية الدوام', 'school-system'); ?></span>
                <input type="time" name="attendance_day_end" dir="ltr" aria-label="<?php esc_attr_e('نهاية الدوام', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_day_end', '13:00')); ?>"></label>
        </div>
        <button class="sch-btn sch-mt"><?php esc_html_e('حفظ المواعيد', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card sch-mt">
    <header class="sch-set__head">
        <span class="sch-set__ic"><?php echo sch_icon('bell', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div>
            <h2><?php esc_html_e('الإنذارات والتنبيهات', 'school-system'); ?></h2>
            <p><?php esc_html_e('حدود الحارس الآلي: متى يُنبَّه على من لم يصعد الباص، أو لم يدخل، أو لم يُرصد حضوره.', 'school-system'); ?></p>
        </div>
    </header>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
        <?php wp_nonce_field('sch_save_alerts', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_alerts">
        <div class="sch-grid">
            <label class="sch-field"><span><?php esc_html_e('آخر موعد لصعود الباص', 'school-system'); ?></span>
                <input type="time" name="alert_bus_no_board_at" dir="ltr" aria-label="<?php esc_attr_e('آخر موعد لصعود الباص', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_bus_no_board_at', '06:50')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('يجب الوصول للمدرسة قبل', 'school-system'); ?></span>
                <input type="time" name="alert_bus_arrival_by" dir="ltr" aria-label="<?php esc_attr_e('يجب الوصول للمدرسة قبل', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_bus_arrival_by', '07:45')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('يجب رصد الحضور قبل', 'school-system'); ?></span>
                <input type="time" name="alert_attendance_by" dir="ltr" aria-label="<?php esc_attr_e('يجب رصد الحضور قبل', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_attendance_by', '09:00')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('وقت الانصراف', 'school-system'); ?></span>
                <input type="time" name="alert_dismissal_at" dir="ltr" aria-label="<?php esc_attr_e('وقت الانصراف', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_dismissal_at', '13:00')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('تنبيه «ما زال في المدرسة» بعد (دقيقة)', 'school-system'); ?></span>
                <input type="number" name="alert_still_at_school" min="0" max="180" aria-label="<?php esc_attr_e('تنبيه ما زال في المدرسة بعد كم دقيقة', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_still_at_school', '30')); ?>"></label>
            <label class="sch-field"><span><?php esc_html_e('مهلة إحالة العيادة (دقيقة)', 'school-system'); ?></span>
                <input type="number" name="alert_referral_minutes" min="1" max="120" aria-label="<?php esc_attr_e('مهلة إحالة العيادة بالدقائق', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_referral_minutes', '10')); ?>"></label>
        </div>
        <button class="sch-btn sch-mt"><?php esc_html_e('حفظ الحدود', 'school-system'); ?></button>
    </form>
</div>
