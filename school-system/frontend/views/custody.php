<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$board  = SCH_Custody::board();
$health = SCH_Alerts::health();
$focus  = isset($_GET['state']) ? sanitize_key(wp_unslash((string) $_GET['state'])) : '';
$list   = $focus !== '' && isset(SCH_Custody::STATES[$focus]) ? SCH_Custody::students_in($focus) : [];

/*
 * التصحيح الإداريّ.
 *
 * `left_early` كانت **حالةً بلا مخرج في النظام كلّه**: لا مسحَ ينتج الخروج
 * منها (فالخروج المبكر انتهى)، و`custody_manual` — الفعل المسجَّل بمعالجه
 * منذ إصدارات — لم يكن يغيّر شيئًا لأن `RESULT` بلا مفتاحٍ له، فـ`$to`
 * تساوي `$from` دائمًا. فطفلٌ خرج مبكرًا يبقى «غادر مبكرًا» إلى الأبد
 * وعدّاد اللوحة يعدّه كل يوم.
 *
 * والتصحيح **حدثٌ مضادّ** لا تعديلٌ للسجلّ — قاعدة المشروع — ويشترط سببًا
 * مكتوبًا في الطبقة نفسها لا في الشاشة وحدها.
 */
$sch_may_fix = current_user_can('sch_manage_students') && SCH_Perms::may('custody', 'edit');
?>
<h1 class="sch-title"><?php echo sch_icon('shield', 22); ?><?php esc_html_e('لوحة العهدة', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('كل طالب في عهدة جهة معلومة. الرقم الأحمر هو ما يحتاج تدخلًا الآن.', 'school-system'); ?></p>

<?php if ($health['stale']) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php esc_html_e('الحارس الآلي متوقف', 'school-system'); ?></strong>
        <div class="sch-sub">
            <?php echo esc_html($health['last_run'] !== ''
                ? sprintf(__('آخر فحص: %s', 'school-system'), $health['last_run'])
                : __('لم يعمل بعد.', 'school-system')); ?>
            <?php esc_html_e('شغّل Cron حقيقيًا من لوحة الاستضافة كل خمس دقائق على wp-cron.php.', 'school-system'); ?>
        </div>
    </div>
<?php endif; ?>

<div class="sch-stats">
    <a class="sch-stat" href="<?php echo esc_url(SCH_Dashboard::url('custody')); ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $board['total'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('طالب نشط', 'school-system'); ?></span>
    </a>
    <?php foreach (SCH_Custody::STATES as $slug => $label) : ?>
        <a class="sch-stat<?php echo $focus === $slug ? ' is-on' : ''; ?>"
           href="<?php echo esc_url(add_query_arg('state', $slug, SCH_Dashboard::url('custody'))); ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $board[$slug])); ?></span>
            <span class="sch-stat__label"><?php echo esc_html($label); ?></span>
        </a>
    <?php endforeach; ?>
    <a class="sch-stat<?php echo (int) $board['unaccounted'] > 0 ? ' sch-stat--warn' : ''; ?>"
       href="<?php echo esc_url(SCH_Dashboard::url('alerts')); ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $board['unaccounted'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('إنذار حرج مفتوح', 'school-system'); ?></span>
    </a>
</div>

<?php if ($focus !== '' && isset(SCH_Custody::STATES[$focus])) : ?>
<div class="sch-card">
    <h2><?php echo esc_html(SCH_Custody::STATES[$focus]); ?></h2>

    <?php if ($list === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد طلاب في هذه الحالة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('منذ', 'school-system'); ?></th>
                    <?php if ($sch_may_fix) : ?>
                        <th class="sch-col--xl"><?php esc_html_e('تصحيح الحالة', 'school-system'); ?></th>
                    <?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($list as $s) : ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($s->academic_no ?: '—'); ?></td>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $s->id)); ?>">
                                <?php echo esc_html(SCH_Enrollment::full_name($s)); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(trim(($s->grade_level ?? '') . ' / ' . ($s->section ?? '')) ?: '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($s->custody_at ?: '—'); ?></td>

                        <?php if ($sch_may_fix) : ?>
                            <td>
                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
                                    <?php wp_nonce_field('sch_custody_manual', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="custody_manual">
                                    <input type="hidden" name="checkpoint" value="correction">
                                    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $s->id); ?>">
                                    <input type="hidden" name="keep[state]" value="<?php echo esc_attr($focus); ?>">

                                    <select name="to_state" required
                                            aria-label="<?php echo esc_attr(sprintf(
                                                /* translators: %s: اسم الطالب */
                                                __('الحالة الصحيحة لـ%s', 'school-system'),
                                                SCH_Enrollment::full_name($s)
                                            )); ?>">
                                        <?php foreach (SCH_Custody::STATES as $sch_st => $sch_lbl) : ?>
                                            <?php if ($sch_st === $focus) { continue; } ?>
                                            <option value="<?php echo esc_attr($sch_st); ?>"><?php echo esc_html($sch_lbl); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <input type="text" name="reason" required maxlength="190"
                                           placeholder="<?php esc_attr_e('السبب — إجباري', 'school-system'); ?>"
                                           aria-label="<?php esc_attr_e('سبب التصحيح', 'school-system'); ?>">

                                    <button class="sch-btn sch-btn--quiet sch-btn--sm"
                                            data-confirm="<?php esc_attr_e('يُسجَّل حدثٌ مضادّ باسمك ولا يُحذف. متابعة؟', 'school-system'); ?>">
                                        <?php esc_html_e('تصحيح', 'school-system'); ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('آخر حركات اليوم', 'school-system'); ?></h2>
    <?php $recent = SCH_Custody::recent(25); ?>
    <?php if ($recent === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد حركات اليوم', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('النقطة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th><?php esc_html_e('المستلم', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($recent as $e) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html(substr((string) $e->occurred_at, 11, 5)); ?></td>
                        <td class="sch-name"><?php echo esc_html($e->full_name); ?></td>
                        <td><?php echo esc_html(SCH_Custody::CHECKPOINTS[$e->checkpoint] ?? ''); ?></td>
                        <td><?php echo esc_html(SCH_Custody::state_label($e->to_state)); ?></td>
                        <td class="sch-sub"><?php echo esc_html($e->receiver_name ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
