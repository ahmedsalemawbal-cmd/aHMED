import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { FlatList, Modal, Pressable, View } from 'react-native'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { useApp } from '../lib/store'
import {
  STATUS_AR, STATUS_ORDER, fetchAttendance, fetchMyPeriods, fetchStudents,
  isoDate, saveAttendance, summarize, todayWeekday, WEEKDAYS,
} from '../lib/classroom'
import { exportSheet, FORMAT_AR, ExportFormat } from '../lib/exportAttendance'
import type { AttendanceStatus, Period, Student } from '../lib/types'
import { AppHeader } from '../ui/AppHeader'
import { Alert, Button, Card, Empty, HScroll, Loading, Row, T } from '../ui/kit'
import {
  IcCheck, IcClock, IcClose, IcAlert, IcTable, IcDownload, IcPdf, IcWord, IcExcel,
} from '../ui/icons'
import { RADIUS, SPACE, TYPE, elevation } from '../lib/theme'
import { fmtHijri } from '../lib/format'

/** لكلّ حالةٍ لونها وأيقونتها — فالمعلّم يميّزها بلمحة، لا بالقراءة */
const LOOK: Record<AttendanceStatus, { key: 'success' | 'warn' | 'danger' | 'info'; Icon: any }> = {
  present: { key: 'success', Icon: IcCheck },
  late: { key: 'warn', Icon: IcClock },
  absent: { key: 'danger', Icon: IcClose },
  excused: { key: 'info', Icon: IcAlert },
}

