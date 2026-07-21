<?php
/**
 * Security & hardening.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Remove version fingerprints (helps avoid targeted exploits of known WP versions).
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Strip ?ver= query strings off scripts/styles.
 * (Cosmetic + caching; kept from the original.)
 */
add_filter( 'script_loader_src', 'osoul_remove_query_ver', 15 );
add_filter( 'style_loader_src', 'osoul_remove_query_ver', 15 );
if ( ! function_exists( 'osoul_remove_query_ver' ) ) {
	function osoul_remove_query_ver( $src ) {
		if ( $src && false !== strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}
}

/**
 * Disable XML-RPC (a common brute-force / pingback-DDoS surface).
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Hide the WordPress admin toolbar from front-end accounts.
 *
 * Customers, partners, reps and branches are logged-in WP users, so WordPress
 * would show them the black admin bar while they browse the public site — which
 * looks wrong and leaks WP chrome. Anyone who can't edit content (i.e. every
 * portal/customer role, and plain subscribers) never needs it; real
 * administrators and editors keep it.
 */
add_filter( 'show_admin_bar', function ( $show ) {
	if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
		return false;
	}
	return $show;
} );

/**
 * Send a few low-risk security headers.
 *
 * Intentionally conservative: no CSP (the site relies on inline styles/scripts,
 * so a strict CSP would break it). These three are safe drop-ins.
 */
add_action( 'send_headers', function () {
	if ( is_admin() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
} );

/**
 * Prevent the "product slug == uploaded image name" attachment redirect.
 *
 * When a product slug (e.g. channel-fls) matches an uploaded file
 * (Channel-FLS.png), WP would 301 the visitor to the raw image. We cancel that.
 * Ported from the legacy snippet, but now reads the catalog via the shared accessor.
 */
add_action( 'template_redirect', 'osoul_fix_product_attachment', 0 );
if ( ! function_exists( 'osoul_fix_product_attachment' ) ) {
	function osoul_fix_product_attachment() {
		if ( ! is_attachment() ) {
			return;
		}
		global $post;
		if ( ! $post ) {
			return;
		}
		$catalog = osoul_catalog();
		if ( ! isset( $catalog[ $post->post_name ] ) ) {
			return;
		}
		remove_action( 'template_redirect', 'redirect_canonical' );
		add_filter( 'wp_redirect', function ( $loc ) {
			if ( is_string( $loc ) && false !== strpos( $loc, '/wp-content/uploads/' ) ) {
				return false;
			}
			return $loc;
		}, 1 );
	}
}

/**
 * Cancel canonical redirects for catalog product URLs.
 */
add_filter( 'redirect_canonical', 'osoul_cancel_product_canonical', 1, 2 );
if ( ! function_exists( 'osoul_cancel_product_canonical' ) ) {
	function osoul_cancel_product_canonical( $redirect_url, $requested_url ) {
		if ( ! $requested_url ) {
			return $redirect_url;
		}
		$catalog = osoul_catalog();
		$path    = trim( (string) wp_parse_url( (string) $requested_url, PHP_URL_PATH ), '/' );
		$hp      = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $hp && 0 === strpos( $path, $hp ) ) {
			$path = ltrim( substr( $path, strlen( $hp ) ), '/' );
		}
		$catalog_keys = array_keys( $catalog );
		if ( in_array( trim( $path, '/' ), $catalog_keys, true ) ) {
			return false;
		}
		return $redirect_url;
	}
}

/**
 * Treat catalog product URLs as valid pages even before the WP page exists,
 * so visitors never hit a 404 during initial rollout.
 */
add_action( 'wp', 'osoul_prevent_product_404' );
if ( ! function_exists( 'osoul_prevent_product_404' ) ) {
	function osoul_prevent_product_404() {
		if ( ! is_404() ) {
			return;
		}
		$catalog = osoul_catalog();
		$slug    = osoul_request_slug();
		if ( ! isset( $catalog[ $slug ] ) ) {
			return;
		}
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_page = true;
		status_header( 200 );
	}
}
