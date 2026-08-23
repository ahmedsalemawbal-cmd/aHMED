import React, { useCallback, useMemo, useState } from 'react'
import { FlatList, RefreshControl, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Badge, Button, Card, Empty, Input, Loading, Row, T } from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { SPACE, TYPE } from '../lib/theme'
import { fmtRelative } from '../lib/format'
import type { DocumentRow, Template } from '../lib/types'

export default function MyFiles() {
  const { c, subscriber, profile } = useApp()
  const nav = useNavigation<any>()
  const [docs, setDocs] = useState<DocumentRow[]>([])
  const [suggest, setSuggest] = useState<Template[]>([])
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!subscriber?.id) return
    const { data } = await supabase.from('documents').select('*')
      .eq('subscriber_id', subscriber.id).order('updated_at', { ascending: false })
    setDocs((data || []) as DocumentRow[])
    if (!data?.length) {
      const { data: t } = await supabase.from('templates').select('*')
        .eq('status', 'published')
        .in('category_key', [profile?.role_key || 'teacher', 'general'])
        .order('sort').limit(3)
      setSuggest((t || []) as Template[])
    }
    setLoading(false)
  }, [subscriber?.id, profile?.role_key])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const shown = useMemo(() => {
    const term = q.trim()
    return term ? docs.filter((d) => d.title.includes(term)) : docs
  }, [docs, q])

  if (loading) return <Loading />

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <AppHeader title="ملفّاتي" />
      {docs.length > 0 && (
        <View style={{ padding: SPACE.s4 }}>
          <Input value={q} onChangeText={setQ} placeholder="ابحث في ملفّاتك" />
        </View>
      )}
      <FlatList
        data={shown}
        keyExtractor={(d) => d.id}
        contentContainerStyle={{ padding: SPACE.s4, paddingTop: docs.length ? 0 : SPACE.s4, gap: SPACE.s3, paddingBottom: SPACE.s8 }}
        refreshControl={
          <RefreshControl refreshing={refreshing} tintColor={c.primary}
            onRefresh={async () => { setRefreshing(true); await load(); setRefreshing(false) }} />
        }
        ListEmptyComponent={
          <View style={{ gap: SPACE.s4 }}>
            <Card>
              <Empty
                title={q ? 'لا نتيجة لهذا البحث' : 'لم تُنشئ ملفًّا بعد'}
                line={q ? 'جرّب كلمةً أقصر.' : 'اختر قالبًا من المكتبة، املأه، وصدّره. يبقى محفوظًا في حسابك تفتحه متى شئت.'}
                action={<Button label={q ? 'امسح البحث' : 'تصفّح المكتبة'} variant="primary"
                  onPress={() => (q ? setQ('') : nav.navigate('القوالب'))} />}
              />
            </Card>
            {!q && suggest.length > 0 && (
              <View style={{ gap: SPACE.s3 }}>
                <T size={TYPE.lead} weight="700">مقترَحٌ لدورك</T>
                {suggest.map((t) => (
                  <Card key={t.id} onPress={() => nav.navigate('TemplateDetail', { slug: t.slug })} style={{ gap: 6 }}>
                    <T size={TYPE.base} weight="700">{t.title}</T>
                    <T size={TYPE.body} color={c.text3}>{t.fields?.length || 0} حقلًا · نحو {t.estimated_minutes} دقائق</T>
                  </Card>
                ))}
              </View>
            )}
          </View>
        }
        renderItem={({ item }) => (
          <Card onPress={() => nav.navigate('Editor', { id: item.id })} style={{ gap: 8 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <T size={TYPE.lead} weight="700" numberOfLines={1} style={{ flex: 1 }}>{item.title}</T>
              <Badge label={item.status === 'complete' ? 'مكتمل' : 'مسوّدة'}
                tone={item.status === 'complete' ? 'success' : 'neutral'} />
            </Row>
            <T size={TYPE.small} color={c.text3}>آخر تعديل {fmtRelative(item.updated_at)}</T>
          </Card>
        )}
      />
    </View>
  )
}
