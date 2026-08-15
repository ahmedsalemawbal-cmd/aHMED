<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$contracts = SCH_HR::contracts();
$expiring  = SCH_HR::expiring_contracts();
$pending   = SCH_HR::leaves('pending');
$all_leave = SCH_HR::leaves();
$staff     = SCH_Staff::list(['status' => 'active', 'per_page' => 200]);
?>
<div class="sch-head">
    <div>
        <h1 class="sch-title"><?php esc_html_e('العقود والإجازات', 'school-system'); ?></h1>
        <p class="sch-sub"><?php esc_html_e('العقد يدخل الموظف في مسير الرواتب', 'school-system'); ?></p>
    </div>
    <div class="sch-head__acts">
        <?php SCH_Modal::button('sch-add-leave', __('طلب إجازة', 'school-system'), 'check'); ?>
        <?php SCH_Modal::button('sch-add-contract', __('إضافة عقد', 'school-system')); ?>
    </div>
</div>

<?php if ($expiring !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php esc_html_e('عقود تنتهي خلال 60 يومًا', 'school-system'); ?></strong>
        <?php foreach ($expiring as $c) : ?>
            <div class="sch-sub"><?php echo esc_html($c->display_name . ' — ' . $c->end_date); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php SCH_Modal::open('sch-add-contract', __('إضافة عقد', 'school-system'), __('بدونه لا يدخل الموظف في مسير الرواتب', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('العقود', 'school-system'); ?></h2>
    <?php if ($contracts === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد عقود', 'school-system'); ?></strong><?php esc_html_e('أضف عقدًا لكل موظف ليدخل في مسير الرواتب.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                    <th><?php esc_html_e('النوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('من', 'school-system'); ?></th>
                    <th><?php esc_html_e('إلى', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأساسي', 'school-system'); ?></th>
                    <th><?php esc_html_e('البدلات', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($contracts as $c) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($c->display_name); ?></td>
                        <td><?php echo esc_html(SCH_HR::CONTRACT_TYPES[$c->contract_type] ?? $c->contract_type); ?></td>
                        <td dir="ltr"><?php echo esc_html($c->start_date); ?></td>
                        <td dir="ltr"><?php echo esc_html($c->end_date ?: '—'); ?></td>
                        <td><?php echo esc_html(sch_money($c->basic_salary)); ?></td>
                        <td><?php echo esc_html(sch_money($c->allowances)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $c->status === 'active' ? 'sch-badge--ok' : 'sch-badge--muted'; ?>">
                                <?php echo esc_html($c->status === 'active' ? __('ساري', 'school-system') : __('منتهٍ', 'school-system')); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-mt">
        <?php wp_nonce_field('sch_add_contract', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_contract">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="c-user"><?php esc_html_e('الموظف', 'school-system'); ?></label>
                <select id="c-user" name="user_id" required>
                    <option value=""><?php esc_html_e('اختر…', 'school-system'); ?></option>
                    <?php foreach ($staff['items'] as $e) : ?>
                        <option value="<?php echo esc_attr((string) $e->user_id); ?>"><?php echo esc_html($e->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="c-type"><?php esc_html_e('نوع العقد', 'school-system'); ?></label>
                <select id="c-type" name="contract_type">
                    <?php foreach (SCH_HR::CONTRACT_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="c-start"><?php esc_html_e('بداية العقد', 'school-system'); ?></label>
                <input id="c-start" type="date" name="start_date" required>
            </div>
            <div class="sch-field">
                <label for="c-end"><?php esc_html_e('نهاية العقد', 'school-system'); ?></label>
                <input id="c-end" type="date" name="end_date">
            </div>
            <div class="sch-field">
                <label for="c-basic"><?php esc_html_e('الراتب الأساسي', 'school-system'); ?></label>
                <input id="c-basic" type="number" name="basic_salary" step="0.01" min="0" required>
            </div>
            <div class="sch-field">
                <label for="c-allow"><?php esc_html_e('البدلات', 'school-system'); ?></label>
                <input id="c-allow" type="number" name="allowances" step="0.01" min="0" value="0">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('حفظ العقد', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>

<?php SCH_Modal::open('sch-add-leave', __('طلب إجازة', 'school-system'), __('يُرفع للمدير للاعتماد', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('طلبات الإجازة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php echo esc_html(sprintf(__('%d طلب بانتظار القرار.', 'school-system'), count($pending))); ?></p>

    <?php if ($all_leave === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد طلبات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                    <th><?php esc_html_e('النوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('من', 'school-system'); ?></th>
                    <th><?php esc_html_e('إلى', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأيام', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($all_leave as $l) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($l->display_name); ?></td>
                        <td><?php echo esc_html(SCH_HR::LEAVE_TYPES[$l->leave_type] ?? $l->leave_type); ?></td>
                        <td dir="ltr"><?php echo esc_html($l->start_date); ?></td>
                        <td dir="ltr"><?php echo esc_html($l->end_date); ?></td>
                        <td><?php echo esc_html((string) $l->days); ?></td>
                        <?php
                        $sch_leave_class = match ($l->status) {
                            'approved' => 'sch-badge--ok',
                            'rejected' => 'sch-badge--muted',
                            default    => 'sch-badge--warn',
                        };
                        ?>
                        <td>
                            <span class="sch-badge <?php echo esc_attr($sch_leave_class); ?>">
                                <?php echo esc_html(match ($l->status) {
                                    'approved' => __('معتمدة', 'school-system'),
                                    'rejected' => __('مرفوضة', 'school-system'),
                                    default    => __('بانتظار', 'school-system'),
                                }); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($l->status === 'pending') : ?>
                                <div class="sch-inline">
                                    <?php foreach (['approved' => __('اعتماد', 'school-system'), 'rejected' => __('رفض', 'school-system')] as $dec => $label) : ?>
                                        <form method="post">
                                            <?php wp_nonce_field('sch_decide_leave', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="decide_leave">
                                            <input type="hidden" name="leave_id" value="<?php echo esc_attr((string) $l->id); ?>">
                                            <input type="hidden" name="decision" value="<?php echo esc_attr($dec); ?>">
                                            <button class="<?php echo $dec === 'approved' ? 'sch-btn sch-btn--quiet' : 'sch-link-danger'; ?>"><?php echo esc_html($label); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-mt">
        <?php wp_nonce_field('sch_request_leave', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="request_leave">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="l-user"><?php esc_html_e('الموظف', 'school-system'); ?></label>
                <select id="l-user" name="user_id" required>
                    <?php foreach ($staff['items'] as $e) : ?>
                        <option value="<?php echo esc_attr((string) $e->user_id); ?>"><?php echo esc_html($e->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="l-type"><?php esc_html_e('نوع الإجازة', 'school-system'); ?></label>
                <select id="l-type" name="leave_type">
                    <?php foreach (SCH_HR::LEAVE_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="l-start"><?php esc_html_e('من', 'school-system'); ?></label>
                <input id="l-start" type="date" name="start_date" required>
            </div>
            <div class="sch-field">
                <label for="l-end"><?php esc_html_e('إلى', 'school-system'); ?></label>
                <input id="l-end" type="date" name="end_date" required>
            </div>
            <div class="sch-field">
                <label for="l-reason"><?php esc_html_e('السبب', 'school-system'); ?></label>
                <input id="l-reason" type="text" name="reason">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('تقديم الطلب', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
