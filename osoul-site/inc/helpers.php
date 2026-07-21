<?php
/**
 * Shared helpers.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the current request URL safely.
 *
 * The legacy code echoed raw $_SERVER['HTTP_HOST'] / REQUEST_URI into canonical
 * and og:url tags — that is attacker-controllable (Host header injection) and a
 * known SEO-poisoning / cache-poisoning vector. We rebuild from home_url() (the
 * trusted, admin-configured host) and only take the *path* from the request.
 *
 * @param bool $path_only When true, returns just the path component.
 * @return string Fully-qualified, escaped-safe URL on the site's own host.
 */
function osoul_current_url( $path_only = false ) {
	$request_path = isset( $_SERVER['REQUEST_URI'] )
		? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
		: '/';
	$request_path = '/' . ltrim( (string) $request_path, '/' );

	if ( $path_only ) {
		return $request_path;
	}

	// home_url() carries the trusted scheme + host configured in WP settings.
	return home_url( $request_path );
}

/**
 * Read the language preference from the cookie, sanitised.
 *
 * Only 'ar' or 'en' are ever returned; anything else falls back to 'ar'.
 *
 * @return string 'ar'|'en'
 */
function osoul_current_lang() {
	$lang = isset( $_COOKIE['osoul_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['osoul_lang'] ) ) : 'ar';
	return ( 'en' === $lang ) ? 'en' : 'ar';
}

/**
 * Resolve the product slug for the current request.
 *
 * Works even before the WP pages exist (URL parsing fallback), mirroring the
 * legacy behaviour but centralised so every module shares one implementation.
 *
 * @return string Trimmed slug (may be empty).
 */
function osoul_request_slug() {
	$uri = trim( (string) osoul_current_url( true ), '/' );

	$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $home_path && 0 === strpos( $uri, $home_path ) ) {
		$uri = ltrim( substr( $uri, strlen( $home_path ) ), '/' );
	}

	return trim( $uri, '/' );
}

/**
 * Convenience: cached accessor for the full product catalog.
 *
 * @return array
 */
function osoul_catalog() {
	static $catalog = null;
	if ( null === $catalog ) {
		$catalog = function_exists( 'osoul_get_all_products_catalog' )
			? osoul_get_all_products_catalog()
			: array();
	}
	return $catalog;
}

/**
 * Echo a bilingual <span> pair (AR + EN) the way the templates expect.
 *
 * Reduces the very repetitive
 *   <span data-lang="ar">..</span><span data-lang="en">..</span>
 * pattern that appears thousands of times in the legacy markup.
 *
 * @param string $ar Arabic text (will be escaped).
 * @param string $en English text (will be escaped).
 */
function osoul_bilingual( $ar, $en ) {
	echo '<span data-lang="ar">' . esc_html( $ar ) . '</span>';
	echo '<span data-lang="en">' . esc_html( $en ) . '</span>';
}

/**
 * Atomically increment a numeric option and return the NEW value.
 *
 * The old pattern — read with get_option(), add 1, write with update_option() —
 * is a classic race: two requests can read the same value and both write N+1,
 * handing two quotes the same number. This does the increment inside a single
 * MySQL statement (LAST_INSERT_ID) so the database serialises concurrent callers
 * and every caller gets a distinct value.
 *
 * @param string $option_name Option holding the integer counter.
 * @return int   The value AFTER incrementing (>= 1).
 */
function osoul_atomic_option_increment( $option_name ) {
	global $wpdb;
	// Make sure the row exists so the UPDATE can touch it (autoload off — it is
	// only read at issue time, never on every page).
	if ( false === get_option( $option_name, false ) ) {
		add_option( $option_name, 0, '', 'no' );
	}
	$ok = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID( option_value + 1 ) WHERE option_name = %s",
			$option_name
		)
	);
	if ( $ok ) {
		$n = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		if ( $n > 0 ) {
			return $n;
		}
	}
	// Fallback (raw query unavailable): non-atomic, but never stops issuing.
	$n = (int) get_option( $option_name, 0 ) + 1;
	update_option( $option_name, $n, false );
	return $n;
}

/**
 * Atomically increment a per-user numeric meta value and return the NEW value.
 * Same race-free technique as osoul_atomic_option_increment(), for user meta
 * (used by each partner's private quote counter).
 *
 * @param int    $user_id
 * @param string $meta_key Meta holding the integer counter.
 * @return int   The value AFTER incrementing (>= 1).
 */
function osoul_atomic_usermeta_increment( $user_id, $meta_key ) {
	global $wpdb;
	$user_id = (int) $user_id;
	$has     = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
			$user_id,
			$meta_key
		)
	);
	if ( null === $has ) {
		add_user_meta( $user_id, $meta_key, 0, true );
	}
	$ok = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->usermeta} SET meta_value = LAST_INSERT_ID( meta_value + 1 ) WHERE user_id = %d AND meta_key = %s",
			$user_id,
			$meta_key
		)
	);
	if ( $ok ) {
		$n = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $user_id, 'user_meta' );
		if ( $n > 0 ) {
			return $n;
		}
	}
	$n = (int) get_user_meta( $user_id, $meta_key, true ) + 1;
	update_user_meta( $user_id, $meta_key, $n );
	return $n;
}
