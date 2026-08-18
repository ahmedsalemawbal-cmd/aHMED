<?php
/**
 * التوظيف — صفحة «التوظيف» بنموذج بسيط، طلباته تُحفظ وتظهر في الداش بورد.
 * (بديل صفحة «المعلمون»).
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * حقول طلب التوظيف (المفتاح => التسمية).
 */
function falak_job_fields() {
	return array(
		'name'          => 'الاسم',
		'phone'         => 'رقم الجوال',
		'email'         => 'البريد الإلكتروني',
		'position'      => 'الوظيفة / التخصص',
		'experience'    => 'سنوات الخبرة',
		'qualification' => 'المؤهل العلمي',
		'notes'         => 'نبذة',
	);
}

/**
 * تسجيل نوع المحتوى «طلب توظيف».
 */
add_action( 'init', 'falak_register_job_cpt' );
function falak_register_job_cpt() {
	register_post_type( 'falak_job', array(
		'labels'       => array( 'name' => 'طلبات التوظيف', 'singular_name' => 'طلب توظيف' ),
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'has_archive'  => false,
		'rewrite'      => false,
		'supports'     => array( 'title' ),
	) );
}

/**
 * مسار REST لاستقبال طلبات التوظيف.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'falak/v1', '/job', array(
		'methods'             => 'POST',
		'callback'            => 'falak_handle_job',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * معالجة طلب التوظيف.
 */
function falak_handle_job( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( empty( $params ) ) {
		$params = $request->get_body_params();
	}

	if ( ! empty( $params['fk_website'] ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'nonce' ), 403 );
	}
	$ip   = falak_client_ip();
	$key  = 'falak_jrl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	$data = array();
	foreach ( falak_job_fields() as $k => $label ) {
		$raw = isset( $params[ $k ] ) ? $params[ $k ] : '';
		if ( 'email' === $k ) {
			$data[ $k ] = sanitize_email( $raw );
		} elseif ( 'notes' === $k ) {
			$data[ $k ] = sanitize_textarea_field( $raw );
		} else {
			$data[ $k ] = sanitize_text_field( $raw );
		}
	}
	if ( '' === $data['name'] || '' === $data['phone'] ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing' ), 400 );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'falak_job',
		'post_status' => 'private',
		'post_title'  => $data['name'] . ' — ' . $data['phone'],
	), true );
	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'save' ), 500 );
	}
	foreach ( $data as $k => $v ) {
		update_post_meta( $post_id, '_falak_' . $k, $v );
	}
	update_post_meta( $post_id, '_falak_status', 'new' );
	update_post_meta( $post_id, '_falak_ip', $ip );

	// إشعار بريدي.
	$to = apply_filters( 'falak_job_notify_email', falak_opt( 'email' ), $post_id );
	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}
	$lines = array( 'وصل طلب توظيف جديد لمدرسة الفلك المنير:', '' );
	foreach ( falak_job_fields() as $k => $label ) {
		if ( ! empty( $data[ $k ] ) ) {
			$lines[] = $label . ': ' . $data[ $k ];
		}
	}
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
		$headers[] = 'Reply-To: ' . $data['email'];
	}
	wp_mail( $to, 'طلب توظيف جديد: ' . $data['name'], implode( "\n", $lines ), $headers );

	do_action( 'falak_job_received', $post_id, $data );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * قالب صفحة «التوظيف».
 */
function falak_tpl_careers() {
	falak_page_head( 'التوظيف', 'انضمّ إلى فريق مدرسة الفلك المنير — عبّئ بياناتك وسنتواصل معك' );
	?>
	<section class="fk-section alt">
		<div class="fk-review-wrap fk-reveal">
			<p style="text-align:center;color:var(--c-muted);margin-bottom:26px;line-height:1.9">نبحث دائمًا عن الكفاءات المتميّزة للانضمام إلى أسرتنا التعليمية. عبّئ النموذج التالي، وسيتواصل معك قسم الموارد البشرية عند توفّر شاغرٍ مناسب.</p>
			<form id="falak-job-form" class="fk-form" novalidate>
				<div class="fk-form-msg" role="alert"></div>

				<div class="fk-field fk-field-full">
					<label>الاسم الكامل <span class="req">*</span></label>
					<input type="text" name="name" required autocomplete="name">
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>رقم الجوال <span class="req">*</span></label>
						<input type="tel" name="phone" dir="ltr" required inputmode="tel" placeholder="05xxxxxxxx" autocomplete="tel">
					</div>
					<div class="fk-field">
						<label>البريد الإلكتروني <small class="fk-opt">(اختياري)</small></label>
						<input type="email" name="email" dir="ltr" autocomplete="email">
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>الوظيفة / التخصص <span class="req">*</span></label>
						<input type="text" name="position" required placeholder="مثال: معلم رياضيات، إداري، مشرف">
					</div>
					<div class="fk-field">
						<label>سنوات الخبرة</label>
						<input type="number" name="experience" min="0" max="50" inputmode="numeric">
					</div>
				</div>

				<div class="fk-field fk-field-full">
					<label>المؤهل العلمي <small class="fk-opt">(اختياري)</small></label>
					<input type="text" name="qualification" placeholder="مثال: بكالوريوس تربية">
				</div>

				<div class="fk-field fk-field-full">
					<label>نبذة عنك <small class="fk-opt">(اختياري)</small></label>
					<textarea name="notes" placeholder="اكتب نبذة مختصرة عن خبرتك ومهاراتك"></textarea>
				</div>

				<input type="text" name="fk_website" class="fk-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

				<button type="submit" class="fk-btn fk-btn-p fk-form-submit"><?php falak_icon( 'user', 18 ); ?> إرسال طلب التوظيف</button>
			</form>
		</div>
	</section>
	<?php
}
