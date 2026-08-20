<?php
/** أين ابني الآن — الخريطة الحية. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$sub     = SCH_Routes::subscription_of($id);
$today   = SCH_App::today($id);
$trip    = $today['trip'];
$student = SCH_Students::get($id);
?>

<?php if (!$sub || !$trip) : ?>

    <a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
        <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <?php esc_html_e('رجوع', 'school-system'); ?>
    </a>

    <h1 class="p-h1"><?php esc_html_e('أين ابني الآن', 'school-system'); ?></h1>

    <?php if (!$sub) : ?>
        <!-- غير مشترك: لا تعرض خريطة فارغة — اعرض حالته عند البوابة -->
        <div class="p-state">
            <span class="p-state__k"><?php esc_html_e('الحالة الآن', 'school-system'); ?></span>
            <b class="p-state__v"><?php echo esc_html(SCH_Custody::state_label($student->custody_state ?? 'home')); ?></b>
            <span class="p-state__s"><?php esc_html_e('غير مشترك في النقل — تُرصد حالته عند البوابة', 'school-system'); ?></span>
        </div>
    <?php else : ?>
        <div class="p-empty">
            <span class="p-empty__i"><?php echo sch_icon('bus', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <b><?php esc_html_e('لا توجد رحلة جارية', 'school-system'); ?></b>
            <p><?php esc_html_e('تظهر الخريطة فور انطلاق الباص، وتختفي عند إغلاق الرحلة.', 'school-system'); ?></p>
        </div>
    <?php endif; ?>

    <?php return; ?>
<?php endif; ?>

<?php
// الاسم الأول وحده في شارة الخريطة: الرباعي يُقطع بثلاث نقاط ولا يفيد أحدًا.
$kid_name = $student ? (string) ($student->first_name ?: $student->full_name) : '';

/**
 * اسم الباص ولوحته وسائقه ليست في سجلّ الاشتراك — تُشتقّ من سلسلته:
 * الاشتراك ← المسار ← الباص ← مستخدم السائق ← ملفّه الوظيفي (وفيه جواله).
 * وكل حلقة مشروطة بما قبلها، فمسارٌ بلا باص لا يكسر الشاشة.
 */
$route  = SCH_Routes::get((int) $sub->route_id);
$bus    = ($route && $route->bus_id) ? SCH_Buses::get((int) $route->bus_id) : null;
$driver = ($bus && $bus->driver_user_id) ? get_user_by('id', (int) $bus->driver_user_id) : null;
$emp    = $driver ? SCH_Staff::get_by_user((int) $driver->ID) : null;
$phone  = $emp ? trim((string) ($emp->phone ?? '')) : '';

// الرقم يُعرض مقروءًا ويُطلَب مجرّدًا — الفراغات والشرطات تكسر رابط الاتصال.
$tel = $phone !== '' ? (string) preg_replace('/[^0-9+]/', '', $phone) : '';

/**
 * الرحلة جارية: الشاشة تخرج من حشوة القالب فتصير خريطةً تملأ الأعلى
 * ولوحًا سفليًّا يعلوها. واللوح أهمّ من الخريطة — الأب لا يريد التحديق
 * في نقطة تتحرّك، يريد «كم بقي» و«هل صعد ابني».
 */
