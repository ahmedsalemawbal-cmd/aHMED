import * as ImagePicker from 'expo-image-picker'
import { Alert } from 'react-native'
import { decode } from 'base64-arraybuffer'
import { supabase, callFunction } from './supabase'

/**
 * سجلّ الإنجاز في الجوّال — الالتقاطُ حيث يقع الحدث.
 *
 * الموظّف لا يعجز عن كتابة ملفّ إنجازه؛ يعجز في يونيو عن تذكّر ما فعله
 * في أكتوبر. والفعاليةُ تقع في الساحة لا أمام الحاسب — فالجوّال هو
 * الموضع الوحيد الذي يُلتقط فيه الشاهد **لحظةَ وقوعه**.
 *
 *     إن كان الالتقاط بطيئًا، ماتت الميزة كلُّها.
 *
 * ولذا: صورةٌ من الكاميرا في نقرتين، ولا حقلَ إلزاميّ إلّا الصورةُ أو
 * النصّ. ما لم يُملأ يُملأ بافتراضٍ معقول، والذكاءُ يُرتّب آخرَ العام.
 */

export type PortfolioKind = 'photo' | 'certificate' | 'file' | 'text'

export interface PortfolioItem {
  id: string
  subscriber_id: string
  owner_id: string
  academic_year: string
  axis: string
  title: string
  note: string
  kind: PortfolioKind
  file_path: string | null
  file_mime: string | null
  file_size: number | null
  happened_on: string
  created_at: string
}

export const KIND_AR: Record<PortfolioKind, string> = {
  photo: 'صورة', certificate: 'شهادة', file: 'مرفق', text: 'ملاحظة',
}

/* ═════════════════════════ القراءة ═════════════════════════ */

export async function fetchItems(ownerId: string, year: string): Promise<PortfolioItem[]> {
  let q = supabase.from('portfolio_items')
    .select('id,subscriber_id,owner_id,academic_year,axis,title,note,kind,file_path,file_mime,file_size,happened_on,created_at')
    .eq('owner_id', ownerId)
    .order('happened_on', { ascending: false })
    .order('created_at', { ascending: false })
  if (year) q = q.eq('academic_year', year)
  const { data, error } = await q
  if (error) throw new Error(error.message)
  return (data || []) as PortfolioItem[]
}

/**
 * روابطٌ موقّعةٌ للعرض — والدلوُ خاصّ، فلا رابطَ عامّ له.
 *
 * وتُطلب دفعةً واحدة: طلبٌ لكلّ صورةٍ يجعل فتحَ الشاشة عشرين رحلة.
 */
export async function signedUrls(paths: string[], seconds = 3600): Promise<Record<string, string>> {
  const clean = paths.filter(Boolean)
  if (!clean.length) return {}
  const { data, error } = await supabase.storage.from('portfolio').createSignedUrls(clean, seconds)
  if (error) return {}
  const out: Record<string, string> = {}
  for (const r of data || []) if (r.path && r.signedUrl) out[r.path] = r.signedUrl
  return out
}

/* ═════════════════════════ الإضافة ═════════════════════════ */

async function ensure(source: 'camera' | 'library'): Promise<boolean> {
  const p = source === 'camera'
    ? await ImagePicker.requestCameraPermissionsAsync()
    : await ImagePicker.requestMediaLibraryPermissionsAsync()
  if (p.granted) return true
  Alert.alert(
    source === 'camera' ? 'الكاميرا مغلقة' : 'الصور مغلقة',
    'افتح إعدادات الجهاز وامنح مِداد الإذن، ثمّ عُد.',
  )
  return false
}

export interface Picked { base64: string; width: number; height: number }

/**
 * التقاطُ صورة — والجودة ٠٫٧ لا ١٫٠ عمدًا.
 *
 * صورةُ الجوّال اليوم أربعةُ ميغابايت، والشاهدُ يُعرض في مربّعٍ صغيرٍ
 * ويُطبع في ثُمن صفحة. فالميغاباياتُ الأربعة تُنفَق في رفعٍ بطيءٍ على
 * شبكة مدرسةٍ ضعيفة، ثمّ في دلوٍ يمتلئ، ثمّ في ملفٍّ لا يُفتح.
 *
 *     ما لا يُرى لا يُرفع.
 */
