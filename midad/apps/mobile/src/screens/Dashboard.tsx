import React, { useCallback, useState } from 'react'
import { Pressable, RefreshControl, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { AppHeader } from '../ui/AppHeader'
import { Badge, Button, Card, Empty, HScroll, Loading, Row, Screen, Section, T } from '../ui/kit'
import {
  IcLibrary, IcFiles, IcChevron, IcTable, IcClock, IcCheck, IcCalendar, IcPlus,
} from '../ui/icons'
import { SPACE, RADIUS, TYPE, elevation } from '../lib/theme'
import { daysLabel, fmtNum, fmtRelative } from '../lib/format'
import type { DocumentRow, Period } from '../lib/types'
import {
  fetchMyPeriods, fetchTakenToday, isoDate, periodState, todayWeekday,
} from '../lib/classroom'

export default function Dashboard() {
  const { profile, subscriber, access, trialDays, c, plan } = useApp()
  const nav = useNavigation<any>()
  const [docs, setDocs] = useState<DocumentRow[]>([])
  const [noor, setNoor] = useState(0)
  const [team, setTeam] = useState(0)
  const [periods, setPeriods] = useState<Period[]>([])
  const [taken, setTaken] = useState<Set<string>>(new Set())
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!subscriber?.id) { setLoading(false); return }
    const [d, n, t] = await Promise.all([
      supabase.from('documents').select('*').eq('subscriber_id', subscriber.id)
        .order('updated_at', { ascending: false }).limit(20),
      supabase.from('noor_tables').select('id', { count: 'exact', head: true }).eq('subscriber_id', subscriber.id),
      supabase.from('profiles').select('id', { count: 'exact', head: true })
        .eq('subscriber_id', subscriber.id).eq('status', 'active'),
    ])
    setDocs((d.data || []) as DocumentRow[])
    setNoor(n.count || 0)
    setTeam(t.count || 0)
    if (profile?.id) {
      try {
        const [ps, tk] = await Promise.all([
          fetchMyPeriods(subscriber.id, profile.id),
          fetchTakenToday(subscriber.id, isoDate()),
        ])
        setPeriods(ps); setTaken(tk)
      } catch { /* الجدول اختياريّ — لا يُسقط الرئيسيّة */ }
    }
    setLoading(false)
  }, [subscriber?.id, profile?.id])

  useFocusEffect(useCallback(() => { load() }, [load]))
  const onRefresh = async () => { setRefreshing(true); await load(); setRefreshing(false) }

  const isSolo = subscriber?.account_type === 'teacher'
  const today = periods.filter((p) => p.weekday === todayWeekday())
  const live = today.find((p) => periodState(p, taken) === 'now')
    || today.find((p) => periodState(p, taken) === 'next')
  const doneToday = today.filter((p) => taken.has(p.id)).length

  return (
    <Screen
      header={<AppHeader />}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.primary} />}>

      {access === 'trial' && (
        <Pressable onPress={() => nav.navigate('حسابي')}>
          <View style={{
            backgroundColor: trialDays <= 2 ? c.warnSoft : c.primarySoft,
            borderRadius: RADIUS.lg, padding: 13,
            flexDirection: 'row-reverse', alignItems: 'center', gap: 10,
          }}>
            <IcClock size={18} color={trialDays <= 2 ? c.warn : c.primarySoftFg} />
            <T size={TYPE.body} weight="600" color={trialDays <= 2 ? c.warn : c.primarySoftFg} style={{ flex: 1 }}>
              {trialDays <= 2 ? `تنتهي تجربتك بعد ${daysLabel(trialDays)}` : `بقي ${daysLabel(trialDays)} من تجربتك`}
            </T>
            <IcChevron size={15} color={trialDays <= 2 ? c.warn : c.primarySoftFg} />
          </View>
        </Pressable>
      )}

      {loading ? <Loading /> : (
        <>
          {/* ───── حصّة الآن ───── */}
          {live ? <LivePeriod live={live} isNow={periodState(live, taken) === 'now'} /> : null}

          {/* ───── وصولٌ سريع: أيقوناتٌ لا جُمل ───── */}
          <HScroll>
            <Quick icon={<IcPlus size={21} color={c.tint.violet} />} label="ملفّ جديد"
              tint={c.tint.violet} onPress={() => nav.navigate('القوالب')} />
            <Quick icon={<IcCheck size={21} color={c.tint.teal} />} label="رصد الحضور"
              tint={c.tint.teal} badge={today.length ? `${doneToday}/${today.length}` : undefined}
              onPress={() => nav.navigate('الرصد')} />
            <Quick icon={<IcCalendar size={21} color={c.tint.blue} />} label="جدولي"
              tint={c.tint.blue} badge={periods.length ? fmtNum(periods.length) : undefined}
              onPress={() => nav.navigate('Timetable')} />
            <Quick icon={<IcTable size={21} color={c.tint.amber} />} label="جداول نور"
              tint={c.tint.amber} badge={noor ? fmtNum(noor) : undefined}
              onPress={() => nav.navigate('Noor')} />
            <Quick icon={<IcFiles size={21} color={c.tint.rose} />} label="ملفّاتي"
              tint={c.tint.rose} badge={docs.length ? fmtNum(docs.length) : undefined}
              onPress={() => nav.navigate('ملفّاتي')} />
            {!isSolo && (
              <Quick icon={<IcLibrary size={21} color={c.tint.lime} />} label="الفريق"
                tint={c.tint.lime} badge={`${team}/${plan?.seats ?? '—'}`}
                onPress={() => nav.navigate('حسابي')} />
            )}
          </HScroll>

          {/* ───── يوم الأحد … الخميس ───── */}
          {today.length > 0 && (
            <Section title="حصص اليوم" action={
              <Button label="الجدول" variant="ghost" small onPress={() => nav.navigate('Timetable')} />
            }>
              <HScroll gap={9}>
                {today.map((p) => (
                  <PeriodChip key={p.id} p={p} state={periodState(p, taken)}
                    onPress={() => nav.navigate('الرصد', { periodId: p.id })} />
                ))}
              </HScroll>
            </Section>
          )}

          {/* ───── آخر الملفّات ───── */}
          <Section title="آخر ملفّاتي" action={
            docs.length > 0
              ? <Button label="الكلّ" variant="ghost" small onPress={() => nav.navigate('ملفّاتي')} />
              : undefined
          }>
            {docs.length === 0 ? (
              <Card>
                <Empty
                  title="لم تُنشئ ملفًّا بعد"
                  line="اختر قالبًا، املأه في جوّالك، وصدّره جاهزًا للطباعة."
                  art={<IcFiles size={30} color={c.text3} />}
                  action={<Button label="تصفّح القوالب" variant="primary" onPress={() => nav.navigate('القوالب')} />}
                />
              </Card>
            ) : (
              <View style={{ gap: 9 }}>
                {docs.slice(0, 5).map((d) => (
                  <Card key={d.id} onPress={() => nav.navigate('Editor', { id: d.id })} style={{ padding: 13 }}>
                    <Row style={{ justifyContent: 'space-between' }} gap={11}>
                      <View style={{
                        width: 38, height: 38, borderRadius: RADIUS.sm,
                        backgroundColor: d.status === 'complete' ? c.successSoft : c.sunken,
                        alignItems: 'center', justifyContent: 'center',
                      }}>
                        <IcFiles size={18} color={d.status === 'complete' ? c.success : c.text3} />
                      </View>
                      <View style={{ flex: 1, gap: 3 }}>
                        <T size={TYPE.base} weight="600" numberOfLines={1}>{d.title}</T>
                        <T size={TYPE.small} color={c.text3}>{fmtRelative(d.updated_at)}</T>
                      </View>
                      {d.status === 'complete'
                        ? <Badge label="مكتمل" tone="success" />
                        : <Badge label="مسوّدة" tone="neutral" />}
                      <IcChevron size={15} color={c.text3} />
                    </Row>
                  </Card>
                ))}
              </View>
            )}
          </Section>
        </>
      )}
    </Screen>
  )
}