export default function Attendance() {
  const { c, subscriber, profile } = useApp()
  const nav = useNavigation<any>()
  const route = useRoute<any>()
  const insets = useSafeAreaInsets()

  const [periods, setPeriods] = useState<Period[]>([])
  const [activeId, setActiveId] = useState<string | null>(route.params?.periodId ?? null)
  const [students, setStudents] = useState<Student[]>([])
  const [marks, setMarks] = useState<Record<string, AttendanceStatus>>({})
  const [saved, setSaved] = useState<Record<string, AttendanceStatus>>({})
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [exporting, setExporting] = useState<ExportFormat | null>(null)
  const [pickFormat, setPickFormat] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const date = isoDate()

  const loadPeriods = useCallback(async () => {
    if (!subscriber?.id || !profile?.id) { setLoading(false); return }
    try {
      const all = await fetchMyPeriods(subscriber.id, profile.id)
      const today = all.filter((p) => p.weekday === todayWeekday())
      setPeriods(today)
      setActiveId((cur) => (cur && today.some((p) => p.id === cur) ? cur : today[0]?.id ?? null))
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

  const doExport = async (format: ExportFormat) => {
    if (!active) return
    setPickFormat(false); setExporting(format); setErr(null)
    try {
      await exportSheet(format, {
        className: active.classes?.name || 'الفصل',
        subject: active.subject,
        periodLabel: `الحصّة ${active.slot} · ${active.starts_at?.slice(0, 5) || ''}`,
        date, dayName: WEEKDAYS[active.weekday] || '',
        teacher: profile?.full_name || '',
        school: subscriber?.name || null,
      }, students.map((s, i) => ({
        n: i + 1, name: s.full_name, status: marks[s.id] ?? 'present',
      })))
    } catch (e: any) { setErr(e?.message || 'تعذّر توليد الكشف') }
    finally { setExporting(null) }
  }

  if (loading) return <Loading label="جارٍ تحميل حصص اليوم…" />

  if (!periods.length) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg }}>
        <AppHeader title="رصد الحضور" subtitle={`${WEEKDAYS[todayWeekday()]} · ${fmtHijri(new Date())}`} />
        <View style={{ padding: SPACE.s5 }}>
          <Card>
            <Empty
              art={<IcTable size={30} color={c.text3} />}
              title="لا حصص لك اليوم"
              line={`${WEEKDAYS[todayWeekday()]} — لا حصص في جدولك. افتح «جدولي» لترى أسبوعك كاملًا.`}
              action={<Button label="افتح جدولي" variant="primary" onPress={() => nav.navigate('Timetable')} />}
            />
          </Card>
        </View>
      </View>
    )
  }

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <AppHeader title="رصد الحضور" subtitle={`${WEEKDAYS[todayWeekday()]} · ${fmtHijri(new Date())}`} />

      {/* شريط حصص اليوم */}
      <View style={{ paddingBottom: SPACE.s3 }}>
        <HScroll gap={8}>
          {periods.map((p) => {
            const on = p.id === activeId
            return (
              <Pressable
                key={p.id} onPress={() => setActiveId(p.id)}
                style={({ pressed }) => ({
                  backgroundColor: on ? c.primary : c.card,
                  borderRadius: RADIUS.md, paddingHorizontal: 16, paddingVertical: 12,
                  minWidth: 108, opacity: pressed ? 0.8 : 1,
                  ...elevation(c, on ? 2 : 1),
                })}>
                <T size={TYPE.body} weight="700" color={on ? c.onPrimary : c.text} align="center" numberOfLines={1}>
                  {p.classes?.name || '—'}
                </T>
                <T size={TYPE.micro} color={on ? c.onPrimary : c.text3} align="center" numberOfLines={1}>
                  الحصّة {p.slot} · {p.starts_at?.slice(0, 5)}
                </T>
              </Pressable>
            )
          })}
        </HScroll>
      </View>

      {/* شريط الحصيلة */}
      {active && (
        <View style={{ paddingHorizontal: SPACE.s5, paddingBottom: SPACE.s3, gap: 8 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T size={TYPE.body} color={c.text2} numberOfLines={1} style={{ flex: 1 }}>
              {active.subject}{active.room ? ` · ${active.room}` : ''}
            </T>
            <Row gap={5}>
              <View style={{
                width: 7, height: 7, borderRadius: 4,
                backgroundColor: dirty ? c.warn : Object.keys(saved).length ? c.success : c.text3,
              }} />
              <T size={TYPE.small} weight="600" color={dirty ? c.warn : Object.keys(saved).length ? c.success : c.text3}>
                {dirty ? 'لم يُحفَظ' : Object.keys(saved).length ? 'محفوظ' : 'لم يُرصد'}
              </T>
            </Row>
          </Row>
          <Row gap={7}>
            {STATUS_ORDER.map((s) => {
              const n = counts[s]
              const fg = c[LOOK[s].key]
              const bg = c[`${LOOK[s].key}Soft` as 'successSoft']
              return (
                <View key={s} style={{
                  flex: 1, backgroundColor: n > 0 ? bg : c.sunken, borderRadius: RADIUS.sm,
                  paddingVertical: 8, alignItems: 'center', gap: 1,
                }}>
                  <T size={TYPE.h3} weight="700" color={n > 0 ? fg : c.text3}>{n}</T>
                  <T size={TYPE.micro} weight="600" color={n > 0 ? fg : c.text3}>{STATUS_AR[s]}</T>
                </View>
              )
            })}
          </Row>
        </View>
      )}

      {err ? <View style={{ paddingHorizontal: SPACE.s5, paddingBottom: SPACE.s3 }}><Alert tone="danger">{err}</Alert></View> : null}

      <FlatList
        data={students}
        keyExtractor={(s) => s.id}
        contentContainerStyle={{
          paddingHorizontal: SPACE.s5, gap: 8, paddingBottom: 40 + insets.bottom,
        }}
        showsVerticalScrollIndicator={false}
        ListEmptyComponent={
          <Card>
            <Empty title="لا طلاب في هذا الفصل"
              line="أضف طلاب الفصل من الموقع: الفصول والطلاب — ثمّ عُد إلى هنا." />
          </Card>
        }
        renderItem={({ item, index }) => (
          <StudentRow
            index={index} name={item.full_name} value={marks[item.id] ?? 'present'}
            onChange={(s) => setMarks((m) => ({ ...m, [item.id]: s }))}
          />
        )}
      />

      {/* شريط الإجراءات */}
      <View style={{
        position: 'absolute', bottom: 0, left: 0, right: 0,
        flexDirection: 'row-reverse', gap: 8,
        paddingHorizontal: SPACE.s5, paddingTop: SPACE.s4,
        paddingBottom: insets.bottom + SPACE.s4,
        backgroundColor: c.card, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl,
        ...elevation(c, 3),
      }}>
        {/* الصفّ مقلوب، فأوّلُ ما يُكتب هنا آخرُ ما يُرى يمينًا — وموضع
            اليمين هو موضع العين الأولى في العربيّة، فيسكنه الفعل الرئيسيّ. */}
        <Button label="احفظ الرصد" variant="primary" style={{ flex: 1 }}
          loading={busy} disabled={!students.length || !dirty} onPress={save} />
        <Button variant="soft" icon={<IcDownload size={18} color={c.primarySoftFg} />}
          onPress={() => setPickFormat(true)} disabled={!students.length}
          loading={!!exporting} />
        <Button variant="secondary" icon={<IcCheck size={18} color={c.text} />}
          onPress={markAll} disabled={!students.length} />
      </View>

      <FormatSheet open={pickFormat} onClose={() => setPickFormat(false)} onPick={doExport} />
    </View>
  )
}

