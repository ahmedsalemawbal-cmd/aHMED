export type AccountType = 'school' | 'teacher'
export type SubscriberStatus = 'trial' | 'active' | 'expired' | 'suspended'
export type MemberStatus = 'active' | 'suspended'
export type InvoiceStatus = 'unpaid' | 'under_review' | 'paid' | 'rejected' | 'cancelled'
export type DocumentStatus = 'draft' | 'complete'
export type TemplateStatus = 'draft' | 'published'
export type FieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'radio' | 'table'

export interface TemplateColumn { key: string; label: string; type: 'text' | 'number' | 'date' | 'select'; options?: string[] }
export interface TemplateField {
  key: string; label: string; type: FieldType; required?: boolean; section?: string
  placeholder?: string; help?: string; options?: string[]; columns?: TemplateColumn[]; default?: string
}

export interface Role { id: string; key: string; name_ar: string; blurb_ar: string | null; sort: number; is_system: boolean }
export interface Plan {
  id: string; key: string; name_ar: string; account_type: AccountType; price_sar: number
  /** السعر قبل الخصم — يُعرض مشطوبًا. فارغٌ يعني لا عرض. */
  price_before_sar?: number | null
  period_months: number; seats: number; template_categories: string[]; noor_enabled: boolean
  ai_quota_monthly: number; features_ar: string[]; is_active: boolean; is_default: boolean; sort: number
}
export interface Subscriber {
  id: string; name: string; account_type: AccountType; city: string | null; school_type: string | null
  education_dept: string | null; academic_year: string | null; semester: string | null
  principal_name: string | null; contact_phone: string | null; logo_url: string | null
  status: SubscriberStatus; suspended_reason: string | null; trial_ends_at: string
  plan_id: string | null; ai_quota_override: number | null; created_at: string
}
export interface Profile {
  id: string; subscriber_id: string | null; full_name: string; phone: string; email: string | null
  role_key: string; is_owner: boolean; status: MemberStatus; theme_pref: string
  email_notifications: boolean; last_login_at: string | null; created_at: string
}
export type TemplateKind = 'doc' | 'form'

export interface PageSetupRow {
  size: 'A4'
  orientation: 'portrait' | 'landscape'
  margins: { top: number; right: number; bottom: number; left: number }
}

/** لأيّ نوع اشتراكٍ يظهر المجلّد */
export type FolderAudience = 'school' | 'teacher'

/**
 * ولأيّ نوعٍ يظهر القالب — وله ثالثٌ ليس للمجلّد: «الكلّ».
 *
 * وكان الجمهور على المجلّد وحده، فيرثه كلّ ما فيه. فلم يكن للمالك سبيلٌ
 * إلى ملفٍّ واحدٍ يخالف مجلّده، ولا إلى ملفٍّ يراه الجميع. فانتقل إلى
 * الملفّ نفسه، وصار المجلّد للتنظيم لا للصلاحيّة.
 */
export type TemplateAudience = FolderAudience | 'all'

export interface TemplateFolder {
  id: string; slug: string; name: string; blurb: string | null
  accent: string; icon: string; sort: number; is_active: boolean
  created_at: string; updated_at: string
  /** المدرسة أو المعلّم — والسياسة تحرسه، لا الواجهة وحدها */
  audience: FolderAudience
  /** لمالك الحساب ومديره وحدهم — لا لمعلّمي المدرسة */
  owner_only: boolean
  /** يظهر ووسمُه «قريبًا»، فيعرف المشترك أنّه آتٍ */
  coming_soon: boolean
  /** المجلّد الذي يستقبل قوالب جمهوره تلقائيًّا — وفيه تظهر قوالب «الكلّ».
      ويُصرَّح به ولا يُستنتَج من نفي `owner_only`: يصحّ اليوم ويكذب غدًا. */
  is_general: boolean
  /** يأتي من المنظر template_folder_counts، لا من الجدول */
  template_count?: number
}

export interface Template {
  id: string; slug: string; title: string; category_key: string; description: string | null
  outputs: string[]; estimated_minutes: number
  version: number; status: TemplateStatus; usage_count: number; is_new: boolean; sort: number
  created_at: string; updated_at: string
  /* ← ٠٠٠٧: القالب صار مستندًا يُحرَّر */
  kind: TemplateKind
  folder_id: string | null
  /** مدرسةٌ، أو معلّمٌ، أو الكلّ — وهذا وحده يقرّر مَن يرى */
  audience: TemplateAudience
  /** ترتيبان لا ترتيب: قالب «الكلّ» يظهر في القائمتين، وأولويّة المدير
      غير أولويّة المعلّم — فسحبُه في إحداهما لا يحرّكه في الأخرى. */
  sort_school: number
  sort_teacher: number
  /** لأيّ أدوارٍ يظهر — والفارغُ لكلّ أدوار جمهوره.
      وهذا بُعدٌ ثانٍ فوق `audience`: «للمدرسة» يقول أيَّ حسابٍ، و«لرائد
      النشاط» يقول أيَّ موظّفٍ داخله. فملفّ إنجاز الوكيل لا يظهر للمعلّم. */
  role_keys?: string[]
  /** أوّلُ صفحةٍ وحدها — تشتقّها القاعدة، وعليها تُرسم مصغّرة البطاقة.
      و`content_html` لا يُجلب في القوائم: ثلاثةَ عشرَ ميغابايتًا لا
      يُعرض منها واحدٌ بالمئة. */
  thumb_html?: string
  /** طولُ المتن — يُغني عن جلبه لمعرفة أفارغٌ هو أم لا */
  body_len?: number
  content_html?: string
  page: PageSetupRow
  source_pdf_path: string | null
  source_pages: number | null
  /** مهجورَان — كانا متن {{الحقول}} وتعريفها. لا يُبنى عليهما. */
  body?: string
  fields?: TemplateField[]
}

