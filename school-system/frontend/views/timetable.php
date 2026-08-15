<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$state    = SCH_Org::timetable_state();
$problems = SCH_Org::timetable_problems();
$load     = SCH_Org::teacher_load();
$status   = (string) $state->tt_status;
?>
<h1 class="sch-title"><?php echo sch_icon('calendar', 22); ?><?php esc_html_e('بناء الجدول', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('مسودة ← اعتماد المدير ← نشر. لا يراه المعلمون قبل النشر — فلا يستيقظ أحد على جدول تغيّر ثلاث مرات ليلًا.', 'school-system'); ?></p>

<!-- المراحل الثلاث -->
<ol class="sch-steps-bar">
    <?php foreach (SCH_Org::TT_STATES as $slug => $label) :
        $order = (int) array_search($slug, array_keys(SCH_Org::TT_STATES), true);
        $now   = (int) array_search($status, array_keys(SCH_Org::TT_STATES), true);
        $cls   = $slug === $status ? 'is-active' : ($order < $now ? 'is-done' : ''); ?>
        <li class="<?php echo esc_attr($cls); ?>">
            <span><?php echo esc_html((string) ($order + 1)); ?></span><?php echo esc_html($label); ?>
        </li>
    <?php endforeach; ?>
</ol>

<div class="sch-card">
    <h2><?php esc_html_e('جاهزية الجدول', 'school-system'); ?></h2>

    <div class="sch-stats">
        <div class="sch-stat">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($problems['slots'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('حصة مجدولة', 'school-system'); ?></span>
        </div>
        <div class="sch-stat<?php echo $problems['conflicts'] > 0 ? ' sch-stat--warn' : ''; ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($problems['conflicts'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('تعارض معلم', 'school-system'); ?></span>
        </div>
        <div class="sch-stat<?php echo $problems['unassigned'] > 0 ? ' sch-stat--warn' : ''; ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($problems['unassigned'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('حصة بلا معلم', 'school-system'); ?></span>
        </div>
    </div>

    <div class="sch-toolbar sch-mt">
        <?php if ($status === 'draft' && current_user_can('sch_build_timetable')) : ?>
            <form method="post">
                <?php wp_nonce_field('sch_submit_timetable', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="submit_timetable">
                <button class="sch-btn"><?php esc_html_e('إرسال للاعتماد', 'school-system'); ?></button>
            </form>
            <span class="sch-sub"><?php esc_html_e('يُرفض الإرسال إن بقي تعارض أو حصة بلا معلم.', 'school-system'); ?></span>
        <?php endif; ?>

        <?php if ($status === 'pending' && current_user_can('sch_approve_timetable')) : ?>
            <form method="post">
                <?php wp_nonce_field('sch_publish_timetable', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="publish_timetable">
                <button class="sch-btn"><?php esc_html_e('اعتماد ونشر', 'school-system'); ?></button>
            </form>
        <?php endif; ?>

        <?php if ($status !== 'draft' && current_user_can('sch_build_timetable')) : ?>
            <form method="post" onsubmit="return confirm('<?php esc_attr_e('سيعود الجدول لمسودة ويختفي عن المعلمين. متابعة؟', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_reopen_timetable', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="reopen_timetable">
                <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إعادة لمسودة', 'school-system'); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($state->tt_ok_at) : ?>
        <p class="sch-sub sch-mt" dir="ltr"><?php echo esc_html($state->tt_ok_at); ?></p>
    <?php endif; ?>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('النصاب', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('تكتشف الظلم وأنت تبني، لا بعد شهرين من الشكاوى.', 'school-system'); ?></p>

    <?php if ($load === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد حصص مجدولة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المعلم', 'school-system'); ?></th>
                    <th><?php esc_html_e('حصصه', 'school-system'); ?></th>
                    <th><?php esc_html_e('نصابه', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($load as $l) :
                    $over = (int) $l->periods > (int) $l->weekly_quota; ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($l->display_name); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $l->periods)); ?></td>
                        <td class="sch-sub"><?php echo esc_html(number_format_i18n((int) $l->weekly_quota)); ?></td>
                        <td>
                            <?php if ($over) : ?>
                                <span class="sch-badge sch-badge--warn"><?php esc_html_e('فوق النصاب', 'school-system'); ?></span>
                            <?php else : ?>
                                <span class="sch-badge sch-badge--ok"><?php esc_html_e('ضمن النصاب', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="sch-sub sch-mt">
        <a href="<?php echo esc_url(SCH_Dashboard::url('subjects')); ?>"><?php esc_html_e('تحرير الجدول الأسبوعي', 'school-system'); ?></a>
    </p>
</div>
