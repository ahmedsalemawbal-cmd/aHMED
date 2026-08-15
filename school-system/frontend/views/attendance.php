<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_Classes::list();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : (int) ($classes[0]->id ?? 0);
$date     = isset($_GET['d']) ? (sch_sanitize_date(wp_unslash((string) $_GET['d'])) ?? current_time('Y-m-d')) : current_time('Y-m-d');
$sheet    = $class_id > 0 ? SCH_Attendance::sheet($class_id, $date) : [];
$summary  = SCH_Attendance::day_summary($date);
?>
<h1 class="sch-title"><?php esc_html_e('الحضور', 'school-system'); ?></h1>

<?php
// شبكة الأمان: طالب سيارة الأهل لا سجل سابق له يُقارَن به،
// فلو انشغل الحارس ظُنّ غائبًا وهو في فصله. المعلم يصحّح.
$sch_unmarked = [];
foreach ($sheet as $sch_row) {
    if ($sch_row->status === null) {
        $sch_unmarked[] = $sch_row;
    }
}
?>
<?php if ($sch_unmarked !== [] && $date === current_time('Y-m-d')) : ?>
    <div class="sch-notice">
        <strong><?php echo esc_html(sprintf(
            /* translators: %d: عدد الطلاب */
            _n('لم يُرصد دخول %d طالب — راجعه', 'لم يُرصد دخول %d طلاب — راجعهم', count($sch_unmarked), 'school-system'),
            count($sch_unmarked)
        )); ?></strong>
        <div class="sch-sub">
            <?php esc_html_e('المسح هو الأصل وأنت المصحّح: انظر أمامك وحدّد الموجود من الغائب في الكشف أدناه.', 'school-system'); ?>
        </div>
    </div>
<?php endif; ?>

<div class="sch-stats">
    <?php foreach (SCH_Attendance::STATUSES as $key => $label) : ?>
        <div class="sch-stat">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($summary[$key])); ?></span>
            <span class="sch-stat__label"><?php echo esc_html($label); ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="sch-card">
    <form method="get" class="sch-toolbar">
        <select name="class_id" aria-label="الشعبة">
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="d" value="<?php echo esc_attr($date); ?>" aria-label="التاريخ">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض الكشف', 'school-system'); ?></button>
    </form>

    <?php if ($sheet === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد طلاب في هذه الشعبة', 'school-system'); ?></strong>
            <?php esc_html_e('سجّل طلابًا في الشعبة أولًا من صفحة الطلاب.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <form method="post">
            <?php wp_nonce_field('sch_mark_attendance', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="mark_attendance">
            <input type="hidden" name="att_date" value="<?php echo esc_attr($date); ?>">

            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                        <?php foreach (SCH_Attendance::STATUSES as $label) : ?>
                            <th style="text-align:center"><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                        <th><?php esc_html_e('المصدر', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($sheet as $row) :
                        $current = $row->status ?: 'present'; ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html($row->full_name); ?></td>
                            <?php foreach (array_keys(SCH_Attendance::STATUSES) as $key) : ?>
                                <td style="text-align:center">
                                    <input type="radio"
                                           name="status[<?php echo esc_attr((string) $row->id); ?>]"
                                           value="<?php echo esc_attr($key); ?>"
                                           aria-label="<?php echo esc_attr(SCH_Attendance::STATUSES[$key] . ' — ' . $row->full_name); ?>"
                                           <?php checked($current, $key); ?>>
                                </td>
                            <?php endforeach; ?>
                            <td class="sch-sub">
                                <?php echo esc_html($row->method === 'transport' ? __('الباص', 'school-system') : ($row->method ?: '—')); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button class="sch-btn sch-mt"><?php esc_html_e('حفظ الكشف', 'school-system'); ?></button>
        </form>
    <?php endif; ?>
</div>
