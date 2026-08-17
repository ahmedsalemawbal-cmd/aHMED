<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$visitors = SCH_Security::visitors(80);
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);
?>
<?php
SCH_Modal::head(
    __('الزوّار', 'school-system'),
    '',
    'sch-add-visitor',
    __('تسجيل زائر', 'school-system')
);
?>

<div class="sch-stats">
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n(SCH_Security::inside_now())); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('زائر داخل المدرسة الآن', 'school-system'); ?></span>
    </div>
</div>


<div class="sch-card">
    <h2><?php esc_html_e('سجل الزيارات', 'school-system'); ?></h2>
    <?php if ($visitors === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد زيارات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الزائر', 'school-system'); ?></th>
                    <th><?php esc_html_e('السبب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الدخول', 'school-system'); ?></th>
                    <th><?php esc_html_e('الخروج', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($visitors as $v) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($v->full_name); ?></td>
                        <td><?php echo esc_html($v->purpose ?: '—'); ?></td>
                        <td><?php echo esc_html($v->student_name ?: '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($v->checked_in); ?></td>
                        <td dir="ltr"><?php echo esc_html($v->checked_out ?: '—'); ?></td>
                        <td>
                            <?php if (!$v->checked_out) : ?>
                                <form method="post">
                                    <?php wp_nonce_field('sch_check_out', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="check_out">
                                    <input type="hidden" name="visitor_id" value="<?php echo esc_attr((string) $v->id); ?>">
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('خروج', 'school-system'); ?></button>
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

<?php SCH_Modal::open('sch-add-visitor', __('تسجيل دخول زائر', 'school-system'), __('يُسجَّل خروجه بضغطة عند مغادرته', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('تسجيل دخول زائر', 'school-system'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('sch_check_in', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="check_in">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="v-name"><?php esc_html_e('اسم الزائر', 'school-system'); ?></label>
                <input id="v-name" type="text" name="full_name" required>
            </div>
            <div class="sch-field">
                <label for="v-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                <input id="v-nid" type="text" name="national_id" dir="ltr">
            </div>
            <div class="sch-field">
                <label for="v-phone"><?php esc_html_e('الجوال', 'school-system'); ?></label>
                <input id="v-phone" type="tel" name="phone" dir="ltr">
            </div>
            <div class="sch-field">
                <label for="v-purpose"><?php esc_html_e('سبب الزيارة', 'school-system'); ?></label>
                <input id="v-purpose" type="text" name="purpose">
            </div>
            <div class="sch-field">
                <label for="v-student"><?php esc_html_e('الطالب المعني', 'school-system'); ?></label>
                <select id="v-student" name="student_id">
                    <option value=""><?php esc_html_e('—', 'school-system'); ?></option>
                    <?php foreach ($students['items'] as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->full_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('تسجيل الدخول', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
