import { useCallback, useEffect, useState } from 'react'
import type { CartLine } from './types'

/**
 * ══ سلّةُ طلب التسعير ══
 *
 * تعيش في المتصفّح حتّى تُرسَل — لأنّ الزائرَ يجمع حاجتَه على مهلٍ وهو
 * يتصفّح، ولا يُطلب منه حسابٌ ليضع بابًا في قائمة.
 *
 * والحدثُ `osoul:cart` ليس زينة: الشارةُ في الرأس والصفحةُ التي فيها
 * الزرُّ مكوّنان منفصلان لا أبَ مشتركًا بينهما إلّا الجذر. فرفعُ الحالة
 * إلى الجذر يُعيد رسمَ الموقع كلِّه عند كلّ إضافة، والحدثُ يُنبّه من
 * يعنيه وحده.
 *
 *     ما يُشترك فيه اثنان بعيدان يُبلَّغ لا يُرفَع.
 */
const KEY = 'osoul.cart'
const EVT = 'osoul:cart'

export function readCart(): CartLine[] {
  try {
    const raw = localStorage.getItem(KEY)
    const v = raw ? JSON.parse(raw) : []
    return Array.isArray(v) ? v : []
  } catch { return [] }
}

function writeCart(lines: CartLine[]) {
  try { localStorage.setItem(KEY, JSON.stringify(lines)) } catch { /* لا شيء */ }
  window.dispatchEvent(new CustomEvent(EVT))
}

export function addToCart(line: Omit<CartLine, 'qty'>, qty = 1) {
  const lines = readCart()
  const i = lines.findIndex((l) => l.slug === line.slug)
  if (i >= 0) lines[i].qty += qty
  else lines.push({ ...line, qty })
  writeCart(lines)
}

export function setQty(slug: string, qty: number) {
  const lines = readCart()
  const i = lines.findIndex((l) => l.slug === slug)
  if (i < 0) return
  if (qty <= 0) lines.splice(i, 1)
  else lines[i].qty = qty
  writeCart(lines)
}

export function removeFromCart(slug: string) { setQty(slug, 0) }
export function clearCart() { writeCart([]) }

/** يتابع السلّةَ ويُعيد الرسمَ عند تبدّلها — في هذا التبويب وفي غيره. */
export function useCart() {
  const [lines, setLines] = useState<CartLine[]>([])

  useEffect(() => {
    const sync = () => setLines(readCart())
    sync()
    window.addEventListener(EVT, sync)
    // و`storage` يُطلق في **التبويبات الأخرى** لا في هذا — فلو فتح
    // الزائرُ صفحتين وأضاف في إحداهما، تبِعتها الأخرى.
    window.addEventListener('storage', sync)
    return () => { window.removeEventListener(EVT, sync); window.removeEventListener('storage', sync) }
  }, [])

  const count = lines.reduce((s, l) => s + l.qty, 0)
  return {
    lines, count,
    add: useCallback((l: Omit<CartLine, 'qty'>, q?: number) => addToCart(l, q), []),
    setQty: useCallback(setQty, []),
    remove: useCallback(removeFromCart, []),
    clear: useCallback(clearCart, []),
  }
}
