import { supabase } from './supabase'
import { splitPages } from './pages'
import type { PortfolioItem, PortfolioKind, Template } from './types'

/**
 * سجلّ الإنجاز — التقاطُ الشواهد على مدار العام.
 *
 * الموظّف لا يعجز عن كتابة ملفّ إنجازه؛ يعجز في يونيو عن تذكّر ما فعله
 * في أكتوبر. فالقيمة هنا ليست في الذكاء الذي يُركّب آخرَ العام، بل في
 * أن يكون الالتقاط **أسرع من نسيانه**.
 *
 *     إن كان الالتقاط بطيئًا، ماتت الميزة كلُّها.
 *
 * ولذا لا شيء هنا إلزاميّ إلّا الملفّ أو النصّ: لا محور، ولا عنوان، ولا
 * تاريخ. ما لم يُملأ يُملأ بافتراضٍ معقول.
 */

/* ═══════════════ المحاور — من القالب لا من جدول إعدادات ═══════════════ */

/**
 * محاورُ ملفّ الإنجاز تُقرأ من عناوين قالب الدور نفسه.
 *
 * ولمَ لا جدولُ إعداداتٍ يُعرّفها؟ لأنّ لكلّ دورٍ محاورَه: ملفّ رائد
 * النشاط غيرُ ملفّ الموجّه الطلابيّ. فتعريفُها يدويًّا لتسعة أدوارٍ عملٌ
 * يُنسى تحديثُه متى بُدّل قالب — ثمّ يُصنّف الشاهد تحت محورٍ لم يعد
 * في الملفّ.
 *
 *     القالبُ هو الذي يُعرّف محاوره.
 *
 * وتُقرأ من العناوين: `h1..h4`، وما كان في التصميم عنوانًا بحجمٍ أكبر
 * ووزنٍ أثقل. ولا تُقرأ الصفحة الأولى: هي غلافٌ لا محاور فيه.
 */
export function axesOf(tpl: Pick<Template, 'content_html' | 'thumb_html'> | null): string[] {
  const html = tpl?.content_html || ''
  if (!html.trim()) return []

  const pages = (() => {
    try { return splitPages(html) } catch { return [html] }
  })()
  /* الغلافُ يُترك: عنوانُ الملفّ ليس محورًا فيه. وإن كانت صفحةً واحدةً
     فهي كلُّ ما لدينا، فتُقرأ. */
  const body = pages.length > 1 ? pages.slice(1) : pages

  const holder = document.createElement('div')
  holder.innerHTML = body.join('')

  const seen = new Set<string>()
  const out: string[] = []

  const add = (raw: string) => {
    const t = raw.replace(/\s+/g, ' ').trim()
    /* عنوانٌ من كلمةٍ واحدةٍ غالبًا وسمٌ لا محور، وما جاوز مئةً جملةٌ
       لا عنوان. والرقمُ وحده ترقيمُ صفحة. */
    if (t.length < 4 || t.length > 100) return
    if (/^[\d٠-٩\s.\-/]+$/.test(t)) return
    const key = t.replace(/[:：.]+$/, '')
    if (seen.has(key)) return
    seen.add(key)
    out.push(key)
  }

  holder.querySelectorAll('h1, h2, h3, h4').forEach((h) => add(h.textContent || ''))

  /* والتصميم المستورد لا يستعمل `h1..h4` غالبًا — يكتب العنوان `div`
     بوزنٍ ثقيلٍ وحجمٍ أكبر. فيُلتقط بما أعلنه في سمته السطريّة. */
  if (out.length < 3) {
    holder.querySelectorAll('[style]').forEach((el) => {
      const st = el.getAttribute('style') || ''
      const weight = Number((st.match(/font-weight:\s*(\d{3})/i) || [])[1] || 0)
      const size = parseFloat((st.match(/font-size:\s*([\d.]+)px/i) || [])[1] || '0')
      if (weight >= 700 && size >= 15 && !el.querySelector('table')) {
        add(el.textContent || '')
      }
    })
  }

  return out.slice(0, 24)
}

/* ═══════════════ السجلّ ═══════════════ */

export async function fetchPortfolio(ownerId: string, year: string): Promise<PortfolioItem[]> {
  let q = supabase.from('portfolio_items').select('*')
    .eq('owner_id', ownerId).order('happened_on', { ascending: false })
  if (year) q = q.eq('academic_year', year)
  const { data, error } = await q
  if (error) throw new Error(error.message)
  return (data || []) as PortfolioItem[]
}

