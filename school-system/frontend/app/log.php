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
    $rate = SCH_Attendance::rate($id);

    // إحصاء كل حالة مرة واحدة — يُقرأ الملخص من فوق قبل التفصيل.
    $tally = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
    foreach ($days as $d) {
        $s = (string) $d->status;
        if (isset($tally[$s])) {
            $tally[$s]++;
        }
    }
    // الحلقة: نصف قطر ٣٩ ⇒ محيط ٢٤٥ — القوس يمتلئ بنسبة الحضور.
    $circ = 245.0;
    ?>

    <?php if ($days === []) : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('check', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا سجل حضور بعد', 'school-system'); ?></b>
            <p><?php esc_html_e('يظهر هنا بعد أول يوم دراسي.', 'school-system'); ?></p>
        </div>
    <?php else : ?>

        <!-- ملخص الحضور: حلقة كبيرة + أربع حالات -->
        <div class="p-stat">
            <span class="p-stat__ring">
                <svg width="88" height="88" viewBox="0 0 88 88" aria-hidden="true">
                    <circle cx="44" cy="44" r="39" fill="none" stroke="var(--p-alt)" stroke-width="8"/>
                    <circle cx="44" cy="44" r="39" fill="none" stroke="var(--p-brand)" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="<?php echo esc_attr((string) $circ); ?>"
                            stroke-dashoffset="<?php echo esc_attr((string) round($circ * (1 - $rate / 100), 1)); ?>"
                            transform="rotate(-90 44 44)"/>
                </svg>
                <b><?php echo esc_html(number_format($rate, 0)); ?><i>٪</i></b>
            </span>
            <div class="p-stat__grid">
                <div class="p-stat__cell p-stat__cell--ok">
                    <b class="p-nm"><?php echo esc_html(number_format_i18n($tally['present'])); ?></b>
                    <span><?php echo esc_html(SCH_Attendance::STATUSES['present']); ?></span>
                </div>
                <div class="p-stat__cell p-stat__cell--warn">
                    <b class="p-nm"><?php echo esc_html(number_format_i18n($tally['late'])); ?></b>
                    <span><?php echo esc_html(SCH_Attendance::STATUSES['late']); ?></span>
                </div>
                <div class="p-stat__cell p-stat__cell--late">
                    <b class="p-nm"><?php echo esc_html(number_format_i18n($tally['absent'])); ?></b>
                    <span><?php echo esc_html(SCH_Attendance::STATUSES['absent']); ?></span>
                </div>
                <div class="p-stat__cell">
                    <b class="p-nm"><?php echo esc_html(number_format_i18n($tally['excused'])); ?></b>
                    <span><?php echo esc_html(SCH_Attendance::STATUSES['excused']); ?></span>
                </div>
            </div>
        </div>

        <!-- شبكة الأيام: لون كل مربّع يحكي حالته -->
        <div class="p-sect"><h2 class="p-sect__h"><?php esc_html_e('كل يوم', 'school-system'); ?></h2></div>
        <div class="p-grid60">
            <?php foreach (array_reverse($days) as $d) :
                $tone = match ((string) $d->status) {
                    'present' => 'ok',
                    'late'    => 'warn',
                    'absent'  => 'late',
                    default   => 'mute',
                }; ?>
                <i class="p-cell p-cell--<?php echo esc_attr($tone); ?>"
                   title="<?php echo esc_attr(wp_date('j M', strtotime((string) $d->att_date)) . ' · ' . (SCH_Attendance::STATUSES[$d->status] ?? '')); ?>"></i>
            <?php endforeach; ?>
        </div>
        <div class="p-legend">
            <span><i style="background:var(--p-ok)"></i><?php echo esc_html(SCH_Attendance::STATUSES['present']); ?></span>
            <span><i style="background:var(--p-warn)"></i><?php echo esc_html(SCH_Attendance::STATUSES['late']); ?></span>
            <span><i style="background:var(--p-n3)"></i><?php echo esc_html(SCH_Attendance::STATUSES['absent']); ?></span>
            <span><i style="background:var(--p-line-2)"></i><?php echo esc_html(SCH_Attendance::STATUSES['excused']); ?></span>
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
                <b><?php echo esc_html(number_format($avg, 0)); ?><i>٪</i></b>
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
                        <b class="p-nm"><?php echo esc_html(number_format($percent, 1)); ?>٪</b>
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
