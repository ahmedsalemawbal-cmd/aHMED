<?php
/**
 * تحصينات أمان خفيفة (مطابقة لنهج إضافة أصول).
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// إزالة بصمة إصدار ووردبريس من الـ head والخلاصات.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// تعطيل XML-RPC (سطح هجوم غير مستخدم لموقع تعريفي).
add_filter( 'xmlrpc_enabled', '__return_false' );

// رؤوس أمان آمنة لا تكسر شيئًا.
add_action( 'send_headers', function () {
	if ( is_admin() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
} );

// إزالة روابط الـ REST/oEmbed غير الضرورية من الـ head (تنظيف).
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wlwmanifest_link' );
