/**
 * Osoul Mail — دليل موظفي الشركة.
 *
 * دفتر العناوين المبني من الصندوق لا يعرف إلا من راسلك من قبل، فالموظف
 * الجديد يفتح بريده ويجده فارغًا ولا يستطيع مراسلة زميل إلا بحفظ عنوانه
 * يدويًا. الدليل يحل ذلك: قائمة الشركة كاملة حاضرة عند كل موظف من أول
 * تشغيل، فيكتب الاسم ويختار.
 *
 * الأسماء والعناوين فقط — لا كلمات مرور ولا أي سرّ. وكلها عناوين داخل نطاق
 * الشركة يعرفها الجميع أصلًا.
 *
 * التعديل بلا إعادة بناء: مسؤول النظام يضع "directory" في policy.json،
 * فيُدمج فوق هذه القائمة — يصحّح اسمًا أو قسمًا، أو يضيف موظفًا جديدًا.
 */

'use strict';

/**
 * الأقسام تُشتق من اسم الصندوق حين يكون واضحًا لا يحتمل التأويل.
 * ما كان غامضًا يُترك فارغًا: قسم مكتوب خطأ أسوأ من قسم غير مكتوب.
 */
const DEPARTMENTS = {
  ceo: { ar: 'الرئيس التنفيذي', en: 'CEO' },
  'ceo.es': { ar: 'مكتب الرئيس التنفيذي', en: 'CEO Office' },
  gm: { ar: 'المدير العام', en: 'General Manager' },
  pm: { ar: 'إدارة المشاريع', en: 'Project Management' },
  fin: { ar: 'المالية', en: 'Finance' },
  qc: { ar: 'الجودة', en: 'Quality' },
  purchase: { ar: 'المشتريات', en: 'Procurement' },
  production: { ar: 'الإنتاج', en: 'Production' },
  's.eng': { ar: 'الهندسة', en: 'Engineering' },
  'tech.dep': { ar: 'القسم الفني', en: 'Technical' },
  'tech.dep1': { ar: 'القسم الفني', en: 'Technical' },
  'tech.depl': { ar: 'القسم الفني', en: 'Technical' },
  'hr.dep': { ar: 'الموارد البشرية', en: 'Human Resources' },
  hr1: { ar: 'الموارد البشرية', en: 'Human Resources' },
  'sep.hr': { ar: 'الموارد البشرية', en: 'Human Resources' },
  sales: { ar: 'المبيعات', en: 'Sales' },
  sales1: { ar: 'المبيعات', en: 'Sales' },
  sales2: { ar: 'المبيعات', en: 'Sales' },
  sales3: { ar: 'المبيعات', en: 'Sales' },
  sales4: { ar: 'المبيعات', en: 'Sales' },
  sales5: { ar: 'المبيعات', en: 'Sales' },
  'sales.co': { ar: 'المبيعات', en: 'Sales' },
  info: { ar: 'الاستقبال', en: 'Reception' },
};

/**
 * قائمة الشركة: الاسم كما ورد في كشف الموارد البشرية، ثم البريد، ثم الاسم
 * بالعربية.
 *
 * الكشف إنجليزي بالكامل، ونصف الموظفين يكتبون بالعربية — بلا هذا العمود
 * يبحث أحدهم عن "عمر" فلا يجد شيئًا. الأسماء العربية منقولة صوتيًا وقد
 * تحتاج تصحيحًا، ويصحّحها المسؤول في policy.json بلا إعادة بناء.
 */
const PEOPLE = [
  ['ABDULRAHMAN GHALIB', 'ceo@osoulalbinaa.com', 'عبدالرحمن غالب'],
  ['AVEENA NA', 'ceo.es@osoulalbinaa.com', 'أفينا نا'],
  ['MOHAMMED GHALIB', 'gm@osoulalbinaa.com', 'محمد غالب'],
  ['Mohammed Ghalib', 'm.ghalib@osoulalbinaa.com', 'محمد غالب'],
  ['MOHANNED BADYAN', 'pm@osoulalbinaa.com', 'مهند بديان'],
  ['OMAR HELMY', 'dpd@osoulalbinaa.com', 'عمر حلمي'],
  ['MOHAMMED SAEED', 'cd@osoulalbinaa.com', 'محمد سعيد'],
  ['MOHAMMED LATIF', 's.eng@osoulalbinaa.com', 'محمد لطيف'],
  ['MOHMMED IBRAHIM', 'tech.dep@osoulalbinaa.com', 'محمد إبراهيم'],
  ['MOHAMMED RAFIQ', 'tech.dep1@osoulalbinaa.com', 'محمد رفيق'],
  ['MOHAMMED RAFIQ', 'tech.depl@osoulalbinaa.com', 'محمد رفيق'],
  ['ABDULHAMED HASHEED', 'fin@osoulalbinaa.com', 'عبدالحميد حشيد'],
  ['WEJDAN AQEL', 'qc@osoulalbinaa.com', 'وجدان عقل'],
  ['Tariq AlAmoudi', 'purchase@osoulalbinaa.com', 'طارق العمودي'],
  ['Shawqi', 'production@osoulalbinaa.com', 'شوقي'],
  ['RAGHAD ALHARBI', 'hr.dep@osoulalbinaa.com', 'رغد الحربي'],
  ['Labeb AlQebati', 'hr1@osoulalbinaa.com', 'لبيب القباطي'],
  ['Reham AlShemrani', 'sep.hr@osoulalbinaa.com', 'ريهام الشمراني'],
  ['Osoul Albinaa', 'sales@osoulalbinaa.com', 'أصول البناء'],
  ['HASSAN SAFWAT', 'sales1@osoulalbinaa.com', 'حسن صفوت'],
  ['HUSSIAN ALSHAAR', 'sales2@osoulalbinaa.com', 'حسين الشعار'],
  ['RAGHAD OMAR', 'sales3@osoulalbinaa.com', 'رغد عمر'],
  ['Peter Hani', 'sales4@osoulalbinaa.com', 'بيتر هاني'],
  ['Abdulrhman Faisal', 'sales5@osoulalbinaa.com', 'عبدالرحمن فيصل'],
  ['FARAH ALJDAANE', 'sales.co@osoulalbinaa.com', 'فرح الجدعان'],
  ['ABDULHAKIM HASSAN', 'wds@osoulalbinaa.com', 'عبدالحكيم حسن'],
  ['ABDULJALEL ALADEM', 'dwsd@osoulalbinaa.com', 'عبدالجليل العادم'],
  ['ABDULMALIK DBWAN', 'pw@osoulalbinaa.com', 'عبدالملك دبوان'],
  ['AYUB SHEIKH', 'epo@osoulalbinaa.com', 'أيوب شيخ'],
  ['DEYAA AMMAR', 'fm@osoulalbinaa.com', 'ضياء عمار'],
  ['RASEL MIA', 'st@osoulalbinaa.com', 'راسل ميا'],
  ['TAMADUR ALJEDANI', 'dc@osoulalbinaa.com', 'تماضر الجدعاني'],
  ['Anwar', 'main@osoulalbinaa.com', 'أنور'],
  ['info', 'info@osoulalbinaa.com', 'استعلامات'],
];

