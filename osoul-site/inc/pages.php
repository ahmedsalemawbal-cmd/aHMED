<?php
/**
 * WordPress page management — create the pages the templates depend on,
 * clean up old/obsolete pages, and hide the demo "Hello World" post.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Create the required pages once.
 *
 * @param bool $force Bypass the "already ran" option (used on activation).
 */
add_action( 'init', 'osoul_create_missing_pages' );
if ( ! function_exists( 'osoul_create_missing_pages' ) ) {
	function osoul_create_missing_pages( $force = false ) {
		if ( ! $force && get_option( 'osoul_pages_v10' ) ) {
			return;
		}

		$pages = array(
			/* Services */
			'service-supply'       => 'توريد المواد والمنتجات',
			'service-install'      => 'التركيب والتنفيذ الاحترافي',
			'service-guarantee'    => 'الضمان الشامل وما بعد البيع',
			'service-consulting'   => 'الاستشارة الفنية والهندسية',
			'service-maintenance'  => 'الصيانة الدورية والطارئة',
			'service-inspection'   => 'الفحص والتفتيش الفني',
			/* Product archives */
			'doors'       => 'الأبواب بأنواعها',
			'gypsum'  => 'بروفايلات الجبسوم بورد',
			'strut'  => 'أنظمة Strut التثبيت',
			/* Doors */
			'fire-resistant-doors' => 'الأبواب المعدنية المقاومة للحريق',
			'hollow-metal-doors'   => 'الأبواب المعدنية المجوفة',
			'cladding-doors'       => 'الأبواب التكسوية',
			'mdf-doors'            => 'الأبواب الخشبية MDF',
			'wpc-doors'            => 'الأبواب WPC مقاومة الرطوبة',
			'custom-doors'         => 'الأبواب المخصصة',
			/* Gypsum */
			'c-stud-25'            => 'C-Stud 25 مم',
			'c-stud-50'            => 'C-Stud 50 مم',
			'c-stud-75'            => 'C-Stud 75 مم',
			'u-track'              => 'U-Track تراك تثبيت',
			'l-angle'              => 'L-Angle زاوية التشطيب',
			'hat-channel'          => 'Hat Channel قبعة',
			'main-ceiling-channel' => 'Main Channel قناة السقف',
			'gypsum-accessories'   => 'إكسسوارات الجبسوم',
			/* Strut / fixing — Fischer */
			'u-bolt-etr-l'             => 'طوق U بخيط طويل ETR-L',
			'u-bolt-connector-fetr-c'  => 'وصلة قناة FETR-C',
			'channel-fls'              => 'قناة FLS للتثبيت الخفيف',
			'cantilever-arm-alk'       => 'ذراع كونسول ALK',
			'angle-brace-ws-31-45'     => 'دعامة زاوية WS 31-45 درجة',
			'beam-clamp-tkr-31'        => 'مشبك تعليق على الجسور TKR-31',
			'saddle-flange-sf-l'       => 'فلنشة سرج SF-L',
			'angle-fitting-faf'        => 'وصلة زاوية FAF',
			'system-connector-fma-fus' => 'وصلة نظام FMA-FUS',
			/* New gypsum accessories */
			'u-clip'        => 'U-Clip يوكليب',
			'wire-clip'     => 'Wire Clip واير كليب',
			'angel-w20'     => 'زاوية دبليو 20×20 مم',
			'angel-w30'     => 'زاوية دبليو 20×30 مم',
			'shadow-angle'  => 'زاوية الضلال Shadow',
		);

		foreach ( $pages as $slug => $title ) {
			if ( ! get_page_by_path( $slug ) ) {
				wp_insert_post( array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				) );
			}
		}

		update_option( 'osoul_pages_v10', 1 );
		flush_rewrite_rules( false );
	}
}

/**
 * 301-redirect the old theme slugs to the new clean URLs.
 */
add_action( 'template_redirect', 'osoul_redirect_legacy_slugs', 0 );
if ( ! function_exists( 'osoul_redirect_legacy_slugs' ) ) {
	function osoul_redirect_legacy_slugs() {
		$map = array(
			'doors-style-01'      => 'doors',
			'gypsum-board-system' => 'gypsum',
			'strut-fixing-system' => 'strut',
			'services-style-01'   => 'services',
			'project-style-01'    => 'projects',
			'grid-view-01'        => 'blog',
		);
		$slug = osoul_request_slug();
		if ( isset( $map[ $slug ] ) ) {
			wp_redirect( home_url( '/' . $map[ $slug ] . '/' ), 301 );
			exit;
		}
	}
}

/**
 * Hide the demo "Hello World" post from the blog index.
 */
add_action( 'pre_get_posts', 'osoul_exclude_demo_post' );
if ( ! function_exists( 'osoul_exclude_demo_post' ) ) {
	function osoul_exclude_demo_post( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
			return;
		}
		global $wpdb;
		$demo = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE '%Hello%world%' AND post_status='publish' AND post_type='post' LIMIT 1"
		);
		if ( $demo ) {
			$query->set( 'post__not_in', array( (int) $demo ) );
		}
	}
}

/**
 * One-time cleanup of old Strut pages that were superseded by the Fischer range.
 */
add_action( 'init', 'osoul_cleanup_old_strut_pages' );
if ( ! function_exists( 'osoul_cleanup_old_strut_pages' ) ) {
	function osoul_cleanup_old_strut_pages() {
		if ( get_option( 'osoul_cleanup_strut_v1' ) ) {
			return;
		}
		$old_slugs = array(
			'strut-channel-4141', 'strut-channel-4121', 'pipe-clamp',
			'spring-nut', 'u-bolt', 'strut-connector', 'channel-hanger', 'strut-accessories',
		);
		foreach ( $old_slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				wp_delete_post( $page->ID, true );
			}
		}
		update_option( 'osoul_cleanup_strut_v1', 1 );
	}
}
