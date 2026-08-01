<?php
/**
 * أدوات مشتركة تُستخدم عبر الإضافة.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * يبني عنوان URL الحالي من home_url() الموثوق (يتجنّب حقن Host header).
 */
function falak_current_url() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
	$path = is_string( $path ) ? $path : '/';
	return home_url( $path );
}

/**
 * رقم الجوال بصيغة دولية نظيفة (أرقام فقط) للاستخدام مع wa.me.
 */
function falak_wa_number() {
	$raw = falak_opt( 'whatsapp' );
	$num = preg_replace( '/\D+/', '', (string) $raw );
	return $num;
}

/**
 * رابط واتساب جاهز مع رسالة اختيارية.
 */
function falak_wa_url( $message = '' ) {
	$num = falak_wa_number();
	if ( '' === $num ) {
		return '#';
	}
	$url = 'https://wa.me/' . $num;
	if ( '' !== $message ) {
		$url .= '?text=' . rawurlencode( $message );
	}
	return $url;
}

/**
 * رابط اتصال هاتفي نظيف.
 */
function falak_tel_url() {
	$raw = falak_opt( 'phone' );
	$num = preg_replace( '/[^\d+]/', '', (string) $raw );
	return 'tel:' . $num;
}

/**
 * قائمة أقسام المدرسة (بنين/بنات) مع مراحلها.
 */
function falak_sections() {
	return array(
		'boys'  => array(
			'key'    => 'boys',
			'label'  => 'قسم البنين',
			'stages' => 'من الحضانة إلى الصف السادس الابتدائي',
			'icon'   => 'boy',
		),
		'girls' => array(
			'key'    => 'girls',
			'label'  => 'قسم البنات',
			'stages' => 'من الحضانة إلى الصف الثالث الثانوي',
			'icon'   => 'girl',
		),
	);
}

/**
 * مراحل قسم البنين (حضانة → السادس الابتدائي).
 */
function falak_grades_boys() {
	return array(
		'حضانة',
		'تمهيدي / روضة',
		'الصف الأول الابتدائي',
		'الصف الثاني الابتدائي',
		'الصف الثالث الابتدائي',
		'الصف الرابع الابتدائي',
		'الصف الخامس الابتدائي',
		'الصف السادس الابتدائي',
	);
}

/**
 * مراحل قسم البنات (حضانة → الثالث الثانوي).
 */
function falak_grades_girls() {
	return array(
		'حضانة',
		'تمهيدي / روضة',
		'الصف الأول الابتدائي',
		'الصف الثاني الابتدائي',
		'الصف الثالث الابتدائي',
		'الصف الرابع الابتدائي',
		'الصف الخامس الابتدائي',
		'الصف السادس الابتدائي',
		'الصف الأول المتوسط',
		'الصف الثاني المتوسط',
		'الصف الثالث المتوسط',
		'الصف الأول الثانوي',
		'الصف الثاني الثانوي',
		'الصف الثالث الثانوي',
	);
}

/**
 * كل المراحل (للاستخدام العام) — تُرجع أطول قائمة (البنات).
 */
function falak_grades() {
	return falak_grades_girls();
}

/**
 * مراحل حسب النوع ('بنين' أو 'بنات').
 */
function falak_grades_for( $section ) {
	return ( 'بنين' === $section ) ? falak_grades_boys() : falak_grades_girls();
}

/**
 * أيقونات SVG المشتركة (خطية، تُلوّن عبر stroke=currentColor).
 * تُرجع محتوى <svg> الداخلي فقط (بدون وسم svg).
 */
