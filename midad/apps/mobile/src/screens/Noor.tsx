import React, { useCallback, useState } from 'react'
import { FlatList, Linking, Modal, Pressable, RefreshControl, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase, callFunction } from '../lib/supabase'
import { Alert, Badge, Button, Card, Divider, Loading, Row, T } from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { SPACE, RADIUS, TYPE } from '../lib/theme'
import { fmtNum, fmtDate, fmtRelative } from '../lib/format'
import { IcTable, IcShare, IcChevron, IcClose, IcPdf, IcWord, IcExcel } from '../ui/icons'
import { exportGrid, type GridFormat } from '../lib/exportGrid'
import { SITE_URL } from '../lib/config'
import type { NoorTable } from '../lib/types'

/**
 * جداولُ نور — ما وصل، لا كيف يصل.
 *
 * كانت الشاشة تعرض خطواتِ الربط دائمًا ثمّ تستبدلها بالقائمة حين يصل
 * أوّلُ جدول. فمن ربط جهازَه ولم يُرسل بعد يرى تعليماتٍ يعرفها، ولا يرى
 * أنّ سجلَّه فارغٌ فعلًا — والفراغُ خبرٌ يجب أن يُقال.
 *
 *     الشاشةُ تقول ما عندك أوّلًا، ثمّ كيف تزيد عليه.
 *
 * فصار الترتيب: عنوانٌ بعدد ما وصل، ثمّ الجداول، ثمّ الربطُ مطويًّا في
 * آخرها. ولكلّ جدولٍ **تصديرٌ ثلاثيّ ومشاركة**: المعلّم يُطلب منه الكشفُ
 * بصيغةٍ بعينها، ولا يملك حاسبًا في اللحظة التي يُطلب فيها.
 */
