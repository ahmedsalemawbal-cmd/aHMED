import React, { useCallback, useState } from 'react'
import { RefreshControl, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Badge, Button, Card, Empty, Loading, Row, Screen, T } from '../ui/kit'
import { IcLibrary, IcFiles, IcChevron, IcTable, IcTeam } from '../ui/icons'
import { SPACE, RADIUS } from '../lib/theme'
import { daysLabel, fmtBoth, fmtNum, fmtRelative, greeting } from '../lib/format'
import type { DocumentRow } from '../lib/types'

export default function Dashboard() {
  const { profile, subscriber, access, trialDays, roles, c, plan } = useApp()
  const nav = useNavigation<any>()
  const [docs, setDocs] = useState<DocumentRow[]>([])
  const [noor, setNoor] = useState(0)
  const [team, setTeam] = useState(0)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!subscriber?.id) return
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
    setLoading(false)
  }, [subscriber?.id])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const onRefresh = async () => { setRefreshing(true); await load(); setRefreshing(false) }
  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || ''
  const isSolo = subscriber?.account_type === 'teacher'

  return (
    <Screen refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.accent} />}>
      <View style={{ gap: 4 }}>
        <T size={22} weight="700">{greeting()}، {(profile?.full_name || '').split(' ')[0]}</T>
        <T size={12.5} color={c.text3}>{fmtBoth(new Date())}</T>
        {roleName ? <Badge label={roleName} tone="accent" /> : null}
      </View>

      {access === 'trial' && (
        <Card style={{
          backgroundColor: trialDays <= 2 ? c.warnSoft : c.infoSoft,
          borderColor: 'transparent', gap: 10,
        }}>
          <T size={13.5} weight="700" color={trialDays <= 2 ? c.warn : c.info}>
            {trialDays <= 2
              ? `تنتهي تجربتك بعد ${daysLabel(trialDays)}`
              : `بقي ${daysLabel(trialDays)} من تجربتك`}
          </T>
          <T size={12.5} color={trialDays <= 2 ? c.warn : c.info}>
            ملفّاتك تخرج بعلامة مائية أثناء التجربة، وتزول فور اشتراكك.
          </T>
        </Card>
      )}

      {loading ? <Loading /> : (
        <>
          <Row gap={12}>
            <Stat label="ملفّاتي" value={fmtNum(docs.length)} />
            <Stat label="جداول نور" value={fmtNum(noor)} />
            {!isSolo && <Stat label="الفريق" value={`${team} من ${plan?.seats ?? '—'}`} />}
          </Row>

          <Card onPress={() => nav.navigate('القوالب')} style={{ gap: 12 }}>
            <View style={{
              width: 44, height: 44, borderRadius: 13, backgroundColor: c.accentSoft,
              alignItems: 'center', justifyContent: 'center',
            }}>
              <IcLibrary size={22} color={c.accentSoftFg} />
            </View>
            <T size={17} weight="700">ابدأ ملفًّا جديدًا</T>
            <T size={13} color={c.text2}>
              اختر قالبًا من المكتبة، املأ حقوله في جوّالك، وصدّره PDF جاهزًا للطباعة.
            </T>
            <Button label="تصفّح القوالب" variant="primary" small
              onPress={() => nav.navigate('القوالب')} style={{ alignSelf: 'flex-start' }} />
          </Card>

          <View style={{ gap: SPACE.s3 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <T size={16} weight="700">آخر ملفّاتي</T>
              {docs.length > 0 && (
                <Button label="عرض الكلّ" variant="ghost" small onPress={() => nav.navigate('ملفّاتي')} />
              )}
            </Row>

            {docs.length === 0 ? (
              <Card>
                <Empty
                  title="لم تُنشئ ملفًّا بعد"
                  line="اختر قالبًا من المكتبة، املأه، وصدّره. يبقى محفوظًا في حسابك."
                  art={<IcFiles size={30} color={c.text3} />}
                action={<Button label="تصفّح القوالب" variant="primary" onPress={() => nav.navigate('القوالب')} />}
                />
              </Card>
            ) : (
              docs.slice(0, 5).map((d) => (
                <Card key={d.id} onPress={() => nav.navigate('Editor', { id: d.id })} style={{ padding: SPACE.s4 }}>
                  <Row style={{ justifyContent: 'space-between' }}>
                    <View style={{ flex: 1, gap: 4 }}>
                      <T size={14} weight="600" numberOfLines={1}>{d.title}</T>
                      <T size={11.5} color={c.text3}>{fmtRelative(d.updated_at)}</T>
                    </View>
                    <Badge label={d.status === 'complete' ? 'مكتمل' : 'مسوّدة'}
                      tone={d.status === 'complete' ? 'success' : 'neutral'} />
                    <IcChevron size={15} color={c.text3} />
                  </Row>
                </Card>
              ))
            )}
          </View>
        </>
      )}
    </Screen>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  const { c } = useApp()
  return (
    <View style={{
      flex: 1, backgroundColor: c.card, borderColor: c.border, borderWidth: 1,
      borderRadius: RADIUS.lg, padding: SPACE.s4, gap: 4,
    }}>
      <T size={11.5} weight="600" color={c.text2}>{label}</T>
      <T size={22} weight="700">{value}</T>
    </View>
  )
}
