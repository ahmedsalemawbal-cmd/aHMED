import { useEffect, useMemo, useState } from 'react'
import AsyncStorage from '@react-native-async-storage/async-storage'
import { supabase } from './supabase'
import { useApp } from './store'
import { isoDate, todayWeekday } from './classroom'

export type NoteTone = 'info' | 'warn' | 'danger' | 'success'
export interface Note {
  id: string
  title: string
  body: string
  tone: NoteTone
  /** الوجهة عند النقر — اسم شاشةٍ في التنقّل */
  go?: string
}

const SEEN_KEY = 'midad.notes.seen'

/**
 * التنبيهات تُشتقّ من حال الحساب لا من جدولٍ مستقلّ:
 * فما لا يقابله واقعٌ في البيانات لا يُعرض، وما زال قائمًا يبقى معروضًا.
 */
export function useNotifications() {
  const { access, trialDays, subscriber, profile, plan } = useApp()
  const [pending, setPending] = useState<{ invoices: number; unmarked: number }>({ invoices: 0, unmarked: 0 })
  const [seen, setSeen] = useState<string[]>([])

  useEffect(() => {
    AsyncStorage.getItem(SEEN_KEY).then((v) => { if (v) try { setSeen(JSON.parse(v)) } catch {} })
  }, [])

  useEffect(() => {
    let alive = true
    ;(async () => {
      try {
        const today = isoDate()
        const [inv, per] = await Promise.all([
          supabase.from('invoices').select('id', { count: 'exact', head: true }).eq('status', 'pending'),
          supabase.from('periods').select('id')
            .eq('weekday', todayWeekday()).eq('teacher_id', profile?.id ?? ''),
        ])
        if (!alive) return
        const periodIds = (per.data as { id: string }[] | null)?.map((p) => p.id) ?? []
        let unmarked = periodIds.length
        if (periodIds.length) {
          const { data: marked } = await supabase
            .from('attendance').select('period_id').eq('on_date', today).in('period_id', periodIds)
          const done = new Set((marked ?? []).map((m: { period_id: string }) => m.period_id))
          unmarked = periodIds.filter((id) => !done.has(id)).length
        }
        setPending({ invoices: inv.count ?? 0, unmarked })
      } catch { /* بلا شبكةٍ لا تنبيهات — ولا خطأ يُزعج */ }
    })()
    return () => { alive = false }
  }, [profile?.id, subscriber?.id])

  const notes = useMemo<Note[]>(() => {
    const out: Note[] = []
    if (access === 'trial' && trialDays > 0) {
      out.push({
        id: `trial-${trialDays}`,
        title: trialDays <= 3 ? 'تجربتك تُشارف على الانتهاء' : 'أنت في الفترة التجريبيّة',
        body: `بقي ${trialDays} ${trialDays === 1 ? 'يوم' : trialDays === 2 ? 'يومان' : trialDays <= 10 ? 'أيّام' : 'يومًا'}${plan ? ` — ثمّ تُفعَّل باقة ${plan.name_ar}` : ''}.`,
        tone: trialDays <= 3 ? 'warn' : 'info',
        go: 'حسابي',
      })
    }
    if (access === 'expired') {
      out.push({ id: 'expired', title: 'انتهى الاشتراك', body: 'يمكنك القراءة والتصدير، ولا يُحفظ تعديل حتّى التجديد.', tone: 'danger', go: 'حسابي' })
    }
    if (pending.unmarked > 0) {
      out.push({
        id: `unmarked-${pending.unmarked}`,
        title: 'حصصٌ لم تُرصد اليوم',
        body: `${pending.unmarked} ${pending.unmarked === 1 ? 'حصّة تنتظر' : 'حصص تنتظر'} رصد الحضور.`,
        tone: 'warn',
        go: 'الرصد',
      })
    }
    if (pending.invoices > 0) {
      out.push({
        id: `inv-${pending.invoices}`,
        title: 'فاتورةٌ بانتظار السداد',
        body: `لديك ${pending.invoices} ${pending.invoices === 1 ? 'فاتورة' : 'فواتير'} غير مسدّدة.`,
        tone: 'warn',
        go: 'حسابي',
      })
    }
    return out
  }, [access, trialDays, plan, pending])

  const unread = notes.filter((n) => !seen.includes(n.id)).length

  const markAllSeen = () => {
    const ids = notes.map((n) => n.id)
    setSeen(ids)
    AsyncStorage.setItem(SEEN_KEY, JSON.stringify(ids)).catch(() => {})
  }

  return { notes, unread, markAllSeen }
}
