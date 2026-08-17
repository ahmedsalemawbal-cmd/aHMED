<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$route = SCH_Driver::route_of(get_current_user_id());
?>
<?php if (!$route) : ?>
    <div class="schd-empty">
        <strong><?php esc_html_e('لا يوجد مسار مُسنَد لك', 'school-system'); ?></strong>
        <p><?php esc_html_e('تحتاج الإدارة أن تربطك بباص، وتربط الباص بمسار.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="schd-route">
        <span class="schd-route__label"><?php esc_html_e('مسارك', 'school-system'); ?></span>
        <strong class="schd-route__name"><?php echo esc_html($route->name); ?></strong>
        <span class="schd-route__bus" dir="ltr"><?php echo esc_html($route->plate_no); ?></span>
    </div>

    <p class="schd-hint"><?php esc_html_e('اختر نوع الرحلة لتبدأ. سيُطلب منك السماح بالوصول للموقع.', 'school-system'); ?></p>

    <?php foreach (['morning' => __('بدء رحلة الذهاب', 'school-system'), 'afternoon' => __('بدء رحلة العودة', 'school-system')] as $dir => $label) : ?>
        <form method="post" action="<?php echo esc_url(rest_url(SCH_API_NS . '/driver/trip/open')); ?>" class="schd-start" data-direction="<?php echo esc_attr($dir); ?>">
            <button type="button" class="schd-btn schd-btn--big js-start" data-direction="<?php echo esc_attr($dir); ?>">
                <?php echo esc_html($label); ?>
            </button>
        </form>
    <?php endforeach; ?>

    <script>
    (function () {
      var api = <?php echo wp_json_encode(esc_url_raw(rest_url(SCH_API_NS . '/'))); ?>;
      var nonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;

      document.querySelectorAll('.js-start').forEach(function (btn) {
        btn.addEventListener('click', function () {
          btn.disabled = true;
          btn.textContent = '…';

          fetch(api + 'driver/trip/open', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body: JSON.stringify({ direction: btn.dataset.direction })
          })
            .then(function (r) { return r.json(); })
            .then(function () { window.location.reload(); })
            .catch(function () { window.location.reload(); });
        });
      });
    })();
    </script>
<?php endif; ?>
