<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$year  = SCH_Years::current();
$from  = isset($_GET['from']) ? (sch_sanitize_date(wp_unslash((string) $_GET['from'])) ?? '') : ($year->start_date ?? '');
$to    = isset($_GET['to']) ? (sch_sanitize_date(wp_unslash((string) $_GET['to'])) ?? '') : current_time('Y-m-d');
$from  = $from ?: gmdate('Y-01-01');

$trial  = SCH_Accounts::trial_balance($to);
$income = SCH_Journal::income_statement($from, $to);

$td = array_sum(array_map(static fn ($r): float => (float) $r->total_debit, $trial));
$tc = array_sum(array_map(static fn ($r): float => (float) $r->total_credit, $trial));
?>
<h1 class="sch-title"><?php esc_html_e('التقارير المالية', 'school-system'); ?></h1>

<div class="sch-card">
    <form method="get" class="sch-toolbar">
        <label class="sch-sub"><?php esc_html_e('من', 'school-system'); ?></label>
        <input type="date" name="from" value="<?php echo esc_attr($from); ?>" aria-label="من تاريخ">
        <label class="sch-sub"><?php esc_html_e('إلى', 'school-system'); ?></label>
        <input type="date" name="to" value="<?php echo esc_attr($to); ?>" aria-label="إلى تاريخ">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تحديث', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('قائمة الدخل', 'school-system'); ?></h2>

    <?php if ($income['revenue'] === [] && $income['expense'] === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد حركة في الفترة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <tbody>
                <tr><td colspan="2" class="sch-name"><?php esc_html_e('الإيرادات', 'school-system'); ?></td></tr>
                <?php foreach ($income['revenue'] as $r) : ?>
                    <tr><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html(sch_money($r->amount)); ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="sch-name"><?php esc_html_e('إجمالي الإيرادات', 'school-system'); ?></td><td class="sch-name"><?php echo esc_html(number_format($income['rev_sum'], 2)); ?></td></tr>

                <tr><td colspan="2" class="sch-name sch-mt"><?php esc_html_e('المصروفات', 'school-system'); ?></td></tr>
                <?php foreach ($income['expense'] as $r) : ?>
                    <tr><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html(sch_money($r->amount)); ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="sch-name"><?php esc_html_e('إجمالي المصروفات', 'school-system'); ?></td><td class="sch-name"><?php echo esc_html(number_format($income['exp_sum'], 2)); ?></td></tr>
                </tbody>
            </table>
        </div>

        <p class="sch-net">
            <?php echo esc_html($income['net'] >= 0 ? __('صافي الربح', 'school-system') : __('صافي الخسارة', 'school-system')); ?>:
            <strong><?php echo esc_html(sch_money(abs($income['net']))); ?></strong>
        </p>
    <?php endif; ?>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('ميزان المراجعة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('حتى تاريخ', 'school-system'); ?> <span dir="ltr"><?php echo esc_html($to); ?></span></p>

    <?php if ($trial === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد قيود مرحّلة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الرمز', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحساب', 'school-system'); ?></th>
                    <th><?php esc_html_e('مدين', 'school-system'); ?></th>
                    <th><?php esc_html_e('دائن', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($trial as $r) : ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($r->code); ?></td>
                        <td class="sch-name"><?php echo esc_html($r->name); ?></td>
                        <td><?php echo esc_html(sch_money($r->total_debit)); ?></td>
                        <td><?php echo esc_html(sch_money($r->total_credit)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td></td>
                    <td class="sch-name"><?php esc_html_e('الإجمالي', 'school-system'); ?></td>
                    <td class="sch-name"><?php echo esc_html(number_format($td, 2)); ?></td>
                    <td class="sch-name"><?php echo esc_html(number_format($tc, 2)); ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <?php if (round($td, 2) !== round($tc, 2)) : ?>
            <div class="sch-notice sch-notice--error sch-mt">
                <?php esc_html_e('الميزان غير متوازن — راجع القيود المرحّلة.', 'school-system'); ?>
            </div>
        <?php else : ?>
            <p class="sch-sub sch-mt"><?php esc_html_e('الميزان متوازن.', 'school-system'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
