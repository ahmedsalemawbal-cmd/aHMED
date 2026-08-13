<?php
/** السجل — الحضور والدرجات. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id  = (int) ($sch_data['id'] ?? 0);
$tab = isset($_GET['t']) && $_GET['t'] === 'grades' ? 'grades' : 'att';
?>

<h1 class="p-h1"><?php esc_html_e('السجل', 'school-system'); ?></h1>

<div class="p-seg">
    <a class="<?php echo esc_attr($tab === 'att' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(SCH_App::url('log', $id)); ?>"><?php esc_html_e('الحضور', 'school-system'); ?></a>
    <a class="<?php echo esc_attr($tab === 'grades' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(add_query_arg('t', 'grades', SCH_App::url('log', $id))); ?>"><?php esc_html_e('الدرجات', 'school-system'); ?></a>
</div>

<?php if ($tab === 'att') :
    $days = SCH_Attendance::history($id, 60); ?>

    <?php if ($days === []) : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('check', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا سجل حضور بعد', 'school-system'); ?></b>
            <p><?php esc_html_e('يظهر هنا بعد أول يوم دراسي.', 'school-system'); ?></p>
        </div>
    <?php else : ?>
        <div class="p-list">
            <?php foreach ($days as $d) :
                $tone = match ((string) $d->status) {
                    'present' => 'ok',
                    'absent'  => 'late',
                    'excused' => 'mute',
                    default   => 'warn',
                }; ?>
                <div class="p-row">
                    <span class="p-row__t">
                        <b><?php echo esc_html(wp_date('l · j M', strtotime((string) $d->att_date))); ?></b>
                    </span>
                    <span class="p-tag p-tag--<?php echo esc_attr($tone); ?>">
                        <?php echo esc_html(SCH_Attendance::STATUSES[$d->status] ?? ''); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else :
    $marks = SCH_Assessment::report_card($id); ?>

    <?php if ($marks === []) : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('award', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا درجات بعد', 'school-system'); ?></b>
            <p><?php esc_html_e('تظهر هنا بعد رصد أول اختبار.', 'school-system'); ?></p>
        </div>
    <?php else : ?>
        <div class="p-list">
            <?php foreach ($marks as $subject => $data) :
                $percent = (float) ($data['percent'] ?? 0); ?>
                <div class="p-row">
                    <span class="p-row__t">
                        <b><?php echo esc_html((string) $subject); ?></b>
                        <span><?php echo esc_html(sprintf(
                            /* translators: %s: عدد الاختبارات */
                            __('%s اختبارًا', 'school-system'),
                            number_format_i18n(count($data['exams'] ?? []))
                        )); ?></span>
                    </span>
                    <span class="p-mark">
                        <b class="p-nm"><?php echo esc_html(number_format($percent, 1)); ?>٪</b>
                        <em><?php echo esc_html(SCH_Assessment::grade_label($percent)); ?></em>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
