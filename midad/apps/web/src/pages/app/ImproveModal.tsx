import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import type { TemplateField } from '../../lib/types'
import { Alert, Button, Modal, Chips } from '../../ui/kit'
import { IcSpinner, IcSpark } from '../../ui/icons'
import { callFunction } from '../../lib/supabase'
import { useApp } from '../../lib/store'

type Tone = 'formal' | 'simple' | 'shorter' | 'longer'
const TONES: { key: Tone; label: string }[] = [
  { key: 'formal', label: 'رسميّ' },
  { key: 'simple', label: 'مبسَّط' },
  { key: 'shorter', label: 'أقصر' },
  { key: 'longer', label: 'أطول' },
]

export default function ImproveModal({ field, value, documentId, onClose, onAccept }: {
  field: TemplateField; value: string; documentId: string
  onClose: () => void; onAccept: (text: string) => void
}) {
  const { plan, subscriber } = useApp()
  const [tone, setTone] = useState<Tone>('formal')
  const [out, setOut] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [quota, setQuota] = useState<{ used: number; limit: number } | null>(null)

  const run = async (t: Tone) => {
    if (!value.trim()) { setErr('اكتب نصًّا أوّلًا ثمّ اطلب التحسين.'); return }
    setBusy(true); setErr(null); setOut('')
    try {
      const res = await callFunction<{ text: string; used: number; limit: number }>('ai-improve', {
        text: value, tone: t, field_label: field.label, document_id: documentId,
      })
      setOut(res.text)
      setQuota({ used: res.used, limit: res.limit })
    } catch (e: any) {
      setErr(e?.message || 'تعذّر التوليد — نصّك سليم كما هو.')
    } finally { setBusy(false) }
  }

  useEffect(() => { run(tone) /* eslint-disable-next-line */ }, [])

  const quotaOver = quota && quota.limit > 0 && quota.used >= quota.limit

  return (
    <Modal open onClose={onClose} title={`تحسين: ${field.label}`} wide
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>ألغِ</Button>
          <Button variant="primary" block disabled={!out || busy} onClick={() => onAccept(out)}>استبدل النصّ</Button>
        </>
      }>
      <Chips items={TONES} value={tone} onChange={(t) => { setTone(t); run(t) }} />

      <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
        <div className="mdd-col" style={{ gap: 6 }}>
          <span className="mdd-field__label">نصّك</span>
          <div className="mdd-card" style={{ background: 'var(--mdd-sunken)', fontSize: 13, lineHeight: 1.85, whiteSpace: 'pre-wrap', minHeight: 130 }}>
            {value || <span className="mdd-muted">— لا نصّ —</span>}
          </div>
        </div>
        <div className="mdd-col" style={{ gap: 6 }}>
          <span className="mdd-field__label">المقترَح</span>
          <div className="mdd-card" style={{ fontSize: 13, lineHeight: 1.85, whiteSpace: 'pre-wrap', minHeight: 130, borderColor: 'var(--mdd-accent)' }}>
            {busy
              ? <span className="mdd-row" style={{ color: 'var(--mdd-text-3)', gap: 8 }}><IcSpinner size={15} /> جارٍ التوليد…</span>
              : out || <span className="mdd-muted">—</span>}
          </div>
        </div>
      </div>

      {err && <Alert tone="danger">{err}</Alert>}

      {quotaOver ? (
        <Alert tone="warn">
          استهلكتَ حصّة التحسين لهذا الشهر.{' '}
          <Link to="/app/plans" style={{ textDecoration: 'underline', fontWeight: 700 }}>ارفع باقتك</Link>
        </Alert>
      ) : quota ? (
        <p className="mdd-field__help">
          استعملت <span className="mdd-num">{quota.used}</span> من <span className="mdd-num">{quota.limit}</span> هذا الشهر.
        </p>
      ) : null}

      <div className="mdd-row" style={{ gap: 8 }}>
        <Button size="sm" auto icon={<IcSpark size={13} />} onClick={() => run(tone)} loading={busy}>جرّب مرّة أخرى</Button>
        <span className="mdd-field__help">لا يُستبدل نصّك إلّا بضغطك على «استبدل النصّ».</span>
      </div>
    </Modal>
  )
}
