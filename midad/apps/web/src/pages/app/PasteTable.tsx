import React, { useEffect, useRef, useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { readClipboard, type GridTable } from '../../lib/tableGrid'
import { Alert, Button, Field, Input, Modal, Select } from '../../ui/kit'
import { IcTable, IcCheck } from '../../ui/icons'

/**
 * جدولٌ يدخل مِداد باللصق — بابٌ ثانٍ بلا تثبيت شيء.
 *
 * إضافةُ كروم تقرأ صفحة نور المفتوحة، وهي أسرع طريقٍ لمن ثبّتها. لكنّها
 * تحتاج تثبيتًا، ولا تعمل على الجوّال، وتنكسر إن غيّرت الوزارة شكل
 * الصفحة — ويقف المعلّم بلا حيلة.
 *
 * فهذا بابٌ لا يحتاج إلّا ما في كلّ متصفّح: يُظلّل المعلّم الجدول في نور
 * وينسخه، ويلصقه هنا.
 *
 *     ما دام يرى الجدول، فهو ينسخه.
 *
 * والسرّ أنّ نسخ جدولٍ من صفحةٍ لا ينسخ نصًّا: المتصفّح يضع في الحافظة
 * `text/html` فيه بنيةُ الجدول كاملةً — صفوفُه وأعمدتُه وخلاياه المدمَجة.
 * فيُقرأ بالمنطق نفسه الذي تقرأ به الإضافة، لا بمنطقٍ ثانٍ يخالفه.
 *
 * ولا يقتصر على جدولٍ بعينه: لا عدد أعمدةٍ مفروض، ولا أسماء، ولا `thead`
 * مشترط. أيُّ جدولٍ في نور أو مدرستي أو غيرهما.
 */
export default function PasteTable({ onClose, onSaved }: {
  onClose: () => void
  onSaved: () => void
}) {
  const { subscriber, profile, toast } = useApp()
  const [found, setFound] = useState<GridTable[]>([])
  const [pick, setPick] = useState(0)
  const [title, setTitle] = useState('')
  const [source, setSource] = useState<'noor' | 'madrasati'>('noor')
  const [saving, setSaving] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const drop = useRef<HTMLDivElement>(null)

  /* المؤشّر في منطقة اللصق فور الفتح: المعلّم نسخ الجدول قبل أن يفتح هذه
     النافذة، وأوّل ما سيفعله `Ctrl+V` — فلا يُطلب منه أن يضغط أوّلًا. */
  useEffect(() => { drop.current?.focus() }, [])

  const take = (data: DataTransfer | null) => {
    setErr(null)
    const tables = readClipboard(data)
    if (!tables.length) {
      setErr('لم نجد جدولًا فيما لُصق. ظلّل الجدول في نور من أوّل عمودٍ '
        + 'إلى آخر صفّ، ثمّ انسخه بـ Ctrl+C وأعد اللصق هنا.')
      return
    }
    setFound(tables)
    setPick(0)
    setTitle(tables[0].title || '')
  }

  const save = async () => {
    const t = found[pick]
    if (!t || !subscriber || !profile) return
    setSaving(true); setErr(null)
    const { error } = await supabase.from('noor_tables').insert({
      subscriber_id: subscriber.id,
      owner_id: profile.id,
      title: (title.trim() || t.title || 'جدول ملصوق').slice(0, 160),
      columns: t.columns,
      rows: t.rows,
      row_count: t.rowCount,
      source,
      source_url: null,     // لم يأتِ من صفحةٍ نعرف عنوانها — جاء من الحافظة
    })
    setSaving(false)
    if (error) { setErr(error.message); return }
    toast('حُفظ الجدول')
    onSaved()
  }

  const t = found[pick]

  return (
    <Modal
      open onClose={onClose} title="الصق جدولًا من نور أو مدرستي" wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
          <Button variant="primary" onClick={save} block loading={saving} disabled={!t}>
            احفظ الجدول
          </Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 14 }}>
        {!found.length && (
          <div
            ref={drop}
            className="mdd-drop mdd-paste"
            tabIndex={0}
            role="textbox"
            aria-label="الصق الجدول هنا"
            onPaste={(e) => { e.preventDefault(); take(e.clipboardData) }}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => { e.preventDefault(); take(e.dataTransfer) }}
          >
            <IcTable size={30} />
            <b>الصق هنا</b>
            <span>
              افتح الجدول في نور، ظلّله بالفأرة، وانسخه بـ <kbd>Ctrl</kbd>+<kbd>C</kbd>.
              ثمّ اضغط هنا والصقه بـ <kbd>Ctrl</kbd>+<kbd>V</kbd>.
            </span>
            <span>بلا تثبيت شيء، ومن أيّ جهاز — ومِداد لا يدخل نور ولا يعرف كلمة مرورك.</span>
          </div>
        )}

        {err && <Alert tone="danger">{err}</Alert>}

        {t && (
          <>
            <div className="mdd-imp-stats">
              <span><b>{t.colCount}</b> عمودًا</span>
              <span><b>{t.rowCount}</b> صفًّا</span>
              {found.length > 1 && <span><b>{found.length}</b> جداول في اللصق</span>}
              <span className="ok"><IcCheck size={13} />قُرئ الجدول</span>
            </div>

            {/* لصقةٌ واحدةٌ قد تحمل أكثر من جدول: صفحة نور فيها جدولُ
                بياناتٍ وجدولُ تلخيصٍ تحته، فيختار المعلّم أيَّهما أراد. */}
            {found.length > 1 && (
              <Field label="أيّ جدول؟">
                <Select value={String(pick)} onChange={(e) => {
                  const i = Number(e.target.value)
                  setPick(i); setTitle(found[i].title || '')
                }}>
                  {found.map((x, i) => (
                    <option key={i} value={i}>
                      {x.title || `جدول ${i + 1}`} — {x.colCount}×{x.rowCount}
                    </option>
                  ))}
                </Select>
              </Field>
            )}

            <Field label="اسم الجدول">
              <Input value={title} onChange={(e) => setTitle(e.target.value)}
                placeholder="مثال: كشف طلاب ثاني/أ" />
            </Field>

            <Field label="من أين؟">
              <Select value={source} onChange={(e) => setSource(e.target.value as any)}>
                <option value="noor">نظام نور</option>
                <option value="madrasati">مدرستي</option>
              </Select>
            </Field>

            <div className="mdd-imp-prev">
              <span className="mdd-imp-lab">
                المعاينة — أوّل عشرة صفوف من {t.rowCount}
              </span>
              <div className="mdd-imp-prev-body">
                <table className="mdd-table">
                  <thead>
                    <tr>{t.columns.map((c, i) => <th key={i}>{c}</th>)}</tr>
                  </thead>
                  <tbody>
                    {t.rows.slice(0, 10).map((r, i) => (
                      <tr key={i}>{r.map((v, j) => <td key={j}>{v}</td>)}</tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <Button variant="ghost" auto onClick={() => { setFound([]); setErr(null) }}>
              الصق جدولًا آخر
            </Button>
          </>
        )}
      </div>
    </Modal>
  )
}
