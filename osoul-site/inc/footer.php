<?php
/**
 * Site footer + floating widgets (language switcher, WhatsApp, cookie notice,
 * certificate lightbox container).
 *
 * Styling in assets/css/osoul.css; behaviour in assets/js/osoul.js.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', 'osoul_footer_output', 2 );
if ( ! function_exists( 'osoul_footer_output' ) ) {
	function osoul_footer_output() {
		$year   = gmdate( 'Y' );
		$home   = home_url( '/' );
		$wa     = osoul_wa_url();
		$phone1 = osoul_opt( 'phone_primary' );
		$phone2 = osoul_opt( 'phone_secondary' );
		$email  = osoul_opt( 'email' );
		?>
<footer id="osoul-footer">
<div class="osf-main">
<div>
<div class="osf-name"><?php osoul_bilingual( 'أصول البناء للصناعة', 'Osoul Albinaa Industrial Co.' ); ?></div>
<span class="osf-name-en">Osoul Albinaa Industrial Co.</span>
<p class="osf-about"><?php osoul_bilingual(
	'شركة سعودية متخصصة في تصنيع الأبواب المعدنية المقاومة للحريق، وأنظمة الجبس بورد، وإكسسوارات التكييف — لدعم المشاريع الحكومية والتجارية والتعليمية بجودة عالية.',
	'A Saudi specialized company manufacturing fire-resistant metal doors, gypsum board systems, and HVAC accessories — supporting government, commercial and educational projects with high quality.'
); ?></p>
<div class="osf-logo-footer"><img src="https://osoulalbinaa.com/wp-content/uploads/2026/06/تصميم-بدون-عنوان-20.png" alt="أصول البناء للصناعة"></div>
</div>
<div>
<div class="osf-heading"><?php osoul_bilingual( 'روابط سريعة', 'Quick Links' ); ?></div>
<ul class="osf-links">
<li><a href="<?php echo esc_url( $home ); ?>"><?php osoul_bilingual( 'الرئيسية', 'Home' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'about-us/' ); ?>"><?php osoul_bilingual( 'من نحن', 'About Us' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'products/' ); ?>"><?php osoul_bilingual( 'المنتجات', 'Products' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'services/' ); ?>"><?php osoul_bilingual( 'خدماتنا', 'Services' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'projects/' ); ?>"><?php osoul_bilingual( 'المشاريع', 'Projects' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'contact/' ); ?>"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'privacy-policy/' ); ?>"><?php osoul_bilingual( 'سياسة الخصوصية', 'Privacy Policy' ); ?></a></li>
</ul>
</div>
<div>
<div class="osf-heading"><?php osoul_bilingual( 'الصناعات', 'Industries' ); ?></div>
<ul class="osf-links">
<li><a href="<?php echo esc_url( $home . 'doors/' ); ?>"><?php osoul_bilingual( 'الأبواب بأنواعها الكاملة', 'All Door Types' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'gypsum/' ); ?>"><?php osoul_bilingual( 'بروفايلات الجبسوم والسمنت بورد', 'Gypsum Board Profiles' ); ?></a></li>
<li><a href="<?php echo esc_url( $home . 'strut/' ); ?>"><?php osoul_bilingual( 'بروفايلات وإكسسوارات التثبيت والتعليق', 'Strut Fixing & Suspension Accessories' ); ?></a></li>
</ul>
</div>
<div>
<div class="osf-heading"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></div>
<ul class="osf-contact">
<li><div class="osf-contact-ic"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div><span><?php osoul_bilingual( osoul_opt( 'address_ar' ), osoul_opt( 'address_en' ) ); ?></span></li>
<li><div class="osf-contact-ic"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 01.12 2.18 2 2 0 012.11 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg></div><span><a href="tel:<?php echo esc_attr( osoul_tel( $phone1 ) ); ?>"><?php echo esc_html( $phone1 ); ?></a><?php if ( $phone2 ) : ?><br><a href="tel:<?php echo esc_attr( osoul_tel( $phone2 ) ); ?>" style="font-size:12px;opacity:.7"><?php echo esc_html( $phone2 ); ?></a><?php endif; ?></span></li>
<li><div class="osf-contact-ic"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></li>
<li><div class="osf-contact-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div><span><?php osoul_bilingual( osoul_opt( 'hours_ar' ), osoul_opt( 'hours_en' ) ); ?></span></li>
</ul>
<div class="osf-social-label"><?php osoul_bilingual( 'تابعنا', 'Follow Us' ); ?></div>
<div class="osf-social">
<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<a href="<?php echo esc_url( osoul_opt( 'instagram' ) ); ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
<a href="<?php echo esc_url( osoul_opt( 'linkedin' ) ); ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
<a href="<?php echo esc_url( osoul_opt( 'twitter' ) ); ?>" target="_blank" rel="noopener" aria-label="Twitter X"><svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
</div>
</div>
</div>
<div class="osf-divider"></div>
<div class="osf-bottom">
<p class="osf-copy">© <?php echo esc_html( $year ); ?> <strong><?php osoul_bilingual( 'أصول البناء للصناعة', 'Osoul Albinaa Industrial Co.' ); ?></strong> — <?php osoul_bilingual( 'جميع الحقوق محفوظة', 'All Rights Reserved' ); ?></p>
<div class="osf-bottom-links">
<a href="<?php echo esc_url( $home . 'privacy-policy/' ); ?>"><?php osoul_bilingual( 'سياسة الخصوصية', 'Privacy Policy' ); ?></a>
<div class="osf-bottom-sep"></div>
<a href="<?php echo esc_url( $home . 'contact/' ); ?>"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
</div>
<div class="osf-bottom-brand"><b>OSOUL ALBINAA</b> — <?php osoul_bilingual( 'أصول البناء', 'Industrial Co.' ); ?></div>
</div>
</footer>

<!-- Floating language switcher -->
<div id="osoul-lang-float" aria-label="Language Switcher">
	<button class="olf-btn" id="olf-ar" data-lang-btn="ar" onclick="osoulSwitchLang('ar')" aria-label="عربي" title="عربي">AR</button>
	<div class="olf-divider"></div>
	<button class="olf-btn" id="olf-en" data-lang-btn="en" onclick="osoulSwitchLang('en')" aria-label="English" title="English">EN</button>
</div>

<!-- Floating WhatsApp -->
<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="osf-wa-float" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
		<?php
	}
}

/**
 * Cookie notice.
 */
