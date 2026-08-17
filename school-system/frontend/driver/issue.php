<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$done = isset($_GET['ok']);
$err  = isset($_GET['err']);
?>
<a class="schd-back" href="<?php echo esc_url(SCH_Driver::url()); ?>">‹ <?php esc_html_e('رجوع', 'school-system'); ?></a>
<h1 class="schd-h1"><?php esc_html_e('بلاغ عطل', 'school-system'); ?></h1>
<p class="schd-hint"><?php esc_html_e('يصل البلاغ فورًا إلى أوامر الصيانة في الإدارة.', 'school-system'); ?></p>

<?php if ($done) : ?>
    <div class="schd-alert schd-alert--ok"><?php esc_html_e('أُرسل البلاغ.', 'school-system'); ?></div>
<?php endif; ?>
<?php if ($err) : ?>
    <div class="schd-alert"><?php esc_html_e('تعذّر الإرسال. تأكد من كتابة العنوان.', 'school-system'); ?></div>
<?php endif; ?>

<form method="post" class="schd-form">
    <?php wp_nonce_field('sch_drv_issue', '_sch_nonce'); ?>
    <input type="hidden" name="sch_drv_action" value="report_issue">

    <label class="schd-label" for="i-title"><?php esc_html_e('العطل', 'school-system'); ?></label>
    <input class="schd-input" id="i-title" type="text" name="title" required
           placeholder="<?php esc_attr_e('مثال: صوت في الفرامل', 'school-system'); ?>">

    <label class="schd-label" for="i-pri"><?php esc_html_e('الأولوية', 'school-system'); ?></label>
    <select class="schd-input" id="i-pri" name="priority">
        <?php foreach (SCH_Assets::PRIORITIES as $slug => $label) : ?>
            <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, 'medium'); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>

    <label class="schd-label" for="i-desc"><?php esc_html_e('تفاصيل', 'school-system'); ?></label>
    <textarea class="schd-input" id="i-desc" name="description" rows="4"></textarea>

    <button class="schd-btn" type="submit"><?php esc_html_e('إرسال البلاغ', 'school-system'); ?></button>
</form>