/** شواهدُ المدرسة كلِّها — للمدير: مَن أنجز ومَن تأخّر. */
export async function fetchSchoolPortfolio(subscriberId: string, year: string): Promise<PortfolioItem[]> {
  let q = supabase.from('portfolio_items').select('*')
    .eq('subscriber_id', subscriberId).order('happened_on', { ascending: false })
  if (year) q = q.eq('academic_year', year)
  const { data, error } = await q
  if (error) throw new Error(error.message)
  return (data || []) as PortfolioItem[]
}

/**
 * يُصغّر الصورة قبل رفعها.
 *
 * صورةُ جوّالٍ حديثةٍ أربعةُ ميغابايت، والشاهد يُعرض في صفحة A4 بعرض
 * لا يتجاوز ألفًا ومئتي بكسل. فخمسون موظّفًا بثلاثين شاهدًا يبلغون
 * ستّة جيغابايتٍ في السنة، وتسعة أعشارها دقّةٌ لا تُرى ولا تُطبع.
 *
 * ويُحتفظ بالأصل إن كان أصغر ممّا سيخرج: إعادةُ الترميز ليست دائمًا ربحًا.
 */
export async function shrinkImage(file: File, maxSide = 1600, quality = 0.82): Promise<Blob> {
  if (!/^image\/(png|jpe?g|webp)$/i.test(file.type)) return file
  const url = URL.createObjectURL(file)
  try {
    const img = await new Promise<HTMLImageElement>((res, rej) => {
      const el = new Image()
      el.onload = () => res(el)
      el.onerror = () => rej(new Error('تعذّرت قراءة الصورة'))
      el.src = url
    })
    const side = Math.max(img.naturalWidth, img.naturalHeight)
    if (!side || side <= maxSide) return file

    const scale = maxSide / side
    const c = document.createElement('canvas')
    c.width = Math.round(img.naturalWidth * scale)
    c.height = Math.round(img.naturalHeight * scale)
    const ctx = c.getContext('2d')
    if (!ctx) return file
    ctx.drawImage(img, 0, 0, c.width, c.height)

    const out = await new Promise<Blob | null>((res) =>
      c.toBlob(res, 'image/jpeg', quality))
    return out && out.size < file.size ? out : file
  } catch {
    return file
  } finally {
    URL.revokeObjectURL(url)
  }
}

/** المسار: <المشترك>/<الموظّف>/<ملفّ> — والسياسة تقرأ المالك منه. */
function pathFor(subscriberId: string, ownerId: string, name: string): string {
  const ext = (name.match(/\.([a-z0-9]{1,5})$/i) || [])[1] || 'jpg'
  const stamp = Date.now().toString(36) + Math.random().toString(36).slice(2, 7)
  return `${subscriberId}/${ownerId}/${stamp}.${ext.toLowerCase()}`
}

export interface NewItem {
  kind: PortfolioKind
  title: string
  note: string
  axis: string
  happened_on: string
  file?: File | null
}

/**
 * يحفظ شاهدًا — ويرفع ملفّه إن كان له ملفّ.
 *
 * والرفع قبل الإدراج: لو أُدرج الصفُّ ثمّ سقط الرفع لبقي في السجلّ
 * شاهدٌ يشير إلى ملفٍّ لا وجود له — وذاك عطبٌ صامتٌ لا يُكتشف إلّا يوم
 * التركيب، حين لا وقت لإصلاحه.
 */
export async function addItem(
  subscriberId: string, ownerId: string, year: string, item: NewItem,
): Promise<PortfolioItem> {
  let file_path: string | null = null
  let file_mime: string | null = null
  let file_size: number | null = null

  if (item.file) {
    const blob = await shrinkImage(item.file)
    const path = pathFor(subscriberId, ownerId, item.file.name)
    const up = await supabase.storage.from('portfolio')
      .upload(path, blob, { contentType: blob.type || item.file.type, upsert: false })
    if (up.error) throw new Error('تعذّر رفع الملفّ: ' + up.error.message)
    file_path = path
    file_mime = blob.type || item.file.type
    file_size = blob.size
  }

  const { data, error } = await supabase.from('portfolio_items').insert({
    subscriber_id: subscriberId,
    owner_id: ownerId,
    academic_year: year,
    axis: item.axis.trim(),
    title: item.title.trim() || defaultTitle(item),
    note: item.note.trim(),
    kind: item.kind,
    file_path, file_mime, file_size,
    happened_on: item.happened_on,
  }).select('*').single()

  if (error) {
    /* الملفُّ رُفع والصفُّ لم يُدرج — يُحذف الملفّ فلا يبقى يتيمًا
       يُحسب على حصّة المشترك ولا يراه أحد. */
    if (file_path) await supabase.storage.from('portfolio').remove([file_path])
    throw new Error(error.message)
  }
  return data as PortfolioItem
}

