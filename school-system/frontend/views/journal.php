<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$entry_id = isset($_GET['jv']) ? absint($_GET['jv']) : 0;
$entry    = $entry_id > 0 ? SCH_Journal::get($entry_id) : null;
$accounts = SCH_Accounts::all();
?>
<h1 class="sch-title"><?php esc_html_e('القيود اليومية', 'school-system'); ?></h1>

<?php if ($entry) : ?>
    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('journal')); ?>">&larr; <?php esc_html_e('كل القيود', 'school-system'); ?></a>

    <div class="sch-card">
        <h2 dir="ltr" class="sch-mono"><?php echo esc_html($entry->entry_no); ?></h2>
        <dl class="sch-dl">
            <div><dt><?php esc_html_e('التاريخ', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($entry->entry_date); ?></dd></div>
            <div><dt><?php esc_html_e('البيان', 'school-system'); ?></dt><dd><?php echo esc_html($entry->description); ?></dd></div>
            <div><dt><?php esc_html_e('الإجمالي', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($entry->total)); ?></dd></div>
            <div><dt><?php esc_html_e('الحالة', 'school-system'); ?></dt><dd><?php echo esc_html($entry->status === 'posted' ? __('مرحّل', 'school-system') : __('مسودة', 'school-system')); ?></dd></div>
        </dl>

        <div class="sch-table-wrap sch-mt">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الحساب', 'school-system'); ?></th>
                    <th><?php esc_html_e('مدين', 'school-system'); ?></th>
                    <th><?php esc_html_e('دائن', 'school-system'); ?></th>
                    <th><?php esc_html_e('البيان', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach (SCH_Journal::lines($entry_id) as $l) : ?>
                    <tr>
                        <td class="sch-name"><span class="sch-mono" dir="ltr"><?php echo esc_html($l->code); ?></span> <?php echo esc_html($l->name); ?></td>
                        <td><?php echo (float) $l->debit > 0 ? esc_html(sch_money($l->debit)) : '—'; ?></td>
                        <td><?php echo (float) $l->credit > 0 ? esc_html(sch_money($l->credit)) : '—'; ?></td>
                        <td class="sch-sub"><?php echo esc_html($l->description ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sch-toolbar sch-mt">
            <?php if ($entry->status === 'draft') : ?>
                <form method="post">
                    <?php wp_nonce_field('sch_post_journal', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="post_journal">
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr((string) $entry->id); ?>">
                    <button class="sch-btn"><?php esc_html_e('ترحيل القيد', 'school-system'); ?></button>
                </form>
            <?php else : ?>
                <form method="post" onsubmit="return confirm('<?php esc_attr_e('سيُنشأ قيد عكسي. القيود المرحّلة لا تُحذف. متابعة؟', 'school-system'); ?>');">
                    <?php wp_nonce_field('sch_reverse_journal', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="reverse_journal">
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr((string) $entry->id); ?>">
                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عكس القيد', 'school-system'); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php else : ?>

<div class="sch-card">
    <?php $entries = SCH_Journal::recent(); ?>
    <?php if ($entries === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد قيود', 'school-system'); ?></strong>
            <?php esc_html_e('القيود تُنشأ تلقائيًا عند إصدار فاتورة أو تسجيل دفعة، أو أدخلها يدويًا أدناه.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('رقم القيد', 'school-system'); ?></th>
                    <th><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                    <th><?php esc_html_e('البيان', 'school-system'); ?></th>
                    <th><?php esc_html_e('المبلغ', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $e) : ?>
                    <tr>
                        <td class="sch-name">
                            <a class="sch-mono" dir="ltr" href="<?php echo esc_url(add_query_arg('jv', (int) $e->id, SCH_Dashboard::url('journal'))); ?>"><?php echo esc_html($e->entry_no); ?></a>
                        </td>
                        <td dir="ltr"><?php echo esc_html($e->entry_date); ?></td>
                        <td><?php echo esc_html($e->description); ?></td>
                        <td><?php echo esc_html(sch_money($e->total)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $e->status === 'posted' ? 'sch-badge--ok' : 'sch-badge--muted'; ?>">
                                <?php echo esc_html($e->status === 'posted' ? __('مرحّل', 'school-system') : __('مسودة', 'school-system')); ?>
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
    <h2><?php esc_html_e('قيد يدوي', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('مجموع المدين يجب أن يساوي مجموع الدائن، وإلا رُفض القيد.', 'school-system'); ?></p>

    <form method="post">
        <?php wp_nonce_field('sch_add_journal', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_journal">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="jv-date"><?php esc_html_e('التاريخ', 'school-system'); ?></label>
                <input id="jv-date" type="date" name="entry_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
            </div>
            <div class="sch-field">
                <label for="jv-desc"><?php esc_html_e('البيان', 'school-system'); ?></label>
                <input id="jv-desc" type="text" name="description" required>
            </div>
        </div>

        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الحساب', 'school-system'); ?></th>
                    <th style="width:140px"><?php esc_html_e('مدين', 'school-system'); ?></th>
                    <th style="width:140px"><?php esc_html_e('دائن', 'school-system'); ?></th>
                    <th><?php esc_html_e('بيان السطر', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php for ($i = 0; $i < 4; $i++) : ?>
                    <tr>
                        <td>
                            <select name="line_account[]" aria-label="الحساب">
                                <option value=""><?php esc_html_e('—', 'school-system'); ?></option>
                                <?php foreach ($accounts as $a) : ?>
                                    <option value="<?php echo esc_attr((string) $a->id); ?>"><?php echo esc_html($a->code . ' — ' . $a->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="line_debit[]" step="0.01" min="0" aria-label="مدين"></td>
                        <td><input type="number" name="line_credit[]" step="0.01" min="0" aria-label="دائن"></td>
                        <td><input type="text" name="line_note[]" aria-label="بيان"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <label class="sch-check sch-mt">
            <input type="checkbox" name="as_draft" value="1">
            <?php esc_html_e('حفظ كمسودة بلا ترحيل', 'school-system'); ?>
        </label>

        <button class="sch-btn"><?php esc_html_e('حفظ القيد', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>
