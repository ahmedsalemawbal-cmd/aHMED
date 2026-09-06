/**
 * ══ نصوصُ الرئيسة وصفحة «من نحن» ══
 *
 * منقولةٌ من `inc/templates/home.php` و`about.php` — **نصًّا نصًّا**.
 * وهذه أخطرُ ما في النقل: الشيفرةُ تُقاس فتُصلَح، والنصُّ لا يُقاس —
 * فلو بدّلتُ «نصنع الجودة ونبني الثقة» بعبارةٍ من عندي لَما سقط بناءٌ
 * ولا احمرَّ فحص، ولَاكتشفه صاحبُ العمل وحده بعد النشر.
 *
 *     الشيفرةُ يكشف خطأَها الحاسوب، والنصُّ يكشفه صاحبُه.
 *
 * والأرقامُ فيها التزاماتٌ تُقال للعملاء (‏+٥٠٠ مشروع · ‏+٢٠ سنة ·
 * ‏٩٥٪ رضا · ‏+٥٠ مهندسًا) — فلا تُقرَّب ولا تُجمَّل.
 */

export type Bi = { ar: string; en: string }
const bi = (ar: string, en: string): Bi => ({ ar, en })

export const HOME = {
  hero: {
    // العنوانُ كلمتان مُبرَزتان بين ثلاثِ كلماتٍ عاديّة، كما في الأصل
    lead:  bi('نصنع', 'We build'),
    hi1:   bi('الجودة', 'quality'),
    mid:   bi('ونبني', 'and earn'),
    hi2:   bi('الثقة', 'trust'),
    sub:   bi(
      'شريكك الموثوق في تصنيع الأبواب المعدنية المقاومة للحريق، وأنظمة الجبس بورد، وإكسسوارات التكييف — لخدمة المشاريع الحكومية والتجارية في المملكة',
      'Your trusted partner in manufacturing fire-resistant metal doors, gypsum board systems and HVAC accessories — serving government and commercial projects across the Kingdom'),
    place: bi('أصول البناء للصناعة — جدة، المملكة العربية السعودية',
              'Osoul Albinaa Industrial — Jeddah, Saudi Arabia'),
    badgeCert: bi('معتمد دولياً', 'Internationally certified'),
    badgeYears: bi('+20 سنة خبرة', '20+ years of experience'),
    ctaQuote: bi('اطلب عرض سعر', 'Request a quote'),
    ctaProducts: bi('استعرض المنتجات', 'Browse products'),
  },

  intro: {
    kicker: bi('شركة سعودية', 'A Saudi company'),
    title:  bi('تصنع الفرق', 'that makes the difference'),
    body:   bi(
      'أصول البناء للصناعة — شركة سعودية متخصصة تأسست في جدة، تعمل في تصنيع وتوريد الأبواب المعدنية المقاومة للحريق، وأنظمة الجبس بورد، وإكسسوارات التكييف، وأنظمة تثبيت الدعامات لخدمة المشاريع الكبرى في المملكة.',
      'Osoul Albinaa Industrial is a specialised Saudi company founded in Jeddah, manufacturing and supplying fire-resistant metal doors, gypsum board systems, HVAC accessories and strut fixing systems for major projects across the Kingdom.'),
    points: [
      bi('منتجات معتمدة دولياً بمعايير ISO وSASO',
         'Internationally certified products to ISO and SASO standards'),
      bi('أكثر من 500 مشروع منجز في 15 مدينة سعودية',
         'Over 500 completed projects in 15 Saudi cities'),
      bi('فريق هندسي متخصص وضمان شامل على جميع المنتجات',
         'A specialised engineering team and full warranty on all products'),
      bi('خطوط إنتاج متكاملة بأحدث التقنيات الصناعية',
         'Integrated production lines with the latest industrial technology'),
    ],
    cta: bi('تعرف علينا أكثر', 'Learn more about us'),
  },

  solutions: {
    kicker: bi('حلول صناعية', 'Integrated industrial'),
    title:  bi('متكاملة', 'solutions'),
    sub:    bi(
      'نوفر طيفاً واسعاً من المنتجات الصناعية عالية الجودة لتلبية احتياجات المشاريع الحكومية والتجارية والصناعية',
      'A broad range of high-quality industrial products for government, commercial and industrial projects'),
    cards: [
      { to: '/doors',
        kicker: bi('أبواب صناعية', 'Industrial doors'),
        t1: bi('الأبواب بأنواعها', 'Doors of every kind'),
        t2: bi('وتفاصيلها الكاملة', 'and all their details'),
        d:  bi('أبواب معدنية مجوفة ومقاومة للحريق بمختلف المقاسات والمواصفات، معتمدة بدرجات مقاومة من 30 إلى 120 دقيقة',
               'Hollow and fire-resistant metal doors in all sizes and specifications, rated from 30 to 120 minutes') },
      { to: '/gypsum',
        kicker: bi('أنظمة التشطيب', 'Finishing systems'),
        t1: bi('بروفايلات الجبسوم', 'Gypsum and cement'),
        t2: bi('والسمنت بورد', 'board profiles'),
        d:  bi('أنظمة بروفايلات متكاملة لتركيب ألواح الجبسوم بورد في الأسقف والجدران، من الصلب المجلفن عالي الجودة',
               'Complete profile systems for installing gypsum board in ceilings and walls, in high-grade galvanised steel') },
      { to: '/strut',
        kicker: bi('أنظمة التثبيت', 'Fixing systems'),
        t1: bi('بروفايلات وإكسسوارات', 'Profiles and accessories'),
        t2: bi('التثبيت والتعليق', 'for fixing and suspension'),
        d:  bi('منظومة متكاملة من بروفايلات Strut وإكسسوارات التثبيت والتعليق المجلفنة لمختلف الأنظمة الصناعية',
               'A complete system of Strut profiles and galvanised fixing and suspension accessories for industrial systems') },
    ],
    cardCta: bi('استعرض المنتج', 'View products'),
  },

  work: {
    kicker: bi('إنجازات', 'Achievements'),
    title:  bi('نفخر بها', 'we are proud of'),
    sub:    bi('من أبرز مشاريعنا المنجزة في المملكة العربية السعودية',
               'Among our most notable completed projects in Saudi Arabia'),
    featured: {
      kicker: bi('أحدث مشاريعنا', 'Our latest project'),
      t1: bi('توريد وتركيب أبواب', 'Supply and installation of'),
      t2: bi('مقاومة للحريق', 'fire-resistant doors'),
      d:  bi('مشروع توريد وتركيب أكثر من 450 باباً معدنياً مقاوماً للحريق في مجمع سكني وتجاري كبير بمدينة جدة — مع ضمان شامل 3 سنوات',
             'Supply and installation of over 450 fire-resistant metal doors in a large residential and commercial complex in Jeddah — with a full 3-year warranty'),
      loc: bi('جدة — المملكة العربية السعودية', 'Jeddah — Saudi Arabia'),
      tag: bi('باب — معتمد ISO', 'doors — ISO certified'),
      count: '450',
    },
    others: [
      bi('وزارة التعليم — مدارس جدة', 'Ministry of Education — Jeddah schools'),
      bi('مستشفى الملك فهد', 'King Fahd Hospital'),
      bi('مجمع تجاري — حي الرحاب', 'Commercial complex — Al-Rehab district'),
    ],
    cta: bi('استعرض جميع المشاريع', 'View all projects'),
  },

  features: {
    kicker: bi('نقدم', 'We offer'),
    title:  bi('أكثر من منتج', 'more than a product'),
    items: [
      { h: bi('جودة معتمدة دولياً', 'Internationally Certified'),
        d: bi('منتجاتنا مطابقة لمعايير ISO وSASO وتخضع لاختبارات صارمة قبل التسليم',
              'Our products comply with ISO and SASO standards and undergo rigorous testing before delivery') },
      { h: bi('التسليم في الوقت', 'On-Time Delivery'),
        d: bi('نلتزم بمواعيد التسليم المتفق عليها ونحرص على ديمومة سلسلة الإمداد',
              'We honor agreed delivery schedules and maintain a reliable supply chain') },
      { h: bi('فريق متخصص', 'Expert Team'),
        d: bi('مهندسون وفنيون ذوو خبرة عالية جاهزون لدعمك في كل مراحل المشروع',
              'Qualified engineers and technicians ready to support you at every project phase') },
      { h: bi('دعم ما بعد البيع', 'After-Sales Support'),
        d: bi('نقدم ضماناً شاملاً ودعماً فنياً مستمراً لضمان رضاك التام عن منتجاتنا',
              'We provide comprehensive warranty and ongoing technical support for your complete satisfaction') },
    ],
  },

  partners: { title: bi('شركاؤنا وعملاؤنا', 'Our partners and clients') },

  cta: {
    t1: bi('مشروعك القادم', 'Your next project'),
    t2: bi('يبدأ من هنا', 'starts here'),
    d:  bi('تواصل معنا اليوم واحصل على عرض سعر مخصص لمشروعك — فريقنا جاهز للرد',
           'Contact us today for a quote tailored to your project — our team is ready'),
    quote: bi('اطلب عرض سعر', 'Request a quote'),
    wa:    bi('واتساب مباشر', 'WhatsApp us'),
  },
}

