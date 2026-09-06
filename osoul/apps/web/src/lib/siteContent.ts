/**
 * ══ محتوى الموقع الثابت ══
 *
 * الخدماتُ الستُّ والمشاريعُ الستّة **مأخوذةٌ حرفيًّا** من
 * `inc/templates/sections.php` — لا مكتوبةٌ من جديد. فالنقلُ الذي يُعاد
 * فيه تأليفُ النصّ يُبدّل ما راجعه أصحابُ العمل، ويُدخل خطأً في اسمِ
 * جهةٍ أو عددِ أبوابٍ لا يظهر إلّا عند من يعرفه.
 *
 *     ما كتبه أهلُه يُنقَل، ولا يُعاد تأليفُه.
 *
 * وأيقونةُ كلِّ خدمةٍ مسارُ SVG كما هو في الأصل — فتبقى الأيقونةُ نفسَها
 * لا شبيهًا لها.
 *
 * ملفٌّ مولَّدٌ — لا يُحرَّر بيد.
 */

export type Service = {
  slug: string; h: string; h_en: string
  d: string; d_en: string; ico: string
}

export type Project = {
  cat: string; cat_en: string; h: string; h_en: string
  d: string; d_en: string; img: string
  loc: string; loc_en: string; year: string
}

/** أصلُ صور المشاريع على ووردبريس — يُبدَّل عند نقل الوسائط (م٨). */
export const PROJECT_IMG_BASE = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/'

