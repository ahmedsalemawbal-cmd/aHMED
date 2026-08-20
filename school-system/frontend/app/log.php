<?php
/**
 * السجل — الحضور (أربع حقائق وتقويم الشهر) والدرجات (المتوسّط وأشرطة المواد).
 *
 * شاشة واحدة بلا تبويب: الحضور والدرجات كلاهما جواب السؤال نفسه
 * «كيف أداء ابني»، والتبويب كان يُخفي نصف الجواب خلف ضغطة.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id    = (int) ($sch_data['id'] ?? 0);
$days  = SCH_Attendance::history($id, 60);
$marks = SCH_Assessment::report_card($id);
?>

<h1 class="p-h1"><?php esc_html_e('السجل', 'school-system'); ?></h1>

<?php if ($days === []) : ?>

    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('check', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا سجل حضور بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('يظهر هنا بعد أول يوم دراسي.', 'school-system'); ?></p>
    </div>

<?php else :

    // إحصاء كل حالة مرة واحدة — يُقرأ الملخص من فوق قبل التفصيل.
    // ومعه تواريخها: البطاقة تقول «متى» لا «كم» وحدها، والسجل مرتّب
    // تنازليًّا فأوّل ما يقابلنا من كل حالة هو آخر ما وقع منها.
    $tally = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
    $sch_last_absent = '';
    $sch_late_days   = [];
    foreach ($days as $d) {
        $s = (string) $d->status;
        if (isset($tally[$s])) {
            $tally[$s]++;
        }
        if ($s === 'absent' && $sch_last_absent === '') {
            $sch_last_absent = (string) $d->att_date;
        }
        if ($s === 'late' && count($sch_late_days) < 2) {
            $sch_late_days[] = wp_date('j M', strtotime((string) $d->att_date));
        }
    }

    $sch_total = count($days);
    // **الإجازة المعتمدة لا تُحسب غيابًا.** النسبة العامة تقسم على كل الأيام،
    // فطفلٌ في إجازة أذِنت بها المدرسة نفسها يظهر لأبيه بـ٤٦٪ كأنه متغيّب —
    // وهذا نقضٌ لمعنى اعتماد الإجازة. هنا نقسم على **أيام الدوام المطلوبة**.
    $sch_due  = max(0, $sch_total - $tally['excused']);
    $sch_came = $tally['present'] + $tally['late'];
    $sch_pct  = $sch_due > 0 ? (int) round($sch_came / $sch_due * 100) : 100;

    // مدى السجل: الأقدم في آخر الصفوف والأحدث في أوّلها.
    $sch_from = (string) end($days)->att_date;
    $sch_to   = (string) $days[0]->att_date;
    reset($days);

    $sch_m_from = wp_date('F', strtotime($sch_from));
    $sch_m_to   = wp_date('F', strtotime($sch_to));

    // شبكة الأسابيع: عمودٌ لكل يوم دراسي (الأحد → الخميس) وصفٌّ لكل أسبوع،
    // فيُقرأ الغياب نمطًا («كل ثلاثاء») لا نقاطًا متفرّقة في شريط واحد.
    // ويومٌ لا سجلّ له عطلةٌ لا غياب — ولذلك خانته مفرَّغة لا ملوّنة.
    $sch_map = [];
    foreach ($days as $d) {
        $sch_map[(string) $d->att_date] = (string) $d->status;
    }

    $sch_head = strtotime($sch_from);
    $sch_head -= ((int) gmdate('w', $sch_head)) * DAY_IN_SECONDS; // 0 = الأحد
    $sch_tail = strtotime($sch_to);

    $sch_grid = [];
    for ($sch_wk = $sch_head; $sch_wk <= $sch_tail; $sch_wk += 7 * DAY_IN_SECONDS) {
        for ($sch_i = 0; $sch_i < 5; $sch_i++) {
            $sch_ymd = gmdate('Y-m-d', $sch_wk + $sch_i * DAY_IN_SECONDS);
            $sch_grid[$sch_ymd] = $sch_map[$sch_ymd] ?? '';
        }
    }
    $sch_off = count(array_filter($sch_grid, static fn (string $st): bool => $st === ''));
    ?>

    <!-- أربع حقائق قبل التفصيل: الرقم أوّلًا، ولونه يقول نوعه -->
    <div class="p-facts">

        <div class="p-fact p-fact--ok">
            <span class="p-fact__k"><?php esc_html_e('الحضور', 'school-system'); ?></span>
            <span class="p-amt p-fact__v">
                <span class="p-nm"><?php echo esc_html(number_format_i18n($sch_pct)); ?></span><i class="p-fact__u">٪</i>
            </span>
            <span class="p-fact__s"><?php echo esc_html(sprintf(
                /* translators: 1: أيام الحضور 2: أيام الدوام المطلوبة */
                __('حضر %1$s من %2$s يوم دوام', 'school-system'),
                number_format_i18n($sch_came),
                number_format_i18n($sch_due)
            )); ?></span>
        </div>

        <div class="p-fact p-fact--bad">
            <span class="p-fact__k"><?php echo esc_html(SCH_Attendance::STATUSES['absent']); ?></span>
            <span class="p-amt p-fact__v">
                <span class="p-nm"><?php echo esc_html(number_format_i18n($tally['absent'])); ?></span><i class="p-fact__u"><?php echo esc_html(_n('يوم', 'أيام', $tally['absent'], 'school-system')); ?></i>
            </span>
            <span class="p-fact__s"><?php echo esc_html($sch_last_absent !== ''
                ? sprintf(
                    /* translators: %s: تاريخ آخر غياب */
                    __('آخرها %s', 'school-system'),
                    wp_date('j M', strtotime($sch_last_absent))
                )
                : __('لا غياب في السجل', 'school-system')); ?></span>
        </div>

        <div class="p-fact p-fact--warn">
            <span class="p-fact__k"><?php echo esc_html(SCH_Attendance::STATUSES['late']); ?></span>
            <span class="p-amt p-fact__v">
                <span class="p-nm"><?php echo esc_html(number_format_i18n($tally['late'])); ?></span><i class="p-fact__u"><?php echo esc_html(_n('مرّة', 'مرّات', $tally['late'], 'school-system')); ?></i>
            </span>
            <span class="p-fact__s"><?php echo esc_html($sch_late_days !== []
                ? implode(' · ', $sch_late_days)
                : __('بلا تأخير', 'school-system')); ?></span>
        </div>

        <div class="p-fact p-fact--pri">
            <span class="p-fact__k"><?php echo esc_html(SCH_Attendance::STATUSES['excused']); ?></span>
            <span class="p-amt p-fact__v">
                <span class="p-nm"><?php echo esc_html(number_format_i18n($tally['excused'])); ?></span><i class="p-fact__u"><?php echo esc_html(_n('يوم', 'أيام', $tally['excused'], 'school-system')); ?></i>
            </span>
            <span class="p-fact__s"><?php esc_html_e('معتمَدة من الإدارة', 'school-system'); ?></span>
        </div>

    </div>

    <!-- التقويم: مربّعٌ لكل يوم، ولونه وحده هو الخبر -->
    <div class="p-cal">

        <div class="p-cal__top">
            <span class="p-cal__h"><?php echo esc_html($sch_m_from === $sch_m_to
                ? sprintf(
                    /* translators: %s: اسم الشهر */
                    __('شهر %s', 'school-system'),
                    $sch_m_to
                )
                : sprintf(
                    /* translators: 1: شهر أول السجل 2: شهر آخره */
                    __('%1$s — %2$s', 'school-system'),
                    $sch_m_from,
                    $sch_m_to
                )); ?></span>
            <span class="p-cal__a"><?php echo esc_html(sprintf(
                /* translators: %s: عدد الأيام المرصودة */
                _n('%s يوم دراسي', '%s يومًا دراسيًّا', $sch_total, 'school-system'),
                number_format_i18n($sch_total)
            )); ?></span>
        </div>

        <div class="p-cal__wd" aria-hidden="true">
            <?php foreach ([
                __('الأحد', 'school-system'),
                __('الاثنين', 'school-system'),
                __('الثلاثاء', 'school-system'),
                __('الأربعاء', 'school-system'),
                __('الخميس', 'school-system'),
            ] as $sch_wd) : ?>
                <span><?php echo esc_html($sch_wd); ?></span>
            <?php endforeach; ?>
        </div>

        <div class="p-cal__grid">
            <?php foreach ($sch_grid as $sch_ymd => $sch_st) :
                $tone = match ($sch_st) {
                    'present' => 'ok',
                    'late'    => 'late',
                    'absent'  => 'bad',
                    'excused' => 'exc',
                    default   => 'off',
                }; ?>
                <i class="p-cal__d p-cal__d--<?php echo esc_attr($tone); ?>"
                   title="<?php echo esc_attr(wp_date('j M', strtotime((string) $sch_ymd)) . ' · ' . (SCH_Attendance::STATUSES[$sch_st] ?? __('عطلة', 'school-system'))); ?>"></i>
            <?php endforeach; ?>
        </div>

        <div class="p-cal__key">
            <?php /* لا يُعرض في الدليل إلا لونٌ ظهر فعلًا — الباقي تشويش */ ?>
            <?php foreach ([
                ['present', 'ok'],
                ['late',    'late'],
                ['absent',  'bad'],
                ['excused', 'exc'],
            ] as [$sch_k, $sch_cls]) : ?>
                <?php if ($tally[$sch_k] > 0) : ?>
                    <span><i class="p-cal__sw p-cal__sw--<?php echo esc_attr($sch_cls); ?>"></i><?php echo esc_html(SCH_Attendance::STATUSES[$sch_k]); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($sch_off > 0) : ?>
                <span><i class="p-cal__sw p-cal__sw--off"></i><?php esc_html_e('عطلة', 'school-system'); ?></span>
            <?php endif; ?>
        </div>

    </div>