/* ───────── صفّ طالب ───────── */

const StudentRow = React.memo(function StudentRow({ index, name, value, onChange }: {
  index: number; name: string; value: AttendanceStatus; onChange: (s: AttendanceStatus) => void
}) {
  const { c } = useApp()
  return (
    <View style={{
      backgroundColor: c.card, borderRadius: RADIUS.lg, padding: 12, gap: 8, ...elevation(c, 1),
    }}>
      <Row gap={9}>
        <View style={{
          width: 25, height: 25, borderRadius: 10, backgroundColor: c.sunken,
          alignItems: 'center', justifyContent: 'center',
        }}>
          <T size={TYPE.micro} weight="700" color={c.text3}>{index + 1}</T>
        </View>
        <T size={TYPE.base} weight="600" style={{ flex: 1 }} numberOfLines={1}>{name}</T>
      </Row>
      <Row gap={6}>
        {STATUS_ORDER.map((s) => {
          const on = value === s
          const fg = c[LOOK[s].key]
          const bg = c[`${LOOK[s].key}Soft` as 'successSoft']
          const Icon = LOOK[s].Icon
          return (
            <Pressable
              key={s} onPress={() => onChange(s)}
              style={({ pressed }) => ({
                flex: 1, paddingVertical: 8, borderRadius: RADIUS.sm,
                backgroundColor: on ? bg : c.sunken,
                alignItems: 'center', justifyContent: 'center',
                flexDirection: 'row-reverse', gap: 4,
                opacity: pressed ? 0.7 : 1,
              })}>
              <Icon size={14} color={on ? fg : c.text3} />
              <T size={TYPE.small} weight={on ? '700' : '500'} color={on ? fg : c.text3}>
                {STATUS_AR[s]}
              </T>
            </Pressable>
          )
        })}
      </Row>
    </View>
  )
})

/* ───────── اختيار الصيغة ───────── */

function FormatSheet({ open, onClose, onPick }: {
  open: boolean; onClose: () => void; onPick: (f: ExportFormat) => void
}) {
  const { c } = useApp()
  const insets = useSafeAreaInsets()
  const opts: { f: ExportFormat; Icon: any; tint: string }[] = [
    { f: 'pdf', Icon: IcPdf, tint: c.danger },
    { f: 'docx', Icon: IcWord, tint: c.info },
    { f: 'xlsx', Icon: IcExcel, tint: c.success },
  ]
  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{ flex: 1, backgroundColor: '#0006' }} onPress={onClose} />
      <View style={{
        position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: c.card,
        borderTopLeftRadius: RADIUS.xxl, borderTopRightRadius: RADIUS.xxl,
        paddingTop: SPACE.s4, paddingBottom: insets.bottom + SPACE.s5,
        paddingHorizontal: SPACE.s5, gap: 8,
      }}>
        <View style={{ width: 40, height: 4, borderRadius: 2, backgroundColor: c.border, alignSelf: 'center' }} />
        <T size={TYPE.h3} weight="700" style={{ paddingVertical: SPACE.s3 }}>صيغة الكشف</T>
        {opts.map(({ f, Icon, tint }) => (
          <Pressable
            key={f} onPress={() => onPick(f)}
            style={({ pressed }) => ({
              backgroundColor: pressed ? c.sunken : c.cardAlt, borderRadius: RADIUS.md,
              padding: 12, flexDirection: 'row-reverse', alignItems: 'center', gap: 12,
            })}>
            <View style={{
              width: 40, height: 40, borderRadius: RADIUS.sm, backgroundColor: tint + '1A',
              alignItems: 'center', justifyContent: 'center',
            }}>
              <Icon size={21} color={tint} />
            </View>
            <T size={TYPE.base} weight="600" style={{ flex: 1 }}>{FORMAT_AR[f]}</T>
            <T size={TYPE.small} weight="700" color={c.text3}>{f.toUpperCase()}</T>
          </Pressable>
        ))}
      </View>
    </Modal>
  )
}