add_action( 'wp_footer', 'osoul_cookie_notice', 97 );
if ( ! function_exists( 'osoul_cookie_notice' ) ) {
	function osoul_cookie_notice() {
		?>
<div id="osoul-cookie">
<p class="osc-txt"><?php
	osoul_bilingual(
		'نستخدم ملفات تعريف الارتباط (الكوكيز) لتحسين تجربتك على موقعنا. ',
		'We use cookies to improve your experience on our site. '
	);
	?><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php osoul_bilingual( 'سياسة الخصوصية', 'Privacy Policy' ); ?></a></p>
<div class="osc-btns">
	<button class="osc-accept" onclick="oscAccept()"><?php osoul_bilingual( 'قبول', 'Accept' ); ?></button>
	<button class="osc-decline" onclick="oscDecline()"><?php osoul_bilingual( 'رفض', 'Decline' ); ?></button>
</div>
</div>
		<?php
	}
}

/**
 * Quote-list drawer (global — opened from the header on every page).
 * Logic lives in assets/js/osoul.js (oql* functions).
 */
add_action( 'wp_footer', 'osoul_quote_list_output', 95 );
if ( ! function_exists( 'osoul_quote_list_output' ) ) {
	function osoul_quote_list_output() {
		?>
<div id="oql-overlay" onclick="oqlCloseDrawer()"></div>
<div id="oql-drawer" role="dialog" aria-label="قائمة العرض">
	<div class="oql-header">
		<h2><?php osoul_bilingual( 'قائمة طلب العرض', 'Quote Request List' ); ?></h2>
		<button class="oql-close" onclick="oqlCloseDrawer()" aria-label="إغلاق"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
	</div>
	<div class="oql-body">
		<div id="oql-empty-state" class="oql-empty">
			<svg viewBox="0 0 24 24"><path d="M9 2L3 7v13a1 1 0 001 1h16a1 1 0 001-1V7l-6-5"/><path d="M16 10a4 4 0 01-8 0"/></svg>
			<h3><?php osoul_bilingual( 'القائمة فارغة', 'Empty List' ); ?></h3>
			<p><?php osoul_bilingual( 'أضف منتجات من الكتالوج', 'Add products from the catalog' ); ?></p>
		</div>
		<div id="oql-list"></div>
	</div>
	<div class="oql-footer" id="oql-footer-actions" style="display:none">
		<div class="oql-summary">
			<span><?php osoul_bilingual( 'إجمالي المنتجات', 'Total Items' ); ?></span>
			<strong id="oql-total-count">0</strong>
		</div>
		<!-- Quote request form (primary). Inline styles guarantee rendering even if the cached stylesheet is stale. -->
		<div class="oql-rfq" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
			<input type="text" id="oql-name" placeholder="<?php echo esc_attr__( 'الاسم', 'osoul' ); ?>" autocomplete="name" style="border:1.5px solid rgba(13,31,60,.18);border-radius:6px;padding:11px 14px;font-size:14px;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;color:#0d1f3c;width:100%;min-height:44px;outline:none;background:#fff">
			<input type="tel" id="oql-phone" placeholder="<?php echo esc_attr__( 'رقم الجوال', 'osoul' ); ?>" autocomplete="tel" style="border:1.5px solid rgba(13,31,60,.18);border-radius:6px;padding:11px 14px;font-size:14px;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;color:#0d1f3c;width:100%;min-height:44px;outline:none;background:#fff">
			<input type="email" id="oql-email" placeholder="<?php echo esc_attr__( 'البريد الإلكتروني', 'osoul' ); ?>" autocomplete="email" style="border:1.5px solid rgba(13,31,60,.18);border-radius:6px;padding:11px 14px;font-size:14px;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;color:#0d1f3c;width:100%;min-height:44px;outline:none;background:#fff">
			<input type="text" id="oql-website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
			<div id="oql-status" class="oql-status" role="status" aria-live="polite" style="display:none"></div>
			<button class="oql-submit-btn" onclick="oqlSubmitRequest()" style="display:flex;align-items:center;justify-content:center;background:#D21217;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:14px;font-weight:700;padding:14px 20px;border:none;border-radius:6px;cursor:pointer;width:100%;min-height:46px"><?php osoul_bilingual( 'أرسل طلب عرض السعر', 'Send Quote Request' ); ?></button>
		</div>
		<button class="oql-clear-btn" onclick="oqlClearAll()">
			<?php osoul_bilingual( 'مسح القائمة', 'Clear List' ); ?>
		</button>
	</div>
</div>
<script>
/* Self-contained quote-request submit — defined inline so it works even if the
   external osoul.js is still cached by the browser/CDN. Runs after osoul.js, so
   this definition wins. */
(function () {
	window.oqlSubmitRequest = function () {
		var DATA = window.osoulData || {};
		function lang() { try { return localStorage.getItem('osoul_lang') || 'ar'; } catch (e) { return 'ar'; } }
		function list() { try { return JSON.parse(localStorage.getItem('osoul_quote_list') || '[]'); } catch (e) { return []; } }
		var L = lang();
		var val = function (id) { var e = document.getElementById(id); return e ? e.value.trim() : ''; };
		var name = val('oql-name'), phone = val('oql-phone'), email = val('oql-email'), website = val('oql-website');
		var CUST = window.osoulCustomer || {};
		if (CUST.loggedIn) { name = name || CUST.name || ''; phone = phone || CUST.phone || ''; email = email || CUST.email || ''; }
		var status = document.getElementById('oql-status');
		var btn = document.querySelector('.oql-submit-btn');
		function show(type, text) {
			if (!status) { if (type === 'err') { alert(text); } return; }
			status.style.display = 'block';
			status.style.padding = '10px 14px'; status.style.borderRadius = '6px';
			status.style.fontSize = '13px'; status.style.fontWeight = '600'; status.style.marginTop = '4px';
			if (type === 'ok') { status.style.background = 'rgba(37,211,102,.12)'; status.style.color = '#1a7e42'; }
			else { status.style.background = 'rgba(210,18,23,.08)'; status.style.color = '#b50f13'; }
			status.textContent = text;
		}
		var items = list();
		if (!items.length) { show('err', L === 'en' ? 'Your quote list is empty.' : 'قائمة العرض فارغة.'); return; }
		if (!CUST.loggedIn && (!name || (!phone && !email))) { show('err', L === 'en' ? 'Please enter your name and a phone or email.' : 'الرجاء إدخال الاسم ورقم الجوال أو الإيميل.'); return; }
		var url = DATA.quoteUrl || '';
		if (!url) { show('err', L === 'en' ? 'Configuration error. Please clear cache.' : 'خطأ إعداد — يرجى مسح الكاش.'); return; }
		if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
		show('ok', L === 'en' ? 'Sending…' : 'جارٍ الإرسال…');
		var payload = { name: name, phone: phone, email: email, website: website, lang: L,
			items: items.map(function (p) { return { slug: p.slug, name: p.name, name_en: p.name_en, qty: p.qty }; }) };
		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DATA.nonce }, body: JSON.stringify(payload) })
			.then(function (res) {
				if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
				if (res.status === 401) { window.location.href = CUST.loginUrl || '/customer/'; return; }
				if (res.ok) {
					show('ok', L === 'en' ? 'Thank you! Your request has been received — our team will send you a quotation soon.' : 'شكراً لك! تم استلام طلبك — بيرسل لك فريقنا عرض السعر قريباً.');
					try { localStorage.setItem('osoul_quote_list', '[]'); } catch (e) {}
					['oql-name', 'oql-phone', 'oql-email'].forEach(function (id) { var e = document.getElementById(id); if (e) { e.value = ''; } });
					if (typeof window.oqlRender === 'function') { window.oqlRender(); }
					return;
				}
				res.json().then(function (j) { show('err', (j && j.message) ? j.message : (L === 'en' ? 'Something went wrong. Please try again.' : 'حدث خطأ، حاول مرة أخرى.')); })
					.catch(function () { show('err', L === 'en' ? 'Something went wrong. Please try again.' : 'حدث خطأ، حاول مرة أخرى.'); });
			})
			.catch(function () { if (btn) { btn.disabled = false; btn.style.opacity = '1'; } show('err', L === 'en' ? 'Network error. Please try again.' : 'تعذّر الاتصال، حاول مرة أخرى.'); });
	};
})();

