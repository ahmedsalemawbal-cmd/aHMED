import React, { useCallback, useState } from 'react'
import { FlatList, RefreshControl, View, Linking } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase, callFunction } from '../lib/supabase'
import { Alert, Badge, Button, Card, Empty, Loading, Row, T } from '../ui/kit'
import { RADIUS, SPACE } from '../lib/theme'
import { fmtNum, fmtRelative, fmtDate } from '../lib/format'
import { WEB_APP_URL } from '../lib/config'
import type { NoorTable } from '../lib/types'

export default function Noor() {
  const { c, subscriber } = useApp()
  const nav = useNavigation<any>()
  const [tables, setTables] = useState<NoorTable[]>([])
  const [key, setKey] = useState<{ key: string; expires_at: string } | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!subscriber?.id) return
    const [t, k] = await Promise.all([
      supabase.from('noor_tables').select('*').eq('subscriber_id', subscriber.id)
        .order('created_at', { ascending: false }),
      supabase.from('link_keys').select('key,expires_at').is('revoked_at', null)
        .gt('expires_at', new Date().toISOString()).order('created_at', { ascending: false }).limit(1),
    ])
    setTables((t.data || []) as NoorTable[])
    setKey((k.data && k.data[0]) as any || null)
    setLoading(false)
  }, [subscriber?.id])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const createKey = async () => {
    setBusy(true); setErr(null)
    try { await callFunction('noor', { action: 'create_key' }); await load() }
    catch (e: any) { setErr(e?.message || 'تعذّر إنشاء المفتاح') }
    finally { setBusy(false) }
  }

  if (loading) return <Loading />

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: c.bg }}
      data={tables}
      keyExtractor={(t) => t.id}
      contentContainerStyle={{ padding: SPACE.s4, gap: SPACE.s3, paddingBottom: SPACE.s8 }}
      refreshControl={
        <RefreshControl refreshing={refreshing} tintColor={c.primary}
          onRefresh={async () => { setRefreshing(true); await load(); setRefreshing(false) }} />
      }
      ListHeaderComponent={
        tables.length > 0 ? null : (
          <View style={{ gap: SPACE.s4 }}>
            <T size={20} weight="700">اربط نور في ثلاث خطوات</T>
            {err ? <Alert tone="danger">{err}</Alert> : null}

            <Step n={1} title="ثبّت إضافة المتصفّح"
              line="الإضافة تعمل على كروم وإيدج في الحاسوب. نزّلها من صفحة «جداول نور» في موقع مِداد."
              action={<Button label="افتح صفحة الإضافة" variant="soft" small
                onPress={() => Linking.openURL(WEB_APP_URL + '/#/app/noor/key')} />} />

            <Step n={2} title="انسخ مفتاح الربط"
              line={key
                ? `مفتاحك جاهز وصالح حتى ${fmtDate(key.expires_at)}. الصقه في الإضافة مرّةً واحدة.`
                : 'أنشئ مفتاحًا الآن، ثمّ الصقه في الإضافة. المفتاح صالح 90 يومًا ويمكن إلغاؤه في أيّ لحظة.'}
              action={key
                ? <View style={{
                    backgroundColor: c.sunken, borderRadius: RADIUS.sm, padding: 12,
                    borderWidth: 1, borderColor: c.border,
                  }}>
                    <T size={13} weight="700" style={{ textAlign: 'left', writingDirection: 'ltr' }}>{key.key}</T>
                  </View>
                : <Button label="أنشئ مفتاحًا" variant="primary" small onPress={createKey} loading={busy} />} />

            <Step n={3} title="افتح نور واضغط «أرسل»"
              line="افتح أيّ كشفٍ في منصّة نور من حاسوبك، اضغط أيقونة مِداد في شريط المتصفّح، ثمّ «أرسل إلى مِداد». يصلك الجدول هنا فورًا." />

            <Alert tone="info">
              الإضافة لا تعمل إلّا حين تضغطها بنفسك، ولا تقرأ كلمة مرور نور، ويمكنك إلغاء المفتاح في أيّ لحظة.
            </Alert>
          </View>
        )
      }
      ListEmptyComponent={null}
      renderItem={({ item }) => (
        <Card onPress={() => nav.navigate('NoorTable', { id: item.id })} style={{ gap: 8 }}>
          <T size={14.5} weight="700" numberOfLines={2}>{item.title}</T>
          <Row style={{ justifyContent: 'space-between' }}>
            <T size={11.5} color={c.text3}>
              {fmtNum(item.row_count)} صفًّا · {item.columns?.length || 0} أعمدة
            </T>
            <T size={11.5} color={c.text3}>{fmtRelative(item.created_at)}</T>
          </Row>
        </Card>
      )}
    />
  )
}

function Step({ n, title, line, action }: { n: number; title: string; line: string; action?: React.ReactNode }) {
  const { c } = useApp()
  return (
    <Card style={{ gap: 10 }}>
      <Row gap={10}>
        <View style={{
          width: 28, height: 28, borderRadius: 9, backgroundColor: c.primary,
          alignItems: 'center', justifyContent: 'center',
        }}>
          <T size={13} weight="700" color={c.onPrimary}>{n}</T>
        </View>
        <T size={15} weight="700" style={{ flex: 1 }}>{title}</T>
      </Row>
      <T size={12.5} color={c.text2}>{line}</T>
      {action}
    </Card>
  )
}