export const ABOUT = {
  hero: {
    kicker: bi('شركة وطنية رائدة', 'A leading national company'),
    title:  bi('في قلب الصناعة السعودية', 'at the heart of Saudi industry'),
    strip:  bi('المدينة الصناعية الثالثة — جدة · معادن · أبواب · جبس بورد · هياكل معدنية',
               'Third Industrial City — Jeddah · Metals · Doors · Gypsum board · Steel structures'),
    ctaContact:  bi('تواصل معنا', 'Contact us'),
    ctaProducts: bi('استعرض منتجاتنا', 'Browse our products'),
  },

  // أرقامٌ تُقال للعملاء — تُنقَل كما هي ولا تُقرَّب
  stats: [
    { n: '+500', l: bi('مشروع منجز', 'completed projects') },
    { n: '+20',  l: bi('عاماً من الخبرة', 'years of experience') },
    { n: '95%',  l: bi('رضا العملاء', 'client satisfaction') },
    { n: '+50',  l: bi('مهندس ومتخصص', 'engineers and specialists') },
  ],

  intro: {
    kicker: bi('شركة أصول البناء الصناعية', 'Osoul Albinaa Industrial'),
    title:  bi('شريك التصنيع الوطني الموثوق', 'The trusted national manufacturing partner'),
    p1: bi('شركة أصول البناء الصناعية هي شركة وطنية رائدة، تتخذ من المدينة الصناعية الثالثة بجدة مقراً لها. منذ انطلاقتنا وضعنا الجودة والكفاءة الهندسية كركيزة أساسية لعملنا، مما أهّلنا لنكون الشريك الموثوق للكثير من الجهات في القطاعين الحكومي والخاص.',
           'Osoul Albinaa Industrial is a leading national company headquartered in the Third Industrial City in Jeddah. From the outset we made quality and engineering competence the foundation of our work, which has made us the trusted partner of many organisations in both the public and private sectors.'),
    p2: bi('تجمع أصول البناء بين الخبرات البشرية المؤهلة وتقنيات صناعية متطورة؛ حيث يضم مصنعنا خطوط إنتاج متكاملة للأعمال المعدنية، إلى جانب قسم متخصص في تشكيل المعادن، مما يمنحنا القدرة على تنفيذ أعقد المشاريع بدقة متناهية.',
           'Osoul Albinaa combines qualified people with advanced industrial technology: our factory houses integrated production lines for metalwork alongside a dedicated metal-forming department, giving us the ability to execute the most complex projects with exacting precision.'),
  },

  lines: {
    title: bi('خطوط الإنتاج المتخصصة', 'Specialised production lines'),
    items: [
      bi('الأبواب المعدنية المجوفة', 'Hollow metal doors'),
      bi('الأبواب المقاومة للحريق', 'Fire-resistant doors'),
      bi('أنظمة الجبس والإسمنت', 'Gypsum and cement systems'),
      bi('ملحقات مجاري الهواء', 'Air-duct accessories'),
      bi('الهياكل المعدنية (WPC)', 'Steel structures (WPC)'),
      bi('الألمنيوم والزجاج', 'Aluminium and glass'),
    ],
  },

  vision: {
    label: bi('رؤيتنا', 'Our vision'),
    title: bi('الشريك الوطني الأكثر موثوقية', 'The most trusted national partner'),
    body:  bi('أن نكون الشريك الوطني الأكثر موثوقية والأقوى أثراً في المشاركة في صياغة مستقبل النهضة الصناعية والعمرانية في المملكة العربية السعودية، من خلال تقديم حلول هندسية وصناعية متكاملة فائقة الجودة.',
              'To be the most trusted and most influential national partner in shaping the future of industrial and urban development in Saudi Arabia, through integrated engineering and industrial solutions of the highest quality.'),
  },

  mission: {
    label: bi('رسالتنا', 'Our mission'),
    title: bi('معايير هندسية لا تقبل المساومة', 'Engineering standards that admit no compromise'),
    body:  bi('نلتزم بتوفير منتجات حديدية ومعدنية متطورة بأعلى معايير الكفاءة الهندسية، ملتزمين بالدقة المتناهية ومستندين إلى بنية تحتية تقنية متطورة تلبي تطلعات شركائنا في القطاعين الحكومي والخاص.',
              'We are committed to providing advanced steel and metal products to the highest standards of engineering competence, with exacting precision and an advanced technical infrastructure that meets the expectations of our partners in both the public and private sectors.'),
  },

  vision2030: {
    label: bi('نساهم في رؤية 2030', 'Contributing to Vision 2030'),
    title: bi('نهضة صناعية وطنية مستدامة', 'A sustainable national industrial renaissance'),
  },

  ceo: {
    label: bi('كلمة المدير التنفيذي', 'A word from the CEO'),
    p1: bi('إن سياسة شركة أصول البناء ترتكز في مقامها الأول على تقديم منتجات وخدمات عالية الجودة، مطابقة لأعلى المواصفات والمعايير الفنية — بهدف نيل رضا عملائنا الكرام وتجاوز تطلعاتهم.',
           'Osoul Albinaa\'s policy rests first and foremost on delivering high-quality products and services that meet the highest technical specifications and standards — to earn our clients\' satisfaction and exceed their expectations.'),
    p2: bi('تلتزم الشركة بتحقيق نمو مستمر ومطرد عبر تطبيق نظام صارم لإدارة الجودة يتوافق مع المعايير الدولية والمهنية، مستندةً إلى كوادر بشرية مؤهلة وأحدث التقنيات الصناعية.',
           'The company is committed to steady, continuous growth through a rigorous quality-management system aligned with international and professional standards, supported by qualified people and the latest industrial technology.'),
    name:  bi('م. عبد الرحمن غالب', 'Eng. Abdulrahman Ghalib'),
    role:  bi('المدير التنفيذي — شركة أصول البناء للصناعة', 'Chief Executive Officer — Osoul Albinaa Industrial'),
  },

  process: {
    title: bi('من طلبك إلى التسليم', 'From your request to delivery'),
    steps: [
      { h: bi('تقديم الطلب', 'Submit your request'),
        d: bi('مقاساتك + متطلباتك — نرد بعرض خلال 24 ساعة',
              'Your sizes and requirements — we reply with a quote within 24 hours') },
      { h: bi('التصنيع', 'Manufacturing'),
        d: bi('Roll Forming & CNC — دقة وتحكم كامل',
              'Roll forming and CNC — precision and full control') },
      { h: bi('فحص الجودة', 'Quality inspection'),
        d: bi('كل منتج يخضع لاختبارات ISO قبل التسليم',
              'Every product undergoes ISO testing before delivery') },
      { h: bi('التسليم + الضمان', 'Delivery and warranty'),
        d: bi('في الموعد + وثائق رسمية + خدمة ما بعد البيع',
              'On time, with official documentation and after-sales service') },
    ],
  },

  certs: {
    label: bi('الشهادات والاعتمادات', 'Certifications and accreditations'),
    title: bi('موثقة ومعتمدة', 'Documented and accredited'),
    items: [
      { h: bi('شهادة الجودة', 'Quality certificate'),   d: bi('ISO 9001 إدارة الجودة', 'ISO 9001 quality management') },
      { h: bi('شهادة السلامة', 'Safety certificate'),   d: bi('معايير السلامة من الحريق', 'Fire-safety standards') },
      { h: bi('شهادة الاعتماد', 'Accreditation'),       d: bi('SGS للجودة والاختبار', 'SGS for quality and testing') },
    ],
  },
}
