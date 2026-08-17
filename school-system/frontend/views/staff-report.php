<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$ym      = isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['ym']) ? (string) $_GET['ym'] : current_time('Y-m');

$school    = sch_settings('school_name', get_bloginfo('name'));
$ym_label  = date_i18n('F Y', (int) strtotime($ym . '-01'));
$base_args = ['sch_section' => 'staff-report', 'ym' => $ym];
?>

<h1 class="sch-title"><?php echo sch_icon('chart', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('تقرير حضور الموظفين', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('انضباط الموظفين شهرًا كاملًا. الأرقام لكل موظف مع نفسه — لا ترتيب ولا مقارنة تُشهِّر بأحد.', 'school-system'); ?></p>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="staff-report">

        <label class="sch-field">
            <span><?php esc_html_e('الشهر', 'school-system'); ?></span>
            <input type="month" name="ym" value="<?php echo esc_attr($ym); ?>" aria-label="<?php esc_attr_e('الشهر', 'school-system'); ?>">
        </label>

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>

        <span class="sch-toolbar__end">
            <button type="button" class="sch-btn sch-btn--quiet" onclick="window.print()"><?php echo sch_icon('printer', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('طباعة', 'school-system'); ?></button>
            <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['sch_export' => 1]), SCH_Dashboard::url('staff-report'))); ?>"><?php echo sch_icon('download', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('تصدير CSV', 'school-system'); ?></a>
        </span>
    </form>
</div>

<?php if ($user_id > 0) :
    // ===== بطاقة تقرير موظف واحد =====
    $person = get_user_by('id', $user_id);
    $emp    = SCH_Staff::get_by_user($user_id);
    $totals = SCH_StaffAttendance::month_totals($user_id, $ym);
    $rows   = SCH_StaffAttendance::month($user_id, $ym);

    $status_map = [];
    $sum_min = 0;
    $cnt_in  = 0;
    foreach ($rows as $date => $r) {
        $status_map[$date] = (string) $r->status;
        if (!empty($r->checked_at)) {
            [$hh, $mm] = array_map('intval', explode(':', substr((string) $r->checked_at, 11, 5)));
            $sum_min  += $hh * 60 + $mm;
            $cnt_in++;
        }
    }
    $avg_in = $cnt_in > 0 ? sprintf('%02d:%02d', intdiv((int) round($sum_min / $cnt_in), 60), (int) round($sum_min / $cnt_in) % 60) : '';
    $role   = $person instanceof WP_User ? (string) ($person->roles[0] ?? '') : '';
    ?>
    <p class="sch-noprint sch-backlink">
        <a class="sch-body-link" href="<?php echo esc_url(add_query_arg($base_args, SCH_Dashboard::url('staff-report'))); ?>"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('عودة للكشف', 'school-system'); ?></a>
    </p>

    <div class="sch-report">
        <div class="sch-report__head">
            <div>
                <b class="sch-report__school"><?php echo esc_html($school); ?></b>
                <span class="sch-report__meta"><?php echo esc_html($ym_label); ?></span>
            </div>
            <div class="sch-report__who">
                <b><?php echo esc_html($person instanceof WP_User ? $person->display_name : ''); ?></b>
                <span><?php echo esc_html($role !== '' ? SCH_Staff::role_label($role) : (string) ($emp->job_title ?? '')); ?></span>
            </div>
        </div>

        <div class="sch-wrow">
            <?php
            echo sch_wave_pill('present', __('حاضر', 'school-system'), (int) $totals['present']); // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('late', __('متأخر', 'school-system'), (int) $totals['late']);       // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('absent', __('غائب', 'school-system'), (int) $totals['absent']);     // phpcs:ignore WordPress.Security.EscapeOutput
            echo sch_wave_pill('leave', __('إجازة', 'school-system'), (int) $totals['leave']);      // phpcs:ignore WordPress.Security.EscapeOutput
            ?>
            <?php if ($avg_in !== '') : ?>
                <span class="sch-wpill sch-wpill--rate"><span class="sch-wpill__t"><?php esc_html_e('متوسط وقت الحضور', 'school-system'); ?></span><b class="sch-wpill__n" dir="ltr"><?php echo esc_html($avg_in); ?></b></span>
            <?php endif; ?>
            <?php if ((int) $totals['late_minutes'] > 0) : ?>
                <span class="sch-wpill sch-wpill--late"><span class="sch-wpill__t"><?php esc_html_e('مجموع دقائق التأخّر', 'school-system'); ?></span><b class="sch-wpill__n"><?php echo esc_html(number_format_i18n((int) $totals['late_minutes'])); ?></b></span>
            <?php endif; ?>
        </div>

        <?php echo sch_attendance_calendar($ym, $status_map); // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <div class="sch-cal__key">
            <?php foreach (['present' => __('حاضر', 'school-system'), 'late' => __('متأخر', 'school-system'), 'absent' => __('غائب', 'school-system'), 'leave' => __('إجازة', 'school-system')] as $k => $lbl) : ?>
                <span class="sch-cal__keyi"><i class="sch-cal__sw sch-cal__day--<?php echo esc_attr($k); ?>"></i><?php echo esc_html($lbl); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

