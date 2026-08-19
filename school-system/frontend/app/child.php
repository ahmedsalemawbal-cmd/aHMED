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
$class    = SCH_Students::current_class($id);

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

<?php
/* ═════ البطل: أين ابني الآن ═════
 *
 * بنية واحدة لكل الحالات، ونغمتها وحدها تتبدّل. ثلاث بنى مختلفة —
 * واحدة لكل حالة — كانت تعني ثلاثة تخطيطات تُصان معًا، فيتباعد
 * ارتفاع البطاقة بين حالة وأخرى فتقفز الشاشة تحتها كلّما تبدّلت الحالة.
 *
 * والنغمة تُشتقّ من العهدة لا من مصادفة: `at_school` أخضر، والباص
 * كهرمانيّ، والمنزل رماديّ — وهي المعاني نفسها في كل شاشة في النظام. */

$sch_tone = ['school' => 'ok', 'bus' => 'warn', 'home' => 'slate'][$hero] ?? 'slate';
$sch_call = SCH_Pickup::open_for($id);

$sch_state = match ($hero) {
    'bus'    => __('في الباص', 'school-system'),
    'school' => __('في المدرسة', 'school-system'),
    default  => $leave ? __('في إجازة معتمدة', 'school-system') : __('في المنزل', 'school-system'),
};

// السطر الأسفل: أدقّ ما نعرفه عن هذه اللحظة
$sch_detail = match ($hero) {
    'bus'    => SCH_App::trip_state_label($trip['state']) ?: __('الرحلة جارية الآن', 'school-system'),
    'school' => $today['attendance'] ? __('سُجّل دخوله وحضوره مؤكّد', 'school-system') : __('سُجّل دخوله من البوابة', 'school-system'),
    default  => $leave ? __('الباص لا ينتظره اليوم — أُبلغت المدرسة', 'school-system') : __('خارج عهدة المدرسة الآن', 'school-system'),
};
?>
<div class="p-hero p-hero--<?php echo esc_attr($hero); ?>">
    <div class="p-hero__who">
        <span class="p-hero__pic">
            <?php if ($student->photo_file) : ?>
                <img src="<?php echo esc_url(SCH_App::photo_url($id)); ?>" alt="" width="52" height="52" loading="lazy">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $first, 0, 1), 52); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
        </span>
        <span class="p-hero__id">
            <b><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></b>
            <em><?php echo esc_html(trim(($class ? SCH_Classes::label($class) : __('بلا شعبة', 'school-system')) . ' · ' . sch_settings('school_name', get_bloginfo('name')))); ?></em>
        </span>
    </div>

    <div class="p-hero__state">
        <span class="p-hero__dot" aria-hidden="true"></span>
        <b class="p-hero__h"><?php echo esc_html($sch_state); ?></b>

        <?php if ($hero === 'bus') : ?>
            <a class="p-hero__go p-tap" href="<?php echo esc_url(SCH_App::url('track', $id)); ?>">
                <?php echo sch_icon('pin', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php esc_html_e('تتبّع الباص', 'school-system'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="p-hero__foot">
        <span><?php echo esc_html($sch_detail); ?></span>
        <em><?php echo esc_html(sprintf(/* translators: %s: وقت */ __('آخر تحديث %s', 'school-system'), wp_date('H:i', strtotime((string) ($student->custody_at ?: sch_now()))))); ?></em>
    </div>
</div>

<?php
/* نداء الانصراف — الفعل الوحيد الذي يعني شيئًا وابنك داخل المدرسة.
   «طلب إجازة» هنا لا معنى له: هو حاضر بالفعل. */
?>
<?php if ($sch_call) : ?>
    <div class="p-hero__call is-<?php echo esc_attr($sch_call->status); ?>">
        <span class="p-hero__call-i" aria-hidden="true"></span>
        <div class="p-hero__call-b">
            <b><?php echo esc_html($sch_call->status === 'onway'
                ? __('المشرفة في الطريق', 'school-system')
                : __('وصل نداؤك — بانتظار المشرفة', 'school-system')); ?></b>
            <span><?php echo esc_html($sch_call->status === 'onway'
                ? __('خرجت من الإدارة · قبل لحظات', 'school-system')
                : sprintf(/* translators: %s: مدّة */ __('%s · سيصلك إشعار عند خروجه', 'school-system'), SCH_App::when_label((string) $sch_call->created_at))); ?></span>
        </div>
        <form method="post">
            <?php wp_nonce_field('sch_app_pickup', '_sch_nonce'); ?>
            <input type="hidden" name="sch_app_action" value="pickup_cancel">
            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
            <button type="submit" class="p-hero__x p-tap"><?php esc_html_e('إلغاء', 'school-system'); ?></button>
        </form>
    </div>
<?php elseif ($hero === 'school') : ?>
    <form method="post" class="p-hero__cta">
        <?php wp_nonce_field('sch_app_pickup', '_sch_nonce'); ?>
        <input type="hidden" name="sch_app_action" value="pickup_call">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
        <button type="submit" class="p-btn p-tap">
            <?php echo sch_icon('logout', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php esc_html_e('استدعِ ابني للانصراف', 'school-system'); ?>
        </button>
    </form>
<?php endif; ?>

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
        <b class="p-num__v"><span class="p-amt"><span class="p-nm"><?php echo esc_html(number_format_i18n((float) $today['balance'], 0)); ?></span><i class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></i></span></b>
    </a>
    <div class="p-num p-num--att">
        <span class="p-num__k"><?php esc_html_e('الحضور', 'school-system'); ?></span>
        <b class="p-num__v"><span class="p-amt"><span class="p-nm"><?php echo esc_html(number_format_i18n($rate, 0)); ?></span><i class="p-cur">٪</i></span></b>
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
