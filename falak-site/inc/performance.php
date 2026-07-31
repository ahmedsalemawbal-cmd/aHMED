<?php
/**
 * تحميل الأصول (CSS/JS)، خطوط جوجل، تلميحات الموارد، وتنظيف الحشو.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'falak_enqueue_assets' );
function falak_enqueue_assets() {
	// خطوط جوجل: Tajawal (واجهة) + Amiri (عناوين مصحفية).
	add_action( 'wp_head', 'falak_font_preconnect', 1 );
	wp_enqueue_style(
		'falak-fonts',
		'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;700;800&display=swap',
		array(),
		null
	);

	$css_path = FALAK_DIR . 'assets/css/falak.css';
	$js_path  = FALAK_DIR . 'assets/js/falak.js';

	wp_enqueue_style(
		'falak-main',
		FALAK_URL . 'assets/css/falak.css',
		array( 'falak-fonts' ),
		is_readable( $css_path ) ? (string) filemtime( $css_path ) : FALAK_VERSION
	);

	// متغيرات الأنماط الزخرفية (data-URIs مولّدة من PHP).
	$vars = ':root{'
		. '--fk-pattern:url("' . falak_pattern_data_uri( '1F9D5A', '0.5' ) . '");'
		. '--fk-pattern-gold:url("' . falak_pattern_data_uri( 'C6A15B', '1' ) . '");'
		. '--fk-pattern-light:url("' . falak_pattern_data_uri( 'FFFFFF', '1' ) . '");'
		. '}';
	wp_add_inline_style( 'falak-main', $vars );

	wp_enqueue_script(
		'falak-main',
		FALAK_URL . 'assets/js/falak.js',
		array(),
		is_readable( $js_path ) ? (string) filemtime( $js_path ) : FALAK_VERSION,
		true
	);

	// بيانات للجافاسكربت (endpoint التسجيل + nonce + واتساب).
	wp_localize_script(
		'falak-main',
		'falakData',
		array(
			'rest'     => esc_url_raw( rest_url( 'falak/v1/enroll' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'whatsapp' => falak_wa_number(),
		)
	);
}

/**
 * preconnect لخطوط جوجل (أداء).
 */
function falak_font_preconnect() {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}

/**
 * إزالة الحشو غير الضروري (إيموجي، ?ver من موارد جوجل).
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
} );

/**
 * إبقاء ?ver على أصولنا (للـ cache-busting) وإزالته من روابط جوجل فقط ليس ضروريًا؛
 * نتركه بسيطًا.
 */
