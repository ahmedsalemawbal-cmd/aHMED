import { supabase } from './supabase'
import type { AttendanceRow, AttendanceStatus, ClassRow, Period, Student } from './types'

/** الأسبوع الدراسيّ السعوديّ: الأحد 0 … الخميس 4 */
export const WEEKDAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'] as const

export const STATUS_AR: Record<AttendanceStatus, string> = {
  present: 'حاضر', late: 'متأخّر', absent: 'غائب', excused: 'معذور',
}
export const STATUS_ORDER: AttendanceStatus[] = ['present', 'late', 'absent', 'excused']

/** يوم اليوم بترقيم الجدول — والجمعة/السبت تعود إلى الأحد */
export function todayWeekday(d = new Date()): number {
  const js = d.getDay() // 0 الأحد … 6 السبت
  return js <= 4 ? js : 0
}

export function isoDate(d = new Date()): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function hhmm(t: string): number {
  const [h, m] = String(t).split(':').map(Number)
  return (h || 0) * 60 + (m || 0)
}

export type PeriodState = 'done' | 'now' | 'next' | 'past' | 'upcoming'

/** حالة الحصّة في يومها: جارية · تالية · انتهت · رُصدت */
export function periodState(p: Period, taken: Set<string>, now = new Date()): PeriodState {
  if (taken.has(p.id)) return 'done'
  if (p.weekday !== todayWeekday(now)) return 'upcoming'
  const mins = now.getHours() * 60 + now.getMinutes()
  if (mins >= hhmm(p.starts_at) && mins <= hhmm(p.ends_at)) return 'now'
  if (mins < hhmm(p.starts_at)) return 'next'
  return 'past'
}

export async function fetchMyPeriods(subscriberId: string, teacherId: string): Promise<Period[]> {
  const { data, error } = await supabase
    .from('periods')
    .select('*, classes(*)')
    .eq('subscriber_id', subscriberId)
    .eq('teacher_id', teacherId)
    .order('weekday').order('slot')
  if (error) throw new Error(error.message)
  return (data || []) as Period[]
}

export async function fetchClasses(subscriberId: string): Promise<ClassRow[]> {
  const { data, error } = await supabase.from('classes').select('*')
    .eq('subscriber_id', subscriberId).order('sort')
  if (error) throw new Error(error.message)
  return (data || []) as ClassRow[]
}

export async function fetchStudents(classId: string): Promise<Student[]> {
  const { data, error } = await supabase.from('students').select('*')
    .eq('class_id', classId).eq('status', 'active').order('sort')
  if (error) throw new Error(error.message)
  return (data || []) as Student[]
}

export async function fetchAttendance(periodId: string, date: string): Promise<AttendanceRow[]> {
  const { data, error } = await supabase.from('attendance').select('*')
    .eq('period_id', periodId).eq('on_date', date)
  if (error) throw new Error(error.message)
  return (data || []) as AttendanceRow[]
}

/** أيّ حصص اليوم رُصدت — لعرض شارة «رُصدت» في الجدول */
export async function fetchTakenToday(subscriberId: string, date: string): Promise<Set<string>> {
  const { data } = await supabase.from('attendance').select('period_id')
    .eq('subscriber_id', subscriberId).eq('on_date', date)
  return new Set((data || []).map((r: any) => r.period_id))
}

export async function saveAttendance(rows: {
  subscriber_id: string; period_id: string; student_id: string
  taken_by: string; on_date: string; status: AttendanceStatus
}[]): Promise<void> {
  if (!rows.length) return
  // دفعاتٌ من 100 — الفصل قد يبلغ أربعين طالبًا وأكثر
  for (let i = 0; i < rows.length; i += 100) {
    const { error } = await supabase
      .from('attendance')
      .upsert(rows.slice(i, i + 100), { onConflict: 'period_id,student_id,on_date' })
    if (error) throw new Error(error.message)
  }
}

export function summarize(map: Record<string, AttendanceStatus>) {
  const out = { present: 0, late: 0, absent: 0, excused: 0 }
  for (const v of Object.values(map)) out[v]++
  return out
}
