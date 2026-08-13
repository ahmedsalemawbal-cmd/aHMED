<?php
/** الرسائل — من الأخصائية أو العيادة أو الإدارة. المعلم لا يراسل ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$threads = SCH_Comms::inbox(get_current_user_id(), 40);
$msgs    = array_values(array_filter(
    $threads,
    static fn (object $m): bool => in_array((string) ($m->ref_type ?? ''), ['message', 'note', 'referral'], true)
));
?>

<h1 class="p-h1"><?php esc_html_e('الرسائل', 'school-system'); ?></h1>

<?php if ($msgs === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('mail', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا رسائل', 'school-system'); ?></b>
        <p><?php esc_html_e('تصلك هنا رسائل الأخصائية والصحة المدرسية والإدارة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-list">
        <?php foreach ($msgs as $m) : ?>
            <div class="p-row<?php echo esc_attr($m->read_at === null ? ' is-new' : ''); ?>">
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $m->title); ?></b>
                    <?php if ($m->body) : ?>
                        <span><?php echo esc_html((string) $m->body); ?></span>
                    <?php endif; ?>
                </span>
                <span class="p-row__e"><?php echo esc_html(wp_date('j M', strtotime((string) $m->created_at))); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