function defaultTitle(i: NewItem): string {
  if (i.note.trim()) return i.note.trim().slice(0, 60)
  return { photo: 'صورة', file: 'مرفق', text: 'ملاحظة', certificate: 'شهادة' }[i.kind]
}

export async function removeItem(it: PortfolioItem): Promise<void> {
  const { error } = await supabase.from('portfolio_items').delete().eq('id', it.id)
  if (error) throw new Error(error.message)
  if (it.file_path) await supabase.storage.from('portfolio').remove([it.file_path])
}

/**
 * رابطٌ موقَّتٌ لعرض الشاهد.
 *
 * والدلو خاصٌّ لا عامّ: فيه صورُ طلّاب. فلا رابطَ دائمًا يُنسخ ويُرسل،
 * بل رابطٌ ينتهي بعد ساعة.
 */
export async function signedUrl(path: string, seconds = 3600): Promise<string> {
  const { data, error } = await supabase.storage.from('portfolio')
    .createSignedUrl(path, seconds)
  if (error || !data?.signedUrl) return ''
  return data.signedUrl
}

/* ═══════════════ الاكتمال ═══════════════ */

export interface Coverage {
  axes: { name: string; count: number }[]
  covered: number
  total: number
  untagged: number
  items: number
}

/**
 * ما أُنجز من المحاور وما بقي.
 *
 * وهذا أقوى محرّك استخدامٍ في الميزة كلّها: «٦ من ٩» يجعل الموظّف يفتح
 * التطبيق شهريًّا. وبدونه يفتحه مرّتين في السنة — مرّةً ليبدأ، ومرّةً
 * ليكتشف أنّه لم يبدأ.
 */
export function coverage(axes: string[], items: PortfolioItem[]): Coverage {
  const counts = new Map<string, number>(axes.map((a) => [a, 0]))
  let untagged = 0
  for (const it of items) {
    const a = (it.axis || '').trim()
    if (!a) { untagged++; continue }
    counts.set(a, (counts.get(a) ?? 0) + 1)
  }
  const list = axes.map((name) => ({ name, count: counts.get(name) ?? 0 }))
  return {
    axes: list,
    covered: list.filter((a) => a.count > 0).length,
    total: axes.length,
    untagged,
    items: items.length,
  }
}

/* ═══════════════ صورُ الإنجاز في المستندات ═══════════════ */

/**
 * الصورُ في ملفّ الإنجاز تُحفظ بمسارها الدائم لا برابطها الموقّع.
 *
 * ودلوُ الإنجاز خاصّ: لا رابطَ عامَّ له، والموقّعُ ينتهي بعد ساعة. فلو
 * حُفظ الموقّعُ في متن المستند لظهرت الصورُ يومَ التركيب، وانكسرت في
 * اليوم التالي — والموظّف لا يعرف لمَ، ويظنّ الملفَّ تلف.
 *
 *     ما ينتهي لا يُحفظ في متن.
 *
 * فيُحفظ `src="portfolio:المسار"`، ويُعاد توقيعُه عند كلّ فتحة. وهي
 * الصيغةُ نفسها التي يكتبها التطبيق (`saveDocument` في تطبيق الجوّال)،
 * فما رُكّب في الجوّال يُفتح في المتصفّح بصورٍ سليمة.
 */
export async function resignPortfolioImages(html: string): Promise<string> {
  const paths = [...html.matchAll(/src="portfolio:([^"]*)"/g)].map((m) => m[1])
  if (!paths.length) return html
  const { data } = await supabase.storage.from('portfolio')
    .createSignedUrls([...new Set(paths)], 3600)
  const urls: Record<string, string> = {}
  for (const r of data || []) if (r.path && r.signedUrl) urls[r.path] = r.signedUrl
  return html.replace(/src="portfolio:([^"]*)"/g, (m, p) => (urls[p] ? `src="${urls[p]}"` : m))
}

/**
 * والعكسُ قبل الحفظ: الروابطُ الموقّعة تعود مساراتٍ دائمة.
 *
 * وإلّا لأعاد المحرّرُ كتابةَ ما وقّعناه للعرض في القاعدة، فعاد العطبُ
 * من حيث أُغلق — وهذه المرّةَ من فعل الحفظ لا من فعل التركيب.
 */
export function unsignPortfolioImages(html: string): string {
  return html.replace(
    /src="[^"]*"(\s+data-mdd-portfolio="([^"]*)")/g,
    (_m, tail, path) => `src="portfolio:${path}"${tail}`,
  )
}
