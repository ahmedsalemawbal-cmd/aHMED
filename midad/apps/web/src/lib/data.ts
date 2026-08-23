import { supabase } from './supabase'
import type { DocumentRow, Invoice, NoorTable, Plan, Profile, Subscriber, Template } from './types'

export async function fetchTemplates(): Promise<Template[]> {
  const { data, error } = await supabase.from('templates').select('*').eq('status', 'published').order('sort').order('title')
  if (error) throw new Error(error.message)
  return (data || []) as Template[]
}
export async function fetchTemplateBySlug(slug: string): Promise<Template | null> {
  const { data, error } = await supabase.from('templates').select('*').eq('slug', slug).maybeSingle()
  if (error) throw new Error(error.message)
  return (data as Template) || null
}
export async function fetchDocuments(subscriberId: string): Promise<DocumentRow[]> {
  const { data, error } = await supabase.from('documents').select('*').eq('subscriber_id', subscriberId).order('updated_at', { ascending: false })
  if (error) throw new Error(error.message)
  return (data || []) as DocumentRow[]
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
