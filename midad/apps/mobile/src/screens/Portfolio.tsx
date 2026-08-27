import React, { useCallback, useMemo, useState } from 'react'
import { Image, Modal, Pressable, RefreshControl, ScrollView, View } from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import {
  Alert as Note, Badge, Button, Card, Divider, Empty, ErrorView, Input, Loading, Row, T,
} from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { RADIUS, SPACE, TYPE } from '../lib/theme'
import { IcCamera, IcImage, IcPlus, IcTrash, IcSpark, IcClose } from '../ui/icons'
import {
  KIND_AR, addItem, assemble, compose, fetchItems, pick, removeItem,
  saveDocument, signedUrls,
  type PortfolioItem, type PortfolioKind, type Picked,
} from '../lib/portfolio'
import type { Template } from '../lib/types'

/**
 * ملفّ الإنجاز — يُلتقط طوال العام، ويُركَّب في آخره.
 *
 * والشاشةُ شاشتان في واحدة: سجلٌّ يُضاف إليه في ثوانٍ طوال تسعة أشهر،
 * وزرٌّ يُضغط مرّةً واحدةً في يونيو. فالسجلّ هو الأصل والزرُّ ثمرتُه —
 * ولذلك يقع الالتقاط في أعلى الشاشة والتركيبُ أسفلها.
 */
