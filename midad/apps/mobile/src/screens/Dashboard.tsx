import React, { useCallback, useState } from 'react'
import { Pressable, RefreshControl, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { AppHeader } from '../ui/AppHeader'
import { Badge, Button, Card, HScroll, Loading, Row, Screen, Section, T } from '../ui/kit'
import { IcChevron, IcTable, IcClock, IcCheck, IcCalendar, IcSpark } from '../ui/icons'
import { SPACE, RADIUS, TYPE, elevation } from '../lib/theme'
import { daysLabel, fmtNum, fmtRelative } from '../lib/format'
import type { Period } from '../lib/types'
import {
  fetchMyPeriods, fetchTakenToday, isoDate, periodState, todayWeekday,
} from '../lib/classroom'

export default function Dashboard() {
  const { profile, subscriber, access, trialDays, c, plan } = useApp()
  const nav = useNavigation<any>()
  const [noor, setNoor] = useState(0)
  const [shots, setShots] = useState(0)
  const [periods, setPeriods] = useState<Period[]>([])
  const [taken, setTaken] = useState<Set<string>>(new Set())
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!subscriber?.id) { setLoading(false); return }
    /* أعدادٌ لا متون: البلاطة تعرض رقمًا، وجلبُ الصفوف كلِّها لعدّها
       يُنزل ميغاباياتٍ لتُرسم منها خانتان. */
    const [n, sh] = await Promise.all([
      supabase.from('noor_tables').select('id', { count: 'exact', head: true })
        .eq('subscriber_id', subscriber.id),
      supabase.from('portfolio_items').select('id', { count: 'exact', head: true })
        .eq('owner_id', profile?.id || ''),
    ])
    setNoor(n.count || 0)
    setShots(sh.count || 0)
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
            borderRadius: RADIUS.lg, padding: 12,
            flexDirection: 'row', alignItems: 'center', gap: 12,
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

          {/* ───── ما يُفعل الآن — شبكةٌ لا شريطٌ يُقطع ─────
              كان صفًّا أفقيًّا يعرض بلاطةً ونصفَ بلاطتين على الطرفين،
              فيبدو مكسورًا لا ممتدًّا. والعين تقرأ نصفَ الشيء عطبًا.

                  ما يُرى نصفُه يُقرأ خطأً.

              وأربعٌ لا ستّ: «الفريق» انتقل إلى حسابي وهو موضعه، فصارت
              الشبكةُ ٢×٢ مكتملةً بلا بلاطةٍ يتيمةٍ في صفٍّ أخير. */}
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: SPACE.s3 }}>
            <Quick icon={<IcCheck size={22} color={c.tint.teal} />} label="رصد الحضور"
              tint={c.tint.teal}
              stat={today.length ? `${fmtNum(doneToday)} من ${fmtNum(today.length)} حصص` : 'لا حصص اليوم'}
              onPress={() => nav.navigate('الرصد')} />
            <Quick icon={<IcCalendar size={22} color={c.tint.blue} />} label="جدولي"
              tint={c.tint.blue}
              stat={periods.length ? `${fmtNum(periods.length)} حصّة أسبوعيًّا` : 'لم يُضبط بعد'}
              onPress={() => nav.navigate('Timetable')} />
            <Quick icon={<IcTable size={22} color={c.tint.amber} />} label="جداول نور"
              tint={c.tint.amber}
              stat={noor ? `${fmtNum(noor)} جدولًا` : 'لا جداول بعد'}
              onPress={() => nav.navigate('Noor')} />
            <Quick icon={<IcSpark size={22} color={c.tint.violet} />} label="ملفّ الإنجاز"
              tint={c.tint.violet}
              stat={shots ? `${fmtNum(shots)} شاهدًا` : 'ابدأ بالتقاط شاهد'}
              onPress={() => nav.navigate('إنجازي')} />
          </View>

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
      borderRadius: RADIUS.xl, padding: SPACE.s5, gap: 12,
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

      <View style={{ gap: 4 }}>
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
          flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
          opacity: pressed ? 0.85 : 1,
        })}>
        <IcCheck size={17} color={isNow ? c.primary : c.onPrimary} />
        <T size={TYPE.base} weight="700" color={isNow ? c.primary : c.onPrimary}>ابدأ الرصد</T>
      </Pressable>
    </View>
  )
}

/* ───────── مربّع وصولٍ سريع ───────── */

/**
 * بلاطةُ عمل — نصفُ العرض، وارتفاعٌ واحدٌ للأربع.
 *
 * وكانت شارةُ العدد تطفو في زاويةٍ فوق البلاطة، فتبدو ملصقةً لا جزءًا
 * منها — وهي أحدُ ما يجعل الشاشة تبدو مبعثرة. فصار العددُ **سطرًا
 * تحت الاسم**: يُقرأ مع ما يصفه، ويشغل موضعًا ثابتًا في كلّ بلاطة.
 *
 *     ما يطفو فوق الشيء ليس منه.
 *
 * والسطرُ لا يغيب: يقول «لا جداول بعد» بدل أن يختفي فتتفاوت ارتفاعات
 * البلاطات وتنكسر الشبكة.
 */
function Quick({ icon, label, tint, stat, onPress }: {
  icon: React.ReactNode; label: string; tint: string; stat: string; onPress: () => void
}) {
  const { c } = useApp()
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => ({
        /* نصفُ العرض ناقصًا نصفَ الفجوة — فيستوي عمودان بلا حسابِ شاشة */
        flexBasis: '48%', flexGrow: 1,
        backgroundColor: c.card, borderRadius: RADIUS.lg,
        padding: SPACE.s4, gap: 12,
        opacity: pressed ? 0.75 : 1, ...elevation(c, 1),
      })}>
      <View style={{
        width: 42, height: 42, borderRadius: RADIUS.md, backgroundColor: tint + '1F',
        alignItems: 'center', justifyContent: 'center',
      }}>{icon}</View>
      <View style={{ gap: 2 }}>
        <T size={TYPE.bodyLg} weight="700" numberOfLines={1}>{label}</T>
        <T size={TYPE.small} color={c.text3} numberOfLines={1}>{stat}</T>
      </View>
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
        padding: 12, gap: 4, opacity: pressed ? 0.8 : 1,
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
