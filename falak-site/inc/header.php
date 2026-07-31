<?php
/**
 * ترويسة الموقع — تُطبع عند فتح <body>.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * روابط التنقّل الرئيسية.
 */
function falak_nav_items() {
	$home = home_url( '/' );
	return array(
		array( 'label' => 'الرئيسية', 'url' => $home, 'icon' => 'compass' ),
		array( 'label' => 'من نحن', 'url' => $home . 'about/', 'icon' => 'book' ),
		array( 'label' => 'البرامج', 'url' => $home . 'programs/', 'icon' => 'quran' ),
		array( 'label' => 'المعلمون', 'url' => $home . 'teachers/', 'icon' => 'users' ),
		array( 'label' => 'المعرض', 'url' => $home . 'gallery/', 'icon' => 'image' ),
		array( 'label' => 'الأسئلة الشائعة', 'url' => $home . 'faq/', 'icon' => 'sparkle' ),
		array( 'label' => 'تواصل معنا', 'url' => $home . 'contact/', 'icon' => 'phone' ),
	);
}

/**
 * شعار المدرسة (صورة إن وُجدت، وإلا أيقونة).
 */
function falak_logo_mark( $class = 'fkh-logo' ) {
	$logo = falak_opt( 'logo' );
	echo '<span class="' . esc_attr( $class ) . '">';
	if ( $logo ) {
		echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( falak_opt( 'school_name' ) ) . '">';
	} else {
		falak_icon( 'quran', 30 );
	}
	echo '</span>';
}

add_action( 'wp_body_open', 'falak_render_header', 1 );
if ( ! function_exists( 'falak_render_header' ) ) {
	function falak_render_header() {
		$home     = home_url( '/' );
		$nav      = falak_nav_items();
		$enroll   = $home . 'enroll/';
		$name     = falak_opt( 'school_name' );
		$short    = falak_opt( 'school_short' );
		?>
<header id="falak-header" class="falak-wrap">
	<div class="fkh-bar">
		<a href="<?php echo esc_url( $home ); ?>" class="fkh-brand" aria-label="<?php echo esc_attr( $name ); ?>">
			<?php falak_logo_mark(); ?>
			<span class="fkh-name">
				<b><?php echo esc_html( $short ? $short : $name ); ?></b>
				<small>لتحفيظ القرآن الكريم</small>
			</span>
		</a>

		<nav aria-label="القائمة الرئيسية">
			<ul class="fkh-nav">
				<?php foreach ( $nav as $item ) : ?>
					<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="fkh-actions">
			<a href="<?php echo esc_url( $enroll ); ?>" class="fkh-cta"><?php falak_icon( 'sparkle', 17 ); ?> سجّل الآن</a>
			<button id="falak-burger" class="fkh-burger" aria-label="فتح القائمة" aria-expanded="false" aria-controls="falak-drawer"><?php falak_icon( 'menu', 24 ); ?></button>
		</div>
	</div>
</header>

<div id="falak-drawer" class="fkh-drawer falak-wrap" role="dialog" aria-modal="true" aria-label="قائمة التنقّل">
	<div class="fkh-drawer-ov"></div>
	<div class="fkh-drawer-panel">
		<div class="fkh-drawer-top">
			<b><?php echo esc_html( $short ? $short : $name ); ?></b>
			<button class="fkh-drawer-close" aria-label="إغلاق القائمة"><?php falak_icon( 'close', 22 ); ?></button>
		</div>
		<ul class="fkh-drawer-nav">
			<?php foreach ( $nav as $item ) : ?>
				<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?><?php falak_icon( $item['icon'], 18 ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<div class="fkh-drawer-cta">
			<a href="<?php echo esc_url( $enroll ); ?>" class="p"><?php falak_icon( 'sparkle', 18 ); ?> سجّل الآن</a>
			<a href="<?php echo esc_url( falak_wa_url( 'السلام عليكم، أرغب في الاستفسار عن التسجيل بمدرسة الفلك المنير' ) ); ?>" class="s" target="_blank" rel="noopener"><?php falak_icon( 'whatsapp', 18 ); ?> واتساب</a>
		</div>
	</div>
</div>
		<?php
	}
}
