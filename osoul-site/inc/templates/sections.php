<?php
/**
 * Section pages: services, single service, projects, blog archive, blog single,
 * contact, privacy. Page-specific CSS is inline (loads only on that page).
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
 * Services — /services/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_services_output', 5 );
if ( ! function_exists( 'osoul_services_output' ) ) {
	function osoul_services_output() {
		if ( ! is_page( 'services' ) ) {
			return;
		}
		$home = home_url( '/' );
		$services = array(
			array( 'ico' => '<path d="M5 18H3a2 2 0 01-2-2V8a2 2 0 012-2h3.19M15 6h2a2 2 0 012 2v8a2 2 0 01-2 2h-3.19"/><line x1="23" y1="13" x2="23" y2="11"/><polyline points="11 6 7 12 13 12 9 18"/>', 'h' => 'توريد المواد والمنتجات', 'h_en' => 'Material & Product Supply', 'd' => 'نوفر جميع احتياجاتك من الأبواب المعدنية وأنظمة الجبس بورد وإكسسوارات التثبيت بأفضل الأسعار وأعلى معايير الجودة', 'd_en' => 'We supply all your needs of metal doors, gypsum board systems and fixing accessories at best prices and highest quality standards', 'slug' => 'service-supply' ),
			array( 'ico' => '<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>', 'h' => 'التركيب والتنفيذ الاحترافي', 'h_en' => 'Professional Installation', 'd' => 'فريق متخصص من الفنيين المدربين لتركيب جميع منتجاتنا بدقة عالية وضمان الجودة في التنفيذ', 'd_en' => 'Specialized team of trained technicians to install all our products with high precision and quality assurance', 'slug' => 'service-install' ),
			array( 'ico' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'h' => 'الضمان الشامل وما بعد البيع', 'h_en' => 'Comprehensive Warranty & After-Sales', 'd' => 'ضمان شامل على جميع منتجاتنا مع خدمات ما بعد البيع لضمان استمرارية عمل المنتجات بكفاءة', 'd_en' => 'Comprehensive warranty on all products with after-sales services to ensure continued product efficiency', 'slug' => 'service-guarantee' ),
			array( 'ico' => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M16.24 7.76a6 6 0 010 8.49M4.93 19.07a10 10 0 010-14.14M7.76 16.24a6 6 0 010-8.49"/>', 'h' => 'الاستشارة الفنية والهندسية', 'h_en' => 'Technical & Engineering Consulting', 'd' => 'مهندسون متخصصون لتقديم الاستشارات الفنية الدقيقة واختيار المنتج الأمثل لمتطلبات مشروعك', 'd_en' => 'Specialized engineers providing precise technical consultations and optimal product selection for your project', 'slug' => 'service-consulting' ),
			array( 'ico' => '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', 'h' => 'الصيانة الدورية والطارئة', 'h_en' => 'Periodic & Emergency Maintenance', 'd' => 'خدمات صيانة متكاملة بجداول منتظمة واستجابة سريعة للحالات الطارئة للحفاظ على عمر المنتجات', 'd_en' => 'Comprehensive maintenance services with regular schedules and fast emergency response to extend product life', 'slug' => 'service-maintenance' ),
			array( 'ico' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>', 'h' => 'الفحص والتفتيش الفني', 'h_en' => 'Technical Inspection & Testing', 'd' => 'خدمات فحص وتفتيش متخصصة للتحقق من مطابقة المنتجات للمواصفات القياسية والمعايير الدولية', 'd_en' => 'Specialized inspection and testing services to verify product compliance with standard and international specifications', 'slug' => 'service-inspection' ),
		);
		?>
<style id="osoul-services-css">
.osrv{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .osrv{direction:ltr}
.osrv-hero{background:#0d1f3c;padding:80px 60px 64px;position:relative;overflow:hidden}
.osrv-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.osrv-hero-inner{position:relative;z-index:1;max-width:860px}
.osrv-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.osrv-tag::before{content:'';width:28px;height:1px;background:#D21217}
.osrv-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(32px,5vw,54px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:14px}
.osrv-h1 i{font-style:italic;color:#D21217}
.osrv-sub{font-size:15px;color:rgba(255,255,255,.6);max-width:560px;line-height:1.65;font-weight:300}
.osrv-body{max-width:1280px;margin:0 auto;padding:72px 60px 88px}
.osrv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:80px}
.osrv-card{background:#fff;border-radius:10px;padding:36px 32px;box-shadow:0 4px 24px rgba(13,31,60,.07);transition:all .3s;display:flex;flex-direction:column;direction:rtl;position:relative;overflow:hidden}
body.lang-en .osrv-card{direction:ltr}
.osrv-card::before{content:'';position:absolute;top:0;right:0;left:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent);opacity:0;transition:opacity .3s}
.osrv-card:hover{box-shadow:0 12px 40px rgba(13,31,60,.12);transform:translateY(-4px)}
.osrv-card:hover::before{opacity:1}
.osrv-ic{width:52px;height:52px;background:rgba(210,18,23,.06);border:1px solid rgba(210,18,23,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;transition:all .3s}
.osrv-card:hover .osrv-ic{background:#D21217;border-color:#D21217}
.osrv-ic svg{width:24px;height:24px;stroke:#D21217;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;transition:stroke .3s}
.osrv-card:hover .osrv-ic svg{stroke:#fff}
.osrv-card-h{font-size:17px;font-weight:700;color:#0d1f3c;line-height:1.3;margin-bottom:12px}
.osrv-card-d{font-size:14px;color:rgba(13,31,60,.58);line-height:1.65;font-weight:300;margin-bottom:24px;flex:1}
.osrv-card-cta{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:#D21217;text-decoration:none;letter-spacing:1px;text-transform:uppercase;transition:gap .2s;min-height:32px}
.osrv-card-cta:hover{gap:12px}
.osrv-card-cta svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.osrv-cta-bar{background:#0d1f3c;border-radius:12px;padding:52px 56px;text-align:center;position:relative;overflow:hidden}
.osrv-cta-bar::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.osrv-cta-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:30px;font-weight:700;color:#fff;margin-bottom:10px;position:relative}
.osrv-cta-h i{font-style:italic;color:#D21217}
.osrv-cta-d{font-size:15px;color:rgba(255,255,255,.55);margin-bottom:32px;position:relative;font-weight:300}
.osrv-cta-btns{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;position:relative}
.osrv-cta-bp{display:inline-flex;align-items:center;gap:8px;background:#D21217;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:14px 32px;text-decoration:none;border-radius:3px;transition:background .25s;min-height:44px}
.osrv-cta-bp:hover{background:#e8464b}
.osrv-cta-bs{display:inline-flex;align-items:center;gap:8px;background:transparent;color:rgba(255,255,255,.8);font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:600;letter-spacing:1px;padding:14px 24px;text-decoration:none;border:1.5px solid rgba(255,255,255,.25);border-radius:3px;transition:all .25s;min-height:44px}
.osrv-cta-bs:hover{border-color:rgba(255,255,255,.7)}
@media(max-width:1024px){.osrv-grid{grid-template-columns:repeat(2,1fr)}.osrv-body{padding:52px 28px 64px}.osrv-hero{padding:60px 28px 44px}.osrv-cta-bar{padding:40px 28px}}
@media(max-width:600px){.osrv-grid{grid-template-columns:1fr}.osrv-cta-btns{flex-direction:column;align-items:stretch}}
</style>
<div id="osrv-wrap" class="osrv">
<div class="osrv-hero"><div class="osrv-hero-inner">
	<div class="osrv-tag"><?php osoul_bilingual( 'خدماتنا', 'Our Services' ); ?></div>
	<h1 class="osrv-h1"><span data-lang="ar">خدمات شاملة <i>من الفكرة للتسليم</i></span><span data-lang="en">Comprehensive Services <i>From Idea to Delivery</i></span></h1>
	<p class="osrv-sub"><?php osoul_bilingual( 'نقدم منظومة متكاملة من الخدمات الصناعية والاستشارات الهندسية لضمان نجاح مشروعك', 'We provide a complete suite of industrial services and engineering consultations to ensure your project success' ); ?></p>
</div></div>
<div class="osrv-body">
	<div class="osrv-grid">
		<?php foreach ( $services as $srv ) : ?>
		<div class="osrv-card">
			<div class="osrv-ic"><svg viewBox="0 0 24 24"><?php echo $srv['ico']; // phpcs:ignore WordPress.Security.EscapeOutput — static SVG path ?></svg></div>
			<div class="osrv-card-h"><?php osoul_bilingual( $srv['h'], $srv['h_en'] ); ?></div>
			<p class="osrv-card-d"><?php osoul_bilingual( $srv['d'], $srv['d_en'] ); ?></p>
			<a href="<?php echo esc_url( $home . $srv['slug'] . '/' ); ?>" class="osrv-card-cta"><?php osoul_bilingual( 'اعرف المزيد', 'Learn More' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="osrv-cta-bar">
		<h2 class="osrv-cta-h"><span data-lang="ar">هل تحتاج خدمة <i>مخصصة لمشروعك؟</i></span><span data-lang="en">Need a <i>Custom Service for Your Project?</i></span></h2>
		<p class="osrv-cta-d"><?php osoul_bilingual( 'فريقنا متاح للرد على استفساراتك وتقديم عرض سعر مخصص', 'Our team is available to answer your inquiries and provide a customized quote' ); ?></p>
		<div class="osrv-cta-btns">
			<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="osrv-cta-bp"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
			<a href="https://wa.me/966556847029" target="_blank" rel="noopener" class="osrv-cta-bs"><?php osoul_bilingual( 'واتساب', 'WhatsApp' ); ?></a>
		</div>
	</div>
</div>
</div>
<script><?php echo osoul_promote_script( 'osrv-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Single service pages
 * ========================================================= */
