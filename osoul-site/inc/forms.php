<?php
/**
 * Server-side quote / contact submissions.
 *
 * NEW capability. The legacy site only ever opened WhatsApp or a mailto: link,
 * so no lead was captured if the visitor closed that app. Here we:
 *
 *   1. Register a private "osoul_lead" custom post type to store every submission.
 *   2. Expose a REST endpoint the front-end posts to (nonce-protected, sanitised,
 *      honeypot + lightweight rate limiting).
 *   3. Email the site admin on each new lead.
 *
 * The existing WhatsApp buttons keep working — the front-end JS now *also* records
 * the lead server-side before handing off to WhatsApp, so nothing is lost.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register the lead storage post type.
 */
add_action( 'init', function () {
	register_post_type( 'osoul_lead', array(
		'labels' => array(
			'name'          => __( 'Leads', 'osoul' ),
			'singular_name' => __( 'Lead', 'osoul' ),
			'menu_name'     => __( 'Quote Leads', 'osoul' ),
		),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_icon'          => 'dashicons-email-alt',
		'menu_position'      => 26,
		'capability_type'    => 'post',
		'capabilities'       => array( 'create_posts' => 'do_not_allow' ), // leads come only from the form
		'map_meta_cap'       => true,
		'supports'           => array( 'title' ),
		'has_archive'        => false,
		'rewrite'            => false,
		'exclude_from_search'=> true,
	) );
} );

/**
 * Register the REST route the front-end submits to.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'osoul/v1', '/submit', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_handle_submission',
		'permission_callback' => '__return_true', // public form; nonce verified inside
	) );
} );

/**
 * Handle an incoming submission.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function osoul_handle_submission( WP_REST_Request $request ) {
	// 1) Nonce check (the front-end is given a fresh wp_rest nonce).
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'osoul_bad_nonce', __( 'Security check failed. Please refresh and try again.', 'osoul' ), array( 'status' => 403 ) );
	}

	// 2) Honeypot — bots fill hidden fields; humans never see them.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 ); // pretend success, store nothing
	}

	// 3) Lightweight per-IP rate limit (max 5 / 10 min).
	$ip  = osoul_client_ip();
	$key = 'osoul_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_Error( 'osoul_rate_limited', __( 'Too many requests. Please try again later.', 'osoul' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	// 4) Sanitise input.
	$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
	$email   = sanitize_email( (string) $request->get_param( 'email' ) );
	$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
	$lang    = ( 'en' === $request->get_param( 'lang' ) ) ? 'en' : 'ar';
	$source  = sanitize_key( (string) $request->get_param( 'source' ) ); // 'contact' | 'quote' | 'archive'

	// Quote list: [{slug,name,qty}, …]
	$items_raw = $request->get_param( 'items' );
	$items     = array();
	if ( is_array( $items_raw ) ) {
		foreach ( $items_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$items[] = array(
				'slug' => sanitize_title( $row['slug'] ?? '' ),
				'name' => sanitize_text_field( $row['name'] ?? '' ),
				'qty'  => max( 1, (int) ( $row['qty'] ?? 1 ) ),
			);
		}
	}

	// 5) Validate the minimum.
	if ( '' === $name || ( '' === $phone && '' === $email ) ) {
		return new WP_Error( 'osoul_missing', __( 'Please provide your name and a phone number or email.', 'osoul' ), array( 'status' => 422 ) );
	}

	// 6) Store the lead.
	$title = sprintf( '%s — %s', $name, $phone ? $phone : $email );
	$lead_id = wp_insert_post( array(
		'post_type'   => 'osoul_lead',
		'post_status' => 'publish',
		'post_title'  => $title,
	), true );

	if ( is_wp_error( $lead_id ) ) {
		return new WP_Error( 'osoul_store_failed', __( 'Could not save your request. Please try WhatsApp.', 'osoul' ), array( 'status' => 500 ) );
	}

	$meta = array(
		'_osoul_name'    => $name,
		'_osoul_phone'   => $phone,
		'_osoul_email'   => $email,
		'_osoul_subject' => $subject,
		'_osoul_message' => $message,
		'_osoul_lang'    => $lang,
		'_osoul_source'  => $source ? $source : 'contact',
		'_osoul_items'   => $items,
		'_osoul_ip'      => $ip,
		'_osoul_ua'      => sanitize_text_field( (string) $request->get_header( 'user_agent' ) ),
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $lead_id, $k, $v );
	}

	// 7) Notify admin.
	osoul_email_lead( $lead_id, $meta );

	/**
	 * Fires after a lead is stored. Hook a CRM / Zapier / SMS integration here.
	 *
	 * @param int   $lead_id
	 * @param array $meta
	 */
	do_action( 'osoul_lead_received', $lead_id, $meta );

	return new WP_REST_Response( array( 'ok' => true, 'id' => $lead_id ), 201 );
}

