<?php
/**
 * الاختبارات — دورةٌ وجدولٌ ودرجات، على التصميم المُرسَل.
 *
 * تبويبان يتقاسمان شريط أدوات واحدًا: **جدول الاختبارات** يبنيه الموظّف
 * بالسحب على أيام الدورة، و**رصد الدرجات** يدخله معلّم كل مادة لمادته وحدها.
 *
 * وكل زرٍّ فعلٌ فوريّ لا حقلٌ يُملأ ثم يُحفَظ — كما في شاشة بناء الجداول.
 * والسحب والإفلات تحسينٌ فوق «اختر مادة ثم اضغط يومًا»: من لا جافاسكربت
 * عنده يبني جدوله كاملًا، ومن يستعمل لوحة المفاتيح كذلك.
 */
declare(strict_types=1);
defined('ABSPATH') || exit;

$can_grade   = current_user_can('sch_manage_grades');
$can_build   = current_user_can('sch_manage_exams');
$can_approve = current_user_can('sch_approve_grades');

$classes = SCH_Classes::list();

if ($classes === []) {
    echo '<div class="sch-empty"><strong>' . esc_html__('لا شعب بعد', 'school-system') . '</strong>'
       . esc_html__('أضف الصفوف والشعب أولًا — جدول الاختبارات يُبنى لشعبة.', 'school-system') . '</div>';
    return;
}

// ── موضع الشاشة ──
$stage = sanitize_key((string) ($_GET['stage'] ?? ''));
if ($stage !== '' && !isset(SCH_Classes::STAGES[$stage])) {
    $stage = '';
}

$shown = $stage === ''
    ? $classes
    : array_values(array_filter($classes, static fn (object $c): bool => (string) $c->stage === $stage));

if ($shown === []) {
    $shown = $classes;
    $stage = '';
}

$cls = absint($_GET['cls'] ?? 0);
if ($cls <= 0 || !SCH_Classes::get($cls)) {
    $cls = (int) $shown[0]->id;
}

$class  = SCH_Classes::get($cls);
$sec_lb = $class->grade_level . ' / ' . $class->section;

$ses = sanitize_key((string) ($_GET['ses'] ?? 'final'));
if (!SCH_Exams::valid_session($ses)) {
    $ses = 'final';
}

$mo  = $ses === 'monthly' ? max(1, min(12, (int) ($_GET['mo'] ?? (int) current_time('n')))) : null;
$tab = (string) ($_GET['tab'] ?? '') === 'grades' && $can_grade ? 'grades' : 'timetable';

$base = SCH_Dashboard::url('exams');
$post = SCH_Dashboard::post_url();

$here = static fn (array $args): string => add_query_arg(
    $args + ['cls' => $cls, 'ses' => $ses, 'mo' => (string) ($mo ?? ''), 'tab' => $tab, 'stage' => $stage],
    $base
);

/** حالة الشاشة تعود مع كل نموذج — فلا يُعاد المستخدم إلى أوّل شعبة بعد كل ضغطة. */
$keep = static function (array $over = []) use ($cls, $ses, $mo, $tab, $stage): void {
    $state = $over + [
        'cls'   => (string) $cls,
        'ses'   => $ses,
        'mo'    => (string) ($mo ?? ''),
        'tab'   => $tab,
        'stage' => $stage,
    ];

    foreach ($state as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        printf('<input type="hidden" name="%s" value="%s">', esc_attr((string) $key), esc_attr((string) $value));
    }
};

$subs   = SCH_Exams::subjects_of($cls);
$window = SCH_Exams::window_label($cls, $ses, $mo);

