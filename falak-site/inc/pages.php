<?php
/**
 * إنشاء صفحات ووردبريس المطلوبة وتعيين الصفحة الرئيسية.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * قائمة الصفحات المطلوبة (slug => عنوان).
 */
function falak_required_pages() {
	return array(
		'home'     => 'الرئيسية',
		'about'    => 'من نحن',
		'programs' => 'البرامج',
		'careers'  => 'التوظيف',
		'gallery'  => 'معرض الصور',
		'faq'      => 'الأسئلة الشائعة',
		'contact'  => 'تواصل معنا',
		'enroll'   => 'التسجيل',
		'review'   => 'أضف تقييمك',
		'dashboard'=> 'لوحة التحكم',
	);
}

/**
 * إنشاء الصفحات الناقصة + تعيين الصفحة الرئيسية الثابتة.
 *
 * @param bool $set_front تعيين صفحة «الرئيسية» كواجهة ثابتة.
 */
function falak_create_missing_pages( $set_front = false ) {
	$ids = get_option( 'falak_page_ids', array() );
	foreach ( falak_required_pages() as $slug => $title ) {
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof WP_Post ) {
			$ids[ $slug ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_content'=> '',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $slug ] = $id;
			update_post_meta( $id, '_falak_page', $slug );
		}
	}
	update_option( 'falak_page_ids', $ids );

	if ( $set_front && isset( $ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
	}
}

/**
 * معرّف صفحة حسب الـ slug.
 */
function falak_page_id( $slug ) {
	$ids = get_option( 'falak_page_ids', array() );
	return isset( $ids[ $slug ] ) ? (int) $ids[ $slug ] : 0;
}

/**
 * أي «صفحة فلك» هذه؟ يرجع الـ slug أو '' .
 */
function falak_current_page_key() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( ! is_page() ) {
		return '';
	}
	$post = get_queried_object();
	if ( $post instanceof WP_Post ) {
		$key = get_post_meta( $post->ID, '_falak_page', true );
		if ( $key ) {
			return $key;
		}
		// رجوع إلى الـ slug إن طابق.
		if ( array_key_exists( $post->post_name, falak_required_pages() ) ) {
			return $post->post_name;
		}
	}
	return '';
}
