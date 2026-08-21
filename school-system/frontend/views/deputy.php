<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$board      = SCH_Deputy::today_board();
$vacancies  = SCH_Deputy::today_vacancies();
$staff      = SCH_Deputy::staff_sheet();
$unchecked  = array_values(array_filter($staff, static fn (object $s): bool => $s->status === null && (int) $s->on_leave === 0));
?>
<h1 class="sch-title"><?php echo sch_icon('home', 22); ?><?php esc_html_e('يوم الوكيل', 'school-system'); ?></h1>
<p class="sch-sub"><?php echo esc_html(wp_date('l، j F Y')); ?> — <?php esc_html_e('قائمة ما ينتظرك، لا قائمة أقسام.', 'school-system'); ?></p>

<!-- المشاكل أولًا: يفتحها فيرى ما ينتظره، ويغلقها حين تصير كلها خضراء -->
<div class="sch-daylist sch-problems">
    <?php
    $items = [
        ['red',    $board['vacant'],      __('حصص بلا معلم', 'school-system'),        __('اعتمد المقترحات أدناه', 'school-system'), ''],
        ['red',    $board['critical'],    __('إنذار حرج مفتوح', 'school-system'),     __('يحتاج تدخلًا فوريًا', 'school-system'), SCH_Dashboard::url('alerts')],
        // «أدناه» كانت تكذب: الملفّ ١٥٣ سطرًا ولا كشفَ فيه — `staff_sheet()`
        // تُحمَّل لحساب العدد وحده. والبند بلا رابط، فلا شيء يقود إلى الكشف.
        ['orange', count($unchecked),     __('موظفون بلا رصد حضور', 'school-system'), __('افتح كشف حضور المعلمين', 'school-system'), SCH_Dashboard::url('staff-day')],
        ['orange', $board['clinic'],      __('إحالة صحية مفتوحة', 'school-system'),  __('راجع صندوق الصحة المدرسية', 'school-system'), add_query_arg('t', 'clinic', SCH_Dashboard::url('inbox'))],
        ['orange', $board['leaves'],      __('طلب إجازة بانتظار القرار', 'school-system'), __('اعتمد أو ارفض بسبب', 'school-system'), SCH_Dashboard::url('leaves')],
        ['yellow', $board['meds'],        __('إذن دواء بانتظار الصحة المدرسية', 'school-system'),  __('راجعه واعتمده', 'school-system'), SCH_Dashboard::url('meds')],
        ['yellow', $board['stale_notes'], __('ملاحظة بانتظار الأخصائية', 'school-system'), __('صندوق المتابعة', 'school-system'), SCH_Dashboard::url('inbox')],
    ];
    ?>
    <?php foreach ($items as [$level, $count, $title, $hint, $link]) : ?>
        <?php
        $sch_cls = (int) $count === 0
            ? 'sch-prob--clear'
            : ('sch-prob--' . ($level === 'red' ? 'r' : ($level === 'orange' ? 'a' : 't')));
        ?>
        <?php if ($link !== '' && (int) $count > 0) : ?>
            <a class="sch-prob <?php echo esc_attr($sch_cls); ?>" href="<?php echo esc_url($link); ?>">
        <?php else : ?>
            <div class="sch-prob <?php echo esc_attr($sch_cls); ?>">
        <?php endif; ?>
            <span class="sch-prob__n"><?php echo esc_html(number_format_i18n((int) $count)); ?></span>
            <span class="sch-prob__t">
                <strong><?php echo esc_html($title); ?></strong>
                <span><?php echo esc_html((int) $count === 0 ? __('لا شيء ينتظرك هنا', 'school-system') : $hint); ?></span>
            </span>
        <?php if ($link !== '' && (int) $count > 0) : ?>
            </a>
        <?php else : ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- الاحتياط: النظام يقترح الحل لا يعرض المشكلة -->
