import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { FlatList, Pressable, ScrollView, View } from 'react-native'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import * as Print from 'expo-print'
import * as Sharing from 'expo-sharing'
import { useApp } from '../lib/store'
import {
  STATUS_AR, STATUS_ORDER, fetchAttendance, fetchMyPeriods, fetchStudents,
  isoDate, periodState, saveAttendance, summarize, todayWeekday, WEEKDAYS,
} from '../lib/classroom'
import type { AttendanceStatus, Period, Student } from '../lib/types'
import { Alert, Badge, Button, Card, Empty, ErrorView, Loading, Row, T } from '../ui/kit'
import { IcCheck, IcClock, IcAlert, IcPrint, IcTable } from '../ui/icons'
import { RADIUS, SPACE } from '../lib/theme'
import { fmtHijri } from '../lib/format'

const TONE: Record<AttendanceStatus, 'success' | 'warn' | 'danger' | 'info'> = {
  present: 'success', late: 'warn', absent: 'danger', excused: 'info',
}

export default function Attendance() {
  const { c, subscriber, profile } = useApp()
  const nav = useNavigation<any>()
  const route = useRoute<any>()

  const [periods, setPeriods] = useState<Period[]>([])
  const [activeId, setActiveId] = useState<string | null>(route.params?.periodId ?? null)
  const [students, setStudents] = useState<Student[]>([])
  const [marks, setMarks] = useState<Record<string, AttendanceStatus>>({})
  const [saved, setSaved] = useState<Record<string, AttendanceStatus>>({})
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const date = isoDate()

  const loadPeriods = useCallback(async () => {
    if (!subscriber?.id || !profile?.id) return
    try {
      const all = await fetchMyPeriods(subscriber.id, profile.id)
      const today = all.filter((p) => p.weekday === todayWeekday())
      setPeriods(today)
      setActiveId((cur) => cur && today.some((p) => p.id === cur) ? cur : (today[0]?.id ?? null))
    } catch (e: any) { setErr(e?.message || 'تعذّر تحميل الجدول') }
    finally { setLoading(false) }
  }, [subscriber?.id, profile?.id])

  useFocusEffect(useCallback(() => { loadPeriods() }, [loadPeriods]))

  const active = useMemo(() => periods.find((p) => p.id === activeId) || null, [periods, activeId])

  useEffect(() => {
    let alive = true
    ;(async () => {
      if (!active?.class_id) { setStudents([]); setMarks({}); setSaved({}); return }
      try {
        const [st, att] = await Promise.all([
          fetchStudents(active.class_id),
          fetchAttendance(active.id, date),
        ])
        if (!alive) return
        setStudents(st)
        const m: Record<string, AttendanceStatus> = {}
        for (const a of att) m[a.student_id] = a.status
        setMarks(m); setSaved(m)
      } catch (e: any) { if (alive) setErr(e?.message || 'تعذّر تحميل الطلاب') }
    })()
    return () => { alive = false }
  }, [active?.id, active?.class_id, date])

  const dirty = useMemo(() => {
    const ks = new Set([...Object.keys(marks), ...Object.keys(saved)])
    for (const k of ks) if (marks[k] !== saved[k]) return true
    return false
  }, [marks, saved])

  const counts = useMemo(() => summarize(marks), [marks])

  const markAll = () => {
    const m: Record<string, AttendanceStatus> = {}
    for (const s of students) m[s.id] = 'present'
    setMarks(m)
  }

  const save = async () => {
    if (!active || !subscriber || !profile) return
    setBusy(true); setErr(null)
    try {
      await saveAttendance(students.map((s) => ({
        subscriber_id: subscriber.id, period_id: active.id, student_id: s.id,
        taken_by: profile.id, on_date: date, status: marks[s.id] ?? 'present',
      })))
      const next: Record<string, AttendanceStatus> = {}
      for (const s of students) next[s.id] = marks[s.id] ?? 'present'
      setMarks(next); setSaved(next)
    } catch (e: any) { setErr(e?.message || 'تعذّر حفظ الرصد') }
    finally { setBusy(false) }
  }

  const exportSheet = async () => {
    if (!active) return
    const rows = students.map((s, i) => `<tr><td>${i + 1}</td><td>${s.full_name}</td><td>${STATUS_AR[marks[s.id] ?? 'present']}</td></tr>`).join('')
    const html = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>@page{size:A4;margin:15mm}body{font-family:-apple-system,"Segoe UI",sans-serif;font-size:11pt;line-height:1.8}
h1{font-size:15pt;text-align:center;margin:0 0 6mm}
.h{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:4mm;margin-bottom:6mm;font-size:10pt}
table{width:100%;border-collapse:collapse;font-size:10pt}th,td{border:1px solid #999;padding:2.5mm;text-align:start}
th{background:#f1f1f1}.s{display:flex;gap:8mm;margin-top:5mm;font-size:10pt}</style></head><body>
<div class="h"><div><strong>${subscriber?.name || ''}</strong><br>${subscriber?.education_dept || ''}</div>
<div>${WEEKDAYS[active.weekday] || ''} · ${fmtHijri(new Date())}<br>الحصّة ${active.slot} · ${active.starts_at?.slice(0,5)}</div></div>
<h1>كشف حضور — ${active.classes?.name || ''} · ${active.subject}</h1>
<table><thead><tr><th style="width:10%">م</th><th>اسم الطالب</th><th style="width:22%">الحالة</th></tr></thead><tbody>${rows}</tbody></table>
<div class="s"><span>حاضر: ${counts.present}</span><span>متأخّر: ${counts.late}</span><span>غائب: ${counts.absent}</span><span>معذور: ${counts.excused}</span></div>
<p style="margin-top:12mm">المعلّم: ${profile?.full_name || ''} &nbsp;&nbsp; التوقيع: ................</p>
</body></html>`
    try {
      const { uri } = await Print.printToFileAsync({ html })
      if (await Sharing.isAvailableAsync()) await Sharing.shareAsync(uri, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf' })
      else await Print.printAsync({ uri })
    } catch (e: any) { setErr(e?.message || 'تعذّر توليد الكشف') }
  }

  if (loading) return <Loading label="جارٍ تحميل حصص اليوم…" />

  if (!periods.length) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, padding: SPACE.s4 }}>
        <Card>
          <Empty
            art={<IcTable size={30} color={c.text3} />}
            title="لا حصص لك اليوم"
            line={`${WEEKDAYS[todayWeekday()]} — لا حصص في جدولك. افتح «جدولي» لترى أسبوعك كاملًا.`}
            action={<Button label="افتح جدولي" variant="primary" onPress={() => nav.navigate('Timetable')} />}
          />
        </Card>
      </View>
    )
  }

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      {/* شريط الحصص */}
      <View style={{ paddingTop: SPACE.s3, paddingBottom: SPACE.s2, backgroundColor: c.card, borderBottomWidth: 1, borderBottomColor: c.border }}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ flexDirection: 'row-reverse', gap: 8, paddingHorizontal: SPACE.s4 }}>
          {periods.map((p) => {
            const on = p.id === activeId
            return (
              <Pressable key={p.id} onPress={() => setActiveId(p.id)}
                style={{
                  backgroundColor: on ? c.primary : c.sunken,
                  borderColor: on ? c.primary : c.border, borderWidth: 1,
                  borderRadius: RADIUS.md, paddingHorizontal: 14, paddingVertical: 9, minWidth: 96,
                }}>
                <T size={13} weight="700" color={on ? c.onPrimary : c.text} align="center">
                  {p.classes?.name || '—'}
                </T>
                <T size={10.5} color={on ? c.onPrimary : c.text3} align="center">
                  الحصّة {p.slot} · {p.starts_at?.slice(0, 5)}
                </T>
              </Pressable>
            )
          })}
        </ScrollView>
      </View>

      {active && (
        <View style={{ paddingHorizontal: SPACE.s4, paddingTop: SPACE.s3, gap: SPACE.s2 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T size={12.5} color={c.text2}>
              {WEEKDAYS[active.weekday]} · {active.subject} · {active.room || ''}
            </T>
            <Badge label={dirty ? 'لم يُحفَظ' : Object.keys(saved).length ? 'محفوظ' : 'لم يُرصد بعد'}
              tone={dirty ? 'warn' : Object.keys(saved).length ? 'success' : 'neutral'} />
          </Row>
          <Row gap={8} style={{ flexWrap: 'wrap' }}>
            <T size={12} color={c.text3}>{students.length} طالبًا</T>
            {STATUS_ORDER.map((s) => counts[s] > 0 && (
              <Badge key={s} label={`${STATUS_AR[s]} ${counts[s]}`} tone={TONE[s]} />
            ))}
          </Row>
        </View>
      )}

      {err ? <View style={{ padding: SPACE.s4 }}><Alert tone="danger">{err}</Alert></View> : null}

      <FlatList
        data={students}
        keyExtractor={(s) => s.id}
        contentContainerStyle={{ padding: SPACE.s4, gap: SPACE.s2, paddingBottom: 120 }}
        ListEmptyComponent={<Card><Empty title="لا طلاب في هذا الفصل" line="أضف طلاب الفصل من الموقع: الفصول والطلاب." /></Card>}
        renderItem={({ item, index }) => {
          const cur = marks[item.id] ?? 'present'
          return (
            <Card style={{ padding: SPACE.s3, gap: 10 }}>
              <Row gap={10}>
                <View style={{
                  width: 26, height: 26, borderRadius: 8, backgroundColor: c.sunken,
                  alignItems: 'center', justifyContent: 'center',
                }}>
                  <T size={11} weight="600" color={c.text3}>{index + 1}</T>
                </View>
                <T size={14} weight="600" style={{ flex: 1 }} numberOfLines={1}>{item.full_name}</T>
              </Row>
              <Row gap={6} style={{ flexWrap: 'wrap' }}>
                {STATUS_ORDER.map((s) => {
                  const on = cur === s
                  const tone = TONE[s]
                  const bg = on
                    ? (tone === 'success' ? c.successSoft : tone === 'warn' ? c.warnSoft : tone === 'danger' ? c.dangerSoft : c.infoSoft)
                    : 'transparent'
                  const fg = on
                    ? (tone === 'success' ? c.success : tone === 'warn' ? c.warn : tone === 'danger' ? c.danger : c.info)
                    : c.text3
                  return (
                    <Pressable key={s} onPress={() => setMarks((m) => ({ ...m, [item.id]: s }))}
                      style={{
                        flex: 1, minWidth: 68, paddingVertical: 9, borderRadius: RADIUS.sm,
                        borderWidth: 1, borderColor: on ? fg : c.border, backgroundColor: bg,
                        alignItems: 'center',
                      }}>
                      <T size={12} weight={on ? '700' : '400'} color={fg}>{STATUS_AR[s]}</T>
                    </Pressable>
                  )
                })}
              </Row>
            </Card>
          )
        }}
      />

      <View style={{
        position: 'absolute', bottom: 0, left: 0, right: 0, flexDirection: 'row-reverse',
        gap: 10, padding: SPACE.s4, backgroundColor: c.card,
        borderTopWidth: 1, borderTopColor: c.border,
      }}>
        <Button label="علِّم الكلّ حاضرًا" variant="secondary" style={{ flex: 1 }}
          icon={<IcCheck size={15} color={c.text} />} onPress={markAll} disabled={!students.length} />
        <Button label="ولّد كشفًا" variant="soft" style={{ flex: 0.8 }}
          icon={<IcPrint size={15} color={c.primarySoftFg} />} onPress={exportSheet} disabled={!students.length} />
        <Button label="احفظ الرصد" variant="primary" style={{ flex: 1 }}
          loading={busy} disabled={!students.length} onPress={save} />
      </View>
    </View>
  )
}
