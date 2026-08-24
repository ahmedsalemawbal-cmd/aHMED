import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Modal } from '../../ui/kit'
import { IcPrint, IcDownload, IcCheck, IcSpinner } from '../../ui/icons'
import { buildRichDocx } from '../../lib/docx'
import { downloadPdf } from '../../lib/pdf'
import { download, safeFileName } from '../../lib/export'
import { useApp } from '../../lib/store'
import type { PageSetupRow } from '../../lib/types'

type Fmt = 'pdf' | 'docx'

/**
 * تصدير المستند — **تنزيلٌ مباشر** لا نافذة طباعة.
 *
 * كان الـPDF يفتح حوار الطباعة ويطلب من المستخدم اختيار «حفظ كـPDF» بنفسه.
 * وهذا خلطٌ بين فعلين: «اطبع» يفتح الحوار، و«نزّل» يُنزّل الملفّ في نقرة.
 * فصلناهما: هذه النافذة تُنزّل، وزرّ «اطبع» في الترويسة يطبع.
 */
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
  const [step, setStep] = useState('')
  const [withHeader, setWithHeader] = useState(true)

  const mark = watermark ?? (access === 'trial' ? 'نسخةٌ تجريبيّة — مِداد' : null)

  const formats: { key: Fmt; name: string; line: string }[] = [
    { key: 'pdf', name: 'PDF', line: 'يُنزَّل فورًا — بألوانه وصفحاته كما تراه' },
    { key: 'docx', name: 'وورد (DOCX)', line: 'يُنزَّل فورًا — لتعديلٍ إضافيّ على حاسوبك' },
  ]

  const run = async () => {
    setBusy(true)
    try {
      const name = safeFileName(title)
      if (fmt === 'pdf') {
        setStep('نُجهّز الصفحات…')
        await downloadPdf(html, `${name}.pdf`, page ?? null, {
          scale: 2,
          onProgress: (d, t) => setStep(`الصفحة ${d} من ${t}…`),
        })
        toast('نُزّل ملفّ PDF')
      } else {
        setStep('نُجهّز الصور…')
        const blob = await buildRichDocx(html, {
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
        download(blob, `${name}.docx`)
        toast('نُزّل ملفّ وورد')
      }
      onClose()
    } catch (e: any) {
      toast(e?.message || 'تعذّر التصدير', 'danger')
    } finally { setBusy(false); setStep('') }
  }

  return (
    <Modal
      open={open} onClose={onClose} title="تنزيل المستند" wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block disabled={busy}>إلغاء</Button>
          <Button variant="primary" onClick={run} loading={busy} block
            icon={busy ? <IcSpinner size={15} className="mdd-spin" /> : <IcDownload size={15} />}>
            {busy ? (step || 'جارٍ التجهيز…') : 'نزّل الآن'}
          </Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 10 }}>
        {formats.map((f) => (
          <button
            key={f.key} onClick={() => setFmt(f.key)} disabled={busy}
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

      {fmt === 'docx' && (
        <label className="mdd-check">
          <input type="checkbox" checked={withHeader} disabled={busy}
            onChange={(e) => setWithHeader(e.target.checked)} />
          <span>أدرج ترويسة المدرسة</span>
        </label>
      )}

      {fmt === 'pdf' && (
        <Alert tone="info">
          الملفّ صورةٌ عالية الدقّة لكلّ صفحة — فالتصميم والألوان والعربيّة تخرج
          كما تراها تمامًا. والنصّ فيه غير قابلٍ للتحديد؛ فإن أردتَ نصًّا يُحرَّر
          فنزّل الوورد.
        </Alert>
      )}

      {fmt === 'docx' && (
        <Alert tone="info">
          يخرج بتنسيقه: العناوين والجداول وألوان الخلايا والقوائم — قابلًا للتعديل
          في الوورد. وقد يختلف بعض التباعد عمّا تراه هنا، فالوورد لا يفهم كلّ ما
          يفهمه المتصفّح.
        </Alert>
      )}

      <p className="mdd-imp-note">
        وللطباعة على ورقٍ مباشرةً استعمل زرّ <b>«اطبع»</b> في الأعلى.
      </p>

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
