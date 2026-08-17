<?php
/** أين ابني الآن — الخريطة الحية. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id    = (int) ($sch_data['id'] ?? 0);
$sub   = SCH_Routes::subscription_of($id);
$today = SCH_App::today($id);
$trip  = $today['trip'];
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('أين ابني الآن', 'school-system'); ?></h1>

<?php if (!$sub) : ?>
    <!-- غير مشترك: لا تعرض خريطة فارغة — اعرض حالته عند البوابة -->
    <div class="p-state">
        <span class="p-state__k"><?php esc_html_e('الحالة الآن', 'school-system'); ?></span>
        <b class="p-state__v"><?php echo esc_html(SCH_Custody::state_label(SCH_Students::get($id)->custody_state ?? 'home')); ?></b>
        <span class="p-state__s"><?php esc_html_e('غير مشترك في النقل — تُرصد حالته عند البوابة', 'school-system'); ?></span>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (!$trip) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('bus', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا توجد رحلة جارية', 'school-system'); ?></b>
        <p><?php esc_html_e('تظهر الخريطة فور انطلاق الباص، وتختفي عند إغلاق الرحلة.', 'school-system'); ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="p-trk" id="p-track"
     data-api="<?php echo esc_attr(rest_url(SCH_API_NS . '/track/' . $id)); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

    <div class="p-trk__head">
        <b id="trk-dir"><?php esc_html_e('رحلة جارية', 'school-system'); ?></b>
        <span id="trk-meta"></span>
        <span class="p-trk__off" id="trk-off" hidden><?php esc_html_e('انقطع البث', 'school-system'); ?></span>
    </div>

    <div class="p-trk__map" id="trk-live"></div>

    <div class="p-trk__foot">
        <span class="p-trk__ring">
            <svg width="58" height="58" aria-hidden="true">
                <circle cx="29" cy="29" r="26" fill="none" stroke="#E5E1DA" stroke-width="5"/>
                <circle id="trk-ring" cx="29" cy="29" r="26" fill="none" stroke="#5170FF" stroke-width="5"
                        stroke-linecap="round" stroke-dasharray="163.4" stroke-dashoffset="163.4"
                        transform="rotate(-90 29 29)"/>
            </svg>
            <b id="trk-min">—</b>
        </span>

        <span class="p-trk__t">
            <b id="trk-head"><?php esc_html_e('جارٍ التحديث…', 'school-system'); ?></b>
            <span id="trk-sub"></span>
        </span>
    </div>

    <div class="p-trk__stops" id="trk-stops"></div>
    <span hidden id="trk-kid"><?php echo esc_html((string) $id); ?></span>
    <span hidden id="trk-kid-s"></span>
</div>

<?php
/**
 * Leaflet مستضاف محليًا (‎assets/vendor/leaflet/‎ — 1.9.4، 192 KB).
 *
 * كان يُحمَّل من ‎unpkg.com‎ بلا SRI وبلا بديل. وشاشة التتبع الحي هي
 * «الميزة المميزة للمنتج» بنص الوثيقة — فتعليقها على CDN خارجي يعني:
 * · شبكة مدرسة مُرشَّحة أو انقطاع عند unpkg = **خريطة بيضاء** لكل أب،
 *   في اللحظة التي يريد فيها معرفة أين ابنه.
 * · وبلا SRI، أي تغيير في الملف عند الطرف الثالث يُنفَّذ في متصفح الأب.
 *
 * البلاطات تبقى من OpenStreetMap (قرار موثّق) — والمكتبة عندنا.
 */
?>
<link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/vendor/leaflet/leaflet.css')); ?>">
<script src="<?php echo esc_url(sch_asset('assets/vendor/leaflet/leaflet.js')); ?>" defer></script>
<script src="<?php echo esc_url(sch_asset('assets/track.js')); ?>" defer></script>
