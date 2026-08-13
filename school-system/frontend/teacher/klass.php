<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$class_id = (int) ($sch_data['id'] ?? 0);
$class    = SCH_Classes::get($class_id);

if (!$class) {
    echo '<div class="t-empty"><b>' . esc_html__('الفصل غير موجود', 'school-system') . '</b></div>';
    return;
}

$roster   = SCH_Teacher::roster($class_id);
$unmarked = SCH_Teacher::unmarked($roster);
?>
<a class="t-back t-tap" href="<?php echo esc_url(SCH_Teacher::url()); ?>"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e('فصولي', 'school-system'); ?></a>
<h1 class="t-h1"><?php echo esc_html(SCH_Classes::label($class)); ?></h1>

<?php if ($unmarked !== []) : ?>
    <div class="t-alert t-alert--error">
        <b><?php echo esc_html(sprintf(
            /* translators: %d: عدد الطلاب */
            _n('لم يُرصد دخول %d طالب — راجعه', 'لم يُرصد دخول %d طلاب — راجعهم', count($unmarked), 'school-system'),
            count($unmarked)
        )); ?></b>
        <span><?php esc_html_e('المسح هو الأصل وأنت المصحّح: انظر أمامك وحدّد الموجود من الغائب.', 'school-system'); ?></span>
    </div>
<?php endif; ?>

<div class="t-sect">
    <h2><?php esc_html_e('رصد الحضور', 'school-system'); ?></h2>
    <span><?php echo esc_html($unmarked === []
        ? __('اكتمل', 'school-system')
        : sprintf(
            /* translators: %d: عدد الطلاب */
            _n('%d بانتظارك', '%d بانتظارك', count($unmarked), 'school-system'),
            count($unmarked)
        )); ?></span>
</div>

<div class="t-roster">
    <?php foreach ($roster as $s) :
        $photo = SCH_Teacher::photo_url($s);
        $name  = SCH_Enrollment::full_name($s); ?>
        <div class="t-pupil<?php echo $s->att_status === null ? ' t-pupil--wait' : ''; ?>">
            <a class="t-pupil__face t-tap" href="<?php echo esc_url(SCH_Teacher::url('student', (int) $s->id)); ?>">
                <?php if ($photo) : ?>
                    <img src="<?php echo esc_url($photo); ?>" alt="" width="60" height="60" loading="lazy">
                <?php else : ?>
                    <?php echo esc_html(mb_substr($name, 0, 1)); ?>
                <?php endif; ?>
            </a>

            <a class="t-pupil__n" href="<?php echo esc_url(SCH_Teacher::url('student', (int) $s->id)); ?>">
                <?php echo esc_html(mb_substr($name, 0, 18)); ?>
            </a>

            <?php if ($s->att_status === null) : ?>
                <span class="t-pupil__acts">
                    <?php foreach (['present' => __('موجود', 'school-system'), 'absent' => __('غائب', 'school-system')] as $slug => $label) : ?>
                        <form method="post">
                            <?php wp_nonce_field('sch_tch_attend', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_tch_action" value="attend">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $s->id); ?>">
                            <input type="hidden" name="status" value="<?php echo esc_attr($slug); ?>">
                            <button class="t-mini t-mini--<?php echo $slug === 'present' ? 'y' : 'n'; ?> t-tap">
                                <?php echo esc_html($label); ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </span>
            <?php else : ?>
                <span class="t-state t-state--<?php echo esc_attr($s->att_status === 'absent' ? 'no' : 'ok'); ?>">
                    <i></i><?php echo esc_html(SCH_Attendance::STATUSES[$s->att_status] ?? ''); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
