<?php
/**
 * النقل — المسار والباص والسائق ونقطة الالتقاء.
 *
 * ثلاث بطاقات بترتيب سؤال وليّ الأمر: **أين ابني الآن** (شريط الراكب
 * بحالته)، ثم **مع من** (السائق ورقمه وزرّ اتصال بضغطة)، ثم **من أين
 * ينطلق ويعود** (نقطة الالتقاء على مخطّط، واسمها مكتوب تحته).
 *
 * ورقم السائق زرًّا لا نصًّا: الأب الذي تأخّر باص ابنه يريد الاتصال لا
 * نسخ الرقم — واللمسة الواحدة هي كل الفرق في تلك اللحظة.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id  = (int) ($sch_data['id'] ?? 0);
$sub = SCH_Routes::subscription_of($id);
?>

<header class="p-sub__h">
    <a class="p-chip" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php echo sch_icon('chev', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
    <h1 class="p-h1"><?php esc_html_e('النقل', 'school-system'); ?></h1>
</header>

<?php if (!$sub) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('bus', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('غير مشترك في النقل', 'school-system'); ?></b>
        <p><?php esc_html_e('تواصل مع الإدارة إن رغبت في الاشتراك.', 'school-system'); ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php
$sch_student = SCH_Students::get($id);
$sch_class   = SCH_Students::current_class($id);
$sch_photo   = ($sch_student && $sch_student->photo_file) ? SCH_App::photo_url($id) : '';
$sch_state   = (string) ($sch_student->custody_state ?? 'home');

// النغمة تتبع العهدة — والمعاني نفسها في كل شاشة في النظام.
$sch_tone = match ($sch_state) {
    'on_bus'    => 'bus',
    'at_school' => 'school',
    default     => 'home',
};

/* الباص والسائق يُقرآن من المسار لا من الرحلة: الرحلة تُفتح صباحًا وتُغلق
   بعد ساعة، والشاشة تُفتح في أي وقت — فمن يقرأ سائقه من رحلة جارية يجد
   البطاقة فارغة أكثر اليوم. */
$sch_route  = SCH_Routes::get((int) $sub->route_id);
$sch_bus    = ($sch_route && $sch_route->bus_id) ? SCH_Buses::get((int) $sch_route->bus_id) : null;
$sch_driver = ($sch_bus && $sch_bus->driver_user_id) ? get_user_by('id', (int) $sch_bus->driver_user_id) : null;
$sch_emp    = $sch_driver ? SCH_Staff::get_by_user((int) $sch_driver->ID) : null;
$sch_phone  = trim((string) ($sch_emp->phone ?? ''));

// وقت الالتقاء صباحًا مخزَّن على نقطة التوقف نفسها لا على الاشتراك.
$sch_pick = '';
foreach (SCH_Routes::stops((int) $sub->route_id) as $sch_stop) {
    if ((int) $sch_stop->id === (int) ($sub->stop_id ?? 0)) {
        $sch_pick = substr((string) ($sch_stop->pickup_time ?? ''), 0, 5);
        break;
    }
}

/**
 * توصيل المساء غير مخزَّن لكل نقطة — يُشتقّ ولا يُخترع.
 *
 * رحلة العودة تنطلق بانتهاء الدوام (`attendance_day_end`، وهو المصدر
 * نفسه الذي تبني عليه محطّة «الانصراف» في شاشة اليوم)، ويُضاف إليها زمن
 * المسار المسجَّل. وبلا زمن مسجَّل يبقى وقت الانصراف — تقديرٌ أقرب من فراغ.
 */
$sch_end  = substr((string) sch_settings('attendance_day_end', '13:00'), 0, 5);
$sch_drop = '';

if ($sch_end !== '') {
    $sch_mins = ((int) substr($sch_end, 0, 2)) * 60
        + ((int) substr($sch_end, 3, 2))
        + (int) ($sch_route->duration_min ?? 0);
    $sch_drop = sprintf('%02d:%02d', intdiv($sch_mins, 60) % 24, $sch_mins % 60);
}
?>

