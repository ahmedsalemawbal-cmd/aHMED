<?php
/** الإشعارات — أحداث النظام. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$items = SCH_Comms::inbox(get_current_user_id(), 40);
?>

<h1 class="p-h1"><?php esc_html_e('الإشعارات', 'school-system'); ?></h1>

<?php if ($items === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('clock', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا إشعارات', 'school-system'); ?></b>
        <p><?php esc_html_e('نُشعرك عند الاستثناء فقط — لا عند كل حدث.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-list">
        <?php foreach ($items as $item) : ?>
            <div class="p-row<?php echo esc_attr($item->read_at === null ? ' is-new' : ''); ?>">
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $item->title); ?></b>
                    <?php if ($item->body) : ?>
                        <span><?php echo esc_html((string) $item->body); ?></span>
                    <?php endif; ?>
                </span>
                <span class="p-row__e"><?php echo esc_html(wp_date('j M', strtotime((string) $item->created_at))); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
