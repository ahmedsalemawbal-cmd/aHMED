<?php
/**
 * بناء الجداول — أربع خطوات في شاشة واحدة.
 *
 * الشاشة تبني **خطة**، والنشر ينسخها إلى الجدول المنشور. ولذلك لا يرى المعلم
 * ولا وليّ الأمر شيئًا حتى تُعتمد — فلا يستيقظ أحد على جدول تغيّر ثلاث مرات ليلًا.
 *
 * كل خطوة رابط حقيقي (`?step=`)، وكل فعل نموذج POST بنونسه. والسحب والإفلات
 * في الشبكة تحسينٌ فوق «اختر مادة ثم اضغط خانة» — فمن لا جافاسكربت عنده يبني
 * جدوله كاملًا، ومن يستعمل لوحة المفاتيح كذلك.
 */
declare(strict_types=1);
defined('ABSPATH') || exit;

$can_pub = current_user_can('sch_approve_timetable');

$months = SCH_TT::months();
$filled = SCH_TT::months_with_slots();

$ym = sanitize_text_field((string) ($_GET['m'] ?? ''));
if (!isset($months[$ym])) {
    $ym = (string) array_key_first($months);
}

$plan = SCH_TT::ensure_plan($ym);

if (!$plan) {
    echo '<div class="sch-empty"><strong>' . esc_html__('لا توجد سنة دراسية نشطة', 'school-system') . '</strong>'
       . esc_html__('افتح سنةً من الإعدادات أولًا — الجدول يتبع سنته.', 'school-system') . '</div>';
    return;
}

$plan_id = (int) $plan->id;
$live    = (string) $plan->status !== 'published';

$classes = SCH_Classes::list();

if ($classes === []) {
    echo '<div class="sch-empty"><strong>' . esc_html__('لا شعب بعد', 'school-system') . '</strong>'
       . esc_html__('أضف الصفوف والشعب أولًا — الجدول يُبنى لشعبة.', 'school-system') . '</div>';
    return;
}

$cls = absint($_GET['cls'] ?? 0);
if ($cls <= 0 || !SCH_Classes::get($cls)) {
    $cls = (int) $classes[0]->id;
}

$class  = SCH_Classes::get($cls);
$stage  = (string) $class->stage;
$bell   = SCH_TT::bell($stage);
$days   = SCH_TT::days($stage);
$ribbon = SCH_TT::ribbon($stage);
$span   = SCH_TT::day_span($stage);
$cap    = SCH_TT::capacity($cls);

$rows   = SCH_TT::quota($plan_id, $cls);
$demand = SCH_TT::demand($plan_id, $cls);
$sum    = 0;
foreach ($rows as $r) {
    $sum += (int) $r->weekly;
}

$grid   = SCH_TT::grid($plan_id, $cls);
$health = SCH_TT::health($plan_id, $cls);
$rules  = SCH_TT::rules_of($plan);

$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$lens = in_array((string) ($_GET['lens'] ?? ''), ['teacher', 'school'], true) ? (string) $_GET['lens'] : 'section';
$pick = absint($_GET['pick'] ?? 0);
$hl   = sanitize_key((string) ($_GET['hl'] ?? ''));

$base = SCH_Dashboard::url('timetable');
$here = static fn (array $args): string => add_query_arg(
    $args + ['m' => $ym, 'cls' => $cls, 'step' => $step, 'lens' => $lens],
    $base
);

/** موضع الشاشة يعود مع كل نموذج — ولا يُعاد الوكيل للخطوة الأولى بعد كل ضغطة. */
$keep = static function (int $to_step = 0) use ($ym, $cls, $step, $lens): void {
    ?>
    <input type="hidden" name="m" value="<?php echo esc_attr($ym); ?>">
    <input type="hidden" name="cls" value="<?php echo esc_attr((string) $cls); ?>">
    <input type="hidden" name="step" value="<?php echo esc_attr((string) ($to_step ?: $step)); ?>">
    <input type="hidden" name="lens" value="<?php echo esc_attr($lens); ?>">
    <input type="hidden" name="plan_id" value="<?php echo esc_attr((string) $GLOBALS['sch_tt_plan']); ?>">
    <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $cls); ?>">
    <?php
};
$GLOBALS['sch_tt_plan'] = $plan_id;

