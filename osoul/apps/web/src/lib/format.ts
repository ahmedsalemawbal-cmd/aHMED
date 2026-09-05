import { getLang } from './i18n'
import { VAT_RATE } from './config'

/**
 * ══ التنسيق ══
 *
 * والأرقامُ **غربيّةٌ دائمًا** (١٢٣ تُكتب 123) في المبالغ والتواريخ — قرارٌ
 * ورثناه من الإضافة: `osoul_money()` تستعمل `number_format` بأرقامٍ لاتينيّة.
 * والسببُ أنّ العروضَ تُطبَع وتُرسَل إلى جهاتٍ حكوميّةٍ وموردين، والفاتورةُ
 * الضريبيّةُ السعوديّةُ تُقرأ بأرقامٍ غربيّة.
 */

const LOCALE: Record<string, string> = { ar: 'ar-SA', en: 'en-US', ur: 'ur-PK' }

function loc(): string {
  // `-u-nu-latn` يُجبر الأرقامَ الغربيّةَ حتّى في المحليّة العربيّة
  return (LOCALE[getLang()] || 'ar-SA') + '-u-nu-latn'
}

/** مبلغٌ بالريال — منزلتان دائمًا، كما في `osoul_money()`. */
export function fmtMoney(n: number | null | undefined): string {
  const v = Number(n ?? 0)
  return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/** عددٌ صحيحٌ بفواصل الآلاف. */
export function fmtNum(n: number | null | undefined): string {
  return Number(n ?? 0).toLocaleString('en-US')
}

export function fmtDate(d: string | Date | null | undefined): string {
  if (!d) return '—'
  const date = typeof d === 'string' ? new Date(d) : d
  if (isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(loc(), { year: 'numeric', month: 'long', day: 'numeric' })
}

export function fmtShort(d: string | Date | null | undefined): string {
  if (!d) return '—'
  const date = typeof d === 'string' ? new Date(d) : d
  if (isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(loc(), { year: 'numeric', month: '2-digit', day: '2-digit' })
}

export function fmtDateTime(d: string | Date | null | undefined): string {
  if (!d) return '—'
  const date = typeof d === 'string' ? new Date(d) : d
  if (isNaN(date.getTime())) return '—'
  return date.toLocaleString(loc(), {
    year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
  })
}

/** «قبل ٣ أيّام» — للخطّ الزمنيّ وقوائم الرسائل. */
export function fmtRelative(d: string | Date | null | undefined): string {
  if (!d) return '—'
  const date = typeof d === 'string' ? new Date(d) : d
  if (isNaN(date.getTime())) return '—'
  const secs = Math.round((date.getTime() - Date.now()) / 1000)
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000], ['month', 2592000], ['day', 86400],
    ['hour', 3600], ['minute', 60], ['second', 1],
  ]
  const rtf = new Intl.RelativeTimeFormat(loc(), { numeric: 'auto' })
  for (const [unit, s] of units) {
    if (Math.abs(secs) >= s || unit === 'second') return rtf.format(Math.round(secs / s), unit)
  }
  return '—'
}

/** الحروفُ الأولى للصورة الرمزيّة. */
export function initials(name: string | null | undefined): string {
  const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '؟'
  if (parts.length === 1) return parts[0].slice(0, 2)
  return parts[0][0] + parts[1][0]
}

export type Totals = {
  subtotal: number
  discount: number
  taxable: number
  vat: number
  total: number
}

/**
 * حسابُ العرض — منقولٌ عن `osoul_quote_totals()` بترتيبه نفسِه.
 *
 * **والترتيبُ هو الحساب:** الخصمُ يُطرح أوّلًا، ثمّ تُحسب الضريبةُ على
 * الباقي. ولو حُسبت الضريبةُ على المجموع ثمّ طُرح الخصمُ لَزادت الضريبةُ
 * عمّا يستحقّه المشتري، وهذا خطأٌ في فاتورةٍ ضريبيّةٍ لا في شاشة.
 *
 *     الضريبةُ على ما يُدفَع، لا على ما كان قبل الخصم.
 *
 * والخصمُ يُقيَّد بين صفرٍ والمجموع — فخصمٌ أكبرُ من الفاتورة يجعل الإجماليَّ
 * سالبًا، وهذا لا معنى له.
 */
export function quoteTotals(
  items: { qty: number; unit_price: number }[],
  discount = 0,
  vatRate = VAT_RATE,
): Totals {
  let subtotal = 0
  for (const it of items) subtotal += Number(it.unit_price || 0) * Number(it.qty || 0)
  const d = Math.min(Math.max(0, Number(discount) || 0), subtotal)
  const taxable = subtotal - d
  const vat = Math.round(taxable * (vatRate / 100) * 100) / 100
  return {
    subtotal: Math.round(subtotal * 100) / 100,
    discount: Math.round(d * 100) / 100,
    taxable: Math.round(taxable * 100) / 100,
    vat,
    total: Math.round((taxable + vat) * 100) / 100,
  }
}
