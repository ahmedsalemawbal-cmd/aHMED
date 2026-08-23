import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, View } from 'react-native'
import { useNavigation, useRoute } from '@react-navigation/native'
import * as Print from 'expo-print'
import * as Sharing from 'expo-sharing'
import { useApp } from '../lib/store'
import { supabase, callFunction } from '../lib/supabase'
import {
  Alert, Badge, Button, Card, Divider, ErrorView, Input, Loading, Progress, Row, T,
} from '../ui/kit'
import { RADIUS, SPACE } from '../lib/theme'
import { fieldSections, filledCount, emptyRow, paperHtml } from '../lib/render'
import { IcSpark, IcDownload, IcCheck, IcChevronDown, IcPlus, IcTrash } from '../ui/icons'
import { fmtRelative } from '../lib/format'
import type { DocumentRow, Template, TemplateField } from '../lib/types'

export default function Editor() {
  const route = useRoute<any>()
  const nav = useNavigation<any>()
  const { c, subscriber, access } = useApp()

  const [doc, setDoc] = useState<DocumentRow | null>(null)
  const [tpl, setTpl] = useState<Template | null>(null)
  const [data, setData] = useState<Record<string, any>>({})
  const [title, setTitle] = useState('')
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [saving, setSaving] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const [savedAt, setSavedAt] = useState<Date | null>(null)
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const [exporting, setExporting] = useState(false)
  const [improving, setImproving] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const dirty = useRef(false)
  const timer = useRef<any>(null)
  const latest = useRef({ data, title })
  latest.current = { data, title }

  useEffect(() => {
    let alive = true
    ;(async () => {
      const { data: d, error } = await supabase.from('documents').select('*').eq('id', route.params?.id).maybeSingle()
      if (!alive) return
      if (error || !d) { setErr('لم نجد هذا الملفّ'); setLoading(false); return }
      const { data: t } = await supabase.from('templates').select('*').eq('id', (d as any).template_id).maybeSingle()
      if (!alive) return
      setDoc(d as DocumentRow); setTpl((t as Template) || null)
      setData((d as any).data || {}); setTitle((d as any).title || '')
      nav.setOptions({ title: (d as any).title || 'الملفّ' })
      setLoading(false)
    })()
    return () => { alive = false; clearTimeout(timer.current) }
  }, [route.params?.id])

  const persist = useCallback(async () => {
    if (!route.params?.id || !dirty.current) return
    setSaving('saving')
    const { error } = await supabase.from('documents')
      .update({ data: latest.current.data, title: latest.current.title })
      .eq('id', route.params.id)
    if (error) { setSaving('error'); return }
    dirty.current = false
    setSaving('saved'); setSavedAt(new Date())
  }, [route.params?.id])

  const markDirty = useCallback(() => {
    dirty.current = true
    setSaving('idle')
    clearTimeout(timer.current)
    timer.current = setTimeout(persist, 1400)
  }, [persist])

  const setField = (key: string, v: any) => { setData((d) => ({ ...d, [key]: v })); markDirty() }

  const sections = useMemo(() => fieldSections(tpl?.fields || []), [tpl])
  const total = tpl?.fields?.length || 0
  const done = useMemo(() => filledCount(tpl?.fields || [], data), [tpl, data])
  const readOnly = access !== 'trial' && access !== 'active'

  const exportPdf = async () => {
    if (!tpl) return
    setExporting(true); setNotice(null)
    try {
      await persist()
      const html = paperHtml(tpl, data, {
        title,
        schoolName: subscriber?.name,
        educationDept: subscriber?.education_dept,
        academicYear: subscriber?.academic_year,
        semester: subscriber?.semester,
        watermark: access === 'trial' ? 'نسخة تجريبية — مِداد' : null,
      })
      const { uri } = await Print.printToFileAsync({ html, base64: false })
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: 'application/pdf', dialogTitle: title, UTI: 'com.adobe.pdf' })
      } else {
        await Print.printAsync({ uri })
      }
    } catch (e: any) {
      setNotice(e?.message || 'تعذّر التصدير — حاول مرّة أخرى')
    } finally { setExporting(false) }
  }

  const improve = async (f: TemplateField) => {
    const value = String(data[f.key] ?? '').trim()
    if (!value) { setNotice('اكتب نصًّا أوّلًا ثمّ اطلب التحسين.'); return }
    setImproving(f.key); setNotice(null)
    try {
      const res = await callFunction<{ text: string }>('ai-improve', {
        text: value, tone: 'formal', field_label: f.label,
        document_id: doc?.id, field_key: f.key,
      })
      setField(f.key, res.text)
      setNotice('استُبدل النصّ بالصياغة المحسّنة.')
    } catch (e: any) {
      setNotice(e?.message || 'تعذّر التحسين — نصّك سليم كما هو.')
    } finally { setImproving(null) }
  }

  if (loading) return <Loading />
  if (err || !doc || !tpl) return <ErrorView message={err || undefined} />

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
      <ScrollView contentContainerStyle={{ padding: SPACE.s4, paddingBottom: 120, gap: SPACE.s4 }}
        keyboardShouldPersistTaps="handled">

        <Input label="اسم الملفّ" value={title}
          onChangeText={(v) => { setTitle(v); markDirty() }}
          onBlur={persist} editable={!readOnly} />

        <Card style={{ gap: 10 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T size={12.5} weight="600" color={c.text2}>{done} من {total} حقلًا</T>
            <Badge label={done === total && total > 0 ? 'مكتمل' : 'قيد الملء'}
              tone={done === total && total > 0 ? 'success' : 'neutral'} />
          </Row>
          <Progress value={done} max={total || 1} />
          <T size={11.5} color={c.text3}>
            {saving === 'saving' ? 'جارٍ الحفظ…'
              : saving === 'error' ? 'تعذّر الحفظ — سنحاول مرّة أخرى'
              : savedAt ? `حُفظ ${fmtRelative(savedAt)}` : 'الحفظ تلقائيّ'}
          </T>
        </Card>

        {readOnly && <Alert tone="warn">انتهى اشتراكك — يمكنك القراءة والتصدير، ولا يُحفظ أيّ تعديل.</Alert>}
        {notice && <Alert tone="primary">{notice}</Alert>}

        {sections.map((sec) => {
          const isOpen = open[sec.name] !== false
          return (
            <Card key={sec.name} style={{ padding: 0, overflow: 'hidden' }}>
              <Pressable
                onPress={() => setOpen((o) => ({ ...o, [sec.name]: !isOpen }))}
                style={{
                  backgroundColor: c.sunken, paddingHorizontal: SPACE.s4, paddingVertical: 14,
                  flexDirection: 'row-reverse', alignItems: 'center', justifyContent: 'space-between',
                }}>
                <T size={14} weight="700">{sec.name}</T>
                <Row gap={7}>
                  <T size={12} color={c.text3}>
                    {sec.fields.filter((f) => filledCount([f], data)).length}/{sec.fields.length}
                  </T>
                  <View style={{ transform: [{ rotate: isOpen ? '180deg' : '0deg' }] }}>
                    <IcChevronDown size={15} color={c.text3} />
                  </View>
                </Row>
              </Pressable>
              {isOpen && (
                <View style={{ padding: SPACE.s4, gap: SPACE.s4 }}>
                  {sec.fields.map((f) => (
                    <FieldRow key={f.key} field={f} value={data[f.key]} readOnly={readOnly}
                      onChange={(v) => setField(f.key, v)}
                      onImprove={f.type === 'textarea' ? () => improve(f) : undefined}
                      improving={improving === f.key} />
                  ))}
                </View>
              )}
            </Card>
          )
        })}
      </ScrollView>

      <View style={{
        position: 'absolute', bottom: 0, left: 0, right: 0, flexDirection: 'row-reverse',
        gap: 10, padding: SPACE.s4, backgroundColor: c.card,
        borderTopWidth: 1, borderTopColor: c.border,
      }}>
        <Button label="حفظ" variant="secondary" style={{ flex: 1 }}
          onPress={() => { dirty.current = true; persist() }} />
        <Button label="تصدير PDF" variant="primary" style={{ flex: 1 }}
          icon={<IcDownload size={16} color={c.onPrimary} />}
          loading={exporting} onPress={exportPdf} />
      </View>
    </KeyboardAvoidingView>
  )
}

