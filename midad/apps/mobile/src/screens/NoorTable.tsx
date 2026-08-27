import React, { useEffect, useMemo, useState } from 'react'
import { ScrollView, View } from 'react-native'
import { useRoute } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Alert, Button, Card, Empty, ErrorView, Input, Loading, Row, T } from '../ui/kit'
import { IcPdf, IcWord, IcExcel } from '../ui/icons'
import { exportGrid, type GridFormat } from '../lib/exportGrid'
import { AppHeader } from '../ui/AppHeader'
import { SPACE, RADIUS, TYPE } from '../lib/theme'
import { fmtNum, fmtDate } from '../lib/format'
import type { NoorTable as NT } from '../lib/types'

const PAGE = 40

export default function NoorTable() {
  const route = useRoute<any>()
  const { c } = useApp()
  const [t, setT] = useState<NT | null>(null)
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [q, setQ] = useState('')
  const [limit, setLimit] = useState(PAGE)
  const [busy, setBusy] = useState<GridFormat | null>(null)
  const [xerr, setXerr] = useState<string | null>(null)

  useEffect(() => {
    let alive = true
    ;(async () => {
      const { data, error } = await supabase.from('noor_tables').select('*').eq('id', route.params?.id).maybeSingle()
      if (!alive) return
      if (error || !data) { setErr('لم نجد هذا الجدول'); setLoading(false); return }
      setT(data as NT); setLoading(false)
    })()
    return () => { alive = false }
  }, [route.params?.id])

  const rows = useMemo(() => {
    if (!t) return []
    const term = q.trim()
    if (!term) return t.rows || []
    return (t.rows || []).filter((r) => r.some((cell) => String(cell ?? '').includes(term)))
  }, [t, q])

  /**
   * التصديرُ يُصدّر **الجدول كلَّه** لا ما يُرى.
   *
   * والبحثُ فوق يُرشّح المعروض؛ فلو صدّرنا `rows` لخرج المعلّمُ بكشفٍ
   * ناقصٍ لا يدري أنّه ناقص — لأنّه كتب كلمةً في خانة البحث ونسيها.
   *
   *     ما يُصدَّر يُقال ما هو.
   */
  const send = async (format: GridFormat) => {
    if (!t) return
    setBusy(format); setXerr(null)
    try {
      await exportGrid(format, {
        title: t.title || 'جدول نور',
        subtitle: `من ${(t as any).source === 'madrasati' ? 'منصّة مدرستي' : 'منصّة نور'} · نُزِّل ${fmtDate(t.created_at)}`,
        columns: t.columns || [],
        rows: t.rows || [],
      })
    } catch (e: any) {
      setXerr(e?.message || 'تعذّر التصدير')
    } finally {
      setBusy(null)
    }
  }

  if (loading) return <><AppHeader title="جدول" back /><Loading /></>
  if (err || !t) return <><AppHeader title="جدول" back /><ErrorView message={err || undefined} /></>

  return (
    <>
    <AppHeader title={t.title} back
      subtitle={`${fmtNum(t.row_count)} صفًّا · ${fmtNum(t.columns?.length || 0)} أعمدة`} />
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ padding: SPACE.s4, gap: SPACE.s3, paddingBottom: SPACE.s8 }}>
      <T size={TYPE.body} color={c.text3}>نُزِّل {fmtDate(t.created_at)}</T>

      {/* التصدير أوّلًا: هو ما يُفتح الجدولُ لأجله غالبًا */}
      {xerr ? <Alert tone="danger">{xerr}</Alert> : null}
      <Card style={{ gap: 12 }}>
        <T size={TYPE.body} weight="700">صدّر الجدول</T>
        <Row gap={8}>
          <Button label="إكسل" style={{ flex: 1 }} loading={busy === 'xlsx'}
            icon={<IcExcel size={16} color={c.success} />} onPress={() => send('xlsx')} />
          <Button label="وورد" style={{ flex: 1 }} loading={busy === 'docx'}
            icon={<IcWord size={16} color={c.info} />} onPress={() => send('docx')} />
          <Button label="PDF" style={{ flex: 1 }} loading={busy === 'pdf'}
            icon={<IcPdf size={16} color={c.danger} />} onPress={() => send('pdf')} />
        </Row>
      </Card>

      <Input value={q} onChangeText={(v) => { setQ(v); setLimit(PAGE) }} placeholder="ابحث في كلّ الخلايا" />

      {rows.length === 0 ? (
        <Empty title="لا نتيجة لهذا البحث" line="جرّب كلمةً أقصر أو امسح البحث." />
      ) : (
        <>
          <T size={TYPE.body} color={c.text3}>{fmtNum(rows.length)} صفًّا مطابقًا</T>
          {/* الجداول تصير بطاقات على الجوّال — لا تمرير أفقيّ */}
          {rows.slice(0, limit).map((r, i) => (
            <Card key={i} style={{ gap: 8, padding: SPACE.s4 }}>
              {(t.columns || []).map((col, ci) => (
                <Row key={ci} style={{ justifyContent: 'space-between', alignItems: 'flex-start' }} gap={12}>
                  <T size={TYPE.small} weight="600" color={c.text3} style={{ flex: 1 }}>{col}</T>
                  <T size={TYPE.body} style={{ flex: 1.4 }}>{String(r[ci] ?? '—')}</T>
                </Row>
              ))}
            </Card>
          ))}
          {rows.length > limit && (
            <Button label={`حمّل المزيد (${fmtNum(rows.length - limit)})`} variant="soft"
              onPress={() => setLimit((n) => n + PAGE)} />
          )}
        </>
      )}
    </ScrollView>
    </>
  )
}
