<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * حضور الطلاب — شاشة البوابة.
 *
 * الرصد آلي: البطاقة تُمسح على البوابة فيُكتب الحضور وحده. ودور هذه الشاشة
 * أن تُري الموظف ما لم يحدث بعد — «بانتظار المسح» — لا أن تطلب منه رصد
 * أربعمئة طالب بيده. ولذلك الكشف على مستوى المدرسة لا على شعبة: الماسح لا
 * يعرف شعبة القادم إليه، والتصفية بالشعبة قرار عرض لا قرار استعلام.
 *
 * والعدّان — المرصود والمنتظر — يخرجان من `day_sheet()` نفسها لا من
 * `day_summary()`: لو جاء رقم من مصدر ورقم من آخر لاختلف ما يُعرض عمّا
 * سيُغلق، وزرّ الإغلاق لا رجعة فيه.
 */

$sch_date  = current_time('Y-m-d');
$sch_sheet = SCH_Attendance::day_sheet($sch_date);
$sch_feed  = SCH_Custody::recent(12);
$sch_last  = $sch_feed[0] ?? null;
$sch_total = count($sch_sheet);

$sch_n = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'none' => 0];

foreach ($sch_sheet as $sch_row) {
    $sch_key = (string) ($sch_row->status ?? '');
    $sch_n[isset($sch_n[$sch_key]) ? $sch_key : 'none']++;
}

// من دخل البوابة فعلًا: الحاضر والمتأخر كلاهما دخل — والتأخّر صفة دخول لا غياب.
$sch_in = $sch_n['present'] + $sch_n['late'];

/** نسبة حالة من الكشف كله — لعرض شريط التقدّم. */
$sch_pct = static function (int $count) use ($sch_total): string {
    return $sch_total > 0 ? (string) round($count * 100 / $sch_total, 2) : '0';
};

// أسماء الحالات كما في التصميم. «بانتظار المسح» حالة قائمة بذاتها لا صفر عدّاد.
$sch_lbl = [
    'present' => __('حاضر', 'school-system'),
    'late'    => __('متأخر', 'school-system'),
    'excused' => __('بعذر', 'school-system'),
    'absent'  => __('غائب', 'school-system'),
    'none'    => __('بانتظار المسح', 'school-system'),
];

// نغمة كل حدث في سجل المسح: قادم · مغادر · إدخال إداري.
$sch_tone = [
    'bus_board'  => 'in',
    'gate_in'    => 'in',
    'gate_out'   => 'out',
    'early_out'  => 'out',
    'bus_alight' => 'out',
    'manual'     => 'note',
    'correction' => 'note',
];

// أرقام اللغة — تُمرَّر للسكربت ليكتب الساعة الحيّة بأرقام الشاشة نفسها
// لا بأرقام لاتينية تقفز فوق ما رسمه الخادم.
$sch_digits = '';
for ($sch_i = 0; $sch_i < 10; $sch_i++) {
    $sch_digits .= number_format_i18n($sch_i);
}

// ساعة اللوح: `sch_clock` تُعطي HH:MM وحدهما، والثواني تُوطَّن بخريطة الأرقام
// نفسها — فلا تختلف أرقام الساعة عن أرقام الشاشة عند أول رسم.
// و`array_combine` ترمي ValueError في PHP 8 حين تختلف الأطوال — لا تُرجع false
// كما كانت في السابع، فحارس `is_array` لا يمسك شيئًا. والساعة زينة على اللوح،
// فلا يجوز أن تُسقط شاشة البوابة كلها لو شذّ توطين الأرقام.
$sch_pair = mb_strlen($sch_digits) === 10 ? mb_str_split($sch_digits) : [];
$sch_map  = $sch_pair === [] ? [] : array_combine(str_split('0123456789'), $sch_pair);
$sch_now  = strtr(current_time('H:i:s'), $sch_map);

// وقت آخر بطاقة مقروءة — «--:--» حين لم يُمسح شيء بعد.
$sch_last_at = $sch_last
    ? sch_clock(substr((string) $sch_last->occurred_at, 11, 5))
    : '--:--';