/* ───────── حصّة الآن ───────── */

function LivePeriod({ live, isNow }: { live: Period; isNow: boolean }) {
  const { c } = useApp()
  const nav = useNavigation<any>()
  return (
    <View style={{
      backgroundColor: isNow ? c.primary : c.card,
      borderRadius: RADIUS.xl, padding: SPACE.s5, gap: 13,
      ...elevation(c, isNow ? 2 : 1),
    }}>
      <Row style={{ justifyContent: 'space-between' }}>
        <Row gap={7}>
          <View style={{
            width: 7, height: 7, borderRadius: 4,
            backgroundColor: isNow ? c.onPrimary : c.warn,
          }} />
          <T size={TYPE.small} weight="700" color={isNow ? c.onPrimary : c.text3}>
            {isNow ? 'جارية الآن' : 'الحصّة التالية'}
          </T>
        </Row>
        <T size={TYPE.small} weight="600" color={isNow ? c.onPrimary : c.text3}>
          {live.starts_at?.slice(0, 5)} — {live.ends_at?.slice(0, 5)}
        </T>
      </Row>

      <View style={{ gap: 3 }}>
        <T size={TYPE.h2} weight="700" color={isNow ? c.onPrimary : c.text} numberOfLines={1}>
          {live.subject}
        </T>
        <T size={TYPE.body} color={isNow ? c.onPrimary : c.text3} numberOfLines={1}>
          {live.classes?.name || ''}{live.room ? ` · ${live.room}` : ''}
        </T>
      </View>

      <Pressable
        onPress={() => nav.navigate('الرصد', { periodId: live.id })}
        style={({ pressed }) => ({
          backgroundColor: isNow ? c.onPrimary : c.primary,
          borderRadius: RADIUS.md, paddingVertical: 12,
          flexDirection: 'row-reverse', alignItems: 'center', justifyContent: 'center', gap: 8,
          opacity: pressed ? 0.85 : 1,
        })}>
        <IcCheck size={17} color={isNow ? c.primary : c.onPrimary} />
        <T size={TYPE.base} weight="700" color={isNow ? c.primary : c.onPrimary}>ابدأ الرصد</T>
      </Pressable>
    </View>
  )
}

