import React, { useCallback, useEffect, useMemo, useState, useRef } from 'react'
import { FlatList, Pressable, RefreshControl, ScrollView, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Badge, Button, Card, Empty, Input, Loading, Row, T } from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { IcSearch, IcChevron, IcLock, IcLibrary } from '../ui/icons'
import { RADIUS, SPACE, TYPE } from '../lib/theme'
import type { Template } from '../lib/types'

export default function Library() {
  const { c, roles, plan } = useApp()
  const hsRef = useRef<ScrollView>(null)
  const nav = useNavigation<any>()
  const [all, setAll] = useState<Template[]>([])
  const [q, setQ] = useState('')
  const [cat, setCat] = useState('all')
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    const { data } = await supabase.from('templates').select('*')
      .eq('status', 'published').order('sort').order('title')
    setAll((data || []) as Template[])
    setLoading(false)
  }, [])

  useEffect(() => { load() }, [load])

  const allowed = plan?.template_categories?.length ? plan.template_categories : null
  const locked = (t: Template) => !!allowed && !allowed.includes(t.category_key)

  const counts = useMemo(() => {
    const m = new Map<string, number>()
    for (const t of all) m.set(t.category_key, (m.get(t.category_key) || 0) + 1)
    return m
  }, [all])

  const chips = useMemo(() => ([
    { key: 'all', label: 'الكلّ', n: all.length },
    ...roles.map((r) => ({ key: r.key, label: r.name_ar, n: counts.get(r.key) || 0 })).filter((x) => x.n > 0),
  ]), [roles, counts, all.length])

  const shown = useMemo(() => {
    let list = all
    if (cat !== 'all') list = list.filter((t) => t.category_key === cat)
    const term = q.trim()
    if (term) list = list.filter((t) => t.title.includes(term) || (t.description || '').includes(term))
    return list
  }, [all, cat, q])

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <AppHeader title="القوالب" />
      <View style={{ padding: SPACE.s4, gap: SPACE.s3 }}>
        <View>
          <Input value={q} onChangeText={setQ} placeholder="ابحث باسم القالب — مثال: سجلّ متابعة"
            style={{ paddingRight: 42 }} />
          <View style={{ position: 'absolute', right: 13, top: 15 }} pointerEvents="none">
            <IcSearch size={17} color={c.text3} />
          </View>
        </View>
        {/* يبدأ من اليمين: `row-reverse` يضع أوّل عنصرٍ يمينًا والمحرّك
            يفتح الشريط يسارًا، فيُرى آخرُه أوّلًا. */}
        <ScrollView horizontal showsHorizontalScrollIndicator={false}
          ref={hsRef}
          onContentSizeChange={(w) => { if (w > 0) hsRef.current?.scrollToEnd({ animated: false }) }}
          contentContainerStyle={{ flexDirection: 'row-reverse', gap: 8 }}>
          {chips.map((ch) => {
            const on = cat === ch.key
            return (
              <Pressable key={ch.key} onPress={() => setCat(ch.key)}
                style={{
                  backgroundColor: on ? c.primary : c.card, borderColor: on ? c.primary : c.border,
                  borderWidth: 1, borderRadius: RADIUS.pill, paddingHorizontal: 14,
                  paddingVertical: 9, minHeight: 40, justifyContent: 'center',
                }}>
                <T size={TYPE.body} weight="600" color={on ? c.onPrimary : c.text2}>
                  {ch.label} ({ch.n})
                </T>
              </Pressable>
            )
          })}
        </ScrollView>
      </View>

      {loading ? <Loading label="جارٍ تحميل المكتبة…" /> : (
        <FlatList
          data={shown}
          keyExtractor={(t) => t.id}
          contentContainerStyle={{ padding: SPACE.s4, paddingTop: 0, paddingBottom: SPACE.s8, gap: SPACE.s3 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} tintColor={c.primary}
              onRefresh={async () => { setRefreshing(true); await load(); setRefreshing(false) }} />
          }
          ListEmptyComponent={
            <Empty
              art={<IcLibrary size={30} color={c.text3} />}
              title={q ? `لم نجد قالبًا باسم «${q}»` : 'لا قوالب في هذه الفئة'}
              line="جرّب كلمةً أقصر، أو امسح الفئة، أو تصفّح كلّ الفئات."
              action={<Button label="امسح البحث" variant="primary" onPress={() => { setQ(''); setCat('all') }} />}
            />
          }
          renderItem={({ item }) => {
            const lk = locked(item)
            return (
              <Card
                onPress={() => nav.navigate('TemplateDetail', { slug: item.slug })}
                style={{ gap: 10, opacity: lk ? 0.7 : 1 }}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <Badge label={roles.find((r) => r.key === item.category_key)?.name_ar || item.category_key} tone="primary" />
                  {lk ? <Badge label="باقة المدرسة" /> : item.is_new ? <Badge label="جديد" tone="info" /> : null}
                </Row>
                <T size={TYPE.h3} weight="700">{item.title}</T>
                {item.description ? <T size={TYPE.body} color={c.text2} numberOfLines={2}>{item.description}</T> : null}
                <Row style={{ justifyContent: 'space-between' }}>
                  <T size={TYPE.small} color={c.text3}>
                    {item.fields?.length || 0} حقلًا · نحو {item.estimated_minutes} دقائق
                  </T>
                  <Row gap={5}>
                    <T size={TYPE.body} weight="700" color={lk ? c.text3 : c.primary}>{lk ? 'ارفع باقتك' : 'ابدأ'}</T>
                    {lk ? <IcLock size={14} color={c.text3} /> : <IcChevron size={14} color={c.primary} />}
                  </Row>
                </Row>
              </Card>
            )
          }}
        />
      )}
    </View>
  )
}