export interface DocumentRow {
  id: string; subscriber_id: string; owner_id: string; template_id: string | null
  title: string; status: DocumentStatus; created_at: string; updated_at: string
  /* ← ٠٠٠٧ */
  content_html?: string
  page: PageSetupRow | null
  /** مهجور — كان قيم الحقول */
  data?: Record<string, any>
}
/** بوّابة الوزارة التي سُحب منها الجدول. */
export type TableSource = 'noor' | 'madrasati'

export interface NoorTable {
  id: string; subscriber_id: string; owner_id: string; title: string
  columns: string[]; rows: string[][]; row_count: number; source_url: string | null; created_at: string
  /** القديم كلّه من نور — لم تكن مدرستي مدعومةً حين حُفظ. */
  source?: TableSource
}
export interface LinkKey {
  id: string; subscriber_id: string; user_id: string; key: string
  created_at: string; expires_at: string; last_used_at: string | null; revoked_at: string | null
}
export interface Subscription {
  id: string; subscriber_id: string; plan_id: string; status: 'active' | 'expired' | 'cancelled'
  starts_at: string; ends_at: string; amount_sar: number; created_at: string; cancelled_at: string | null
}
export interface Invoice {
  id: string; number: string; subscriber_id: string; subscription_id: string | null; plan_id: string | null
  description_ar: string | null; amount_sar: number; tax_rate: number; tax_amount: number; total_sar: number
  status: InvoiceStatus; issued_at: string; paid_at: string | null; submitted_at: string | null
  receipt_url: string | null; internal_note: string | null; rejected_reason: string | null; created_at: string
}
export interface AuditEntry {
  id: string; subscriber_id: string | null; actor_id: string | null; actor_name: string | null
  event_type: string; message_ar: string; meta: Record<string, any>; created_at: string
}
export interface PublicPaymentSettings {
  payments_enabled: boolean; beneficiary: string; bank: string; iban: string
  tax_rate: number; tax_number: string; show_tax: boolean
}
export interface GeneralSettings {
  platform_name: string; tagline: string; whatsapp: string; email: string; working_hours: string
}
export type Database = any

/* ---------- الإدارة الصفّية ---------- */
export type AttendanceStatus = 'present' | 'late' | 'absent' | 'excused'
export type StudentStatus = 'active' | 'transferred' | 'withdrawn'

export interface ClassRow {
  id: string; subscriber_id: string; name: string
  stage: string | null; room: string | null; sort: number; created_at: string
}
export interface Student {
  id: string; subscriber_id: string; class_id: string | null
  full_name: string; national_id: string | null; guardian_phone: string | null
  status: StudentStatus; sort: number; created_at: string
}
export interface Period {
  id: string; subscriber_id: string; teacher_id: string | null; class_id: string | null
  subject: string; weekday: number; slot: number
  starts_at: string; ends_at: string; room: string | null; created_at: string
}
export interface AttendanceRow {
  id: string; subscriber_id: string; period_id: string; student_id: string
  taken_by: string | null; on_date: string; status: AttendanceStatus; note: string | null
}

/**
 * شاهدٌ في سجلّ الإنجاز.
 *
 * الموظّف يلتقطه لحظةَ وقوعه — صورةً من الكاميرا أو مرفقًا أو نصًّا —
 * فلا يبحث عنه في يونيو بين ثلاثة آلاف صورةٍ في جوّاله.
 *
 *     مِدادٌ لا يكتب الملفّ؛ يمنع الضياع.
 *
 * والمحور اختياريّ: الإلزام يُبطئ الالتقاط فيموت السجلّ من أصله،
 * وتركُه بلا سؤالٍ يجعل التوزيع تخمينًا. فيُسأل ويجوز تخطّيه.
 */
export type PortfolioKind = 'photo' | 'file' | 'text' | 'certificate'

export interface PortfolioItem {
  id: string
  subscriber_id: string
  owner_id: string
  academic_year: string
  /** من عناوين قالب الدور — أو فراغٌ إن تخطّاه صاحبُه */
  axis: string
  title: string
  note: string
  kind: PortfolioKind
  file_path: string | null
  file_mime: string | null
  file_size: number | null
  /** يومُ وقوع الحدث لا يومُ رفعه — والملفّ يُرتَّب به */
  happened_on: string
  created_at: string
  updated_at: string
}
