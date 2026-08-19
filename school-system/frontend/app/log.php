<?php
/** السجل — الحضور (ملخص + شبكة الأيام) والدرجات (أشرطة بصرية). */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id  = (int) ($sch_data['id'] ?? 0);
$tab = isset($_GET['t']) && $_GET['t'] === 'grades' ? 'grades' : 'att';
?>

<h1 class="p-h1"><?php esc_html_e('السجل', 'school-system'); ?></h1>

<div class="p-seg">
    <a class="<?php echo esc_attr($tab === 'att' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(SCH_App::url('log', $id)); ?>"><?php esc_html_e('الحضور', 'school-system'); ?></a>
    <a class="<?php echo esc_attr($tab === 'grades' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(add_query_arg('t', 'grades', SCH_App::url('log', $id))); ?>"><?php esc_html_e('الدرجات', 'school-system'); ?></a>
</div>

<?php if ($tab === 'att') :
    $days = SCH_Attendance::history($id, 60);

    // إحصاء كل حالة مرة واحدة — يُقرأ الملخص من فوق قبل التفصيل.
    $tally = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
    foreach ($days as $d) {
        $s = (string) $d->status;
        if (isset($tally[$s])) {
            $tally[$s]++;
        }
    }

    $sch_total = count($days);
    // **الإجازة المعتمدة لا تُحسب غيابًا.** النسبة العامة تقسم على كل الأيام،
    // فطفلٌ في إجازة أذِنت بها المدرسة نفسها يظهر لأبيه بـ٤٦٪ كأنه متغيّب —
    // وهذا نقضٌ لمعنى اعتماد الإجازة. هنا نقسم على **أيام الدوام المطلوبة**.
    $sch_due     = max(0, $sch_total - $tally['excused']);
    $sch_came    = $tally['present'] + $tally['late'];
    $sch_pct     = $sch_due > 0 ? (int) round($sch_came / $sch_due * 100) : 100;
    // خمسة أعمدة: صفٌّ لكل أسبوع دراسي — فتُقرأ الشبكة أسابيع لا شريطًا
    $sch_cols    = max(1, min(5, $sch_total));

    // نِسَب الشريط المكدّس — على كل الأيام لأنه يعرض التركيب لا النسبة.
    $sch_w = static fn (int $n): string => $sch_total > 0
        ? (string) round($n / $sch_total * 100, 2) . '%'
        : '0%';
    ?>

    <?php if ($days === []) : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('check', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا سجل حضور بعد', 'school-system'); ?></b>
            <p><?php esc_html_e('يظهر هنا بعد أول يوم دراسي.', 'school-system'); ?></p>
        </div>
    <?php else : ?>

        <!-- الحكم: رقم بطل + جملة تقولـه، ثم التركيب، ثم المفاتيح -->
        <div class="p-score">
            <div class="p-score__top">
                <div class="p-score__n">
                    <span class="p-amt"><span class="p-nm"><?php echo esc_html(number_format_i18n($sch_pct)); ?></span><i class="p-cur">٪</i></span>
                </div>
                <div class="p-score__t">
                    <b><?php echo esc_html(sprintf(
                        /* translators: 1: أيام الحضور 2: أيام الدوام المطلوبة */
                        __('حضر %1$s من %2$s أيام دوام', 'school-system'),
                        number_format_i18n($sch_came),
                        number_format_i18n($sch_due)
                    )); ?></b>
                    <span><?php echo esc_html($tally['excused'] > 0
                        ? sprintf(
                            /* translators: %s: عدد الأيام */
                            _n('ويوم إجازة معتمدة لا يُحتسب غيابًا', 'و%s أيام إجازة معتمدة لا تُحتسب غيابًا', $tally['excused'], 'school-system'),
                            number_format_i18n($tally['excused'])
                        )
                        : __('آخر ٦٠ يومًا', 'school-system')); ?></span>
                </div>
            </div>

            <div class="p-split" aria-hidden="true">
                <?php if ($tally['present'] > 0) : ?><i class="is-ok" style="--w:<?php echo esc_attr($sch_w($tally['present'])); ?>"></i><?php endif; ?>
                <?php if ($tally['late'] > 0) : ?><i class="is-warn" style="--w:<?php echo esc_attr($sch_w($tally['late'])); ?>"></i><?php endif; ?>
                <?php if ($tally['absent'] > 0) : ?><i class="is-bad" style="--w:<?php echo esc_attr($sch_w($tally['absent'])); ?>"></i><?php endif; ?>
                <?php if ($tally['excused'] > 0) : ?><i class="is-excused" style="--w:<?php echo esc_attr($sch_w($tally['excused'])); ?>"></i><?php endif; ?>
            </div>

            <div class="p-tally">
                <?php foreach ([
                    ['present', 'is-ok'],
                    ['late',    'is-warn'],
                    ['absent',  'is-bad'],
                    ['excused', 'is-excused'],
                ] as [$sch_k, $sch_cls]) : ?>
                    <span class="p-tally__i <?php echo esc_attr($sch_cls); ?>">
                        <i></i><?php echo esc_html(SCH_Attendance::STATUSES[$sch_k]); ?>
                        <b><?php echo esc_html(number_format_i18n($tally[$sch_k])); ?></b>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- شريط الأيام: لون كل مربّع يحكي حالته، والأعمدة بعدد الأيام -->
        <div class="p-sect">
            <h2 class="p-sect__h"><?php esc_html_e('يومًا بيوم', 'school-system'); ?></h2>
            <span class="p-sect__a"><?php echo esc_html(sprintf(
                /* translators: 1: أول تاريخ 2: آخر تاريخ */
                __('%1$s — %2$s', 'school-system'),
                wp_date('j M', strtotime((string) end($days)->att_date)),
                wp_date('j M', strtotime((string) $days[0]->att_date))
            )); ?></span>
        </div>
        <div class="p-days" style="--n:<?php echo esc_attr((string) $sch_cols); ?>">
            <?php foreach (array_reverse($days) as $d) :
                $tone = match ((string) $d->status) {
                    'present' => 'ok',
                    'late'    => 'warn',
                    'absent'  => 'late',
                    default   => 'excused',
                }; ?>
                <i class="p-cell p-cell--<?php echo esc_attr($tone); ?>"
                   title="<?php echo esc_attr(wp_date('j M', strtotime((string) $d->att_date)) . ' · ' . (SCH_Attendance::STATUSES[$d->status] ?? '')); ?>"></i>
            <?php endforeach; ?>
        </div>
        <div class="p-legend">
            <?php /* لا يُعرض في الدليل إلا لونٌ ظهر فعلًا — الباقي تشويش */ ?>
            <?php foreach ([
                ['present', 'var(--p-ok)'],
                ['late',    'var(--p-warn)'],
                ['absent',  'var(--p-n3)'],
                ['excused', 'var(--p-excused)'],
            ] as [$sch_k, $sch_c]) : ?>
                <?php if ($tally[$sch_k] > 0) : ?>
                    <span><i style="background:<?php echo esc_attr($sch_c); ?>"></i><?php echo esc_html(SCH_Attendance::STATUSES[$sch_k]); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

<?php else :
    $marks = SCH_Assessment::report_card($id);

    // المعدل العام = متوسط نِسب المواد.
    $avg = 0.0;
    if ($marks !== []) {
        $sum = 0.0;
        foreach ($marks as $data) {
            $sum += (float) ($data['percent'] ?? 0);
        }
        $avg = round($sum / count($marks), 1);
    }
    $circ = 245.0;
    ?>

    <?php if ($marks === []) : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('award', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا درجات بعد', 'school-system'); ?></b>
            <p><?php esc_html_e('تظهر هنا بعد رصد أول اختبار.', 'school-system'); ?></p>
        </div>
    <?php else : ?>

        <!-- المعدل العام في حلقة -->
        <div class="p-stat">
            <span class="p-stat__ring">
                <svg width="88" height="88" viewBox="0 0 88 88" aria-hidden="true">
                    <circle cx="44" cy="44" r="39" fill="none" stroke="var(--p-alt)" stroke-width="8"/>
                    <circle cx="44" cy="44" r="39" fill="none" stroke="var(--p-brand)" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="<?php echo esc_attr((string) $circ); ?>"
                            stroke-dashoffset="<?php echo esc_attr((string) round($circ * (1 - $avg / 100), 1)); ?>"
                            transform="rotate(-90 44 44)"/>
                </svg>
                <b><?php echo esc_html(number_format_i18n($avg, 0)); ?><i>٪</i></b>
            </span>
            <div class="p-stat__lead">
                <b><?php esc_html_e('المعدل العام', 'school-system'); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: 1: عدد المواد 2: التقدير */
                    __('%1$s مادة · %2$s', 'school-system'),
                    number_format_i18n(count($marks)),
                    SCH_Assessment::grade_label($avg)
                )); ?></span>
            </div>
        </div>

        <!-- أشرطة المواد -->
        <div class="p-sect"><h2 class="p-sect__h"><?php esc_html_e('المواد', 'school-system'); ?></h2></div>
        <div class="p-bars">
            <?php foreach ($marks as $subject => $data) :
                $percent = (float) ($data['percent'] ?? 0);
                $tier = match (true) {
                    $percent >= 90 => 'ex',
                    $percent >= 75 => 'gd',
                    $percent >= 60 => 'ps',
                    default        => 'fl',
                }; ?>
                <div class="p-bar p-bar--<?php echo esc_attr($tier); ?>">
                    <div class="p-bar__top">
                        <b><?php echo esc_html((string) $subject); ?></b>
                        <b class="p-nm"><?php echo esc_html(number_format_i18n($percent, 1)); ?>٪</b>
                    </div>
                    <div class="p-bar__track"><i style="width:<?php echo esc_attr((string) min(100, max(0, $percent))); ?>%"></i></div>
                    <div class="p-bar__sub">
                        <span><?php echo esc_html(sprintf(
                            /* translators: %s: عدد الاختبارات */
                            __('%s اختبارًا', 'school-system'),
                            number_format_i18n(count($data['exams'] ?? []))
                        )); ?></span>
                        <em><?php echo esc_html(SCH_Assessment::grade_label($percent)); ?></em>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
<?php endif; ?>
