import { supabase } from './supabase'
import type { ClassRow, Period, Student } from './types'

export const WEEKDAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'] as const
export const STATUS_AR: Record<string, string> = {
  present: 'حاضر', late: 'متأخّر', absent: 'غائب', excused: 'معذور',
}
export const STUDENT_STATUS_AR: Record<string, string> = {
  active: 'منتظم', transferred: 'منقول', withdrawn: 'منسحب',
}

export async function fetchClasses(subscriberId: string): Promise<ClassRow[]> {
  const { data, error } = await supabase.from('classes').select('*')
    .eq('subscriber_id', subscriberId).order('sort').order('name')
  if (error) throw new Error(error.message)
  return (data || []) as ClassRow[]
}
export async function fetchStudents(subscriberId: string): Promise<Student[]> {
  const { data, error } = await supabase.from('students').select('*')
    .eq('subscriber_id', subscriberId).order('sort')
  if (error) throw new Error(error.message)
  return (data || []) as Student[]
}
export async function fetchPeriods(subscriberId: string): Promise<Period[]> {
  const { data, error } = await supabase.from('periods').select('*')
    .eq('subscriber_id', subscriberId).order('weekday').order('slot')
  if (error) throw new Error(error.message)
  return (data || []) as Period[]
}

/** أوقات الحصص المعتادة في المدرسة السعودية — تُقترَح ولا تُفرَض */
export const SLOT_TIMES: Record<number, [string, string]> = {
  1: ['07:00', '07:45'], 2: ['07:50', '08:35'], 3: ['08:40', '09:25'],
  4: ['09:45', '10:30'], 5: ['10:35', '11:20'], 6: ['11:25', '12:10'],
  7: ['12:40', '13:25'], 8: ['13:30', '14:15'],
}
