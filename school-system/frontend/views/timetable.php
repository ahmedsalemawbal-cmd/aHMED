<?php
/**
 * بناء الجداول — أربع خطوات في شاشة واحدة.
 *
 * الشاشة تبني **خطة**، والنشر ينسخها إلى الجدول المنشور. ولذلك لا يرى المعلم
 * ولا وليّ الأمر شيئًا حتى تُعتمد — فلا يستيقظ أحد على جدول تغيّر ثلاث مرات ليلًا.
 *
 * كل خطوة رابط حقيقي (`?step=`)، وكل زرٍّ في التصميم فعلٌ فوريّ: المِزواد
 * ليس حقلًا يُملأ ثم يُحفظ، بل زرَّان يُرسلان القيمة التالية. ولذلك اختفى زرّ
 * «حفظ» من الإعدادات — التصميم لا يعرفه، والضغطة تكفي.
 *
 * والسحب والإفلات في الشبكة تحسينٌ فوق «اختر مادة ثم اضغط خانة» — فمن لا
 * جافاسكربت عنده يبني جدوله كاملًا، ومن يستعمل لوحة المفاتيح كذلك.
 */
declare(strict_types=1);
defined('ABSPATH') || exit;

$can_pub = current_user_can('sch_approve_timetable');
$can_sub = current_user_can('sch_manage_subjects');

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
$sec_lb = $class->grade_level . ' / ' . $class->section;

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

/** لون المادة: ثابتٌ لمعرّفها، فلا يتنقّل بين الشاشات ولا بين الأشهر. */
$tone = static fn (int $subject_id): string => 'sch-tt__tone sch-tt__tone--' . ($subject_id % 8);

/** موضع الشاشة يعود مع كل نموذج — ولا يُعاد الوكيل للخطوة الأولى بعد كل ضغطة. */
$keep = static function (array $over = []) use ($ym, $cls, $step, $lens, $plan_id): void {
    $state = $over + [
        'm'        => $ym,
        'cls'      => (string) $cls,
        'step'     => (string) $step,
        'lens'     => $lens,
        'plan_id'  => (string) $plan_id,
        'class_id' => (string) $cls,
    ];

    foreach ($state as $key => $value) {
        printf(
            '<input type="hidden" name="%s" value="%s">',
            esc_attr((string) $key),
            esc_attr((string) $value)
        );
    }
};

/** حقول جرس اليوم الستّة — كل مِزواد يرسلها كاملةً ويبدّل واحدًا منها. */
$bell_fields = [
    'stage'       => $stage,
    'periods'     => (string) $bell->periods,
    'period_len'  => (string) $bell->period_len,
    'start'       => SCH_TT::hhmm((int) $bell->start_min),
    'break_after' => (string) $bell->break_after,
    'break_len'   => (string) $bell->break_len,
    'days_mask'   => (string) $bell->days_mask,
];

/**
 * حقلٌ قيمته `null` يُحذَف لا يُطبَع.
 *
 * الزرّ الذي يحمل اسم الحقل وقيمته التالية يجب ألّا يجد حقلًا مخفيًّا بالاسم
 * نفسه: اسمان متساويان في نموذج واحد يجعل النتيجة رهينة ترتيب العناصر، وهو
 * عقدٌ لم يكتبه أحد.
 */
