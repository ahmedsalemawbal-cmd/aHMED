<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$items = SCH_Assets::items();
$low   = SCH_Assets::low_stock();
?>
<?php
SCH_Modal::head(
    __('المستودع', 'school-system'),
    '',
    'sch-add-item',
    __('إضافة صنف', 'school-system'),
    'grid'
);
?>

<?php if ($low !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php esc_html_e('أصناف تحت الحد الأدنى', 'school-system'); ?></strong>
        <?php foreach ($low as $i) : ?>
            <div class="sch-sub"><?php echo esc_html($i->name . ' — ' . number_format((float) $i->qty, 2) . ' ' . ($i->unit ?: '')); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('الأصناف', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('الرصيد يُحسب من الحركات ولا يُدخل يدويًا.', 'school-system'); ?></p>

    <?php if ($items === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('المستودع فارغ', 'school-system'); ?></strong><?php esc_html_e('أضف صنفًا ثم سجّل حركة إدخال.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الصنف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الوحدة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الرصيد', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحد الأدنى', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $i) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($i->name); ?></td>
                        <td><?php echo esc_html($i->unit ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format((float) $i->qty, 2)); ?></td>
                        <td class="sch-sub"><?php echo esc_html(number_format((float) $i->min_qty, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar sch-mt">
            <?php wp_nonce_field('sch_move_stock', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="move_stock">
            <select name="item_id" required aria-label="الصنف">
                <option value=""><?php esc_html_e('الصنف…', 'school-system'); ?></option>
                <?php foreach ($items as $i) : ?>
                    <option value="<?php echo esc_attr((string) $i->id); ?>"><?php echo esc_html($i->name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="move_type" required aria-label="نوع الحركة">
                <option value="in"><?php esc_html_e('إدخال', 'school-system'); ?></option>
                <option value="out"><?php esc_html_e('صرف', 'school-system'); ?></option>
                <option value="adjust"><?php esc_html_e('جرد (ضبط الرصيد)', 'school-system'); ?></option>
            </select>
            <input type="number" name="qty" step="0.01" min="0.01" placeholder="<?php esc_attr_e('الكمية', 'school-system'); ?>" required>
            <input type="text" name="note" placeholder="<?php esc_attr_e('ملاحظة', 'school-system'); ?>">
            <button class="sch-btn"><?php esc_html_e('تسجيل الحركة', 'school-system'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php SCH_Modal::open('sch-add-item', __('إضافة صنف', 'school-system'), __('يدخل المستودع بكميته الابتدائية', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إضافة صنف', 'school-system'); ?></h2>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
        <?php wp_nonce_field('sch_add_item', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_item">
        <input type="text" name="sku" placeholder="<?php esc_attr_e('الرمز', 'school-system'); ?>" dir="ltr" style="max-width:120px">
        <input type="text" name="name" placeholder="<?php esc_attr_e('اسم الصنف', 'school-system'); ?>" required>
        <input type="text" name="unit" placeholder="<?php esc_attr_e('الوحدة', 'school-system'); ?>" style="max-width:110px">
        <input type="number" name="min_qty" step="0.01" min="0" placeholder="<?php esc_attr_e('الحد الأدنى', 'school-system'); ?>">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إضافة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
