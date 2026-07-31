<?php
/**
 * نظام التسجيل — بديل نظام «عروض الأسعار» في إضافة أصول.
 * نموذج تسجيل → REST endpoint → حفظ كـ CPT + إشعار بريد، مع honeypot و rate-limit.
 * تُدار الطلبات من: لوحة التحكم → التسجيلات.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * الحقول المعتمدة في طلب التسجيل (المفتاح => التسمية العربية).
 */
function falak_enroll_fields() {
	return array(
		'student_name'   => 'اسم الطالب/ة',
		'student_age'    => 'العمر',
		'gender'         => 'الجنس',
		'section'        => 'القسم',
		'grade'          => 'المرحلة الدراسية',
		'program'        => 'المرحلة/البرنامج المطلوب',
		'current_level'  => 'المدرسة السابقة',
		'guardian_name'  => 'اسم ولي الأمر',
		'guardian_phone' => 'رقم الجوال',
		'guardian_email' => 'البريد الإلكتروني',
		'preferred_time' => 'الوقت المفضّل',
		'notes'          => 'ملاحظات',
	);
}

/**
 * تسجيل نوع المحتوى «طلب تسجيل».
 */
add_action( 'init', 'falak_register_enroll_cpt' );
function falak_register_enroll_cpt() {
	register_post_type(
		'falak_enroll',
		array(
			'labels'       => array(
				'name'          => 'التسجيلات',
				'singular_name' => 'طلب تسجيل',
				'menu_name'     => 'التسجيلات',
				'all_items'     => 'كل الطلبات',
				'edit_item'     => 'عرض الطلب',
				'search_items'  => 'بحث في الطلبات',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-welcome-learn-more',
			'menu_position'=> 25,
			'capability_type' => 'post',
			'supports'     => array( 'title' ),
			'has_archive'  => false,
			'rewrite'      => false,
		)
	);
}

// منع إنشاء طلب جديد يدويًا (تأتي من النموذج فقط).
add_filter( 'map_meta_cap', function ( $caps, $cap ) {
	if ( 'create_posts' === $cap && isset( $_GET['post_type'] ) && 'falak_enroll' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		$caps[] = 'do_not_allow';
	}
	return $caps;
}, 10, 2 );

/**
 * تسجيل مسار REST للتسجيل.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'falak/v1',
		'/enroll',
		array(
			'methods'             => 'POST',
			'callback'            => 'falak_handle_enroll',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * معالجة طلب التسجيل.
 */
function falak_handle_enroll( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( empty( $params ) ) {
		$params = $request->get_body_params();
	}

	// honeypot: إن امتلأ الحقل المخفي فهو سبام.
	if ( ! empty( $params['fk_website'] ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 ); // نتظاهر بالنجاح.
	}

	// التحقق من nonce (CSRF).
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'nonce' ), 403 );
	}

	// rate-limit: 5 طلبات / 10 دقائق لكل IP.
	$ip  = falak_client_ip();
	$key = 'falak_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	// تنقية الحقول.
	$data   = array();
	$fields = falak_enroll_fields();
	foreach ( $fields as $key_f => $label ) {
		$raw = isset( $params[ $key_f ] ) ? $params[ $key_f ] : '';
		if ( 'guardian_email' === $key_f ) {
			$data[ $key_f ] = sanitize_email( $raw );
		} elseif ( 'notes' === $key_f ) {
			$data[ $key_f ] = sanitize_textarea_field( $raw );
		} else {
			$data[ $key_f ] = sanitize_text_field( $raw );
		}
	}

	// الحد الأدنى المطلوب.
	if ( '' === $data['student_name'] || '' === $data['guardian_phone'] ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing' ), 400 );
	}

	// إنشاء الطلب.
	$title = $data['student_name'] . ' — ' . $data['guardian_phone'];
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'falak_enroll',
			'post_status' => 'private',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'save' ), 500 );
	}
	foreach ( $data as $key_f => $val ) {
		update_post_meta( $post_id, '_falak_' . $key_f, $val );
	}
	update_post_meta( $post_id, '_falak_ip', $ip );
	update_post_meta( $post_id, '_falak_status', 'new' );

	// إشعار بريد للإدارة.
	falak_notify_enroll( $post_id, $data );

	/**
	 * خطّاف لإعادة توجيه الطلب (CRM / واتساب API / …).
	 */
	do_action( 'falak_enroll_received', $post_id, $data );

	return new WP_REST_Response( array( 'ok' => true, 'id' => $post_id ), 200 );
}