export async function pick(source: 'camera' | 'library'): Promise<Picked | null> {
  if (!(await ensure(source))) return null
  const opts: ImagePicker.ImagePickerOptions = {
    mediaTypes: ImagePicker.MediaTypeOptions.Images,
    quality: 0.7,
    base64: true,
    /* ولا قصَّ إلزاميّ: شاهدُ الفعاليّة عريضٌ أو طوليّ، وإجبارُه على
       مربّعٍ يقصّ نصفَ ما التُقط لأجله. */
    allowsEditing: false,
  }
  const res = source === 'camera'
    ? await ImagePicker.launchCameraAsync(opts)
    : await ImagePicker.launchImageLibraryAsync(opts)
  if (res.canceled || !res.assets?.length) return null
  const a = res.assets[0]
  if (!a.base64) throw new Error('تعذّرت قراءة الصورة')
  return { base64: a.base64, width: a.width || 0, height: a.height || 0 }
}

export interface NewItem {
  kind: PortfolioKind
  title: string
  note: string
  axis: string
  happened_on: string
  photo?: Picked | null
}

/**
 * الرفعُ قبل الإدراج، والحذفُ إن فشل الإدراج.
 *
 * ولو عُكس الترتيب لبقي في السجلّ صفٌّ يشير إلى ملفٍّ لم يُرفع — فيظهر
 * للموظّف شاهدٌ صورتُه مكسورة، ولا يعرف أرفعها ثانيةً أم يحذفه. والعكسُ
 * أهون: ملفٌّ يتيمٌ في الدلو لا يراه أحد.
 *
 *     الصفُّ الذي يعِد بملفٍّ لا وجود له أسوأ من ملفٍّ لا صفَّ له.
 */
export async function addItem(
  subscriberId: string, ownerId: string, year: string, it: NewItem,
): Promise<void> {
  let path: string | null = null
  let mime: string | null = null
  let size: number | null = null

  if (it.photo) {
    const bytes = decode(it.photo.base64)
    /* المسار: <المشترك>/<الموظّف>/<ملفّ> — بهذا الترتيب لا بغيره.
       سياسةُ الدلو تقرأ الجزء الأوّل مشتركًا والثاني مالكًا، وأيُّ ترتيبٍ
       آخر يُرفض قبل أن يصل بايتٌ واحد. وهو ما وقعتُ فيه أوّلَ مرّة:
       كتبتُ المالكَ أوّلًا وعلّقتُ بأنّ ذلك ما تشترطه السياسة — ولم أقرأها.

           السياسةُ تُقرأ، لا تُتذكَّر.

       والاسمُ بصمةٌ لا عامٌ دراسيّ: العام «1447 هـ» فيه مسافةٌ وحرفٌ
       عربيّ، والعامُ محفوظٌ في الصفّ فلا حاجة إليه في المفتاح. */
    const stamp = Date.now().toString(36) + Math.random().toString(36).slice(2, 7)
    path = `${subscriberId}/${ownerId}/${stamp}.jpg`
    mime = 'image/jpeg'
    size = bytes.byteLength
    const { error: upErr } = await supabase.storage
      .from('portfolio').upload(path, bytes, { contentType: mime, upsert: false })
    if (upErr) throw new Error('تعذّر رفع الصورة — تحقّق من اتّصالك')
  }

  const { error } = await supabase.from('portfolio_items').insert({
    subscriber_id: subscriberId,
    owner_id: ownerId,
    academic_year: year,
    axis: it.axis.trim(),
    title: it.title.trim(),
    note: it.note.trim(),
    kind: it.kind,
    file_path: path,
    file_mime: mime,
    file_size: size,
    happened_on: it.happened_on,
  })

  if (error) {
    if (path) await supabase.storage.from('portfolio').remove([path]).catch(() => {})
    throw new Error(error.message)
  }
}

export async function removeItem(it: PortfolioItem): Promise<void> {
  const { error } = await supabase.from('portfolio_items').delete().eq('id', it.id)
  if (error) throw new Error(error.message)
  /* والملفُّ بعد الصفّ: لو حُذف أوّلًا وفشل حذفُ الصفّ لبقي صفٌّ يعِد
     بملفٍّ محذوف — وهو العطبُ نفسه مقلوبًا. */
  if (it.file_path) await supabase.storage.from('portfolio').remove([it.file_path]).catch(() => {})
}