/* ───────── مربّع وصولٍ سريع ───────── */

function Quick({ icon, label, tint, badge, onPress }: {
  icon: React.ReactNode; label: string; tint: string; badge?: string; onPress: () => void
}) {
  const { c } = useApp()
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => ({
        width: 96, backgroundColor: c.card, borderRadius: RADIUS.lg,
        paddingVertical: 14, paddingHorizontal: 9, gap: 8, alignItems: 'center',
        opacity: pressed ? 0.75 : 1, ...elevation(c, 1),
      })}>
      <View style={{
        width: 44, height: 44, borderRadius: RADIUS.md, backgroundColor: tint + '1A',
        alignItems: 'center', justifyContent: 'center',
      }}>{icon}</View>
      <T size={TYPE.small} weight="600" numberOfLines={1} style={{ textAlign: 'center' }}>{label}</T>
      {badge ? (
        <View style={{
          position: 'absolute', top: 9, left: 9,
          backgroundColor: tint, borderRadius: RADIUS.pill,
          paddingHorizontal: 6, paddingVertical: 1.5,
        }}>
          <T size={TYPE.micro} weight="700" color="#fff">{badge}</T>
        </View>
      ) : null}
    </Pressable>
  )
}

/* ───────── شارة حصّة ───────── */

function PeriodChip({ p, state, onPress }: {
  p: Period; state: ReturnType<typeof periodState>; onPress: () => void
}) {
  const { c } = useApp()
  const look = {
    done: { bg: c.successSoft, fg: c.success, note: 'رُصدت' },
    now: { bg: c.primary, fg: c.onPrimary, note: 'الآن' },
    next: { bg: c.warnSoft, fg: c.warn, note: 'التالية' },
    past: { bg: c.sunken, fg: c.text3, note: 'فاتت' },
    upcoming: { bg: c.sunken, fg: c.text3, note: '' },
  }[state]
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => ({
        width: 132, backgroundColor: look.bg, borderRadius: RADIUS.lg,
        padding: 13, gap: 5, opacity: pressed ? 0.8 : 1,
      })}>
      <Row style={{ justifyContent: 'space-between' }}>
        <T size={TYPE.micro} weight="700" color={look.fg}>الحصّة {p.slot}</T>
        {look.note ? <T size={TYPE.micro} weight="700" color={look.fg}>{look.note}</T> : null}
      </Row>
      <T size={TYPE.body} weight="700" color={look.fg} numberOfLines={1}>{p.subject}</T>
      <T size={TYPE.micro} color={look.fg} numberOfLines={1} style={{ opacity: 0.85 }}>
        {p.classes?.name || ''}
      </T>
      <T size={TYPE.micro} color={look.fg} style={{ opacity: 0.75 }}>
        {p.starts_at?.slice(0, 5)}
      </T>
    </Pressable>
  )
}