function falak_icon_path( $name ) {
	$icons = array(
		'quran'    => '<path d="M4 4v16a2 2 0 0 1 2-2h14V2H6a2 2 0 0 0-2 2z"/><path d="M20 18H6a2 2 0 0 0-2 2"/><path d="M12 7v6M9.5 9.5h5"/>',
		'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
		'star'     => '<path d="M12 2l2.9 6.2 6.8.9-4.9 4.7 1.2 6.7L12 17.8 5.9 21l1.2-6.7L2.2 9.6l6.8-.9z"/>',
		'mosque'   => '<path d="M3 21V11c0-3 4-4 4-7 0 3 4 4 4 7v10"/><path d="M13 21v-6a3 3 0 0 1 6 0v6"/><path d="M2 21h20"/>',
		'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'boy'      => '<circle cx="12" cy="6" r="3"/><path d="M12 9v7M8 22l1-6M16 22l-1-6M9 13h6"/>',
		'girl'     => '<circle cx="12" cy="6" r="3"/><path d="M9 22l3-9 3 9M8 22h8M10 13h4"/>',
		'check'    => '<polyline points="20 6 9 17 4 12"/>',
		'award'    => '<circle cx="12" cy="8" r="6"/><path d="M8.2 13.5L7 22l5-3 5 3-1.2-8.5"/>',
		'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
		'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1A19.5 19.5 0 0 1 3.1 9.8 19.8 19.8 0 0 1 .1 1.2 2 2 0 0 1 2.1 0h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L6.9 7.1a16 16 0 0 0 6 6l.5-.5a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
		'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>',
		'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2z"/><path d="M8.5 8.2c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .6.5l.7 1.7c.1.2 0 .4-.1.5l-.5.6c-.1.1-.2.3-.1.5a5 5 0 0 0 2.4 2.3c.2.1.4 0 .5-.1l.5-.6c.1-.2.3-.2.5-.1l1.6.8c.2.1.3.3.3.5v.5c0 .5-.5 1-1 1.1-.4.1-1 .2-3-.6a8 8 0 0 1-3.8-3.8c-.8-2-.7-2.6-.6-3z"/>',
		'clock2'   => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
		'arrow'    => '<path d="M5 12h14M12 5l7 7-7 7"/>',
		'arrow-l'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
		'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.4 2.4M15.3 15.3l2.4 2.4M17.7 6.3l-2.4 2.4M8.7 15.3l-2.4 2.4"/>',
		'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
		'target'   => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
		'compass'  => '<circle cx="12" cy="12" r="10"/><polygon points="16.2 7.8 14.1 14.1 7.8 16.2 9.9 9.9"/>',
		'menu'     => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
		'close'    => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
		'chevron'  => '<polyline points="6 9 12 15 18 9"/>',
		'play'     => '<polygon points="5 3 19 12 5 21 5 3"/>',
		'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['star'];
}

/**
 * يطبع أيقونة SVG جاهزة.
 */
function falak_icon( $name, $size = 24, $class = '' ) {
	printf(
		'<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		esc_attr( $class ),
		(int) $size,
		(int) $size,
		falak_icon_path( $name ) // phpcs:ignore WordPress.Security.EscapeOutput — مسارات SVG ثابتة
	);
}

/**
 * نمط زخرفي إسلامي (SVG) كـ data-URI للاستخدام في الخلفيات.
 *
 * @param string $color لون الخط (hex بدون #) الافتراضي أخضر.
 * @param float  $opacity الشفافية.
 */
function falak_pattern_data_uri( $color = '1F9D5A', $opacity = '0.06' ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60">'
		. '<g fill="none" stroke="#' . $color . '" stroke-opacity="' . $opacity . '" stroke-width="1">'
		. '<path d="M30 0l15 15-15 15-15-15z"/><path d="M0 30l15 15L0 60z" opacity="0.6"/>'
		. '<path d="M60 30l-15 15L60 60z" opacity="0.6"/><circle cx="30" cy="30" r="6"/>'
		. '</g></svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}

/**
 * علامة مائية أنيقة للهيرو — نجمة ثمانية (خَتم) متباعدة وراقية بدل النقش الكثيف.
 *
 * @param string $color لون الخط (hex بدون #).
 * @param string $opacity شفافية الخط داخل الـ SVG.
 */
function falak_pattern_hero( $color = 'C6A15B', $opacity = '0.55' ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="132" height="132" viewBox="0 0 132 132">'
		. '<g fill="none" stroke="#' . $color . '" stroke-opacity="' . $opacity . '" stroke-width="1" stroke-linejoin="round">'
		. '<rect x="34" y="34" width="64" height="64" rx="3"/>'
		. '<rect x="34" y="34" width="64" height="64" rx="3" transform="rotate(45 66 66)"/>'
		. '<circle cx="66" cy="66" r="12"/>'
		. '</g></svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}