$bell_hidden = static function (array $over = []) use ($bell_fields): void {
    foreach ($over + $bell_fields as $key => $value) {
        if ($value === null) {
            continue;
        }

        printf(
            '<input type="hidden" name="%s" value="%s">',
            esc_attr((string) $key),
            esc_attr((string) $value)
        );
    }
};

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
            <p><?php esc_html_e('اختر المرحلة والشعبة، حدّد النصاب، ثم ولّد الجدول — الأسبوع يتكرر كل شهر وله نسخته الخاصة.', 'school-system'); ?></p>
        </div>

        <div class="sch-tt__acts">
            <span class="sch-tt__span">
                <b><?php echo esc_html((SCH_Classes::STAGES[$stage] ?? $stage) . ' — ' . $sec_lb); ?></b>
                <i aria-hidden="true"></i>
                <code dir="ltr"><?php echo esc_html(SCH_TT::hhmm((int) $bell->start_min) . ' → ' . $span['end']); ?></code>
            </span>

            <?php if ($live) : ?>
                <form method="post">
                    <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_bell">
                    <?php $bell_hidden(); ?>
                    <?php $keep(); ?>
                    <button class="sch-tt__b" title="<?php esc_attr_e('يُثبَّت إيقاع اليوم قالبًا لكل شعب المرحلة', 'school-system'); ?>">
                        <?php esc_html_e('حفظ كقالب', 'school-system'); ?>
                    </button>
                </form>

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
                        <?php $keep(['step' => '4']); ?>
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
        <?php foreach ($months as $key => $label) : ?>
            <a class="sch-tt__m<?php echo $key === $ym ? ' is-on' : ''; ?><?php echo isset($filled[$key]) ? ' is-filled' : ''; ?>"
               href="<?php echo esc_url(add_query_arg(['m' => $key, 'cls' => $cls, 'step' => $step, 'lens' => $lens], $base)); ?>">
                <i aria-hidden="true"></i><?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
        <span class="sch-tt__gap" aria-hidden="true"></span>
        <span class="sch-tt__mnote"><?php esc_html_e('نفس الأسبوع يتكرر داخل الشهر المحدد', 'school-system'); ?></span>
    </div>

    <?php // ══════════════════ ① الإعدادات ══════════════════ ?>
    <?php if ($step === 1) : ?>
        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <b class="sch-tt__cth"><?php esc_html_e('إعدادات جاهزة لكل مرحلة', 'school-system'); ?></b>
                <span class="sch-tt__hint"><?php esc_html_e('اضغط المرحلة ويتعبّأ اليوم كامل — يمكنك التعديل بعدها', 'school-system'); ?></span>
            </div>

            <div class="sch-tt__presets">
                <?php
                /* أول شعبة في كل مرحلة — ضغط المرحلة ينقل إليها، فالإيقاع
                   يُحفظ لمرحلته لا للمرحلة المعروضة. المرحلة بلا شعب تبقى
                   على الشعبة الحالية ويُحفظ إيقاعها استعدادًا لأول شعبة. */
                $first_of = [];
                foreach ($classes as $c) {
                    $first_of[(string) $c->stage] ??= (int) $c->id;
                }
                ?>
                <?php foreach (SCH_TT::PRESETS as $slug => $p) :
                    $on   = $slug === $stage;
                    $end  = SCH_TT::hhmm($p['start'] + $p['periods'] * $p['len'] + $p['break_len']);
                    $goto = (string) ($first_of[$slug] ?? $cls); ?>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_bell">
                        <?php $bell_hidden([
                            'stage'       => $slug,
                            'periods'     => (string) $p['periods'],
                            'period_len'  => (string) $p['len'],
                            'start'       => SCH_TT::hhmm($p['start']),
                            'break_after' => (string) $p['break_after'],
                            'break_len'   => (string) $p['break_len'],
                        ]); ?>
                        <?php $keep(['cls' => $goto, 'class_id' => $goto]); ?>
                        <button class="sch-tt__preset<?php echo $on ? ' is-on' : ''; ?>"
                                aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
                                title="<?php echo esc_attr(sprintf(
                                    /* translators: %s: اسم المرحلة */
                                    __('انتقل إلى %s وطبّق إيقاعها', 'school-system'),
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
            <b class="sch-tt__cth"><?php esc_html_e('اليوم الدراسي', 'school-system'); ?></b>

            <div class="sch-tt__day">
                <?php foreach ([
                    ['periods',     __('عدد الحصص', 'school-system'),        (int) $bell->periods,     1,  14],
                    ['period_len',  __('مدة الحصة (د)', 'school-system'),    (int) $bell->period_len,  10, 120],
                    ['break_after', __('الفسحة بعد الحصة', 'school-system'), (int) $bell->break_after, 0,  13],
                    ['break_len',   __('مدة الفسحة (د)', 'school-system'),   (int) $bell->break_len,   0,  120],
                ] as [$name, $label, $value, $min, $max]) : ?>
                    <div class="sch-tt__f">
                        <span class="sch-tt__flab" id="ttl-<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></span>
                        <form method="post" class="sch-tt__num" aria-labelledby="ttl-<?php echo esc_attr($name); ?>">
                            <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="tt_bell">
                            <?php $bell_hidden([$name => null]); ?>
                            <?php $keep(); ?>
                            <button class="sch-tt__pm" name="<?php echo esc_attr($name); ?>"
                                    value="<?php echo esc_attr((string) max($min, $value - 1)); ?>"
                                    <?php disabled($value <= $min); ?>
                                    aria-label="<?php echo esc_attr(sprintf(
                                        /* translators: %s: اسم الحقل */
                                        __('إنقاص %s', 'school-system'),
                                        $label
                                    )); ?>">−</button>
                            <b dir="ltr"><?php echo esc_html((string) $value); ?></b>
                            <button class="sch-tt__pm" name="<?php echo esc_attr($name); ?>"
                                    value="<?php echo esc_attr((string) min($max, $value + 1)); ?>"
                                    <?php disabled($value >= $max); ?>
                                    aria-label="<?php echo esc_attr(sprintf(
                                        /* translators: %s: اسم الحقل */
                                        __('زيادة %s', 'school-system'),
                                        $label
                                    )); ?>">+</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <div class="sch-tt__f sch-tt__f--time">
                    <label class="sch-tt__flab" for="tt-start"><?php esc_html_e('بداية الحصة الأولى', 'school-system'); ?></label>
                    <form method="post">
                        <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="tt_bell">
                        <?php $bell_hidden(['start' => null]); ?>
                        <?php $keep(); ?>
                        <input id="tt-start" class="sch-tt__time" type="time" name="start" dir="ltr" data-tt-submit
                               value="<?php echo esc_attr(SCH_TT::hhmm((int) $bell->start_min)); ?>">
                        <button class="sch-sr"><?php esc_html_e('حفظ البداية', 'school-system'); ?></button>
                    </form>
                </div>

                <div class="sch-tt__f sch-tt__f--days">
                    <span class="sch-tt__flab" id="ttl-days"><?php esc_html_e('أيام الدراسة', 'school-system'); ?></span>
                    <div class="sch-tt__days" role="group" aria-labelledby="ttl-days">
                        <?php foreach (SCH_TT::WEEK as $n => $label) :
                            $bit  = 1 << ($n - 1);
                            $on   = (bool) ((int) $bell->days_mask & $bit);
                            $next = $on ? ((int) $bell->days_mask & ~$bit) : ((int) $bell->days_mask | $bit); ?>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_bell', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_bell">
                                <?php $bell_hidden(['days_mask' => (string) $next]); ?>
                                <?php $keep(); ?>
                                <?php // يومٌ واحد على الأقل — أسبوعٌ بلا دوام لا يقبله المولّد ولا معنى له. ?>
                                <button class="sch-tt__d<?php echo $on ? ' is-on' : ''; ?>"
                                        <?php disabled($on && $next === 0); ?>
                                        aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html($label); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="sch-tt__rib">
                <div class="sch-tt__ribh">
                    <b><?php esc_html_e('شريط اليوم', 'school-system'); ?></b>
                    <span class="sch-tt__ribm">
                        <em><?php esc_html_e('الخروج', 'school-system'); ?></em>
                        <code dir="ltr"><?php echo esc_html($span['end']); ?></code>
                        <i aria-hidden="true"></i>
                        <em><?php esc_html_e('إجمالي الدوام', 'school-system'); ?></em>
                        <b dir="ltr"><?php echo esc_html($span['length']); ?></b>
                    </span>
                </div>

                <div class="sch-tt__ribbon">
                    <?php foreach ($ribbon as $r) :
                        $len = $r['kind'] === 'break' ? (int) $bell->break_len : (int) $bell->period_len; ?>
                        <span class="sch-tt__slot<?php echo $r['kind'] === 'break' ? ' is-break' : ''; ?>"
                              style="flex:<?php echo esc_attr((string) max(1, $len)); ?>"
                              title="<?php echo esc_attr($r['from'] . ' — ' . $r['to']); ?>">
                            <b><?php echo esc_html($r['kind'] === 'break' ? __('فسحة', 'school-system') : (string) $r['no']); ?></b>
                            <span dir="ltr"><?php echo esc_html($r['from']); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="sch-tt__c">
            <div class="sch-tt__ch">
                <span class="sch-tt__ct">
                    <b><?php esc_html_e('الشعبة', 'school-system'); ?></b>
                    <span><?php esc_html_e('اختر شعبة لتبني جدولها — وبعد الاعتماد تنسخه لبقية الشعب بضغطة', 'school-system'); ?></span>
                </span>
                <span class="sch-tt__row">
                    <span class="sch-tt__hint"><?php echo esc_html(sprintf(
                        /* translators: %d: عدد الشعب */
                        __('%d شعبة في المدرسة', 'school-system'),
                        count($classes)
                    )); ?></span>
                    <a class="sch-tt__b sch-tt__b--sm" href="<?php echo esc_url(SCH_Dashboard::url('classes')); ?>">
                        <?php esc_html_e('+ شعبة', 'school-system'); ?>
                    </a>
                </span>
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
                        <div class="sch-tt__secs">
                            <?php foreach ($list as $c) : ?>
                                <a class="sch-tt__sec<?php echo (int) $c->id === $cls ? ' is-on' : ''; ?>"
                                   href="<?php echo esc_url(add_query_arg(['m' => $ym, 'cls' => (int) $c->id, 'step' => $step, 'lens' => $lens], $base)); ?>">
                                    <?php echo esc_html($c->grade_level . ' / ' . $c->section); ?>
                                    <em dir="ltr"><?php echo esc_html($c->enrolled . '/' . $c->capacity); ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php // ══════════════════ ② المواد والنصاب ══════════════════ ?>
    <?php if ($step === 2) :
        $tone_q = $sum === $cap ? '' : ($sum > $cap ? 'is-over' : 'is-under'); ?>
        <div class="sch-tt__c sch-tt__quotah">
            <span class="sch-tt__ct">
                <b><?php echo esc_html(sprintf(
                    /* translators: %s: اسم الشعبة */
                    __('نصاب المواد — %s', 'school-system'),
                    $sec_lb
                )); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: %d: سعة الأسبوع */
                    __('وزّع %d حصة أسبوعية على المواد. القالب معبّأ مسبقًا.', 'school-system'),
                    $cap
                )); ?></span>
            </span>

            <span class="sch-tt__meter">
                <span class="sch-tt__meterh">
                    <em><?php esc_html_e('الموزّع', 'school-system'); ?></em>
                    <b class="sch-tt__sum <?php echo esc_attr($tone_q); ?>" dir="ltr"><?php echo esc_html($sum . ' / ' . $cap); ?></b>
                </span>
                <span class="sch-tt__bar <?php echo esc_attr($tone_q); ?>">
                    <span style="width:<?php echo esc_attr((string) min(100, $cap > 0 ? $sum / $cap * 100 : 0)); ?>%"></span>
                </span>
                <span class="sch-tt__mnote <?php echo esc_attr($tone_q); ?>"><?php echo esc_html($sum === $cap
                    ? __('مطابق تمامًا لعدد الحصص', 'school-system')
                    : ($sum > $cap
                        ? sprintf(
                            /* translators: %d: الفرق */
                            __('زائد %d حصة — لن تجد كلّها مكانًا', 'school-system'),
                            $sum - $cap
                        )
                        : sprintf(
                            /* translators: %d: الفرق */
                            __('ناقص %d حصة — ستبقى خانات فارغة', 'school-system'),
                            $cap - $sum
                        ))); ?></span>
            </span>

            <form method="post">
                <?php wp_nonce_field('sch_tt_seed', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="tt_seed">
                <?php $keep(); ?>
                <button class="sch-tt__b"><?php esc_html_e('استرجاع القالب', 'school-system'); ?></button>
            </form>
        </div>

        <div class="sch-tt__subs">
            <?php foreach ($rows as $r) :
                $n   = (int) $r->weekly;
                $sid = (int) $r->subject_id; ?>
                <div class="sch-tt__sub<?php echo $n === 0 ? ' is-zero' : ''; ?>">
                    <span class="sch-tt__sh">
                        <span class="<?php echo esc_attr($tone($sid)); ?>" aria-hidden="true"></span>
                        <span class="sch-tt__st">
                            <b><?php echo esc_html((string) $r->subject_name); ?></b>
                            <span><?php echo esc_html(SCH_TT::is_core((string) $r->subject_name)
                                ? __('مادة أساسية', 'school-system')
                                : __('مادة إثرائية', 'school-system')); ?></span>
                        </span>
                    </span>

                    <span class="sch-tt__num sch-tt__num--lg">
                        <form method="post">
                            <?php wp_nonce_field('sch_tt_quota', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="tt_quota">
                            <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $sid); ?>">
                            <input type="hidden" name="weekly" value="<?php echo esc_attr((string) max(0, $n - 1)); ?>">
                            <?php $keep(); ?>
                            <button class="sch-tt__pm" <?php disabled($n <= 0); ?> aria-label="<?php echo esc_attr(sprintf(
                                /* translators: %s: اسم المادة */
                                __('إنقاص نصاب %s', 'school-system'),
                                (string) $r->subject_name
                            )); ?>">−</button>
                        </form>
                        <b dir="ltr"><?php echo esc_html((string) $n); ?></b>
                        <form method="post">
                            <?php wp_nonce_field('sch_tt_quota', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="tt_quota">
                            <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $sid); ?>">
                            <input type="hidden" name="weekly" value="<?php echo esc_attr((string) ($n + 1)); ?>">
                            <?php $keep(); ?>
                            <button class="sch-tt__pm" aria-label="<?php echo esc_attr(sprintf(
                                /* translators: %s: اسم المادة */
                                __('زيادة نصاب %s', 'school-system'),
                                (string) $r->subject_name
                            )); ?>">+</button>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php if ($can_sub) : ?>
                <form method="post" class="sch-tt__add">
                    <?php wp_nonce_field('sch_tt_subject', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_subject">
                    <input type="hidden" name="stage" value="<?php echo esc_attr($stage); ?>">
                    <?php $keep(); ?>
                    <label for="tt-newsub"><?php esc_html_e('إضافة مادة', 'school-system'); ?></label>
                    <input id="tt-newsub" type="text" name="name" required maxlength="80"
                           placeholder="<?php esc_attr_e('اسم المادة', 'school-system'); ?>">
                    <button class="sch-tt__b sch-tt__b--go"><?php esc_html_e('إضافة', 'school-system'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($rows === []) : ?>
            <div class="sch-empty">
                <strong><?php esc_html_e('لا مواد لهذه المرحلة بعد', 'school-system'); ?></strong>
                <?php esc_html_e('اكتب اسم المادة في بطاقة «إضافة مادة» لتبدأ.', 'school-system'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php // ══════════════════ ③ المعلمون والقيود ══════════════════ ?>
    <?php if ($step === 3) :
        $load     = SCH_TT::teacher_load($plan_id);
        $teachers = SCH_TT::teachers();
        $tcap     = max(1, (int) $plan->max_daily) * max(1, count($days)); ?>
        <div class="sch-tt__c sch-tt__c--row">
            <span class="sch-tt__ct">
                <b><?php esc_html_e('إسناد المعلمين', 'school-system'); ?></b>
                <span><?php esc_html_e('لكل مادة معلم. النظام يمنع تعارض المعلم بين شعبتين في نفس الحصة.', 'school-system'); ?></span>
            </span>
            <span class="sch-tt__row">
                <form method="post">
                    <?php wp_nonce_field('sch_tt_auto_assign', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_auto_assign">
                    <?php $keep(); ?>
                    <button class="sch-tt__b sch-tt__b--soft"><?php esc_html_e('إسناد تلقائي للكل', 'school-system'); ?></button>
                </form>
                <a class="sch-tt__b sch-tt__b--sm" href="<?php echo esc_url(SCH_Dashboard::url('employees')); ?>">
                    <?php esc_html_e('+ معلم', 'school-system'); ?>
                </a>
            </span>
        </div>

        <div class="sch-tt__two">
            <div class="sch-tt__c sch-tt__c--flush">
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
                            $lt    = $ratio > 1 ? 'is-over' : ($ratio > 0.8 ? 'is-warn' : ''); ?>
                            <form method="post" class="sch-tt__arow">
                                <?php wp_nonce_field('sch_tt_teacher', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_teacher">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $r->subject_id); ?>">
                                <?php $keep(); ?>

                                <span class="sch-tt__aname">
                                    <span class="<?php echo esc_attr($tone((int) $r->subject_id)); ?>" aria-hidden="true"></span>
                                    <b><?php echo esc_html((string) $r->subject_name); ?></b>
                                </span>

                                <span class="sch-tt__an" dir="ltr"><?php echo esc_html((string) $r->weekly); ?></span>

                                <select class="sch-tt__sel<?php echo $t > 0 ? ' is-on' : ''; ?>" name="teacher_user_id" data-tt-submit
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

                                <span class="sch-tt__load <?php echo esc_attr($lt); ?>">
                                    <span class="sch-tt__ltrack"><span style="width:<?php echo esc_attr((string) min(100, $ratio * 100)); ?>%"></span></span>
                                    <b dir="ltr"><?php echo esc_html($has . '/' . $tcap); ?></b>
                                </span>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sch-tt__side">
                <div class="sch-tt__c">
                    <b class="sch-tt__cth sch-tt__cth--sm"><?php esc_html_e('القيود المفعّلة', 'school-system'); ?></b>

                    <div class="sch-tt__rules">
                        <?php foreach (SCH_TT::RULES as $key => $label) :
                            $on   = in_array($key, $rules, true);
                            $next = $on ? array_values(array_diff($rules, [$key])) : array_merge($rules, [$key]); ?>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_rules', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_rules">
                                <input type="hidden" name="max_daily" value="<?php echo esc_attr((string) $plan->max_daily); ?>">
                                <?php foreach ($next as $rk) : ?>
                                    <input type="hidden" name="rules[]" value="<?php echo esc_attr($rk); ?>">
                                <?php endforeach; ?>
                                <?php $keep(); ?>
                                <button class="sch-tt__rule<?php echo $on ? ' is-on' : ''; ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
                                    <span class="sch-tt__sw" aria-hidden="true"></span>
                                    <span><?php echo esc_html($label); ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sch-tt__c">
                    <b class="sch-tt__cth sch-tt__cth--sm" id="ttl-max"><?php esc_html_e('حد المعلم اليومي', 'school-system'); ?></b>

                    <?php $md = max(1, (int) $plan->max_daily); ?>
                    <div class="sch-tt__num sch-tt__num--lg" role="group" aria-labelledby="ttl-max">
                        <?php foreach ([[-1, '−', __('إنقاص الحد', 'school-system')], [1, '+', __('زيادة الحد', 'school-system')]] as $i => [$delta, $glyph, $aria]) :
                            $to = max(1, min(14, $md + $delta)); ?>
                            <?php if ($i === 1) : ?><b dir="ltr"><?php echo esc_html((string) $md); ?></b><?php endif; ?>
                            <form method="post">
                                <?php wp_nonce_field('sch_tt_rules', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="tt_rules">
                                <input type="hidden" name="max_daily" value="<?php echo esc_attr((string) $to); ?>">
                                <?php foreach ($rules as $rk) : ?>
                                    <input type="hidden" name="rules[]" value="<?php echo esc_attr($rk); ?>">
                                <?php endforeach; ?>
                                <?php $keep(); ?>
                                <button class="sch-tt__pm" <?php disabled($to === $md); ?>
                                        aria-label="<?php echo esc_attr($aria); ?>"><?php echo esc_html($glyph); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <span class="sch-tt__hint"><?php esc_html_e('لا يُسند للمعلم أكثر من هذا العدد في اليوم الواحد.', 'school-system'); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php // ══════════════════ ④ الجدول ══════════════════ ?>
    <?php if ($step === 4) : ?>
        <div class="sch-tt__tools">
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

            <span class="sch-tt__gap" aria-hidden="true"></span>

            <?php foreach ([
                ['conflict', __('تعارضات', 'school-system'),          $health['conflicts']],
                ['missing',  __('نصاب ناقص', 'school-system'),        $health['missing']],
                ['late',     __('حصص ثقيلة متأخرة', 'school-system'), $health['late']],
            ] as [$key, $label, $n]) : ?>
                <a class="sch-tt__chip<?php echo $n > 0 ? ' is-bad' : ''; ?><?php echo $hl === $key ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url($here(['hl' => $hl === $key ? '' : $key])); ?>">
                    <i aria-hidden="true"></i><?php echo esc_html($label); ?>
                    <b dir="ltr"><?php echo esc_html((string) $n); ?></b>
                </a>
            <?php endforeach; ?>

            <?php if ($live) : ?>
                <form method="post">
                    <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_generate">
                    <input type="hidden" name="scope" value="school">
                    <?php $keep(); ?>
                    <button class="sch-tt__b sch-tt__b--go"
                            title="<?php esc_attr_e('التوليد لكل الشعب معًا يمنع تعارض المعلمين — والشعبة الأخيرة لا تجد معلميها محجوزين', 'school-system'); ?>">
                        <?php esc_html_e('توليد من جديد', 'school-system'); ?>
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('<?php esc_attr_e('تفريغ جدول هذه الشعبة؟', 'school-system'); ?>');">
                    <?php wp_nonce_field('sch_tt_wipe', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="tt_wipe">
                    <?php $keep(); ?>
                    <button class="sch-tt__b sch-tt__b--quiet"><?php esc_html_e('تفريغ', 'school-system'); ?></button>
                </form>
            <?php endif; ?>
            <button type="button" class="sch-tt__b" data-sch-print><?php esc_html_e('طباعة A5', 'school-system'); ?></button>
        </div>

        <?php if ($lens === 'school') :
            $day = max(1, (int) ($_GET['day'] ?? (int) array_key_first($days)));
            $per = max(1, min((int) $bell->periods, (int) ($_GET['p'] ?? 1)));
            $map = SCH_TT::school_map($plan_id, $day, $per); ?>
            <div class="sch-tt__c">
                <span class="sch-tt__ct">
                    <b><?php esc_html_e('خريطة المدرسة', 'school-system'); ?></b>
                    <span><?php esc_html_e('كل شعبة وما فيها في هذه اللحظة — الفارغ يعني حصةً بلا مادة.', 'school-system'); ?></span>
                </span>

                <div class="sch-tt__row">
                    <?php foreach ($days as $n => $label) : ?>
                        <a class="sch-tt__b sch-tt__b--sm<?php echo $n === $day ? ' sch-tt__b--go' : ''; ?>"
                           href="<?php echo esc_url($here(['day' => $n, 'p' => $per])); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="sch-tt__row">
                    <?php for ($p = 1; $p <= (int) $bell->periods; $p++) : ?>
                        <a class="sch-tt__b sch-tt__b--sm<?php echo $p === $per ? ' sch-tt__b--go' : ''; ?>"
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
                        <select class="sch-tt__sel" name="t" data-tt-submit aria-label="<?php esc_attr_e('المعلم', 'school-system'); ?>">
                            <?php foreach ($tall as $u) : ?>
                                <option value="<?php echo esc_attr((string) $u->ID); ?>" <?php selected($tid, (int) $u->ID); ?>>
                                    <?php echo esc_html((string) $u->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="sch-tt__b"><?php esc_html_e('عرض', 'school-system'); ?></button>
                    </form>
                </div>

                <?php
                $tt_grid_cols = 'grid-template-columns:96px repeat(' . count($days) . ',minmax(0,1fr))';
                ?>
                <div class="sch-tt__sheet">
                    <div class="sch-tt__rowsx">
                        <div class="sch-tt__gr" style="<?php echo esc_attr($tt_grid_cols); ?>">
                            <span class="sch-tt__gh"><?php esc_html_e('الحصة', 'school-system'); ?></span>
                            <?php foreach ($days as $label) : ?>
                                <span class="sch-tt__gd"><?php echo esc_html($label); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <?php for ($p = 1; $p <= (int) $bell->periods; $p++) : ?>
                            <div class="sch-tt__gr" style="<?php echo esc_attr($tt_grid_cols); ?>">
                                <span class="sch-tt__pno">
                                    <b dir="ltr"><?php echo esc_html((string) $p); ?></b>
                                    <span dir="ltr"><?php echo esc_html(SCH_TT::period_hhmm($p, $stage)); ?></span>
                                </span>
                                <?php foreach (array_keys($days) as $d) :
                                    $cell = $tgrid[$d][$p] ?? null; ?>
                                    <span class="sch-tt__cb<?php echo $cell ? '' : ' is-empty'; ?>">
                                        <?php if ($cell) : ?>
                                            <b><?php echo esc_html((string) $cell->subject_name); ?></b>
                                            <span><?php echo esc_html($cell->grade_level . ' / ' . $cell->section); ?></span>
                                        <?php else : ?>
                                            <span><?php esc_html_e('فراغ', 'school-system'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($p === (int) $bell->break_after && (int) $bell->break_len > 0) :
                                $bs = (int) $bell->start_min + $p * (int) $bell->period_len; ?>
                                <div class="sch-tt__brk">
                                    <b><?php esc_html_e('الفسحة', 'school-system'); ?></b>
                                    <code dir="ltr"><?php echo esc_html(SCH_TT::hhmm($bs) . ' — ' . SCH_TT::hhmm($bs + (int) $bell->break_len)); ?></code>
                                    <i aria-hidden="true"></i>
                                    <span><?php echo esc_html(sprintf(
                                        /* translators: %d: عدد الدقائق */
                                        __('%d دقيقة · للجميع', 'school-system'),
                                        (int) $bell->break_len
                                    )); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

        <?php else :
            $placed = [];
            foreach ($grid as $prow) {
                foreach ($prow as $c) {
                    $placed[(int) $c->subject_id] = ($placed[(int) $c->subject_id] ?? 0) + 1;
                }
            }
            $tt_grid_cols = 'grid-template-columns:96px repeat(' . count($days) . ',minmax(0,1fr))';
            $later_months = max(0, count($months) - array_search($ym, array_keys($months), true) - 1);
            $peers        = 0;
            foreach ($classes as $c) {
                if ((int) $c->id !== $cls
                    && (string) $c->stage === $stage
                    && (string) $c->grade_level === (string) $class->grade_level) {
                    $peers++;
                }
            }
            ?>
            <div class="sch-tt__work">
                <div class="sch-tt__c sch-tt__sheet">
                    <div class="sch-tt__printh">
                        <b><?php echo esc_html(sprintf(
                            /* translators: 1: اسم الشعبة 2: اسم المرحلة */
                            __('جدول %1$s — %2$s', 'school-system'),
                            $sec_lb,
                            SCH_Classes::STAGES[$stage] ?? $stage
                        )); ?></b>
                        <span><?php echo esc_html($months[$ym] . ' · '
                            . SCH_TT::hhmm((int) $bell->start_min) . ' → ' . $span['end']); ?></span>
                    </div>

                    <div class="sch-tt__rowsx">
                        <div class="sch-tt__gr" style="<?php echo esc_attr($tt_grid_cols); ?>">
                            <span class="sch-tt__gh"><?php esc_html_e('الحصة', 'school-system'); ?></span>
                            <?php foreach ($days as $label) : ?>
                                <span class="sch-tt__gd"><?php echo esc_html($label); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <?php for ($p = 1; $p <= (int) $bell->periods; $p++) : ?>
                            <div class="sch-tt__gr" style="<?php echo esc_attr($tt_grid_cols); ?>">
                                <span class="sch-tt__pno">
                                    <b dir="ltr"><?php echo esc_html((string) $p); ?></b>
                                    <span dir="ltr"><?php echo esc_html(SCH_TT::period_hhmm($p, $stage)); ?></span>
                                </span>

                                <?php foreach (array_keys($days) as $d) :
                                    $cell = $grid[$d][$p] ?? null;
                                    $mark = $health['cells'][$d . '|' . $p] ?? '';
                                    $cbc  = $cell ? '' : ' is-empty';
                                    if ($cell && (int) $cell->clash === 1) {
                                        $cbc = ' is-clash';
                                    } elseif ($cell && $hl === 'late' && $mark === 'late') {
                                        $cbc = ' is-late';
                                    }
                                    if ($cell && !$cell->teacher_user_id) {
                                        $cbc .= ' is-noteacher';
                                    }
                                    if ($cell && (int) $cell->locked === 1) {
                                        $cbc .= ' is-lock';
                                    }
                                    if (!$cell && $hl === 'missing') {
                                        $cbc .= ' is-late';
                                    }
                                    ?>
                                    <?php if (!$live) : ?>
                                        <span class="sch-tt__cb<?php echo esc_attr($cbc); ?>">
                                            <b><?php echo esc_html($cell ? (string) $cell->subject_name : '—'); ?></b>
                                            <?php if ($cell) : ?><span><?php echo esc_html($cell->teacher_name ?: __('بلا معلم', 'school-system')); ?></span><?php endif; ?>
                                        </span>
                                    <?php else : ?>
                                        <form method="post" class="sch-tt__cf">
                                            <?php wp_nonce_field('sch_tt_place', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="tt_place">
                                            <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $pick); ?>">
                                            <input type="hidden" name="day" value="<?php echo esc_attr((string) $d); ?>">
                                            <input type="hidden" name="period" value="<?php echo esc_attr((string) $p); ?>">
                                            <?php $keep(); ?>

                                            <button class="sch-tt__cb<?php echo esc_attr($cbc); ?>"
                                                    data-tt-cell="<?php echo esc_attr($d . '|' . $p); ?>"
                                                    data-tt-subject="<?php echo esc_attr($cell ? (string) $cell->subject_id : ''); ?>"
                                                    <?php echo $cell ? 'draggable="true"' : ''; ?>
                                                    title="<?php echo esc_attr($pick > 0
                                                        ? __('ضع المادة المختارة هنا', 'school-system')
                                                        : ($cell ? __('اضغط لتفريغ الخانة', 'school-system') : __('اختر مادة من السلة أولًا', 'school-system'))); ?>">
                                                <?php if ($cell) : ?>
                                                    <b><?php echo esc_html((string) $cell->subject_name); ?></b>
                                                    <span><?php echo esc_html($cell->teacher_name ?: __('بلا معلم', 'school-system')); ?></span>
                                                    <?php if ((int) $cell->clash === 1) : ?><i class="sch-tt__flag" aria-hidden="true"></i><?php endif; ?>
                                                <?php else : ?>
                                                    <span><?php echo esc_html($pick > 0 ? __('ضع هنا', 'school-system') : '—'); ?></span>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($p === (int) $bell->break_after && (int) $bell->break_len > 0) :
                                $bs = (int) $bell->start_min + $p * (int) $bell->period_len; ?>
                                <div class="sch-tt__brk">
                                    <b><?php esc_html_e('الفسحة', 'school-system'); ?></b>
                                    <code dir="ltr"><?php echo esc_html(SCH_TT::hhmm($bs) . ' — ' . SCH_TT::hhmm($bs + (int) $bell->break_len)); ?></code>
                                    <i aria-hidden="true"></i>
                                    <span><?php echo esc_html(sprintf(
                                        /* translators: %d: عدد الدقائق */
                                        __('%d دقيقة · للجميع', 'school-system'),
                                        (int) $bell->break_len
                                    )); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="sch-tt__side">
                    <div class="sch-tt__c">
                        <div class="sch-tt__ch">
                            <b class="sch-tt__cth sch-tt__cth--sm"><?php esc_html_e('سلة الحصص', 'school-system'); ?></b>
                            <span class="sch-tt__hint"><?php esc_html_e('اسحبها للخلية', 'school-system'); ?></span>
                        </div>

                        <div class="sch-tt__basket">
                            <?php foreach ($demand as $r) :
                                $left = max(0, (int) $r->weekly - (int) ($placed[(int) $r->subject_id] ?? 0));
                                $on   = $pick === (int) $r->subject_id; ?>
                                <a class="sch-tt__bi<?php echo $on ? ' is-on' : ''; ?><?php echo $left === 0 ? ' is-done' : ''; ?>"
                                   href="<?php echo esc_url($here(['pick' => $on ? 0 : (int) $r->subject_id])); ?>"
                                   data-tt-pick="<?php echo esc_attr((string) $r->subject_id); ?>" draggable="true">
                                    <span class="<?php echo esc_attr($tone((int) $r->subject_id)); ?>" aria-hidden="true"></span>
                                    <span class="sch-tt__bt">
                                        <b><?php echo esc_html((string) $r->subject_name); ?></b>
                                        <span><?php echo esc_html($r->teacher_name ?: __('بلا معلم', 'school-system')); ?></span>
                                    </span>
                                    <b class="sch-tt__bn" dir="ltr"><?php echo esc_html($left . '/' . (int) $r->weekly); ?></b>
                                </a>
                            <?php endforeach; ?>

                            <?php if ($demand === []) : ?>
                                <div class="sch-empty"><strong><?php esc_html_e('لا نصاب بعد', 'school-system'); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($live) : ?>
                        <div class="sch-tt__c">
                            <b class="sch-tt__cth sch-tt__cth--sm"><?php esc_html_e('النشر على المدرسة', 'school-system'); ?></b>
                            <span class="sch-tt__hint"><?php esc_html_e('هذا الشهر يتكرر أسبوعيًا حتى تغيّره — نسخه على الأشهر القادمة يوفّر التكرار.', 'school-system'); ?></span>

                            <div class="sch-tt__pub">
                                <form method="post">
                                    <?php wp_nonce_field('sch_tt_copy', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="tt_copy">
                                    <?php $keep(); ?>
                                    <button class="sch-tt__pubi" <?php disabled($peers === 0); ?>>
                                        <b><?php esc_html_e('نسخ إلى بقية شعب الصف', 'school-system'); ?></b>
                                        <span><?php echo esc_html(sprintf(
                                            /* translators: %d: عدد الشعب */
                                            _n('%d شعبة', '%d شعب', $peers, 'school-system'),
                                            $peers
                                        )); ?></span>
                                    </button>
                                </form>

                                <form method="post">
                                    <?php wp_nonce_field('sch_tt_spread', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="tt_spread">
                                    <?php $keep(); ?>
                                    <button class="sch-tt__pubi" <?php disabled($later_months === 0); ?>>
                                        <b><?php esc_html_e('فرش على الأشهر القادمة', 'school-system'); ?></b>
                                        <span><?php echo esc_html(sprintf(
                                            /* translators: %d: عدد الأشهر */
                                            _n('%d شهر', '%d شهرًا', $later_months, 'school-system'),
                                            $later_months
                                        )); ?></span>
                                    </button>
                                </form>

                                <button type="button" class="sch-tt__pubi" data-sch-print>
                                    <b><?php esc_html_e('طباعة A5', 'school-system'); ?></b>
                                    <span><?php echo esc_html($sec_lb); ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ══ الترقيم السفلي ══ -->
    <div class="sch-tt__foot">
        <?php if ($step > 1) : ?>
            <a class="sch-tt__b" href="<?php echo esc_url($here(['step' => $step - 1])); ?>">
                <span aria-hidden="true">‹</span> <?php echo esc_html($steps[$step - 1][0]); ?>
            </a>
        <?php else : ?>
            <span class="sch-tt__b is-off"><?php esc_html_e('البداية', 'school-system'); ?></span>
        <?php endif; ?>

        <span class="sch-tt__hint"><?php echo esc_html(match ($step) {
            1       => __('اختر المرحلة والشعبة — الباقي معبّأ مسبقًا', 'school-system'),
            2       => sprintf(
                /* translators: %d: سعة الأسبوع */
                __('المجموع لازم يساوي %d حصة', 'school-system'),
                $cap
            ),
            3       => __('راجع الإسناد والقيود ثم ولّد', 'school-system'),
            default => __('اسحب لتعدّل · اضغط خانة مملوءة لتفريغها', 'school-system'),
        }); ?></span>

        <?php if ($step === 3 && $live) : ?>
            <form method="post">
                <?php wp_nonce_field('sch_tt_generate', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="tt_generate">
                <input type="hidden" name="scope" value="school">
                <?php $keep(['step' => '4']); ?>
                <button class="sch-tt__b sch-tt__b--go">
                    <?php esc_html_e('ولّد الجدول', 'school-system'); ?> <span aria-hidden="true">›</span>
                </button>
            </form>
        <?php elseif ($step < 4) : ?>
            <a class="sch-tt__b sch-tt__b--go" href="<?php echo esc_url($here(['step' => $step + 1])); ?>">
                <?php echo esc_html($steps[$step + 1][0]); ?> <span aria-hidden="true">›</span>
            </a>
        <?php elseif ($can_pub && $live) : ?>
            <form method="post" onsubmit="return confirm('<?php esc_attr_e('اعتماد الجدول ونشره؟', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_tt_publish', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="tt_publish">
                <?php $keep(); ?>
                <button class="sch-tt__b sch-tt__b--go">
                    <?php esc_html_e('اعتماد', 'school-system'); ?> <span aria-hidden="true">›</span>
                </button>
            </form>
        <?php else : ?>
            <span class="sch-tt__b is-off"><?php esc_html_e('النهاية', 'school-system'); ?></span>
        <?php endif; ?>
    </div>
</div>