$steps = [
    1 => [__('الإعدادات', 'school-system'),     __('المرحلة واليوم والشعبة', 'school-system')],
    2 => [__('المواد والنصاب', 'school-system'), sprintf(
        /* translators: %d: عدد الحصص */
        __('%d حصة', 'school-system'),
        $sum
    )],
    3 => [__('المعلمون', 'school-system'),      __('إسناد وقيود', 'school-system')],
    4 => [__('الجدول', 'school-system'),        $health['filled'] > 0 ? sprintf(
        /* translators: %d: عدد الحصص */
        __('%d حصة موضوعة', 'school-system'),
        $health['filled']
    ) : __('لم يُولَّد بعد', 'school-system')],
];
?>
<div class="sch-tt" data-sch-tt>

    <!-- ══ الرأس ══ -->
    <div class="sch-tt__top">
        <div class="sch-tt__title">
            <h1><?php esc_html_e('بناء الجداول', 'school-system'); ?></h1>
            <p><?php esc_html_e('اختر المرحلة والشعبة، حدّد النصاب، ثم ولّد الجدول — والأسبوع يتكرر داخل الشهر وله نسخته الخاصة.', 'school-system'); ?></p>
        </div>

        <div class="sch-tt__acts">
            <span class="sch-tt__span">
                <b><?php echo esc_html((SCH_Classes::STAGES[$stage] ?? $stage) . ' — ' . $class->grade_level . ' / ' . $class->section); ?></b>
                <i aria-hidden="true"></i>
                <code dir="ltr"><?php echo esc_html(SCH_TT::hhmm($bell->start_min) . ' → ' . $span['end']); ?></code>
            </span>

            <?php if ($live) : ?>
                <button type="submit" form="sch-tt-bell" class="sch-tt__b"><?php esc_html_e('حفظ كقالب', 'school-system'); ?></button>

                <?php if ($step === 4 && $can_pub) : ?>
                    <form method="post" onsubmit="return confirm('<?php esc_attr_e('اعتماد الجدول ونشره للمعلمين وأولياء الأمور؟', 'school-system'); ?>');">
                        <?php wp_nonce_field('sch_tt_publish', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_publish">
                        <?php $keep(); ?>
                        <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('اعتماد ونشر', 'school-system'); ?></button>
                    </form>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_generate">
                        <input type="hidden" name="scope" value="class">
                        <?php $keep(4); ?>
                        <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('ولّد الجدول', 'school-system'); ?></button>
                    </form>
                <?php endif; ?>
            <?php else : ?>
                <span class="sch-tt__chip"><i aria-hidden="true"></i><?php esc_html_e('منشور', 'school-system'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ الخطوات الأربع ══ -->
    <nav class="sch-tt__steps" aria-label="<?php esc_attr_e('خطوات بناء الجدول', 'school-system'); ?>">
        <?php foreach ($steps as $n => [$label, $sub]) : ?>
            <a class="sch-tt__step<?php echo esc_attr($n === $step ? ' is-on' : ($n < $step ? ' is-done' : '')); ?>"
               href="<?php echo esc_url($here(['step' => $n])); ?>"
               aria-current="<?php echo $n === $step ? 'true' : 'false'; ?>">
                <span class="sch-tt__stepn"><?php echo esc_html($n < $step ? '✓' : (string) $n); ?></span>
                <span class="sch-tt__stept"><b><?php echo esc_html($label); ?></b><span><?php echo esc_html($sub); ?></span></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- ══ شريط الأشهر ══ -->
    <div class="sch-tt__months">
        <b><?php esc_html_e('الشهر', 'school-system'); ?></b>
        <div class="sch-tt__mlist">
            <?php foreach ($months as $key => $label) : ?>
                <a class="sch-tt__m<?php echo $key === $ym ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg(['m' => $key, 'cls' => $cls, 'step' => $step, 'lens' => $lens], $base)); ?>">
                    <?php if (isset($filled[$key])) : ?><i aria-hidden="true" title="<?php esc_attr_e('فيه جدول', 'school-system'); ?>"></i><?php endif; ?>
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <span class="sch-tt__mnote"><?php esc_html_e('نفس الأسبوع يتكرر داخل الشهر المحدد', 'school-system'); ?></span>
    </div>

    <?php // ══════════════════ ① الإعدادات ══════════════════ ?>
    <?php if ($step === 1) : ?>
        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <span class="sch-tt__ct">
                    <b><?php esc_html_e('إعدادات جاهزة لكل مرحلة', 'school-system'); ?></b>
                    <span><?php esc_html_e('اضغط المرحلة ويتعبّأ اليوم كامل — يمكنك التعديل بعدها.', 'school-system'); ?></span>
                </span>
            </div>

            <div class="sch-tt__presets">
                <?php foreach (SCH_TT::PRESETS as $slug => $p) :
                    $on  = $slug === $stage;
                    $end = SCH_TT::hhmm($p['start'] + $p['periods'] * $p['len'] + $p['break_len']); ?>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_bell">
                        <input type="hidden" name="stage" value="<?php echo esc_attr($stage); ?>">
                        <input type="hidden" name="periods" value="<?php echo esc_attr((string) $p['periods']); ?>">
                        <input type="hidden" name="period_len" value="<?php echo esc_attr((string) $p['len']); ?>">
                        <input type="hidden" name="start" value="<?php echo esc_attr(SCH_TT::hhmm($p['start'])); ?>">
                        <input type="hidden" name="break_after" value="<?php echo esc_attr((string) $p['break_after']); ?>">
                        <input type="hidden" name="break_len" value="<?php echo esc_attr((string) $p['break_len']); ?>">
                        <input type="hidden" name="days_mask" value="<?php echo esc_attr((string) $bell->days_mask); ?>">
                        <?php $keep(); ?>
                        <button class="sch-tt__preset<?php echo $on ? ' is-on' : ''; ?>"
                                title="<?php echo esc_attr(sprintf(
                                    /* translators: %s: اسم المرحلة */
                                    __('طبّق إيقاع %s على المرحلة الحالية', 'school-system'),
                                    $p['label']
                                )); ?>">
                            <span class="sch-tt__ph">
                                <b><?php echo esc_html($p['label']); ?></b>
                                <span class="sch-tt__pdot" aria-hidden="true"></span>
                            </span>
                            <span class="sch-tt__pfacts">
                                <span><em><?php esc_html_e('الحصص', 'school-system'); ?></em><b dir="ltr"><?php echo esc_html((string) $p['periods']); ?></b></span>
                                <span><em><?php esc_html_e('مدة الحصة', 'school-system'); ?></em><b dir="ltr"><?php echo esc_html($p['len'] . 'د'); ?></b></span>
                                <span><em><?php esc_html_e('البداية', 'school-system'); ?></em><b dir="ltr"><?php echo esc_html(SCH_TT::hhmm($p['start'])); ?></b></span>
                                <span><em><?php esc_html_e('الخروج', 'school-system'); ?></em><b dir="ltr"><?php echo esc_html($end); ?></b></span>
                            </span>
                            <span class="sch-tt__pnote"><?php echo esc_html($p['note']); ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <span class="sch-tt__ct">
                    <b><?php esc_html_e('اليوم الدراسي', 'school-system'); ?></b>
                    <span><?php echo esc_html(sprintf(
                        /* translators: %s: اسم المرحلة */
                        __('يُحفظ لمرحلة «%s» كلّها — كل شعبها تتبعه.', 'school-system'),
                        SCH_Classes::STAGES[$stage] ?? $stage
                    )); ?></span>
                </span>
                <span class="sch-tt__span">
                    <b><?php esc_html_e('الخروج', 'school-system'); ?></b>
                    <code dir="ltr"><?php echo esc_html($span['end']); ?></code>
                    <i aria-hidden="true"></i>
                    <b><?php echo esc_html($span['length']); ?></b>
                </span>
            </div>

            <form method="post" id="sch-tt-bell" class="sch-tt__day">
                <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="tt_bell">
                <input type="hidden" name="stage" value="<?php echo esc_attr($stage); ?>">
                <?php $keep(); ?>

                <?php foreach ([
                    ['periods',     __('عدد الحصص', 'school-system'),        $bell->periods,     1,  14],
                    ['period_len',  __('مدة الحصة (د)', 'school-system'),    $bell->period_len,  10, 120],
                    ['break_after', __('الفسحة بعد الحصة', 'school-system'), $bell->break_after, 0,  13],
                    ['break_len',   __('مدة الفسحة (د)', 'school-system'),   $bell->break_len,   0,  120],
                ] as [$name, $label, $value, $min, $max]) : ?>
                    <span class="sch-tt__f">
                        <label for="tt-<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
                        <span class="sch-tt__num">
                            <button type="button" class="sch-tt__pm" data-tt-step="1"
                                    data-tt-for="tt-<?php echo esc_attr($name); ?>"
                                    aria-label="<?php esc_attr_e('زيادة', 'school-system'); ?>">+</button>
                            <input id="tt-<?php echo esc_attr($name); ?>" type="number" name="<?php echo esc_attr($name); ?>"
                                   value="<?php echo esc_attr((string) $value); ?>"
                                   min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>"
                                   class="sch-tt__inp" dir="ltr">
                            <button type="button" class="sch-tt__pm" data-tt-step="-1"
                                    data-tt-for="tt-<?php echo esc_attr($name); ?>"
                                    aria-label="<?php esc_attr_e('إنقاص', 'school-system'); ?>">−</button>
                        </span>
                    </span>
                <?php endforeach; ?>

                <span class="sch-tt__f">
                    <label for="tt-start"><?php esc_html_e('بداية الحصة الأولى', 'school-system'); ?></label>
                    <input id="tt-start" type="time" name="start" dir="ltr"
                           value="<?php echo esc_attr(SCH_TT::hhmm($bell->start_min)); ?>">
                </span>

                <span class="sch-tt__f">
                    <label for="tt-days"><?php esc_html_e('أيام الدراسة', 'school-system'); ?></label>
                    <span class="sch-tt__days" id="tt-days">
                        <?php foreach (SCH_TT::WEEK as $n => $label) :
                            $on = (bool) ($bell->days_mask & (1 << ($n - 1))); ?>
                            <button type="button" class="sch-tt__d<?php echo $on ? ' is-on' : ''; ?>"
                                    data-tt-day="<?php echo esc_attr((string) $n); ?>"
                                    aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html($label); ?></button>
                        <?php endforeach; ?>
                        <input type="hidden" name="days_mask" id="tt-mask" value="<?php echo esc_attr((string) $bell->days_mask); ?>">
                    </span>
                </span>

                <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('حفظ اليوم', 'school-system'); ?></button>
            </form>

            <span class="sch-tt__ct"><b><?php esc_html_e('شريط اليوم', 'school-system'); ?></b></span>
            <div class="sch-tt__ribbon">
                <?php foreach ($ribbon as $r) : ?>
                    <span class="sch-tt__slot<?php echo $r['kind'] === 'break' ? ' is-break' : ''; ?>">
                        <b><?php echo esc_html($r['kind'] === 'break' ? __('فسحة', 'school-system') : (string) $r['no']); ?></b>
                        <span dir="ltr"><?php echo esc_html($r['from']); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <span class="sch-tt__ct">
                    <b><?php esc_html_e('الشعبة', 'school-system'); ?></b>
                    <span><?php esc_html_e('اختر شعبة لتبني جدولها — وبعد الاعتماد تنسخه لبقية الشعب بضغطة.', 'school-system'); ?></span>
                </span>
                <span class="sch-tt__hint"><?php echo esc_html(sprintf(
                    /* translators: %d: عدد الشعب */
                    __('%d شعبة في المدرسة', 'school-system'),
                    count($classes)
                )); ?></span>
            </div>

            <?php
            $by_grade = [];
            foreach ($classes as $c) {
                $by_grade[(SCH_Classes::STAGES[$c->stage] ?? $c->stage) . ' · ' . $c->grade_level][] = $c;
            }
            ?>
            <div class="sch-tt__grades">
                <?php foreach ($by_grade as $label => $list) : ?>
                    <div class="sch-tt__grade">
                        <b><?php echo esc_html($label); ?></b>
                        <?php foreach ($list as $c) : ?>
                            <a class="sch-tt__sec<?php echo (int) $c->id === $cls ? ' is-on' : ''; ?>"
                               href="<?php echo esc_url(add_query_arg(['m' => $ym, 'cls' => (int) $c->id, 'step' => $step, 'lens' => $lens], $base)); ?>">
                                <?php echo esc_html($c->section); ?>
                                <em dir="ltr"><?php echo esc_html($c->enrolled . '/' . $c->capacity); ?></em>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php // ══════════════════ ② المواد والنصاب ══════════════════ ?>
    <?php if ($step === 2) :
        $tone = $sum === $cap ? '' : ($sum > $cap ? 'is-over' : 'is-under'); ?>
        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <span class="sch-tt__ct">
                    <b><?php echo esc_html(sprintf(
                        /* translators: %s: اسم الشعبة */
                        __('نصاب المواد — %s', 'school-system'),
                        $class->grade_level . ' / ' . $class->section
                    )); ?></b>
                    <span><?php echo esc_html(sprintf(
                        /* translators: %d: سعة الأسبوع */
                        __('وزّع %d حصة أسبوعية على المواد.', 'school-system'),
                        $cap
                    )); ?></span>
                </span>
                <form method="post">
                    <?php wp_nonce_field('sch_tt_seed', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_seed">
                    <?php $keep(); ?>
                    <button class="sch-tt__b"><?php esc_html_e('استرجاع القالب', 'school-system'); ?></button>
                </form>
            </div>

            <div class="sch-tt__meter">
                <span class="sch-tt__hint"><?php esc_html_e('الموزّع', 'school-system'); ?></span>
                <span class="sch-tt__bar <?php echo esc_attr($tone); ?>">
                    <span style="width:<?php echo esc_attr((string) min(100, $cap > 0 ? $sum / $cap * 100 : 0)); ?>%"></span>
                </span>
                <b class="sch-tt__sum <?php echo esc_attr($tone); ?>" dir="ltr"><?php echo esc_html($sum . ' / ' . $cap); ?></b>
            </div>
            <span class="sch-tt__hint"><?php echo esc_html($sum === $cap
                ? __('مطابق تمامًا لعدد الحصص', 'school-system')
                : ($sum > $cap
                    ? sprintf(
                        /* translators: %d: الفرق */
                        __('زائد %d حصة عن سعة الأسبوع — لن تجد كلّها مكانًا.', 'school-system'),
                        $sum - $cap
                    )
                    : sprintf(
                        /* translators: %d: الفرق */
                        __('ناقص %d حصة — ستبقى خانات فارغة.', 'school-system'),
                        $cap - $sum
                    ))); ?></span>
        </div>

        <?php if ($rows === []) : ?>
            <div class="sch-empty">
                <strong><?php esc_html_e('لا مواد لهذه المرحلة بعد', 'school-system'); ?></strong>
                <?php esc_html_e('أضف المواد من شاشة «المواد والجداول» أولًا.', 'school-system'); ?>
            </div>
        <?php else : ?>
            <div class="sch-tt__subs">
                <?php foreach ($rows as $r) :
                    $n = (int) $r->weekly; ?>
                    <div class="sch-tt__sub<?php echo $n === 0 ? ' is-zero' : ''; ?>">
                        <span class="sch-tt__sh">
                            <span class="sch-tt__tone" aria-hidden="true"></span>
                            <span class="sch-tt__st">
                                <b><?php echo esc_html((string) $r->subject_name); ?></b>
                                <span><?php echo esc_html($r->teacher_name ?: __('بلا معلم', 'school-system')); ?></span>
                            </span>
                        </span>

                        <span class="sch-tt__num">
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_quota', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_quota">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $r->subject_id); ?>">
                                <input type="hidden" name="weekly" value="<?php echo esc_attr((string) ($n + 1)); ?>">
                                <?php $keep(); ?>
                                <button class="sch-tt__pm" aria-label="<?php echo esc_attr(sprintf(
                                    /* translators: %s: اسم المادة */
                                    __('زيادة نصاب %s', 'school-system'),
                                    (string) $r->subject_name
                                )); ?>">+</button>
                            </form>
                            <b dir="ltr"><?php echo esc_html((string) $n); ?></b>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_quota', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_quota">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $r->subject_id); ?>">
                                <input type="hidden" name="weekly" value="<?php echo esc_attr((string) max(0, $n - 1)); ?>">
                                <?php $keep(); ?>
                                <button class="sch-tt__pm" aria-label="<?php echo esc_attr(sprintf(
                                    /* translators: %s: اسم المادة */
                                    __('إنقاص نصاب %s', 'school-system'),
                                    (string) $r->subject_name
                                )); ?>">−</button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php // ══════════════════ ③ المعلمون والقيود ══════════════════ ?>
    <?php if ($step === 3) :
        $load     = SCH_TT::teacher_load($plan_id);
        $teachers = SCH_TT::teachers();
        $tcap     = max(1, (int) $plan->max_daily) * max(1, count($days)); ?>
        <div class="sch-tt__two">
            <div class="sch-tt__c">
                <div class="sch-tt__ch">
                    <span class="sch-tt__ct">
                        <b><?php esc_html_e('إسناد المعلمين', 'school-system'); ?></b>
                        <span><?php esc_html_e('لكل مادة معلم. النظام يمنع تعارض المعلم بين شعبتين في نفس الحصة.', 'school-system'); ?></span>
                    </span>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_auto_assign', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_auto_assign">
                        <?php $keep(); ?>
                        <button class="sch-tt__b"><?php esc_html_e('إسناد تلقائي للكل', 'school-system'); ?></button>
                    </form>
                </div>

                <?php if ($demand === []) : ?>
                    <div class="sch-empty">
                        <strong><?php esc_html_e('لا نصاب بعد', 'school-system'); ?></strong>
                        <?php esc_html_e('ارجع للخطوة الثانية ووزّع الحصص على المواد.', 'school-system'); ?>
                    </div>
                <?php else : ?>
                    <div class="sch-tt__assign">
                        <div class="sch-tt__ahead">
                            <span><?php esc_html_e('المادة', 'school-system'); ?></span>
                            <span><?php esc_html_e('النصاب', 'school-system'); ?></span>
                            <span><?php esc_html_e('المعلم', 'school-system'); ?></span>
                            <span><?php esc_html_e('حِمله', 'school-system'); ?></span>
                        </div>

                        <?php foreach ($demand as $r) :
                            $t     = (int) $r->teacher_user_id;
                            $has   = $t > 0 ? (int) ($load[$t] ?? 0) : 0;
                            $ratio = $tcap > 0 ? $has / $tcap : 0;
                            $tone  = $ratio > 1 ? 'is-over' : ($ratio > 0.8 ? 'is-warn' : ''); ?>
                            <div class="sch-tt__arow">
                                <form method="post">
                                    <?php wp_nonce_field('sch_tt_teacher', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="tt_teacher">
                                    <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $r->subject_id); ?>">
                                    <?php $keep(); ?>

                                    <span class="sch-tt__aname">
                                        <span class="sch-tt__tone" aria-hidden="true"></span>
                                        <b><?php echo esc_html((string) $r->subject_name); ?></b>
                                    </span>

                                    <span class="sch-tt__an" dir="ltr"><?php echo esc_html((string) $r->weekly); ?></span>

                                    <select name="teacher_user_id" data-tt-submit
                                            aria-label="<?php echo esc_attr(sprintf(
                                                /* translators: %s: اسم المادة */
                                                __('معلم %s', 'school-system'),
                                                (string) $r->subject_name
                                            )); ?>">
                                        <option value=""><?php esc_html_e('بلا معلم', 'school-system'); ?></option>
                                        <?php foreach ($teachers as $u) : ?>
                                            <option value="<?php echo esc_attr((string) $u->ID); ?>" <?php selected($t, (int) $u->ID); ?>>
                                                <?php echo esc_html((string) $u->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <span class="sch-tt__load <?php echo esc_attr($tone); ?>">
                                        <span dir="ltr"><?php echo esc_html($has . '/' . $tcap); ?></span>
                                        <span class="sch-tt__ltrack"><span style="width:<?php echo esc_attr((string) min(100, $ratio * 100)); ?>%"></span></span>
                                    </span>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sch-tt__c">
                <span class="sch-tt__ct"><b><?php esc_html_e('القيود المفعّلة', 'school-system'); ?></b></span>

                <div class="sch-tt__rules">
                    <?php foreach (SCH_TT::RULES as $key => $label) :
                        $on   = in_array($key, $rules, true);
                        $next = $on ? array_values(array_diff($rules, [$key])) : array_merge($rules, [$key]); ?>
                        <form method="post">
                            <?php wp_nonce_field('sch_tt_rules', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="tt_rules">
                            <input type="hidden" name="max_daily" value="<?php echo esc_attr((string) $plan->max_daily); ?>">
                            <?php foreach ($next as $r) : ?>
                                <input type="hidden" name="rules[]" value="<?php echo esc_attr($r); ?>">
                            <?php endforeach; ?>
                            <?php $keep(); ?>
                            <button class="sch-tt__rule<?php echo $on ? ' is-on' : ''; ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
                                <span class="sch-tt__sw" aria-hidden="true"></span>
                                <?php echo esc_html($label); ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <span class="sch-tt__ct"><b><?php esc_html_e('حد المعلم اليومي', 'school-system'); ?></b></span>
                <form method="post" class="sch-tt__row">
                    <?php wp_nonce_field('sch_tt_rules', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_rules">
                    <?php foreach ($rules as $r) : ?>
                        <input type="hidden" name="rules[]" value="<?php echo esc_attr($r); ?>">
                    <?php endforeach; ?>
                    <?php $keep(); ?>
                    <span class="sch-tt__num">
                        <button type="button" class="sch-tt__pm" data-tt-step="1" data-tt-for="tt-max"
                                aria-label="<?php esc_attr_e('زيادة', 'school-system'); ?>">+</button>
                        <input id="tt-max" type="number" name="max_daily" min="1" max="14" dir="ltr"
                               value="<?php echo esc_attr((string) $plan->max_daily); ?>"
                               aria-label="<?php esc_attr_e('حد المعلم اليومي', 'school-system'); ?>">
                        <button type="button" class="sch-tt__pm" data-tt-step="-1" data-tt-for="tt-max"
                                aria-label="<?php esc_attr_e('إنقاص', 'school-system'); ?>">−</button>
                    </span>
                    <button class="sch-tt__b"><?php esc_html_e('حفظ', 'school-system'); ?></button>
                </form>
                <span class="sch-tt__hint"><?php esc_html_e('لا يُسند للمعلم أكثر من هذا العدد في اليوم الواحد.', 'school-system'); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php // ══════════════════ ④ الجدول ══════════════════ ?>
    <?php if ($step === 4) : ?>
        <div class="sch-tt__bar2">
            <div class="sch-tt__lens">
                <?php foreach ([
                    'section' => __('جدول الشعبة', 'school-system'),
                    'teacher' => __('جدول المعلم', 'school-system'),
                    'school'  => __('خريطة المدرسة', 'school-system'),
                ] as $key => $label) : ?>
                    <a class="sch-tt__lensb<?php echo $key === $lens ? ' is-on' : ''; ?>"
                       href="<?php echo esc_url($here(['lens' => $key])); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="sch-tt__chips">
                <?php foreach ([
                    ['conflict', __('تعارضات', 'school-system'),           $health['conflicts']],
                    ['missing',  __('نصاب ناقص', 'school-system'),         $health['missing']],
                    ['late',     __('حصص ثقيلة متأخرة', 'school-system'), $health['late']],
                ] as [$key, $label, $n]) : ?>
                    <a class="sch-tt__chip<?php echo $n > 0 ? ' is-bad' : ''; ?><?php echo $hl === $key ? ' is-on' : ''; ?>"
                       href="<?php echo esc_url($here(['hl' => $hl === $key ? '' : $key])); ?>">
                        <i aria-hidden="true"></i><?php echo esc_html($label); ?>
                        <b dir="ltr"><?php echo esc_html((string) $n); ?></b>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($live) : ?>
                <div class="sch-tt__row">
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_generate">
                        <input type="hidden" name="scope" value="class">
                        <?php $keep(); ?>
                        <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('توليد من جديد', 'school-system'); ?></button>
                    </form>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_generate">
                        <input type="hidden" name="scope" value="school">
                        <?php $keep(); ?>
                        <button class="sch-tt__b" title="<?php esc_attr_e('التوليد لكل الشعب معًا يمنع تعارض المعلمين — والشعبة الأخيرة لا تجد معلميها محجوزين', 'school-system'); ?>">
                            <?php esc_html_e('توليد للمدرسة كلّها', 'school-system'); ?>
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('<?php esc_attr_e('تفريغ جدول هذه الشعبة؟', 'school-system'); ?>');">
                        <?php wp_nonce_field('sch_tt_wipe', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_wipe">
                        <?php $keep(); ?>
                        <button class="sch-tt__b sch-tt__b--bad"><?php esc_html_e('تفريغ', 'school-system'); ?></button>
                    </form>
                    <button type="button" class="sch-tt__b" data-sch-print><?php esc_html_e('طباعة A5', 'school-system'); ?></button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($lens === 'school') :
            $day = max(1, (int) ($_GET['day'] ?? (int) array_key_first($days)));
            $per = max(1, min($bell->periods, (int) ($_GET['p'] ?? 1)));
            $map = SCH_TT::school_map($plan_id, $day, $per); ?>
            <div class="sch-tt__c">
                <div class="sch-tt__ch">
                    <span class="sch-tt__ct">
                        <b><?php esc_html_e('خريطة المدرسة', 'school-system'); ?></b>
                        <span><?php esc_html_e('كل شعبة وما فيها في هذه اللحظة — الفارغ يعني حصةً بلا مادة.', 'school-system'); ?></span>
                    </span>
                </div>

                <div class="sch-tt__row">
                    <?php foreach ($days as $n => $label) : ?>
                        <a class="sch-tt__b<?php echo $n === $day ? ' sch-tt__b--go' : ''; ?>"
                           href="<?php echo esc_url($here(['day' => $n, 'p' => $per])); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="sch-tt__row">
                    <?php for ($p = 1; $p <= $bell->periods; $p++) : ?>
                        <a class="sch-tt__b<?php echo $p === $per ? ' sch-tt__b--go' : ''; ?>"
                           href="<?php echo esc_url($here(['day' => $day, 'p' => $p])); ?>">
                            <?php echo esc_html(sprintf(
                                /* translators: %d: رقم الحصة */
                                __('الحصة %d', 'school-system'),
                                $p
                            )); ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <div class="sch-tt__map">
                    <?php foreach ($map as $m) : ?>
                        <div class="sch-tt__mc<?php echo $m->subject_id ? '' : ' is-free'; ?>">
                            <em><?php echo esc_html((SCH_Classes::STAGES[$m->stage] ?? '') . ' · ' . $m->grade_level . ' / ' . $m->section); ?></em>
                            <b><?php echo esc_html($m->subject_name ?: __('فارغة', 'school-system')); ?></b>
                            <span><?php echo esc_html($m->teacher_name ?: __('—', 'school-system')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($lens === 'teacher') :
            $tid  = absint($_GET['t'] ?? 0);
            $tall = SCH_TT::teachers();
            if ($tid <= 0 && $tall !== []) {
                $tid = (int) $tall[0]->ID;
            }
            $tgrid = $tid > 0 ? SCH_TT::teacher_grid($plan_id, $tid) : []; ?>
            <div class="sch-tt__c">
                <div class="sch-tt__ch">
                    <span class="sch-tt__ct">
                        <b><?php esc_html_e('جدول المعلم', 'school-system'); ?></b>
                        <span><?php esc_html_e('نفس البيانات بزاوية أخرى — هنا يُرى الفراغ والتكدّس.', 'school-system'); ?></span>
                    </span>
                    <form method="get" class="sch-tt__row">
                        <input type="hidden" name="m" value="<?php echo esc_attr($ym); ?>">
                        <input type="hidden" name="cls" value="<?php echo esc_attr((string) $cls); ?>">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="lens" value="teacher">
                        <select name="t" data-tt-submit aria-label="<?php esc_attr_e('المعلم', 'school-system'); ?>">
                            <?php foreach ($tall as $u) : ?>
                                <option value="<?php echo esc_attr((string) $u->ID); ?>" <?php selected($tid, (int) $u->ID); ?>>
                                    <?php echo esc_html((string) $u->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="sch-tt__b"><?php esc_html_e('عرض', 'school-system'); ?></button>
                    </form>
                </div>

                <div class="sch-tt__gridwrap">
                    <table class="sch-tt__grid">
                        <thead><tr>
                            <th><?php esc_html_e('الحصة', 'school-system'); ?></th>
                            <?php foreach ($days as $label) : ?><th><?php echo esc_html($label); ?></th><?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php for ($p = 1; $p <= $bell->periods; $p++) : ?>
                            <tr>
                                <th><span class="sch-tt__pno"><b dir="ltr"><?php echo esc_html((string) $p); ?></b><span dir="ltr"><?php echo esc_html(SCH_TT::period_hhmm($p, $stage)); ?></span></span></th>
                                <?php foreach (array_keys($days) as $d) :
                                    $cell = $tgrid[$d][$p] ?? null; ?>
                                    <td class="sch-tt__cell">
                                        <span class="sch-tt__cb<?php echo $cell ? '' : ' is-empty'; ?>">
                                            <?php if ($cell) : ?>
                                                <b><?php echo esc_html((string) $cell->subject_name); ?></b>
                                                <span><?php echo esc_html($cell->grade_level . ' / ' . $cell->section); ?></span>
                                            <?php else : ?>
                                                <span><?php esc_html_e('فراغ', 'school-system'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else : ?>
            <div class="sch-tt__work">
                <div class="sch-tt__c sch-tt__basket">
                    <span class="sch-tt__ct">
                        <b><?php esc_html_e('سلة الحصص', 'school-system'); ?></b>
                        <span><?php esc_html_e('اختر مادة ثم اضغط خانة — أو اسحبها إليها.', 'school-system'); ?></span>
                    </span>

                    <?php
                    $placed = [];
                    foreach ($grid as $prow) {
                        foreach ($prow as $c) {
                            $placed[(int) $c->subject_id] = ($placed[(int) $c->subject_id] ?? 0) + 1;
                        }
                    }
                    ?>
                    <?php foreach ($demand as $r) :
                        $left = max(0, (int) $r->weekly - (int) ($placed[(int) $r->subject_id] ?? 0));
                        $on   = $pick === (int) $r->subject_id; ?>
                        <a class="sch-tt__bi<?php echo $on ? ' is-on' : ''; ?><?php echo $left === 0 ? ' is-done' : ''; ?>"
                           href="<?php echo esc_url($here(['pick' => $on ? 0 : (int) $r->subject_id])); ?>"
                           data-tt-pick="<?php echo esc_attr((string) $r->subject_id); ?>" draggable="true">
                            <span class="sch-tt__tone" aria-hidden="true"></span>
                            <span class="sch-tt__bt">
                                <b><?php echo esc_html((string) $r->subject_name); ?></b>
                                <span><?php echo esc_html($r->teacher_user_id ? '' : __('بلا معلم', 'school-system')); ?></span>
                            </span>
                            <span class="sch-tt__bn" dir="ltr"><?php echo esc_html($left . '/' . (int) $r->weekly); ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($demand === []) : ?>
                        <div class="sch-empty"><strong><?php esc_html_e('لا نصاب بعد', 'school-system'); ?></strong></div>
                    <?php endif; ?>
                </div>

                <div class="sch-tt__c">
                    <div class="sch-tt__gridwrap">
                        <table class="sch-tt__grid">
                            <thead><tr>
                                <th><?php esc_html_e('الحصة', 'school-system'); ?></th>
                                <?php foreach ($days as $label) : ?><th><?php echo esc_html($label); ?></th><?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                            <?php for ($p = 1; $p <= $bell->periods; $p++) : ?>
                                <tr>
                                    <th><span class="sch-tt__pno"><b dir="ltr"><?php echo esc_html((string) $p); ?></b><span dir="ltr"><?php echo esc_html(SCH_TT::period_hhmm($p, $stage)); ?></span></span></th>
                                    <?php foreach (array_keys($days) as $d) :
                                        $cell = $grid[$d][$p] ?? null;
                                        $mark = $health['cells'][$d . '|' . $p] ?? '';
                                        $cls_ = $cell ? '' : ' is-empty';
                                        if ($cell && (int) $cell->clash === 1) {
                                            $cls_ = ' is-clash';
                                        } elseif ($cell && $hl === 'late' && $mark === 'late') {
                                            $cls_ = ' is-late';
                                        }
                                        if ($cell && !$cell->teacher_user_id) {
                                            $cls_ .= ' is-noteacher';
                                        }
                                        if ($cell && (int) $cell->locked === 1) {
                                            $cls_ .= ' is-lock';
                                        }
                                        if (!$cell && $hl === 'missing') {
                                            $cls_ .= ' is-late';
                                        }
                                        ?>
                                        <td class="sch-tt__cell">
                                            <?php if (!$live) : ?>
                                                <span class="sch-tt__cb<?php echo esc_attr($cls_); ?>">
                                                    <b><?php echo esc_html($cell ? (string) $cell->subject_name : '—'); ?></b>
                                                    <?php if ($cell) : ?><span><?php echo esc_html($cell->teacher_name ?: __('بلا معلم', 'school-system')); ?></span><?php endif; ?>
                                                </span>
                                            <?php else : ?>
                                                <form method="post">
                                                    <?php wp_nonce_field('sch_tt_place', '_sch_nonce'); ?>
                                                    <input type="hidden" name="sch_action" value="tt_place">
                                                    <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $pick); ?>">
                                                    <input type="hidden" name="day" value="<?php echo esc_attr((string) $d); ?>">
                                                    <input type="hidden" name="period" value="<?php echo esc_attr((string) $p); ?>">
                                                    <?php $keep(); ?>

                                                    <button class="sch-tt__cb<?php echo esc_attr($cls_); ?>"
                                                            data-tt-cell="<?php echo esc_attr($d . '|' . $p); ?>"
                                                            data-tt-subject="<?php echo esc_attr($cell ? (string) $cell->subject_id : ''); ?>"
                                                            <?php echo $cell ? 'draggable="true"' : ''; ?>
                                                            title="<?php echo esc_attr($pick > 0
                                                                ? __('ضع المادة المختارة هنا', 'school-system')
                                                                : ($cell ? __('اضغط لتفريغ الخانة', 'school-system') : __('اختر مادة من السلة أولًا', 'school-system'))); ?>">
                                                        <?php if ($cell) : ?>
                                                            <b><?php echo esc_html((string) $cell->subject_name); ?></b>
                                                            <span><?php echo esc_html($cell->teacher_name ?: __('بلا معلم', 'school-system')); ?></span>
                                                        <?php else : ?>
                                                            <span><?php echo esc_html($pick > 0 ? __('ضع هنا', 'school-system') : '—'); ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>

                                <?php if ($p === $bell->break_after && $bell->break_len > 0) :
                                    $bs = $bell->start_min + $p * $bell->period_len; ?>
                                    <tr class="sch-tt__brk">
                                        <td colspan="<?php echo esc_attr((string) (count($days) + 1)); ?>">
                                            <span class="sch-tt__brkin">
                                                <b><?php esc_html_e('الفسحة', 'school-system'); ?></b>
                                                <code dir="ltr"><?php echo esc_html(SCH_TT::hhmm($bs) . ' — ' . SCH_TT::hhmm($bs + $bell->break_len)); ?></code>
                                                <span><?php echo esc_html(sprintf(
                                                    /* translators: %d: عدد الدقائق */
                                                    __('%d دقيقة · للجميع', 'school-system'),
                                                    $bell->break_len
                                                )); ?></span>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($live) : ?>
                <div class="sch-tt__c">
                    <span class="sch-tt__ct">
                        <b><?php esc_html_e('النشر على المدرسة', 'school-system'); ?></b>
                        <span><?php esc_html_e('هذا الشهر يتكرر أسبوعيًا حتى تغيّره — ونسخه على بقية الشعب يوفّر التكرار.', 'school-system'); ?></span>
                    </span>

                    <div class="sch-tt__pub">
                        <div class="sch-tt__pubi">
                            <b><?php esc_html_e('نسخ إلى بقية شعب الصف', 'school-system'); ?></b>
                            <span><?php esc_html_e('المواد تُنسخ والإسناد يتبع معلم كل شعبة — نسخ المعلم معه يعني تعارضًا في كل خانة.', 'school-system'); ?></span>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_copy', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_copy">
                                <?php $keep(); ?>
                                <button class="sch-tt__b"><?php esc_html_e('نسخ', 'school-system'); ?></button>
                            </form>
                        </div>

                        <div class="sch-tt__pubi">
                            <b><?php esc_html_e('توليد للمدرسة كلّها', 'school-system'); ?></b>
                            <span><?php echo esc_html(sprintf(
                                /* translators: %d: عدد الشعب */
                                __('%d شعبة دفعة واحدة — وهو الوحيد الذي يضمن ألّا يتعارض معلم مع نفسه.', 'school-system'),
                                count($classes)
                            )); ?></span>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_generate">
                                <input type="hidden" name="scope" value="school">
                                <?php $keep(); ?>
                                <button class="sch-tt__b"><?php esc_html_e('ولّد للجميع', 'school-system'); ?></button>
                            </form>
                        </div>

                        <div class="sch-tt__pubi">
                            <b><?php esc_html_e('اعتماد ونشر', 'school-system'); ?></b>
                            <span><?php echo esc_html($can_pub
                                ? __('لا يُنشر جدول فيه تعارض أو حصة بلا معلم — يراه المعلمون وأولياء الأمور فور نشره.', 'school-system')
                                : __('الاعتماد بيد المدير — أبلغه أن الجدول جاهز.', 'school-system')); ?></span>
                            <?php if ($can_pub) : ?>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e('اعتماد الجدول ونشره؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_tt_publish', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="tt_publish">
                                    <?php $keep(); ?>
                                    <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('اعتماد ونشر', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ══ الترقيم السفلي ══ -->
    <div class="sch-tt__bar2">
        <?php if ($step > 1) : ?>
            <a class="sch-tt__b" href="<?php echo esc_url($here(['step' => $step - 1])); ?>">
                <span aria-hidden="true">‹</span> <?php echo esc_html($steps[$step - 1][0]); ?>
            </a>
        <?php else : ?>
            <a class="sch-tt__b" aria-disabled="true" href="<?php echo esc_url($here(['step' => 1])); ?>"><?php esc_html_e('البداية', 'school-system'); ?></a>
        <?php endif; ?>

        <span class="sch-tt__hint"><?php echo esc_html(match ($step) {
            1       => __('اختر المرحلة والشعبة — الباقي معبّأ مسبقًا', 'school-system'),
            2       => sprintf(
                /* translators: %d: سعة الأسبوع */
                __('المجموع لازم يساوي %d حصة', 'school-system'),
                $cap
            ),
            3       => __('راجع الإسناد والقيود ثم ولّد', 'school-system'),
            default => __('اختر مادة من السلة ثم اضغط خانة · واضغط خانة مملوءة لتفريغها', 'school-system'),
        }); ?></span>

        <?php if ($step < 4) : ?>
            <a class="sch-tt__b sch-tt__b--go" href="<?php echo esc_url($here(['step' => $step + 1])); ?>">
                <?php echo esc_html($steps[$step + 1][0]); ?> <span aria-hidden="true">›</span>
            </a>
        <?php else : ?>
            <a class="sch-tt__b" aria-disabled="true" href="<?php echo esc_url($here(['step' => 4])); ?>"><?php esc_html_e('النهاية', 'school-system'); ?></a>
        <?php endif; ?>
    </div>
</div>
