<?php
/**
 * Quotation system — data layer.
 *
 * Stores each customer quote request as an `osoul_quote` post, defines the
 * pipeline stages, a dedicated "quote agent" role, and the helpers shared by
 * the dashboard and the public quote page (numbering, secure token, totals).
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pipeline stages (key => bilingual label + colour).
 *
 * @return array<string,array{ar:string,en:string,color:string}>
 */
function osoul_quote_stages() {
	return array(
		'new'         => array( 'ar' => 'جديد', 'en' => 'New', 'ur' => 'نیا', 'color' => '#2563eb' ),
		'pricing'     => array( 'ar' => 'قيد التسعير', 'en' => 'Pricing', 'ur' => 'قیمت کاری', 'color' => '#d97706' ),
		'sent'        => array( 'ar' => 'عرض مُرسل', 'en' => 'Quote Sent', 'ur' => 'کوٹیشن بھیجا گیا', 'color' => '#7c3aed' ),
		'negotiation' => array( 'ar' => 'تفاوض', 'en' => 'Negotiation', 'ur' => 'گفت و شنید', 'color' => '#0891b2' ),
		'won'         => array( 'ar' => 'مقبول', 'en' => 'Won', 'ur' => 'منظور', 'color' => '#16a34a' ),
		'lost'        => array( 'ar' => 'مرفوض', 'en' => 'Lost', 'ur' => 'مسترد', 'color' => '#dc2626' ),
	);
}

/**
 * Capability that gates the quotes dashboard.
 */
function osoul_quotes_cap() {
	return 'manage_osoul_quotes';
}

/**
 * Register the quote post type (private — never public on the front-end;
 * the public view is served through a token URL by quotes-public.php).
 */
add_action( 'init', function () {
	register_post_type( 'osoul_quote', array(
		'labels'             => array(
			'name'          => __( 'Quotes', 'osoul' ),
			'singular_name' => __( 'Quote', 'osoul' ),
		),
		'public'             => false,
		'show_ui'            => false,
		'show_in_menu'       => false,
		'publicly_queryable' => false,
		'exclude_from_search'=> true,
		'rewrite'            => false,
		'supports'           => array( 'title' ),
	) );
} );

/**
 * Register the dedicated agent role + grant the capability to administrators.
 * Called on plugin activation (and defensively on init if missing).
 */
function osoul_register_quote_role() {
	$cap = osoul_quotes_cap();
	add_role( 'osoul_agent', __( 'موظف عروض الأسعار', 'osoul' ), array(
		'read'  => true,
		$cap    => true,
	) );
	$role = get_role( 'osoul_agent' );
	if ( $role && ! $role->has_cap( $cap ) ) {
		$role->add_cap( $cap );
	}
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( $cap ) ) {
		$admin->add_cap( $cap );
	}
}
add_action( 'init', function () {
	// Make sure the cap exists for admins even if the plugin was updated.
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( osoul_quotes_cap() ) ) {
		osoul_register_quote_role();
	}
}, 5 );

/**
 * Generate the next sequential quote number, e.g. OSB-2026-0001.
 *
 * @return string
 */
function osoul_next_quote_number() {
	$prefix = osoul_opt( 'quote_prefix' );
	$prefix = $prefix ? $prefix : 'OSB';
	$year   = gmdate( 'Y' );
	$key    = 'osoul_quote_counter_' . $year;

	// The counter now resets each year (OSB-2026-0001 → OSB-2027-0001). One-time
	// carryover: the FIRST year-scoped counter continues from the old global
	// counter, so switching mid-year can never reuse a number already issued.
	if ( false === get_option( $key, false ) && ! get_option( 'osoul_quote_counter_migrated', false ) ) {
		add_option( $key, (int) get_option( 'osoul_quote_counter', 0 ), '', 'no' );
		update_option( 'osoul_quote_counter_migrated', 1, false );
	}

	$counter = osoul_atomic_option_increment( $key );
	return sprintf( '%s-%s-%04d', $prefix, $year, $counter );
}

/**
 * A hard-to-guess token for the public quote URL.
 *
 * @return string
 */
function osoul_quote_token() {
	if ( function_exists( 'random_bytes' ) ) {
		return bin2hex( random_bytes( 16 ) );
	}
	return wp_generate_password( 32, false, false );
}

/**
 * Sanitise + normalise a list of quote line items.
 *
 * @param mixed $items
 * @return array<int,array{slug:string,name:string,name_en:string,qty:int,unit_price:float}>
 */
function osoul_sanitize_quote_items( $items ) {
	$out = array();
	if ( ! is_array( $items ) ) {
		return $out;
	}
	foreach ( $items as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$out[] = array(
			'slug'       => sanitize_title( $row['slug'] ?? '' ),
			'name'       => sanitize_text_field( $row['name'] ?? '' ),
			'name_en'    => sanitize_text_field( $row['name_en'] ?? ( $row['name'] ?? '' ) ),
			'qty'        => max( 1, (int) ( $row['qty'] ?? 1 ) ),
			'unit_price' => isset( $row['unit_price'] ) ? max( 0, (float) $row['unit_price'] ) : 0.0,
		);
	}
	return $out;
}

/**
 * Compute the money totals for a quote.
 *
 * Prices are entered EXCLUSIVE of VAT; 15% is added on top of (subtotal − discount).
 *
 * @param array $items    Line items with qty + unit_price.
 * @param float $discount Flat discount amount (in SAR).
 * @param float $vat_rate VAT percentage (default 15).
 * @return array{subtotal:float,discount:float,taxable:float,vat:float,total:float}
 */
function osoul_quote_totals( $items, $discount = 0, $vat_rate = 15 ) {
	$subtotal = 0;
	foreach ( (array) $items as $it ) {
		$subtotal += ( (float) ( $it['unit_price'] ?? 0 ) ) * ( (int) ( $it['qty'] ?? 0 ) );
	}
	$discount = min( max( 0, (float) $discount ), $subtotal );
	$taxable  = $subtotal - $discount;
	$vat      = round( $taxable * ( (float) $vat_rate / 100 ), 2 );
	$total    = round( $taxable + $vat, 2 );
	return array(
		'subtotal' => round( $subtotal, 2 ),
		'discount' => round( $discount, 2 ),
		'taxable'  => round( $taxable, 2 ),
		'vat'      => $vat,
		'total'    => $total,
	);
}

/**
 * Format an amount as SAR with two decimals (Latin digits).
 *
 * @param float $amount
 * @return string
 */
function osoul_money( $amount ) {
	return number_format( (float) $amount, 2, '.', ',' );
}

/**
 * Look up a quote post by its public token.
 *
 * @param string $token
 * @return WP_Post|null
 */
function osoul_get_quote_by_token( $token ) {
	$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
	if ( ! $token ) {
		return null;
	}
	$q = new WP_Query( array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'meta_key'       => '_osoul_token',
		'meta_value'     => $token,
	) );
	return $q->posts ? $q->posts[0] : null;
}
