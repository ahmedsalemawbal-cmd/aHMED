<?php
/**
 * Bilingual (AR/EN) language system — critical bootstrap only.
 *
 * The full switcher (osoulSwitchLang, placeholder swaps, etc.) lives in the
 * cached assets/js/osoul.js. The only thing that MUST stay inline is the tiny
 * pre-paint snippet that applies the saved language to <html> before first
 * paint — otherwise visitors who chose English would see a flash of Arabic.
 *
 * All the language CSS that used to be printed here now lives in
 * assets/css/osoul.css under the "Language system" section.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_head', function () {
	?>
<script id="osoul-lang-critical">
(function(){
	try{
		var lang = localStorage.getItem('osoul_lang') || 'ar';
		var el = document.documentElement;
		el.setAttribute('lang', lang === 'en' ? 'en' : 'ar');
		el.setAttribute('dir', lang === 'en' ? 'ltr' : 'rtl');
		if (lang === 'en') { el.classList.add('lang-en'); }
		// Keep the cookie in sync so PHP (SEO titles, og:locale) can read it.
		document.cookie = 'osoul_lang=' + (lang === 'en' ? 'en' : 'ar') + ';path=/;max-age=31536000;SameSite=Lax';
	}catch(e){}
})();
</script>
	<?php
}, 2 );