export const SERVICES: Service[] = [
  { slug: 'service-supply', h: 'توريد المواد والمنتجات', h_en: 'Material & Product Supply',
    d: 'نوفر جميع احتياجاتك من الأبواب المعدنية وأنظمة الجبس بورد وإكسسوارات التثبيت بأفضل الأسعار وأعلى معايير الجودة',
    d_en: 'We supply all your needs of metal doors, gypsum board systems and fixing accessories at best prices and highest quality standards',
    ico: '<path d="M5 18H3a2 2 0 01-2-2V8a2 2 0 012-2h3.19M15 6h2a2 2 0 012 2v8a2 2 0 01-2 2h-3.19"/><line x1="23" y1="13" x2="23" y2="11"/><polyline points="11 6 7 12 13 12 9 18"/>' },
  { slug: 'service-install', h: 'التركيب والتنفيذ الاحترافي', h_en: 'Professional Installation',
    d: 'فريق متخصص من الفنيين المدربين لتركيب جميع منتجاتنا بدقة عالية وضمان الجودة في التنفيذ',
    d_en: 'Specialized team of trained technicians to install all our products with high precision and quality assurance',
    ico: '<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>' },
  { slug: 'service-guarantee', h: 'الضمان الشامل وما بعد البيع', h_en: 'Comprehensive Warranty & After-Sales',
    d: 'ضمان شامل على جميع منتجاتنا مع خدمات ما بعد البيع لضمان استمرارية عمل المنتجات بكفاءة',
    d_en: 'Comprehensive warranty on all products with after-sales services to ensure continued product efficiency',
    ico: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>' },
  { slug: 'service-consulting', h: 'الاستشارة الفنية والهندسية', h_en: 'Technical & Engineering Consulting',
    d: 'مهندسون متخصصون لتقديم الاستشارات الفنية الدقيقة واختيار المنتج الأمثل لمتطلبات مشروعك',
    d_en: 'Specialized engineers providing precise technical consultations and optimal product selection for your project',
    ico: '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M16.24 7.76a6 6 0 010 8.49M4.93 19.07a10 10 0 010-14.14M7.76 16.24a6 6 0 010-8.49"/>' },
  { slug: 'service-maintenance', h: 'الصيانة الدورية والطارئة', h_en: 'Periodic & Emergency Maintenance',
    d: 'خدمات صيانة متكاملة بجداول منتظمة واستجابة سريعة للحالات الطارئة للحفاظ على عمر المنتجات',
    d_en: 'Comprehensive maintenance services with regular schedules and fast emergency response to extend product life',
    ico: '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' },
  { slug: 'service-inspection', h: 'الفحص والتفتيش الفني', h_en: 'Technical Inspection & Testing',
    d: 'خدمات فحص وتفتيش متخصصة للتحقق من مطابقة المنتجات للمواصفات القياسية والمعايير الدولية',
    d_en: 'Specialized inspection and testing services to verify product compliance with standard and international specifications',
    ico: '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>' },
]

export const PROJECTS: Project[] = [
  { cat: 'حكومي', cat_en: 'Government',
    h: 'وزارة التعليم — توريد وتركيب أبواب مدارس جدة',
    h_en: 'Ministry of Education — Supply & Install of School Doors, Jeddah',
    d: 'توريد وتركيب أكثر من 800 باب مقاوم للحريق لمجموعة مدارس حكومية في مدينة جدة',
    d_en: 'Supply and installation of 800+ fire-resistant doors for government schools in Jeddah',
    img: 'DSC_2387-scaled.jpg', loc: 'جدة', loc_en: 'Jeddah', year: '2024' },
  { cat: 'صحي', cat_en: 'Healthcare',
    h: 'مستشفى الملك فهد — أبواب أقسام الطوارئ',
    h_en: 'King Fahd Hospital — Emergency Department Doors',
    d: 'توريد أبواب معدنية بمواصفات خاصة لأقسام الطوارئ والعمليات والعزل في مستشفى الملك فهد',
    d_en: 'Supply of special specification metal doors for emergency, surgical and isolation departments',
    img: 'DSC_2382-scaled.jpg', loc: 'جدة', loc_en: 'Jeddah', year: '2024' },
  { cat: 'تجاري', cat_en: 'Commercial',
    h: 'مجمع الرحاب التجاري — منظومة الجبس بورد',
    h_en: 'Al Rehab Complex — Gypsum Board Systems',
    d: 'توريد أنظمة البروفايلات الكاملة لألواح الجبسوم بورد في أسقف وجدران المجمع التجاري',
    d_en: 'Complete supply of gypsum board profile systems for ceilings and walls of the commercial complex',
    img: 'DSC_2368-scaled.jpg', loc: 'جدة', loc_en: 'Jeddah', year: '2025' },
  { cat: 'صناعي', cat_en: 'Industrial',
    h: 'منشأة صناعية — المدينة الصناعية الثالثة',
    h_en: 'Industrial Facility — 3rd Industrial City',
    d: 'توريد وتركيب أبواب معدنية صناعية ومنظومة تثبيت Strut لمنشأة تصنيع في جدة',
    d_en: 'Supply and installation of industrial metal doors and Strut fixing system for a Jeddah manufacturing facility',
    img: 'DSC_2377-scaled.jpg', loc: 'جدة', loc_en: 'Jeddah', year: '2025' },
  { cat: 'سكني', cat_en: 'Residential',
    h: 'مجمع بدر السكني — أبواب الوحدات',
    h_en: 'Badr Residential Complex — Unit Doors',
    d: 'توريد أبواب MDF وأبواب تكسية لوحدات سكنية متعددة في مجمع سكني حديث بحي الرحاب',
    d_en: 'Supply of MDF and cladding doors for multiple residential units in a modern complex in Al Rehab',
    img: 'DSC_2341-scaled-1.webp', loc: 'جدة', loc_en: 'Jeddah', year: '2025' },
  { cat: 'تعليمي', cat_en: 'Educational',
    h: 'جامعة الملك عبدالعزيز — كليات هندسة',
    h_en: 'King Abdulaziz University — Engineering Colleges',
    d: 'توريد أبواب مقاومة للحريق لمباني الكليات الهندسية بمعايير الجامعة وبمواصفات دولية معتمدة',
    d_en: 'Supply of fire-resistant doors for engineering college buildings per university standards and international specs',
    img: 'DSC_2347-scaled.jpg', loc: 'جدة', loc_en: 'Jeddah', year: '2024' },
]
