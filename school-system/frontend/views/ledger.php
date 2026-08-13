<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$accounts   = SCH_Accounts::all();
$account_id = isset($_GET['acc']) ? absint($_GET['acc']) : (int) ($accounts[0]->id ?? 0);
$account    = $account_id > 0 ? SCH_Accounts::get($account_id) : null;
$from       = isset($_GET['from']) ? (sch_sanitize_date(wp_unslash((string) $_GET['from'])) ?? '') : '';
$to         = isset($_GET['to']) ? (sch_sanitize_date(wp_unslash((string) $_GET['to'])) ?? '') : '';
$rows       = $account ? SCH_Journal::ledger($account_id, $from ?: null, $to ?: null) : [];
?>
<h1 class="sch-title"><?php esc_html_e('دفتر الأستاذ', 'school-system'); ?></h1>

<div class="sch-card">
    <form method="get" class="sch-toolbar">
        <select name="acc" onchange="this.form.submit()" aria-label="الحساب">
            <?php foreach ($accounts as $a) : ?>
                <option value="<?php echo esc_attr((string) $a->id); ?>" <?php selected($account_id, (int) $a->id); ?>>
                    <?php echo esc_html($a->code . ' — ' . $a->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?php echo esc_attr($from); ?>" aria-label="من تاريخ">
        <input type="date" name="to" value="<?php echo esc_attr($to); ?>" aria-label="إلى تاريخ">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>
    </form>

    <?php if (!$account) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد حسابات', 'school-system'); ?></strong></div>
    <?php elseif ($rows === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا حركة على هذا الحساب', 'school-system'); ?></strong>
            <?php esc_html_e('تظهر الحركات هنا بعد ترحيل قيود تخصّه.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <p class="sch-sub">
            <?php echo esc_html($account->name); ?> —
            <?php esc_html_e('الرصيد الحالي:', 'school-system'); ?>
            <strong><?php echo esc_html(sch_money(SCH_Accounts::balance($account_id))); ?></strong>
        </p>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                    <th><?php esc_html_e('القيد', 'school-system'); ?></th>
                    <th><?php esc_html_e('البيان', 'school-system'); ?></th>
                    <th><?php esc_html_e('مدين', 'school-system'); ?></th>
                    <th><?php esc_html_e('دائن', 'school-system'); ?></th>
                    <th><?php esc_html_e('الرصيد', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html($r->entry_date); ?></td>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($r->entry_no); ?></td>
                        <td><?php echo esc_html($r->description); ?></td>
                        <td><?php echo (float) $r->debit > 0 ? esc_html(sch_money($r->debit)) : '—'; ?></td>
                        <td><?php echo (float) $r->credit > 0 ? esc_html(sch_money($r->credit)) : '—'; ?></td>
                        <td class="sch-name"><?php echo esc_html(sch_money($r->balance)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
