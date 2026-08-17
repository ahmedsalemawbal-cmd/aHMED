<?php
/** الجدول الدراسي. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$class   = SCH_Students::current_class($id);
$grid    = $class ? SCH_Timetable::grid((int) $class->id) : [];
$exams   = $class ? SCH_Assessment::upcoming_exams((int) $class->id) : [];
$w       = (int) current_time('w');
$day_now = $w === 0 ? 1 : ($w <= 4 ? $w + 1 : 0);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('الجدول الدراسي', 'school-system'); ?></h1>
<p class="p-sub"><?php echo esc_html($class ? SCH_Classes::label($class) : ''); ?></p>

<?php if ($exams !== []) : ?>
    <h2 class="p-h2"><?php esc_html_e('اختبارات قادمة', 'school-system'); ?></h2>
    <div class="p-list">
        <?php foreach ($exams as $e) : ?>
            <div class="p-row">
                <span class="p-date">
                    <b><?php echo esc_html(wp_date('j', strtotime((string) $e->exam_date))); ?></b>
                    <em><?php echo esc_html(wp_date('M', strtotime((string) $e->exam_date))); ?></em>
                </span>
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $e->title); ?></b>
                    <span><?php echo esc_html((string) $e->subject_name); ?></span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="p-h2"><?php esc_html_e('الحصص', 'school-system'); ?></h2>

<?php if ($grid === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('calendar', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لم يُعتمد الجدول بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('يظهر هنا فور اعتماده من المدرسة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php foreach (SCH_Timetable::DAYS as $day_num => $day_label) :
        $periods = $grid[$day_num] ?? [];
        if ($periods === []) { continue; } ?>
        <section class="p-day<?php echo esc_attr($day_num === $day_now ? ' is-now' : ''); ?>">
            <header class="p-day__h">
                <b><?php echo esc_html($day_label); ?></b>
                <?php if ($day_num === $day_now) : ?>
                    <em><?php esc_html_e('اليوم', 'school-system'); ?></em>
                <?php endif; ?>
            </header>

            <?php for ($n = 1; $n <= SCH_Timetable::PERIODS; $n++) :
                if (empty($periods[$n])) { continue; }
                $slot = $periods[$n]; ?>
                <div class="p-slot">
                    <span class="p-slot__n"><?php echo esc_html(number_format_i18n($n)); ?></span>
                    <span class="p-row__t">
                        <b><?php echo esc_html((string) $slot->subject_name); ?></b>
                        <?php if ($slot->teacher_name) : ?>
                            <span><?php echo esc_html((string) $slot->teacher_name); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endfor; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