export default function Portfolio() {
  const nav = useNavigation<any>()
  const { c, subscriber, profile } = useApp()
  const year = (subscriber as any)?.academic_year || ''

  const [items, setItems] = useState<PortfolioItem[]>([])
  const [urls, setUrls] = useState<Record<string, string>>({})
  const [tpl, setTpl] = useState<Template | null>(null)
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [note, setNote] = useState<string | null>(null)
  const [draft, setDraft] = useState<Draft | null>(null)
  const [viewing, setViewing] = useState<PortfolioItem | null>(null)
  const [made, setMade] = useState<{ id: string; title: string; updated_at: string }[]>([])

  const load = useCallback(async () => {
    if (!profile) return
    setErr(null)
    try {
      const rows = await fetchItems(profile.id, year)
      setItems(rows)
      setUrls(await signedUrls(rows.map((r) => r.file_path || '')))

      /* قالبُ الإنجاز: الأخصُّ يفوز. قالبٌ أُسنِد لدور المستخدم أولى من
         قالبٍ عامٍّ لكلّ الأدوار — وإلّا لغلب العامُّ الخاصَّ بترتيب
         الجلب وحده، وهو ترتيبٌ لا معنى له. */
      const { data: tpls } = await supabase.from('templates')
        .select('id,title,role_keys,is_portfolio,status')
        .eq('is_portfolio', true).eq('status', 'published')
      const mine = (tpls || []) as any[]
      const exact = mine.find((t) => (t.role_keys || []).includes(profile.role_key))
      const generic = mine.find((t) => !(t.role_keys || []).length)
      const chosen = (exact || generic || null) as Template | null
      setTpl(chosen)

      /* الملفّاتُ المركَّبة — تُقرأ هنا لأنّها لا تُقرأ في مكانٍ آخر.
         حُذف تبويب «ملفّاتي» من التطبيق (الملفّات في الموقع)، ولو تُرك
         ملفُّ الإنجاز بلا موضعٍ في التطبيق لضاع بعد إغلاقه: يُركَّب
         مرّةً، ويُفتح مرّةً، ثمّ لا سبيل إليه.

             ما يُنشئه التطبيق يبقى في التطبيق. */
      if (chosen) {
        const { data: docs } = await supabase.from('documents')
          .select('id,title,updated_at')
          .eq('owner_id', profile.id).eq('template_id', chosen.id)
          .order('updated_at', { ascending: false }).limit(6)
        setMade((docs || []) as any[])
      }
    } catch (e: any) {
      setErr(e?.message || 'تعذّر تحميل سجلّ الإنجاز')
    } finally {
      setLoading(false)
    }
  }, [profile, year])

  useFocusEffect(useCallback(() => { load() }, [load]))

  const counts = useMemo(() => {
    const n: Record<string, number> = { photo: 0, certificate: 0, file: 0, text: 0 }
    for (const i of items) n[i.kind] = (n[i.kind] || 0) + 1
    return n
  }, [items])

  /* ═════════════════ الالتقاط ═════════════════ */

  const capture = async (source: 'camera' | 'library', kind: PortfolioKind) => {
    try {
      const photo = await pick(source)
      if (!photo) return
      setDraft({ kind, photo, title: '', note: '', axis: '' })
    } catch (e: any) {
      setNote(e?.message || 'تعذّرت قراءة الصورة')
    }
  }

  const save = async (d: Draft) => {
    if (!subscriber || !profile) return
    if (!d.photo && !d.title.trim() && !d.note.trim()) {
      setNote('اكتب عنوانًا أو ملاحظةً على الأقلّ')
      return
    }
    setBusy(true)
    try {
      await addItem(subscriber.id, profile.id, year, {
        kind: d.kind,
        title: d.title,
        note: d.note,
        axis: d.axis,
        happened_on: new Date().toISOString().slice(0, 10),
        photo: d.photo,
      })
      setDraft(null)
      await load()
      setNote('أُضيف إلى سجلّك')
    } catch (e: any) {
      setNote(e?.message || 'تعذّرت الإضافة')
    } finally {
      setBusy(false)
    }
  }

  const drop = async (it: PortfolioItem) => {
    setBusy(true)
    try {
      await removeItem(it)
      setViewing(null)
      await load()
    } catch (e: any) {
      setNote(e?.message || 'تعذّر الحذف')
    } finally {
      setBusy(false)
    }
  }

  /* ═════════════════ التركيب ═════════════════ */

  const build = async () => {
    if (!tpl || !subscriber || !profile) return
    setBusy(true)
    setNote('يقرأ الذكاءُ شواهدَك ويرتّبها…')
    try {
      const plan = await compose(tpl.id, year)
      /* الروابطُ تُطلب من جديدٍ لا تُؤخذ من الحالة: قد مضت ساعةٌ على
         فتح الشاشة، والموقّعُ المنتهي يُخرج ملفًّا بصورٍ مكسورة. */
      const fresh = await signedUrls(items.map((i) => i.file_path || ''))
      const html = assemble(plan, items, fresh, {
        title: tpl.title,
        owner: profile.full_name,
        role: (profile as any).role_name_ar || '',
        school: (subscriber as any).school_name || (subscriber as any).name || '',
        year,
      })
      const id = await saveDocument(subscriber.id, profile.id, tpl.id, `${tpl.title} — ${year}`, html)
      setNote(null)
      nav.navigate('PortfolioResult', { id, html, title: tpl.title })
    } catch (e: any) {
      setNote(e?.message || 'تعذّر التركيب')
    } finally {
      setBusy(false)
    }
  }

  /* ═════════════════ العرض ═════════════════ */

  if (loading) return <><AppHeader title="ملفّ الإنجاز" /><Loading label="يقرأ سجلّك…" /></>
  if (err) return <><AppHeader title="ملفّ الإنجاز" /><ErrorView message={err} onRetry={load} /></>

  return (
    <>
      <AppHeader title="ملفّ الإنجاز" subtitle={year ? `العام ${year}` : undefined} />

      <ScrollView
        contentContainerStyle={{ padding: SPACE.s5, paddingBottom: 40, gap: SPACE.s4 }}
        refreshControl={<RefreshControl refreshing={false} onRefresh={load} tintColor={c.primary} />}>

        {!!note && <Note tone="info">{note}</Note>}

        {/* ═ الالتقاط أوّلًا: هو ما يُفتح التطبيقُ لأجله ═ */}
        <Card style={{ gap: SPACE.s3 }}>
          <T size={TYPE.body} weight="700">أضِف شاهدًا</T>
          <T size={TYPE.small} color={c.text3}>
            صوّر الفعاليّة الآن — وأنت في الساحة. والذكاءُ يرتّبها آخرَ العام.
          </T>
          <Row gap={8}>
            <Button label="كاميرا" icon={<IcCamera size={17} color={c.onPrimary} />}
              variant="primary" style={{ flex: 1 }}
              onPress={() => capture('camera', 'photo')} />
            <Button label="من الصور" icon={<IcImage size={17} color={c.text} />}
              style={{ flex: 1 }} onPress={() => capture('library', 'photo')} />
          </Row>
          <Row gap={8}>
            <Button label="شهادة" small style={{ flex: 1 }}
              onPress={() => capture('library', 'certificate')} />
            <Button label="ملاحظة" small icon={<IcPlus size={15} color={c.text} />} style={{ flex: 1 }}
              onPress={() => setDraft({ kind: 'text', photo: null, title: '', note: '', axis: '' })} />
          </Row>
        </Card>

        {/* ═ ما جُمع ═ */}
        <Row gap={8}>
          <Stat n={items.length} label="شاهدًا" tone={c.primary} />
          <Stat n={counts.photo} label="صورة" />
          <Stat n={counts.certificate} label="شهادة" />
          <Stat n={counts.text} label="ملاحظة" />
        </Row>

        {items.length === 0 ? (
          <Empty title="سجلُّك فارغ"
            line="أضِف أوّل شاهدٍ من الأزرار أعلاه. لا يلزمك عنوانٌ ولا محور — صوّر وامضِ." />
        ) : (
          <View style={{ gap: SPACE.s3 }}>
            <T size={TYPE.body} weight="700">سجلّي</T>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {items.map((it) => (
                <Pressable key={it.id} onPress={() => setViewing(it)}
                  style={{
                    width: '31.5%', aspectRatio: 1, borderRadius: RADIUS.md,
                    overflow: 'hidden', backgroundColor: c.sunken,
                    borderWidth: 1, borderColor: c.border,
                  }}>
                  {it.file_path && urls[it.file_path] ? (
                    <Image source={{ uri: urls[it.file_path] }}
                      style={{ width: '100%', height: '100%' }} resizeMode="cover" />
                  ) : (
                    <View style={{ flex: 1, padding: 8, justifyContent: 'center' }}>
                      <T size={TYPE.micro} color={c.text3} numberOfLines={4}>
                        {it.title || it.note || KIND_AR[it.kind]}
                      </T>
                    </View>
                  )}
                </Pressable>
              ))}
            </View>
          </View>
        )}

        <Divider />

        {/* ═ التركيب — مرّةً في العام ═ */}
        <Card style={{ gap: SPACE.s3 }}>
          <Row gap={8}>
            <IcSpark size={19} color={c.primary} />
            <T size={TYPE.body} weight="700">اصنع ملفّ الإنجاز</T>
          </Row>
          {!tpl ? (
            <Note tone="warn">
              لا قالبَ إنجازٍ لفئتك بعد. يحدّده مالك المنصّة من: القوالب ← «ملفّ إنجاز».
            </Note>
          ) : items.length === 0 ? (
            <T size={TYPE.small} color={c.text3}>أضِف شواهدك أوّلًا، ثمّ عُد إلى هنا.</T>
          ) : (
            <>
              <T size={TYPE.small} color={c.text3}>
                يقرأ الذكاءُ شواهدك الـ{items.length}، ويوزّعها على محاور «{tpl.title}»،
                ويكتب لكلّ محورٍ فقرته. ثمّ تُعدّله كما تشاء.
              </T>
              <Button label={made.length ? 'ركّب ملفًّا جديدًا' : 'ركّب الملفّ'}
                variant="primary" loading={busy} onPress={build} />
            </>
          )}

          {made.length > 0 && (
            <>
              <Divider />
              <T size={TYPE.small} weight="700" color={c.text2}>ما ركّبتَه</T>
              {made.map((d) => (
                <Pressable key={d.id}
                  onPress={() => nav.navigate('PortfolioResult', { id: d.id, title: d.title })}
                  style={({ pressed }) => ({
                    flexDirection: 'row-reverse', alignItems: 'center', gap: 12,
                    paddingVertical: 12, opacity: pressed ? 0.6 : 1,
                  })}>
                  <IcSpark size={16} color={c.primary} />
                  <T size={TYPE.body} numberOfLines={1} style={{ flex: 1 }}>{d.title}</T>
                  <T size={TYPE.micro} color={c.text3}>{d.updated_at.slice(0, 10)}</T>
                </Pressable>
              ))}
            </>
          )}
        </Card>
      </ScrollView>

      {/* ═ نافذةُ التفاصيل قبل الحفظ ═ */}
      <DraftSheet draft={draft} busy={busy} onClose={() => setDraft(null)} onSave={save} />

      {/* ═ معاينةُ شاهدٍ وحذفُه ═ */}
      <Modal visible={!!viewing} transparent animationType="fade" onRequestClose={() => setViewing(null)}>
        <Pressable onPress={() => setViewing(null)}
          style={{ flex: 1, backgroundColor: '#000c', justifyContent: 'center', padding: SPACE.s5 }}>
          <Pressable onPress={() => {}}>
            <Card style={{ gap: SPACE.s3 }}>
              {viewing?.file_path && urls[viewing.file_path] && (
                <Image source={{ uri: urls[viewing.file_path] }}
                  style={{ width: '100%', height: 240, borderRadius: RADIUS.md }} resizeMode="contain" />
              )}
              <Badge label={KIND_AR[viewing?.kind || 'photo']} />
              {!!viewing?.title && <T size={TYPE.body} weight="700">{viewing.title}</T>}
              {!!viewing?.note && <T size={TYPE.small} color={c.text2}>{viewing.note}</T>}
              {!!viewing?.axis && <T size={TYPE.micro} color={c.text3}>المحور: {viewing.axis}</T>}
              <T size={TYPE.micro} color={c.text3}>{viewing?.happened_on}</T>
              <Row gap={8}>
                <Button label="إغلاق" style={{ flex: 1 }} onPress={() => setViewing(null)} />
                <Button label="احذف" variant="danger" style={{ flex: 1 }} loading={busy}
                  icon={<IcTrash size={16} color={c.danger} />}
                  onPress={() => viewing && drop(viewing)} />
              </Row>
            </Card>
          </Pressable>
        </Pressable>
      </Modal>
    </>
  )
}

