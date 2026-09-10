/**
 * Osoul Mail — تصنيفات الرسائل.
 *
 * التصنيف يُخزَّن كـ IMAP keyword باسم "osoul_<slug>" على الرسالة نفسها، لا في
 * قاعدة بيانات محلية. هذا مقصود: الكلمات المفتاحية تعيش في صندوق البريد على
 * الخادم، فالتصنيف الذي يضعه الموظف هنا يظهر في نسخة الويب والعكس، ولا يضيع
 * عند إعادة تثبيت التطبيق أو استخدامه من جهاز آخر.
 *
 * التسمية والألوان مطابقة لما في نسخة الويب حتى لا يختلف تصنيف عن تصنيف.
 */

'use strict';

/** التصنيفات الخمسة المعتمدة في الشركة. */
const LABELS = Object.freeze([
  { slug: 'projects', ar: 'مشاريع', en: 'Projects', color: '#1683bd' },
  { slug: 'procurement', ar: 'مشتريات', en: 'Procurement', color: '#2f7d5c' },
  { slug: 'quality', ar: 'جودة وسلامة', en: 'Quality & safety', color: '#c9922f' },
  { slug: 'hr', ar: 'موارد بشرية', en: 'HR', color: '#5b4b8a' },
  { slug: 'finance', ar: 'مالية', en: 'Finance', color: '#7a3550' },
]);

const PREFIX = 'osoul_';

/**
 * الكلمة المفتاحية المقابلة لتصنيف.
 * حروف ASCII فقط: خوادم IMAP (Dovecot منها) لا تقبل كلمات مفتاحية عربية،
 * وتخزّنها بحروف صغيرة.
 */
function keyword(slug) {
  const clean = String(slug || '').toLowerCase().replace(/[^a-z0-9_]/g, '');
  return clean ? PREFIX + clean : '';
}

/** عكس keyword(): أول تصنيف موجود في قائمة أعلام الرسالة، أو ''. */
function fromFlags(flags) {
  const list = flags instanceof Set ? [...flags] : Array.isArray(flags) ? flags : [];
  for (const raw of list) {
    const f = String(raw).toLowerCase();
    if (f.startsWith('\\')) continue; // علم نظام لا تصنيف
    if (!f.startsWith(PREFIX)) continue;
    const slug = f.slice(PREFIX.length);
    if (slug) return slug;
  }
  return '';
}

/** تعريف تصنيف بالـ slug، أو null. */
function find(slug) {
  return LABELS.find((l) => l.slug === String(slug || '').toLowerCase()) || null;
}

/** كل الكلمات المفتاحية للتصنيفات — لإزالة القديم قبل وضع الجديد. */
function allKeywords() {
  return LABELS.map((l) => keyword(l.slug));
}

module.exports = { LABELS, PREFIX, keyword, fromFlags, find, allKeywords };
