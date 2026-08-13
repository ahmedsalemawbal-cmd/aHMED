<?php
/** الرسائل — من الأخصائية أو العيادة أو الإدارة. المعلم لا يراسل ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$threads = SCH_Comms::inbox(get_current_user_id(), 40);
$msgs    = array_values(array_filter(
    $threads,
    static fn (object $m): bool => in_array((string) ($m->ref_type ?? ''), ['message', 'note', 'referral'], true)
));

// لكل مصدر أيقونته ولونه — يُعرَف قبل قراءة العنوان.
$sch_msg_icon = ['message' => 'mail', 'note' => 'book', 'referral' => 'heart'];
?>

<h1 class="p-h1"><?php esc_html_e('الرسائل', 'school-system'); ?></h1>

<?php if ($msgs === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('mail', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا رسائل', 'school-system'); ?></b>
        <p><?php esc_html_e('تصلك هنا رسائل الأخصائية والصحة المدرسية والإدارة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-msgs">
        <?php foreach ($msgs as $m) :
            $kind = (string) ($m->ref_type ?? 'message'); ?>
            <article class="p-msg p-msg--<?php echo esc_attr($kind); ?><?php echo esc_attr($m->read_at === null ? ' is-new' : ''); ?>">
                <span class="p-msg__i"><?php echo sch_icon($sch_msg_icon[$kind] ?? 'mail', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div class="p-msg__b">
                    <div class="p-msg__h">
                        <b><?php echo esc_html((string) $m->title); ?></b>
                        <time datetime="<?php echo esc_attr((string) $m->created_at); ?>"><?php echo esc_html(wp_date('j M', strtotime((string) $m->created_at))); ?></time>
                    </div>
                    <?php if ($m->body) : ?>
                        <p class="p-msg__x"><?php echo esc_html((string) $m->body); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