/* Login gate for the quote list: logged-in customers send with one click (their
   details come from the account); guests get a login button instead of the form. */
(function () {
	function gate() {
		var C = window.osoulCustomer || {};
		var rfq = document.querySelector('.oql-rfq');
		if (!rfq) { return; }
		var ins = ['oql-name', 'oql-phone', 'oql-email'].map(function (id) { return document.getElementById(id); });
		var en = (function () { try { return (localStorage.getItem('osoul_lang') || 'ar') === 'en'; } catch (e) { return false; } })();
		if (C.loggedIn) {
			ins.forEach(function (e) { if (e) { e.style.display = 'none'; e.removeAttribute('required'); } });
			if (!document.getElementById('oql-cust-note')) {
				var n = document.createElement('div');
				n.id = 'oql-cust-note';
				n.style.cssText = 'font-size:13px;color:#0d5a2a;background:#eef6ef;border:1px solid #cdeacf;border-radius:8px;padding:9px 12px;text-align:center';
				n.textContent = (en ? 'Signed in as ' : 'مسجّل باسم ') + (C.name || '') + (en ? ' — just press Send' : ' — اضغط إرسال وخلاص');
				rfq.insertBefore(n, rfq.firstChild);
			}
		} else {
			ins.forEach(function (e) { if (e) { e.style.display = 'none'; } });
			var btn = document.querySelector('.oql-submit-btn');
			if (btn) {
				btn.textContent = en ? 'Sign in to send your request' : 'سجّل دخولك لإرسال الطلب';
				btn.onclick = function () { window.location.href = C.loginUrl || '/customer/'; };
			}
		}
	}
	if (document.readyState !== 'loading') { gate(); } else { document.addEventListener('DOMContentLoaded', gate); }
})();

