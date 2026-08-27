import React, { useCallback, useMemo, useState } from 'react'
import { Image, Linking, Pressable, ScrollView, TextInput, View } from 'react-native'
import { useRoute } from '@react-navigation/native'
import * as Print from 'expo-print'
import * as Sharing from 'expo-sharing'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { Alert as Note, Badge, Button, Card, Loading, Row, T } from '../ui/kit'
import { AppHeader } from '../ui/AppHeader'
import { RADIUS, SPACE, TYPE } from '../lib/theme'
import { IcTrash, IcDownload, IcCheck, IcExternal } from '../ui/icons'
import {
  editable, fromBlocks, moveBlock, removeBlock, setText, toBlocks, type Block,
} from '../lib/docBlocks'
import { SITE_URL } from '../lib/config'

/**
 * تحريرُ الملفّ بعد أن ركّبه الذكاء — ثلاثُ عمليّاتٍ لا محرّرٌ كامل.
 *
 * الموظّف يريد أن يحذف فقرةً لا تشبهه، ويرفع محورًا يراه أهمّ، ويصحّح
 * كلمةً كتبها النموذج. وهذه ثلاثٌ تُنجَز بأزرارٍ ظاهرة، لا بمحرّر نصٍّ
 * غنيٍّ يُبنى في React Native بشقّ الأنفس ثمّ لا يُشبه الوورد.
 *
 *     زرٌّ يُرى خيرٌ من إيماءةٍ تُخمَّن.
 *
 * وما وراء الثلاث — لونٌ، جدولٌ، خطٌّ — يُفتح في مِداد على المتصفّح،
 * وهناك المحرّرُ الكامل. ويُقال ذلك صراحةً لا يُترك ليُكتشف.
 */