/**
 * Best-effort client IP (used only for rate limiting / logging, never trusted).
 *
 * @return string
 */
function osoul_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Email the admin about a new lead.
 *
 * @param int   $lead_id
 * @param array $meta
 */
function osoul_email_lead( $lead_id, $meta ) {
	$to = apply_filters( 'osoul_lead_notify_email', get_option( 'admin_email' ) );

	$lines   = array();
	$lines[] = 'New request from the website:';
	$lines[] = '';
	$lines[] = 'Name:    ' . $meta['_osoul_name'];
	$lines[] = 'Phone:   ' . $meta['_osoul_phone'];
	$lines[] = 'Email:   ' . $meta['_osoul_email'];
	$lines[] = 'Subject: ' . $meta['_osoul_subject'];
	$lines[] = 'Source:  ' . $meta['_osoul_source'];
	$lines[] = 'Lang:    ' . $meta['_osoul_lang'];

	if ( ! empty( $meta['_osoul_items'] ) ) {
		$lines[] = '';
		$lines[] = 'Quote list:';
		foreach ( $meta['_osoul_items'] as $item ) {
			$lines[] = sprintf( '  - %s (x%d)', $item['name'], $item['qty'] );
		}
	}

	if ( '' !== $meta['_osoul_message'] ) {
		$lines[] = '';
		$lines[] = 'Message:';
		$lines[] = $meta['_osoul_message'];
	}

	$lines[] = '';
	$lines[] = 'Manage: ' . admin_url( 'post.php?post=' . $lead_id . '&action=edit' );

	$subject = sprintf( '[%s] New lead — %s', wp_specialchars_decode( get_bloginfo( 'name' ) ), $meta['_osoul_name'] );

	$headers = array();
	if ( is_email( $meta['_osoul_email'] ) ) {
		$headers[] = 'Reply-To: ' . $meta['_osoul_name'] . ' <' . $meta['_osoul_email'] . '>';
	}

	wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}

/* =========================================================
 * Admin: show lead details in the list table + edit screen.
 * ========================================================= */

add_filter( 'manage_osoul_lead_posts_columns', function ( $cols ) {
	$new = array( 'cb' => $cols['cb'] ?? '' );
	$new['title']         = __( 'Name', 'osoul' );
	$new['osoul_phone']   = __( 'Phone', 'osoul' );
	$new['osoul_email']   = __( 'Email', 'osoul' );
	$new['osoul_source']  = __( 'Source', 'osoul' );
	$new['date']          = __( 'Received', 'osoul' );
	return $new;
} );

add_action( 'manage_osoul_lead_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'osoul_phone':
			echo esc_html( get_post_meta( $post_id, '_osoul_phone', true ) );
			break;
		case 'osoul_email':
			echo esc_html( get_post_meta( $post_id, '_osoul_email', true ) );
			break;
		case 'osoul_source':
			echo esc_html( get_post_meta( $post_id, '_osoul_source', true ) );
			break;
	}
}, 10, 2 );

add_action( 'add_meta_boxes_osoul_lead', function () {
	add_meta_box( 'osoul_lead_details', __( 'Lead details', 'osoul' ), function ( $post ) {
		$m = get_post_meta( $post->ID );
		$g = function ( $k ) use ( $m ) { return isset( $m[ $k ][0] ) ? $m[ $k ][0] : ''; };
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>Name</th><td>%s</td></tr>', esc_html( $g( '_osoul_name' ) ) );
		printf( '<tr><th>Phone</th><td>%s</td></tr>', esc_html( $g( '_osoul_phone' ) ) );
		printf( '<tr><th>Email</th><td>%s</td></tr>', esc_html( $g( '_osoul_email' ) ) );
		printf( '<tr><th>Subject</th><td>%s</td></tr>', esc_html( $g( '_osoul_subject' ) ) );
		printf( '<tr><th>Source</th><td>%s</td></tr>', esc_html( $g( '_osoul_source' ) ) );
		printf( '<tr><th>Language</th><td>%s</td></tr>', esc_html( $g( '_osoul_lang' ) ) );
		printf( '<tr><th>Message</th><td>%s</td></tr>', nl2br( esc_html( $g( '_osoul_message' ) ) ) );

		$items = maybe_unserialize( $g( '_osoul_items' ) );
		if ( is_array( $items ) && $items ) {
			echo '<tr><th>Quote list</th><td><ul style="margin:0">';
			foreach ( $items as $item ) {
				printf( '<li>%s &times;%d</li>', esc_html( $item['name'] ?? '' ), (int) ( $item['qty'] ?? 1 ) );
			}
			echo '</ul></td></tr>';
		}
		printf( '<tr><th>IP</th><td>%s</td></tr>', esc_html( $g( '_osoul_ip' ) ) );
		echo '</tbody></table>';
	}, 'osoul_lead', 'normal', 'high' );
} );