/* Cache-proof drawer: editable quantity + bilingual placeholders that follow the
   language toggle. Overrides the cached osoul.js drawer (runs after it). */
(function () {
	var KEY = 'osoul_quote_list';
	function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
	function save(l) { try { localStorage.setItem(KEY, JSON.stringify(l)); } catch (e) {} }
	function lang() { try { return localStorage.getItem('osoul_lang') || 'ar'; } catch (e) { return 'ar'; } }
	function syncBadges() {
		var t = load().reduce(function (s, p) { return s + (parseInt(p.qty, 10) || 0); }, 0);
		['oql-badge', 'oql-header-badge'].forEach(function (id) { var b = document.getElementById(id); if (b) { b.textContent = t; b.style.display = t > 0 ? 'flex' : 'none'; } });
		var c = document.getElementById('oql-total-count'); if (c) { c.textContent = t; }
	}
	function render() {
		var list = load(), el = document.getElementById('oql-list'), empty = document.getElementById('oql-empty-state'), footer = document.getElementById('oql-footer-actions');
		syncBadges();
		if (!list.length) { if (empty) empty.style.display = ''; if (el) el.innerHTML = ''; if (footer) footer.style.display = 'none'; return; }
		if (empty) empty.style.display = 'none'; if (footer) footer.style.display = ''; if (!el) return;
		var L = lang();
		el.innerHTML = list.map(function (p, i) {
			return '<div class="oql-item">' +
				'<div class="oql-item-img"><img src="' + p.img + '" alt=""></div>' +
				'<div class="oql-item-info">' +
					'<div class="oql-item-cat">' + (L === 'en' ? (p.group_en || '') : (p.group_ar || '')) + '</div>' +
					'<div class="oql-item-name">' + (L === 'en' ? (p.name_en || p.name) : p.name) + '</div>' +
					'<div class="oql-item-qty">' +
						'<button class="oql-qty-btn" onclick="oqlQty(' + i + ',-1)">−</button>' +
						'<input class="oql-qty-input" type="number" min="1" value="' + p.qty + '" onchange="oqlSetQty(' + i + ',this.value)" onfocus="this.select()" style="width:70px;text-align:center;border:1px solid rgba(13,31,60,.18);border-radius:5px;padding:5px;font-family:\'IBM Plex Sans\',\'IBM Plex Sans Arabic\',sans-serif;font-weight:700;font-size:14px;min-height:32px">' +
						'<button class="oql-qty-btn" onclick="oqlQty(' + i + ',1)">+</button>' +
					'</div>' +
				'</div>' +
				'<button class="oql-item-del" onclick="oqlRemove(' + i + ')" aria-label="del"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>' +
			'</div>';
		}).join('');
	}
	window.oqlRender = render;
	window.oqlQty = function (i, d) { var l = load(); if (!l[i]) return; l[i].qty = (parseInt(l[i].qty, 10) || 1) + d; if (l[i].qty < 1) l.splice(i, 1); save(l); render(); };
	window.oqlSetQty = function (i, v) { var l = load(); if (!l[i]) return; v = parseInt(v, 10); if (isNaN(v) || v < 1) v = 1; l[i].qty = v; save(l); render(); };
	window.oqlRemove = function (i) { var l = load(); l.splice(i, 1); save(l); render(); };
	window.oqlClearAll = function () { save([]); render(); };
	function setPlaceholders() {
		var L = lang();
		var map = { 'oql-name': { ar: 'الاسم', en: 'Name' }, 'oql-phone': { ar: 'رقم الجوال', en: 'Phone' }, 'oql-email': { ar: 'البريد الإلكتروني', en: 'Email' } };
		Object.keys(map).forEach(function (id) { var e = document.getElementById(id); if (e) { e.placeholder = map[id][L === 'en' ? 'en' : 'ar']; } });
	}
	function boot() {
		render(); setPlaceholders();
		var orig = window.osoulSwitchLang;
		if (typeof orig === 'function' && !orig.__oqlWrapped) {
			window.osoulSwitchLang = function (l) { orig(l); render(); setPlaceholders(); };
			window.osoulSwitchLang.__oqlWrapped = true;
		}
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
</script>
		<?php
	}
}

/**
 * Certificate lightbox container.
 */
add_action( 'wp_footer', 'osoul_cert_lightbox', 96 );
if ( ! function_exists( 'osoul_cert_lightbox' ) ) {
	function osoul_cert_lightbox() {
		?>
<div id="cert-lightbox-overlay" class="cert-lightbox" onclick="certLbClose(event)">
	<button class="cert-lightbox-close" onclick="certLbClose(event)" aria-label="إغلاق"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
	<img id="cert-lightbox-img" class="cert-lightbox-img" src="" alt="" onclick="event.stopPropagation()">
</div>
		<?php
	}
}