<?php endif; ?>

<?php
// المعدل العام = متوسط نِسب المواد.
$avg = 0.0;
if ($marks !== []) {
    $sum = 0.0;
    foreach ($marks as $data) {
        $sum += (float) ($data['percent'] ?? 0);
    }
    $avg = round($sum / count($marks), 1);

    // الأعلى أوّلًا: القائمة تُقرأ من قمّتها، وترتيب الأبجدية لا يقول شيئًا.
    uasort($marks, static fn (array $a, array $b): int
        => ((float) ($b['percent'] ?? 0)) <=> ((float) ($a['percent'] ?? 0)));
}
?>

<?php if ($marks === []) : ?>

    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('award', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا درجات بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('تظهر هنا بعد رصد أول اختبار.', 'school-system'); ?></p>
    </div>

<?php else : ?>

    <!-- المتوسّط العام: رقمٌ واحد في طرف البطاقة، وما يفسّره في طرفها -->
    <div class="p-avg">
        <div class="p-avg__t">
            <span class="p-avg__k"><?php esc_html_e('المتوسّط العام', 'school-system'); ?></span>
            <span class="p-avg__s"><?php echo esc_html(sprintf(
                /* translators: 1: عدد المواد 2: التقدير */
                __('%1$s مادة · %2$s', 'school-system'),
                number_format_i18n(count($marks)),
                SCH_Assessment::grade_label($avg)
            )); ?></span>
        </div>
        <span class="p-amt p-avg__v">
            <span class="p-nm"><?php echo esc_html(number_format_i18n($avg, 0)); ?></span><i class="p-cur">٪</i>
        </span>
    </div>

    <!-- المواد كلّها في بطاقة واحدة مرتّبة من الأعلى للأدنى -->
    <div class="p-bars">
        <span class="p-bars__h"><?php esc_html_e('الدرجات', 'school-system'); ?></span>
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
                    <b class="p-amt"><span class="p-nm"><?php echo esc_html(number_format_i18n($percent, 1)); ?></span><i class="p-cur">٪</i></b>
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
