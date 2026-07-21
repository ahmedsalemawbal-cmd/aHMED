<?php
/**
 * Product catalog data.
 *
 * Pure data — no output. Consumed by templates, security and SEO modules.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Built-in product catalog defaults, keyed by slug.
 *
 * These seed the editable products in the admin (see inc/products-cpt.php).
 * Once seeded, the live catalog is read from the database, and this is only a
 * fallback before seeding runs.
 *
 * @return array
 */
if ( ! function_exists( 'osoul_catalog_defaults' ) ) {
	function osoul_catalog_defaults() {
		$home = home_url( '/' );

		return array(

			/* ───── Doors ───── */
			'fire-resistant-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب المعدنية المقاومة للحريق', 'name_en' => 'Fire-Resistant Metal Doors',
				'sub' => 'معتمدة من 30 إلى 120 دقيقة', 'sub_en' => 'Rated 30 to 120 minutes',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-الطوارئ.png', 'badge' => 'الأكثر طلباً', 'badge_en' => 'Best Seller',
				'url' => $home . 'fire-resistant-doors/',
				'specs' => array( 'مقاومة الحريق: 30-120 دقيقة', 'سماكة الصفيح: 1.2-2.0 مم', 'إطار مجلفن مزدوج', 'مزودة بنظام إغلاق تلقائي' ),
				'specs_en' => array( 'Fire rating: 30–120 min', 'Sheet thickness: 1.2–2.0 mm', 'Double galvanized frame', 'Equipped with automatic closing' ),
			),
			'hollow-metal-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب المعدنية المجوفة', 'name_en' => 'Hollow Metal Doors',
				'sub' => 'تصميمات متعددة للمباني التجارية', 'sub_en' => 'Multiple designs for commercial buildings',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-حديد.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'hollow-metal-doors/',
				'specs' => array( 'سماكة الصفيح: 1.0-1.6 مم', 'ملء اختياري: هانيكوم أو مواد عازلة', 'إطار مجلفن أو ملون', 'متوفرة بمقاسات مخصصة' ),
				'specs_en' => array( 'Sheet: 1.0–1.6 mm', 'Fill: honeycomb or insulation', 'Galvanized or painted frame', 'Custom sizes available' ),
			),
			'cladding-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب التكسوية', 'name_en' => 'Cladding Doors',
				'sub' => 'تكسوة معدنية أو HPL أو زجاجية', 'sub_en' => 'Metal, HPL or glass cladding',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-كلادينج-تركي.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'cladding-doors/',
				'specs' => array( 'تكسوة: معدن، HPL، زجاج', 'نواة: صلب مجوف أو EPS', 'سهلة التنظيف', 'مظهر معماري مميز' ),
				'specs_en' => array( 'Cladding: metal, HPL, glass', 'Core: hollow steel or EPS', 'Easy to clean', 'Distinctive architectural look' ),
			),
			'mdf-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب الخشبية MDF', 'name_en' => 'MDF Wooden Doors',
				'sub' => 'للوحدات السكنية والتجارية', 'sub_en' => 'For residential & commercial units',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-تركي-ام-دي-اف.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'mdf-doors/',
				'specs' => array( 'مادة: MDF عالي الكثافة', 'أوجه متعددة: مرايا، خشب، دهان', 'بمقاسات قياسية ومخصصة', 'متوفر مع إطار خشبي أو معدني' ),
				'specs_en' => array( 'Material: high-density MDF', 'Faces: mirror, wood, paint', 'Standard & custom sizes', 'Available with wood or metal frame' ),
			),
			'wpc-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب WPC مقاومة الرطوبة', 'name_en' => 'WPC Moisture-Resistant Doors',
				'sub' => 'للحمامات والمناطق الرطبة', 'sub_en' => 'For bathrooms & wet areas',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-ببليو-بي-سي.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'wpc-doors/',
				'specs' => array( 'مادة: WPC (خشب-بلاستيك)', 'مقاوم للرطوبة 100%', 'لا يتآكل ولا يتشوه', 'ألوان وأنماط متعددة' ),
				'specs_en' => array( 'Material: WPC (wood-plastic)', '100% moisture resistant', 'No corrosion or deformation', 'Multiple colors & patterns' ),
			),
			'custom-doors' => array(
				'group' => 'doors', 'group_ar' => 'الأبواب', 'group_en' => 'Doors',
				'name' => 'الأبواب المخصصة', 'name_en' => 'Custom Doors',
				'sub' => 'وفق مواصفات العميل والمشروع', 'sub_en' => 'Per client and project specs',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تفصيل-حسب-الطلب.png', 'badge' => 'مخصص', 'badge_en' => 'Custom',
				'url' => $home . 'custom-doors/',
				'specs' => array( 'تصنيع حسب الطلب', 'متطلبات حريق مخصصة', 'أبعاد وأشكال غير قياسية', 'ألوان ومواد حسب العميل' ),
				'specs_en' => array( 'Made to order', 'Custom fire requirements', 'Non-standard dimensions & shapes', 'Colors & materials per client' ),
			),

			/* ───── Gypsum Board ───── */
			'c-stud-25' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'C-Stud 25 مم', 'name_en' => 'C-Stud 25 mm',
				'sub' => 'للجدران الخفيفة الداخلية', 'sub_en' => 'For lightweight interior partitions',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/098900899.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'c-stud-25/',
				'specs' => array( 'الحجم: 25×50 مم', 'السماكة: 0.45 مم', 'الطول: 3-4 متر', 'مجلفن سماكة Z120' ),
				'specs_en' => array( 'Size: 25×50 mm', 'Thickness: 0.45 mm', 'Length: 3-4 m', 'Z120 galvanized' ),
			),
			'c-stud-50' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'C-Stud 50 مم', 'name_en' => 'C-Stud 50 mm',
				'sub' => 'للجدران المتوسطة', 'sub_en' => 'For medium partition walls',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/098900899.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'c-stud-50/',
				'specs' => array( 'الحجم: 50×50 مم', 'السماكة: 0.5-0.6 مم', 'الطول: 3-5 متر', 'مجلفن سماكة Z120' ),
				'specs_en' => array( 'Size: 50×50 mm', 'Thickness: 0.5–0.6 mm', 'Length: 3–5 m', 'Z120 galvanized' ),
			),
			'c-stud-75' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'C-Stud 75 مم', 'name_en' => 'C-Stud 75 mm',
				'sub' => 'للجدران العازلة الأكبر', 'sub_en' => 'For larger insulation walls',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/098900899.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'c-stud-75/',
				'specs' => array( 'الحجم: 75×50 مم', 'السماكة: 0.55-0.7 مم', 'الطول: 3-6 متر', 'مجلفن سماكة Z120' ),
				'specs_en' => array( 'Size: 75×50 mm', 'Thickness: 0.55–0.7 mm', 'Length: 3–6 m', 'Z120 galvanized' ),
			),
			'u-track' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'U-Track تراك تثبيت', 'name_en' => 'U-Track',
				'sub' => 'لتثبيت C-Stud في الأرضية والسقف', 'sub_en' => 'For fixing C-Stud to floor and ceiling',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/778789899.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'u-track/',
				'specs' => array( 'أبعاد: 25/50/75 مم', 'السماكة: 0.45 مم', 'الطول: 3 متر', 'مجلفن Z120' ),
				'specs_en' => array( 'Sizes: 25/50/75 mm', 'Thickness: 0.45 mm', 'Length: 3 m', 'Z120 galvanized' ),
			),
			'l-angle' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'L-Angle زاوية التشطيب', 'name_en' => 'L-Angle Corner Bead',
				'sub' => 'لتشطيب حواف الجبسوم بورد', 'sub_en' => 'For finishing gypsum board edges',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/3677578.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'l-angle/',
				'specs' => array( 'القياس: 25×25 مم أو 30×30 مم', 'السماكة: 0.4 مم', 'الطول: 3 متر', 'مجلفن أو مطلي بالزنك' ),
				'specs_en' => array( 'Size: 25×25 or 30×30 mm', 'Thickness: 0.4 mm', 'Length: 3 m', 'Galvanized or zinc-coated' ),
			),
			'hat-channel' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'Hat Channel قبعة', 'name_en' => 'Hat Channel',
				'sub' => 'للأسقف المصطنعة والجدران', 'sub_en' => 'For suspended ceilings and walls',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/65477657.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'hat-channel/',
				'specs' => array( 'الحجم: 50×17 مم أو 60×20 مم', 'السماكة: 0.45 مم', 'الطول: 3 متر', 'مجلفن Z120' ),
				'specs_en' => array( 'Size: 50×17 or 60×20 mm', 'Thickness: 0.45 mm', 'Length: 3 m', 'Z120 galvanized' ),
			),
			'main-ceiling-channel' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'Main Channel قناة السقف', 'name_en' => 'Main Ceiling Channel',
				'sub' => 'قناة السقف الرئيسية', 'sub_en' => 'Primary ceiling support channel',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/647676767.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'main-ceiling-channel/',
				'specs' => array( 'الحجم: 50×28 مم أو 60×27 مم', 'السماكة: 0.5-0.6 مم', 'الطول: 3-4 متر', 'مجلفن Z120' ),
				'specs_en' => array( 'Size: 50×28 or 60×27 mm', 'Thickness: 0.5–0.6 mm', 'Length: 3–4 m', 'Z120 galvanized' ),
			),
			'gypsum-accessories' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'إكسسوارات الجبسوم', 'name_en' => 'Gypsum Board Accessories',
				'sub' => 'كامل إكسسوارات نظام الجبسوم', 'sub_en' => 'Complete gypsum system accessories',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/٦٥٦٥٧٥٦.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'gypsum-accessories/',
				'specs' => array( 'خيوط تقوية', 'إكسسوارات تعليق', 'مسامير وبراغي خاصة', 'إكسسوارات حواف ومنافذ' ),
				'specs_en' => array( 'Reinforcement tape', 'Suspension accessories', 'Special screws & fixings', 'Edge & outlet accessories' ),
			),
			'u-clip' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'U-Clip يوكليب', 'name_en' => 'U-Clip',
				'sub' => 'تثبيت الشبكة الثانوية على C-Channel', 'sub_en' => 'Fixes secondary grid to C-Channel',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/77667888.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'u-clip/',
				'specs' => array( 'المادة: صلب مجلفن', 'السماكة: 0.5 مم', 'سهل التركيب والتثبيت', 'مناسب لجميع أحجام C-Channel' ),
				'specs_en' => array( 'Material: galvanized steel', 'Thickness: 0.5 mm', 'Easy installation', 'Compatible with all C-Channel sizes' ),
			),
			'wire-clip' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'Wire Clip واير كليب', 'name_en' => 'Wire Clip',
				'sub' => 'ربط أسلاك التعليق بـ C-Channel', 'sub_en' => 'Connects suspension wires to C-Channel',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/9877678887.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'wire-clip/',
				'specs' => array( 'المادة: صلب مجلفن', 'السماكة: 2.5 مم', 'قوة تحمل عالية', 'سهولة الضبط والتركيب' ),
				'specs_en' => array( 'Material: galvanized steel', 'Thickness: 2.5 mm', 'High load capacity', 'Easy adjustment and installation' ),
			),
			'angel-w20' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'زاوية دبليو 20×20 مم', 'name_en' => 'Angle W20×20 mm',
				'sub' => 'زاوية تشطيب للحواف الداخلية', 'sub_en' => 'Finishing angle for internal edges',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/666554466.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'angel-w20/',
				'specs' => array( 'القياس: 20×20 مم', 'السماكة: 0.35-0.6 مم', 'الطول: 3-6 متر', 'مجلفن Z120' ),
				'specs_en' => array( 'Size: 20×20 mm', 'Thickness: 0.35-0.6 mm', 'Length: 3-6 m', 'Z120 galvanized' ),
			),
			'angel-w30' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'زاوية دبليو 20×30 مم', 'name_en' => 'Angle W20×30 mm',
				'sub' => 'زاوية تشطيب للحواف الخارجية', 'sub_en' => 'Finishing angle for external edges',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/7679898787.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'angel-w30/',
				'specs' => array( 'القياس: 20×30 مم', 'السماكة: 0.35-0.6 مم', 'الطول: 3-6 متر', 'مجلفن Z120' ),
				'specs_en' => array( 'Size: 20×30 mm', 'Thickness: 0.35-0.6 mm', 'Length: 3-6 m', 'Z120 galvanized' ),
			),
			'shadow-angle' => array(
				'group' => 'gypsum', 'group_ar' => 'الجبسوم بورد', 'group_en' => 'Gypsum Board',
				'name' => 'زاوية الضلال Shadow', 'name_en' => 'Shadow Angle',
				'sub' => 'تأثير Shadow Line على حواف الأسقف والجدران', 'sub_en' => 'Shadow line effect on ceiling and wall edges',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/09987767899.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'shadow-angle/',
				'specs' => array( 'السماكة: 0.4-0.6 مم', 'الطول: 3-6 متر', 'لإنشاء خط الظل المعماري', 'مجلفن Z120' ),
				'specs_en' => array( 'Thickness: 0.4-0.6 mm', 'Length: 3-6 m', 'Creates architectural shadow line', 'Z120 galvanized' ),
			),

			/* ───── Installation / Suspension — Fischer ───── */
			'u-bolt-etr-l' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'طوق U بخيط طويل ETR-L', 'name_en' => 'U-Bolt ETR-L',
				'sub' => 'تثبيت مباشر للأنابيب على قنوات FUS', 'sub_en' => 'Direct pipe fastening to FUS channels',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/U-bolt-ETR-L.png', 'badge' => 'الأكثر مبيعاً', 'badge_en' => 'Best Seller',
				'url' => $home . 'u-bolt-etr-l/',
				'specs' => array( 'أقطار خارجية: 21 - 324 مم', 'مادة: فولاذ مجلفن', 'متوافق مع قنوات FUS 41 وأذرع FCA', 'يعدل بمسامير وصواميل مرفقة', 'للاستخدام الداخلي الجاف فقط' ),
				'specs_en' => array( 'Outer diameter: 21-324 mm', 'Material: galvanized steel', 'Compatible with FUS 41 channels', 'Adjustable with supplied screws', 'For dry indoor use only' ),
			),
			'u-bolt-connector-fetr-c' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'وصلة قناة FETR-C', 'name_en' => 'U-Bolt Connector FETR-C',
				'sub' => 'توصيل أطواق ETR بقنوات FUS', 'sub_en' => 'Connect ETR U-Bolts to FUS channels',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/U-bolt-ETR-L.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'u-bolt-connector-fetr-c/',
				'specs' => array( 'تمركز حر بدون قيد الثقوب', 'مادة: فولاذ S235JR مجلفن', 'يتصل بـ FCN Clix P وSKS', 'صامولتان اضافيتان لتوصيل ETR' ),
				'specs_en' => array( 'Free positioning regardless of holes', 'Material: steel S235JR galvanized', 'Via FCN Clix P and SKS screws', '2 additional nuts for ETR' ),
			),
			'channel-fls' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'قناة FLS للتثبيت الخفيف', 'name_en' => 'Channel FLS',
				'sub' => 'العنصر الاساسي لنظام FLS المرن', 'sub_en' => 'Base element of the flexible FLS system',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/Channel-FLS.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'channel-fls/',
				'specs' => array( 'ارتفاعات: 17 / 30 / 37 مم', 'مادة: فولاذ S250GD مجلفن', 'اطوال: 2 / 3 / 6 متر', 'شهادة حريق MLAR/EN1363-1 للـ FLS 37' ),
				'specs_en' => array( 'Heights: 17/30/37 mm', 'Material: pre-galvanised S250GD', 'Lengths: 2/3/6 m', 'Fire cert MLAR/EN1363-1 for FLS 37' ),
			),
			'cantilever-arm-alk' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'ذراع كونسول ALK', 'name_en' => 'Cantilever Arm ALK',
				'sub' => 'ذراع تعليق اقتصادي من بروفايل FLS', 'sub_en' => 'Cost-effective cantilever from FLS profile',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/Cantilever-arm-ALK.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'cantilever-arm-alk/',
				'specs' => array( 'اطوال: 200 / 300 / 450 / 600 مم', 'لوحة القاعدة: فولاذ E295', 'القناة: فولاذ S215G وفق DIN 1623', 'شهادة حريق MLAR/EN1363-1 للـ ALK 37' ),
				'specs_en' => array( 'Lengths: 200/300/450/600 mm', 'Base plate: steel E295', 'Channel: S215G per DIN 1623', 'Fire cert MLAR/EN1363-1 for ALK 37' ),
			),
			'angle-brace-ws-31-45' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'دعامة زاوية WS 31-45 درجة', 'name_en' => 'Angle Brace WS 31-45 degrees',
				'sub' => 'ثبات عال للإنشاءات الداعمة', 'sub_en' => 'High stability for supporting structures',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/fischer-Angle-brace-WS-31-45%C2%B0.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'angle-brace-ws-31-45/',
				'specs' => array( 'زاوية التثبيت: 45 درجة', 'فتحات طويلة قياسية', 'يتصل بـ FSM Clix P ومسمار', 'متوافق مع قنوات FLS وأذرع ALK' ),
				'specs_en' => array( 'Fixing angle: 45 degrees', 'Standardised long slots', 'Connects via FSM Clix P', 'Compatible with FLS channels and ALK' ),
			),
			'beam-clamp-tkr-31' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'مشبك تعليق على الجسور TKR-31', 'name_en' => 'Beam Clamp TKR-31',
				'sub' => 'تثبيت قنوات FLS على العوارض الفولاذية', 'sub_en' => 'Fix FLS channels to steel girders',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/fischer-Clamp-hanger-TKR-31.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'beam-clamp-tkr-31/',
				'specs' => array( 'بدون لحام او حفر في العارضة', 'مشبك: فولاذ S235JR', 'لوحة: فولاذ E295', 'مشبكان لكل نقطة تثبيت' ),
				'specs_en' => array( 'No welding or drilling', 'Clamp: steel S235JR', 'Plate: steel E295', 'Two clamps per fixing point' ),
			),
			'saddle-flange-sf-l' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'فلنشة سرج SF-L', 'name_en' => 'Saddle Flange SF-L',
				'sub' => 'ربط قنوات FUS بالهيكل بزاوية 90 درجة', 'sub_en' => 'Connect FUS channels at 90 degrees',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/Saddle-flange-SF-L.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'saddle-flange-sf-l/',
				'specs' => array( 'زاوية التركيب: 90 درجة', 'لوحة قاعدة بفتحات 90 درجة', 'سرج مطابق لإدخال القناة', 'متوفر: مجلفن - مغطس ساخن - ستانلس' ),
				'specs_en' => array( 'Installation angle: 90 degrees', 'Base plate with 90 degree slots', 'Perfect-fit saddle', 'Available: zinc - hot-dip - stainless' ),
			),
			'angle-fitting-faf' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'وصلة زاوية FAF', 'name_en' => 'Angle Fitting FAF',
				'sub' => 'وصل قنوات FUS بزوايا متعددة', 'sub_en' => 'Join FUS channels at multiple angles',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/Angle-fitting-FAF.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'angle-fitting-faf/',
				'specs' => array( '2 / 3 / 4 ثقوب بزاوية 90 درجة', '3 ثقوب بزاوية 45 درجة', 'مادة: فولاذ S235JR', 'FAF مجلفن - FAF hdg خارجي - FAF A4 ستانلس' ),
				'specs_en' => array( '2/3/4-hole at 90 degrees', '3-hole at 45 degrees', 'Material: S235JR steel', 'FAF zinc - FAF hdg outdoor - FAF A4 stainless' ),
			),
			'system-connector-fma-fus' => array(
				'group' => 'strut', 'group_ar' => 'أنظمة التثبيت والتعليق', 'group_en' => 'Installation Systems',
				'name' => 'وصلة نظام FMA-FUS', 'name_en' => 'System Connector FMA-FUS',
				'sub' => 'هياكل متعددة الابعاد من قنوات FUS', 'sub_en' => 'Multidimensional FUS channel structures',
				'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/fischer-System-connector-FMA-FUS.png', 'badge' => '', 'badge_en' => '',
				'url' => $home . 'system-connector-fma-fus/',
				'specs' => array( 'لوحة قاعدة: فولاذ S355 عالي المقاومة', 'حامل البروفايل: فولاذ S235JR', 'فتحات طويلة مع شبكة FMHB', 'يعمل كفلنشة سرج او كزاوية' ),
				'specs_en' => array( 'Base plate: high-strength S355 steel', 'Profile mount: S235JR steel', 'Long slots with FMHB grating', 'Functions as saddle flange or angle' ),
			),
		);
	}
}

/**
 * Catalog grouped by section: doors / gypsum / strut.
 *
 * @return array<string,array>
 */
function osoul_catalog_grouped() {
	$groups = array( 'doors' => array(), 'gypsum' => array(), 'strut' => array() );
	foreach ( osoul_catalog() as $slug => $p ) {
		if ( isset( $groups[ $p['group'] ] ) ) {
			$groups[ $p['group'] ][ $slug ] = $p;
		}
	}
	return $groups;
}
