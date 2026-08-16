<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes    = SCH_Classes::list();
$class_id   = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
$student_id = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;
$ym         = isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['ym']) ? (string) $_GET['ym'] : current_time('Y-m');

$school     = sch_settings('school_name', get_bloginfo('name'));
$ym_label   = date_i18n('F Y', (int) strtotime($ym . '-01'));

$base_args  = array_filter(['sch_section' => 'attendance-report', 'class_id' => $class_id, 'ym' => $ym]);
?>

<h1 class="sch-title"><?php echo sch_icon('chart', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('تقرير الحضور الشهري', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('حضور الطلاب شهرًا كاملًا — عدّادات ونِسَب وخريطة تقويم لكل طالب. للطباعة والتصدير.', 'school-system'); ?></p>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="attendance-report">

        <label class="sch-field">
            <span><?php esc_html_e('الشهر', 'school-system'); ?></span>
            <input type="month" name="ym" value="<?php echo esc_attr($ym); ?>" aria-label="<?php esc_attr_e('الشهر', 'school-system'); ?>">
        </label>

        <label class="sch-field">
            <span><?php esc_html_e('الشعبة', 'school-system'); ?></span>
            <select name="class_id" aria-label="<?php esc_attr_e('الشعبة', 'school-system'); ?>">
                <option value=""><?php esc_html_e('اختر الشعبة…', 'school-system'); ?></option>
                <?php foreach ($classes as $c) : ?>
                    <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                        <?php echo esc_html(SCH_Classes::label($c)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>

        <?php if ($class_id > 0) : ?>
            <span class="sch-toolbar__end">
                <button type="button" class="sch-btn sch-btn--quiet" onclick="window.print()"><?php echo sch_icon('printer', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('طباعة', 'school-system'); ?></button>
                <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['sch_export' => 1]), SCH_Dashboard::url('attendance-report'))); ?>"><?php echo sch_icon('download', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('تصدير CSV', 'school-system'); ?></a>
            </span>
        <?php endif; ?>
    </form>
</div>

<?php if ($class_id === 0) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('chart', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('اختر شعبة وشهرًا', 'school-system'); ?></strong>
        <p><?php esc_html_e('يظهر هنا تقرير الحضور الشهري لكل طلاب الشعبة، مع خريطة تقويم لكل طالب.', 'school-system'); ?></p>
    </div>

<?php elseif ($student_id > 0) :
    // ===== بطاقة تقرير طالب واحد: خريطة تقويم + عدّادات =====
    $student = SCH_Students::get($student_id);
    $totals  = SCH_Attendance::month_totals($student_id, $ym);
    $rows    = SCH_Attendance::month($student_id, $ym);

    $status_map = [];
    foreach ($rows as $date => $r) {
        $status_map[$date] = (string) $r->status;
    }
    ?>
    <p class="sch-noprint sch-backlink">
        <a class="sch-body-link" href="<?php echo esc_url(add_query_arg($base_args, SCH_Dashboard::url('attendance-report'))); ?>"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('عودة لكشف الشعبة', 'school-system'); ?></a>
    </p>

    <div class="sch-report">
        <div class="sch-report__head">
            <div>
                <b class="sch-report__school"><?php echo esc_html($school); ?></b>
                <span class="sch-report__meta"><?php echo esc_html($ym_label); ?></span>
            </div>
            <div class="sch-report__who">
                <b><?php echo esc_html($student ? SCH_Enrollment::full_name($student) : ''); ?></b>
                <span dir="ltr"><?php echo esc_html((string) ($student->academic_no ?? '')); ?></span>
            </div>
        </div>

        <div class="sch-wrow">
            <?php
            echo sch_wave_pill('present', __('أيام حضور', 'school-system'), (int) $totals['present']); // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('late', __('أيام تأخّر', 'school-system'), (int) $totals['late']);       // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('absent', __('أيام غياب', 'school-system'), (int) $totals['absent']);     // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('excused', __('بعذر', 'school-system'), (int) $totals['excused']);        // phpcs:ignore WordPress.Security.EscapeOutput
            ?>
            <span class="sch-wpill sch-wpill--rate"><span class="sch-wpill__t"><?php esc_html_e('نسبة الحضور', 'school-system'); ?></span><b class="sch-wpill__n"><?php echo esc_html(number_format_i18n($totals['rate'], 1)); ?>٪</b></span>
            <?php if ((int) $totals['late_minutes'] > 0) : ?>
                <span class="sch-wpill sch-wpill--late"><span class="sch-wpill__t"><?php esc_html_e('مجموع دقائق التأخّر', 'school-system'); ?></span><b class="sch-wpill__n"><?php echo esc_html(number_format_i18n((int) $totals['late_minutes'])); ?></b></span>
            <?php endif; ?>
        </div>

        <?php echo sch_attendance_calendar($ym, $status_map); // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <div class="sch-cal__key">
            <?php foreach (['present' => __('حاضر', 'school-system'), 'late' => __('متأخر', 'school-system'), 'absent' => __('غائب', 'school-system'), 'excused' => __('بعذر', 'school-system')] as $k => $lbl) : ?>
                <span class="sch-cal__keyi"><i class="sch-cal__sw sch-cal__day--<?php echo esc_attr($k); ?>"></i><?php echo esc_html($lbl); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

<?php else :
    // ===== كشف الشعبة: صف لكل طالب =====
    $roster = SCH_Attendance::class_month($class_id, $ym);
    $class  = null;
    foreach ($classes as $c) {
        if ((int) $c->id === $class_id) {
            $class = $c;
            break;
        }
    }

    $sum = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
    foreach ($roster as $r) {
        $sum['present'] += (int) $r->present;
        $sum['late']    += (int) $r->late;
        $sum['absent']  += (int) $r->absent;
        $sum['excused'] += (int) $r->excused;
    }
    ?>
    <div class="sch-report">
        <div class="sch-report__head">
            <div>
                <b class="sch-report__school"><?php echo esc_html($school); ?></b>
                <span class="sch-report__meta"><?php echo esc_html($ym_label); ?></span>
            </div>
            <div class="sch-report__who">
                <b><?php echo esc_html($class ? SCH_Classes::label($class) : ''); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: %s: عدد الطلاب */
                    __('%s طالبًا', 'school-system'),
                    number_format_i18n(count($roster))
                )); ?></span>
            </div>
        </div>

        <div class="sch-wrow sch-noprint">
            <?php
            echo sch_wave_pill('present', __('أيام حضور', 'school-system'), $sum['present']); // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('late', __('أيام تأخّر', 'school-system'), $sum['late']);       // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('absent', __('أيام غياب', 'school-system'), $sum['absent']);     // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('excused', __('بعذر', 'school-system'), $sum['excused']);        // phpcs:ignore WordPress.Security.EscapeOutput
            ?>
        </div>

        <?php if ($roster === []) : ?>
            <div class="sch-blank"><strong><?php esc_html_e('لا طلاب في هذه الشعبة', 'school-system'); ?></strong></div>
        <?php else : ?>
            <div class="sch-table-wrap">
                <table class="sch-table sch-table--report">
                    <thead><tr>
                        <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                        <th><?php esc_html_e('حاضر', 'school-system'); ?></th>
                        <th><?php esc_html_e('متأخر', 'school-system'); ?></th>
                        <th><?php esc_html_e('غائب', 'school-system'); ?></th>
                        <th><?php esc_html_e('بعذر', 'school-system'); ?></th>
                        <th><?php esc_html_e('دقائق التأخّر', 'school-system'); ?></th>
                        <th><?php esc_html_e('النسبة', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($roster as $r) :
                        $total = (int) $r->total;
                        $rate  = $total > 0 ? round(((int) $r->present + (int) $r->late) / $total * 100, 1) : 0.0;
                        $link  = add_query_arg(array_merge($base_args, ['student_id' => (int) $r->id]), SCH_Dashboard::url('attendance-report')); ?>
                        <tr>
                            <td class="sch-name"><a class="sch-body-link sch-noprint" href="<?php echo esc_url($link); ?>"><?php echo esc_html((string) $r->full_name); ?></a><span class="sch-print-only"><?php echo esc_html((string) $r->full_name); ?></span></td>
                            <td class="sch-num sch-cell--present"><?php echo esc_html(number_format_i18n((int) $r->present)); ?></td>
                            <td class="sch-num sch-cell--late"><?php echo esc_html(number_format_i18n((int) $r->late)); ?></td>
                            <td class="sch-num sch-cell--absent"><?php echo esc_html(number_format_i18n((int) $r->absent)); ?></td>
                            <td class="sch-num sch-cell--excused"><?php echo esc_html(number_format_i18n((int) $r->excused)); ?></td>
                            <td class="sch-num"><?php echo esc_html((int) $r->late_minutes > 0 ? number_format_i18n((int) $r->late_minutes) : '—'); ?></td>
                            <td class="sch-num"><b><?php echo esc_html(number_format_i18n($rate, 1)); ?>٪</b></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
