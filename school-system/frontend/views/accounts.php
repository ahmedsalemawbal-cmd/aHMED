<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$accounts = SCH_Accounts::all();
$by_type  = [];
foreach ($accounts as $a) {
    $by_type[$a->type][] = $a;
}
?>
<?php
SCH_Modal::head(
    __('دليل الحسابات', 'school-system'),
    '',
    'sch-add-account',
    __('إضافة حساب', 'school-system'),
    'list'
);
?>

<?php foreach (SCH_Accounts::TYPES as $type => $label) : ?>
    <?php if (empty($by_type[$type])) { continue; } ?>
    <div class="sch-card">
        <h2><?php echo esc_html($label); ?></h2>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th style="width:100px"><?php esc_html_e('الرمز', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحساب', 'school-system'); ?></th>
                    <th style="width:160px"><?php esc_html_e('الرصيد', 'school-system'); ?></th>
                    <th style="width:90px"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($by_type[$type] as $a) : ?>
                    <tr>
                        <td dir="ltr" class="sch-mono"><?php echo esc_html($a->code); ?></td>
                        <td class="sch-name">
                            <?php echo esc_html($a->name); ?>
                            <?php if ($a->is_system) : ?>
                                <span class="sch-badge sch-badge--muted"><?php esc_html_e('نظامي', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(sch_money(SCH_Accounts::balance((int) $a->id))); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('acc', (int) $a->id, SCH_Dashboard::url('ledger'))); ?>">
                                <?php esc_html_e('الأستاذ', 'school-system'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php SCH_Modal::open('sch-add-account', __('إضافة حساب', 'school-system'), __('في دليل الحسابات', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إضافة حساب', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('الحسابات النظامية يعتمد عليها الترحيل التلقائي ولا تُحذف.', 'school-system'); ?></p>

    <form method="post" class="sch-toolbar">
        <?php wp_nonce_field('sch_add_account', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_account">
        <input type="text" name="code" placeholder="<?php esc_attr_e('الرمز', 'school-system'); ?>" dir="ltr" required style="max-width:110px">
        <input type="text" name="name" placeholder="<?php esc_attr_e('اسم الحساب', 'school-system'); ?>" required>
        <select name="type" required aria-label="النوع">
            <?php foreach (SCH_Accounts::TYPES as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn"><?php esc_html_e('إضافة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
