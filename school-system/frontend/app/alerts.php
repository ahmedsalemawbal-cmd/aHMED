<?php
/** الإشعارات — أحداث النظام، كلٌّ بأيقونة مصدره ولونه. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$items = SCH_Comms::inbox(get_current_user_id(), 40);

// لكل نوع حدث أيقونته ولونه — يُقرأ المصدر بالعين قبل النص.
$sch_alert_icon = [
    'alert'    => 'clock',
    'custody'  => 'route',
    'invoice'  => 'invoice',
    'payment'  => 'wallet',
    'reversal' => 'wallet',
    'message'  => 'mail',
    'payroll'  => 'wallet',
];
$sch_alert_known = ['alert', 'custody', 'invoice', 'payment', 'reversal', 'message'];
?>

<h1 class="p-h1"><?php esc_html_e('الإشعارات', 'school-system'); ?></h1>

<?php if ($items === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('clock', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا إشعارات', 'school-system'); ?></b>
        <p><?php esc_html_e('نُشعرك عند الاستثناء فقط — لا عند كل حدث.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-msgs">
        <?php foreach ($items as $item) :
            $type = (string) ($item->ref_type ?? '');
            $mod  = in_array($type, $sch_alert_known, true) ? $type : 'message'; ?>
            <article class="p-msg p-msg--<?php echo esc_attr($mod); ?><?php echo esc_attr($item->read_at === null ? ' is-new' : ''); ?>">
                <span class="p-msg__i"><?php echo sch_icon($sch_alert_icon[$type] ?? 'clock', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div class="p-msg__b">
                    <div class="p-msg__h">
                        <b><?php echo esc_html((string) $item->title); ?></b>
                        <time datetime="<?php echo esc_attr((string) $item->created_at); ?>"><?php echo esc_html(wp_date('j M', strtotime((string) $item->created_at))); ?></time>
                    </div>
                    <?php if ($item->body) : ?>
                        <p class="p-msg__x"><?php echo esc_html((string) $item->body); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
