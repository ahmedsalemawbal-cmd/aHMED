<?php
/**
 * Customer-facing RFQ submission.
 *
 * The quote-list drawer now lets the visitor enter name / phone / email and
 * submit a real quote request, which is stored as an `osoul_quote` (stage
 * "new") and emailed to the team — instead of only opening WhatsApp.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST route.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'osoul/v1', '/quote-request', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_handle_quote_request',
		// Customers must be logged in (falls back to open if the customer module
		// is absent, so the site keeps working during a partial deploy).
		'permission_callback' => function_exists( 'osoul_customer_rfq_perm' ) ? 'osoul_customer_rfq_perm' : '__return_true',
	) );
} );

/**
 * Handle a customer quote request.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function osoul_handle_quote_request( WP_REST_Request $request ) {
	// Nonce.
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'osoul_bad_nonce', __( 'Security check failed. Please refresh and try again.', 'osoul' ), array( 'status' => 403 ) );
	}
	// Honeypot.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	// Rate limit (reuse the leads limiter helper).
	$ip   = function_exists( 'osoul_client_ip' ) ? osoul_client_ip() : '0.0.0.0';
	$key  = 'osoul_rlq_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_Error( 'osoul_rate_limited', __( 'Too many requests. Please try again later.', 'osoul' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	// Input.
	$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	$lang  = ( 'en' === $request->get_param( 'lang' ) ) ? 'en' : 'ar';

	// Logged-in customers don't retype their details — pull from the account.
	if ( function_exists( 'osoul_is_customer' ) && osoul_is_customer() ) {
		$cu = wp_get_current_user();
		if ( '' === $name )  { $name  = $cu->display_name; }
		if ( '' === $email ) { $email = $cu->user_email; }
		if ( '' === $phone && function_exists( 'osoul_customer_phone' ) ) { $phone = osoul_customer_phone( $cu->ID ); }
	}

	if ( '' === $name || ( '' === $phone && '' === $email ) ) {
		return new WP_Error( 'osoul_missing', __( 'Please provide your name and a phone number or email.', 'osoul' ), array( 'status' => 422 ) );
	}

	$items = osoul_sanitize_quote_items( $request->get_param( 'items' ) );
	if ( empty( $items ) ) {
		return new WP_Error( 'osoul_no_items', __( 'Your quote list is empty.', 'osoul' ), array( 'status' => 422 ) );
	}

	// Enrich items from the catalog: English name + default unit price as a start.
	$catalog = osoul_catalog();
	foreach ( $items as &$it ) {
		if ( isset( $catalog[ $it['slug'] ] ) ) {
			$p             = $catalog[ $it['slug'] ];
			$it['name']    = $it['name'] ?: ( $p['name'] ?? '' );
			$it['name_en'] = $p['name_en'] ?? $it['name_en'];
			$it['unit_price'] = isset( $p['price'] ) ? (float) $p['price'] : 0.0;
		}
	}
	unset( $it );

	// Store the quote.
	$quote_id = wp_insert_post( array(
		'post_type'   => 'osoul_quote',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s — %s', $name, $phone ? $phone : $email ),
	), true );
	if ( is_wp_error( $quote_id ) ) {
		return new WP_Error( 'osoul_store_failed', __( 'Could not save your request. Please try WhatsApp.', 'osoul' ), array( 'status' => 500 ) );
	}

	$meta = array(
		'_osoul_customer' => array( 'name' => $name, 'phone' => $phone, 'email' => $email ),
		'_osoul_items'    => $items,
		'_osoul_stage'    => 'new',
		'_osoul_token'    => osoul_quote_token(),
		'_osoul_discount' => 0,
		'_osoul_lang'     => $lang,
		'_osoul_ip'       => $ip,
		'_osoul_created'  => current_time( 'mysql' ),
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $quote_id, $k, $v );
	}

	// Tag origin so the B2B portal can filter (website customer, not rep/branch).
	if ( function_exists( 'osoul_quote_set_attribution' ) ) {
		osoul_quote_set_attribution( $quote_id, null );
	}
	if ( function_exists( 'osoul_quote_log' ) ) {
		osoul_quote_log( $quote_id, 'تم إنشاء الطلب (الموقع)' );
	}

	osoul_notify_new_quote( $quote_id, $name, $phone, $email, $items );

	do_action( 'osoul_quote_request_received', $quote_id, $meta );

	return new WP_REST_Response( array( 'ok' => true, 'id' => $quote_id ), 201 );
}

/**
 * Email the team about a new quote request.
 */
function osoul_notify_new_quote( $quote_id, $name, $phone, $email, $items ) {
	$to = apply_filters( 'osoul_quote_notify_email', get_option( 'admin_email' ) );

	$lines   = array();
	$lines[] = 'New quote request from the website:';
	$lines[] = '';
	$lines[] = 'Name:  ' . $name;
	$lines[] = 'Phone: ' . $phone;
	$lines[] = 'Email: ' . $email;
	$lines[] = '';
	$lines[] = 'Items:';
	foreach ( $items as $it ) {
		$lines[] = sprintf( '  - %s (x%d)', $it['name'], $it['qty'] );
	}
	$lines[] = '';
	$lines[] = 'Open the dashboard: ' . home_url( '/dashboard/' );

	$subject = sprintf( '[%s] New quote request — %s', wp_specialchars_decode( get_bloginfo( 'name' ) ), $name );
	$headers = array();
	if ( is_email( $email ) ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}
	wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}
