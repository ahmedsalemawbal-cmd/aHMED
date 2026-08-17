<?php
/** الإشعارات — أحداث النظام مجموعةً بيومها، كلٌّ بأيقونة نوعه ولونه. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$items = SCH_Comms::inbox(get_current_user_id(), 40);
?>

<h1 class="p-h1"><?php esc_html_e('الإشعارات', 'school-system'); ?></h1>
<p class="p-sub"><?php esc_html_e('نُشعرك عند الاستثناء لا عند كل حدث.', 'school-system'); ?></p>

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
                        [$sch_ic, $sch_kind] = SCH_App::alert_look((string) ($item->ref_type ?? '')); ?>
                        <article class="p-msg p-msg--<?php echo esc_attr($sch_kind); ?><?php echo esc_attr($item->read_at === null ? ' is-new' : ''); ?>">
                            <span class="p-msg__i"><?php echo sch_icon($sch_ic, 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <div class="p-msg__b">
                                <div class="p-msg__h">
                                    <b><?php echo esc_html((string) $item->title); ?></b>
                                    <time datetime="<?php echo esc_attr((string) $item->created_at); ?>">
                                        <?php echo esc_html(SCH_App::when_label((string) $item->created_at)); ?>
                                    </time>
                                </div>
                                <?php if ($item->body) : ?>
                                    <p class="p-msg__x"><?php echo esc_html((string) $item->body); ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