/* ═════════════════════════ التركيب ═════════════════════════ */

export interface Plan {
  intro: string
  conclusion: string
  sections: { axis: string; summary: string; item_ids: string[] }[]
  axes: string[]
  counted: number
  placed: number
  used: number
  limit: number
}

export async function compose(templateId: string, year: string): Promise<Plan> {
  return callFunction<Plan>('portfolio-compose', { template_id: templateId, year })
}

const esc = (s: string) => String(s == null ? '' : s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')

const AR_DATE = (iso: string) => {
  try {
    return new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-latn',
      { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso))
  } catch { return iso }
}

/**
 * التركيب — حسابيٌّ لا احتماليّ.
 *
 * الذكاء ردّ خطّةً: لكلّ محورٍ فقرةٌ ومعرّفاتُ شواهده. وهنا تُبنى الصفحات
 * منها بلا اجتهاد: نفس الترتيب، نفس الأنماط، نفس الصناديق. فلو رُكّبت
 * الخطّةُ نفسها مرّتين خرجت الصفحتان متطابقتين حرفًا بحرف.
 *
 * ولمَ صناديقُ `data-page`؟ لأنّها ما يفهمه محرّرُ مِداد ومُصدِّرُ الـPDF
 * على السواء: صندوقٌ = صفحةٌ. فالملفّ يخرج مُصفّحًا لا كتلةً واحدة.
 *
 * والصورةُ برابطٍ موقّت — ولذلك تُستبدل عند الحفظ بمسارٍ دائم (انظر
 * `PORTFOLIO_IMG_ATTR`): الرابطُ الموقّع ينتهي بعد ساعة، ولو حُفظ في متن
 * المستند لظهرت الصور اليوم وانكسرت غدًا.
 */
export const PORTFOLIO_IMG_ATTR = 'data-mdd-portfolio'

