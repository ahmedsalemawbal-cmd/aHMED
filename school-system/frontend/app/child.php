<?php
/** شاشة اليوم — البطل الحيّ + الخط الزمني ليوم الطفل (القلب). */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    require SCH_PATH . 'frontend/app/home.php';
    return;
}

$today    = SCH_App::today($id);
$trip     = $today['trip'];
$leave    = SCH_StudentLeave::on_leave_today($id);
$rate     = SCH_Attendance::rate($id);
$thread   = SCH_App::day_thread($id);
$first    = $student->first_name ?: SCH_Enrollment::full_name($student);

// نوع البطل: حالة واحدة تُقرأ في لحظة.
if ($leave) {
    $hero = 'home';
} elseif ($trip) {
    $hero = 'bus';
} elseif ($student->custody_state === 'at_school') {
    $hero = 'school';
} else {
    $hero = 'home';
}
?>

<!-- ═════ البطل: أين ابني الآن — يتشكّل حسب الحالة ═════ -->
<div class="p-hero p-hero--<?php echo esc_attr($hero); ?>">
    <div class="p-hero__sheen" aria-hidden="true"></div>

    <?php if ($hero === 'bus') : ?>
        <div class="p-hero__top">
            <span class="p-hero__badge"><i></i><?php esc_html_e('مباشر', 'school-system'); ?></span>
            <span class="p-hero__time"><?php echo esc_html($trip['direction'] === 'morning' ? __('رحلة الذهاب', 'school-system') : __('رحلة العودة', 'school-system')); ?></span>
        </div>
        <h2 class="p-hero__h"><?php echo esc_html(sprintf(/* translators: %s: اسم الطفل */ __('%s في الباص', 'school-system'), $first)); ?></h2>
        <p class="p-hero__sub"><?php echo esc_html(SCH_App::trip_state_label($trip['state']) ?: __('الرحلة جارية الآن', 'school-system')); ?></p>

        <div class="p-route">
            <div class="p-route__line"><i></i></div>
            <div class="p-route__bus"><?php echo sch_icon('bus', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
            <div class="p-route__stops"><span><?php esc_html_e('المدرسة', 'school-system'); ?></span><span><?php esc_html_e('الآن', 'school-system'); ?></span><span><?php esc_html_e('المنزل', 'school-system'); ?></span></div>
        </div>
        <div class="p-hero__cta">
            <a class="p-hero__btn is-solid p-tap" href="<?php echo esc_url(SCH_App::url('track', $id)); ?>"><?php esc_html_e('التتبّع على الخريطة', 'school-system'); ?></a>
        </div>

    <?php elseif ($hero === 'school') : ?>
        <div class="p-hero__top">
            <span class="p-hero__badge"><i></i><?php esc_html_e('في المدرسة', 'school-system'); ?></span>
        </div>
        <h2 class="p-hero__h"><?php echo esc_html(sprintf(/* translators: %s: اسم الطفل */ __('%s بأمان في المدرسة', 'school-system'), $first)); ?></h2>
        <p class="p-hero__sub"><?php echo esc_html($today['attendance'] ? __('سُجّل دخوله وحضوره مؤكّد', 'school-system') : __('سُجّل دخوله من البوابة', 'school-system')); ?></p>

        <div class="p-hero__facts">
            <span class="p-hero__ring">
                <svg width="56" height="56" viewBox="0 0 56 56" aria-hidden="true">
                    <circle cx="28" cy="28" r="23" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="5"/>
                    <circle cx="28" cy="28" r="23" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round"
                            stroke-dasharray="144.5" stroke-dashoffset="<?php echo esc_attr((string) round(144.5 * (1 - $rate / 100), 1)); ?>" transform="rotate(-90 28 28)"/>
                </svg>
                <b><?php echo esc_html(number_format($rate, 0)); ?>٪</b>
            </span>
            <div class="p-hero__list">
                <div><span><?php esc_html_e('الحضور', 'school-system'); ?></span><b><?php echo esc_html(number_format($rate, 0)); ?>٪</b></div>
                <div><span><?php esc_html_e('الحالة', 'school-system'); ?></span><b><?php echo esc_html(SCH_Custody::state_label($student->custody_state)); ?></b></div>
            </div>
        </div>

        <?php
        /* نداء الانصراف — الفعل الوحيد الذي يعني شيئًا وابنك داخل المدرسة.
           «طلب إجازة» هنا لا معنى له: هو حاضر بالفعل. */
        $sch_call = SCH_Pickup::open_for($id);
        ?>
        <?php if ($sch_call) : ?>
            <div class="p-hero__call">
                <span class="p-hero__call-s">
                    <i></i><?php echo esc_html(SCH_Pickup::STATUSES[$sch_call->status] ?? ''); ?>
                </span>
                <p><?php esc_html_e('انتظر في مكانك — تُحضره المشرفة وتسلّمه لك.', 'school-system'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('sch_app_pickup', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_app_action" value="pickup_cancel">
                    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
                    <button type="submit" class="p-hero__btn p-tap"><?php esc_html_e('إلغاء النداء', 'school-system'); ?></button>
                </form>
            </div>
        <?php else : ?>
            <form method="post" class="p-hero__cta">
                <?php wp_nonce_field('sch_app_pickup', '_sch_nonce'); ?>
                <input type="hidden" name="sch_app_action" value="pickup_call">
                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
                <button type="submit" class="p-hero__btn is-solid p-tap">
                    <?php echo sch_icon('bell', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('أنا عند البوابة — أخرجوا ابني', 'school-system'); ?>
                </button>
            </form>
        <?php endif; ?>

    <?php else : ?>
        <div class="p-hero__top">
            <span class="p-hero__badge"><?php echo esc_html($leave ? __('إجازة معتمدة', 'school-system') : __('في المنزل', 'school-system')); ?></span>
            <span class="p-hero__time"><?php echo esc_html(wp_date('l', current_time('timestamp'))); ?></span>
        </div>
        <h2 class="p-hero__h"><?php echo esc_html(sprintf(/* translators: %s: اسم الطفل */ __('%s في المنزل', 'school-system'), $first)); ?></h2>
        <p class="p-hero__sub"><?php echo esc_html($leave ? __('الباص لا ينتظره اليوم — أُبلغت المدرسة تلقائيًا', 'school-system') : __('خارج عهدة المدرسة الآن', 'school-system')); ?></p>
        <div class="p-hero__cta">
            <a class="p-hero__btn is-solid p-tap" href="<?php echo esc_url(SCH_App::url('leave', $id)); ?>"><?php esc_html_e('طلبات الإجازات', 'school-system'); ?></a>
            <a class="p-hero__btn p-tap" href="<?php echo esc_url(SCH_App::url('schedule', $id)); ?>"><?php esc_html_e('جدول الغد', 'school-system'); ?></a>
        </div>
    <?php endif; ?>
</div>

<!-- ═════ بوابات الوصول السريع ═════ -->
<div class="p-gates">
    <?php foreach ([
        ['schedule', __('الجدول', 'school-system'),       'calendar', 1],
        ['track',    __('أين ابني', 'school-system'),      'bus',      2],
        ['clinic',   __('الصحة', 'school-system'),         'heart',    3],
        ['leave',    __('الإجازات', 'school-system'),      'check',    4],
    ] as [$slug, $label, $icon, $tone]) : ?>
        <a class="p-gate p-gate--<?php echo esc_attr((string) $tone); ?> p-tap" href="<?php echo esc_url(SCH_App::url($slug, $id)); ?>">
            <span class="p-gate__i"><?php echo sch_icon($icon, 20); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b class="p-gate__t"><?php echo esc_html($label); ?></b>
        </a>
    <?php endforeach; ?>
</div>

<!-- ═════ الرقمان: المستحق والحضور ═════ -->
<div class="p-duo">
    <a class="p-num p-num--pay p-tap" href="<?php echo esc_url(SCH_App::url('invoices', $id)); ?>">
        <span class="p-num__k"><?php esc_html_e('المستحق', 'school-system'); ?></span>
        <b class="p-num__v"><span class="p-amt"><span class="p-nm"><?php echo esc_html(number_format((float) $today['balance'], 0)); ?></span><i class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></i></span></b>
    </a>
    <div class="p-num p-num--att">
        <span class="p-num__k"><?php esc_html_e('الحضور', 'school-system'); ?></span>
        <b class="p-num__v"><span class="p-amt"><span class="p-nm"><?php echo esc_html(number_format($rate, 0)); ?></span><i class="p-cur">٪</i></span></b>
        <span class="p-num__s"><?php esc_html_e('آخر ٦٠ يومًا', 'school-system'); ?></span>
    </div>
</div>

<!-- ═════ خيط اليوم — القلب: ما مضى، وأين هو الآن، وما بقي ═════ -->
<?php /* بلا رابط «كل السجل»: تبويب «السجل» في الشريط السفلي يذهب إليه نفسه،
         ومدخلان لمكان واحد في شاشة واحدة حشوٌ يزاحم العنوان. */ ?>
<div class="p-sect"><h2 class="p-sect__h"><?php esc_html_e('يومه', 'school-system'); ?></h2></div>

<ol class="p-thr">
    <?php foreach ($thread as $ev) : ?>
        <li class="p-thr__i is-<?php echo esc_attr($ev['state']); ?>">
            <span class="p-thr__d" aria-hidden="true"></span>
            <span class="p-thr__b">
                <b><?php echo esc_html($ev['title']); ?></b>
                <span>
                    <?php if ($ev['time'] !== '') : ?>
                        <time dir="ltr"><?php echo esc_html($ev['time']); ?></time>
                        <?php if ($ev['detail'] !== '' && $ev['detail'] !== $ev['title']) : ?> · <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($ev['detail'] !== '' && $ev['detail'] !== $ev['title']) : ?>
                        <?php echo esc_html($ev['detail']); ?>
                    <?php endif; ?>
                </span>
            </span>
        </li>
    <?php endforeach; ?>
</ol>

<?php
// بطاقات ثانوية: الشهادات · تقرير الروضة · الكتب المتأخرة.
$sch_certs  = SCH_Certificates::of_student($id);
$late_books = array_values(array_filter(
    SCH_Library::loans_of_student($id),
    static fn (object $l): bool => $l->returned_at === null && $l->due_date < current_time('Y-m-d')
));
?>

<?php if ($sch_certs !== []) : ?>
    <a class="p-linkcard p-tap" href="<?php echo esc_url(SCH_App::url('certificates', $id)); ?>">
        <span class="p-linkcard__i p-i--gold"><?php echo sch_icon('award', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-linkcard__t"><b><?php esc_html_e('الشهادات', 'school-system'); ?></b><span><?php echo esc_html(sprintf(_n('شهادة واحدة', '%s شهادات', count($sch_certs), 'school-system'), number_format_i18n(count($sch_certs)))); ?></span></span>
        <span class="p-linkcard__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </a>
<?php endif; ?>

<?php if ($student->stage === 'kg') : ?>
    <a class="p-linkcard p-tap" href="<?php echo esc_url(SCH_App::url('kg', $id)); ?>">
        <span class="p-linkcard__i p-i--n2"><?php echo sch_icon('sun', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-linkcard__t"><b><?php esc_html_e('التقرير اليومي', 'school-system'); ?></b><span><?php esc_html_e('الوجبة والنوم والنشاط', 'school-system'); ?></span></span>
        <span class="p-linkcard__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </a>
<?php endif; ?>

<?php if ($late_books !== []) : ?>
    <div class="p-note">
        <b><?php echo esc_html(sprintf(_n('كتاب متأخر لم يُرجَع', '%d كتب متأخرة لم تُرجَع', count($late_books), 'school-system'), count($late_books))); ?></b>
        <span><?php echo esc_html(implode(' · ', array_map(static fn (object $l): string => (string) $l->title, array_slice($late_books, 0, 3)))); ?></span>
    </div>
<?php endif; ?>
