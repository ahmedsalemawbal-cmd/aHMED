import React, { useEffect, useState } from 'react'
import { View } from 'react-native'
import { useNavigation, useRoute } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Alert, Badge, Button, Card, Divider, ErrorView, Loading, Row, Screen, T } from '../ui/kit'
import { SPACE } from '../lib/theme'
import { fieldSections } from '../lib/render'
import type { Template } from '../lib/types'

export default function TemplateDetail() {
  const route = useRoute<any>()
  const nav = useNavigation<any>()
  const { c, roles, plan, profile, subscriber, access } = useApp()
  const [tpl, setTpl] = useState<Template | null>(null)
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let alive = true
    ;(async () => {
      const { data, error } = await supabase.from('templates').select('*')
        .eq('slug', route.params?.slug).maybeSingle()
      if (!alive) return
      if (error || !data) { setErr('لم نجد هذا القالب'); setLoading(false); return }
      setTpl(data as Template); setLoading(false)
    })()
    return () => { alive = false }
  }, [route.params?.slug])

  if (loading) return <Loading />
  if (err || !tpl) return <ErrorView message={err || undefined} />

  const allowed = plan?.template_categories?.length ? plan.template_categories : null
  const locked = !!allowed && !allowed.includes(tpl.category_key)
  const canWrite = access === 'trial' || access === 'active'
  const sections = fieldSections(tpl.fields || [])

  const start = async () => {
    if (!subscriber || !profile) return
    setBusy(true)
    const { data, error } = await supabase.from('documents').insert({
      subscriber_id: subscriber.id, owner_id: profile.id, template_id: tpl.id,
      title: tpl.title, data: {}, status: 'draft',
    }).select().single()
    setBusy(false)
    if (error || !data) { setErr('تعذّر إنشاء الملفّ — تحقّق من اشتراكك'); return }
    nav.replace('Editor', { id: (data as any).id })
  }

  return (
    <Screen>
      <View style={{ gap: 10 }}>
        <Badge label={roles.find((r) => r.key === tpl.category_key)?.name_ar || tpl.category_key} tone="accent" />
        <T size={23} weight="700">{tpl.title}</T>
        {tpl.description ? <T size={14} color={c.text2}>{tpl.description}</T> : null}
        <Row gap={8}>
          <Badge label={`${tpl.fields?.length || 0} حقلًا`} />
          <Badge label={`نحو ${tpl.estimated_minutes} دقائق`} />
          <Badge label={(tpl.outputs || []).join(' · ').toUpperCase()} />
        </Row>
      </View>

      {locked ? (
        <>
          <Alert tone="warn">
            هذا القالب من فئةٍ غير مفتوحة في باقتك. باقة المدرسة تفتح كلّ الفئات لكلّ أعضاء فريقك.
          </Alert>
          <Button label="شاهد الباقات" variant="primary" onPress={() => nav.navigate('حسابي')} />
        </>
      ) : !canWrite ? (
        <>
          <Alert tone="danger">انتهى اشتراكك — جدّده لتبدأ ملفًّا جديدًا. ملفّاتك السابقة محفوظة.</Alert>
          <Button label="افتح الاشتراك" variant="primary" onPress={() => nav.navigate('حسابي')} />
        </>
      ) : (
        <Button label="ابدأ الملفّ" variant="primary" onPress={start} loading={busy} />
      )}

      <Card style={{ gap: SPACE.s3 }}>
        <T size={16} weight="700">ما ستملأ</T>
        {sections.map((sec) => (
          <View key={sec.name} style={{ gap: 6 }}>
            <T size={13} weight="700" color={c.accentSoftFg}>{sec.name}</T>
            {sec.fields.map((f) => (
              <Row key={f.key} gap={8} style={{ paddingVertical: 2 }}>
                <T size={12.5} color={c.text2}>•</T>
                <T size={12.5} color={c.text2} style={{ flex: 1 }}>
                  {f.label}{f.required ? ' (مطلوب)' : ''}
                  {f.type === 'table' ? ' — جدول' : ''}
                </T>
              </Row>
            ))}
            <Divider />
          </View>
        ))}
      </Card>
    </Screen>
  )
}
