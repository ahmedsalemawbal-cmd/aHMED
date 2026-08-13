<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$counters = SCH_Gate::counters();
?>
<!-- الحالة والعدّاد -->
<div class="schg-status">
    <span class="schg-pill" id="schg-net"><?php esc_html_e('متصل', 'school-system'); ?></span>
    <span class="schg-counts" id="schg-counts">
        <?php echo esc_html(sprintf(
            /* translators: 1: عدد من في المدرسة 2: الإجمالي */
            __('في المدرسة %1$s من %2$s', 'school-system'),
            number_format_i18n($counters['inside']),
            number_format_i18n($counters['total'])
        )); ?>
    </span>
</div>

<!-- ثلاثة أوضاع: أزرار كبيرة لا قائمة منسدلة -->
<div class="schg-modes" id="schg-modes">
    <?php foreach (SCH_Gate::MODES as $slug => $label) : ?>
        <button type="button" class="schg-mode" data-mode="<?php echo esc_attr($slug); ?>">
            <?php echo esc_html($label); ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- مساحة المسح: تملأ نصف الشاشة -->
<div class="schg-scan" id="schg-scan">
    <video id="schg-video" playsinline muted></video>
    <div class="schg-scan__frame"></div>
    <p class="schg-scan__hint" id="schg-hint"><?php esc_html_e('وجّه الكاميرا نحو الباركود', 'school-system'); ?></p>
</div>

<!-- بطاقة التأكيد: صورة الطالب ثلاث ثوانٍ -->
<div class="schg-card" id="schg-card" hidden>
    <img id="schg-photo" alt="" width="88" height="110">
    <div class="schg-card__text">
        <strong id="schg-name"></strong>
        <span id="schg-meta"></span>
        <span class="schg-card__state" id="schg-state"></span>
    </div>
</div>

<!-- البحث بالاسم: بديل حين تفقد البطاقة أو تتعطل الكاميرا -->
<div class="schg-manual">
    <input type="text" id="schg-search" inputmode="search"
           placeholder="<?php esc_attr_e('أو ابحث بالاسم أو الرقم الأكاديمي', 'school-system'); ?>">
    <div class="schg-results" id="schg-results"></div>
</div>

<!-- آخر عشر عمليات -->
<h2 class="schg-h2"><?php esc_html_e('آخر العمليات', 'school-system'); ?></h2>
<div class="schg-recent" id="schg-recent">
    <?php foreach (SCH_Custody::recent(10) as $e) : ?>
        <div class="schg-recent__i">
            <strong><?php echo esc_html($e->full_name); ?></strong>
            <span><?php echo esc_html(SCH_Custody::CHECKPOINTS[$e->checkpoint] ?? ''); ?></span>
            <span dir="ltr"><?php echo esc_html(substr((string) $e->occurred_at, 11, 5)); ?></span>
        </div>
    <?php endforeach; ?>
</div>
