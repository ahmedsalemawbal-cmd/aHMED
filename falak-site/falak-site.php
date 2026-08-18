<?php
/**
 * Plugin Name:       مدرسة الفلك المنير — Al-Falak Al-Munir Quran School
 * Plugin URI:        https://alfalak-almunir.com
 * Description:        موقع «مدرسة الفلك المنير» (مدرسة عامة) — واجهة عربية RTL كاملة + داش بورد مستقلة ببوابة دخول لإدارة التسجيلات والمعرض والمعلمين والتقييمات، ونظام تسجيل طلاب احترافي بمراحل ديناميكية حسب النوع، وسلايدرات معرض وتقييمات. مبنية على نمط إضافة «أصول البناء».
 * Version:           2.4.0
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

define( 'FALAK_VERSION', '2.4.0' );
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
	'inc/careers.php',       // التوظيف: صفحة + REST + CPT (بديل المعلمون)
	'inc/dashboard.php',     // الداش بورد المستقلة + بوابة الدخول
	'inc/pages.php',         // إنشاء صفحات ووردبريس المطلوبة
	'inc/performance.php',   // تحميل الأصول + تلميحات الموارد + تنظيف
	'inc/seo.php',           // ميتا، canonical، schema.org
	'inc/tracking.php',      // بكسل تيك توك + أحداث التحويل
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
	if ( function_exists( 'falak_register_job_cpt' ) ) { falak_register_job_cpt(); }
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
	$need = ( get_option( 'falak_ver' ) !== FALAK_VERSION );

	// شبكة أمان: لو صفحة «لوحة التحكم» مفقودة (حُذفت أو لم تُنشأ) — سبب شائع لتحويل /dashboard/
	// إلى الرئيسية — فعّل الترقية لإعادة إنشائها وتحديث روابط ووردبريس.
	if ( ! $need && function_exists( 'falak_page_id' ) && ! falak_page_id( 'dashboard' ) ) {
		$need = true;
	}
	if ( ! $need ) {
		return;
	}

	if ( function_exists( 'falak_create_roles' ) ) { falak_create_roles(); }

	// إزالة صفحة «المعلمون» القديمة (استُبدلت بصفحة «التوظيف»).
	$falak_old = get_page_by_path( 'teachers' );
	if ( $falak_old instanceof WP_Post ) {
		wp_delete_post( $falak_old->ID, true );
		$falak_ids = get_option( 'falak_page_ids', array() );
		unset( $falak_ids['teachers'] );
		update_option( 'falak_page_ids', $falak_ids );
	}

	if ( function_exists( 'falak_create_missing_pages' ) ) { falak_create_missing_pages( true ); }
	if ( function_exists( 'falak_seed_programs' ) ) { falak_seed_programs(); }

	// ترحيل الشعار إلى الشعار الرسمي للمدرسة إن كان لا يزال على الافتراضي القديم
	// (لا يُلمَس إن كان المستخدم قد عيّن شعارًا خاصًا به).
	$falak_opts     = get_option( 'falak_opts', array() );
	$falak_old_logo = 'https://ahmedawbal.com/wp-content/uploads/2026/07/%D8%AA%D8%B5%D9%85%D9%8A%D9%85-%D8%A8%D8%AF%D9%88%D9%86-%D8%B9%D9%86%D9%88%D8%A7%D9%86-89.png';
	if ( is_array( $falak_opts ) && isset( $falak_opts['logo'] ) && $falak_opts['logo'] === $falak_old_logo ) {
		$falak_opts['logo'] = falak_default_opts()['logo'];
		update_option( 'falak_opts', $falak_opts );
	}

	update_option( 'falak_ver', FALAK_VERSION );
	flush_rewrite_rules();
}
