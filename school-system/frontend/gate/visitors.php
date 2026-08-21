<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// التصفية في القاعدة: نافذة الخمسين كانت تُسقط الزائر القديم من الشاشة
// فلا يُخرجه أحد، والعدّاد يعدّ الصفوف كلّها فيتصاعد أبدًا.
$inside = SCH_Security::visitors_inside();
?>
<h1 class="schg-h1"><?php esc_html_e('الزوّار', 'school-system'); ?></h1>
<p class="schg-sub">
    <?php echo esc_html(sprintf(
        /* translators: %d: عدد الزوار */
        _n('زائر واحد بالداخل الآن', '%d زوّار بالداخل الآن', count($inside), 'school-system'),
        count($inside)
    )); ?>
</p>

<?php if (isset($_GET['ok'])) : ?>
    <div class="schg-alert schg-alert--ok"><?php esc_html_e('سُجّل دخول الزائر.', 'school-system'); ?></div>
<?php endif; ?>

<?php
/*
 * فشل النونس كان صامتًا تمامًا: النموذج يُرسَل، ولا شيء يُكتب، ولا رسالة —
 * على جهازٍ يبقى مفتوحًا من السابعة إلى الثانية فينتهي نونسه في منتصف اليوم.
 */
?>
<?php if (isset($_GET['err'])) : ?>
    <div class="schg-alert schg-alert--bad">
        <?php echo esc_html(match (sanitize_key((string) $_GET['err'])) {
            'nonce'  => __('انتهت صلاحية الصفحة — حدّثها ثم أعد التسجيل.', 'school-system'),
            'name'   => __('اسم الزائر مطلوب.', 'school-system'),
            default  => __('تعذّر تنفيذ العملية.', 'school-system'),
        }); ?>
    </div>
<?php endif; ?>

<form method="post" class="schg-form">
    <?php wp_nonce_field('sch_gate_visitor', '_sch_nonce'); ?>
    <input type="hidden" name="sch_gate_action" value="visitor_in">

    <label class="schg-label" for="v-name"><?php esc_html_e('اسم الزائر', 'school-system'); ?></label>
    <input class="schg-input" id="v-name" type="text" name="full_name" required>

    <label class="schg-label" for="v-id"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
    <input class="schg-input" id="v-id" type="text" name="national_id" dir="ltr" inputmode="numeric">

    <label class="schg-label" for="v-phone"><?php esc_html_e('الجوال', 'school-system'); ?></label>
    <input class="schg-input" id="v-phone" type="tel" name="phone" dir="ltr" inputmode="tel">

    <label class="schg-label" for="v-purpose"><?php esc_html_e('سبب الزيارة', 'school-system'); ?></label>
    <input class="schg-input" id="v-purpose" type="text" name="purpose"
           placeholder="<?php esc_attr_e('مثال: مقابلة الأخصائية', 'school-system'); ?>">

    <button class="schg-btn" type="submit"><?php esc_html_e('تسجيل الدخول', 'school-system'); ?></button>
</form>

<h2 class="schg-h2"><?php esc_html_e('بالداخل الآن', 'school-system'); ?></h2>

<?php if ($inside === []) : ?>
    <div class="schg-empty"><strong><?php esc_html_e('لا يوجد زوّار', 'school-system'); ?></strong></div>
<?php else : ?>
    <?php foreach ($inside as $v) : ?>
        <div class="schg-visitor">
            <span class="schg-visitor__t">
                <strong><?php echo esc_html($v->full_name); ?></strong>
                <span><?php echo esc_html($v->purpose ?: '—'); ?></span>
                <span dir="ltr"><?php echo esc_html(substr((string) $v->checked_in, 11, 5)); ?></span>
            </span>
            <form method="post">
                <?php wp_nonce_field('sch_gate_visitor_out', '_sch_nonce'); ?>
                <input type="hidden" name="sch_gate_action" value="visitor_out">
                <input type="hidden" name="visitor_id" value="<?php echo esc_attr((string) $v->id); ?>">
                <button class="schg-mini"><?php esc_html_e('خروج', 'school-system'); ?></button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