function FieldRow({ field, value, onChange, onImprove, improving, readOnly }: {
  field: TemplateField; value: any; onChange: (v: any) => void
  onImprove?: () => void; improving?: boolean; readOnly?: boolean
}) {
  const { c } = useApp()

  if (field.type === 'table') {
    const cols = field.columns || []
    const rows: any[] = Array.isArray(value) ? value : []
    return (
      <View style={{ gap: 10 }}>
        <T size={12} weight="600" color={c.text2}>{field.label}</T>
        {rows.length === 0 && <T size={12} color={c.text3}>لا صفوف بعد — أضف الصفّ الأوّل.</T>}
        {rows.map((r, i) => (
          <View key={i} style={{
            borderWidth: 1, borderColor: c.border, borderRadius: RADIUS.md,
            padding: SPACE.s3, gap: SPACE.s3, backgroundColor: c.sunken,
          }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <T size={12} weight="700" color={c.text2}>الصفّ {i + 1}</T>
              {!readOnly && (
                <Button label="حذف" variant="danger" small icon={<IcTrash size={13} color={c.danger} />}
                  onPress={() => onChange(rows.filter((_, ri) => ri !== i))} />
              )}
            </Row>
            {cols.map((col) => (
              <Input key={col.key} label={col.label} editable={!readOnly}
                value={String(r?.[col.key] ?? '')}
                keyboardType={col.type === 'number' ? 'numeric' : 'default'}
                onChangeText={(v) => onChange(rows.map((x, xi) => (xi === i ? { ...x, [col.key]: v } : x)))} />
            ))}
          </View>
        ))}
        {!readOnly && (
          <Button label="أضف صفًّا" variant="soft" small icon={<IcPlus size={14} color={c.primarySoftFg} />}
            onPress={() => onChange([...rows, emptyRow(cols)])} style={{ alignSelf: 'flex-start' }} />
        )}
      </View>
    )
  }

  if (field.type === 'select' || field.type === 'radio') {
    return (
      <View style={{ gap: 8 }}>
        <T size={12} weight="600" color={c.text2}>
          {field.label}{field.required ? ' *' : ''}
        </T>
        <View style={{ flexDirection: 'row-reverse', flexWrap: 'wrap', gap: 8 }}>
          {(field.options || []).map((o) => {
            const on = value === o
            return (
              <Pressable key={o} disabled={readOnly} onPress={() => onChange(o)}
                style={{
                  backgroundColor: on ? c.primary : c.card, borderColor: on ? c.primary : c.border,
                  borderWidth: 1, borderRadius: RADIUS.pill, paddingHorizontal: 14,
                  paddingVertical: 9, minHeight: 40, justifyContent: 'center',
                }}>
                <T size={12.5} weight="600" color={on ? c.onPrimary : c.text2}>{o}</T>
              </Pressable>
            )
          })}
        </View>
        {field.help ? <T size={11.5} color={c.text3}>{field.help}</T> : null}
      </View>
    )
  }

  if (field.type === 'textarea') {
    return (
      <View style={{ gap: 8 }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <T size={12} weight="600" color={c.text2}>
            {field.label}{field.required ? ' *' : ''}
          </T>
          {onImprove && !readOnly && (
            <Button label="حسّن" variant="soft" small onPress={onImprove} loading={improving}
              icon={<IcSpark size={14} color={c.primarySoftFg} />} />
          )}
        </Row>
        <Input value={String(value ?? '')} onChangeText={onChange} multiline numberOfLines={4}
          editable={!readOnly} placeholder={field.placeholder} help={field.help}
          style={{ minHeight: 108, textAlignVertical: 'top', paddingTop: 12 }} />
      </View>
    )
  }

  return (
    <Input
      label={field.label + (field.required ? ' *' : '')}
      help={field.help}
      value={String(value ?? '')}
      onChangeText={onChange}
      editable={!readOnly}
      placeholder={field.placeholder || (field.type === 'date' ? 'YYYY-MM-DD' : undefined)}
      keyboardType={field.type === 'number' ? 'numeric' : 'default'}
      style={field.type === 'number' || field.type === 'date'
        ? { textAlign: 'left', writingDirection: 'ltr' } : undefined}
    />
  )
}