add_action( 'wp_footer', 'osoul_single_service_output', 5 );
if ( ! function_exists( 'osoul_single_service_output' ) ) {
	function osoul_single_service_output() {
		$service_pages = array( 'service-supply', 'service-install', 'service-guarantee', 'service-consulting', 'service-maintenance', 'service-inspection' );
		$current = '';
		foreach ( $service_pages as $sp ) {
			if ( is_page( $sp ) ) { $current = $sp; break; }
		}
		if ( ! $current ) {
			return;
		}
		$home = home_url( '/' );
		$data = array(
			'service-supply'      => array( 'h' => 'توريد المواد والمنتجات', 'h_en' => 'Material & Product Supply', 'ico' => '<path d="M5 18H3a2 2 0 01-2-2V8a2 2 0 012-2h3.19M15 6h2a2 2 0 012 2v8a2 2 0 01-2 2h-3.19"/><line x1="23" y1="13" x2="23" y2="11"/><polyline points="11 6 7 12 13 12 9 18"/>', 'desc' => 'نوفر جميع احتياجاتك من الأبواب المعدنية وأنظمة الجبس بورد وإكسسوارات التثبيت بأفضل الأسعار وأعلى معايير الجودة المعتمدة دولياً.', 'desc_en' => 'We supply all your needs of metal doors, gypsum board systems and fixing accessories at best prices with internationally certified quality standards.', 'pts' => array( 'منتجات مطابقة لمعايير ISO وSASO', 'توريد بكميات مشاريع كبيرة وصغيرة', 'توصيل وتغليف احترافي', 'أسعار تنافسية مع ضمان الجودة' ), 'pts_en' => array( 'ISO & SASO compliant products', 'Supply for large and small project quantities', 'Professional delivery and packaging', 'Competitive prices with quality guarantee' ) ),
			'service-install'     => array( 'h' => 'التركيب والتنفيذ الاحترافي', 'h_en' => 'Professional Installation', 'ico' => '<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>', 'desc' => 'فريق متخصص من الفنيين المدربين لتركيب جميع منتجاتنا بدقة عالية وضمان الجودة في التنفيذ مع الإشراف الهندسي الكامل.', 'desc_en' => 'Specialized team of trained technicians to install all our products with high precision and quality assurance with full engineering supervision.', 'pts' => array( 'فريق فني معتمد ومدرب', 'إشراف هندسي طوال فترة التنفيذ', 'تركيب وفق المواصفات الدولية', 'تسليم في الوقت المحدد' ), 'pts_en' => array( 'Certified and trained technical team', 'Engineering supervision throughout execution', 'Installation per international standards', 'On-time delivery' ) ),
			'service-guarantee'   => array( 'h' => 'الضمان الشامل وما بعد البيع', 'h_en' => 'Comprehensive Warranty & After-Sales', 'ico' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'desc' => 'ضمان شامل على جميع منتجاتنا مع خدمات ما بعد البيع لضمان استمرارية عمل المنتجات بكفاءة واحتراف.', 'desc_en' => 'Comprehensive warranty on all products with after-sales services to ensure continued product efficiency and professionalism.', 'pts' => array( 'ضمان شامل سنة وما فوق', 'استجابة سريعة للمشكلات', 'قطع غيار أصلية متوفرة', 'دعم فني مستمر' ), 'pts_en' => array( 'One year+ comprehensive warranty', 'Fast issue response', 'Original spare parts available', 'Continuous technical support' ) ),
			'service-consulting'  => array( 'h' => 'الاستشارة الفنية والهندسية', 'h_en' => 'Technical & Engineering Consulting', 'ico' => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M16.24 7.76a6 6 0 010 8.49M4.93 19.07a10 10 0 010-14.14M7.76 16.24a6 6 0 010-8.49"/>', 'desc' => 'مهندسون متخصصون لتقديم الاستشارات الفنية الدقيقة واختيار المنتج الأمثل لمتطلبات مشروعك.', 'desc_en' => 'Specialized engineers providing precise technical consultations and optimal product selection for your project requirements.', 'pts' => array( 'استشارات هندسية مخصصة', 'تقييم المتطلبات والمواصفات', 'اختيار المنتج الأمثل', 'تقديم التقارير والتوصيات' ), 'pts_en' => array( 'Customized engineering consultations', 'Requirements and spec evaluation', 'Optimal product selection', 'Reports and recommendations' ) ),
			'service-maintenance' => array( 'h' => 'الصيانة الدورية والطارئة', 'h_en' => 'Periodic & Emergency Maintenance', 'ico' => '<circle cx="12" cy="12" r="3"/><path d="M22 12h-4"/><path d="M6 12H2"/><path d="M12 6V2"/><path d="M12 22v-4"/>', 'desc' => 'خدمات صيانة متكاملة بجداول منتظمة واستجابة سريعة للحالات الطارئة للحفاظ على عمر المنتجات.', 'desc_en' => 'Comprehensive maintenance services with regular schedules and fast emergency response to extend product life.', 'pts' => array( 'جداول صيانة دورية', 'استجابة طارئة خلال 24 ساعة', 'فحص شامل ودوري', 'تقارير صيانة مفصلة' ), 'pts_en' => array( 'Periodic maintenance schedules', '24-hour emergency response', 'Comprehensive periodic inspection', 'Detailed maintenance reports' ) ),
			'service-inspection'  => array( 'h' => 'الفحص والتفتيش الفني', 'h_en' => 'Technical Inspection & Testing', 'ico' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>', 'desc' => 'خدمات فحص وتفتيش متخصصة للتحقق من مطابقة المنتجات للمواصفات القياسية والمعايير الدولية.', 'desc_en' => 'Specialized inspection and testing services to verify product compliance with standard and international specifications.', 'pts' => array( 'فحص مطابقة المواصفات', 'اختبارات مقاومة الحريق', 'تقارير الفحص المعتمدة', 'متابعة توصيات التحسين' ), 'pts_en' => array( 'Specification compliance testing', 'Fire resistance testing', 'Certified inspection reports', 'Improvement recommendation follow-up' ) ),
		);
		$sd = $data[ $current ];
		?>
<style id="osoul-singleservice-css">
.osi{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .osi{direction:ltr}
.osi-hero{background:#0d1f3c;padding:80px 60px 64px;position:relative;overflow:hidden}
.osi-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.osi-hero-inner{position:relative;z-index:1;max-width:860px}
.osi-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.osi-tag::before{content:'';width:28px;height:1px;background:#D21217}
.osi-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(30px,4.5vw,50px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:14px}
.osi-h1 i{font-style:italic;color:#D21217}
.osi-body{max-width:1100px;margin:0 auto;padding:64px 60px 80px}
.osi-grid{display:grid;grid-template-columns:1fr 1.1fr;gap:56px;align-items:start}
.osi-ic-wrap{width:64px;height:64px;background:rgba(210,18,23,.08);border:1.5px solid rgba(210,18,23,.25);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:28px}
.osi-ic-wrap svg{width:28px;height:28px;stroke:#D21217;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.osi-desc{font-size:15px;color:rgba(13,31,60,.7);line-height:1.75;font-weight:300;margin-bottom:32px}
.osi-pts h4{font-size:14px;font-weight:700;color:#0d1f3c;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px}
.osi-pts ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.osi-pts li{display:flex;align-items:center;gap:10px;font-size:14px;color:rgba(13,31,60,.7);line-height:1.6}
.osi-pts li::before{content:'';width:6px;height:6px;background:#D21217;border-radius:50%;flex-shrink:0}
.osi-cta-card{background:#0d1f3c;border-radius:10px;padding:40px 36px;position:sticky;top:128px}
.osi-cta-card h3{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:10px;line-height:1.3}
.osi-cta-card h3 i{font-style:italic;color:#D21217}
.osi-cta-card p{font-size:14px;color:rgba(255,255,255,.55);line-height:1.65;font-weight:300;margin-bottom:28px}
.osi-btn-p{display:flex;align-items:center;justify-content:center;gap:8px;background:#D21217;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:14px 24px;text-decoration:none;border-radius:3px;transition:background .25s;margin-bottom:12px;min-height:44px}
.osi-btn-p:hover{background:#e8464b}
.osi-btn-s{display:flex;align-items:center;justify-content:center;gap:8px;background:transparent;color:rgba(255,255,255,.75);font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:600;letter-spacing:1px;padding:14px 24px;text-decoration:none;border:1.5px solid rgba(255,255,255,.2);border-radius:3px;transition:all .25s;min-height:44px}
.osi-btn-s:hover{border-color:rgba(255,255,255,.6)}
.osi-back{display:inline-flex;align-items:center;gap:8px;color:rgba(13,31,60,.5);font-size:13px;font-weight:600;text-decoration:none;margin-bottom:40px;transition:color .2s;min-height:32px}
.osi-back:hover{color:#D21217}
.osi-back svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
@media(max-width:1024px){.osi-grid{grid-template-columns:1fr}.osi-cta-card{position:static}.osi-body{padding:48px 28px 60px}.osi-hero{padding:60px 28px 44px}}
</style>
<div id="osi-wrap" class="osi">
<div class="osi-hero"><div class="osi-hero-inner">
	<div class="osi-tag"><a href="<?php echo esc_url( $home . 'services/' ); ?>" style="color:#D21217;text-decoration:none"><?php osoul_bilingual( 'خدماتنا', 'Services' ); ?></a></div>
	<h1 class="osi-h1"><span data-lang="ar"><i><?php echo esc_html( $sd['h'] ); ?></i></span><span data-lang="en"><i><?php echo esc_html( $sd['h_en'] ); ?></i></span></h1>
</div></div>
<div class="osi-body">
	<a href="<?php echo esc_url( $home . 'services/' ); ?>" class="osi-back"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg><?php osoul_bilingual( 'العودة للخدمات', 'Back to Services' ); ?></a>
	<div class="osi-grid">
		<div>
			<div class="osi-ic-wrap"><svg viewBox="0 0 24 24"><?php echo $sd['ico']; // phpcs:ignore WordPress.Security.EscapeOutput — static SVG ?></svg></div>
			<p class="osi-desc"><?php osoul_bilingual( $sd['desc'], $sd['desc_en'] ); ?></p>
			<div class="osi-pts">
				<h4><?php osoul_bilingual( 'ما يميز خدمتنا', 'What Makes Our Service Stand Out' ); ?></h4>
				<ul>
					<?php for ( $i = 0; $i < count( $sd['pts'] ); $i++ ) : ?>
					<li><?php osoul_bilingual( $sd['pts'][ $i ], $sd['pts_en'][ $i ] ?? $sd['pts'][ $i ] ); ?></li>
					<?php endfor; ?>
				</ul>
			</div>
		</div>
		<div>
			<div class="osi-cta-card">
				<h3><span data-lang="ar">هل تحتاج <i>هذه الخدمة؟</i></span><span data-lang="en">Do You Need <i>This Service?</i></span></h3>
				<p><?php osoul_bilingual( 'فريقنا جاهز لمساعدتك — اتصل الآن أو أرسل لنا عبر واتساب', 'Our team is ready to help — call now or message us on WhatsApp' ); ?></p>
				<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="osi-btn-p"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
				<a href="https://wa.me/966556847029" target="_blank" rel="noopener" class="osi-btn-s"><?php osoul_bilingual( 'راسلنا على واتساب', 'Message on WhatsApp' ); ?></a>
			</div>
		</div>
	</div>
</div>
</div>
<script><?php echo osoul_promote_script( 'osi-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Projects — /projects/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_projects_output', 5 );
if ( ! function_exists( 'osoul_projects_output' ) ) {
	function osoul_projects_output() {
		if ( ! is_page( 'projects' ) ) {
			return;
		}
		$base = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/';
		$projects = array(
			array( 'cat' => 'حكومي', 'cat_en' => 'Government', 'h' => 'وزارة التعليم — توريد وتركيب أبواب مدارس جدة', 'h_en' => 'Ministry of Education — Supply & Install of School Doors, Jeddah', 'd' => 'توريد وتركيب أكثر من 800 باب مقاوم للحريق لمجموعة مدارس حكومية في مدينة جدة', 'd_en' => 'Supply and installation of 800+ fire-resistant doors for government schools in Jeddah', 'img' => 'DSC_2387-scaled.jpg', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2024' ),
			array( 'cat' => 'صحي', 'cat_en' => 'Healthcare', 'h' => 'مستشفى الملك فهد — أبواب أقسام الطوارئ', 'h_en' => 'King Fahd Hospital — Emergency Department Doors', 'd' => 'توريد أبواب معدنية بمواصفات خاصة لأقسام الطوارئ والعمليات والعزل في مستشفى الملك فهد', 'd_en' => 'Supply of special specification metal doors for emergency, surgical and isolation departments', 'img' => 'DSC_2382-scaled.jpg', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2024' ),
			array( 'cat' => 'تجاري', 'cat_en' => 'Commercial', 'h' => 'مجمع الرحاب التجاري — منظومة الجبس بورد', 'h_en' => 'Al Rehab Complex — Gypsum Board Systems', 'd' => 'توريد أنظمة البروفايلات الكاملة لألواح الجبسوم بورد في أسقف وجدران المجمع التجاري', 'd_en' => 'Complete supply of gypsum board profile systems for ceilings and walls of the commercial complex', 'img' => 'DSC_2368-scaled.jpg', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2025' ),
			array( 'cat' => 'صناعي', 'cat_en' => 'Industrial', 'h' => 'منشأة صناعية — المدينة الصناعية الثالثة', 'h_en' => 'Industrial Facility — 3rd Industrial City', 'd' => 'توريد وتركيب أبواب معدنية صناعية ومنظومة تثبيت Strut لمنشأة تصنيع في جدة', 'd_en' => 'Supply and installation of industrial metal doors and Strut fixing system for a Jeddah manufacturing facility', 'img' => 'DSC_2377-scaled.jpg', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2025' ),
			array( 'cat' => 'سكني', 'cat_en' => 'Residential', 'h' => 'مجمع بدر السكني — أبواب الوحدات', 'h_en' => 'Badr Residential Complex — Unit Doors', 'd' => 'توريد أبواب MDF وأبواب تكسية لوحدات سكنية متعددة في مجمع سكني حديث بحي الرحاب', 'd_en' => 'Supply of MDF and cladding doors for multiple residential units in a modern complex in Al Rehab', 'img' => 'DSC_2341-scaled-1.webp', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2025' ),
			array( 'cat' => 'تعليمي', 'cat_en' => 'Educational', 'h' => 'جامعة الملك عبدالعزيز — كليات هندسة', 'h_en' => 'King Abdulaziz University — Engineering Colleges', 'd' => 'توريد أبواب مقاومة للحريق لمباني الكليات الهندسية بمعايير الجامعة وبمواصفات دولية معتمدة', 'd_en' => 'Supply of fire-resistant doors for engineering college buildings per university standards and international specs', 'img' => 'DSC_2347-scaled.jpg', 'loc' => 'جدة', 'loc_en' => 'Jeddah', 'year' => '2024' ),
		);
		?>
<style id="osoul-projects-css">
.opg{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .opg{direction:ltr}
.opg-hero{background:#0d1f3c;padding:80px 60px 60px;position:relative;overflow:hidden}
.opg-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.opg-hero-inner{position:relative;z-index:1;max-width:1000px}
.opg-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.opg-tag::before{content:'';width:28px;height:1px;background:#D21217}
.opg-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(32px,5vw,56px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:14px}
.opg-h1 i{font-style:italic;color:#D21217}
.opg-sub{font-size:15px;color:rgba(255,255,255,.6);max-width:520px;line-height:1.65;font-weight:300}
.opg-body{max-width:1280px;margin:0 auto;padding:60px 48px 80px}
.opg-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(210,18,23,.15);margin-bottom:60px;border-radius:8px;overflow:hidden}
.opg-stat{background:#fff;padding:28px 24px;text-align:center;transition:background .25s}
.opg-stat:hover{background:#fdf9f6}
.opg-stat-n{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:36px;font-weight:700;color:#D21217;line-height:1}
.opg-stat-l{font-size:12px;color:rgba(13,31,60,.55);margin-top:6px;letter-spacing:1px;font-weight:500}
.opg-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.opg-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 24px rgba(13,31,60,.07);transition:all .3s;direction:rtl}
body.lang-en .opg-card{direction:ltr}
.opg-card:hover{box-shadow:0 12px 40px rgba(13,31,60,.14);transform:translateY(-4px)}
.opg-card-img{height:220px;overflow:hidden;position:relative;background:#0d1f3c}
.opg-card-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s}
.opg-card:hover .opg-card-img img{transform:scale(1.06)}
.opg-card-cat{position:absolute;top:14px;right:14px;background:#D21217;color:#fff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:4px 11px;border-radius:2px}
.opg-card-body{padding:24px}
.opg-card-year{font-size:11px;color:rgba(13,31,60,.4);letter-spacing:2px;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.opg-card-year::before{content:'';width:18px;height:1px;background:rgba(210,18,23,.4)}
.opg-card-h{font-size:16px;font-weight:700;color:#0d1f3c;line-height:1.4;margin-bottom:10px}
.opg-card-d{font-size:13px;color:rgba(13,31,60,.6);line-height:1.65;font-weight:300;margin-bottom:16px}
.opg-card-meta{display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(13,31,60,.5)}
.opg-card-meta svg{width:13px;height:13px;stroke:#D21217;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
@media(max-width:1024px){.opg-grid{grid-template-columns:repeat(2,1fr)}.opg-body{padding:48px 28px 60px}.opg-hero{padding:60px 28px 44px}}
@media(max-width:640px){.opg-grid{grid-template-columns:1fr}.opg-stats{grid-template-columns:repeat(2,1fr)}}
</style>
<div id="opg-wrap" class="opg">
<div class="opg-hero"><div class="opg-hero-inner">
	<div class="opg-tag"><?php osoul_bilingual( 'مشاريعنا المنجزة', 'Completed Projects' ); ?></div>
	<h1 class="opg-h1"><span data-lang="ar">إنجازاتنا <i>تتحدث عنا</i></span><span data-lang="en">Our Work <i>Speaks for Itself</i></span></h1>
	<p class="opg-sub"><?php osoul_bilingual( '+500 مشروع منجز في 15 مدينة سعودية للقطاعين الحكومي والخاص', '500+ completed projects across 15 Saudi cities for government and private sectors' ); ?></p>
</div></div>
<div class="opg-body">
	<div class="opg-stats">
		<div class="opg-stat"><div class="opg-stat-n">+500</div><div class="opg-stat-l"><?php osoul_bilingual( 'مشروع منجز', 'Projects Done' ); ?></div></div>
		<div class="opg-stat"><div class="opg-stat-n">15</div><div class="opg-stat-l"><?php osoul_bilingual( 'مدينة سعودية', 'Saudi Cities' ); ?></div></div>
		<div class="opg-stat"><div class="opg-stat-n">+20</div><div class="opg-stat-l"><?php osoul_bilingual( 'سنة خبرة', 'Years Experience' ); ?></div></div>
		<div class="opg-stat"><div class="opg-stat-n">95%</div><div class="opg-stat-l"><?php osoul_bilingual( 'رضا العملاء', 'Client Satisfaction' ); ?></div></div>
	</div>
	<div class="opg-grid">
		<?php foreach ( $projects as $p ) : ?>
		<div class="opg-card">
			<div class="opg-card-img">
				<img src="<?php echo esc_url( $base . $p['img'] ); ?>" alt="<?php echo esc_attr( $p['h'] ); ?>" loading="lazy">
				<div class="opg-card-cat"><?php osoul_bilingual( $p['cat'], $p['cat_en'] ); ?></div>
			</div>
			<div class="opg-card-body">
				<div class="opg-card-year"><?php echo esc_html( $p['year'] ); ?></div>
				<div class="opg-card-h"><?php osoul_bilingual( $p['h'], $p['h_en'] ); ?></div>
				<p class="opg-card-d"><?php osoul_bilingual( $p['d'], $p['d_en'] ); ?></p>
				<div class="opg-card-meta"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg><?php osoul_bilingual( $p['loc'], $p['loc_en'] ); ?></div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
</div>
<script><?php echo osoul_promote_script( 'opg-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Blog archive — /blog/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_blog_archive_output', 5 );
if ( ! function_exists( 'osoul_blog_archive_output' ) ) {
	function osoul_blog_archive_output() {
		if ( ! is_page( 'blog' ) ) {
			return;
		}
		$paged = max( 1, (int) ( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 ) );
		$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 9, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC' ) );
		?>
<style id="osoul-blog-css">
.obls{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .obls{direction:ltr}
.obls-hero{background:#0d1f3c;padding:80px 60px 60px;position:relative;overflow:hidden}
.obls-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.obls-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(32px,5vw,52px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:10px;position:relative}
.obls-h1 i{font-style:italic;color:#D21217}
.obls-sub{font-size:15px;color:rgba(255,255,255,.55);font-weight:300;position:relative}
.obls-body{max-width:1200px;margin:0 auto;padding:60px 48px 80px}
.obls-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.obls-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 24px rgba(13,31,60,.07);transition:all .3s;direction:rtl}
body.lang-en .obls-card{direction:ltr}
.obls-card:hover{box-shadow:0 12px 40px rgba(13,31,60,.13);transform:translateY(-4px)}
.obls-card-img{height:200px;overflow:hidden;background:#0d1f3c;position:relative}
.obls-card-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s}
.obls-card:hover .obls-card-img img{transform:scale(1.06)}
.obls-card-cat{position:absolute;top:12px;right:12px;background:#D21217;color:#fff;font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;border-radius:20px}
.obls-card-body{padding:22px 24px}
.obls-card-meta{display:flex;align-items:center;gap:14px;margin-bottom:10px}
.obls-card-meta span{font-size:11px;color:rgba(13,31,60,.45);display:flex;align-items:center;gap:5px}
.obls-card-meta svg{width:12px;height:12px;stroke:rgba(210,18,23,.7);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.obls-card-h{font-size:16px;font-weight:700;color:#0d1f3c;line-height:1.4;margin-bottom:10px}
.obls-card-ex{font-size:13px;color:rgba(13,31,60,.58);line-height:1.65;font-weight:300;margin-bottom:16px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.obls-read-more{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#D21217;text-decoration:none;letter-spacing:1px;transition:gap .2s;min-height:32px}
.obls-read-more:hover{gap:10px}
.obls-read-more svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.obls-empty{text-align:center;padding:80px 40px}
.obls-empty h2{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:28px;color:#0d1f3c;margin-bottom:12px}
.obls-empty p{color:rgba(13,31,60,.55);font-size:15px}
.obls-pagination{display:flex;justify-content:center;gap:8px;margin-top:52px;flex-wrap:wrap}
.obls-page-btn{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;background:#fff;border:1px solid rgba(13,31,60,.12);border-radius:6px;color:#0d1f3c;text-decoration:none;font-size:14px;font-weight:600;transition:all .2s}
.obls-page-btn:hover,.obls-page-btn.active{background:#D21217;border-color:#D21217;color:#fff}
@media(max-width:1024px){.obls-grid{grid-template-columns:repeat(2,1fr)}.obls-body{padding:44px 28px 60px}.obls-hero{padding:60px 28px 44px}}
@media(max-width:600px){.obls-grid{grid-template-columns:1fr}}
</style>
<div id="obls-wrap" class="obls">
<div class="obls-hero"><div style="position:relative;z-index:1">
	<div style="font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px"><?php osoul_bilingual( 'مدونتنا', 'Our Blog' ); ?></div>
	<h1 class="obls-h1"><span data-lang="ar">مقالات ومعلومات <i>صناعية</i></span><span data-lang="en">Industrial <i>Articles & Insights</i></span></h1>
	<p class="obls-sub"><?php osoul_bilingual( 'نشاركك أحدث المستجدات في قطاع البناء والتشطيب الصناعي', 'We share the latest updates in the construction and industrial finishing sector' ); ?></p>
</div></div>
<div class="obls-body">
<?php if ( $q->have_posts() ) : ?>
<div class="obls-grid">
<?php while ( $q->have_posts() ) : $q->the_post(); global $post; ?>
<div class="obls-card">
	<div class="obls-card-img">
		<?php if ( has_post_thumbnail() ) : ?><img src="<?php echo esc_url( get_the_post_thumbnail_url( $post->ID, 'medium_large' ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy"><?php endif; ?>
		<?php $cats = get_the_category(); if ( $cats ) : ?><div class="obls-card-cat"><?php echo esc_html( $cats[0]->name ); ?></div><?php endif; ?>
	</div>
	<div class="obls-card-body">
		<div class="obls-card-meta"><span><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?php echo esc_html( get_the_date( 'j M Y' ) ); ?></span></div>
		<div class="obls-card-h"><?php echo esc_html( get_the_title() ); ?></div>
		<div class="obls-card-ex"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '...' ) ); ?></div>
		<a href="<?php echo esc_url( get_permalink() ); ?>" class="obls-read-more"><?php osoul_bilingual( 'اقرأ المزيد', 'Read More' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
	</div>
</div>
<?php endwhile; wp_reset_postdata(); ?>
</div>
<?php $total = $q->max_num_pages; if ( $total > 1 ) : ?>
<div class="obls-pagination">
<?php for ( $i = 1; $i <= $total; $i++ ) {
	$active = $i === $paged ? ' active' : '';
	echo "<a href='" . esc_url( get_pagenum_link( $i ) ) . "' class='obls-page-btn{$active}'>{$i}</a>";
} ?>
</div>
<?php endif; ?>
<?php else : ?>
<div class="obls-empty">
	<h2><?php osoul_bilingual( 'لا توجد مقالات بعد', 'No Posts Yet' ); ?></h2>
	<p><?php osoul_bilingual( 'سيتم نشر المقالات قريباً', 'Articles will be published soon' ); ?></p>
</div>
<?php endif; ?>
</div>
</div>
<script><?php echo osoul_promote_script( 'obls-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Blog single post
 * ========================================================= */
add_action( 'wp_footer', 'osoul_blog_single_output', 5 );
if ( ! function_exists( 'osoul_blog_single_output' ) ) {
	function osoul_blog_single_output() {
		if ( ! is_single() ) {
			return;
		}
		global $post;
		if ( ! $post ) {
			return;
		}
		$home   = home_url( '/' );
		$thumb  = get_the_post_thumbnail_url( $post->ID, 'full' );
		$author = get_the_author_meta( 'display_name', $post->post_author );
		$date   = get_the_date( 'j F Y', $post->ID );
		$cats   = get_the_category( $post->ID );
		$cat    = $cats ? $cats[0]->name : '';
		$content = apply_filters( 'the_content', $post->post_content );
		?>
<style id="osoul-single-css">
.obl{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .obl{direction:ltr}
.obl-hero{background:#0d1f3c;padding:80px 60px 60px;position:relative;overflow:hidden}
.obl-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.obl-hero-inner{position:relative;z-index:1;max-width:820px}
.obl-cat-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(210,18,23,.15);border:1px solid rgba(210,18,23,.3);color:#D21217;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:5px 14px;border-radius:20px;margin-bottom:20px}
.obl-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4vw,46px);font-weight:700;color:#fff;line-height:1.25;margin-bottom:18px}
.obl-meta{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.obl-meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:rgba(255,255,255,.55)}
.obl-meta-item svg{width:14px;height:14px;stroke:rgba(210,18,23,.8);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.obl-body{max-width:820px;margin:0 auto;padding:60px 48px 80px}
.obl-feat-img{border-radius:10px;overflow:hidden;margin-bottom:48px;box-shadow:0 8px 32px rgba(13,31,60,.12)}
.obl-feat-img img{width:100%;max-height:460px;object-fit:cover;display:block}
.obl-content{background:#fff;border-radius:10px;padding:52px 48px;box-shadow:0 4px 24px rgba(13,31,60,.06);font-size:15px;color:rgba(26,23,20,.75);line-height:1.75;font-weight:300}
.obl-content h2,.obl-content h3,.obl-content h4{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;color:#0d1f3c;margin:32px 0 14px;line-height:1.3}
.obl-content h2{font-size:26px}.obl-content h3{font-size:22px}.obl-content h4{font-size:18px}
.obl-content p{margin-bottom:18px}.obl-content ul,.obl-content ol{margin:16px 0 16px 20px;padding:0}
.obl-content li{margin-bottom:8px}.obl-content img{max-width:100%;border-radius:6px;margin:16px 0}
.obl-content a{color:#D21217;text-decoration:underline}
.obl-back{display:inline-flex;align-items:center;gap:8px;color:#0d1f3c;font-size:13px;font-weight:600;text-decoration:none;border-bottom:1px solid transparent;transition:border-color .2s;margin-bottom:32px}
.obl-back:hover{border-bottom-color:#D21217;color:#D21217}
.obl-back svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.obl-fc-box{background:#0d1f3c;border-radius:10px;padding:40px 44px;margin-top:48px;display:flex;align-items:center;justify-content:space-between;gap:32px;flex-wrap:wrap}
.obl-fc-txt h3{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:8px}
.obl-fc-txt p{font-size:14px;color:rgba(255,255,255,.6);line-height:1.65;font-weight:300}
.obl-fc-cta{display:inline-flex;align-items:center;gap:8px;background:#D21217;color:#fff;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:14px 28px;text-decoration:none;border-radius:3px;transition:background .25s;white-space:nowrap;min-height:44px}
.obl-fc-cta:hover{background:#e8464b}
@media(max-width:1024px){.obl-body{padding:40px 28px 60px}.obl-hero{padding:60px 28px 44px}.obl-content{padding:36px 28px}}
</style>
<div id="obl-wrap" class="obl">
<div class="obl-hero"><div class="obl-hero-inner">
	<?php if ( $cat ) : ?><div class="obl-cat-badge"><?php echo esc_html( $cat ); ?></div><?php endif; ?>
	<h1 class="obl-h1"><?php echo esc_html( get_the_title( $post->ID ) ); ?></h1>
	<div class="obl-meta">
		<div class="obl-meta-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><?php echo esc_html( $author ); ?></div>
		<div class="obl-meta-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?php echo esc_html( $date ); ?></div>
	</div>
</div></div>
<div class="obl-body">
	<a href="<?php echo esc_url( $home . 'blog/' ); ?>" class="obl-back"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg><?php osoul_bilingual( 'العودة للمدونة', 'Back to Blog' ); ?></a>
	<?php if ( $thumb ) : ?><div class="obl-feat-img"><img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>"></div><?php endif; ?>
	<div class="obl-content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput — already run through the_content filters ?></div>
	<div class="obl-fc-box">
		<div class="obl-fc-txt">
			<h3><?php osoul_bilingual( 'هل تحتاج منتجاتنا لمشروعك؟', 'Need Our Products for Your Project?' ); ?></h3>
			<p><?php osoul_bilingual( 'فريقنا جاهز لتقديم عرض سعر مخصص وفق متطلبات مشروعك', 'Our team is ready to provide a customized quote for your project requirements' ); ?></p>
		</div>
		<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="obl-fc-cta"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
	</div>
</div>
</div>
<script><?php echo osoul_promote_script( 'obl-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Contact — /contact/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_contact_output', 5 );
if ( ! function_exists( 'osoul_contact_output' ) ) {
	function osoul_contact_output() {
		if ( ! is_page( 'contact' ) ) {
			return;
		}
		?>
<style id="osoul-contact-css">
.oct{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .oct{direction:ltr}
.oct-hero{background:#0d1f3c;padding:80px 60px 64px;position:relative;overflow:hidden}
.oct-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.oct-hero-inner{position:relative;z-index:1;max-width:820px}
.oct-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.oct-tag::before{content:'';width:28px;height:1px;background:#D21217}
.oct-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(30px,5vw,52px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:14px}
.oct-h1 i{font-style:italic;color:#D21217}
.oct-sub{font-size:15px;color:rgba(255,255,255,.6);max-width:520px;line-height:1.65;font-weight:300}
.oct-body{max-width:1200px;margin:0 auto;padding:72px 60px 88px}
.oct-grid{display:grid;grid-template-columns:1fr 1.3fr;gap:56px}
.oct-info h3{font-size:13px;letter-spacing:2px;color:rgba(13,31,60,.4);text-transform:uppercase;font-weight:700;margin-bottom:24px;border-bottom:1px solid rgba(13,31,60,.08);padding-bottom:12px}
.oct-info-list{display:flex;flex-direction:column;gap:20px;margin-bottom:36px}
.oct-info-item{display:flex;align-items:flex-start;gap:14px}
.oct-info-ic{width:40px;height:40px;background:rgba(210,18,23,.06);border:1px solid rgba(210,18,23,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.oct-info-ic svg{width:18px;height:18px;stroke:#D21217;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.oct-info-txt label{font-size:10px;letter-spacing:2px;color:rgba(13,31,60,.4);text-transform:uppercase;font-weight:700;display:block;margin-bottom:3px}
.oct-info-txt p,.oct-info-txt a{font-size:14px;color:#0d1f3c;line-height:1.6;font-weight:500;text-decoration:none}
.oct-info-txt a:hover{color:#D21217}
.oct-hours{background:#0d1f3c;border-radius:8px;padding:20px 24px;margin-bottom:28px}
.oct-hours h4{font-size:12px;letter-spacing:2px;color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:12px}
.oct-hours-row{display:flex;align-items:center;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05)}
.oct-hours-row:last-child{border-bottom:none}
.oct-hours-row span:first-child{color:rgba(255,255,255,.6)}
.oct-hours-row span:last-child{color:#D21217;font-weight:700}
.oct-social{display:flex;gap:10px}
.oct-social a{width:40px;height:40px;background:#fff;border:1px solid rgba(13,31,60,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;color:rgba(13,31,60,.5)!important;text-decoration:none;transition:all .25s}
.oct-social a:hover{background:#D21217;border-color:#D21217;color:#fff!important}
.oct-social svg{width:16px;height:16px;fill:currentColor}
.oct-form-wrap{background:#fff;border-radius:12px;padding:44px 40px;box-shadow:0 8px 32px rgba(13,31,60,.08)}
.oct-form-wrap h3{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:22px;font-weight:700;color:#0d1f3c;margin-bottom:6px}
.oct-form-wrap h3 i{font-style:italic;color:#D21217}
.oct-form-wrap p{font-size:14px;color:rgba(13,31,60,.5);margin-bottom:28px;font-weight:300}
.oct-form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.oct-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.oct-field label{font-size:12px;font-weight:700;color:rgba(13,31,60,.6);letter-spacing:.5px}
.oct-field input,.oct-field textarea,.oct-field select{border:1.5px solid rgba(13,31,60,.1);border-radius:6px;padding:12px 16px;font-size:14px;color:#0d1f3c;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;outline:none;transition:border-color .2s;background:#fafaf8;min-height:44px}
.oct-field input:focus,.oct-field textarea:focus,.oct-field select:focus{border-color:#D21217;background:#fff}
.oct-field input::placeholder,.oct-field textarea::placeholder{color:rgba(13,31,60,.3)}
.oct-field textarea{resize:vertical;min-height:100px}
.oct-sbtn{width:100%;background:#D21217;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:15px 32px;border:none;border-radius:6px;cursor:pointer;transition:background .25s;min-height:48px}
.oct-sbtn:hover{background:#e8464b}
.oct-sbtn[disabled]{opacity:.6;cursor:default}
.oct-sbtn-wa{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:transparent;color:#0d1f3c;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:600;letter-spacing:.5px;padding:13px 24px;border:1.5px solid rgba(13,31,60,.15);border-radius:6px;cursor:pointer;transition:all .25s;min-height:46px;margin-top:10px}
.oct-sbtn-wa:hover{border-color:#25D366;color:#25D366}
.oct-sbtn-wa svg{width:17px;height:17px;fill:#25D366;flex-shrink:0}
.oct-status{padding:12px 16px;border-radius:6px;font-size:13px;font-weight:600;margin-bottom:14px;line-height:1.5}
.oct-status.ok{background:rgba(37,211,102,.1);color:#1a7e42;border:1px solid rgba(37,211,102,.3)}
.oct-status.err{background:rgba(210,18,23,.08);color:#b50f13;border:1px solid rgba(210,18,23,.25)}
.oct-map{border-radius:10px;overflow:hidden;box-shadow:0 8px 32px rgba(13,31,60,.1);margin-top:48px;height:360px;background:#edf0f3;position:relative}
.oct-map iframe{width:100%;height:100%;border:none;display:block}
.oct-map-label{position:absolute;top:14px;right:14px;background:#0d1f3c;color:#fff;font-size:11px;font-weight:700;letter-spacing:2px;padding:6px 14px;border-radius:20px}
@media(max-width:1024px){.oct-grid{grid-template-columns:1fr}.oct-body{padding:52px 28px 64px}.oct-hero{padding:60px 28px 44px}.oct-form-wrap{padding:32px 24px}}
@media(max-width:600px){.oct-form-row{grid-template-columns:1fr}}
</style>
<div id="oct-wrap" class="oct">
	<div class="oct-hero"><div class="oct-hero-inner">
		<div class="oct-tag"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></div>
		<h1 class="oct-h1"><span data-lang="ar">نحن هنا <i>لخدمتك</i></span><span data-lang="en">We Are Here <i>to Serve You</i></span></h1>
		<p class="oct-sub"><?php osoul_bilingual( 'تواصل معنا لطلب عرض سعر أو للاستفسار — فريقنا يرد خلال 24 ساعة', 'Contact us to request a quote or inquire — our team responds within 24 hours' ); ?></p>
	</div></div>
	<div class="oct-body">
		<div class="oct-grid">
			<div class="oct-info">
				<h3><?php osoul_bilingual( 'معلومات التواصل', 'Contact Information' ); ?></h3>
				<div class="oct-info-list">
					<div class="oct-info-item">
						<div class="oct-info-ic"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.1 1.18 2 2 0 012.08 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.09a16 16 0 006 6z"/></svg></div>
						<div class="oct-info-txt"><label><?php osoul_bilingual( 'الهاتف', 'Phone' ); ?></label><a href="tel:<?php echo esc_attr( osoul_tel( osoul_opt( 'phone_primary' ) ) ); ?>"><?php echo esc_html( osoul_opt( 'phone_primary' ) ); ?></a><?php if ( osoul_opt( 'phone_secondary' ) ) : ?><br><a href="tel:<?php echo esc_attr( osoul_tel( osoul_opt( 'phone_secondary' ) ) ); ?>" style="font-size:13px;opacity:.7"><?php echo esc_html( osoul_opt( 'phone_secondary' ) ); ?></a><?php endif; ?></div>
					</div>
					<div class="oct-info-item">
						<div class="oct-info-ic"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
						<div class="oct-info-txt"><label><?php osoul_bilingual( 'البريد الإلكتروني', 'Email' ); ?></label><a href="mailto:<?php echo esc_attr( osoul_opt( 'email' ) ); ?>"><?php echo esc_html( osoul_opt( 'email' ) ); ?></a></div>
					</div>
					<div class="oct-info-item">
						<div class="oct-info-ic"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
						<div class="oct-info-txt"><label><?php osoul_bilingual( 'العنوان', 'Address' ); ?></label><p><?php osoul_bilingual( osoul_opt( 'address_ar' ), osoul_opt( 'address_en' ) ); ?></p></div>
					</div>
					<div class="oct-info-item">
						<div class="oct-info-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div>
						<div class="oct-info-txt"><label><?php osoul_bilingual( 'ساعات العمل', 'Working Hours' ); ?></label><p><?php osoul_bilingual( osoul_opt( 'hours_ar' ), osoul_opt( 'hours_en' ) ); ?></p></div>
					</div>
				</div>
				<div class="oct-hours">
					<h4><?php osoul_bilingual( 'جدول أوقات الاستجابة', 'Response Time Schedule' ); ?></h4>
					<div class="oct-hours-row"><span><?php osoul_bilingual( 'واتساب', 'WhatsApp' ); ?></span><span><?php osoul_bilingual( 'فوري', 'Instant' ); ?></span></div>
					<div class="oct-hours-row"><span><?php osoul_bilingual( 'هاتف', 'Phone' ); ?></span><span><?php osoul_bilingual( 'خلال ساعة', 'Within 1h' ); ?></span></div>
					<div class="oct-hours-row"><span><?php osoul_bilingual( 'بريد إلكتروني', 'Email' ); ?></span><span><?php osoul_bilingual( 'خلال 24 ساعة', 'Within 24h' ); ?></span></div>
				</div>
				<div class="oct-social">
					<a href="<?php echo esc_url( osoul_wa_url() ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
					<a href="<?php echo esc_url( osoul_opt( 'instagram' ) ); ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
					<a href="<?php echo esc_url( osoul_opt( 'linkedin' ) ); ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
				</div>
			</div>
			<div class="oct-form-wrap">
				<h3><span data-lang="ar">أرسل لنا <i>رسالتك</i></span><span data-lang="en">Send Us <i>Your Message</i></span></h3>
				<p><?php osoul_bilingual( 'سنرد عليك خلال يوم عمل واحد', 'We will respond within one business day' ); ?></p>
				<div class="oct-form-row">
					<div class="oct-field"><label><?php osoul_bilingual( 'الاسم الكامل *', 'Full Name *' ); ?></label><input type="text" id="oct-name" placeholder="اسمك الكريم"></div>
					<div class="oct-field"><label><?php osoul_bilingual( 'رقم الجوال *', 'Phone *' ); ?></label><input type="tel" id="oct-phone" placeholder="05XXXXXXXX"></div>
				</div>
				<div class="oct-field"><label><?php osoul_bilingual( 'البريد الإلكتروني', 'Email' ); ?></label><input type="email" id="oct-email" placeholder="example@domain.com"></div>
				<div class="oct-field"><label><?php osoul_bilingual( 'موضوع الرسالة', 'Subject' ); ?></label>
					<select id="oct-subject">
						<option value=""><?php esc_html_e( 'اختر الموضوع', 'osoul' ); ?></option>
						<option value="quote"><?php esc_html_e( 'طلب عرض سعر', 'osoul' ); ?></option>
						<option value="products"><?php esc_html_e( 'استفسار عن المنتجات', 'osoul' ); ?></option>
						<option value="services"><?php esc_html_e( 'استفسار عن الخدمات', 'osoul' ); ?></option>
						<option value="other"><?php esc_html_e( 'أخرى', 'osoul' ); ?></option>
					</select>
				</div>
				<!-- Honeypot: hidden from humans, bots fill it -->
				<input type="text" id="oct-website" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
				<div class="oct-field"><label><?php osoul_bilingual( 'رسالتك', 'Your Message' ); ?></label><textarea id="oct-msg" placeholder="اكتب رسالتك هنا..."></textarea></div>
				<div id="oct-status" class="oct-status" role="status" aria-live="polite" style="display:none"></div>
				<button class="oct-sbtn" onclick="octSubmit()"><?php osoul_bilingual( 'إرسال الطلب', 'Send Request' ); ?></button>
				<button class="oct-sbtn-wa" onclick="octSendWhatsApp()"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg><?php osoul_bilingual( 'أو عبر واتساب', 'or via WhatsApp' ); ?></button>
			</div>
		</div>
		<div class="oct-map">
			<div class="oct-map-label"><?php osoul_bilingual( 'موقعنا', 'Our Location' ); ?></div>
			<iframe src="<?php echo esc_url( osoul_opt( 'maps_embed' ) ); ?>" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
		</div>
	</div>
</div>
<script><?php echo osoul_promote_script( 'oct-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Privacy policy — /privacy-policy/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_privacy_output', 5 );
if ( ! function_exists( 'osoul_privacy_output' ) ) {
	function osoul_privacy_output() {
		if ( ! is_page( 'privacy-policy' ) ) {
			return;
		}
		$home = home_url( '/' );
		$sections = array(
			array( 'h' => 'مقدمة', 'h_en' => 'Introduction', 'p' => array( array( 'تلتزم شركة أصول البناء للصناعة ("نحن"، "الشركة") بحماية خصوصية زوار موقعنا الإلكتروني وعملائنا. توضح هذه السياسة كيفية جمع المعلومات الشخصية واستخدامها والحفاظ عليها والإفصاح عنها من خلال موقعنا osoulalbinaa.com.', 'Osoul Albinaa Industrial Co. ("we", "the Company") is committed to protecting the privacy of visitors to our website and our clients. This policy explains how we collect, use, maintain, and disclose personal information through our website osoulalbinaa.com.' ) ) ),
			array( 'h' => 'المعلومات التي نجمعها', 'h_en' => 'Information We Collect', 'lead' => array( 'قد نجمع المعلومات التالية عند تفاعلك مع موقعنا:', 'We may collect the following information when you interact with our website:' ), 'ul' => array( array( 'الاسم ورقم الهاتف وعنوان البريد الإلكتروني (عند ملء نماذج التواصل)', 'Name, phone number and email address (when filling out contact forms)' ), array( 'معلومات المشروع والمنتجات المطلوبة (عند طلب عروض الأسعار)', 'Project information and requested products (when requesting price quotes)' ), array( 'بيانات الاستخدام مثل صفحات الزيارة وأوقاتها (عبر ملفات تعريف الارتباط)', 'Usage data such as visited pages and times (via cookies)' ), array( 'عنوان IP ونوع المتصفح ونظام التشغيل', 'IP address, browser type, and operating system' ) ) ),
			array( 'h' => 'كيف نستخدم معلوماتك', 'h_en' => 'How We Use Your Information', 'lead' => array( 'نستخدم المعلومات المجمعة للأغراض التالية:', 'We use collected information for the following purposes:' ), 'ul' => array( array( 'الرد على استفساراتك وتقديم عروض الأسعار المطلوبة', 'Responding to your inquiries and providing requested price quotes' ), array( 'تحسين محتوى الموقع وتجربة المستخدم', 'Improving website content and user experience' ), array( 'إرسال معلومات تتعلق بالمنتجات والخدمات (بموافقتك فقط)', 'Sending product and service information (with your consent only)' ), array( 'الامتثال للمتطلبات القانونية والتنظيمية في المملكة العربية السعودية', 'Complying with legal and regulatory requirements in Saudi Arabia' ) ) ),
			array( 'h' => 'ملفات تعريف الارتباط (الكوكيز)', 'h_en' => 'Cookies', 'p' => array( array( 'يستخدم موقعنا ملفات تعريف الارتباط لتحسين تجربتك. يمكنك التحكم في الكوكيز من خلال إعدادات متصفحك. رفض الكوكيز قد يؤثر على بعض وظائف الموقع.', 'Our website uses cookies to improve your experience. You can control cookies through your browser settings. Declining cookies may affect some website functionality.' ), array( 'نستخدم الكوكيز لتذكر تفضيل اللغة (عربي/إنجليزي) وقائمة المنتجات المحفوظة مؤقتاً ولإحصائيات الزيارات المجهولة الهوية.', 'We use cookies to remember language preference (Arabic/English), temporarily saved product lists, and anonymous visit statistics.' ) ) ),
			array( 'h' => 'أمان البيانات', 'h_en' => 'Data Security', 'p' => array( array( 'نتخذ تدابير أمنية مناسبة لحماية معلوماتك من الوصول غير المصرح به أو التعديل أو الإفصاح أو الإتلاف. يستخدم موقعنا بروتوكول HTTPS لتشفير البيانات المنقولة.', 'We take appropriate security measures to protect your information from unauthorized access, modification, disclosure, or destruction. Our website uses HTTPS protocol to encrypt transmitted data.' ) ) ),
			array( 'h' => 'حقوقك', 'h_en' => 'Your Rights', 'lead' => array( 'لديك الحق في:', 'You have the right to:' ), 'ul' => array( array( 'الاطلاع على البيانات الشخصية التي نحتفظ بها عنك', 'Access the personal data we hold about you' ), array( 'طلب تصحيح أو حذف بياناتك الشخصية', 'Request correction or deletion of your personal data' ), array( 'سحب موافقتك على استخدام بياناتك في أي وقت', 'Withdraw your consent for data use at any time' ), array( 'تقديم شكوى للجهات التنظيمية المختصة في المملكة العربية السعودية', 'File a complaint with the competent regulatory authorities in Saudi Arabia' ) ) ),
		);
		?>
<style id="osoul-privacy-css">
.opr{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee}
body.lang-en .opr{direction:ltr}
.opr-hero{background:#0d1f3c;padding:80px 60px 64px;position:relative;overflow:hidden}
.opr-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#D21217,transparent)}
.opr-hero-inner{position:relative;z-index:1;max-width:860px}
.opr-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px}
.opr-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4.5vw,48px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:14px}
.opr-h1 i{font-style:italic;color:#D21217}
.opr-sub{font-size:14px;color:rgba(255,255,255,.55);font-weight:300}
.opr-body{max-width:820px;margin:0 auto;padding:64px 48px 88px}
.opr-card{background:#fff;border-radius:10px;padding:52px 48px;box-shadow:0 4px 24px rgba(13,31,60,.06)}
.opr-updated{font-size:12px;color:rgba(13,31,60,.4);letter-spacing:1.5px;margin-bottom:36px;border-bottom:1px solid rgba(13,31,60,.07);padding-bottom:18px}
.opr-section{margin-bottom:36px}
.opr-section h2{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:20px;font-weight:700;color:#0d1f3c;margin-bottom:12px;padding-right:14px;border-right:3px solid #D21217;line-height:1.3}
body.lang-en .opr-section h2{padding-right:0;padding-left:14px;border-right:none;border-left:3px solid #D21217}
.opr-section p{font-size:14px;color:rgba(13,31,60,.65);line-height:1.75;font-weight:300;margin-bottom:12px}
.opr-section ul{padding-right:20px;margin:12px 0}
body.lang-en .opr-section ul{padding-right:0;padding-left:20px}
.opr-section li{font-size:14px;color:rgba(13,31,60,.65);line-height:1.75;font-weight:300;margin-bottom:6px}
.opr-section a{color:#D21217;text-decoration:none}
.opr-section a:hover{text-decoration:underline}
.opr-contact-box{background:#f9f7f5;border:1px solid rgba(13,31,60,.06);border-radius:8px;padding:24px 28px;margin-top:40px}
.opr-contact-box h3{font-size:15px;font-weight:700;color:#0d1f3c;margin-bottom:10px}
.opr-contact-box p{font-size:14px;color:rgba(13,31,60,.6);line-height:1.65;font-weight:300;margin-bottom:4px}
@media(max-width:768px){.opr-body{padding:44px 20px 60px}.opr-hero{padding:60px 28px 44px}.opr-card{padding:32px 24px}}
</style>
<div id="opr-wrap" class="opr">
	<div class="opr-hero"><div class="opr-hero-inner">
		<div class="opr-tag"><?php osoul_bilingual( 'الشفافية', 'Transparency' ); ?></div>
		<h1 class="opr-h1"><span data-lang="ar">سياسة <i>الخصوصية</i></span><span data-lang="en"><i>Privacy</i> Policy</span></h1>
		<p class="opr-sub"><?php osoul_bilingual( 'نلتزم بحماية بياناتك الشخصية والحفاظ على خصوصيتك', 'We are committed to protecting your personal data and preserving your privacy' ); ?></p>
	</div></div>
	<div class="opr-body"><div class="opr-card">
		<div class="opr-updated"><?php osoul_bilingual( 'آخر تحديث: يناير 2025', 'Last Updated: January 2025' ); ?></div>
		<?php foreach ( $sections as $s ) : ?>
		<div class="opr-section">
			<h2><?php osoul_bilingual( $s['h'], $s['h_en'] ); ?></h2>
			<?php if ( ! empty( $s['p'] ) ) : foreach ( $s['p'] as $pr ) : ?><p><?php osoul_bilingual( $pr[0], $pr[1] ); ?></p><?php endforeach; endif; ?>
			<?php if ( ! empty( $s['lead'] ) ) : ?><p><?php osoul_bilingual( $s['lead'][0], $s['lead'][1] ); ?></p><?php endif; ?>
			<?php if ( ! empty( $s['ul'] ) ) : ?><ul><?php foreach ( $s['ul'] as $li ) : ?><li><?php osoul_bilingual( $li[0], $li[1] ); ?></li><?php endforeach; ?></ul><?php endif; ?>
		</div>
		<?php endforeach; ?>
		<div class="opr-contact-box">
			<h3><?php osoul_bilingual( 'للتواصل بخصوص الخصوصية', 'Contact Us About Privacy' ); ?></h3>
			<p><?php osoul_bilingual( 'إذا كان لديك أي استفسار بخصوص سياسة الخصوصية، يرجى التواصل معنا:', 'If you have any questions about our privacy policy, please contact us:' ); ?></p>
			<p><a href="mailto:info@osoulalbinaa.com">info@osoulalbinaa.com</a></p>
			<p><a href="tel:+966563627063">+966 563 627 063</a></p>
			<p style="margin-top:12px"><a href="<?php echo esc_url( $home . 'contact/' ); ?>" style="color:#D21217;font-weight:600"><?php osoul_bilingual( 'زيارة صفحة التواصل', 'Visit Contact Page' ); ?> &rarr;</a></p>
		</div>
	</div></div>
</div>
<script><?php echo osoul_promote_script( 'opr-wrap' ); ?></script>
		<?php
	}
}