export default function PortfolioResult() {
  const route = useRoute<any>()
  const { c } = useApp()
  const id: string = route.params?.id
  const title: string = route.params?.title || 'ملفّ الإنجاز'

  const [blocks, setBlocks] = useState<Block[]>(() => toBlocks(route.params?.html || ''))
  const [dirty, setDirty] = useState(false)
  const [saving, setSaving] = useState(false)
  const [note, setNote] = useState<string | null>(null)
  const [editing, setEditing] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)

  const pages = useMemo(() => {
    let n = 1
    return blocks.map((b, i) => (i > 0 && b.page !== blocks[i - 1].page ? ++n : n))
  }, [blocks])

  const apply = (next: Block[]) => { setBlocks(next); setDirty(true) }

  const save = useCallback(async () => {
    setSaving(true)
    try {
      const { error } = await supabase.from('documents')
        .update({ content_html: fromBlocks(blocks) }).eq('id', id)
      if (error) throw new Error(error.message)
      setDirty(false)
      setNote('حُفظ')
    } catch (e: any) {
      setNote(e?.message || 'تعذّر الحفظ')
    } finally {
      setSaving(false)
    }
  }, [blocks, id])

  /**
   * المعاينةُ تُبنى من الحالة الحاضرة لا من المحفوظ: الموظّف يعاين ليرى
   * أثرَ ما عدّله للتوّ، فلو عُرض المحفوظُ ظنّ أنّ تعديله ضاع.
   */
  const preview = async () => {
    setBusy(true)
    try {
      const html = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>@page{size:A4;margin:0}
body{margin:0;font-family:-apple-system,"Segoe UI",Roboto,"Noto Naskh Arabic",sans-serif;color:#111}
[data-page]{width:210mm;min-height:297mm;page-break-after:always;box-sizing:border-box}
img{max-width:100%}table{width:100%;border-collapse:collapse}
</style></head><body>${fromBlocks(blocks)}</body></html>`
      const { uri } = await Print.printToFileAsync({ html })
      if (await Sharing.isAvailableAsync()) await Sharing.shareAsync(uri, { mimeType: 'application/pdf' })
    } catch (e: any) {
      setNote(e?.message || 'تعذّرت المعاينة')
    } finally {
      setBusy(false)
    }
  }

  if (!id) return <><AppHeader title={title} back /><Loading /></>

  return (
    <>
      <AppHeader title={title} back subtitle={`${blocks.length} كتلة · ${pages[pages.length - 1] || 1} صفحة`} />

      <ScrollView contentContainerStyle={{ padding: SPACE.s5, paddingBottom: 120, gap: SPACE.s3 }}>
        {!!note && <Note tone={note === 'حُفظ' ? 'success' : 'danger'}>{note}</Note>}

        <Note tone="info">
          احذف ما لا يعجبك، وارفع ما تراه أهمّ، وصحّح النصّ بلمسه.
          وللألوان والجداول افتحه في مِداد على المتصفّح.
        </Note>

        {blocks.map((b, i) => (
          <View key={`${i}-${b.tag}`}
            style={{
              backgroundColor: c.card, borderRadius: RADIUS.md,
              borderWidth: 1, borderColor: editing === i ? c.primary : c.border,
              padding: SPACE.s3, gap: 8,
            }}>
            <Row gap={6}>
              <Badge label={LABEL[b.tag] || b.tag} tone={b.tag[0] === 'h' ? 'primary' : 'neutral'} />
              {i === 0 || blocks[i - 1].page !== b.page
                ? <Badge label={`صفحة ${pages[i]}`} tone="info" /> : null}
              <View style={{ flex: 1 }} />
              <Ctl label="↑" dim={i === 0} onPress={() => i > 0 && apply(moveBlock(blocks, i, -1))} />
              <Ctl label="↓" dim={i === blocks.length - 1}
                onPress={() => i < blocks.length - 1 && apply(moveBlock(blocks, i, 1))} />
              <Ctl icon={<IcTrash size={15} color={c.danger} />}
                onPress={() => { apply(removeBlock(blocks, i)); setEditing(null) }} />
            </Row>

            {b.images > 0 ? (
              <Row gap={6}>
                {srcOf(b.html).slice(0, 4).map((u, k) => (
                  <Image key={k} source={{ uri: u }}
                    style={{ width: 58, height: 58, borderRadius: 6, backgroundColor: c.sunken }} />
                ))}
                <T size={TYPE.micro} color={c.text3}>{b.images} صورة</T>
              </Row>
            ) : editing === i && editable(b) ? (
              <TextInput
                value={b.text} multiline autoFocus
                onChangeText={(t) => apply(blocks.map((x, k) => (k === i ? setText(x, t) : x)))}
                onBlur={() => setEditing(null)}
                style={{
                  color: c.text, fontSize: TYPE.small, lineHeight: 24, minHeight: 60,
                  textAlign: 'right', padding: 0,
                }} />
            ) : (
              <Pressable onPress={() => editable(b) && setEditing(i)}>
                <T size={b.tag[0] === 'h' ? TYPE.body : TYPE.small}
                  weight={b.tag[0] === 'h' ? '700' : '400'}
                  color={b.text ? c.text : c.text3}>
                  {b.text || '(بلا نصّ)'}
                </T>
                {editable(b) && (
                  <T size={TYPE.micro} color={c.text3}>المسه لتعدّله</T>
                )}
              </Pressable>
            )}
          </View>
        ))}

        {blocks.length === 0 && (
          <Card><T color={c.text3}>حذفتَ كلَّ شيء. ارجع وركّب الملفّ من جديد.</T></Card>
        )}
      </ScrollView>

      {/* الشريط السفليّ ثابت: الحفظُ لا يُبحث عنه بالتمرير */}
      <View style={{
        position: 'absolute', left: 0, right: 0, bottom: 0,
        backgroundColor: c.card, borderTopWidth: 1, borderTopColor: c.border,
        padding: SPACE.s4, gap: 8, flexDirection: 'row',
      }}>
        <Button label={dirty ? 'احفظ' : 'محفوظ'} variant={dirty ? 'primary' : 'secondary'}
          disabled={!dirty} loading={saving} style={{ flex: 1 }}
          icon={dirty ? undefined : <IcCheck size={16} color={c.text3} />}
          onPress={save} />
        <Button label="PDF" loading={busy} style={{ flex: 1 }}
          icon={<IcDownload size={16} color={c.text} />} onPress={preview} />
        <Button label="المتصفّح" variant="ghost"
          icon={<IcExternal size={16} color={c.primary} />}
          onPress={() => Linking.openURL(`${SITE_URL}/#/app/doc/${id}`)} />
      </View>
    </>
  )
}

const LABEL: Record<string, string> = {
  h1: 'عنوانٌ رئيس', h2: 'عنوان', h3: 'عنوانٌ فرعيّ',
  p: 'فقرة', ul: 'قائمة', ol: 'قائمةٌ مرقّمة', table: 'صور', div: 'كتلة',
}

/** روابطُ الصور في كتلة — للمعاينة المصغّرة وحدها. */
function srcOf(html: string): string[] {
  return [...html.matchAll(/<img[^>]+src="([^"]+)"/gi)].map((m) => m[1])
}

function Ctl({ label, icon, onPress, dim }: {
  label?: string; icon?: React.ReactNode; onPress: () => void; dim?: boolean
}) {
  const { c } = useApp()
  return (
    <Pressable onPress={onPress} disabled={dim} hitSlop={6}
      style={{
        width: 32, height: 32, borderRadius: 8, alignItems: 'center', justifyContent: 'center',
        backgroundColor: c.sunken, opacity: dim ? 0.35 : 1,
      }}>
      {icon || <T size={16} weight="700" color={c.text2}>{label}</T>}
    </Pressable>
  )
}