export default function Noor() {
  const { c, subscriber } = useApp()
  const nav = useNavigation<any>()
  const [tables, setTables] = useState<NoorTable[]>([])
  const [key, setKey] = useState<{ key: string; expires_at: string } | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [setup, setSetup] = useState(false)
  const [picking, setPicking] = useState<NoorTable | null>(null)

  const load = useCallback(async () => {
    if (!subscriber?.id) { setLoading(false); return }
    const [t, k] = await Promise.all([
      supabase.from('noor_tables').select('*').eq('subscriber_id', subscriber.id)
        .order('created_at', { ascending: false }),
      supabase.from('link_keys').select('key,expires_at').is('revoked_at', null)
        .gt('expires_at', new Date().toISOString())
        .order('created_at', { ascending: false }).limit(1),
    ])
    setTables((t.data || []) as NoorTable[])
    setKey(((k.data && k.data[0]) as any) || null)
    setLoading(false)
  }, [subscriber?.id])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const createKey = async () => {
    setBusy(true); setErr(null)
    try { await callFunction('noor', { action: 'create_key' }); await load() }
    catch (e: any) { setErr(e?.message || 'تعذّر إنشاء المفتاح') }
    finally { setBusy(false) }
  }

  /**
   * التصدير — والصفوفُ تُجلب الآن لا عند فتح الشاشة.
   *
   * قائمةُ الجداول تحمل عناوينَها وأعدادَها وحدها؛ ومتونُها قد تكون
   * ألوفَ صفوف. فجلبُها كلِّها ليُصدَّر واحدٌ منها يجعل فتحَ الشاشة
   * انتظارًا لما لن يُستعمل.
   */
  const doExport = async (t: NoorTable, format: GridFormat) => {
    setPicking(null); setBusy(true); setErr(null)
    try {
      const { data, error } = await supabase.from('noor_tables')
        .select('title,columns,rows,created_at,source').eq('id', t.id).single()
      if (error || !data) throw new Error('تعذّر جلب الجدول')
      const row = data as any
      await exportGrid(format, {
        title: row.title || 'جدول نور',
        subtitle: `من ${row.source === 'madrasati' ? 'منصّة مدرستي' : 'منصّة نور'} · نُزِّل ${fmtDate(row.created_at)}`,
        columns: row.columns || [],
        rows: row.rows || [],
      })
    } catch (e: any) {
      setErr(e?.message || 'تعذّر التصدير')
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <><AppHeader title="جداول نور" back /><Loading /></>

  return (
    <>
      <AppHeader title="جداول نور" back
        subtitle={tables.length ? `${fmtNum(tables.length)} جدولًا` : undefined} />

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
          <View style={{ gap: SPACE.s3 }}>
            {err ? <Alert tone="danger">{err}</Alert> : null}
            {tables.length === 0 ? (
              /* الفراغُ يُقال، ولا يُترك ليُستنتج من غياب شيء. */
              <Card style={{ alignItems: 'center', gap: 12, paddingVertical: SPACE.s6 }}>
                <View style={{
                  width: 56, height: 56, borderRadius: RADIUS.lg,
                  backgroundColor: c.tint.amber + '1F',
                  alignItems: 'center', justifyContent: 'center',
                }}>
                  <IcTable size={26} color={c.tint.amber} />
                </View>
                <T size={TYPE.h3} weight="700">لا جداول حاليًّا</T>
                <T size={TYPE.body} color={c.text3} style={{ textAlign: 'center' }}>
                  أرسِل أيَّ كشفٍ من نور أو مدرستي عبر إضافة المتصفّح، فيظهر هنا
                  ويُصدَّر إكسل أو وورد أو PDF.
                </T>
                <Button label="كيف أربط نور؟" variant="soft" onPress={() => setSetup(true)} />
              </Card>
            ) : null}
          </View>
        }

        renderItem={({ item }) => (
          <Card style={{ gap: 0, padding: 0, overflow: 'hidden' }}>
            <Pressable onPress={() => nav.navigate('NoorTable', { id: item.id })}
              style={{ padding: SPACE.s4, gap: 8 }}>
              <Row gap={10}>
                <View style={{
                  width: 38, height: 38, borderRadius: RADIUS.sm,
                  backgroundColor: c.tint.amber + '1F',
                  alignItems: 'center', justifyContent: 'center',
                }}>
                  <IcTable size={18} color={c.tint.amber} />
                </View>
                <View style={{ flex: 1, gap: 4 }}>
                  <T size={TYPE.bodyLg} weight="700" numberOfLines={2}>{item.title}</T>
                  <T size={TYPE.small} color={c.text3}>
                    {fmtNum(item.row_count)} صفًّا · {fmtNum(item.columns?.length || 0)} أعمدة · {fmtRelative(item.created_at)}
                  </T>
                </View>
                <IcChevron size={15} color={c.text3} />
              </Row>
            </Pressable>

            <Divider />

            {/* صفُّ التصدير — ثلاثُ صيغٍ ومشاركة، ظاهرةٌ لا مخبوءةٌ في قائمة */}
            <Row gap={0} style={{ paddingHorizontal: 4, paddingVertical: 2 }}>
              <Act icon={<IcExcel size={17} color={c.success} />} label="إكسل"
                onPress={() => doExport(item, 'xlsx')} />
              <Act icon={<IcWord size={17} color={c.info} />} label="وورد"
                onPress={() => doExport(item, 'docx')} />
              <Act icon={<IcPdf size={17} color={c.danger} />} label="PDF"
                onPress={() => doExport(item, 'pdf')} />
              <Act icon={<IcShare size={17} color={c.text2} />} label="شارِك"
                onPress={() => setPicking(item)} />
            </Row>
          </Card>
        )}

        ListFooterComponent={
          tables.length > 0 ? (
            <Pressable onPress={() => setSetup(true)} style={{ paddingVertical: SPACE.s4 }}>
              <T size={TYPE.body} color={c.primary} style={{ textAlign: 'center' }}>
                اربط جهازًا آخر أو جدّد المفتاح
              </T>
            </Pressable>
          ) : null
        }
      />

      {busy ? (
        <View style={{
          position: 'absolute', left: 0, right: 0, bottom: 0,
          backgroundColor: c.card, padding: SPACE.s4,
          borderTopWidth: 1, borderTopColor: c.border,
        }}>
          <T size={TYPE.body} color={c.text2} style={{ textAlign: 'center' }}>يُجهَّز الملفّ…</T>
        </View>
      ) : null}

      {/* المشاركة تسأل عن الصيغة: «شارِك» بلا صيغةٍ لا معنى له */}
      <Modal visible={!!picking} transparent animationType="slide"
        onRequestClose={() => setPicking(null)}>
        <Pressable onPress={() => setPicking(null)}
          style={{ flex: 1, backgroundColor: '#0008', justifyContent: 'flex-end' }}>
          <Pressable onPress={() => {}} style={{
            backgroundColor: c.card, borderTopLeftRadius: 20, borderTopRightRadius: 20,
            padding: SPACE.s5, gap: SPACE.s3,
          }}>
            <T size={TYPE.h3} weight="700">بأيّ صيغةٍ تشاركه؟</T>
            <T size={TYPE.small} color={c.text3} numberOfLines={2}>{picking?.title}</T>
            {(['xlsx', 'docx', 'pdf'] as GridFormat[]).map((f) => (
              <Button key={f} label={FORMAT_AR[f]} variant={f === 'xlsx' ? 'primary' : 'secondary'}
                onPress={() => picking && doExport(picking, f)} />
            ))}
          </Pressable>
        </Pressable>
      </Modal>

      {/* خطواتُ الربط — تُفتح عند الطلب، ولا تحتلّ الشاشة كلّ مرّة */}
      <Modal visible={setup} animationType="slide" onRequestClose={() => setSetup(false)}>
        <View style={{ flex: 1, backgroundColor: c.bg }}>
          <Row gap={8} style={{ padding: SPACE.s4, paddingTop: SPACE.s7 }}>
            <T size={TYPE.h2} weight="700" style={{ flex: 1 }}>اربط نور في ثلاث خطوات</T>
            <Pressable onPress={() => setSetup(false)} hitSlop={10}>
              <IcClose size={22} color={c.text3} />
            </Pressable>
          </Row>
          <View style={{ padding: SPACE.s4, gap: SPACE.s3 }}>
            {err ? <Alert tone="danger">{err}</Alert> : null}

            <Step n={1} title="ثبّت إضافة المتصفّح"
              line="الإضافة تعمل على كروم وإيدج في الحاسوب. نزّلها من صفحة «جداول نور» في موقع مِداد."
              action={<Button label="افتح صفحة الإضافة" variant="soft" small
                onPress={() => Linking.openURL(`${SITE_URL}/#/app/noor/key`)} />} />

            <Step n={2} title="انسخ مفتاح الربط"
              line={key
                ? `مفتاحك جاهز وصالح حتى ${fmtDate(key.expires_at)}. الصقه في الإضافة مرّةً واحدة.`
                : 'أنشئ مفتاحًا الآن، ثمّ الصقه في الإضافة. المفتاح صالح ٩٠ يومًا ويمكن إلغاؤه في أيّ لحظة.'}
              action={key
                ? <View style={{
                  backgroundColor: c.sunken, borderRadius: RADIUS.sm, padding: 12,
                  borderWidth: 1, borderColor: c.border,
                }}>
                  <T size={TYPE.bodyLg} weight="700"
                    style={{ textAlign: 'left', writingDirection: 'ltr' }}>{key.key}</T>
                </View>
                : <Button label="أنشئ مفتاحًا" variant="primary" small onPress={createKey} loading={busy} />} />

            <Step n={3} title="افتح نور واضغط «أرسل»"
              line="افتح أيّ كشفٍ في منصّة نور من حاسوبك، اضغط أيقونة مِداد في شريط المتصفّح، ثمّ «أرسل إلى مِداد». يصلك الجدول هنا فورًا." />

            <Alert tone="info">
              الإضافة لا تعمل إلّا حين تضغطها بنفسك، ولا تقرأ كلمة مرور نور،
              ويمكنك إلغاء المفتاح في أيّ لحظة.
            </Alert>
          </View>
        </View>
      </Modal>
    </>
  )
}

const FORMAT_AR: Record<GridFormat, string> = {
  xlsx: 'إكسل — للجداول والحساب',
  docx: 'وورد — قابلٌ للتعديل',
  pdf: 'PDF — للطباعة',
}

/** زرُّ إجراءٍ في صفٍّ واحد — متساوي العرض فلا يتفاوت الصفّ. */
function Act({ icon, label, onPress }: {
  icon: React.ReactNode; label: string; onPress: () => void
}) {
  const { c } = useApp()
  return (
    <Pressable onPress={onPress}
      style={({ pressed }) => ({
        flex: 1, alignItems: 'center', gap: 4,
        paddingVertical: 12, borderRadius: RADIUS.sm,
        backgroundColor: pressed ? c.sunken : 'transparent',
      })}>
      {icon}
      <T size={TYPE.micro} weight="600" color={c.text2}>{label}</T>
    </Pressable>
  )
}

function Step({ n, title, line, action }: {
  n: number; title: string; line: string; action?: React.ReactNode
}) {
  const { c } = useApp()
  return (
    <Card style={{ gap: 12 }}>
      <Row gap={10}>
        <View style={{
          width: 28, height: 28, borderRadius: 10, backgroundColor: c.primary,
          alignItems: 'center', justifyContent: 'center',
        }}>
          <T size={TYPE.bodyLg} weight="700" color={c.onPrimary}>{n}</T>
        </View>
        <T size={TYPE.lead} weight="700" style={{ flex: 1 }}>{title}</T>
      </Row>
      <T size={TYPE.body} color={c.text2}>{line}</T>
      {action}
    </Card>
  )
}
