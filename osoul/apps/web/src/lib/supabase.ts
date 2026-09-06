import { createClient } from '@supabase/supabase-js'
import { SUPABASE_URL, SUPABASE_KEY } from './config'

/**
 * عميلُ سوبابيز.
 *
 * و`storageKey` مسمّىً باسم المشروع — ولو تُرك للافتراضيّ لَتصادم مع أيّ
 * تطبيقٍ آخرَ على النطاق نفسه، فيخرج المستخدمُ من هذا حين يدخل ذاك.
 */
export const supabase = createClient(SUPABASE_URL, SUPABASE_KEY, {
  auth: {
    storageKey: 'osoul.auth',
    autoRefreshToken: true,
    persistSession: true,
    detectSessionInUrl: true, // مطلوبٌ لعودة تسجيل الدخول بجوجل
  },
})

/**
 * نداءُ دالّةِ حافّة.
 *
 * ولمَ `fetch` يدويٌّ لا `supabase.functions.invoke`؟ لأنّ `invoke` تبتلع
 * متنَ الخطأ وتردّ رسالةً عامّة، فتضيع الرسالةُ العربيّةُ التي كتبتها
 * الدالّةُ لتدلَّ المستخدمَ على الإصلاح. وهنا يُقرأ المتنُ ويُرمى ما فيه.
 *
 *     رسالةٌ تدلّ على الإصلاح خيرٌ من رسالةٍ تدلّ على العطب.
 */
export async function callFunction<T = any>(name: string, body?: unknown): Promise<T> {
  const { data: { session } } = await supabase.auth.getSession()
  const res = await fetch(`${SUPABASE_URL}/functions/v1/${name}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      apikey: SUPABASE_KEY,
      ...(session?.access_token ? { Authorization: `Bearer ${session.access_token}` } : {}),
    },
    body: JSON.stringify(body ?? {}),
  })
  let json: any = null
  try { json = await res.json() } catch { /* ردٌّ بلا متنٍ صالح */ }
  if (!res.ok) throw new Error(json?.error || `تعذّر الاتّصال (${res.status})`)
  return json as T
}
