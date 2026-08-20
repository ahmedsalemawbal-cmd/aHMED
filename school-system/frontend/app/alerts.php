<?php
/** الإشعارات — أحداث النظام مجموعةً بيومها، كلٌّ بأيقونة نوعه ولونه. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$items = SCH_Comms::inbox(get_current_user_id(), 40);

// الابن المعروض — زرّ الرجوع يعيد إلى شاشته لا إلى أول الأبناء.
$sch_kid = (int) ($sch_data['id'] ?? 0);

/**
 * «تحديد الكل كمقروء» لا يظهر إلا وفي القائمة ما لم يُقرأ.
 * العدد يُحصى من القائمة المحمّلة أصلًا — لا استعلام ثانٍ لسؤالٍ
 * إجابته بين يدينا.
 */
$sch_unread = 0;

foreach ($items as $sch_it) {
    $sch_unread += $sch_it->read_at === null ? 1 : 0;
}

/**
 * السطر السفلي في التصميم يحمل مصدر الحدث ثم وقته.
 *
 * والإشعار لا يحمل معرّف الطالب — يحمل نوعه (`ref_type`) وحده — فالمصدر
 * يُشتقّ من النوع المتاح: «العيادة · ١٢:٠٥». والمفاتيح هي نفسها التي
 * تُرجعها `SCH_App::alert_look()` فلا خريطتان تتباعدان.
 */
$sch_kind_names = [
    'certificate'  => __('شهادة', 'school-system'),
    'attendance'   => __('الحضور', 'school-system'),
    'leave'        => __('إجازة', 'school-system'),
    'med'          => __('دواء', 'school-system'),
    'clinic'       => __('العيادة', 'school-system'),
    'custody'      => __('الحركة', 'school-system'),
    'transport'    => __('النقل', 'school-system'),
    'invoice'      => __('الرسوم', 'school-system'),
    'finance'      => __('المالية', 'school-system'),
    'payment'      => __('دفعة', 'school-system'),
    'reversal'     => __('تسوية', 'school-system'),
    'payroll'      => __('الرواتب', 'school-system'),
    'timetable'    => __('الجدول', 'school-system'),
    'substitution' => __('تبديل حصّة', 'school-system'),
    'note'         => __('ملاحظة', 'school-system'),
    'message'      => __('رسالة', 'school-system'),
    'alert'        => __('تنبيه', 'school-system'),
    'kg'           => __('الروضة', 'school-system'),
];
?>

<div class="p-hd">
    <a class="p-hd__back" href="<?php echo esc_url(SCH_App::url('child', $sch_kid)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php /* الشيفرون في التصميم يشير إلى جهة الرجوع (اليمين في RTL)، و`chev` مرسوم لليسار — فيُقلب في CSS لا برسم ثانٍ */ ?>
        <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>

    <h1 class="p-h1"><?php esc_html_e('الإشعارات', 'school-system'); ?></h1>

    <?php if ($sch_unread > 0) : ?>
        <?php /* الفعل الوحيد في الشاشة — عقد التطبيق `sch_app_action` ونونسه `sch_read_alerts` */ ?>
        <form class="p-hd__f" method="post" action="<?php echo esc_url(SCH_App::url('alerts')); ?>">
            <?php wp_nonce_field('sch_read_alerts', '_sch_nonce'); ?>
            <input type="hidden" name="sch_app_action" value="read_alerts">
            <button class="p-hd__a" type="submit"><?php esc_html_e('تحديد الكل كمقروء', 'school-system'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php if ($items === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('bell', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا إشعارات', 'school-system'); ?></b>
        <p><?php esc_html_e('كل شيء على ما يرام — نصلك حين يستجدّ ما يستحق.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php
    // تجميع بيوم الحدث: رأس صغير لكل يوم، وسطح واحد لصفوفه.
    // القائمة أصلًا مرتّبة تنازليًا، فالتجميع يحفظ ترتيبها بلا فرز ثانٍ.
    $sch_groups = [];

    foreach ($items as $sch_it) {
        $sch_groups[substr((string) $sch_it->created_at, 0, 10)][] = $sch_it;
    }
    ?>

    <div class="p-msgs">
        <?php foreach ($sch_groups as $sch_day => $sch_rows) : ?>
            <section>
                <h2 class="p-day-h"><?php echo esc_html(SCH_App::day_label($sch_day)); ?></h2>

                <div class="p-msgs__g">
                    <?php foreach ($sch_rows as $item) :
                        [$sch_ic, $sch_kind] = SCH_App::alert_look((string) ($item->ref_type ?? ''));
                        $sch_new = $item->read_at === null; ?>
                        <article class="p-msg p-msg--<?php echo esc_attr($sch_kind); ?><?php echo esc_attr($sch_new ? ' is-new' : ''); ?>">
                            <span class="p-msg__i"><?php echo sch_icon($sch_ic, 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <div class="p-msg__b">
                                <div class="p-msg__h">
                                    <span class="p-msg__t"><?php echo esc_html((string) $item->title); ?></span>
                                    <?php if ($sch_new) : ?>
                                        <span class="p-msg__dot" role="img"
                                              aria-label="<?php esc_attr_e('غير مقروء', 'school-system'); ?>"></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($item->body) : ?>
                                    <p class="p-msg__x"><?php echo esc_html((string) $item->body); ?></p>
                                <?php endif; ?>

                                <div class="p-msg__m">
                                    <span><?php echo esc_html($sch_kind_names[$sch_kind] ?? __('إشعار', 'school-system')); ?></span>
                                    <span class="p-msg__sep" aria-hidden="true">·</span>
                                    <time datetime="<?php echo esc_attr((string) $item->created_at); ?>">
                                        <?php echo esc_html(SCH_App::when_label((string) $item->created_at)); ?>
                                    </time>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
