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
?>

<h1 class="p-h1"><?php esc_html_e('الرسائل', 'school-system'); ?></h1>

<?php if ($msgs === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('mail', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا رسائل', 'school-system'); ?></b>
        <p><?php esc_html_e('تصلك هنا رسائل الأخصائية والصحة المدرسية والإدارة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php
    $sch_groups = [];

    foreach ($msgs as $sch_m) {
        $sch_groups[substr((string) $sch_m->created_at, 0, 10)][] = $sch_m;
    }
    ?>

    <div class="p-msgs">
        <?php foreach ($sch_groups as $sch_day => $sch_rows) : ?>
            <section>
                <h2 class="p-day-h"><?php echo esc_html(SCH_App::day_label($sch_day)); ?></h2>

                <div class="p-msgs__g">
                    <?php foreach ($sch_rows as $m) :
                        [$sch_ic, $sch_kind] = SCH_App::alert_look((string) ($m->ref_type ?? 'message')); ?>
                        <article class="p-msg p-msg--<?php echo esc_attr($sch_kind); ?><?php echo esc_attr($m->read_at === null ? ' is-new' : ''); ?>">
                            <span class="p-msg__i"><?php echo sch_icon($sch_ic, 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <div class="p-msg__b">
                                <div class="p-msg__h">
                                    <b><?php echo esc_html((string) $m->title); ?></b>
                                    <time datetime="<?php echo esc_attr((string) $m->created_at); ?>">
                                        <?php echo esc_html(SCH_App::when_label((string) $m->created_at)); ?>
                                    </time>
                                </div>
                                <?php if ($m->body) : ?>
                                    <p class="p-msg__x"><?php echo esc_html((string) $m->body); ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