<div class="sch-card">
    <h2><?php esc_html_e('الاحتياط اليوم', 'school-system'); ?></h2>

    <?php if ($vacancies === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد حصص شاغرة', 'school-system'); ?></strong>
            <?php esc_html_e('تظهر تلقائيًا عند اعتماد إجازة أو رصد غياب معلم.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <?php foreach ($vacancies as $v) : ?>
            <div class="sch-vacancy">
                <div class="sch-vacancy__head">
                    <strong>
                        <?php echo esc_html(sprintf(
                            /* translators: 1: رقم الحصة 2: الشعبة 3: المادة */
                            __('الحصة %1$d — %2$s — %3$s', 'school-system'),
                            (int) $v->period_no,
                            trim((string) $v->grade_level . ' / ' . (string) $v->section),
                            (string) ($v->subject_name ?: '')
                        )); ?>
                    </strong>
                    <span class="sch-badge<?php echo $v->status === 'assigned' ? ' sch-badge--ok' : ''; ?>">
                        <?php echo esc_html($v->status === 'assigned'
                            ? sprintf(__('البديل: %s', 'school-system'), (string) $v->substitute_name)
                            : __('يحتاج بديلًا', 'school-system')); ?>
                    </span>
                </div>

                <span class="sch-sub">
                    <?php echo esc_html(sprintf(
                        /* translators: 1: اسم المعلم 2: السبب */
                        __('المعلم الأصلي: %1$s — %2$s', 'school-system'),
                        (string) ($v->original_name ?: '—'),
                        SCH_Deputy::REASONS[$v->reason] ?? ''
                    )); ?>
                </span>

                <?php if ($v->status === 'proposed' && $v->candidates !== []) : ?>
                    <div class="sch-candidates">
                        <?php foreach ($v->candidates as $i => $c) : ?>
                            <form method="post" class="sch-candidate<?php echo $i === 0 ? ' is-best' : ''; ?>">
                                <?php wp_nonce_field('sch_assign_sub', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="assign_sub">
                                <input type="hidden" name="sub_id" value="<?php echo esc_attr((string) $v->id); ?>">
                                <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $c->user_id); ?>">

                                <span class="sch-candidate__who">
                                    <strong><?php echo esc_html($c->display_name); ?></strong>
                                    <span>
                                        <?php echo esc_html(implode(' · ', array_filter([
                                            (int) $c->same_subject > 0 ? __('نفس المادة', 'school-system') : '',
                                            sprintf(
                                                /* translators: %d: عدد الحصص */
                                                __('نصابه %d حصة', 'school-system'),
                                                (int) $c->load_count
                                            ),
                                            (int) $c->today_subs > 0
                                                ? sprintf(
                                                    /* translators: %d: عدد حصص الاحتياط */
                                                    __('أخذ %d احتياط اليوم', 'school-system'),
                                                    (int) $c->today_subs
                                                )
                                                : '',
                                        ]))); ?>
                                    </span>
                                </span>

                                <button class="sch-btn<?php echo $i === 0 ? '' : ' sch-btn--quiet'; ?>">
                                    <?php esc_html_e('اعتمد', 'school-system'); ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($v->status === 'proposed') : ?>
                    <div class="sch-notice sch-notice--error sch-mt">
                        <?php esc_html_e('لا يوجد معلم متفرغ في هذه الحصة.', 'school-system'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php $sch_gaps = SCH_Flow::gaps(); ?>
<?php if ($sch_gaps !== []) : ?>
    <div class="sch-sect">
        <h2><?php esc_html_e('ملفات ناقصة', 'school-system'); ?></h2>
        <span><?php esc_html_e('السجل الناقص لا يشتكي', 'school-system'); ?></span>
    </div>

    <div class="sch-gaps">
        <?php foreach ($sch_gaps as $sch_g) : ?>
            <a class="sch-gap sch-gap--<?php echo esc_attr($sch_g['level']); ?>"
               href="<?php echo esc_url($sch_g['url']); ?>">
                <b><?php echo esc_html(number_format_i18n((int) $sch_g['n'])); ?></b>
                <span>
                    <?php echo esc_html($sch_g['label']); ?>
                    <em><?php echo esc_html($sch_g['why']); ?></em>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
