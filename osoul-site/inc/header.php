<?php
/**
 * Site header markup.
 *
 * Styling lives in assets/css/osoul.css (#osoul-header …); behaviour
 * (scroll state, burger, submenu toggles, badge sync) lives in
 * assets/js/osoul.js. This file only emits markup.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', 'osoul_header_output', 1 );
if ( ! function_exists( 'osoul_header_output' ) ) {
	function osoul_header_output() {
		$home   = home_url( '/' );
		$phone1 = osoul_opt( 'phone_primary' );
		$email  = osoul_opt( 'email' );
		$wa     = osoul_wa_url();
		$nav    = array(
			array( 'ar' => 'الرئيسية', 'en' => 'Home', 'url' => $home, 'sub' => array() ),
			array( 'ar' => 'من نحن', 'en' => 'About Us', 'url' => $home . 'about-us/', 'sub' => array() ),
			array( 'ar' => 'المنتجات', 'en' => 'Products', 'url' => $home . 'products/', 'sub' => array(
				array( 'ar' => 'الأبواب بأنواعها الكاملة', 'en' => 'All Door Types', 'url' => $home . 'doors/' ),
				array( 'ar' => 'بروفايلات الجبسوم والسمنت بورد', 'en' => 'Gypsum Board Profiles', 'url' => $home . 'gypsum/' ),
				array( 'ar' => 'بروفايلات وإكسسوارات التثبيت', 'en' => 'Strut Fixing Accessories', 'url' => $home . 'strut/' ),
			) ),
			array( 'ar' => 'خدماتنا', 'en' => 'Services', 'url' => $home . 'services/', 'sub' => array() ),
			array( 'ar' => 'المشاريع', 'en' => 'Projects', 'url' => $home . 'projects/', 'sub' => array() ),
			array( 'ar' => 'المدونة', 'en' => 'Blog', 'url' => $home . 'blog/', 'sub' => array() ),
		);
		?>
<div class="osh-mobile-overlay" id="osh-overlay" onclick="oshCloseMenu()"></div>
<div class="osh-mobile-menu" id="osh-mobile-menu">
<ul class="osh-mob-nav">
<?php foreach ( $nav as $i => $item ) : ?>
<li>
<a href="<?php echo esc_url( $item['url'] ); ?>"<?php if ( $item['sub'] ) : ?> onclick="oshToggleSub(event,<?php echo (int) $i; ?>)"<?php endif; ?>>
<span><?php osoul_bilingual( $item['ar'], $item['en'] ); ?></span>
<?php if ( $item['sub'] ) : ?><svg viewBox="0 0 24 24" id="osh-mob-arrow-<?php echo (int) $i; ?>"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
</a>
<?php if ( $item['sub'] ) : ?>
<ul class="osh-mob-sub" id="osh-mob-sub-<?php echo (int) $i; ?>">
<?php foreach ( $item['sub'] as $sub ) : ?>
<li><a href="<?php echo esc_url( $sub['url'] ); ?>"><?php osoul_bilingual( $sub['ar'], $sub['en'] ); ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<div class="osh-mob-footer">
<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="osh-mob-cta"><?php osoul_bilingual( 'تواصل معنا الآن', 'Contact Us Now' ); ?></a>
<div class="osh-mob-contact">
<a href="tel:<?php echo esc_attr( osoul_tel( $phone1 ) ); ?>"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 01.12 2.18 2 2 0 012.11 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg><?php echo esc_html( $phone1 ); ?></a>
<a href="mailto:<?php echo esc_attr( $email ); ?>"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><?php echo esc_html( $email ); ?></a>
</div>
</div>
</div>

<header id="osoul-header">
<div class="osh-top">
<div class="osh-top-left">
<a href="tel:<?php echo esc_attr( osoul_tel( $phone1 ) ); ?>" class="osh-top-item"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 01.12 2.18 2 2 0 012.11 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg><?php echo esc_html( $phone1 ); ?></a>
<a href="mailto:<?php echo esc_attr( $email ); ?>" class="osh-top-item"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><?php echo esc_html( $email ); ?></a>
<span class="osh-top-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg><?php osoul_bilingual( osoul_opt( 'hours_ar' ), osoul_opt( 'hours_en' ) ); ?></span>
</div>
<div class="osh-top-right">
<div class="osh-top-social" style="display:flex;gap:4px">
<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<a href="<?php echo esc_url( osoul_opt( 'instagram' ) ); ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
<a href="<?php echo esc_url( osoul_opt( 'linkedin' ) ); ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
</div>
<div class="osh-top-sep"></div>
<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="osh-top-cta"><?php osoul_bilingual( 'طلب عرض سعر', 'Get a Quote' ); ?></a>
</div>
</div>
<div class="osh-main">
<a href="<?php echo esc_url( $home ); ?>" class="osh-logo"><?php echo osoul_brand_logo( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with esc_* internally ?></a>
<ul class="osh-nav">
<?php foreach ( $nav as $item ) : ?>
<li<?php echo ! empty( $item['sub'] ) ? ' class="has-sub"' : ''; ?>>
<a href="<?php echo esc_url( $item['url'] ); ?>">
<?php osoul_bilingual( $item['ar'], $item['en'] ); ?>
<?php if ( ! empty( $item['sub'] ) ) : ?><svg class="osh-arrow" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
</a>
<?php if ( ! empty( $item['sub'] ) ) : ?>
<ul class="osh-dropdown">
<?php foreach ( $item['sub'] as $sub ) : ?>
<li><a href="<?php echo esc_url( $sub['url'] ); ?>"><?php osoul_bilingual( $sub['ar'], $sub['en'] ); ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<div class="osh-actions">
<button id="oql-trigger" onclick="oqlOpenDrawer()" aria-label="قائمة العرض" style="background:#eceef0;color:#00344F;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:12px;font-weight:700;letter-spacing:.5px;padding:8px 16px;border-radius:20px;border:1.5px solid #0074A4;cursor:pointer;display:inline-flex;align-items:center;gap:7px;position:relative;margin-left:8px">
    <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:#00344F;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0"><path d="M9 2L3 7v13a1 1 0 001 1h16a1 1 0 001-1V7l-6-5"/><path d="M16 10a4 4 0 01-8 0"/></svg>
    <?php osoul_bilingual( 'قائمة العرض', 'Quote List' ); ?>
    <span id="oql-header-badge" style="display:none;position:absolute;top:-7px;left:-7px;background:#0074A4;color:#fff;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;align-items:center;justify-content:center;line-height:1">0</span>
</button>
<?php if ( function_exists( 'osoul_is_customer' ) && osoul_is_customer() ) :
	$osh_first = trim( explode( ' ', (string) wp_get_current_user()->display_name )[0] );
	?>
<a href="<?php echo esc_url( osoul_customer_url() ); ?>" class="osh-cta-btn"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-3.5 3.6-6 8-6s8 2.5 8 6"/></svg><?php osoul_bilingual( 'مرحباً ' . $osh_first, 'Hi ' . $osh_first ); ?></a>
<?php else : ?>
<a href="<?php echo esc_url( function_exists( 'osoul_customer_url' ) ? osoul_customer_url() : $home . 'contact/' ); ?>" class="osh-cta-btn"><svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg><?php osoul_bilingual( 'دخول العملاء', 'Customer Login' ); ?></a>
<?php endif; ?>
<button class="osh-burger" id="osh-burger" onclick="oshToggleMenu()" aria-label="Menu"><span></span><span></span><span></span></button>
</div>
</div>
</header>
		<?php
	}
}
