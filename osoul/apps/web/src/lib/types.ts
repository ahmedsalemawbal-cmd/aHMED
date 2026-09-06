/** أنواعُ القاعدة — تُستعمل ولا تُخترع أسماءُ أعمدةٍ من عند النفس. */

export type GroupKey = 'doors' | 'gypsum' | 'strut'

export type Product = {
  id: string
  slug: string
  group_key: GroupKey
  name_ar: string; name_en: string
  sub_ar: string;  sub_en: string
  desc_ar: string; desc_en: string
  badge_ar: string; badge_en: string
  image_url: string
  specs_ar: string[]; specs_en: string[]
  certs: string[]
  is_active: boolean
  sort: number
}

export type QuoteStage = 'new' | 'pricing' | 'sent' | 'negotiation' | 'won' | 'lost'
export type QuoteKind = 'osoul' | 'supply' | 'cust'

/** بندٌ في سلّة طلب التسعير — يعيش في المتصفّح حتّى يُرسَل. */
export type CartLine = { slug: string; name_ar: string; name_en: string; qty: number }

export type Settings = {
  general: Record<string, string>
  contact: Record<string, string>
  brand: Record<string, string>
  quote_doc: Record<string, any>
}
