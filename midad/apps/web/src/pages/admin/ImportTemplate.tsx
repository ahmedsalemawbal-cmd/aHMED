import React, { useRef, useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { importPdf, type ImportResult } from '../../lib/importPdf'
import type { TemplateFolder } from '../../lib/types'
import { Alert, Button, Field, Input, Modal, Select } from '../../ui/kit'
import { IcPage, IcCheck, IcAlert, IcSpinner } from '../../ui/icons'

/**
 * استيراد قالبٍ من ملفّ PDF.
 *
 * الاستخراج يجري في متصفّح المالك: الملفّ لا يُرفع إلى خادمٍ ليُقرأ، وأصله
 * وحده يُحفظ في دلوٍ خاصٍّ بالمشرف للرجوع والمقارنة.
 */

function slugify(name: string): string {
  const map: Record<string, string> = {
    ا: 'a', أ: 'a', إ: 'i', آ: 'a', ب: 'b', ت: 't', ث: 'th', ج: 'j', ح: 'h',
    خ: 'kh', د: 'd', ذ: 'dh', ر: 'r', ز: 'z', س: 's', ش: 'sh', ص: 's', ض: 'd',
    ط: 't', ظ: 'z', ع: 'a', غ: 'gh', ف: 'f', ق: 'q', ك: 'k', ل: 'l', م: 'm',
    ن: 'n', ه: 'h', و: 'w', ي: 'y', ى: 'a', ة: 'h', ء: '', ؤ: 'w', ئ: 'y',
  }
  const out: string[] = []
  for (const ch of (name || '').normalize('NFKC')) {
    if (map[ch] !== undefined) out.push(map[ch])
    else if (/[a-z0-9]/i.test(ch)) out.push(ch.toLowerCase())
    else if (/[\s\-_/]/.test(ch)) out.push('-')
  }
  return out.join('').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 56) || 'template'
}

export default function ImportTemplate({ folders, onClose, onDone }: {
  folders: TemplateFolder[]
  onClose: () => void
  onDone: (id?: string) => void
}) {
  const { toast } = useApp()
  const fileRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [res, setRes] = useState<ImportResult | null>(null)
  const [title, setTitle] = useState('')
  const [folder, setFolder] = useState(folders[0]?.id ?? '')
  const [reading, setReading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const pick = async (f: File) => {
    setErr(null); setRes(null); setFile(f)
    if (f.size > 25 * 1024 * 1024) { setErr('الملفّ أكبر من ٢٥ ميغابايت'); return }
    setReading(true)
    try {
      const r = await importPdf(f)
      setRes(r)
      setTitle(r.title)
    } catch (e: any) {
      setErr(e?.message || 'تعذّرت قراءة الملفّ — تأكّد أنّه PDF سليم')
    } finally { setReading(false) }
  }

  const save = async () => {
    if (!res || !file) return
    const name = title.trim() || res.title
    setSaving(true); setErr(null)
    try {
      const slug = `${slugify(name)}-${Date.now().toString(36).slice(-4)}`

      // الأصل يُحفظ للرجوع — دلوٌ خاصٌّ لا يراه إلّا المشرف
      const path = `${slug}.pdf`
      const up = await supabase.storage.from('template-sources')
        .upload(path, file, { contentType: 'application/pdf', upsert: true })
      if (up.error) throw new Error('تعذّر حفظ الأصل: ' + up.error.message)

      const { data, error } = await supabase.from('templates').insert({
        slug,
        title: name,
        category_key: 'general',
        description: null,
        kind: 'doc',
        folder_id: folder || null,
        content_html: res.html,
        page: {
          size: 'A4',
          orientation: res.landscape ? 'landscape' : 'portrait',
          margins: { top: 16, right: 14, bottom: 16, left: 14 },
        },
        source_pdf_path: path,
        source_pages: res.pages,
        status: 'draft',            // مسوّدةٌ دائمًا: تُراجع ثمّ تُنشر
        outputs: ['pdf', 'docx'],
      }).select('id').single()
      if (error) throw new Error(error.message)

      toast('استُورد القالب مسوّدةً — راجعه ثمّ انشره')
      onDone((data as any).id)
    } catch (e: any) {
      setErr(e?.message || 'تعذّر الحفظ')
    } finally { setSaving(false) }
  }

  return (
    <Modal
      open onClose={onClose} title="استيراد قالبٍ من ملفّ" wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
          <Button variant="primary" onClick={save} block loading={saving}
            disabled={!res || reading}>احفظ مسوّدةً</Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 14 }}>
        {!res && (
          <button type="button" className="mdd-drop" onClick={() => fileRef.current?.click()}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => {
              e.preventDefault()
              const f = e.dataTransfer.files?.[0]
              if (f) pick(f)
            }}>
            {reading ? <IcSpinner size={30} className="mdd-spin" /> : <IcPage size={30} />}
            <b>{reading ? 'جارٍ القراءة…' : 'اختر ملفّ PDF أو أسقطه هنا'}</b>
            <span>حتّى ٢٥ ميغابايت · تُقرأ في متصفّحك ولا تُرفع لتُقرأ</span>
          </button>
        )}
        <input ref={fileRef} type="file" accept="application/pdf,.pdf" hidden
          onChange={(e) => { const f = e.target.files?.[0]; if (f) pick(f) }} />

        {err && <Alert tone="danger">{err}</Alert>}

        {res && (
          <>
            <div className="mdd-imp-stats">
              <span><b>{res.pages}</b> صفحة</span>
              <span><b>{res.landscape ? 'أفقيّ' : 'رأسيّ'}</b> الاتّجاه</span>
              <span className={res.health.healthy ? 'ok' : 'bad'}>
                {res.health.healthy ? <IcCheck size={13} /> : <IcAlert size={13} />}
                {res.health.healthy ? 'طبقة النصّ سليمة' : 'طبقة النصّ فاسدة'}
              </span>
            </div>

            {res.warnings.map((w, i) => (
              <Alert key={i} tone={i === 0 && !res.health.healthy ? 'danger' : 'info'}>{w}</Alert>
            ))}

            <Field label="اسم القالب">
              <Input value={title} onChange={(e) => setTitle(e.target.value)}
                placeholder="مثال: تحليل نتيجة اختبار نافس" />
            </Field>

            <Field label="المجلّد">
              <Select value={folder} onChange={(e) => setFolder(e.target.value)}>
                <option value="">بلا مجلّد</option>
                {folders.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
              </Select>
            </Field>

            <div className="mdd-imp-prev">
              <span className="mdd-imp-lab">معاينة المتن المستخرَج</span>
              <div className="mdd-imp-prev-body" dangerouslySetInnerHTML={{ __html: res.html }} />
            </div>

            <Alert tone="warn">
              يُحفظ مسوّدةً لا يراها المعلّمون. افتحه في المحرّر، أعِد جدولة ما كان
              مؤطَّرًا، راجع الأرقام، ثمّ انشره.
            </Alert>
          </>
        )}
      </div>
    </Modal>
  )
}
