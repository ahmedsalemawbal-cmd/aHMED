<?php
/**
 * تذييل الموقع + الأزرار العائمة — يُطبع في wp_footer.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', 'falak_render_footer', 20 );
if ( ! function_exists( 'falak_render_footer' ) ) {
	function falak_render_footer() {
		$home    = home_url( '/' );
		$name    = falak_opt( 'school_name' );
		$tagline = falak_opt( 'tagline' );
		$ayah    = falak_opt( 'ayah' );
		$phone   = falak_opt( 'phone' );
		$email   = falak_opt( 'email' );
		$address = falak_opt( 'address' );
		$hours   = falak_opt( 'hours' );
		$socials = falak_social_links();
		$year    = gmdate( 'Y' );
		$nav     = falak_nav_items();
		?>
<footer id="falak-footer" class="falak-wrap">
	<div class="fkf-top">
		<div class="fkf-about">
			<div class="fkf-brand-row">
				<?php falak_logo_mark( 'fkf-logo' ); ?>
				<b><?php echo esc_html( $name ); ?></b>
			</div>
			<p class="fkf-desc"><?php echo esc_html( $tagline ); ?></p>
			<?php if ( $ayah ) : ?>
				<p class="fkf-ayah">﴿ <?php echo esc_html( $ayah ); ?> ﴾</p>
			<?php endif; ?>
		</div>

		<div class="fkf-col">
			<h4>روابط سريعة</h4>
			<ul>
				<?php foreach ( $nav as $item ) : ?>
					<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="fkf-col">
			<h4>الأقسام</h4>
			<ul>
				<li><a href="<?php echo esc_url( $home . 'programs/' ); ?>">قسم البنين</a></li>
				<li><a href="<?php echo esc_url( $home . 'programs/' ); ?>">قسم البنات</a></li>
				<li><a href="<?php echo esc_url( $home . 'enroll/' ); ?>">التسجيل</a></li>
				<li><a href="<?php echo esc_url( $home . 'about/' ); ?>">رؤيتنا ورسالتنا</a></li>
			</ul>
		</div>

		<div class="fkf-col">
			<h4>تواصل معنا</h4>
			<ul class="fkf-contact">
				<?php if ( $address ) : ?>
					<li><?php falak_icon( 'pin', 18 ); ?><span><?php echo esc_html( $address ); ?></span></li>
				<?php endif; ?>
				<?php if ( $phone ) : ?>
					<li><?php falak_icon( 'phone', 18 ); ?><a href="<?php echo esc_url( falak_tel_url() ); ?>" dir="ltr"><?php echo esc_html( $phone ); ?></a></li>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<li><?php falak_icon( 'mail', 18 ); ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></li>
				<?php endif; ?>
				<?php if ( $hours ) : ?>
					<li><?php falak_icon( 'clock', 18 ); ?><span><?php echo esc_html( $hours ); ?></span></li>
				<?php endif; ?>
			</ul>
			<?php if ( $socials ) : ?>
				<div class="fkf-social">
					<?php foreach ( $socials as $s ) : ?>
						<a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $s['label'] ); ?>" title="<?php echo esc_attr( $s['label'] ); ?>">
							<?php echo falak_social_icon_svg( $s['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput — SVG ثابت ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="fkf-bottom">
		© <?php echo esc_html( $year ); ?> — جميع الحقوق محفوظة لـ <b><?php echo esc_html( $name ); ?></b>
	</div>
</footer>

<!-- الأزرار العائمة -->
<div class="fk-float falak-wrap">
	<a href="#" id="falak-top" class="top" aria-label="العودة للأعلى"><?php falak_icon( 'chevron', 22, 'up' ); ?><span class="screen-reader-text"></span></a>
	<?php
	$fk_wa_msg = ( function_exists( 'falak_current_page_key' ) && 'enroll' === falak_current_page_key() )
		? 'السلام عليكم، شاهدت إعلانكم وأرغب بالاستفسار عن التسجيل في مدرسة الفلك المنير'
		: 'السلام عليكم، أرغب في الاستفسار عن مدرسة الفلك المنير';
	?>
	<a href="<?php echo esc_url( falak_wa_url( $fk_wa_msg ) ); ?>" class="wa" target="_blank" rel="noopener" aria-label="تواصل عبر واتساب"><?php falak_icon( 'whatsapp', 30 ); ?></a>
</div>
<style>#falak-top svg{transform:rotate(180deg)}</style>
		<?php
	}
}

/**
 * أيقونات وسائل التواصل (SVG بسيطة موحّدة الطراز).
 */
function falak_social_icon_svg( $name ) {
	$icons = array(
		'x'         => '<path d="M18 2h3l-7 8 8 12h-6l-5-6-5 6H2l8-9L2 2h6l4 5z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>',
		'youtube'   => '<rect x="2" y="5" width="20" height="14" rx="4"/><polygon points="10 9 15 12 10 15" fill="currentColor" stroke="none"/>',
		'snapchat'  => '<path d="M12 2c3 0 4.5 2.2 4.5 5 0 1.2-.2 2.4.3 3 .6.7 1.7.4 2.2 1 .3.5-.5 1-1.7 1.4-.5.2-.8.3-.7.8.3 1.3 2.4 2.6 2.4 3.2 0 .4-.9.5-1.6.6-.4.1-.6.2-.7.7-.1.4-.2.8-.7.8-.7 0-1.4-.5-2.6-.2-1 .3-1.8 1.4-3.1 1.4s-2.1-1.1-3.1-1.4c-1.2-.3-1.9.2-2.6.2-.5 0-.6-.4-.7-.8-.1-.5-.3-.6-.7-.7-.7-.1-1.6-.2-1.6-.6 0-.6 2.1-1.9 2.4-3.2.1-.5-.2-.6-.7-.8C3.3 12.5 2.5 12 2.8 11.5c.5-.6 1.6-.3 2.2-1 .5-.6.3-1.8.3-3C5.5 4.2 7 2 12 2z"/>',
		'telegram'  => '<path d="M21 4L3 11l5 2 2 6 3-4 5 4z"/>',
		'tiktok'    => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3-3.46V13a1.5 1.5 0 1 0 1 1.4V3h2c.2 1.7 1.4 3 3 3.2V9c-1.2 0-2.3-.4-3-1z"/>',
	);
	$inner = isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['x'];
	return '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
