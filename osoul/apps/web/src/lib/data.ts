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