/**
 * "AVEENA NA" → "Aveena Na". الكشوف تُكتب بحروف كبيرة، والواجهة ليست كشفًا.
 *
 * أما "AlShemrani" فتبقى كما هي: الحرف الكبير داخل الاسم مقصود، وتصغيره
 * يعطي "Alshemrani" — اسمًا لم يكتبه صاحبه هكذا قط.
 */
function titleCase(name) {
  const raw = String(name || '').trim();
  if (!raw) return '';
  const letters = raw.replace(/[^\p{L}]/gu, '');
  const shouting = letters && letters === letters.toUpperCase();
  if (!shouting) return raw;
  return raw
    .toLowerCase()
    .replace(/(^|[\s'’-])(\p{L})/gu, (_m, sep, ch) => sep + ch.toUpperCase());
}

function localPart(email) {
  return String(email || '').split('@')[0].toLowerCase();
}

function entry(name, email, arabic) {
  const addr = String(email || '').trim().toLowerCase();
  return {
    email: addr,
    name: titleCase(name) || addr,
    nameAr: String(arabic || '').trim(),
    dept: DEPARTMENTS[localPart(addr)] || null,
    directory: true,
  };
}

/**
 * الدليل النهائي بعد دمج تعديلات السياسة.
 *
 * مدخلة في policy.json بنفس البريد تحلّ محل المدمجة (تصحيح اسم أو قسم)،
 * وببريد جديد تُضاف. وإن ضُبط directoryReplace فالقائمة المدمجة تُطرح كليًا،
 * لشركة تريد كشفها هي لا كشفنا.
 */
function build(policy) {
  const p = policy || {};
  const base = p.directoryReplace ? [] : PEOPLE.map(([n, e, a]) => entry(n, e, a));
  const byEmail = new Map(base.map((x) => [x.email, x]));

  for (const row of Array.isArray(p.directory) ? p.directory : []) {
    const email = String((row && row.email) || '').trim().toLowerCase();
    if (!email.includes('@')) continue;
    const found = byEmail.get(email);
    const dept = row.dept && (row.dept.ar || row.dept.en)
      ? { ar: row.dept.ar || row.dept.en, en: row.dept.en || row.dept.ar }
      : (found && found.dept) || DEPARTMENTS[localPart(email)] || null;
    byEmail.set(email, {
      email,
      name: row.name ? String(row.name) : (found ? found.name : email),
      nameAr: row.nameAr ? String(row.nameAr) : (found ? found.nameAr : ''),
      dept,
      directory: true,
    });
  }

  return [...byEmail.values()];
}


/**
 * باحث عن الاسم بالعنوان.
 *
 * كثير من الزملاء لا يضبطون اسم المرسل في برامجهم، فتصل رسائلهم بعنوان
 * عارٍ مثل s.eng@ — والموظف يرى صندوقًا لا شخصًا. الدليل يعرف من هو، فنملأ
 * الاسم الناقص من عندنا عند العرض.
 *
 * لا نستبدل اسمًا موجودًا: ما كتبه صاحب الرسالة عن نفسه أولى بالثقة.
 *
 * @param {object} policy
 * @returns {(address: {name?:string, email?:string}) => {name:string, email:string}}
 */
function resolver(policy) {
  const index = new Map(build(policy).map((p) => [p.email, p]));

  return function named(address) {
    const a = address || {};
    const email = String(a.email || '').trim();
    const name = String(a.name || '').trim();
    if (name && name.toLowerCase() !== email.toLowerCase()) {
      return { name, email };
    }
    const found = index.get(email.toLowerCase());
    return { name: found ? found.name : name, email, nameAr: found ? found.nameAr : '' };
  };
}

module.exports = { build, resolver, PEOPLE, DEPARTMENTS, titleCase };