?>
<div class="p-live" id="p-track"
     data-api="<?php echo esc_attr(rest_url(SCH_API_NS . '/track/' . $id)); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
     aria-label="<?php esc_attr_e('أين ابني الآن', 'school-system'); ?>">

    <?php /* Leaflet يبني خريطته في العنصر المسمّى `scha-map` بالاسم، والحاوية
             حوله تحمل `trk-live` لأن السكربت يضع عليها `is-off` عند انقطاع
             النداء. فصلهما يُبقي الاسمين معًا بلا أن يسرق أحدهما الآخر. */ ?>
    <div class="p-live__map" id="trk-live">
        <div class="p-live__canvas" id="scha-map"></div>

        <div class="p-live__bar">
            <a class="p-live__back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
               aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
                <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </a>

            <?php /* شارة الطفل: النقطة تنبض ما دام على متن الباص، ويطفئها
                     `track.js` بوضع `is-off` على `#trk-kid` حين لا يكون صعد. */ ?>
            <span class="p-live__who" id="trk-kid">
                <i class="p-live__dot" aria-hidden="true"></i>
                <b><?php echo esc_html($kid_name); ?></b>
                <span class="p-live__st" id="trk-kid-s"><?php echo esc_html(SCH_Custody::state_label($student->custody_state ?? 'home')); ?></span>
            </span>

            <span class="p-live__off" id="trk-off" hidden><?php esc_html_e('انقطع البث', 'school-system'); ?></span>
        </div>
    </div>

    <div class="p-live__sheet">
        <span class="p-live__grip" aria-hidden="true"></span>

        <div class="p-live__head">
            <span class="p-live__t">
                <h1 class="p-live__hd" id="trk-head"><?php esc_html_e('جارٍ التحديث…', 'school-system'); ?></h1>
                <span class="p-live__sub">
                    <span id="trk-sub"><?php esc_html_e('نحسب وقت الوصول…', 'school-system'); ?></span>
                    <span class="p-live__sep" aria-hidden="true">·</span>
                    <span id="trk-dir"><?php esc_html_e('رحلة جارية', 'school-system'); ?></span>
                </span>
            </span>

            <?php /* `track.js` يحقن الرقم ووحدته («٦» ثم «دقائق») في هذا
                     العنصر نفسه، فهو الحبّة كلّها لا الرقم وحده. */ ?>
            <span class="p-live__eta" id="trk-min">—</span>
        </div>

        <?php if ($driver) : ?>
            <div class="p-live__drv">
                <span class="p-live__pic">
                    <?php echo sch_avatar_svg(mb_substr((string) $driver->display_name, 0, 1), 46); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </span>
                <span class="p-live__t2">
                    <b><?php echo esc_html((string) $driver->display_name); ?></b>
                    <span class="p-nm"><?php echo esc_html($phone !== '' ? $phone : __('لا رقم مسجَّل', 'school-system')); ?></span>
                </span>
                <?php if ($tel !== '') : ?>
                    <a class="p-live__call" href="<?php echo esc_url('tel:' . $tel); ?>"
                       aria-label="<?php esc_attr_e('الاتصال بالسائق', 'school-system'); ?>">
                        <?php echo sch_icon('chat', 20); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="p-live__facts">
            <?php /* اسم المسار مكتوبٌ من PHP ليقرأه الأب قبل أوّل نداء، ثم
                     يستبدله `track.js` بـ«المسار · الباص» حين يصل الردّ. */ ?>
            <span class="p-live__fact">
                <span><?php esc_html_e('الباص', 'school-system'); ?></span>
                <b id="trk-meta"><?php echo esc_html((string) $sub->route_name); ?></b>
            </span>

            <?php if ($bus && $bus->plate_no) : ?>
                <span class="p-live__fact">
                    <span><?php esc_html_e('اللوحة', 'school-system'); ?></span>
                    <b class="p-nm"><?php echo esc_html((string) $bus->plate_no); ?></b>
                </span>
            <?php else : ?>
                <?php /* بلا لوحة مسجَّلة لا تُترك الخانة فارغة: نقطة الالتقاء
                         خبرٌ يفيد الأب في الموضع نفسه. */ ?>
                <span class="p-live__fact">
                    <span><?php esc_html_e('نقطة الالتقاء', 'school-system'); ?></span>
                    <b><?php echo esc_html((string) ($sub->stop_name ?: __('غير محددة', 'school-system'))); ?></b>
                </span>
            <?php endif; ?>
        </div>

        <?php /* مرسى شريط النقاط يبنيه `track.js` بـ`appendChild` — وحذفه
                 يُسقط الرسم كلّه في الخطأ فتُظلَّم الخريطة عند كل نداء ناجح. */ ?>
        <div class="p-trk__stops" id="trk-stops"></div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="<?php echo esc_url(sch_asset('assets/track.js')); ?>" defer></script>
