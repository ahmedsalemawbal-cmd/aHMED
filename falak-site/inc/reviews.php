<?php
/**
 * التقييمات — صفحة عامة لإضافة تقييم + REST endpoint + عرض الشهادات كسلايدر.
 * ولي الأمر يفتح رابط /review/ ويكتب تقييمه؛ يُحفظ بانتظار الاعتماد من الداش بورد،
 * ثم يظهر في سلايدر «ثقة أولياء الأمور».
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * نجوم التقييم كـ HTML (ممتلئة حتى القيمة).
 */
function falak_stars_html( $rating, $size = 18 ) {
	$rating = max( 0, min( 5, (int) $rating ) );
	$out    = '<div class="fk-stars" aria-label="' . esc_attr( $rating ) . ' من 5">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$fill = $i <= $rating ? 'var(--c-gold)' : 'none';
		$out .= '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="' . $fill . '" stroke="var(--c-gold)" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.9 6.2 6.8.9-4.9 4.7 1.2 6.7L12 17.8 5.9 21l1.2-6.7L2.2 9.6l6.8-.9z"/></svg>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * عرض سلايدر الشهادات (يُستخدم في الرئيسية).
 */
function falak_render_testimonials() {
	$reviews = falak_get_reviews_display();
	?>
	<div class="fk-slider testi fk-reveal" data-slider>
		<button class="fk-sl-btn fk-sl-prev" type="button" aria-label="السابق"><?php falak_icon( 'chevron', 22 ); ?></button>
		<div class="fk-sl-track">
			<?php foreach ( $reviews as $r ) : $initial = function_exists( 'mb_substr' ) ? mb_substr( $r['name'], 0, 1 ) : substr( $r['name'], 0, 1 ); ?>
				<div class="fk-slide">
					<article class="fk-testi-card">
						<span class="quote">”</span>
						<?php echo falak_stars_html( $r['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<p><?php echo esc_html( $r['text'] ); ?></p>
						<div class="fk-testi-who">
							<span class="fk-testi-av"><?php echo esc_html( $initial ); ?></span>
							<span><b><?php echo esc_html( $r['name'] ); ?></b><small><?php echo esc_html( $r['role'] ); ?></small></span>
						</div>
					</article>
				</div>
			<?php endforeach; ?>
		</div>
		<button class="fk-sl-btn fk-sl-next" type="button" aria-label="التالي"><?php falak_icon( 'chevron', 22 ); ?></button>
	</div>
	<?php
}

/**
 * قالب صفحة «أضف تقييمك».
 */
function falak_tpl_review() {
	falak_page_head( 'أضف تقييمك', 'رأيك يهمّنا — شاركنا تجربتك مع مدرسة الفلك المنير' );
	?>
	<section class="fk-section alt">
		<div class="fk-review-wrap fk-reveal">
			<form id="falak-review-form" class="fk-form" novalidate>
				<div class="fk-form-msg" role="alert"></div>

				<div class="fk-field fk-field-full">
					<label>تقييمك <span class="req">*</span></label>
					<div class="fk-rate" data-rate>
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<button type="button" class="fk-star" data-v="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $i ); ?> نجوم">
								<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--c-gold)" stroke-width="1.5" stroke-linejoin="round"><path d="M12 2l2.9 6.2 6.8.9-4.9 4.7 1.2 6.7L12 17.8 5.9 21l1.2-6.7L2.2 9.6l6.8-.9z"/></svg>
							</button>
						<?php endfor; ?>
						<input type="hidden" name="rating" value="5">
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>الاسم <span class="req">*</span></label>
						<input type="text" name="name" required placeholder="اسمك الكريم">
					</div>
					<div class="fk-field">
						<label>الصفة</label>
						<select name="role">
							<option value="ولي أمر">ولي أمر</option>
							<option value="ولية أمر">ولية أمر</option>
							<option value="طالب">طالب</option>
							<option value="طالبة">طالبة</option>
						</select>
					</div>
				</div>

				<div class="fk-field fk-field-full">
					<label>تقييمك ورأيك <span class="req">*</span></label>
					<textarea name="text" required placeholder="شاركنا تجربتك مع المدرسة..." style="min-height:120px"></textarea>
				</div>

				<input type="text" name="fk_website" class="fk-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

				<button type="submit" class="fk-btn fk-btn-p fk-form-submit"><?php falak_icon( 'star', 18 ); ?> إرسال التقييم</button>
			</form>
		</div>
	</section>
	<?php
}

/**
 * تسجيل مسار REST للتقييم.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'falak/v1',
		'/review',
		array(
			'methods'             => 'POST',
			'callback'            => 'falak_handle_review',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * معالجة إرسال التقييم.
 */
function falak_handle_review( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( empty( $params ) ) {
		$params = $request->get_body_params();
	}

	// honeypot.
	if ( ! empty( $params['fk_website'] ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// nonce.
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'nonce' ), 403 );
	}

	// rate-limit: 3 تقييمات / 30 دقيقة لكل IP.
	$ip   = falak_client_ip();
	$key  = 'falak_rvl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 3 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
	}
	set_transient( $key, $hits + 1, 30 * MINUTE_IN_SECONDS );

	$name   = sanitize_text_field( $params['name'] ?? '' );
	$role   = sanitize_text_field( $params['role'] ?? 'ولي أمر' );
	$text   = sanitize_textarea_field( $params['text'] ?? '' );
	$rating = (int) ( $params['rating'] ?? 5 );
	$rating = max( 1, min( 5, $rating ) );

	if ( '' === $name || '' === $text ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing' ), 400 );
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'falak_review',
		'post_status'  => 'pending', // بانتظار الاعتماد من الداش بورد.
		'post_title'   => $name,
		'post_content' => $text,
	), true );

	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'save' ), 500 );
	}
	update_post_meta( $post_id, '_falak_role', $role );
	update_post_meta( $post_id, '_falak_rating', $rating );
	update_post_meta( $post_id, '_falak_ip', $ip );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
