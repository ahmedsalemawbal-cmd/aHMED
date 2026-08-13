<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// قادم من صندوق الإحالات: الطالب مُختار سلفًا فلا تبحث عنه الممرضة يدويًا.
$sch_pre_student = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;
$sch_pre_note    = isset($_GET['note_id']) ? absint($_GET['note_id']) : 0;
$sch_pre_obj     = $sch_pre_student > 0 ? SCH_Students::get($sch_pre_student) : null;

$visits   = SCH_Clinic::visits(80);
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);
?>
<?php if ($sch_pre_obj) : ?>
    <div class="sch-notice">
        <strong><?php echo esc_html(sprintf(
            /* translators: %s: اسم الطالب */
            __('إحالة: %s', 'school-system'),
            SCH_Enrollment::full_name($sch_pre_obj)
        )); ?></strong>
        <?php $sch_pre_health = SCH_Enrollment::health($sch_pre_student); ?>
        <?php if ($sch_pre_health && $sch_pre_health->allergies) : ?>
            <div class="sch-sub"><?php echo esc_html(__('حساسية:', 'school-system') . ' ' . $sch_pre_health->allergies); ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
SCH_Modal::head(
    __('الصحة المدرسية', 'school-system'),
    '',
    'sch-add-visit',
    __('تسجيل زيارة', 'school-system')
);
?>


<div class="sch-card">
    <h2><?php esc_html_e('سجل الزيارات', 'school-system'); ?></h2>
    <?php if ($visits === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد زيارات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الشكوى', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإجراء', 'school-system'); ?></th>
                    <th><?php esc_html_e('غادر', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($visits as $v) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html($v->visit_at); ?></td>
                        <td class="sch-name"><?php echo esc_html($v->full_name); ?></td>
                        <td><?php echo esc_html($v->complaint); ?></td>
                        <td class="sch-sub"><?php echo esc_html($v->action_note ?: '—'); ?></td>
                        <td><?php echo $v->sent_home ? '<span class="sch-badge">' . esc_html__('نعم', 'school-system') . '</span>' : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php SCH_Modal::open('sch-add-visit', __('تسجيل زيارة', 'school-system'), __('يصل إشعار لولي الأمر فور التسجيل', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('تسجيل زيارة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('يصل إشعار لولي الأمر فور التسجيل.', 'school-system'); ?></p>

    <form method="post">
        <?php wp_nonce_field('sch_add_clinic', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_clinic">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="cl-student"><?php esc_html_e('الطالب', 'school-system'); ?></label>
                <select id="cl-student" name="student_id" required>
                    <option value=""><?php esc_html_e('اختر…', 'school-system'); ?></option>
                    <?php foreach ($students['items'] as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>" <?php selected($sch_pre_student, (int) $s->id); ?>><?php echo esc_html($s->full_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="cl-complaint"><?php esc_html_e('الشكوى', 'school-system'); ?></label>
                <input id="cl-complaint" type="text" name="complaint" required>
            </div>
            <div class="sch-field">
                <label for="cl-action"><?php esc_html_e('الإجراء المتخذ', 'school-system'); ?></label>
                <input id="cl-action" type="text" name="action_note">
            </div>
        </div>
        <label class="sch-check">
            <input type="checkbox" name="sent_home" value="1">
            <?php esc_html_e('طُلب من ولي الأمر استلام الطالب', 'school-system'); ?>
        </label>
        <button class="sch-btn"><?php esc_html_e('حفظ', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
