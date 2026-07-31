<?php
/**
 * Plugin Name:       مدرسة الفلك المنير — Al-Falak Al-Munir Quran School
 * Plugin URI:        https://alfalak-almunir.com
 * Description:        موقع «مدرسة الفلك المنير» (مدرسة عامة) — واجهة عربية RTL كاملة + داش بورد مستقلة ببوابة دخول لإدارة التسجيلات والمعرض والمعلمين والتقييمات، ونظام تسجيل طلاب احترافي بمراحل ديناميكية حسب النوع، وسلايدرات معرض وتقييمات. مبنية على نمط إضافة «أصول البناء».
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            مدرسة الفلك المنير
 * Text Domain:       falak
 *
 * بنية معيارية (modules) مستوحاة من إضافة osoul-site، لكن مبسّطة ومخصّصة لمدرسة عامة:
 * الواجهة عربية فقط (RTL)، بألوان أخضر/أبيض/ذهبي، مع نظام تسجيل طلاب بديل عن نظام عروض الأسعار.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// حماية من التحميل المزدوج.
if ( defined( 'FALAK_VERSION' ) ) { return; }

define( 'FALAK_VERSION', '2.0.0' );
define( 'FALAK_FILE', __FILE__ );
define( 'FALAK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALAK_URL', plugin_dir_url( __FILE__ ) );

/**
 * محمّل الوحدات — الترتيب مهم:
 * helpers توفّر أدوات مشتركة، security تُحصّن الطلبات قبل قراءتها،
 * ثم بقية الوحدات (البيانات، الصفحات، القوالب، الأصول).
 */
$falak_modules = array(
	'inc/helpers.php',       // أدوات مشتركة (روابط آمنة، معلومات المدرسة)
	'inc/settings.php',      // معلومات التواصل + صفحة إعدادات في الأدمن
	'inc/security.php',      // تحصين الطلبات، إزالة بصمات النسخة، رؤوس أمان
	'inc/programs.php',      // المراحل والبرامج (بيانات افتراضية + CPT قابل للإدارة)
	'inc/content.php',       // أنواع المحتوى: المعرض + المعلمون + التقييمات
	'inc/enroll.php',        // نظام التسجيل: REST + حفظ + بريد
	'inc/reviews.php',       // التقييمات: صفحة عامة + REST + عرض الشهادات
	'inc/dashboard.php',     // الداش بورد المستقلة + بوابة الدخول
	'inc/pages.php',         // إنشاء صفحات ووردبريس المطلوبة
	'inc/performance.php',   // تحميل الأصول + تلميحات الموارد + تنظيف
	'inc/seo.php',           // ميتا، canonical، schema.org
	'inc/favicon.php',       // أيقونة الموقع (favicon)
	'inc/header.php',        // ترويسة الموقع
	'inc/footer.php',        // تذييل الموقع + الأزرار العائمة
	'inc/templates.php',     // محمّل قوالب الصفحات
);

foreach ( $falak_modules as $falak_module ) {
	$falak_path = FALAK_DIR . $falak_module;
	if ( is_readable( $falak_path ) ) {
		require_once $falak_path;
	}
}
unset( $falak_modules, $falak_module, $falak_path );

/**
 * التفعيل: إنشاء الصفحات، زرع البرامج، وتحديث قواعد الروابط.
 */
register_activation_hook( __FILE__, 'falak_activate' );
function falak_activate() {
	if ( function_exists( 'falak_register_program_cpt' ) ) { falak_register_program_cpt(); }
	if ( function_exists( 'falak_register_enroll_cpt' ) ) { falak_register_enroll_cpt(); }
	if ( function_exists( 'falak_register_content_cpts' ) ) { falak_register_content_cpts(); }
	if ( function_exists( 'falak_seed_programs' ) ) { falak_seed_programs(); }
	if ( function_exists( 'falak_create_roles' ) ) { falak_create_roles(); }
	if ( function_exists( 'falak_create_missing_pages' ) ) { falak_create_missing_pages( true ); }
	update_option( 'falak_ver', FALAK_VERSION );
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

/**
 * ترقية تلقائية للمواقع المفعّلة سابقًا (تُنشئ الصفحات/الأدوار الجديدة دون إعادة تفعيل).
 */
add_action( 'init', 'falak_maybe_upgrade', 99 );
function falak_maybe_upgrade() {
	if ( get_option( 'falak_ver' ) === FALAK_VERSION ) {
		return;
	}
	if ( function_exists( 'falak_create_roles' ) ) { falak_create_roles(); }
	if ( function_exists( 'falak_create_missing_pages' ) ) { falak_create_missing_pages( true ); }
	if ( function_exists( 'falak_seed_programs' ) ) { falak_seed_programs(); }
	update_option( 'falak_ver', FALAK_VERSION );
	flush_rewrite_rules();
}