<article class="p-rider p-rider--<?php echo esc_attr($sch_tone); ?>">
    <span class="p-rider__pic">
        <?php if ($sch_photo) : ?>
            <img src="<?php echo esc_url($sch_photo); ?>" alt="" width="50" height="50" loading="lazy">
        <?php else : ?>
            <?php echo sch_avatar_svg(mb_substr((string) ($sch_student->full_name ?? ''), 0, 1), 50); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <?php endif; ?>
    </span>

    <span class="p-rider__t">
        <b><?php echo esc_html($sch_student ? SCH_Enrollment::full_name($sch_student) : ''); ?></b>
        <em>
            <?php echo esc_html(sprintf(
                /* translators: 1: الشعبة، 2: صفة الاشتراك */
                __('%1$s · %2$s في النقل المدرسي', 'school-system'),
                $sch_class ? SCH_Classes::label($sch_class) : __('بلا شعبة', 'school-system'),
                ($sch_student->gender ?? '') === 'female'
                    ? __('مشتركة', 'school-system')
                    : __('مشترك', 'school-system')
            )); ?>
        </em>
    </span>

    <span class="p-rider__s"><?php echo esc_html(SCH_Custody::state_label($sch_state)); ?></span>
</article>

<section class="p-card p-drv">
    <div class="p-drv__row">
        <span class="p-drv__pic">
            <?php /* لا صور للسائقين في النظام — والحرف الأول أدلّ من أيقونة شخص عامة */ ?>
            <?php echo sch_avatar_svg(mb_substr((string) ($sch_driver->display_name ?? ''), 0, 1), 54); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </span>

        <span class="p-drv__t">
            <span class="p-drv__k"><?php esc_html_e('السائق', 'school-system'); ?></span>
            <b class="p-drv__n">
                <?php echo esc_html($sch_driver
                    ? $sch_driver->display_name
                    : __('لم يُسنَد سائق بعد', 'school-system')); ?>
            </b>
            <?php if ($sch_phone !== '') : ?>
                <span class="p-drv__tel p-nm"><?php echo esc_html($sch_phone); ?></span>
            <?php endif; ?>
        </span>

        <?php if ($sch_phone !== '') : ?>
            <a class="p-drv__call p-tap" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $sch_phone)); ?>"
               aria-label="<?php esc_attr_e('اتصال بالسائق', 'school-system'); ?>">
                <?php echo sch_icon('phone', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="p-facts">
        <span class="p-facts__i">
            <span class="p-facts__k"><?php esc_html_e('المسار', 'school-system'); ?></span>
            <b class="p-facts__v"><?php echo esc_html((string) $sub->route_name); ?></b>
        </span>

        <span class="p-facts__i">
            <span class="p-facts__k"><?php esc_html_e('لوحة الباص', 'school-system'); ?></span>
            <b class="p-facts__v p-nm"><?php echo esc_html((string) ($sch_bus->plate_no ?? '—')); ?></b>
        </span>

        <span class="p-facts__i">
            <span class="p-facts__k"><?php esc_html_e('التقاط الصباح', 'school-system'); ?></span>
            <b class="p-facts__v p-nm"><?php echo esc_html($sch_pick !== '' ? sch_clock($sch_pick) : '—'); ?></b>
        </span>

        <span class="p-facts__i">
            <span class="p-facts__k"><?php esc_html_e('التوصيل المساء', 'school-system'); ?></span>
            <b class="p-facts__v p-nm"><?php echo esc_html($sch_drop !== '' ? sch_clock($sch_drop) : '—'); ?></b>
        </span>
    </div>
</section>

<section class="p-card p-mp">
    <div class="p-mp__h">
        <b><?php esc_html_e('نقطة الالتقاء', 'school-system'); ?></b>

        <?php /* لا يعدّلها وليّ الأمر بنفسه — نقطة التوقف تخصّ ثلاثين طفلًا
                 وترتيبها يحكم الكشف كلّه. فالتعديل طلبٌ يصل الإدارة. */ ?>
        <a class="p-mp__a" href="<?php echo esc_url(SCH_App::url('messages')); ?>">
            <?php esc_html_e('تعديل', 'school-system'); ?>
        </a>
    </div>

    <?php /* المخطّط ساكن — والخريطة الحيّة خلفه: الضغط عليه يفتح «أين ابني الآن» */ ?>
    <a class="p-mp__map" href="<?php echo esc_url(SCH_App::url('track', $id)); ?>"
       aria-label="<?php esc_attr_e('أين ابني الآن', 'school-system'); ?>">
        <span class="p-mp__blk p-mp__blk--a" aria-hidden="true"></span>
        <span class="p-mp__blk p-mp__blk--b" aria-hidden="true"></span>
        <span class="p-mp__road p-mp__road--v" aria-hidden="true"></span>
        <span class="p-mp__road p-mp__road--h" aria-hidden="true"></span>

        <span class="p-mp__pin">
            <?php echo sch_icon('pin', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </span>

        <span class="p-mp__cap">
            <?php echo esc_html((string) ($sub->stop_name ?: __('غير محددة', 'school-system'))); ?>
            <?php echo sch_icon('chev', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </span>
    </a>
</section>
