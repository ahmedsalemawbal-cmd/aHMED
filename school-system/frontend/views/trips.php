<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$trips    = SCH_Trips::today();
$open_id  = isset($_GET['trip']) ? absint($_GET['trip']) : (int) ($trips[0]->id ?? 0);
$manifest = $open_id > 0 ? SCH_Trips::manifest($open_id) : [];
$position = $open_id > 0 ? SCH_Trips::last_position($open_id) : null;
?>

<div class="sch-card">
    <?php if ($trips === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد رحلات اليوم', 'school-system'); ?></strong>
            <?php esc_html_e('افتح رحلة من صفحة المسار.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المسار', 'school-system'); ?></th>
                    <th><?php esc_html_e('الاتجاه', 'school-system'); ?></th>
                    <th><?php esc_html_e('الباص', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($trips as $t) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(add_query_arg(['t' => 'trips', 'trip' => (int) $t->id], SCH_Dashboard::url('transport'))); ?>"><?php echo esc_html($t->route_name); ?></a>
                        </td>
                        <td><?php echo esc_html($t->direction === 'morning' ? __('ذهاب', 'school-system') : __('عودة', 'school-system')); ?></td>
                        <td dir="ltr"><?php echo esc_html($t->plate_no ?: '—'); ?></td>
                        <td>
                            <?php if ($t->status === 'running') : ?>
                                <span class="sch-badge"><?php esc_html_e('جارية', 'school-system'); ?></span>
                            <?php else : ?>
                                <span class="sch-badge sch-badge--muted"><?php esc_html_e('منتهية', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t->status === 'running') : ?>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e('ستُغلق الرحلة. تأكد أن كل الطلاب نزلوا. متابعة؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_close_trip', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="close_trip">
                                    <input type="hidden" name="trip_id" value="<?php echo esc_attr((string) $t->id); ?>">
                                    <button class="sch-link-danger"><?php esc_html_e('إنهاء', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($manifest !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('كشف الرحلة', 'school-system'); ?></h2>
    <?php if ($position) : ?>
        <p class="sch-sub" dir="ltr"><?php echo esc_html(__('آخر موقع:', 'school-system') . ' ' . $position->lat . ', ' . $position->lng . ' — ' . $position->recorded_at); ?></p>
    <?php endif; ?>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                <th><?php esc_html_e('النقطة', 'school-system'); ?></th>
                <th><?php esc_html_e('الصعود', 'school-system'); ?></th>
                <th><?php esc_html_e('النزول', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($manifest as $m) : ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html($m->full_name); ?></td>
                    <td><?php echo esc_html($m->stop_name ?: '—'); ?></td>
                    <td dir="ltr"><?php echo $m->boarded_at ? esc_html($m->boarded_at) : '<span class="sch-badge sch-badge--muted">' . esc_html__('لم يصعد', 'school-system') . '</span>'; ?></td>
                    <td dir="ltr"><?php echo $m->alighted_at ? esc_html($m->alighted_at) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
