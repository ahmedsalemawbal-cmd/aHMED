<?php
/** شاشة اليوم — تُقرأ في ثلاث ثوانٍ بلا تمرير. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    require SCH_PATH . 'frontend/app/home.php';
    return;
}

$today  = SCH_App::today($id);
$trip   = $today['trip'];
$leave  = SCH_StudentLeave::on_leave_today($id);
$rate   = SCH_Attendance::rate($id);
$notes  = SCH_Notes::of_student($id, 3);

// الحالة سطران: ما هو الآن، ولماذا نعرف ذلك.
if ($leave) {
    $state_v = __('في إجازة معتمدة', 'school-system');
    $state_s = __('الباص لا ينتظره اليوم', 'school-system');
} elseif ($trip) {
    $state_v = SCH_App::trip_state_label($trip['state']);
    $state_s = $trip['direction'] === 'morning'
        ? __('رحلة الذهاب جارية', 'school-system')
        : __('رحلة العودة جارية', 'school-system');
} else {
    $state_v = SCH_Custody::state_label($student->custody_state);
    $state_s = $today['attendance']
        ? (SCH_Attendance::STATUSES[$today['attendance']->status] ?? '')
        : __('لم يُرصد الحضور بعد', 'school-system');
}
?>

<!-- أربع بوابات: الأب يفتح إحداها في ثانيتين بلا تفكير.
     والصحة أقواها لونًا لأنها الوحيدة التي قد تكون عاجلة. -->
<div class="p-gates">
    <?php foreach ([
        ['schedule', __('الجدول الدراسي', 'school-system'), 'calendar', 1],
        ['track',    __('أين ابني الآن', 'school-system'),  'bus',      2],
        ['clinic',   __('الصحة المدرسية', 'school-system'), 'heart',    3],
        ['leave',    __('طلبات الإجازات', 'school-system'), 'check',    4],
    ] as [$slug, $label, $icon, $tone]) : ?>
        <a class="p-gate p-gate--<?php echo esc_attr((string) $tone); ?> p-tap"
           href="<?php echo esc_url(SCH_App::url($slug, $id)); ?>">
            <span class="p-gate__i"><?php echo sch_icon($icon, 20); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b class="p-gate__t"><?php echo esc_html($label); ?></b>
        </a>
    <?php endforeach; ?>
</div>

<div class="p-state<?php echo esc_attr($trip ? ' is-live' : ''); ?>">
    <span class="p-state__k"><?php esc_html_e('الحالة الآن', 'school-system'); ?></span>
    <b class="p-state__v"><?php echo esc_html($state_v); ?></b>
    <span class="p-state__s"><?php echo esc_html($state_s); ?></span>
</div>

<div class="p-duo">
    <a class="p-num p-num--pay p-tap" href="<?php echo esc_url(SCH_App::url('invoices', $id)); ?>">
        <span class="p-num__k"><?php esc_html_e('المستحق', 'school-system'); ?></span>
        <b class="p-num__v"><span class="p-nm"><?php echo esc_html(number_format((float) $today['balance'], 0)); ?></span><i><?php esc_html_e('ر.س', 'school-system'); ?></i></b>
    </a>

    <div class="p-num p-num--att">
        <span class="p-num__k"><?php esc_html_e('الحضور', 'school-system'); ?></span>
        <b class="p-num__v"><span class="p-nm"><?php echo esc_html(number_format($rate, 0)); ?></span><i>٪</i></b>
        <span class="p-num__s"><?php esc_html_e('آخر ٦٠ يومًا', 'school-system'); ?></span>
    </div>
</div>

<?php $sch_certs = SCH_Certificates::of_student($id); ?>
<?php if ($sch_certs !== []) : ?>
    <a class="p-card p-tap" href="<?php echo esc_url(SCH_App::url('certificates', $id)); ?>">
        <div class="p-row" style="padding:0;border:0">
            <span class="p-row__i"><?php echo sch_icon('award', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-row__t">
                <b><?php esc_html_e('الشهادات', 'school-system'); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: %s: العدد */
                    _n('شهادة واحدة', '%s شهادات', count($sch_certs), 'school-system'),
                    number_format_i18n(count($sch_certs))
                )); ?></span>
            </span>
            <span class="p-row__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </div>
    </a>
<?php endif; ?>

<?php
// المكتبة ليست شاشة هنا — سطر يظهر عند التأخير ويختفي بعد الإرجاع.
$late_books = array_values(array_filter(
    SCH_Library::loans_of_student($id),
    static fn (object $l): bool => $l->returned_at === null && $l->due_date < current_time('Y-m-d')
));
?>
<?php if ($late_books !== []) : ?>
    <div class="p-note">
        <b><?php echo esc_html(sprintf(
            /* translators: %d: عدد الكتب */
            _n('كتاب متأخر لم يُرجَع', '%d كتب متأخرة لم تُرجَع', count($late_books), 'school-system'),
            count($late_books)
        )); ?></b>
        <span><?php echo esc_html(implode(' · ', array_map(
            static fn (object $l): string => (string) $l->title,
            array_slice($late_books, 0, 3)
        ))); ?></span>
    </div>
<?php endif; ?>

<?php if ($student->stage === 'kg') : ?>
    <a class="p-card p-tap" href="<?php echo esc_url(SCH_App::url('kg', $id)); ?>">
        <div class="p-row" style="padding:0;border:0">
            <span class="p-row__i"><?php echo sch_icon('sun', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-row__t">
                <b><?php esc_html_e('التقرير اليومي', 'school-system'); ?></b>
                <span><?php esc_html_e('الوجبة والنوم والنشاط', 'school-system'); ?></span>
            </span>
            <span class="p-row__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </div>
    </a>
<?php endif; ?>

<?php if ($notes !== []) : ?>
    <h2 class="p-h2"><?php esc_html_e('من المدرسة', 'school-system'); ?></h2>
    <div class="p-list">
        <?php foreach ($notes as $note) : ?>
            <div class="p-row">
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $note->body); ?></b>
                    <span><?php echo esc_html(SCH_Notes::CATEGORIES[$note->category][0] ?? ''); ?></span>
                </span>
                <span class="p-row__e" dir="ltr"><?php echo esc_html(substr((string) $note->created_at, 5, 5)); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