/**
 * إرسال إشعار بريدي بالطلب الجديد.
 */
function falak_notify_enroll( $post_id, $data ) {
	$to = apply_filters( 'falak_enroll_notify_email', falak_opt( 'email' ), $post_id );
	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}
	$fields  = falak_enroll_fields();
	$lines   = array( 'وصل طلب تسجيل جديد لمدرسة الفلك المنير:', '' );
	foreach ( $fields as $key_f => $label ) {
		if ( ! empty( $data[ $key_f ] ) ) {
			$lines[] = $label . ': ' . $data[ $key_f ];
		}
	}
	$lines[] = '';
	$lines[] = 'إدارة الطلبات: ' . admin_url( 'edit.php?post_type=falak_enroll' );

	$subject = 'طلب تسجيل جديد: ' . $data['student_name'];
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! empty( $data['guardian_email'] ) && is_email( $data['guardian_email'] ) ) {
		$headers[] = 'Reply-To: ' . $data['guardian_email'];
	}
	wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}

/**
 * IP العميل (مع مراعاة البروكسي).
 */
function falak_client_ip() {
	$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
	foreach ( $keys as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) );
			$ip = trim( explode( ',', $ip )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

/* ─────────────────────────────────────────
   لوحة التحكم: أعمدة + عرض تفاصيل الطلب
───────────────────────────────────────────*/

add_filter( 'manage_falak_enroll_posts_columns', function ( $cols ) {
	return array(
		'cb'         => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'title'      => 'الطالب',
		'fk_phone'   => 'الجوال',
		'fk_section' => 'القسم',
		'fk_program' => 'البرنامج',
		'fk_status'  => 'الحالة',
		'date'       => 'التاريخ',
	);
} );

add_action( 'manage_falak_enroll_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'fk_phone':
			echo esc_html( get_post_meta( $post_id, '_falak_guardian_phone', true ) );
			break;
		case 'fk_section':
			echo esc_html( get_post_meta( $post_id, '_falak_section', true ) );
			break;
		case 'fk_program':
			echo esc_html( get_post_meta( $post_id, '_falak_program', true ) );
			break;
		case 'fk_status':
			$st  = get_post_meta( $post_id, '_falak_status', true ) ?: 'new';
			$map = array( 'new' => 'جديد', 'contacted' => 'تم التواصل', 'enrolled' => 'مُسجّل', 'closed' => 'مغلق' );
			echo '<span style="background:#e6f7ee;color:#137a45;padding:3px 10px;border-radius:100px;font-size:12px">' . esc_html( $map[ $st ] ?? $st ) . '</span>';
			break;
	}
}, 10, 2 );

// صندوق تفاصيل الطلب.
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'falak_enroll_details', 'تفاصيل الطلب', 'falak_enroll_details_box', 'falak_enroll', 'normal', 'high' );
} );

function falak_enroll_details_box( $post ) {
	$fields = falak_enroll_fields();
	echo '<table class="widefat striped" style="max-width:600px"><tbody>';
	foreach ( $fields as $key_f => $label ) {
		$val = get_post_meta( $post->ID, '_falak_' . $key_f, true );
		if ( '' === $val ) {
			continue;
		}
		echo '<tr><th style="width:170px;text-align:right">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	$phone = get_post_meta( $post->ID, '_falak_guardian_phone', true );
	if ( $phone ) {
		$wa = preg_replace( '/\D+/', '', $phone );
		echo '<tr><th>تواصل سريع</th><td>'
			. '<a class="button" href="tel:' . esc_attr( $phone ) . '">اتصال</a> '
			. '<a class="button button-primary" target="_blank" href="https://wa.me/' . esc_attr( $wa ) . '">واتساب</a>'
			. '</td></tr>';
	}
	echo '</tbody></table>';
}
