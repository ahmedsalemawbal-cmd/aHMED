<?php
/**
 * أيقونة الموقع (favicon) — يُخرج مجموعة روابط نظيفة في <head>.
 * الأولوية: إعداد «favicon» → إعداد «logo» → أيقونة SVG افتراضية (مصحف).
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * عنوان أيقونة الموقع (قابل للتعديل عبر فلتر falak_favicon_url).
 */
function falak_favicon_url() {
	$icon = falak_opt( 'favicon' );
	if ( ! $icon ) {
		$icon = falak_opt( 'logo' );
	}
	if ( ! $icon ) {
		// أيقونة SVG افتراضية بلون أخضر (data-URI).
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
			. '<rect width="64" height="64" rx="14" fill="#0E4D33"/>'
			. '<path d="M18 16v32a5 5 0 0 1 5-4h24V12H23a5 5 0 0 0-5 4z" fill="none" stroke="#C6A15B" stroke-width="3" stroke-linejoin="round"/>'
			. '<path d="M32 20v16M25 28h14" stroke="#1F9D5A" stroke-width="3" stroke-linecap="round"/>'
			. '</svg>';
		$icon = 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}
	return apply_filters( 'falak_favicon_url', $icon );
}

add_action( 'wp_head', 'falak_favicon_head', 1 );
function falak_favicon_head() {
	$icon = falak_favicon_url();
	if ( ! $icon ) {
		return;
	}
	echo '<link rel="icon" href="' . esc_url( $icon ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '">' . "\n";
	echo '<meta name="theme-color" content="#0E4D33">' . "\n";
}

// منع ووردبريس من طباعة أيقونته المكررة (نتكفّل نحن).
add_action( 'init', function () {
	remove_action( 'wp_head', 'wp_site_icon', 99 );
} );