/* ═══════════════════════ أجزاءٌ صغيرة ═══════════════════════ */

interface Draft {
  kind: PortfolioKind
  photo: Picked | null
  title: string
  note: string
  axis: string
}

function Stat({ n, label, tone }: { n: number; label: string; tone?: string }) {
  const { c } = useApp()
  return (
    <View style={{
      flex: 1, backgroundColor: c.card, borderRadius: RADIUS.md,
      paddingVertical: 12, alignItems: 'center', borderWidth: 1, borderColor: c.border,
    }}>
      <T size={TYPE.h3} weight="700" color={tone || c.text}>{String(n)}</T>
      <T size={TYPE.micro} color={c.text3}>{label}</T>
    </View>
  )
}

/**
 * ورقةُ التفاصيل — وكلُّ حقولها اختياريّة عمدًا.
 *
 * الموظّف يصوّر وهو واقفٌ في طابور الصباح. فلو طُلب منه محورٌ وعنوانٌ
 * وتاريخٌ قبل أن يُحفظ الشاهد، لأغلق التطبيقَ ولم يعد.
 *
 *     ما يُطلب عند الالتقاط يُدفع ثمنُه التقاطًا لا يقع.
 */
function DraftSheet({ draft, busy, onClose, onSave }: {
  draft: Draft | null
  busy: boolean
  onClose: () => void
  onSave: (d: Draft) => void
}) {
  const { c } = useApp()
  const [d, setD] = useState<Draft | null>(draft)
  React.useEffect(() => { setD(draft) }, [draft])
  if (!d) return null

  return (
    <Modal visible transparent animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: '#0008', justifyContent: 'flex-end' }}>
        <View style={{
          backgroundColor: c.card, borderTopLeftRadius: 20, borderTopRightRadius: 20,
          padding: SPACE.s5, gap: SPACE.s3, maxHeight: '88%',
        }}>
          <Row gap={8}>
            <T size={TYPE.body} weight="700" style={{ flex: 1 }}>
              {d.photo ? 'شاهدٌ جديد' : 'ملاحظةٌ جديدة'}
            </T>
            <Pressable onPress={onClose} hitSlop={10}><IcClose size={20} color={c.text3} /></Pressable>
          </Row>

          <ScrollView contentContainerStyle={{ gap: SPACE.s3 }} keyboardShouldPersistTaps="handled">
            {d.photo && (
              <Image source={{ uri: `data:image/jpeg;base64,${d.photo.base64}` }}
                style={{ width: '100%', height: 170, borderRadius: RADIUS.md }} resizeMode="cover" />
            )}
            <Input label="العنوان (اختياريّ)" value={d.title}
              placeholder="مثال: يوم المهنة"
              onChangeText={(t) => setD({ ...d, title: t })} />
            <Input label="وصفٌ موجز (اختياريّ)" value={d.note}
              placeholder="ماذا حدث؟ ودورك فيه."
              multiline numberOfLines={3}
              onChangeText={(t) => setD({ ...d, note: t })} />
            <Input label="المحور (اختياريّ)" value={d.axis}
              help="اتركه فارغًا ودع الذكاء يختار محوره."
              onChangeText={(t) => setD({ ...d, axis: t })} />
          </ScrollView>

          <Button label="احفظ في سجلّي" variant="primary" loading={busy}
            onPress={() => onSave(d)} />
        </View>
      </View>
    </Modal>
  )
}