export function assemble(
  plan: Plan,
  items: PortfolioItem[],
  urls: Record<string, string>,
  meta: { title: string; owner: string; role: string; school: string; year: string },
): string {
  const byId = new Map(items.map((i) => [i.id, i]))
  const pages: string[] = []

  const page = (inner: string) =>
    `<div data-page="true" style="padding:56px 48px">${inner}</div>`

  /* الغلاف */
  pages.push(page([
    `<h1 style="text-align:center;font-size:26pt;margin:120px 0 8px">${esc(meta.title)}</h1>`,
    `<p style="text-align:center;font-size:14pt;color:#555;margin:0 0 64px">العام الدراسيّ ${esc(meta.year)}</p>`,
    `<p style="text-align:center;font-size:13pt;margin:0 0 4px"><strong>${esc(meta.owner)}</strong></p>`,
    meta.role ? `<p style="text-align:center;font-size:12pt;color:#555;margin:0 0 4px">${esc(meta.role)}</p>` : '',
    meta.school ? `<p style="text-align:center;font-size:12pt;color:#555;margin:0">${esc(meta.school)}</p>` : '',
  ].join('')))

  /* المقدّمة */
  if (plan.intro) {
    pages.push(page([
      '<h2 style="font-size:16pt;margin:0 0 16px">مقدّمة</h2>',
      `<p style="font-size:12pt;line-height:2;text-align:justify">${esc(plan.intro)}</p>`,
    ].join('')))
  }

  /* المحاور — كلُّ محورٍ صفحةٌ فأكثر */
  for (const sec of plan.sections) {
    const its = sec.item_ids.map((id) => byId.get(id)).filter(Boolean) as PortfolioItem[]
    const photos = its.filter((i) => i.file_path && urls[i.file_path!])
    const texts = its.filter((i) => !i.file_path)

    const head = [
      `<h2 style="font-size:16pt;margin:0 0 14px">${esc(sec.axis)}</h2>`,
      sec.summary
        ? `<p style="font-size:12pt;line-height:2;text-align:justify;margin:0 0 18px">${esc(sec.summary)}</p>`
        : '',
    ].join('')

    /* الملاحظاتُ قائمةً — ولكلٍّ تاريخُه، فهو ما يُثبت أنّه شاهدُ عام. */
    const list = texts.length
      ? `<ul style="font-size:11.5pt;line-height:1.9;padding-inline-start:22px;margin:0 0 18px">${
        texts.map((i) => `<li>${esc(i.title || i.note)}${
          i.title && i.note ? ` — ${esc(i.note)}` : ''
        } <span style="color:#777">(${esc(AR_DATE(i.happened_on))})</span></li>`).join('')
      }</ul>`
      : ''

    /* الصورُ شبكةً من عمودين — والتعليقُ تحت كلٍّ لا بجانبها. */
    const grid = photos.length
      ? `<table style="width:100%;border-collapse:separate;border-spacing:10px 14px;border:0"><tbody>${
        Array.from({ length: Math.ceil(photos.length / 2) }, (_, r) => {
          const pair = photos.slice(r * 2, r * 2 + 2)
          const cells = pair.map((i) => [
            '<td style="width:50%;vertical-align:top;border:0;padding:0">',
            `<img src="${esc(urls[i.file_path!])}" ${PORTFOLIO_IMG_ATTR}="${esc(i.file_path!)}"`,
            ' style="width:100%;height:auto;border:1px solid #ddd;border-radius:6px">',
            `<div style="font-size:10pt;color:#444;margin-top:5px;text-align:center">${
              esc(i.title || KIND_AR[i.kind])
            } <span style="color:#888">— ${esc(AR_DATE(i.happened_on))}</span></div>`,
            '</td>',
          ].join(''))
          /* خليّةٌ فارغةٌ تُكمل الصفّ الفرد، وإلّا تمدّدت الصورةُ الوحيدة
             على عرض الصفحة كلِّه. */
          if (pair.length === 1) cells.push('<td style="width:50%;border:0"></td>')
          return `<tr>${cells.join('')}</tr>`
        }).join('')
      }</tbody></table>`
      : ''

    pages.push(page(head + list + grid))
  }

  /* الخاتمة */
  if (plan.conclusion) {
    pages.push(page([
      '<h2 style="font-size:16pt;margin:0 0 16px">خاتمة</h2>',
      `<p style="font-size:12pt;line-height:2;text-align:justify">${esc(plan.conclusion)}</p>`,
      '<div class="mdd-sign-row" style="margin-top:70px">',
      `<div>${esc(meta.owner)}</div><div>مدير المدرسة</div>`,
      '</div>',
    ].join('')))
  }

  return pages.join('')
}

/**
 * ينشئ المستند ويردّ معرّفه — ليُفتح في المحرّر.
 *
 * والروابطُ الموقّعة تُنزع من المتن قبل الحفظ: تنتهي بعد ساعة، ولو
 * حُفظت لظهرت الصورُ اليومَ وانكسرت غدًا — وذلك عطبٌ لا يُلاحَظ إلّا بعد
 * فوات أوانه. فيُحفظ المسارُ الدائم في السمة، ويُعيد العارضُ توقيعَه
 * عند كلّ فتحة.
 */
export async function saveDocument(
  subscriberId: string, ownerId: string, templateId: string, title: string, html: string,
): Promise<string> {
  const stored = html.replace(
    new RegExp(`src="[^"]*"(\\s+${PORTFOLIO_IMG_ATTR}="([^"]*)")`, 'g'),
    (_m, tail, path) => `src="portfolio:${path}"${tail}`,
  )
  const { data, error } = await supabase.from('documents').insert({
    subscriber_id: subscriberId,
    owner_id: ownerId,
    template_id: templateId,
    title,
    content_html: stored,
    status: 'draft',
  }).select('id').single()
  if (error || !data) throw new Error(error?.message || 'تعذّر حفظ الملفّ')
  return (data as any).id
}

/** ويُعاد التوقيع عند العرض: `portfolio:المسار` ← رابطٌ صالحٌ ساعة. */
export async function resign(html: string): Promise<string> {
  const paths = [...html.matchAll(/src="portfolio:([^"]*)"/g)].map((m) => m[1])
  if (!paths.length) return html
  const urls = await signedUrls([...new Set(paths)])
  return html.replace(/src="portfolio:([^"]*)"/g,
    (m, p) => (urls[p] ? `src="${urls[p]}"` : m))
}
