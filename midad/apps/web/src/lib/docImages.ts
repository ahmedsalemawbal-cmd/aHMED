import { supabase } from './supabase'

/**
 * رفع صورةٍ إلى مستند — شعار المدرسة وما يُدرجه المعلّم.
 *
 * المسار `{subscriber_id}/{وقت}-{عشوائيّ}.{امتداد}`. والجزء الأوّل ليس
 * تنظيمًا فحسب: سياسة التخزين تقارنه بمشترك الرافع، فلا يكتب أحدٌ في
 * مجلّد غيره. والاسم عشوائيّ لا اسم الملفّ الأصليّ — فالاسم الأصليّ قد
 * يحمل عربيّةً ومسافاتٍ ومحارف تكسر الروابط، وقد يفضح ما لا يُراد.
 */

const BUCKET = 'doc-images'
const MAX_BYTES = 5 * 1024 * 1024

const TYPES: Record<string, string> = {
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/webp': 'webp',
  'image/gif': 'gif',
  'image/svg+xml': 'svg',
}

export function imageError(file: File): string | null {
  if (!TYPES[file.type]) return 'الصيغة غير مدعومة. استعمل PNG أو JPG أو WEBP أو SVG.'
  if (file.size > MAX_BYTES) {
    return `الصورة أكبر من ٥ ميغابايت (${(file.size / 1048576).toFixed(1)}). اضغطها ثمّ أعِد المحاولة.`
  }
  return null
}

export async function uploadDocImage(file: File, subscriberId: string): Promise<string> {
  const bad = imageError(file)
  if (bad) throw new Error(bad)
  if (!subscriberId) throw new Error('لا مشترك — سجّل الدخول ثمّ أعِد المحاولة.')

  const ext = TYPES[file.type]
  const name = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}.${ext}`
  const path = `${subscriberId}/${name}`

  const { error } = await supabase.storage.from(BUCKET).upload(path, file, {
    contentType: file.type,
    cacheControl: '31536000',   // الاسم عشوائيّ فلا يحمل محتوًى مختلفًا أبدًا
    upsert: false,
  })
  if (error) throw new Error(`تعذّر رفع الصورة: ${error.message}`)

  const { data } = supabase.storage.from(BUCKET).getPublicUrl(path)
  if (!data?.publicUrl) throw new Error('رُفعت الصورة ولم نحصل على رابطها.')
  return data.publicUrl
}

/**
 * يفتح منتقي الملفّات ويردّ ما اختير — أو null إن أُلغي.
 *
 * ولمَ عنصرٌ يُصنع ويُطرح في كلّ مرّة؟ لأنّ `<input type=file>` يحتفظ
 * بآخر اختيار، فاختيارُ الملفّ نفسه مرّتين لا يُطلق `change` في الثانية،
 * فيظنّ المستخدم أنّ الرفع تعطّل.
 */
export function pickImage(): Promise<File | null> {
  return new Promise((resolve) => {
    const el = document.createElement('input')
    el.type = 'file'
    el.accept = Object.keys(TYPES).join(',')
    el.style.display = 'none'
    let done = false
    const finish = (f: File | null) => {
      if (done) return
      done = true
      el.remove()
      resolve(f)
    }
    el.addEventListener('change', () => finish(el.files?.[0] ?? null))
    /* الإلغاء لا يُطلق `change` في كلّ المتصفّحات، و`cancel` حديثٌ لا
       يعرفه القديم منها. فنستعمله إن وُجد، ونتّكل على أنّ الوعد المهمَل
       لا يضرّ إن لم يوجد. */
    el.addEventListener('cancel', () => finish(null))
    document.body.appendChild(el)
    el.click()
  })
}
