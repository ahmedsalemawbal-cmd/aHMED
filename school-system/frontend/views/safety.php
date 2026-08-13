<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$incidents = SCH_Security::incidents(80);
?>
<?php
SCH_Modal::head(
    __('الأمن والسلامة', 'school-system'),
    '',
    'sch-add-incident',
    __('تسجيل بلاغ', 'school-system'),
    'shield'
);
?>


<div class="sch-card">
    <h2><?php esc_html_e('البلاغات', 'school-system'); ?></h2>
    <?php if ($incidents === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد بلاغات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('البلاغ', 'school-system'); ?></th>
                    <th><?php esc_html_e('النوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('الخطورة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($incidents as $i) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($i->title); ?></td>
                        <td><?php echo esc_html(SCH_Security::INCIDENT_TYPES[$i->incident_type] ?? $i->incident_type); ?></td>
                        <td><?php echo esc_html(SCH_Security::SEVERITIES[$i->severity] ?? $i->severity); ?></td>
                        <td dir="ltr"><?php echo esc_html($i->occurred_at); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $i->status === 'closed' ? 'sch-badge--ok' : ''; ?>">
                                <?php echo esc_html($i->status === 'closed' ? __('مغلق', 'school-system') : __('مفتوح', 'school-system')); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($i->status === 'open') : ?>
                                <form method="post" onsubmit="return confirm('سيُغلق البلاغ ولا يمكن إعادة فتحه. متابعة؟');">
                                    <?php wp_nonce_field('sch_close_incident', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="close_incident">
                                    <input type="hidden" name="incident_id" value="<?php echo esc_attr((string) $i->id); ?>">
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إغلاق', 'school-system'); ?></button>
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

<?php SCH_Modal::open('sch-add-incident', __('تسجيل بلاغ سلامة', 'school-system'), __('يصل للمدير فورًا', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('تسجيل بلاغ', 'school-system'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('sch_add_incident', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_incident">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="i-title"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="i-title" type="text" name="title" required>
            </div>
            <div class="sch-field">
                <label for="i-type"><?php esc_html_e('النوع', 'school-system'); ?></label>
                <select id="i-type" name="incident_type">
                    <?php foreach (SCH_Security::INCIDENT_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="i-sev"><?php esc_html_e('الخطورة', 'school-system'); ?></label>
                <select id="i-sev" name="severity">
                    <?php foreach (SCH_Security::SEVERITIES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sch-field">
            <label for="i-desc"><?php esc_html_e('التفاصيل', 'school-system'); ?></label>
            <textarea id="i-desc" name="description" rows="3"></textarea>
        </div>
        <button class="sch-btn"><?php esc_html_e('حفظ البلاغ', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