<?php else :
    // ===== كشف الموظفين =====
    $roster = SCH_StaffAttendance::staff_roster($ym);

    // الوسيط لدقائق التأخّر — سياق محايد لا قائمة تشهير.
    $mins = [];
    foreach ($roster as $r) {
        $mins[] = (int) $r->late_minutes;
    }
    sort($mins);
    $n      = count($mins);
    $median = $n === 0 ? 0 : ($n % 2 ? $mins[intdiv($n, 2)] : (int) round(($mins[$n / 2 - 1] + $mins[$n / 2]) / 2));
    ?>
    <div class="sch-report">
        <div class="sch-report__head">
            <div>
                <b class="sch-report__school"><?php echo esc_html($school); ?></b>
                <span class="sch-report__meta"><?php echo esc_html($ym_label); ?></span>
            </div>
            <div class="sch-report__who">
                <b><?php esc_html_e('كل الموظفين', 'school-system'); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: 1: عدد الموظفين 2: وسيط دقائق التأخّر */
                    __('%1$s موظفًا · وسيط التأخّر %2$s د', 'school-system'),
                    number_format_i18n($n),
                    number_format_i18n($median)
                )); ?></span>
            </div>
        </div>

        <?php if ($roster === []) : ?>
            <div class="sch-blank"><strong><?php esc_html_e('لا موظفين نشطين', 'school-system'); ?></strong></div>
        <?php else : ?>
            <div class="sch-table-wrap">
                <table class="sch-table sch-table--report">
                    <thead><tr>
                        <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                        <th><?php esc_html_e('الدور', 'school-system'); ?></th>
                        <th><?php esc_html_e('حاضر', 'school-system'); ?></th>
                        <th><?php esc_html_e('متأخر', 'school-system'); ?></th>
                        <th><?php esc_html_e('غائب', 'school-system'); ?></th>
                        <th><?php esc_html_e('إجازة', 'school-system'); ?></th>
                        <th><?php esc_html_e('دقائق التأخّر', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($roster as $r) :
                        $link = add_query_arg(array_merge($base_args, ['user_id' => (int) $r->user_id]), SCH_Dashboard::url('staff-report')); ?>
                        <tr>
                            <td class="sch-name"><a class="sch-body-link sch-noprint" href="<?php echo esc_url($link); ?>"><?php echo esc_html((string) $r->display_name); ?></a><span class="sch-print-only"><?php echo esc_html((string) $r->display_name); ?></span></td>
                            <td><?php echo esc_html(SCH_Staff::role_label((string) $r->role)); ?></td>
                            <td class="sch-num sch-cell--present"><?php echo esc_html(number_format_i18n((int) $r->present)); ?></td>
                            <td class="sch-num sch-cell--late"><?php echo esc_html(number_format_i18n((int) $r->late)); ?></td>
                            <td class="sch-num sch-cell--absent"><?php echo esc_html(number_format_i18n((int) $r->absent)); ?></td>
                            <td class="sch-num sch-cell--leave"><?php echo esc_html(number_format_i18n((int) $r->leave_days)); ?></td>
                            <td class="sch-num"><?php echo esc_html((int) $r->late_minutes > 0 ? number_format_i18n((int) $r->late_minutes) : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