/** لون المادة: ثابتٌ لمعرّفها، فلا يتنقّل بين الشاشات ولا بين الدورات. */
$tone = static fn (int $subject_id): string => 'sch-tt__tone sch-tt__tone--' . ($subject_id % 8);
?>
<div class="sch-ex" data-sch-ex>

    <!-- ══ الرأس ══ -->
    <div class="sch-ex__title sch-noprint">
        <h1><?php esc_html_e('الاختبارات', 'school-system'); ?></h1>
        <p><?php esc_html_e('جدول الاختبارات يبنيه الموظف — رصد الدرجات يدخله معلم كل مادة لمادته فقط.', 'school-system'); ?></p>
    </div>

    <!-- ══ شريط الأدوات ══ -->
    <div class="sch-ex__bar sch-noprint">
        <?php foreach (SCH_Exams::SESSIONS as $key => $label) : ?>
            <a class="sch-ex__ses<?php echo $key === $ses ? ' is-on' : ''; ?>"
               href="<?php echo esc_url($here(['ses' => $key, 'mo' => $key === 'monthly' ? (string) ($mo ?? current_time('n')) : ''])); ?>">
                <i aria-hidden="true"></i><?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>

        <span class="sch-ex__sep" aria-hidden="true"></span>

        <a class="sch-ex__chip<?php echo $stage === '' ? ' is-on' : ''; ?>"
           href="<?php echo esc_url($here(['stage' => '', 'cls' => $cls])); ?>"><?php esc_html_e('كل المراحل', 'school-system'); ?></a>

        <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
            <a class="sch-ex__chip<?php echo $slug === $stage ? ' is-on' : ''; ?>"
               href="<?php echo esc_url(add_query_arg(['stage' => $slug, 'ses' => $ses, 'mo' => (string) ($mo ?? ''), 'tab' => $tab], $base)); ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>

        <form method="get" action="<?php echo esc_url($base); ?>" class="sch-ex__pick">
            <input type="hidden" name="ses" value="<?php echo esc_attr($ses); ?>">
            <input type="hidden" name="mo" value="<?php echo esc_attr((string) ($mo ?? '')); ?>">
            <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
            <input type="hidden" name="stage" value="<?php echo esc_attr($stage); ?>">
            <select class="sch-ex__sel" name="cls" data-ex-submit aria-label="<?php esc_attr_e('الشعبة', 'school-system'); ?>">
                <?php foreach ($shown as $c) : ?>
                    <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($cls, (int) $c->id); ?>>
                        <?php echo esc_html(SCH_Classes::label($c)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="sch-ex__b sch-ex__b--sm"><?php esc_html_e('عرض', 'school-system'); ?></button></noscript>
        </form>

        <span class="sch-ex__gap" aria-hidden="true"></span>
        <span class="sch-ex__note"><?php echo esc_html($window); ?></span>
    </div>

    <?php if ($ses === 'monthly') : ?>
        <div class="sch-ex__bar sch-noprint">
            <b class="sch-ex__lab"><?php esc_html_e('الشهر', 'school-system'); ?></b>
            <?php foreach (SCH_TT::months() as $ym => $label) :
                $n = (int) substr($ym, 5, 2); ?>
                <a class="sch-ex__m<?php echo $n === $mo ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url($here(['mo' => (string) $n])); ?>">
                    <?php echo esc_html(mysql2date('F', $ym . '-01')); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ══ التبويبان ══ -->
    <div class="sch-ex__tabs sch-noprint">
        <?php foreach ([
            'timetable' => __('جدول الاختبارات', 'school-system'),
            'grades'    => __('رصد الدرجات', 'school-system'),
        ] as $key => $label) :
            if ($key === 'grades' && !$can_grade) {
                continue;
            } ?>
            <a class="sch-ex__tab<?php echo $key === $tab ? ' is-on' : ''; ?>"
               href="<?php echo esc_url($here(['tab' => $key])); ?>"
               aria-current="<?php echo $key === $tab ? 'true' : 'false'; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </div>

    <?php // ══════════════════ ① جدول الاختبارات ══════════════════ ?>
    <?php if ($tab === 'timetable') :
        $days   = SCH_Exams::days_for($cls, $ses, $mo);
        $placed = SCH_Exams::schedule($cls, $ses, $mo);
        $health = SCH_Exams::conflicts($cls, $ses, $mo);
        $rooms  = SCH_Exams::rooms();
        $staff  = SCH_TT::teachers();
        $pick   = absint($_GET['pick'] ?? 0);
        $saved  = SCH_Exams::saved_list();

        $by_date = [];
        foreach ($placed as $sid => $e) {
            if ((string) $e->exam_date !== '') {
                $by_date[(string) $e->exam_date] = (int) $sid;
            }
        }

        $is_saved = false;
        foreach ($placed as $e) {
            if ($e->published_at !== null) {
                $is_saved = true;
                break;
            }
        }
        ?>

        <div class="sch-ex__bar sch-noprint">
            <?php foreach ([
                ['room',    __('تعارضات قاعة', 'school-system'),  $health['room']],
                ['proctor', __('تعارضات مراقب', 'school-system'), $health['proctor']],
                ['missing', __('مواد بلا موعد', 'school-system'), $health['missing']],
            ] as [$key, $label, $n]) : ?>
                <span class="sch-ex__h<?php echo $n > 0 ? ' is-bad' : ''; ?>">
                    <i aria-hidden="true"></i>
                    <span><?php echo esc_html($label); ?></span>
                    <b dir="ltr"><?php echo esc_html((string) $n); ?></b>
                </span>
            <?php endforeach; ?>

            <span class="sch-ex__gap" aria-hidden="true"></span>

            <?php if ($can_build) : ?>
                <form method="post" action="<?php echo esc_url($post); ?>">
                    <?php wp_nonce_field('sch_ex_save', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="ex_save">
                    <?php $keep(); ?>
                    <button class="sch-ex__b sch-ex__b--go">
                        <?php echo esc_html($is_saved
                            ? __('تحديث الجدول المحفوظ', 'school-system')
                            : __('حفظ الجدول', 'school-system')); ?>
                    </button>
                </form>
            <?php endif; ?>

            <button type="button" class="sch-ex__b" data-sch-print><?php esc_html_e('طباعة الجدول', 'school-system'); ?></button>
        </div>

        <div class="sch-ex__c sch-noprint">
            <div class="sch-ex__ch">
                <b class="sch-ex__cth"><?php esc_html_e('الجداول المُنشأة', 'school-system'); ?></b>
                <span class="sch-ex__note"><?php esc_html_e('جدول واحد فقط لكل شعبة في الدورة أو الشهر — الحفظ مرة ثانية يحدّثه لا يكرّره', 'school-system'); ?></span>
            </div>

            <?php if ($saved === []) : ?>
                <p class="sch-ex__empty"><?php esc_html_e('لا جداول محفوظة بعد — جهّز الجدول ثم اضغط «حفظ الجدول».', 'school-system'); ?></p>
            <?php else : ?>
                <?php foreach ($saved as $row) :
                    $cur = (int) $row->class_id === $cls
                        && (string) $row->session === $ses
                        && (int) $row->session_month === (int) $mo; ?>
                    <div class="sch-ex__saved<?php echo $cur ? ' is-on' : ''; ?>">
                        <span class="sch-ex__savedt">
                            <b><?php echo esc_html((string) $row->label); ?></b>
                            <span><?php echo esc_html(sprintf(
                                /* translators: 1: وقت الحفظ 2: عدد المواد */
                                __('حُفظ %1$s · %2$d مادة', 'school-system'),
                                mysql2date('H:i', (string) $row->at),
                                (int) $row->n
                            )); ?></span>
                        </span>

                        <span class="sch-ex__st<?php echo (int) $row->conflicts > 0 ? ' is-bad' : ''; ?>">
                            <i aria-hidden="true"></i>
                            <?php echo esc_html((int) $row->conflicts > 0
                                ? __('به تعارض', 'school-system')
                                : __('سليم', 'school-system')); ?>
                        </span>

                        <a class="sch-ex__b sch-ex__b--sm"
                           href="<?php echo esc_url(add_query_arg([
                               'cls' => (int) $row->class_id,
                               'ses' => (string) $row->session,
                               'mo'  => (string) ($row->session_month ?? ''),
                               'tab' => 'timetable',
                           ], $base)); ?>"><?php esc_html_e('تعديل', 'school-system'); ?></a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="sch-ex__work">
            <div class="sch-ex__c sch-ex__sheet">
                <div class="sch-ex__printh">
                    <b><?php echo esc_html(sprintf(
                        /* translators: 1: اسم الشعبة 2: اسم الدورة */
                        __('جدول اختبارات %1$s — %2$s', 'school-system'),
                        $sec_lb,
                        SCH_Exams::session_label($ses, $mo)
                    )); ?></b>
                    <span><?php echo esc_html($window); ?></span>
                </div>

                <div class="sch-ex__gr sch-ex__gr--head">
                    <span><?php esc_html_e('التاريخ', 'school-system'); ?></span>
                    <span><?php esc_html_e('المادة', 'school-system'); ?></span>
                    <span><?php esc_html_e('القاعة', 'school-system'); ?></span>
                    <span><?php esc_html_e('المراقب', 'school-system'); ?></span>
                    <span></span>
                </div>

                <?php if ($days === []) : ?>
                    <div class="sch-empty">
                        <strong><?php esc_html_e('لا مواد لهذه الشعبة بعد', 'school-system'); ?></strong>
                        <?php esc_html_e('أسند المواد للشعبة من شاشة المواد والجداول أولًا.', 'school-system'); ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($days as $d) :
                    $sid  = $by_date[$d['date']] ?? 0;
                    $e    = $sid > 0 ? $placed[$sid] : null;
                    $flag = $sid > 0 ? ($health['cells'][$sid] ?? '') : '';
                    $cn   = $e ? ($flag !== '' ? ' is-clash' : '') : ' is-empty';
                    ?>
                    <div class="sch-ex__gr<?php echo esc_attr($cn); ?>"
                         data-ex-day="<?php echo esc_attr($d['date']); ?>">

                        <span class="sch-ex__date">
                            <b><?php echo esc_html($d['weekday']); ?></b>
                            <span dir="ltr"><?php echo esc_html($d['label']); ?></span>
                        </span>

                        <?php if ($e) : ?>
                            <span class="sch-ex__sub">
                                <span class="<?php echo esc_attr($tone($sid)); ?>" aria-hidden="true"></span>
                                <b><?php echo esc_html((string) $e->subject_name); ?></b>
                                <?php if ($flag !== '') : ?>
                                    <i class="sch-ex__flag" title="<?php esc_attr_e('تعارض', 'school-system'); ?>" aria-hidden="true"></i>
                                <?php endif; ?>
                            </span>
                        <?php elseif ($can_build) : ?>
                            <form method="post" action="<?php echo esc_url($post); ?>" class="sch-ex__cf">
                                <?php wp_nonce_field('sch_ex_place', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="ex_place">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $pick); ?>">
                                <input type="hidden" name="date" value="<?php echo esc_attr($d['date']); ?>">
                                <?php $keep(); ?>
                                <button class="sch-ex__drop" <?php disabled($pick <= 0); ?>>
                                    <?php echo esc_html($pick > 0
                                        ? __('ضع هنا', 'school-system')
                                        : __('— لا اختبار —', 'school-system')); ?>
                                </button>
                            </form>
                        <?php else : ?>
                            <span class="sch-ex__none"><?php esc_html_e('— لا اختبار —', 'school-system'); ?></span>
                        <?php endif; ?>

                        <?php if ($e && $can_build) : ?>
                            <form method="post" action="<?php echo esc_url($post); ?>">
                                <?php wp_nonce_field('sch_ex_field', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="ex_field">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $sid); ?>">
                                <input type="hidden" name="field" value="room">
                                <?php $keep(); ?>
                                <select class="sch-ex__sel sch-ex__sel--sm<?php echo str_contains($flag, 'room') ? ' is-bad' : ''; ?>"
                                        name="value" data-ex-submit
                                        aria-label="<?php esc_attr_e('القاعة', 'school-system'); ?>">
                                    <option value=""><?php esc_html_e('بلا قاعة', 'school-system'); ?></option>
                                    <?php foreach ($rooms as $rm) : ?>
                                        <option value="<?php echo esc_attr($rm); ?>" <?php selected((string) $e->room, $rm); ?>>
                                            <?php echo esc_html($rm); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <form method="post" action="<?php echo esc_url($post); ?>">
                                <?php wp_nonce_field('sch_ex_field', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="ex_field">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $sid); ?>">
                                <input type="hidden" name="field" value="proctor">
                                <?php $keep(); ?>
                                <select class="sch-ex__sel sch-ex__sel--sm<?php echo str_contains($flag, 'proctor') ? ' is-bad' : ''; ?>"
                                        name="value" data-ex-submit
                                        aria-label="<?php esc_attr_e('المراقب', 'school-system'); ?>">
                                    <option value=""><?php esc_html_e('بلا مراقب', 'school-system'); ?></option>
                                    <?php foreach ($staff as $u) : ?>
                                        <option value="<?php echo esc_attr((string) $u->ID); ?>" <?php selected((int) $e->proctor_user_id, (int) $u->ID); ?>>
                                            <?php echo esc_html((string) $u->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <form method="post" action="<?php echo esc_url($post); ?>">
                                <?php wp_nonce_field('sch_ex_clear', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="ex_clear">
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $sid); ?>">
                                <?php $keep(); ?>
                                <button class="sch-ex__x" aria-label="<?php echo esc_attr(sprintf(
                                    /* translators: %s: اسم المادة */
                                    __('إزالة %s من هذا اليوم', 'school-system'),
                                    (string) $e->subject_name
                                )); ?>">✕</button>
                            </form>
                        <?php elseif ($e) : ?>
                            <span class="sch-ex__ro"><?php echo esc_html((string) ($e->room ?: '—')); ?></span>
                            <span class="sch-ex__ro"><?php echo esc_html((string) ($e->proctor_name ?: '—')); ?></span>
                            <span></span>
                        <?php else : ?>
                            <span></span><span></span><span></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sch-ex__side sch-noprint">
                <div class="sch-ex__c">
                    <div class="sch-ex__ch">
                        <b class="sch-ex__cth"><?php esc_html_e('مواد بلا موعد', 'school-system'); ?></b>
                        <span class="sch-ex__note"><?php esc_html_e('اسحب أو اضغط', 'school-system'); ?></span>
                    </div>

                    <?php
                    $free = array_values(array_filter(
                        $subs,
                        static fn (object $s): bool => !isset($placed[(int) $s->id]) || (string) $placed[(int) $s->id]->exam_date === ''
                    ));
                    ?>

                    <?php foreach ($free as $s) :
                        $on = $pick === (int) $s->id; ?>
                        <a class="sch-ex__bi<?php echo $on ? ' is-on' : ''; ?>"
                           href="<?php echo esc_url($here(['pick' => $on ? '' : (string) (int) $s->id])); ?>"
                           data-ex-pick="<?php echo esc_attr((string) (int) $s->id); ?>" draggable="true">
                            <span class="<?php echo esc_attr($tone((int) $s->id)); ?>" aria-hidden="true"></span>
                            <span class="sch-ex__bt">
                                <b><?php echo esc_html((string) $s->name); ?></b>
                                <span><?php echo esc_html((string) ($s->teacher_name ?: __('بلا معلم', 'school-system'))); ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($free === [] && $subs !== []) : ?>
                        <p class="sch-ex__done"><?php esc_html_e('كل المواد مجدولة', 'school-system'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php // ══════════════════ ② رصد الدرجات ══════════════════ ?>
    <?php elseif ($subs === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا مواد لهذه الشعبة بعد', 'school-system'); ?></strong>
            <?php esc_html_e('أسند المواد للشعبة من شاشة المواد والجداول أولًا.', 'school-system'); ?>
        </div>

    <?php else :
        $sub = absint($_GET['sub'] ?? 0);
        $ok  = false;
        foreach ($subs as $s) {
            if ((int) $s->id === $sub) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $sub = (int) $subs[0]->id;
        }

        $w      = SCH_Exams::weights($cls, $sub, $ses, $mo);
        $roster = SCH_Exams::roster($cls, $sub, $ses, $mo);
        $stats  = SCH_Exams::stats($roster, $w);
        $state  = SCH_Exams::grade_state($cls, $sub, $ses, $mo);
        $locked = $state !== 'open';

        $sub_lb = '';
        foreach ($subs as $s) {
            if ((int) $s->id === $sub) {
                $sub_lb = (string) $s->name;
                break;
            }
        }

        $makeup = array_values(array_filter($roster, static fn (object $r): bool => (int) $r->excused === 1));
        ?>

        <div class="sch-ex__c sch-ex__gh">
            <label class="sch-ex__f">
                <span><?php esc_html_e('مادتك', 'school-system'); ?></span>
                <form method="get" action="<?php echo esc_url($base); ?>">
                    <input type="hidden" name="cls" value="<?php echo esc_attr((string) $cls); ?>">
                    <input type="hidden" name="ses" value="<?php echo esc_attr($ses); ?>">
                    <input type="hidden" name="mo" value="<?php echo esc_attr((string) ($mo ?? '')); ?>">
                    <input type="hidden" name="tab" value="grades">
                    <input type="hidden" name="stage" value="<?php echo esc_attr($stage); ?>">
                    <select class="sch-ex__sel" name="sub" data-ex-submit aria-label="<?php esc_attr_e('المادة', 'school-system'); ?>">
                        <?php foreach ($subs as $s) : ?>
                            <option value="<?php echo esc_attr((string) (int) $s->id); ?>" <?php selected($sub, (int) $s->id); ?>>
                                <?php echo esc_html((string) $s->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="sch-ex__b sch-ex__b--sm"><?php esc_html_e('عرض', 'school-system'); ?></button></noscript>
                </form>
            </label>

            <div class="sch-ex__f">
                <span id="exl-w"><?php esc_html_e('وزن أعمال السنة', 'school-system'); ?></span>
                <div class="sch-ex__num" role="group" aria-labelledby="exl-w">
                    <?php foreach ([[-5, '−', __('إنقاص الوزن', 'school-system')], [5, '+', __('زيادة الوزن', 'school-system')]] as $i => [$delta, $glyph, $aria]) :
                        $to = max(10, min(60, $w['year'] + $delta)); ?>
                        <?php if ($i === 1) : ?><b dir="ltr"><?php echo esc_html((string) $w['year']); ?></b><?php endif; ?>
                        <form method="post" action="<?php echo esc_url($post); ?>">
                            <?php wp_nonce_field('sch_ex_weight', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="ex_weight">
                            <input type="hidden" name="year_weight" value="<?php echo esc_attr((string) $to); ?>">
                            <?php $keep(['sub' => (string) $sub]); ?>
                            <button class="sch-ex__pm" <?php disabled($to === $w['year'] || $locked); ?>
                                    aria-label="<?php echo esc_attr($aria); ?>"><?php echo esc_html($glyph); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sch-ex__f">
                <span><?php esc_html_e('وزن الاختبار النهائي', 'school-system'); ?></span>
                <b class="sch-ex__fixed" dir="ltr"><?php echo esc_html((string) $w['final']); ?></b>
            </div>

            <span class="sch-ex__sep" aria-hidden="true"></span>

            <?php foreach ([
                [__('مرصود', 'school-system'), $stats['entered'] . '/' . $stats['total'], ''],
                [__('متوسط الشعبة', 'school-system'), (string) $stats['avg'], $stats['avg'] >= SCH_Exams::PASS_MARK ? 'is-ok' : 'is-bad'],
                [__('عدد الراسبين', 'school-system'), (string) $stats['fails'], $stats['fails'] > 0 ? 'is-bad' : ''],
            ] as [$label, $value, $tone_s]) : ?>
                <div class="sch-ex__stat">
                    <span><?php echo esc_html($label); ?></span>
                    <b class="<?php echo esc_attr($tone_s); ?>" dir="ltr"><?php echo esc_html($value); ?></b>
                </div>
            <?php endforeach; ?>

            <span class="sch-ex__gap" aria-hidden="true"></span>

            <?php if ($state === 'approved') : ?>
                <span class="sch-ex__b is-off"><?php esc_html_e('معتمدة ومنشورة لولي الأمر', 'school-system'); ?></span>
            <?php elseif ($state === 'submitted') : ?>
                <span class="sch-ex__b is-wait"><?php esc_html_e('بانتظار اعتماد المديرة', 'school-system'); ?></span>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url($post); ?>"
                      onsubmit="return confirm('<?php esc_attr_e('ستُقفل الدرجات عن التعديل حتى تعتمدها الإدارة. متابعة؟', 'school-system'); ?>');">
                    <?php wp_nonce_field('sch_ex_submit', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="ex_submit">
                    <?php $keep(['sub' => (string) $sub]); ?>
                    <button class="sch-ex__b sch-ex__b--go"><?php esc_html_e('إرسال للاعتماد', 'school-system'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($state === 'submitted' && $can_approve) : ?>
            <div class="sch-ex__wait">
                <span><?php echo esc_html(sprintf(
                    /* translators: 1: المادة 2: الشعبة 3: الدورة */
                    __('درجات %1$s — %2$s — %3$s بانتظار اعتماد المديرة قبل نشرها لأولياء الأمور.', 'school-system'),
                    $sub_lb,
                    $sec_lb,
                    SCH_Exams::session_label($ses, $mo)
                )); ?></span>
                <form method="post" action="<?php echo esc_url($post); ?>">
                    <?php wp_nonce_field('sch_ex_approve', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="ex_approve">
                    <?php $keep(['sub' => (string) $sub]); ?>
                    <button class="sch-ex__b sch-ex__b--go"><?php esc_html_e('اعتماد المديرة والنشر', 'school-system'); ?></button>
                </form>
            </div>
        <?php endif; ?>

        <div class="sch-ex__c sch-ex__c--flush">
            <div class="sch-ex__rg sch-ex__rg--head">
                <span><?php esc_html_e('الطالب', 'school-system'); ?></span>
                <span><?php esc_html_e('أعمال السنة', 'school-system'); ?></span>
                <span><?php esc_html_e('النهائي', 'school-system'); ?></span>
                <span><?php esc_html_e('المجموع', 'school-system'); ?></span>
                <span><?php esc_html_e('الحالة', 'school-system'); ?></span>
                <span><?php esc_html_e('غياب بعذر', 'school-system'); ?></span>
            </div>

            <?php if ($roster === []) : ?>
                <div class="sch-empty">
                    <strong><?php esc_html_e('لا طلاب في هذه الشعبة', 'school-system'); ?></strong>
                    <?php esc_html_e('سجّل الطلاب أولًا ثم ارصد درجاتهم.', 'school-system'); ?>
                </div>
            <?php endif; ?>

            <?php foreach ($roster as $r) :
                $excused = (int) $r->excused === 1;
                $has     = $r->year_score !== null && $r->final_score !== null;
                $total   = $has ? SCH_Exams::total_of($r, $w) : null;

                if ($excused) {
                    [$st_l, $st_c] = [__('غياب بعذر', 'school-system'), 'is-excused'];
                } elseif (!$has) {
                    [$st_l, $st_c] = [__('لم يُدخل', 'school-system'), ''];
                } elseif ($total >= SCH_Exams::PASS_MARK) {
                    [$st_l, $st_c] = [__('ناجح', 'school-system'), 'is-ok'];
                } else {
                    [$st_l, $st_c] = [__('راسب', 'school-system'), 'is-bad'];
                }
                ?>
                <div class="sch-ex__rg">
                    <span class="sch-ex__who">
                        <i aria-hidden="true"><?php echo esc_html(mb_substr((string) $r->full_name, 0, 1)); ?></i>
                        <span>
                            <b><?php echo esc_html((string) $r->full_name); ?></b>
                            <span dir="ltr">#<?php echo esc_html((string) $r->academic_no); ?></span>
                        </span>
                    </span>

                    <?php foreach ([
                        ['year',   $r->year_score,  $w['year'],  __('أعمال السنة', 'school-system')],
                        ['graded', $r->final_score, $w['final'], __('النهائي', 'school-system')],
                    ] as [$part, $val, $max, $lab]) : ?>
                        <form method="post" action="<?php echo esc_url($post); ?>">
                            <?php wp_nonce_field('sch_ex_score', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="ex_score">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $r->id); ?>">
                            <input type="hidden" name="part" value="<?php echo esc_attr($part); ?>">
                            <?php $keep(['sub' => (string) $sub]); ?>
                            <input class="sch-ex__in" type="number" name="score" step="0.25" min="0"
                                   max="<?php echo esc_attr((string) $max); ?>" dir="ltr" data-ex-submit
                                   <?php disabled($excused || $locked); ?>
                                   value="<?php echo esc_attr($excused || $val === null ? '' : (string) (float) $val); ?>"
                                   aria-label="<?php echo esc_attr($lab . ' — ' . (string) $r->full_name); ?>">
                            <noscript><button class="sch-sr"><?php esc_html_e('حفظ', 'school-system'); ?></button></noscript>
                        </form>
                    <?php endforeach; ?>

                    <b class="sch-ex__total <?php echo esc_attr($st_c); ?>" dir="ltr"><?php echo esc_html($total === null ? '—' : (string) $total); ?></b>

                    <span class="sch-ex__st <?php echo esc_attr($st_c); ?>">
                        <i aria-hidden="true"></i><?php echo esc_html($st_l); ?>
                    </span>

                    <form method="post" action="<?php echo esc_url($post); ?>">
                        <?php wp_nonce_field('sch_ex_excuse', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="ex_excuse">
                        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $r->id); ?>">
                        <?php $keep(['sub' => (string) $sub]); ?>
                        <button class="sch-ex__ex<?php echo $excused ? ' is-on' : ''; ?>" <?php disabled($locked); ?>>
                            <?php echo esc_html($excused ? __('إلغاء', 'school-system') : __('غياب بعذر', 'school-system')); ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($makeup !== []) : ?>
            <div class="sch-ex__c sch-ex__makeup">
                <b class="sch-ex__cth"><?php echo esc_html(sprintf(
                    /* translators: %d: عدد الطلاب */
                    __('الدور البديل — %d طالبًا', 'school-system'),
                    count($makeup)
                )); ?></b>
                <?php foreach ($makeup as $m) : ?>
                    <div class="sch-ex__mk">
                        <span><?php echo esc_html((string) $m->full_name); ?></span>
                        <span class="sch-ex__note"><?php esc_html_e('يُرصد له بعد أداء الدور البديل — والخانتان مفتوحتان متى أُلغي العذر', 'school-system'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
