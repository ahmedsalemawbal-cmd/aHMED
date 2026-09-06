import { supabase } from './supabase'
import type { GroupKey, Product, Settings } from './types'

/**
 * الأعمدةُ تُسمّى صراحةً لا بـ`*`.
 *
 * ودرسُ مِداد في `lib/data.ts` عندها: كانت `select('*')` تجلب ثلاثةَ
 * عشرَ ميغابايتًا من المتون لترسم بطاقاتٍ ارتفاعُها مئةٌ وثلاثون بكسلًا.
 * وعمودٌ ثقيلٌ يُضاف غدًا يدخل القائمةَ بلا أن ينتبه أحد.
 *
 *     ما لا يُعرض لا يُجلَب.
 */
const LIST_COLS =
  'id,slug,group_key,name_ar,name_en,sub_ar,sub_en,badge_ar,badge_en,image_url,sort'

const FULL_COLS = LIST_COLS + ',desc_ar,desc_en,specs_ar,specs_en,certs,is_active'

export async function fetchProducts(group?: GroupKey): Promise<Product[]> {
  let q = supabase.from('products').select(LIST_COLS).eq('is_active', true).order('sort')
  if (group) q = q.eq('group_key', group)
  const { data, error } = await q
  if (error) throw new Error(error.message)
  return (data ?? []) as unknown as Product[]
}

export async function fetchProductBySlug(slug: string): Promise<Product | null> {
  const { data, error } = await supabase
    .from('products').select(FULL_COLS).eq('slug', slug).maybeSingle()
  if (error) throw new Error(error.message)
  return (data as unknown as Product) ?? null
}

/** عددُ منتجاتِ كلِّ عائلة — للرئيسة، بلا جلبِ المنتجات نفسِها. */
export async function fetchGroupCounts(): Promise<Record<GroupKey, number>> {
  const out = { doors: 0, gypsum: 0, strut: 0 } as Record<GroupKey, number>
  await Promise.all((Object.keys(out) as GroupKey[]).map(async (g) => {
    const { count } = await supabase
      .from('products').select('id', { count: 'exact', head: true })
      .eq('is_active', true).eq('group_key', g)
    out[g] = count ?? 0
  }))
  return out
}

/**
 * الإعداداتُ العامّة — أربعةُ مفاتيحَ يقرؤها الزائرُ بلا تسجيل دخول.
 * وما ينتهي بـ`_secret` لا تُخرجه السياسةُ أصلًا، فلا حاجةَ لترشيحه هنا.
 */
export async function fetchSettings(): Promise<Settings> {
  const { data, error } = await supabase
    .from('settings').select('key,value')
    .in('key', ['general', 'contact', 'brand', 'quote_doc'])
  if (error) throw new Error(error.message)
  const out: any = { general: {}, contact: {}, brand: {}, quote_doc: {} }
  for (const row of data ?? []) out[(row as any).key] = (row as any).value
  return out as Settings
}

/** طلبُ تواصلٍ أو تسعير — يكتبه الزائرُ بمفتاح anon، ولا يقرؤه بعدها. */
export async function submitLead(payload: {
  name: string; phone: string; email?: string
  subject?: string; message?: string
  source: 'contact' | 'quote'
  items?: { slug: string; name: string; qty: number }[]
  lang: string
}) {
  const { error } = await supabase.from('leads').insert({
    name: payload.name,
    phone: payload.phone,
    email: payload.email ?? '',
    subject: payload.subject ?? '',
    message: payload.message ?? '',
    source: payload.source,
    items: payload.items ?? [],
    lang: payload.lang,
    user_agent: navigator.userAgent.slice(0, 400),
  })
  if (error) throw new Error(error.message)
}

/* ════════════════ العروض ════════════════ */

import type { QuoteKind, QuoteStage } from './types'

export type QuoteRow = {
  id: string
  number: string | null
  kind: QuoteKind
  stage: QuoteStage
  source: 'web' | 'rep' | 'branch' | 'partner'
  customer_name: string
  customer_phone: string
  issued_at: string | null
  token: string
  created_at: string
  rep_id: string | null
  branch_id: string | null
  partner_id: string | null
}

