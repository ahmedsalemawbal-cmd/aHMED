<?php
/**
 * تتبّع التحويلات — بكسل تيك توك (TikTok Pixel).
 *
 * يُحقن كود البكسل في <head> على كل الصفحات عند ضبط «معرّف بكسل تيك توك» من الإعدادات،
 * ويُطلَق تلقائيًا:
 *   - PageView على كل صفحة (ttq.page).
 *   - CompleteRegistration عند نجاح إرسال نموذج التسجيل (من falak.js).
 *
 * لا يُطبع أي شيء إن كان المعرّف فارغًا (بلا أثر على الأداء).
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * معرّف بكسل تيك توك بعد التنظيف (أحرف/أرقام فقط)، أو '' .
 */
function falak_tiktok_pixel_id() {
	$pid = preg_replace( '/[^A-Za-z0-9]/', '', (string) falak_opt( 'tiktok_pixel' ) );
	return $pid ? $pid : '';
}

add_action( 'wp_head', 'falak_tiktok_pixel_head', 3 );
function falak_tiktok_pixel_head() {
	$pid = falak_tiktok_pixel_id();
	if ( '' === $pid ) {
		return;
	}
	?>
<!-- TikTok Pixel Code -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load('<?php echo esc_js( $pid ); ?>');
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- End TikTok Pixel Code -->
	<?php
}
