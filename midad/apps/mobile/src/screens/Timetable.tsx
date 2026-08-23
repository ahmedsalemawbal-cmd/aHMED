import React, { useCallback, useMemo, useState } from 'react'
import { Pressable, RefreshControl, ScrollView, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import {
  WEEKDAYS, fetchMyPeriods, fetchTakenToday, isoDate, periodState, todayWeekday,
} from '../lib/classroom'
import type { Period } from '../lib/types'
import { Badge, Button, Card, Empty, ErrorView, Loading, Row, T } from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { IcTable, IcCheck, IcClock, IcChevron } from '../ui/icons'
import { RADIUS, SPACE, TYPE } from '../lib/theme'

export default function Timetable() {
  const { c, subscriber, profile } = useApp()
  const nav = useNavigation<any>()
  const [day, setDay] = useState(todayWeekday())
  const [periods, setPeriods] = useState<Period[]>([])
  const [taken, setTaken] = useState<Set<string>>(new Set())
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!subscriber?.id || !profile?.id) return
    try {
      const [ps, tk] = await Promise.all([
        fetchMyPeriods(subscriber.id, profile.id),
        fetchTakenToday(subscriber.id, isoDate()),
      ])
      setPeriods(ps); setTaken(tk); setErr(null)
    } catch (e: any) { setErr(e?.message || 'تعذّر تحميل الجدول') }
    finally { setLoading(false) }
  }, [subscriber?.id, profile?.id])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const ofDay = useMemo(
    () => periods.filter((p) => p.weekday === day).sort((a, b) => a.slot - b.slot),
    [periods, day])

  const perDay = useMemo(() => {
    const m: Record<number, number> = {}
    for (const p of periods) m[p.weekday] = (m[p.weekday] || 0) + 1
    return m
  }, [periods])

  if (loading) return <Loading label="جارٍ تحميل جدولك…" />
  if (err) return <ErrorView message={err} onRetry={load} />

  const isToday = day === todayWeekday()

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <AppHeader title="جدولي" back />
      <View style={{ backgroundColor: c.card, borderBottomWidth: 1, borderBottomColor: c.border, paddingVertical: SPACE.s3 }}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ flexDirection: 'row-reverse', gap: 8, paddingHorizontal: SPACE.s4 }}>
          {WEEKDAYS.map((w, i) => {
            const on = i === day
            return (
              <Pressable key={w} onPress={() => setDay(i)}
                style={{
                  backgroundColor: on ? c.primary : c.sunken,
                  borderColor: on ? c.primary : c.border, borderWidth: 1,
                  borderRadius: RADIUS.pill, paddingHorizontal: 16, paddingVertical: 9, minHeight: 42,
                  justifyContent: 'center',
                }}>
                <T size={TYPE.body} weight={on ? '700' : '500'} color={on ? c.onPrimary : c.text2}>
                  {w}{perDay[i] ? ` (${perDay[i]})` : ''}
                </T>
              </Pressable>
            )
          })}
        </ScrollView>
      </View>

      <ScrollView
        contentContainerStyle={{ padding: SPACE.s4, gap: SPACE.s3, paddingBottom: SPACE.s8 }}
        refreshControl={<RefreshControl refreshing={refreshing} tintColor={c.primary}
          onRefresh={async () => { setRefreshing(true); await load(); setRefreshing(false) }} />}>

        {ofDay.length === 0 ? (
          <Card>
            <Empty art={<IcTable size={30} color={c.text3} />}
              title={`لا حصص ${WEEKDAYS[day]}`}
              line="اليوم فارغٌ في جدولك. اختر يومًا آخر من الأعلى." />
          </Card>
        ) : ofDay.map((p) => {
          const st = isToday ? periodState(p, taken) : (taken.has(p.id) ? 'done' : 'upcoming')
          const meta =
            st === 'done' ? { label: 'رُصدت', tone: 'success' as const, border: c.success }
            : st === 'now' ? { label: 'الآن', tone: 'primary' as const, border: c.primary }
            : st === 'next' ? { label: 'انتظار', tone: 'info' as const, border: c.border }
            : st === 'past' ? { label: 'فاتت بلا رصد', tone: 'warn' as const, border: c.warn }
            : { label: '', tone: 'neutral' as const, border: c.border }

          return (
            <Card key={p.id}
              onPress={() => nav.navigate('الرصد', { periodId: p.id })}
              style={{
                gap: 10, borderColor: meta.border,
                borderWidth: st === 'now' ? 2 : 1,
              }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <Row gap={9}>
                  <View style={{
                    width: 34, height: 34, borderRadius: RADIUS.sm,
                    backgroundColor: st === 'now' ? c.primary : c.sunken,
                    alignItems: 'center', justifyContent: 'center',
                  }}>
                    <T size={TYPE.bodyLg} weight="700" color={st === 'now' ? c.onPrimary : c.text2}>{p.slot}</T>
                  </View>
                  <View>
                    <T size={TYPE.lead} weight="700">{p.subject} — {p.classes?.name || '—'}</T>
                    <T size={TYPE.small} color={c.text3}>
                      {p.starts_at?.slice(0, 5)} — {p.ends_at?.slice(0, 5)}{p.room ? ` · ${p.room}` : ''}
                    </T>
                  </View>
                </Row>
                {meta.label ? <Badge label={meta.label} tone={meta.tone} /> : null}
              </Row>
              <Row style={{ justifyContent: 'space-between' }}>
                <T size={TYPE.body} weight="700" color={st === 'done' ? c.success : c.primary}>
                  {st === 'done' ? 'عرض الرصد' : 'ابدأ رصد الحضور'}
                </T>
                <IcChevron size={15} color={c.text3} />
              </Row>
            </Card>
          )
        })}
      </ScrollView>
    </View>
  )
}
