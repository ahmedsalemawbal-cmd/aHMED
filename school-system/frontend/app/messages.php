<?php
/** الرسائل — من الأخصائية أو العيادة أو الإدارة. المعلم لا يراسل ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$threads = SCH_Comms::inbox(get_current_user_id(), 40);
$msgs    = array_values(array_filter(
    $threads,
    static fn (object $m): bool => in_array((string) ($m->ref_type ?? ''), ['message', 'note', 'referral'], true)
));

// المظهر من `SCH_App::alert_look()` نفسها — لئلا يظهر المصدر الواحد
// بأيقونتين مختلفتين في شاشتي الرسائل والإشعارات.

// الابن المعروض — زرّ الرجوع يعيد إلى شاشته لا إلى أول الأبناء.
$sch_kid = (int) ($sch_data['id'] ?? 0);

/**
 * عدّاد «جديدة» في الرأس يُحصى من القائمة المحمّلة أصلًا — لا استعلام
 * ثانٍ لسؤالٍ إجابته بين يدينا.
 */
$sch_new = 0;

foreach ($msgs as $sch_m) {
    $sch_new += $sch_m->read_at === null ? 1 : 0;
}
?>

<div class="p-hd">
    <a class="p-hd__back" href="<?php echo esc_url(SCH_App::url('child', $sch_kid)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php /* الشيفرون في التصميم يشير إلى جهة الرجوع (اليمين في RTL)، و`chev` مرسوم لليسار — فيُقلب في CSS لا برسم ثانٍ */ ?>
        <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>

    <h1 class="p-h1 p-hd__t"><?php esc_html_e('الرسائل', 'school-system'); ?></h1>

    <?php if ($sch_new > 0) : ?>
        <?php /* الرقم في عنصره (`p-nm`) لا داخل الجملة — العربية تقلب اتجاهه إن اختلط بها */ ?>
        <span class="p-hd__n">
            <span class="p-nm"><?php echo esc_html(number_format_i18n($sch_new)); ?></span>
            <?php esc_html_e('جديدة', 'school-system'); ?>
        </span>
    <?php endif; ?>
</div>

<?php if ($msgs === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('mail', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا رسائل', 'school-system'); ?></b>
        <p><?php esc_html_e('تصلك هنا رسائل الأخصائية والصحة المدرسية والإدارة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-inb">
        <?php foreach ($msgs as $m) :
            [$sch_ic, $sch_kind] = SCH_App::alert_look((string) ($m->ref_type ?? 'message')); ?>
            <article class="p-inb__r p-inb__r--<?php echo esc_attr($sch_kind); ?><?php echo esc_attr($m->read_at === null ? ' is-new' : ''); ?>">
                <span class="p-inb__i"><?php echo sch_icon($sch_ic, 20); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>

                <div class="p-inb__b">
                    <div class="p-inb__h">
                        <b class="p-inb__t"><?php echo esc_html((string) $m->title); ?></b>
                        <time class="p-inb__w" datetime="<?php echo esc_attr((string) $m->created_at); ?>">
                            <?php echo esc_html(SCH_App::when_label((string) $m->created_at)); ?>
                        </time>
                    </div>

                    <?php if ($m->body) : ?>
                        <p class="p-inb__x"><?php echo esc_html((string) $m->body); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
