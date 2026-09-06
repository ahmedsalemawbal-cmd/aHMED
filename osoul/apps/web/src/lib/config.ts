/**
 * ══ إعداداتُ الاتّصال ══
 *
 * وقيمٌ افتراضيّةٌ مكتوبةٌ بجانب متغيّرات البيئة — على نمط
 * `midad/apps/web/src/lib/config.ts`. والسببُ عمليّ: البناءُ ينجح بلا ملفّ
 * `.env` وبلا خطوةِ سرٍّ في سير العمل، ويعمل عند من يستنسخ المستودعَ من
 * أوّل أمر.
 *
 * والمفتاحُ المنشور (`publishable`) ليس سرًّا — هو معرّفٌ للمشروع لا إذنٌ
 * بالقراءة. والذي يمنع القراءةَ سياساتُ RLS في القاعدة، لا خفاءُ المفتاح.
 *
 *     ما يحرسه الخفاءُ وحده ليس محروسًا.
 */
export const SUPABASE_URL =
  import.meta.env.VITE_SUPABASE_URL || 'https://bnixzzhcnfjdfmtiwjvr.supabase.co'

export const SUPABASE_KEY =
  import.meta.env.VITE_SUPABASE_KEY || 'sb_publishable_PT-cwZQ_mzdDebJAvdMe8w_MSNHMYfV'

/** عنوانُ الموقع — يُستعمل في الروابط المطلقة وschema.org وhreflang. */
export const SITE_URL =
  import.meta.env.VITE_SITE_URL || 'https://osoulalbinaa.com'

/** الضريبةُ المضافة في السعوديّة — تُحسب فوق (المجموع − الخصم). */
export const VAT_RATE = 15

/** سابقةُ ترقيم عروض أصول. وللشركاء سابقتُهم، ولكلٍّ عدّادُه. */
export const QUOTE_PREFIX = 'OSB'
