<?php
/**
 * Performance: asset loading, dequeuing bloat, resource hints.
 *
 * The legacy snippet printed ~1,500 lines of CSS and ~600 lines of JS *inline*
 * on every single page (no caching, no minification, re-downloaded each visit).
 * Here the shared/global CSS + JS live in real files that the browser can cache,
 * and we still expose the REST nonce the forms need.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue the global stylesheet + script and localise front-end data.
 */
add_action( 'wp_enqueue_scripts', function () {

	$css = OSOUL_DIR . 'assets/css/osoul.css';
	$js  = OSOUL_DIR . 'assets/js/osoul.js';

	// filemtime() as version → automatic cache-busting on every edit.
	wp_enqueue_style(
		'osoul',
		OSOUL_URL . 'assets/css/osoul.css',
		array(),
		is_readable( $css ) ? filemtime( $css ) : OSOUL_VERSION
	);

	wp_enqueue_script(
		'osoul',
		OSOUL_URL . 'assets/js/osoul.js',
		array(),
		is_readable( $js ) ? filemtime( $js ) : OSOUL_VERSION,
		true // in footer
	);

	// Everything the front-end JS needs to talk to the server safely.
	wp_localize_script( 'osoul', 'osoulData', array(
		'restUrl' => esc_url_raw( rest_url( 'osoul/v1/submit' ) ),
		'quoteUrl'=> esc_url_raw( rest_url( 'osoul/v1/quote-request' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'whatsapp'=> preg_replace( '/\D/', '', osoul_opt( 'whatsapp' ) ),
		'lang'    => osoul_current_lang(),
	) );
}, 20 );

/**
 * Load the Google Fonts once, with preconnect, as early as possible.
 * (The legacy code loaded this stylesheet two or three separate times.)
 */
add_action( 'wp_head', function () {
	?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="//fonts.googleapis.com">
<link rel="dns-prefetch" href="//fonts.gstatic.com">
<link rel="preconnect" href="https://osoulalbinaa.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
	<?php
}, 1 );

/**
 * Remove the WordPress emoji scripts/styles (~17 KB per page, unused here).
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );

/**
 * Drop unused Gutenberg block CSS (the site never uses the block editor styles).
 */
add_action( 'wp_print_styles', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
}, 100 );
