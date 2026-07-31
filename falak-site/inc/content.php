<?php
/**
 * أنواع المحتوى التي تُدار من الداش بورد المستقلة:
 *   - falak_photo   : صور المعرض (سلايدر)
 *   - falak_teacher : المعلمون (صورة + اسم + مسمّى)
 *   - falak_review  : تقييمات أولياء الأمور
 *
 * جميعها show_ui=false — لا تظهر في لوحة ووردبريس، وتُدار من صفحة «لوحة التحكم» في الموقع.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', 'falak_register_content_cpts' );
function falak_register_content_cpts() {
	$common = array(
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'has_archive'  => false,
		'rewrite'      => false,
		'supports'     => array( 'title', 'editor' ),
	);

	register_post_type( 'falak_photo', array_merge( $common, array(
		'labels'   => array( 'name' => 'المعرض', 'singular_name' => 'صورة' ),
		'supports' => array( 'title' ),
	) ) );

	register_post_type( 'falak_teacher', array_merge( $common, array(
		'labels'   => array( 'name' => 'المعلمون', 'singular_name' => 'معلم' ),
		'supports' => array( 'title' ),
	) ) );

	register_post_type( 'falak_review', array_merge( $common, array(
		'labels'   => array( 'name' => 'التقييمات', 'singular_name' => 'تقييم' ),
		'supports' => array( 'title', 'editor' ),
	) ) );
}

/* ─────────────────────────────────────────
   المعرض
───────────────────────────────────────────*/

/**
 * صور المعرض المرفوعة.
 *
 * @return array عناصر: id, img, caption.
 */
function falak_get_photos( $limit = 0 ) {
	$posts = get_posts( array(
		'post_type'   => 'falak_photo',
		'post_status' => 'publish',
		'numberposts' => $limit > 0 ? $limit : -1,
		'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$img = get_post_meta( $p->ID, '_falak_img', true );
		if ( ! $img ) {
			continue;
		}
		$out[] = array(
			'id'      => $p->ID,
			'img'     => $img,
			'caption' => $p->post_title,
		);
	}
	return $out;
}

/* ─────────────────────────────────────────
   المعلمون
───────────────────────────────────────────*/

/**
 * قائمة المعلمين الافتراضية (تظهر قبل إضافة معلمين حقيقيين).
 */
function falak_default_teachers() {
	return array(
		array( 'name' => 'أ. / أحمد العتيبي', 'role' => 'مدير المدرسة', 'note' => 'إدارة تربوية', 'img' => '' ),
		array( 'name' => 'أ. / عبدالرحمن', 'role' => 'مشرف قسم البنين', 'note' => 'خبرة تربوية', 'img' => '' ),
		array( 'name' => 'أ. / خالد', 'role' => 'معلم رياضيات', 'note' => 'بكالوريوس رياضيات', 'img' => '' ),
		array( 'name' => 'أ. / يوسف', 'role' => 'معلم لغة عربية', 'note' => 'بكالوريوس لغة عربية', 'img' => '' ),
		array( 'name' => 'أ. / فاطمة', 'role' => 'مشرفة قسم البنات', 'note' => 'خبرة تربوية', 'img' => '' ),
		array( 'name' => 'أ. / مريم', 'role' => 'معلمة علوم', 'note' => 'بكالوريوس علوم', 'img' => '' ),
		array( 'name' => 'أ. / نورة', 'role' => 'معلمة لغة إنجليزية', 'note' => 'بكالوريوس لغات', 'img' => '' ),
		array( 'name' => 'أ. / سارة', 'role' => 'معلمة رياض أطفال', 'note' => 'تخصّص طفولة مبكّرة', 'img' => '' ),
	);
}

/**
 * المعلمون للعرض — من الداش بورد، أو الافتراضية إن لم يُضَف أحد.
 */
function falak_get_teachers() {
	$posts = get_posts( array(
		'post_type'   => 'falak_teacher',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
	if ( empty( $posts ) ) {
		return falak_default_teachers();
	}
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'   => $p->ID,
			'name' => $p->post_title,
			'role' => get_post_meta( $p->ID, '_falak_role', true ),
			'note' => get_post_meta( $p->ID, '_falak_note', true ),
			'img'  => get_post_meta( $p->ID, '_falak_img', true ),
		);
	}
	return $out;
}

/* ─────────────────────────────────────────
   التقييمات
───────────────────────────────────────────*/

/**
 * تقييمات افتراضية (تظهر قبل ورود تقييمات حقيقية معتمدة).
 */
function falak_default_reviews() {
	return array(
		array( 'name' => 'أم عبدالله', 'role' => 'ولية أمر', 'rating' => 5, 'text' => 'تحسّن مستوى ابني الدراسي بشكلٍ ملحوظ، ولمستُ اهتمامًا حقيقيًّا من المعلمين والإدارة. مدرسةٌ نثق بها.' ),
		array( 'name' => 'أبو سارة', 'role' => 'ولي أمر', 'rating' => 5, 'text' => 'المتابعة الفردية والتقارير الدورية طمأنتني كثيرًا. بيئةٌ راقيةٌ ومعاملةٌ تربويةٌ رائعة لبناتنا.' ),
		array( 'name' => 'أم يوسف', 'role' => 'ولية أمر', 'rating' => 5, 'text' => 'مرحلة رياض الأطفال ممتازة؛ ابنتي أحبّت مدرستها وصارت تنتظر يومها الدراسي بشغف. شكرًا للفلك المنير.' ),
	);
}

/**
 * التقييمات المعتمدة (published) للعرض في السلايدر — أو الافتراضية إن لم يوجد.
 */
function falak_get_reviews_display() {
	$posts = get_posts( array(
		'post_type'   => 'falak_review',
		'post_status' => 'publish',
		'numberposts' => 30,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
	if ( empty( $posts ) ) {
		return falak_default_reviews();
	}
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'     => $p->ID,
			'name'   => $p->post_title,
			'role'   => get_post_meta( $p->ID, '_falak_role', true ) ?: 'ولي أمر',
			'rating' => (int) ( get_post_meta( $p->ID, '_falak_rating', true ) ?: 5 ),
			'text'   => $p->post_content,
		);
	}
	return $out;
}

/**
 * عدّ التقييمات حسب الحالة (للداش بورد).
 */
function falak_count_reviews( $status ) {
	$posts = get_posts( array(
		'post_type'   => 'falak_review',
		'post_status' => $status,
		'numberposts' => -1,
		'fields'      => 'ids',
	) );
	return count( $posts );
}