const QUOTE_COLS =
  'id,number,kind,stage,source,customer_name,customer_phone,issued_at,token,created_at,' +
  'rep_id,branch_id,partner_id'

/**
 * قائمةُ العروض.
 *
 * ولا تُمرَّر هنا شروطُ «مَن يرى ماذا» — لا `rep_id = me` ولا استثناءُ
 * `cust`. تلك تفرضها السياساتُ في القاعدة، وإعادتُها هنا توهم أنّها
 * تحمي، فإذا نُسيت مرّةً ظُنّ أنّ الحمايةَ ذهبت وهي قائمة، أو — وهو
 * الأسوأ — بُنيت شاشةٌ تعتمد عليها.
 *
 *     ما تحرسه القاعدةُ لا يُعاد حَرْسُه في الاستعلام.
 *
 * وما هنا **ترشيحٌ للعرض** لا للأمان: مرحلةٌ يختارها المستخدم، وبحثٌ.
 */
export async function fetchQuotes(opts: {
  stage?: QuoteStage | ''
  search?: string
  kind?: QuoteKind
  limit?: number
} = {}): Promise<QuoteRow[]> {
  let q = supabase.from('quotes').select(QUOTE_COLS)
    .is('archived_at', null)
    .order('created_at', { ascending: false })
    .limit(opts.limit ?? 200)
  if (opts.stage) q = q.eq('stage', opts.stage)
  if (opts.kind) q = q.eq('kind', opts.kind)
  if (opts.search?.trim()) {
    const s = opts.search.trim().replace(/[%,()]/g, '')
    q = q.or(`customer_name.ilike.%${s}%,customer_phone.ilike.%${s}%,number.ilike.%${s}%`)
  }
  const { data, error } = await q
  if (error) throw new Error(error.message)
  return (data ?? []) as unknown as QuoteRow[]
}

/** مجاميعُ عرضٍ — تأتي من المنظر، ولا تصل إلّا لمن يحقّ له المال. */
export async function fetchQuoteTotals(quoteId: string) {
  const { data, error } = await supabase
    .from('quote_totals').select('*').eq('quote_id', quoteId).maybeSingle()
  if (error) throw new Error(error.message)
  return data as null | {
    quote_id: string; subtotal: number; discount: number
    taxable: number; vat: number; total: number
  }
}

export type StageCount = Record<QuoteStage, number>

/** عددُ العروض في كلّ مرحلة — للوحة القيادة، بلا جلبِ العروض. */
export async function fetchStageCounts(): Promise<StageCount> {
  const stages: QuoteStage[] = ['new', 'pricing', 'sent', 'negotiation', 'won', 'lost']
  const out = {} as StageCount
  await Promise.all(stages.map(async (st) => {
    const { count } = await supabase.from('quotes')
      .select('id', { count: 'exact', head: true })
      .eq('stage', st).is('archived_at', null)
    out[st] = count ?? 0
  }))
  return out
}

export const STAGES: { key: QuoteStage; ar: string; en: string; ur: string }[] = [
  { key: 'new',         ar: 'جديد',        en: 'New',         ur: 'نیا' },
  { key: 'pricing',     ar: 'قيد التسعير', en: 'Pricing',     ur: 'قیمت کاری' },
  { key: 'sent',        ar: 'عرض مُرسل',   en: 'Quote Sent',  ur: 'کوٹیشن بھیجا گیا' },
  { key: 'negotiation', ar: 'تفاوض',       en: 'Negotiation', ur: 'گفت و شنید' },
  { key: 'won',         ar: 'مقبول',       en: 'Won',         ur: 'منظور' },
  { key: 'lost',        ar: 'مرفوض',       en: 'Lost',        ur: 'مسترد' },
]

export const SOURCE_LABELS: Record<string, string> = {
  web: 'الموقع', rep: 'مندوب', branch: 'فرع', partner: 'شريك',
}
