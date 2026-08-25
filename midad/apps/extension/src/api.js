/**
 * الاتّصال بمِداد.
 *
 * لا تُخزَّن هنا كلمة مرورٍ ولا رمزُ دخول: المفتاح وحده، وهو يُنشأ من
 * المنصّة ويُلغى منها، وله أجلٌ ينتهي. فإن سُرِق الجهاز أُلغي المفتاح ولم
 * يُمسّ الحساب.
 *
 * والتخزين في `storage.local` لا `sync`: الأخير يُزامن المفتاح إلى كلّ
 * جهازٍ يدخل بحساب كروم نفسه — وهذا انتشارٌ لا يطلبه المعلّم.
 */

export const API = 'https://ehimyixcqnmnwgbqrdmr.supabase.co/functions/v1/noor'

const KEY_FIELD = 'midad_key'

export async function getKey() {
  const o = await chrome.storage.local.get(KEY_FIELD)
  return String(o?.[KEY_FIELD] || '')
}

export async function setKey(key) {
  if (!key) return chrome.storage.local.remove(KEY_FIELD)
  return chrome.storage.local.set({ [KEY_FIELD]: String(key).trim() })
}

/**
 * نداءٌ إلى الدالّة.
 *
 * ورسائل الخطأ تُعرض كما تأتي من الخادم: هو الذي يعرف أنّ الاشتراك انتهى
 * أو أنّ المفتاح أُلغي، ونحن لا نُخمّن. و`BADKEY` علامةٌ داخليّة تُسقط
 * المفتاح المحفوظ فيعود المعلّم إلى شاشة الربط بدل أن يُعاود الفشل.
 */
async function call(action, body = {}, key) {
  const k = key ?? await getKey()
  let res
  try {
    res = await fetch(API, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-midad-key': k },
      body: JSON.stringify({ action, ...body }),
    })
  } catch {
    throw new Error('تعذّر الوصول إلى مِداد — تحقّق من اتّصالك بالإنترنت.')
  }

  let data = null
  try { data = await res.json() } catch { /* قد يردّ الخادم نصًّا عند عطبٍ عميق */ }

  if (!res.ok) {
    const msg = data?.error || data?.message || `تعذّر الاتّصال (${res.status})`
    const err = new Error(msg)
    if (res.status === 401) err.code = 'BADKEY'
    throw err
  }
  return data || {}
}

export function verifyKey(key) {
  return call('verify_key', {}, key)
}

export function sendTable(payload) {
  return call('ingest', payload)
}
