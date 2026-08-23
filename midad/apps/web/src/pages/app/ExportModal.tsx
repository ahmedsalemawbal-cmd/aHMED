import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import type { Subscriber, Template } from '../../lib/types'
import { Alert, Button, Modal } from '../../ui/kit'
import { IcPrint, IcDownload, IcCheck } from '../../ui/icons'
import { buildDocx, buildXlsx, download, documentToSheet, safeFileName, renderPaperHtml } from '../../lib/export'
import { useApp } from '../../lib/store'

type Fmt = 'pdf' | 'docx' | 'xlsx'

export default function ExportModal({ open, onClose, template, data, title, subscriber, watermark }: {
  open: boolean; onClose: () => void
  template: Template; data: Record<string, any>; title: string
  subscriber: Subscriber | null; watermark: string | null
}) {
  const { toast } = useApp()
  const [fmt, setFmt] = useState<Fmt>('pdf')
  const [busy, setBusy] = useState(false)
  const [withHeader, setWithHeader] = useState(true)

  const sheet = documentToSheet(template, data)
  const formats: { key: Fmt; name: string; line: string; available: boolean }[] = [
    { key: 'pdf', name: 'PDF', line: 'للطباعة والإرسال — الأدقّ تنسيقًا', available: true },
    { key: 'docx', name: 'وورد (DOCX)', line: 'لتعديلٍ إضافيّ على حاسوبك', available: true },
    { key: 'xlsx', name: 'إكسل (XLSX)', line: 'للجداول والأرقام', available: !!sheet },
  ]

  const run = async () => {
    setBusy(true)
    try {
      const name = safeFileName(title)
      if (fmt === 'pdf') {
        onClose()
        setTimeout(() => window.print(), 120)
        return
      }
      if (fmt === 'docx') {
        const blob = buildDocx({
          schoolName: withHeader ? (subscriber?.name || '') : '',
          educationDept: withHeader ? (subscriber?.education_dept || '') : '',
          academicYear: withHeader ? (subscriber?.academic_year || '') : '',
          semester: withHeader ? (subscriber?.semester || '') : '',
          title, watermark,
        }, renderPaperHtml(template, data))
        download(blob, `${name}.docx`)
      }
      if (fmt === 'xlsx' && sheet) {
        download(buildXlsx(name, sheet.headers, sheet.rows), `${name}.xlsx`)
      }
      toast('جهّزنا الملفّ — تحقّق من تنزيلاتك')
      onClose()
    } catch (e: any) {
      toast(e?.message || 'تعذّر التصدير', 'danger')
    } finally { setBusy(false) }
  }

  return (
    <Modal open={open} onClose={onClose} title="تصدير الملفّ" wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
          <Button variant="primary" onClick={run} loading={busy} block
            icon={fmt === 'pdf' ? <IcPrint size={15} /> : <IcDownload size={15} />}>
            {fmt === 'pdf' ? 'افتح الطباعة' : 'تصدير'}
          </Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 10 }}>
        {formats.map((f) => (
          <button key={f.key} disabled={!f.available} onClick={() => setFmt(f.key)}
            className={'mdd-card mdd-card--action mdd-row' + (fmt === f.key ? ' mdd-card--selected' : '')}
            style={{ gap: 12, padding: 14, opacity: f.available ? 1 : .5 }}>
            <span style={{
              width: 34, height: 34, borderRadius: 9, display: 'grid', placeItems: 'center', flex: 'none',
              background: fmt === f.key ? 'var(--mdd-accent)' : 'var(--mdd-sunken)',
              color: fmt === f.key ? 'var(--mdd-on-accent)' : 'var(--mdd-text-3)',
            }}>{fmt === f.key ? <IcCheck size={16} /> : null}</span>
            <span style={{ minWidth: 0 }}>
              <span style={{ display: 'block', fontWeight: 700, fontSize: 14 }}>{f.name}</span>
              <span style={{ display: 'block', fontSize: 12, color: 'var(--mdd-text-3)', marginBlockStart: 2 }}>
                {f.available ? f.line : 'هذا القالب ليس جدوليًّا'}
              </span>
            </span>
          </button>
        ))}
      </div>

      {fmt !== 'xlsx' && (
        <label className="mdd-check">
          <input type="checkbox" checked={withHeader} onChange={(e) => setWithHeader(e.target.checked)} />
          <span>أدرج ترويسة المدرسة</span>
        </label>
      )}

      {fmt === 'pdf' && (
        <Alert tone="info">
          يفتح مِداد نافذة الطباعة — اختر «حفظ كـ PDF» وجهةً للطباعة. الورقة مضبوطة على A4 بهوامش حقيقية.
        </Alert>
      )}

      {watermark && (
        <Alert tone="accent">
          ملفّاتك تخرج بعلامةٍ مائية أثناء التجربة.{' '}
          <Link to="/app/plans" style={{ textDecoration: 'underline', fontWeight: 700 }}>اشترك لتخرج نظيفة</Link>
        </Alert>
      )}
    </Modal>
  )
}
