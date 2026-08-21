<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$runs   = SCH_Payroll::runs();
$run_id = isset($_GET['run']) ? absint($_GET['run']) : 0;
$run    = $run_id > 0 ? SCH_Payroll::get_run($run_id) : null;
$slips  = $run ? SCH_Payroll::payslips($run_id) : [];
?>
<h1 class="sch-title"><?php echo sch_icon('wallet', 22); ?><?php esc_html_e('الرواتب', 'school-system'); ?></h1>

<?php if ($run) : ?>
    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('payroll')); ?>">&larr; <?php esc_html_e('كل المسيّرات', 'school-system'); ?></a>

    <div class="sch-card">
        <h2><?php echo esc_html((SCH_Payroll::MONTHS[(int) $run->period_month] ?? '') . ' ' . $run->period_year); ?></h2>
        <dl class="sch-dl">
            <div><dt><?php esc_html_e('عدد الموظفين', 'school-system'); ?></dt><dd><?php echo esc_html(number_format_i18n((int) $run->headcount)); ?></dd></div>
            <div><dt><?php esc_html_e('الإجمالي', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($run->total)); ?></dd></div>
            <div><dt><?php esc_html_e('الحالة', 'school-system'); ?></dt><dd><?php echo esc_html($run->status === 'posted' ? __('مرحّل', 'school-system') : __('مسودة', 'school-system')); ?></dd></div>
        </dl>

        <div class="sch-table-wrap sch-mt">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأساسي', 'school-system'); ?></th>
                    <th><?php esc_html_e('البدلات', 'school-system'); ?></th>
                    <th><?php esc_html_e('الخصومات', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصافي', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($slips as $s) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($s->display_name); ?></td>
                        <td><?php echo esc_html(sch_money($s->basic)); ?></td>
                        <td><?php echo esc_html(sch_money($s->allowances)); ?></td>
                        <td><?php echo esc_html(sch_money($s->deductions)); ?></td>
                        <td class="sch-name"><?php echo esc_html(sch_money($s->net)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($run->status === 'draft') : ?>
            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt" onsubmit="return confirm('<?php esc_attr_e('الترحيل ينشئ قيدًا محاسبيًا ولا يمكن التراجع عنه. متابعة؟', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_post_payroll', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="post_payroll">
                <input type="hidden" name="run_id" value="<?php echo esc_attr((string) $run->id); ?>">
                <button class="sch-btn"><?php esc_html_e('ترحيل المسير محاسبيًا', 'school-system'); ?></button>
            </form>
        <?php endif; ?>
    </div>

<?php else : ?>

<div class="sch-card">
    <?php if ($runs === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد مسيّرات', 'school-system'); ?></strong>
            <?php esc_html_e('ولّد مسير الشهر من العقود النشطة.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الفترة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الموظفون', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإجمالي', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($runs as $r) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(add_query_arg('run', (int) $r->id, SCH_Dashboard::url('payroll'))); ?>">
                                <?php echo esc_html((SCH_Payroll::MONTHS[(int) $r->period_month] ?? '') . ' ' . $r->period_year); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(number_format_i18n((int) $r->headcount)); ?></td>
                        <td><?php echo esc_html(sch_money($r->total)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $r->status === 'posted' ? 'sch-badge--ok' : 'sch-badge--muted'; ?>">
                                <?php echo esc_html($r->status === 'posted' ? __('مرحّل', 'school-system') : __('مسودة', 'school-system')); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('توليد مسير', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('يُحسب من العقود النشطة، وتُخصم أيام الإجازة بدون راتب تلقائيًا.', 'school-system'); ?></p>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
        <?php wp_nonce_field('sch_run_payroll', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="run_payroll">
        <select name="period_month" required aria-label="الشهر">
            <?php $m = (int) current_time('n'); ?>
            <?php foreach (SCH_Payroll::MONTHS as $num => $label) : ?>
                <option value="<?php echo esc_attr((string) $num); ?>" <?php selected($m, $num); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="period_year" value="<?php echo esc_attr(current_time('Y')); ?>" min="2020" max="2100" required aria-label="السنة">
        <button class="sch-btn"><?php esc_html_e('توليد', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>
