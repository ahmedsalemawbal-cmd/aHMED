import { supabase } from './supabase'
import type {
  DocumentRow, Invoice, NoorTable, Plan, Profile, Subscriber, Template, TemplateFolder,
} from './types'

export async function fetchFolders(): Promise<TemplateFolder[]> {
  /* المجلّدات وعدّاداتها في طلبين متوازيين — والعدّاد من منظرٍ في القاعدة
     لا بجلب القوالب كلّها لتُعدَّ في المتصفّح. */
  const [f, c] = await Promise.all([
    supabase.from('template_folders').select('*').eq('is_active', true).order('sort').order('name'),
    supabase.from('template_folder_counts').select('folder_id,template_count'),
  ])
  if (f.error) throw new Error(f.error.message)
  const counts = new Map<string, number>(
    ((c.data || []) as { folder_id: string; template_count: number }[])
      .map((r) => [r.folder_id, Number(r.template_count) || 0]),
  )
  return ((f.data || []) as TemplateFolder[]).map((x) => ({ ...x, template_count: counts.get(x.id) ?? 0 }))
}

export async function fetchFolderBySlug(slug: string): Promise<TemplateFolder | null> {
  const { data, error } = await supabase.from('template_folders').select('*').eq('slug', slug).maybeSingle()
  if (error) throw new Error(error.message)
  return (data as TemplateFolder) || null
}

/**
 * أعمدةُ القائمة — بلا `content_html`.
 *
 * كانت `select('*')`، فتجلب صفحةُ المكتبة **ثلاثةَ عشرَ ميغابايتًا** من
 * المتون لترسم بطاقاتٍ ارتفاعُها مئةٌ واثنان وثلاثون بكسلًا. فيقف المشترك
 * أمام هياكل التحميل حتّى ينزل ما لا يُعرض منه واحدٌ بالمئة.
 *
 *     ما لا يُعرض لا يُجلَب.
 *
 * والمصغّرة تحتاج أوّل صفحةٍ فقط، والقاعدة تشتقّها في `thumb_html` —
 * سبعُ مئة كيلوبايتٍ بدل ثلاثةَ عشرَ ميغابايتًا. و`body_len` يُغني عن
 * المتن في معرفة أفارغٌ هو أم لا.
 *
 * وتُسمّى الأعمدة صراحةً لا بـ`*`: عمودٌ ثقيلٌ يُضاف غدًا يدخل القائمة
 * بلا أن ينتبه أحد — وهكذا دخل `content_html` نفسه.
 */
const LIST_COLS =
  'id,slug,title,category_key,description,outputs,estimated_minutes,version,' +
  'status,usage_count,is_new,sort,sort_school,sort_teacher,created_at,updated_at,' +
  'kind,folder_id,audience,role_keys,is_portfolio,page,source_pdf_path,source_pages,thumb_html,body_len'

export async function fetchTemplates(): Promise<Template[]> {
  const { data, error } = await supabase.from('templates').select(LIST_COLS)
    .eq('status', 'published').order('sort').order('title')
  if (error) throw new Error(error.message)
  return (data || []) as unknown as Template[]
}
export async function fetchTemplateBySlug(slug: string): Promise<Template | null> {
  const { data, error } = await supabase.from('templates').select('*').eq('slug', slug).maybeSingle()
  if (error) throw new Error(error.message)
  return (data as Template) || null
}
/**
 * ملفّات المشترك — بلا متونها.
 *
 * وهذا العطب نفسه الذي كان في القوالب، ينتظر في موضعٍ ثانٍ: أربعُ صفحاتٍ
 * تستدعي هذه الدالّة — «ملفّاتي» والرئيسة والفريق والاشتراك — ولا واحدةٌ
 * منها تعرض متنًا. تقرأ العنوان والحالة وصاحبَه وتاريخَه.
 *
 * والمستندات ستّةٌ اليوم فلا يُحسّ. لكنّها تكبر بعدد المعلّمين × عدد
 * ملفّاتهم، وأكبرُها نصفُ ميغابايت: خمسون معلّمًا بعشرين ملفًّا نصفُ
 * جيغابايت في كلّ فتحةٍ لصفحةٍ لا تعرض منها حرفًا.
 *
 *     العطبُ الذي عولج في موضعٍ يُبحث عنه في أمثاله.
 *
 * والمحرّر يجلب متنَ ملفٍّ واحدٍ حين يُفتح — وهذا موضعه.
 */
const DOC_COLS =
  'id,subscriber_id,owner_id,template_id,title,status,data,page,created_at,updated_at'

export async function fetchDocuments(subscriberId: string): Promise<DocumentRow[]> {
  const { data, error } = await supabase.from('documents').select(DOC_COLS)
    .eq('subscriber_id', subscriberId).order('updated_at', { ascending: false })
  if (error) throw new Error(error.message)
  return (data || []) as unknown as DocumentRow[]
}
export async function fetchNoorTables(subscriberId: string): Promise<NoorTable[]> {
  const { data, error } = await supabase.from('noor_tables').select('*').eq('subscriber_id', subscriberId).order('created_at', { ascending: false })
  if (error) throw new Error(error.message)
  return (data || []) as NoorTable[]
}
export async function fetchTeam(subscriberId: string): Promise<Profile[]> {
  const { data, error } = await supabase.from('profiles').select('*').eq('subscriber_id', subscriberId).order('is_owner', { ascending: false }).order('created_at')
  if (error) throw new Error(error.message)
  return (data || []) as Profile[]
}
export async function fetchInvoices(subscriberId: string): Promise<Invoice[]> {
  const { data, error } = await supabase.from('invoices').select('*').eq('subscriber_id', subscriberId).order('issued_at', { ascending: false })
  if (error) throw new Error(error.message)
  return (data || []) as Invoice[]
}
export async function aiUsageThisMonth(subscriberId: string): Promise<number> {
  const start = new Date(); start.setDate(1); start.setHours(0, 0, 0, 0)
  const { count, error } = await supabase.from('ai_usage').select('id', { count: 'exact', head: true })
    .eq('subscriber_id', subscriberId).gte('created_at', start.toISOString())
  if (error) return 0
  return count || 0
}
/** الفئات التي تفتحها باقة المشترك — الفارغة تعني كلّ الفئات. */
export function allowedCategories(plan: Plan | null): string[] | null {
  if (!plan) return null
  if (!plan.template_categories?.length) return null
  return plan.template_categories
}
export function templateLocked(tpl: Template, plan: Plan | null): boolean {
  const allowed = allowedCategories(plan)
  if (!allowed) return false
  return !allowed.includes(tpl.category_key)
}
/** ما يراه صاحب الدور: فئة دوره + العامّة. */
export function visibleForRole(tpl: Template, roleKey: string): boolean {
  return tpl.category_key === roleKey || tpl.category_key === 'general'
}
