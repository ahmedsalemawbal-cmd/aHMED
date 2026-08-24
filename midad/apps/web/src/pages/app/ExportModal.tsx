import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Modal } from '../../ui/kit'
import { IcPrint, IcDownload, IcCheck } from '../../ui/icons'
import { buildRichDocx } from '../../lib/docx'
import { download, safeFileName } from '../../lib/export'
import { useApp } from '../../lib/store'
import type { PageSetupRow } from '../../lib/types'

type Fmt = 'pdf' | 'docx'

export default function ExportModal({ open, onClose, title, html, page, watermark }: {
  open: boolean
  onClose: () => void
  title: string
  html: string
  page?: PageSetupRow | null
  watermark?: string | null
}) {
  const { toast, subscriber, access } = useApp()
  const [fmt, setFmt] = useState<Fmt>('pdf')
  const [busy, setBusy] = useState(false)
  const [withHeader, setWithHeader] = useState(true)

  const mark = watermark ?? (access === 'trial' ? 'نسخةٌ تجريبيّة — مِداد' : null)

  const formats: { key: Fmt; name: string; line: string }[] = [
    { key: 'pdf', name: 'PDF', line: 'للطباعة والإرسال — الأدقّ تنسيقًا' },
    { key: 'docx', name: 'وورد (DOCX)', line: 'لتعديلٍ إضافيّ على حاسوبك' },
  ]

  const run = async () => {
    setBusy(true)
    try {
      if (fmt === 'pdf') {
        onClose()
        // ننتظر إغلاق النافذة قبل الطباعة، وإلّا طُبعت فوق الورقة
        setTimeout(() => window.print(), 140)
        return
      }
      const blob = buildRichDocx(html, {
        title,
        header: withHeader ? {
          school: subscriber?.name || '',
          dept: (subscriber as any)?.education_dept || '',
          year: (subscriber as any)?.academic_year || '',
          semester: (subscriber as any)?.semester || '',
        } : null,
        watermark: mark,
        page: page ? { orientation: page.orientation, margins: page.margins } : undefined,
      })
      download(blob, `${safeFileName(title)}.docx`)
      toast('جهّزنا الملفّ — تحقّق من تنزيلاتك')
      onClose()
    } catch (e: any) {
      toast(e?.message || 'تعذّر التصدير', 'danger')
    } finally { setBusy(false) }
  }

  return (
    <Modal
      open={open} onClose={onClose} title="تصدير المستند" wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
          <Button variant="primary" onClick={run} loading={busy} block
            icon={fmt === 'pdf' ? <IcPrint size={15} /> : <IcDownload size={15} />}>
            {fmt === 'pdf' ? 'افتح الطباعة' : 'نزّل الملفّ'}
          </Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 10 }}>
        {formats.map((f) => (
          <button
            key={f.key} onClick={() => setFmt(f.key)}
            className={'mdd-card mdd-card--action mdd-row' + (fmt === f.key ? ' mdd-card--selected' : '')}
            style={{ gap: 12, padding: 14 }}>
            <span style={{
              width: 34, height: 34, borderRadius: 9, display: 'grid', placeItems: 'center', flex: 'none',
              background: fmt === f.key ? 'var(--mdd-accent)' : 'var(--mdd-sunken)',
              color: fmt === f.key ? 'var(--mdd-on-accent)' : 'var(--mdd-text-3)',
            }}>{fmt === f.key ? <IcCheck size={16} /> : null}</span>
            <span style={{ minWidth: 0 }}>
              <span style={{ display: 'block', fontWeight: 700, fontSize: 14 }}>{f.name}</span>
              <span style={{ display: 'block', fontSize: 12, color: 'var(--mdd-text-3)', marginBlockStart: 2 }}>
                {f.line}
              </span>
            </span>
          </button>
        ))}
      </div>

      <label className="mdd-check">
        <input type="checkbox" checked={withHeader} onChange={(e) => setWithHeader(e.target.checked)} />
        <span>أدرج ترويسة المدرسة</span>
      </label>

      {fmt === 'pdf' && (
        <Alert tone="info">
          يفتح مِداد نافذة الطباعة — اختر «حفظ كـ PDF» وجهةً. الورقة مضبوطةٌ على A4
          بهوامشها الحقيقيّة، وما تراه في المحرّر هو ما يُطبع.
        </Alert>
      )}

      {fmt === 'docx' && (
        <Alert tone="info">
          يخرج الملفّ بتنسيقه كاملًا: العناوين والجداول والقوائم والألوان — قابلًا
          للتعديل في الوورد.
        </Alert>
      )}

      {mark && (
        <Alert tone="accent">
          ملفّاتك تخرج بعلامةٍ مائية أثناء التجربة.{' '}
          <Link to="/app/plans" style={{ textDecoration: 'underline', fontWeight: 700 }}>
            اشترك لتخرج نظيفة
          </Link>
        </Alert>
      )}
    </Modal>
  )
}
