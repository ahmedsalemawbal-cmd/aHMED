<?php
/**
 * Favicon / site icon — make the brand icon reliably appear next to the site
 * in Google results and browser tabs.
 *
 * Why this module exists
 * ----------------------
 * The whole front-end is injected by this plugin over a near-blank theme, and
 * the favicon was left entirely to WordPress' "Site Icon" feature. That caused
 * the icon to go missing from Google for two reasons:
 *
 *   1. No guaranteed <link rel="icon">. If the Site Icon is unset — or a
 *      performance/security layer strips core's output — the page ships with
 *      no favicon tag at all, so Google falls back to the generic globe.
 *   2. A non-ASCII icon URL. The uploaded icon is named in Arabic
 *      (…/cropped-cropped-شعار-اصول.ai_.png). Google's favicon fetcher is far
 *      more reliable with a plain ASCII URL; a percent-encoded non-ASCII path
 *      is a well-known reason a favicon silently never shows.
 *
 * What it does
 * ------------
 *   - Emits one clean, complete favicon <link> set in <head> on every page
 *     (icon + apple-touch-icon + shortcut icon + msapplication tile +
 *     theme-color), and stands WordPress' own site-icon output down so there
 *     is exactly one, well-formed set.
 *   - Adds a Site Settings field ("Site favicon") so an admin can point it at
 *     a clean, square, ASCII-named PNG — the recommended, fully-reliable fix.
 *   - Answers the classic /favicon.ico probe (browsers and crawlers request it
 *     directly) by redirecting it to the resolved icon.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Resolve the URL of the square brand icon to use as the favicon.
 *
 * Priority:
 *   1. Site Settings → "favicon"  (admin can paste a clean ASCII square PNG).
 *   2. WordPress core Site Icon    (if one is configured).
 *   3. The existing square "logo_icon" setting.
 *
 * Filter `osoul_favicon_url` to override programmatically.
 *
 * @return string Icon URL, or '' when none is configured.
 */
if ( ! function_exists( 'osoul_favicon_url' ) ) {
	function osoul_favicon_url() {
		$url = osoul_opt( 'favicon' );

		if ( '' === $url && function_exists( 'get_site_icon_url' ) ) {
			$url = (string) get_site_icon_url( 512 );
		}
		if ( '' === $url ) {
			$url = osoul_opt( 'logo_icon' );
		}

		return (string) apply_filters( 'osoul_favicon_url', $url );
	}
}

/**
 * Emit the favicon markup in <head>, and stand WordPress' own site-icon output
 * down so exactly one clean, ASCII-friendly set of tags is shipped.
 */
add_action( 'wp_head', 'osoul_favicon_head', 2 );
if ( ! function_exists( 'osoul_favicon_head' ) ) {
	function osoul_favicon_head() {
		$icon = osoul_favicon_url();
		if ( '' === $icon ) {
			return; // Nothing configured — leave WordPress' default behaviour alone.
		}

		// We ship our own complete set; drop core's duplicate (runs at 99).
		remove_action( 'wp_head', 'wp_site_icon', 99 );

		$icon = esc_url( $icon );
		$ext  = strtolower( (string) pathinfo( (string) wp_parse_url( $icon, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

		if ( 'svg' === $ext ) {
			echo '<link rel="icon" type="image/svg+xml" href="' . $icon . '">' . "\n";
			echo '<link rel="icon" sizes="any" href="' . $icon . '">' . "\n";
		} else {
			$type = ( 'ico' === $ext ) ? 'image/x-icon' : 'image/png';
			echo '<link rel="icon" href="' . $icon . '" sizes="any">' . "\n";
			echo '<link rel="icon" type="' . esc_attr( $type ) . '" sizes="32x32" href="' . $icon . '">' . "\n";
			echo '<link rel="icon" type="' . esc_attr( $type ) . '" sizes="192x192" href="' . $icon . '">' . "\n";
		}

		echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $icon . '">' . "\n";
		echo '<link rel="shortcut icon" href="' . $icon . '">' . "\n";
		echo '<meta name="msapplication-TileImage" content="' . $icon . '">' . "\n";
		echo '<meta name="theme-color" content="#0d1f3c">' . "\n";
	}
}

/**
 * Resolve the classic /favicon.ico probe to the brand icon.
 *
 * On a blank/near-empty theme WordPress may answer /favicon.ico with nothing,
 * yet browsers and crawlers still request it directly. Point it at the icon.
 * Runs on `template_redirect`, before core's own `do_faviconico` handler.
 */
add_action( 'template_redirect', 'osoul_favicon_ico_redirect' );
if ( ! function_exists( 'osoul_favicon_ico_redirect' ) ) {
	function osoul_favicon_ico_redirect() {
		if ( '/favicon.ico' !== osoul_current_url( true ) ) {
			return;
		}
		$icon = osoul_favicon_url();
		if ( '' === $icon ) {
			return; // Let WordPress' default favicon handling run.
		}
		// Never redirect /favicon.ico onto itself.
		if ( trailingslashit( home_url() ) . 'favicon.ico' === $icon || '/favicon.ico' === $icon ) {
			return;
		}
		wp_redirect( $icon, 302 );
		exit;
	}
}
