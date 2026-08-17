<?php
/** طلبات الإجازات — سبب يُختار لا يُكتب. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id       = (int) ($sch_data['id'] ?? 0);
$requests = SCH_StudentLeave::of_student($id);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('طلبات الإجازات', 'school-system'); ?></h1>
<p class="p-sub"><?php esc_html_e('الطلب المعتمد يُسكت إنذار الصباح ولا ينتظره الباص.', 'school-system'); ?></p>

<form method="post" class="p-card">
    <?php wp_nonce_field('sch_app_leave', '_sch_nonce'); ?>
    <input type="hidden" name="sch_app_action" value="request_leave">
    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">

    <div class="p-field">
        <label for="l-from"><?php esc_html_e('من تاريخ', 'school-system'); ?></label>
        <input class="p-in" id="l-from" type="date" name="start_date" required
               min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
    </div>

    <div class="p-field">
        <label for="l-to"><?php esc_html_e('إلى تاريخ', 'school-system'); ?></label>
        <input class="p-in" id="l-to" type="date" name="end_date" required
               min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
    </div>

    <div class="p-field">
        <label for="l-why"><?php esc_html_e('السبب', 'school-system'); ?></label>
        <select class="p-in" id="l-why" name="reason_code" required>
            <?php foreach (SCH_StudentLeave::REASONS as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="p-field">
        <label for="l-note"><?php esc_html_e('ملاحظة (اختيارية)', 'school-system'); ?></label>
        <input class="p-in" id="l-note" type="text" name="note" maxlength="190">
    </div>

    <button class="p-btn p-btn--brand p-tap"><?php esc_html_e('إرسال الطلب', 'school-system'); ?></button>
</form>

<?php if ($requests !== []) : ?>
    <h2 class="p-h2"><?php esc_html_e('طلباتك', 'school-system'); ?></h2>
    <div class="p-list">
        <?php foreach ($requests as $req) :
            $tone = match ((string) $req->status) {
                'approved' => 'ok',
                'rejected' => 'late',
                default    => 'warn',
            }; ?>
            <div class="p-row">
                <span class="p-row__t">
                    <b><?php echo esc_html(SCH_StudentLeave::REASONS[$req->reason] ?? ''); ?></b>
                    <span dir="ltr"><?php echo esc_html($req->from_date . ' → ' . $req->to_date); ?></span>
                    <?php if ($req->status === 'rejected' && $req->decision_note) : ?>
                        <span><?php echo esc_html(__('السبب:', 'school-system') . ' ' . $req->decision_note); ?></span>
                    <?php endif; ?>
                </span>
                <span class="p-tag p-tag--<?php echo esc_attr($tone); ?>">
                    <?php echo esc_html(SCH_StudentLeave::STATUSES[$req->status] ?? ''); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
