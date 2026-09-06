import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * جلبٌ غيرُ متزامنٍ بحالاته الأربع.
 *
 * منقولةٌ من `midad/apps/web/src/lib/hooks.ts` — وفيها حارسٌ ضدَّ **السباق**:
 * لو بدّل المستخدمُ المرشّحَ مرّتين بسرعة، وصلت استجابةُ الأولى بعد الثانية
 * فعرضت نتيجةً قديمةً على مرشّحٍ جديد. فيُرقَّم كلُّ نداءٍ ويُهمَل ما ليس
 * الأخير.
 *
 *     آخرُ ما طُلب هو ما يُعرض، لا آخرُ ما وصل.
 */
export function useAsync<T>(fn: () => Promise<T>, deps: any[] = []) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const seq = useRef(0)

  const run = useCallback(() => {
    const my = ++seq.current
    setLoading(true)
    setError(null)
    fn().then(
      (r) => { if (my === seq.current) { setData(r); setLoading(false) } },
      (e) => { if (my === seq.current) { setError(e?.message || 'تعذّر الجلب'); setLoading(false) } },
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  useEffect(() => {
    run()
    return () => { seq.current++ }  // إلغاءُ ما لم يصل عند التفكيك
  }, [run])

  return { data, loading, error, reload: run, setData }
}

/** يؤخّر القيمةَ حتّى يهدأ الكاتب — للبحث الحيّ. */
export function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

/** حالةٌ محفوظةٌ في المتصفّح — للتفضيلات الصغيرة لا للبيانات. */
export function useLocalState<T>(key: string, initial: T) {
  const [v, setV] = useState<T>(() => {
    try {
      const raw = localStorage.getItem(key)
      return raw ? (JSON.parse(raw) as T) : initial
    } catch { return initial }
  })
  useEffect(() => {
    try { localStorage.setItem(key, JSON.stringify(v)) } catch { /* لا شيء */ }
  }, [key, v])
  return [v, setV] as const
}
