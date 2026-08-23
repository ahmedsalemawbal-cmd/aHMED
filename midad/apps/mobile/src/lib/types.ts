export type AccountType = 'school' | 'teacher'
export type FieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'radio' | 'table'

export interface TemplateColumn { key: string; label: string; type: 'text' | 'number' | 'date' | 'select'; options?: string[] }
export interface TemplateField {
  key: string; label: string; type: FieldType; required?: boolean; section?: string
  placeholder?: string; help?: string; options?: string[]; columns?: TemplateColumn[]
}
export interface Template {
  id: string; slug: string; title: string; category_key: string; description: string | null
  body: string; fields: TemplateField[]; outputs: string[]; estimated_minutes: number
  status: string; usage_count: number; is_new: boolean
}
export interface DocumentRow {
  id: string; subscriber_id: string; owner_id: string; template_id: string | null
  title: string; data: Record<string, any>; status: 'draft' | 'complete'
  created_at: string; updated_at: string
}
export interface NoorTable {
  id: string; subscriber_id: string; owner_id: string; title: string
  columns: string[]; rows: string[][]; row_count: number; created_at: string
}
export interface Subscriber {
  id: string; name: string; account_type: AccountType; city: string | null
  education_dept: string | null; academic_year: string | null; semester: string | null
  logo_url: string | null; status: 'trial' | 'active' | 'expired' | 'suspended'
  suspended_reason: string | null; trial_ends_at: string; plan_id: string | null
}
export interface Profile {
  id: string; subscriber_id: string | null; full_name: string; phone: string
  email: string | null; role_key: string; is_owner: boolean; status: 'active' | 'suspended'
}
export interface Plan {
  id: string; key: string; name_ar: string; account_type: AccountType; price_sar: number
  seats: number; template_categories: string[]; ai_quota_monthly: number; features_ar: string[]
}
export interface Role { key: string; name_ar: string; blurb_ar: string | null; sort: number }