?>
<div class="sch-gate__head">
    <div class="sch-gate__ttlw">
        <h1 class="sch-gate__ttl"><?php esc_html_e('حضور الطلاب', 'school-system'); ?></h1>
        <div class="sch-gate__sub"><?php esc_html_e('الرصد آلي عبر مسح باركود البطاقة على البوابة. تتدخّل يدويًا في الاستثناءات فقط.', 'school-system'); ?></div>
    </div>

    <div class="sch-gate__tags">
        <div class="sch-gate__tag">
            <span class="sch-gate__pulse"></span>
            <span class="sch-gate__tagt"><?php esc_html_e('الماسح متصل', 'school-system'); ?></span>
            <span class="sch-gate__sep"></span>
            <span class="sch-gate__tagm"><?php esc_html_e('البوابة الرئيسية', 'school-system'); ?></span>
        </div>
        <div class="sch-gate__tag">
            <span class="sch-gate__tagm"><?php esc_html_e('جرس الحصة', 'school-system'); ?></span>
            <span class="sch-gate__mono"><?php echo esc_html(sch_clock(SCH_Attendance::expected_in())); ?></span>
        </div>
    </div>
</div>

<div class="sch-gate" id="sch-gate">
    <div class="sch-gate__main">

        <div class="sch-gate__sum">
            <div class="sch-gate__sumh">
                <div class="sch-gate__lead">
                    <span class="sch-gate__big"><?php echo esc_html(number_format_i18n($sch_in)); ?></span>
                    <span class="sch-gate__of">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: عدد الطلاب في الكشف */
                            __('من %s طلاب دخلوا البوابة', 'school-system'),
                            number_format_i18n($sch_total)
                        )); ?>
                    </span>
                </div>

                <div class="sch-gate__keys">
                    <?php foreach (['present', 'late', 'excused', 'absent', 'none'] as $sch_k) : ?>
                        <div class="sch-gate__key sch-gate__key--<?php echo esc_attr($sch_k); ?>">
                            <span class="sch-gate__sq"></span>
                            <span class="sch-gate__keyt"><?php echo esc_html($sch_lbl[$sch_k]); ?></span>
                            <span class="sch-gate__keyn"><?php echo esc_html(number_format_i18n($sch_n[$sch_k])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sch-gate__bar">
                <?php foreach (['present', 'late', 'excused', 'absent'] as $sch_k) : ?>
                    <?php if ($sch_n[$sch_k] > 0) : ?>
                        <span class="sch-gate__seg sch-gate__seg--<?php echo esc_attr($sch_k); ?>"
                              style="width: <?php echo esc_attr($sch_pct($sch_n[$sch_k])); ?>%"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sch-gate__filters">
            <div class="sch-gate__searchw">
                <input type="text" id="sch-gate-q" class="sch-gate__search" autocomplete="off"
                       placeholder="<?php esc_attr_e('ابحث باسم الطالب أو رقم بطاقته', 'school-system'); ?>">
                <span class="sch-gate__ic"><?php echo sch_icon('search', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            </div>

            <?php
            $sch_chips = [
                'all'     => [__('الكل', 'school-system'), $sch_total],
                'present' => [$sch_lbl['present'], $sch_n['present']],
                'late'    => [$sch_lbl['late'], $sch_n['late']],
                'none'    => [$sch_lbl['none'], $sch_n['none']],
            ];
            foreach ($sch_chips as $sch_k => $sch_c) :
                ?>
                <button type="button" class="sch-gate__chip sch-gate__chip--<?php echo esc_attr($sch_k); ?><?php echo $sch_k === 'all' ? ' is-on' : ''; ?>"
                        data-sch-filter="<?php echo esc_attr($sch_k); ?>"
                        aria-pressed="<?php echo $sch_k === 'all' ? 'true' : 'false'; ?>">
                    <span class="sch-gate__dot"></span>
                    <span class="sch-gate__chipt"><?php echo esc_html($sch_c[0]); ?></span>
                    <span class="sch-gate__chipn"><?php echo esc_html(number_format_i18n($sch_c[1])); ?></span>
                </button>
            <?php endforeach; ?>

            <button type="button" class="sch-gate__toggle" id="sch-gate-mode" aria-pressed="false">
                <?php esc_html_e('رصد يدوي', 'school-system'); ?>
            </button>
        </div>

        <?php if ($sch_sheet === []) : ?>
            <div class="sch-blank">
                <span class="sch-blank__ic"><?php echo sch_icon('users', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <strong><?php esc_html_e('لا يوجد طلاب نشطون', 'school-system'); ?></strong>
                <p><?php esc_html_e('سجّل طلابًا أولًا من شاشة الطلاب، ثم تظهر بطاقاتهم هنا فور مسحها على البوابة.', 'school-system'); ?></p>
            </div>
        <?php else : ?>
            <?php /* نموذج واحد لكل الكشف: كل زرّ يحمل حالته باسمه وقيمته،
                     فلا يُرسَل إلا ما ضُغط عليه — والمعالج القائم يقرأه كما هو. */ ?>
            <form method="post" id="sch-gate-form">
                <?php wp_nonce_field('sch_mark_attendance', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="mark_attendance">
                <input type="hidden" name="att_date" value="<?php echo esc_attr($sch_date); ?>">

                <div class="sch-gate__list" id="sch-gate-list">
                    <?php foreach ($sch_sheet as $sch_r) :
                        $sch_id   = (int) $sch_r->id;
                        $sch_name = (string) $sch_r->full_name;
                        $sch_st   = (string) ($sch_r->status ?? '');
                        $sch_st   = isset($sch_lbl[$sch_st]) && $sch_st !== 'none' ? $sch_st : 'none';
                        $sch_no   = (string) ($sch_r->academic_no ?? $sch_r->student_no ?? '');
                        $sch_at   = $sch_r->checked_in_at ? sch_clock(substr((string) $sch_r->checked_in_at, 11, 5)) : '';
                        $sch_at   = $sch_at !== '' ? $sch_at : '— : —';
                        $sch_ltr  = trim($sch_name) !== '' ? mb_substr(trim($sch_name), 0, 1) : '؟';
                        ?>
                        <article class="sch-gate__row" data-status="<?php echo esc_attr($sch_st); ?>"
                                 data-find="<?php echo esc_attr($sch_name . ' ' . $sch_no); ?>">
                            <span class="sch-gate__av" aria-hidden="true"><?php echo esc_html($sch_ltr); ?></span>

                            <span class="sch-gate__id">
                                <span class="sch-gate__name"><?php echo esc_html($sch_name); ?></span>
                                <span class="sch-gate__no"><?php echo esc_html($sch_no); ?></span>
                            </span>

                            <span class="sch-gate__meta">
                                <span class="sch-gate__st">
                                    <span class="sch-gate__dot"></span>
                                    <span><?php echo esc_html($sch_lbl[$sch_st]); ?></span>
                                </span>
                                <span class="sch-gate__time"><?php echo esc_html($sch_at); ?></span>
                            </span>

                            <span class="sch-gate__acts">
                                <button type="submit" class="sch-gate__act sch-gate__act--absent"
                                        name="status[<?php echo esc_attr((string) $sch_id); ?>]" value="absent">
                                    <?php esc_html_e('تسجيل غياب', 'school-system'); ?>
                                </button>
                                <button type="submit" class="sch-gate__act sch-gate__act--excused"
                                        name="status[<?php echo esc_attr((string) $sch_id); ?>]" value="excused">
                                    <?php esc_html_e('بعذر', 'school-system'); ?>
                                </button>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php /* اللوح الحيّ — يُصيَّر من الخادم ويُستبدل وحده كل بضع ثوانٍ. */ ?>
    <aside class="sch-gate__live" id="sch-gate-live">
        <div class="sch-gate__lh">
            <span class="sch-gate__lht">
                <span class="sch-gate__pulse"></span>
                <b><?php esc_html_e('المسح المباشر', 'school-system'); ?></b>
            </span>
            <span class="sch-gate__clock" id="sch-gate-clock"
                  data-t="<?php echo esc_attr(current_time('H:i:s')); ?>"
                  data-d="<?php echo esc_attr($sch_digits); ?>"><?php echo esc_html($sch_now); ?></span>
        </div>

        <div class="sch-gate__last">
            <span class="sch-gate__sweep" aria-hidden="true"></span>
            <span class="sch-gate__cap"><?php esc_html_e('آخر بطاقة مقروءة', 'school-system'); ?></span>
            <span class="sch-gate__lname">
                <?php echo $sch_last
                    ? esc_html((string) $sch_last->full_name)
                    : esc_html__('بانتظار أول مسح', 'school-system'); ?>
            </span>
            <span class="sch-gate__lrow">
                <span class="sch-gate__lt"><?php echo esc_html($sch_last_at); ?></span>
                <span class="sch-gate__lmeta">
                    <?php echo $sch_last
                        ? esc_html(SCH_Custody::CHECKPOINTS[(string) $sch_last->checkpoint] ?? (string) $sch_last->checkpoint)
                        : esc_html__('الماسح جاهز', 'school-system'); ?>
                </span>
            </span>
        </div>

        <div class="sch-gate__feedw">
            <span class="sch-gate__cap"><?php esc_html_e('سجل المسح', 'school-system'); ?></span>

            <?php if ($sch_feed === []) : ?>
                <div class="sch-gate__none"><?php esc_html_e('لم تُقرأ أي بطاقة بعد', 'school-system'); ?></div>
            <?php else : ?>
                <div class="sch-gate__feed">
                    <?php foreach ($sch_feed as $sch_e) :
                        $sch_cp = (string) $sch_e->checkpoint;
                        ?>
                        <div class="sch-gate__ev sch-gate__ev--<?php echo esc_attr($sch_tone[$sch_cp] ?? 'note'); ?>">
                            <span class="sch-gate__dot"></span>
                            <span class="sch-gate__evn"><?php echo esc_html((string) $sch_e->full_name); ?></span>
                            <span class="sch-gate__evl"><?php echo esc_html(SCH_Custody::CHECKPOINTS[$sch_cp] ?? $sch_cp); ?></span>
                            <span class="sch-gate__evt"><?php echo esc_html(sch_clock(substr((string) $sch_e->occurred_at, 11, 5))); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sch-gate__foot">
            <div class="sch-gate__pend">
                <span><?php echo esc_html($sch_lbl['none']); ?></span>
                <b><?php echo esc_html(number_format_i18n($sch_n['none'])); ?></b>
            </div>

            <?php /* فعل لا رجعة فيه: يكتب غيابًا على كل من لم يُرصد ويُبلغ أهلهم. */ ?>
            <form method="post" onsubmit="return confirm('<?php esc_attr_e('سيُسجَّل غياب كل من لم تُقرأ بطاقته اليوم ويصل أهلهم إشعار. لا رجعة. متابعة؟', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_close_attendance', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="close_attendance">
                <button type="submit" class="sch-gate__close" <?php disabled($sch_n['none'], 0); ?>>
                    <?php esc_html_e('إغلاق الكشف وتسجيل الغياب', 'school-system'); ?>
                </button>
            </form>

            <span class="sch-gate__hint">
                <?php echo $sch_n['none'] > 0
                    ? esc_html(sprintf(
                        /* translators: %s: عدد الطلاب الذين لم تُرصد حالتهم */
                        _n(
                            'سيُسجَّل غياب طالب واحد ويُبلَّغ وليّ أمره.',
                            'سيُسجَّل غياب %s طلاب ويُبلَّغ أولياء الأمور.',
                            $sch_n['none'],
                            'school-system'
                        ),
                        number_format_i18n($sch_n['none'])
                    ))
                    : esc_html__('لم يبقَ أحد بلا رصد — الكشف مكتمل.', 'school-system'); ?>
            </span>
        </div>
    </aside>
</div>

<style>
/* ═══ شاشة البوابة — كل لون رمز، وكل مقاس درجة من السلّم ═══ */
/* الترويسة أخت العمودين لا ابنتهما، فرمز الخط يُعلَن على الاثنين معًا:
   رمز غير معرَّف عند الاستعمال يُسقط التصريح كلّه، فكانت ساعة «جرس الحصة»
   تخرج بخط الواجهة بينما كل رقم آخر في الشاشة أحاديّ العرض. */
.sch-gate,
.sch-gate__head {
    --gate-mono: ui-monospace, SFMono-Regular, 'IBM Plex Mono', Menlo, monospace;
}
.sch-gate {
    display: flex;
    align-items: stretch;
    gap: var(--sch-3);
    flex-wrap: wrap;
}
.sch-gate__main { flex: 1 1 0; min-width: 430px; display: flex; flex-direction: column; gap: var(--sch-3); }

/* ── الترويسة ── */
.sch-gate__head {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: var(--sch-5); flex-wrap: wrap; margin-bottom: var(--sch-3);
}
.sch-gate__ttlw { display: flex; flex-direction: column; gap: var(--sch-1); }
.sch-gate__ttl { font-size: var(--sch-text-2xl); line-height: var(--sch-lh-snug); }
.sch-gate__sub { font-size: var(--sch-text-sm); color: var(--sch-muted); }
.sch-gate__tags { display: flex; align-items: center; gap: var(--sch-2); flex-wrap: wrap; }
.sch-gate__tag {
    display: flex; align-items: center; gap: var(--sch-2);
    padding: var(--sch-1) var(--sch-3);
    border: 1px solid var(--sch-line); border-radius: var(--sch-radius);
    background: var(--sch-surface); min-height: 34px;
}
.sch-gate__tagt { font-size: var(--sch-text-xs); font-weight: var(--sch-w-medium); color: var(--sch-ink-soft); }
.sch-gate__tagm { font-size: var(--sch-text-xs); color: var(--sch-muted); }
.sch-gate__sep { width: 1px; height: 12px; background: var(--sch-line-strong); }
.sch-gate__mono, .sch-gate__no, .sch-gate__time, .sch-gate__big,
.sch-gate__keyn, .sch-gate__chipn, .sch-gate__clock, .sch-gate__lt,
.sch-gate__evt, .sch-gate__pend b {
    font-family: var(--gate-mono); font-variant-numeric: tabular-nums; direction: ltr;
}
.sch-gate__mono { font-size: var(--sch-text-sm); font-weight: var(--sch-w-medium); color: var(--sch-ink); }
.sch-gate__pulse {
    width: 7px; height: 7px; border-radius: var(--sch-r-full);
    background: var(--sch-success); flex: 0 0 auto;
    animation: sch-gate-beat 1.6s ease infinite;
}

/* ── بطاقة الملخّص ── */
.sch-gate__sum {
    background: var(--sch-surface); border-radius: var(--sch-radius-lg); box-shadow: var(--sch-e1);
    padding: var(--sch-4); display: flex; flex-direction: column; gap: var(--sch-4);
}
.sch-gate__sumh { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--sch-4); flex-wrap: wrap; }
.sch-gate__lead { display: flex; align-items: baseline; gap: var(--sch-2); }
.sch-gate__big { font-size: var(--sch-text-3xl); font-weight: var(--sch-w-medium); line-height: var(--sch-lh-tight); color: var(--sch-ink); }
.sch-gate__of { font-size: var(--sch-text-base); color: var(--sch-muted); }
.sch-gate__keys { display: flex; align-items: center; gap: var(--sch-4); flex-wrap: wrap; }
.sch-gate__key { display: flex; align-items: center; gap: var(--sch-2); }
.sch-gate__sq { width: 9px; height: 9px; border-radius: 3px; background: var(--gate-tone); flex: 0 0 auto; }
.sch-gate__keyt { font-size: var(--sch-text-xs); color: var(--sch-ink-soft); }
.sch-gate__keyn { font-size: var(--sch-text-base); font-weight: var(--sch-w-medium); color: var(--sch-ink); }
.sch-gate__bar {
    display: flex; height: 12px; border-radius: var(--sch-r-full);
    overflow: hidden; background: var(--sch-surface-alt); gap: 2px;
}
.sch-gate__seg { display: block; transition: width var(--sch-slow) var(--sch-ease); }
.sch-gate__seg--present { background: var(--sch-success); }
.sch-gate__seg--late    { background: var(--sch-warn); }
.sch-gate__seg--excused { background: var(--sch-accent); }
.sch-gate__seg--absent  { background: var(--sch-danger); }

/* نغمة كل حالة — تُورَّث فتلوّن المربّع والنقطة والحافة والنص معًا.
   وثلاثة رموز لا اثنان: `--gate-tone` للمساحات الصمّاء (نقطة · مربّع · حافة)،
   و`--gate-ink` للنص فوق `--gate-soft`. البارز #5170FF على أبيض تباينه 3.9
   وهو يكفي سطحًا ولا يكفي كتابة — والقاعدة مكتوبة في CLAUDE.md — فوسم «بعذر»
   يأخذ الدرجة الأغمق كما يأخذها زرّه في هذه الشاشة نفسها. */
.sch-gate__key--present, .sch-gate__chip--present, .sch-gate__row[data-status="present"], .sch-gate__ev--in
    { --gate-tone: var(--sch-success); --gate-ink: var(--sch-success); --gate-soft: var(--sch-success-soft); }
.sch-gate__key--late, .sch-gate__chip--late, .sch-gate__row[data-status="late"], .sch-gate__ev--out
    { --gate-tone: var(--sch-warn); --gate-ink: var(--sch-warn); --gate-soft: var(--sch-warn-soft); }
.sch-gate__key--excused, .sch-gate__row[data-status="excused"], .sch-gate__ev--note
    { --gate-tone: var(--sch-accent); --gate-ink: var(--sch-accent-ink); --gate-soft: var(--sch-accent-soft); }
.sch-gate__key--absent, .sch-gate__row[data-status="absent"]
    { --gate-tone: var(--sch-danger); --gate-ink: var(--sch-danger); --gate-soft: var(--sch-danger-soft); }
.sch-gate__key--none, .sch-gate__chip--none, .sch-gate__chip--all, .sch-gate__row[data-status="none"]
    { --gate-tone: var(--sch-muted); --gate-ink: var(--sch-ink-soft); --gate-soft: var(--sch-surface-alt); }

/* ── صفّ المرشّحات ── */
.sch-gate__filters {
    background: var(--sch-surface); border-radius: var(--sch-radius-lg); box-shadow: var(--sch-e1);
    padding: var(--sch-3); display: flex; align-items: center; gap: var(--sch-2); flex-wrap: wrap;
}
.sch-gate__searchw { position: relative; flex: 1 1 200px; min-width: 200px; display: flex; }
.sch-gate__search { width: 100%; }
/* المحدِّق يطابق شكل قاعدة الحقول العامّة في dashboard.css ويزيد عليها صنفًا:
   تلك القاعدة تخصّصيتها (0,4,1) بفضل ثلاثة `:not`، و`.sch-body input.اسم`
   (0,2,1) تخسر أمامها مهما تأخّرت في المصدر — فتُطمس الإزاحة ويجلس رمز
   البحث فوق ما يكتبه الموظف. والسطح `--sch-paper` غائر عن البطاقة كما في
   التصميم، وينقلب مع الوضع الداكن وحده. */
.sch-body :is(input, select, textarea):not([type="checkbox"]):not([type="radio"]):not([type="submit"]).sch-gate__search {
    min-height: 40px;
    padding-block: 9px; padding-inline: 34px var(--sch-3);
    border: 1px solid var(--sch-line-strong); border-radius: var(--sch-radius);
    background: var(--sch-paper); color: var(--sch-ink);
    font-family: inherit; font-size: var(--sch-text-sm);
}
.sch-gate__ic {
    position: absolute; inset-inline-start: var(--sch-3); inset-block: 0;
    display: flex; align-items: center; color: var(--sch-muted); pointer-events: none;
}
.sch-body button.sch-gate__chip {
    display: flex; align-items: center; gap: var(--sch-2); min-height: 36px;
    padding: var(--sch-2) var(--sch-3);
    border: 1px solid var(--sch-line); border-radius: var(--sch-radius);
    background: var(--sch-surface); color: var(--sch-ink-soft);
    font-family: inherit; cursor: pointer;
}
.sch-body button.sch-gate__chip.is-on { background: var(--sch-surface-alt); border-color: var(--sch-line-strong); }
.sch-gate__chipt { font-size: var(--sch-text-xs); }
.sch-gate__chip.is-on .sch-gate__chipt { font-weight: var(--sch-w-medium); }
.sch-gate__chipn { font-size: var(--sch-text-xs); font-weight: var(--sch-w-medium); opacity: .7; }
.sch-gate__dot { width: 7px; height: 7px; border-radius: var(--sch-r-full); background: var(--gate-tone); flex: 0 0 auto; }
.sch-body button.sch-gate__toggle {
    min-height: 38px; padding: var(--sch-2) var(--sch-4);
    border: 1px solid var(--sch-line-strong); border-radius: var(--sch-radius);
    background: var(--sch-surface-alt); color: var(--sch-ink-soft);
    font-family: inherit; font-size: var(--sch-text-xs); font-weight: var(--sch-w-medium); cursor: pointer;
}
.sch-body button.sch-gate__toggle[aria-pressed="true"] { background: var(--sch-accent); color: var(--sch-on-accent); border-color: var(--sch-accent); }

/* ── الكشف: قائمة أو بطاقات، والصفّ نفسه في الوضعين ── */
.sch-gate__list {
    background: var(--sch-surface); border-radius: var(--sch-radius-lg);
    box-shadow: var(--sch-e1); overflow: hidden;
}
.sch-gate__row {
    display: grid; align-items: center;
    grid-template-columns: auto minmax(0, 1fr) auto;
    grid-template-areas: "av id meta";
    gap: var(--sch-3); padding: var(--sch-3) var(--sch-4);
    border-bottom: 1px solid var(--sch-line);
}
.sch-gate__row:last-child { border-bottom: 0; }
.sch-gate__row[hidden] { display: none; }
.sch-gate__av {
    grid-area: av; width: 34px; height: 34px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    background: var(--sch-surface-alt); color: var(--sch-ink-soft);
    font-weight: var(--sch-w-bold); font-size: var(--sch-text-base); flex: 0 0 auto;
}
.sch-gate__id { grid-area: id; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sch-gate__name { font-size: var(--sch-text-base); color: var(--sch-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sch-gate__no { font-size: var(--sch-text-xs); color: var(--sch-muted); text-align: end; }
.sch-gate__meta { grid-area: meta; display: flex; flex-direction: row-reverse; align-items: center; gap: var(--sch-3); }
.sch-gate__st {
    display: flex; align-items: center; gap: var(--sch-2);
    padding: var(--sch-1) var(--sch-2); border-radius: var(--sch-r-sm);
    background: var(--gate-soft); color: var(--gate-ink, var(--gate-tone));
    font-size: var(--sch-text-xs); font-weight: var(--sch-w-medium); white-space: nowrap;
}
.sch-gate__time { font-size: var(--sch-text-base); color: var(--sch-muted); }
.sch-gate__acts { grid-area: acts; display: none; align-items: center; gap: var(--sch-1); }
.sch-body button.sch-gate__act {
    flex: 1 1 0; min-height: 34px; padding: var(--sch-2);
    border: 1px solid transparent; border-radius: 9px;
    font-family: inherit; font-size: var(--sch-text-xs); font-weight: var(--sch-w-medium); cursor: pointer;
}
.sch-body button.sch-gate__act--absent  { background: var(--sch-danger-soft); color: var(--sch-danger); }
.sch-body button.sch-gate__act--excused { background: var(--sch-accent-soft); color: var(--sch-accent-ink); }
.sch-body button.sch-gate__act--absent:hover  { border-color: var(--sch-danger); }
.sch-body button.sch-gate__act--excused:hover { border-color: var(--sch-accent); }

.sch-gate__list.is-cards {
    background: none; box-shadow: none; overflow: visible;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(252px, 1fr)); gap: var(--sch-3);
}
.sch-gate__list.is-cards .sch-gate__row {
    position: relative; overflow: hidden; align-content: start;
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas: "av id" "meta meta" "acts acts";
    background: var(--sch-surface); border: 0; border-radius: var(--sch-radius-lg);
    box-shadow: var(--sch-e1); padding: var(--sch-3);
}
.sch-gate__list.is-cards .sch-gate__row::before {
    content: ""; position: absolute; inset-block: 0; inset-inline-end: 0;
    width: 3px; background: var(--gate-tone);
}
.sch-gate__list.is-cards .sch-gate__av { width: 38px; height: 38px; border-radius: 12px; }
.sch-gate__list.is-cards .sch-gate__meta {
    flex-direction: row; justify-content: space-between;
    padding-top: var(--sch-3); border-top: 1px solid var(--sch-line);
}
.sch-gate__list.is-cards .sch-gate__acts { display: flex; }

/* ── اللوح الحيّ — قشرة داكنة في الوضعين ── */
.sch-gate__live {
    width: 334px; flex: 0 0 auto; min-width: 300px; align-self: flex-start;
    position: sticky; top: var(--sch-4);
    background: var(--sch-chrome-2); color: var(--sch-chrome-ink);
    border-radius: var(--sch-r-xl); box-shadow: var(--sch-e3);
    padding: var(--sch-4); display: flex; flex-direction: column; gap: var(--sch-4);
}
.sch-gate__lh { display: flex; align-items: center; justify-content: space-between; gap: var(--sch-2); }
.sch-gate__lht { display: flex; align-items: center; gap: var(--sch-2); }
.sch-gate__lht b { font-size: var(--sch-text-base); font-weight: var(--sch-w-bold); }
.sch-gate__clock { font-size: var(--sch-text-sm); font-weight: var(--sch-w-medium); color: var(--sch-chrome-mut); }
.sch-gate__last {
    position: relative; overflow: hidden;
    border-radius: var(--sch-radius-lg); border: 1px solid var(--sch-nav-line);
    background: var(--sch-chrome); padding: var(--sch-4);
    display: flex; flex-direction: column; gap: var(--sch-2);
}
.sch-gate__sweep {
    position: absolute; inset: 0; pointer-events: none; opacity: .16;
    background: linear-gradient(transparent, var(--sch-success), transparent);
    animation: sch-gate-sweep 2.6s linear infinite;
}
.sch-gate__cap { font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); }
.sch-gate__lname { font-size: var(--sch-text-lg); font-weight: var(--sch-w-bold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sch-gate__lrow { display: flex; align-items: center; gap: var(--sch-2); }
.sch-gate__lt { font-size: var(--sch-text-xl); font-weight: var(--sch-w-medium); color: var(--sch-success); }
.sch-gate__lmeta { font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); }
.sch-gate__feedw { display: flex; flex-direction: column; gap: var(--sch-2); flex: 1 1 auto; min-height: 180px; }
.sch-gate__feed { display: flex; flex-direction: column; gap: var(--sch-1); }
.sch-gate__ev {
    display: flex; align-items: center; gap: var(--sch-2);
    padding: var(--sch-2) var(--sch-3); border-radius: 11px; background: var(--sch-nav-hover);
}
.sch-gate__evn { flex: 1 1 auto; min-width: 0; font-size: var(--sch-text-sm); font-weight: var(--sch-w-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sch-gate__evl { font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); white-space: nowrap; }
.sch-gate__evt { font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); }
.sch-gate__none { padding: var(--sch-4) 0; text-align: center; font-size: var(--sch-text-sm); color: var(--sch-chrome-mut); }
.sch-gate__foot { border-top: 1px solid var(--sch-nav-line); padding-top: var(--sch-3); display: flex; flex-direction: column; gap: var(--sch-3); }
.sch-gate__pend { display: flex; align-items: center; justify-content: space-between; font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); }
.sch-gate__pend b { font-size: var(--sch-text-lg); font-weight: var(--sch-w-medium); color: var(--sch-chrome-ink); }
.sch-body button.sch-gate__close {
    width: 100%; min-height: 46px; padding: var(--sch-3);
    border: 1px solid var(--sch-chrome-ink); border-radius: var(--sch-radius-lg);
    background: var(--sch-chrome-ink); color: var(--sch-chrome);
    font-family: inherit; font-size: var(--sch-text-sm); font-weight: var(--sch-w-bold); cursor: pointer;
}
.sch-body button.sch-gate__close[disabled] { opacity: .45; cursor: not-allowed; }
.sch-gate__hint { font-size: var(--sch-text-xs); color: var(--sch-chrome-mut); line-height: var(--sch-lh-read); text-wrap: pretty; }

@keyframes sch-gate-beat { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
@keyframes sch-gate-sweep { 0% { transform: translateY(-100%); } 100% { transform: translateY(100%); } }

@media (max-width: 900px) {
    .sch-gate__main { min-width: 0; }
    .sch-gate__live { width: 100%; position: static; }
}
@media (prefers-reduced-motion: reduce) {
    .sch-gate__pulse, .sch-gate__sweep { animation: none; }
    .sch-gate__seg { transition: none; }
}
</style>

<script>
/* الوضعان والمرشّحات والبحث في المتصفّح: الطلاب مُصيَّرون مرّة واحدة،
   والسكربت يُظهر ويُخفي — فلا إعادة تحميل بين ضغطة وأخرى.
   واللوح الحيّ يُجلب من الصفحة نفسها لا من مسار جديد: الصلاحية محسومة
   بالصفحة، ومسار REST إضافي يعني بابًا آخر يُحرَس. */
(function () {
  var list = document.getElementById('sch-gate-list');
  var box  = document.getElementById('sch-gate-q');
  var mode = document.getElementById('sch-gate-mode');
  var chips = document.querySelectorAll('[data-sch-filter]');
  var rows = list ? Array.prototype.slice.call(list.querySelectorAll('.sch-gate__row')) : [];
  var pick = 'all';

  function apply() {
    var term = (box && box.value ? box.value : '').trim().toLowerCase();

    rows.forEach(function (row) {
      var okState = pick === 'all' || row.getAttribute('data-status') === pick;
      var okTerm  = term === '' || (row.getAttribute('data-find') || '').toLowerCase().indexOf(term) > -1;
      row.hidden = !(okState && okTerm);
    });
  }

  Array.prototype.forEach.call(chips, function (chip) {
    chip.addEventListener('click', function () {
      pick = chip.getAttribute('data-sch-filter');

      Array.prototype.forEach.call(chips, function (c) {
        var on = c === chip;
        c.classList.toggle('is-on', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      apply();
    });
  });

  if (box) { box.addEventListener('input', apply); }

  if (mode && list) {
    mode.addEventListener('click', function () {
      var on = !list.classList.contains('is-cards');
      list.classList.toggle('is-cards', on);
      mode.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  /* ── الساعة الحيّة ── */
  var secs = -1;
  var digits = [];

  function readClock() {
    var el = document.getElementById('sch-gate-clock');
    if (!el) { return null; }

    var parts = (el.getAttribute('data-t') || '').split(':');
    digits = (el.getAttribute('data-d') || '0123456789').split('');
    secs = (+parts[0] || 0) * 3600 + (+parts[1] || 0) * 60 + (+parts[2] || 0);
    return el;
  }

  function two(n) {
    var s = n < 10 ? '0' + n : '' + n;
    return s.replace(/\d/g, function (c) { return digits[+c] || c; });
  }

  function tick() {
    var el = document.getElementById('sch-gate-clock');
    if (!el || secs < 0) { return; }

    secs = (secs + 1) % 86400;
    el.textContent = two(Math.floor(secs / 3600)) + ':' + two(Math.floor(secs / 60) % 60) + ':' + two(secs % 60);
  }

  /* ── تحديث اللوح وحده ── */
  function refresh() {
    if (document.hidden) { return; }

    window.fetch(window.location.href, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
      .then(function (html) {
        var live = document.getElementById('sch-gate-live');
        var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('sch-gate-live');

        /* لا يُسحب اللوح من تحت يد من يضغط زرّه في هذه اللحظة. */
        if (live && fresh && !live.contains(document.activeElement)) {
          live.innerHTML = fresh.innerHTML;
          readClock();
        }
      })
      .catch(function () {});
  }

  readClock();
  var beat = window.setInterval(tick, 1000);
  var pull = window.setInterval(refresh, 8000);

  window.addEventListener('pagehide', function () {
    window.clearInterval(beat);
    window.clearInterval(pull);
  });
}());
</script>
