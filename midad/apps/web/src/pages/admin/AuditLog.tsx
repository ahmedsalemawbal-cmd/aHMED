import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useAsync } from '../../lib/hooks'
import { fmtBoth, fmtRelative, fmtShort } from '../../lib/format'
import type { AuditEntry } from '../../lib/types'
import {
  Badge, Button, Card, EmptyState, ErrorState, PageHead, Select, SkeletonRows,
} from '../../ui/kit'
import {
  IcDownload, IcUser, IcCard, IcInvoice, IcTeam, IcTable, IcKey, IcShield, IcClock,
} from '../../ui/icons'
import { buildXlsx, download } from '../../lib/export'

const EVENT_AR: Record<string, string> = {
  signup: 'تسجيل جديد', invoice_created: 'إنشاء فاتورة', transfer_submitted: 'إبلاغ بتحويل',
  payment_confirmed: 'تأكيد دفع', payment_rejected: 'رفض تحويل', subscriber_status: 'تغيير حالة مشترك',
  trial_extended: 'تمديد تجربة', plan_changed: 'تغيير باقة', member_added: 'إضافة عضو',
  member_status: 'حالة عضو', member_password_reset: 'إعادة كلمة مرور',
  noor_key_created: 'إنشاء مفتاح نور', noor_key_revoked: 'إلغاء مفتاح نور', noor_ingest: 'تنزيل جدول نور',
}

function iconFor(t: string) {
  if (t === 'signup') return <IcUser size={16} />
  if (t.startsWith('payment') || t === 'transfer_submitted') return <IcCard size={16} />
  if (t === 'invoice_created') return <IcInvoice size={16} />
  if (t.startsWith('member')) return <IcTeam size={16} />
  if (t.startsWith('noor_key')) return <IcKey size={16} />
  if (t === 'noor_ingest') return <IcTable size={16} />
  if (t === 'subscriber_status' || t === 'plan_changed' || t === 'trial_extended') return <IcShield size={16} />
  return <IcClock size={16} />
}

const RANGES = [
  { key: '7', label: 'آخر 7 أيّام' },
  { key: '30', label: 'آخر 30 يومًا' },
  { key: '90', label: 'آخر 90 يومًا' },
  { key: 'all', label: 'كلّ الوقت' },
]

export default function AuditLog() {
  const [sub, setSub] = useState('')
  const [type, setType] = useState('')
  const [range, setRange] = useState('30')
  const [open, setOpen] = useState<string | null>(null)

  const { data, loading, error, reload } = useAsync(async () => {
    let q = supabase.from('audit_log').select('*').order('created_at', { ascending: false }).limit(500)
    if (range !== 'all') {
      q = q.gte('created_at', new Date(Date.now() - Number(range) * 86400000).toISOString())
    }
    const [log, subs] = await Promise.all([q, supabase.from('subscribers').select('id,name')])
    if (log.error) throw new Error(log.error.message)
    return {
      rows: (log.data || []) as AuditEntry[],
      names: new Map<string, string>((subs.data || []).map((s: any) => [s.id, s.name])),
    }
  }, [range])

  const rows = data?.rows || []
  const types = useMemo(() => [...new Set(rows.map((r) => r.event_type))].sort(), [rows])

  const shown = useMemo(() => {
    let out = rows
    if (sub) out = out.filter((r) => r.subscriber_id === sub)
    if (type) out = out.filter((r) => r.event_type === type)
    return out
  }, [rows, sub, type])

  const exportLog = () => {
    const headers = ['الوقت', 'الحدث', 'الوصف', 'المشترك', 'الفاعل']
    const body = shown.map((r) => [
      fmtShort(r.created_at), EVENT_AR[r.event_type] || r.event_type, r.message_ar,
      r.subscriber_id ? (data?.names.get(r.subscriber_id) || '') : '', r.actor_name || '',
    ])
    download(buildXlsx('سجل النظام', headers, body), 'سجل-النظام.xlsx')
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="سجلّ النظام" sub="من فعل ماذا ومتى — وهو ما يُرجَع إليه عند الخلاف."
        actions={<Button auto icon={<IcDownload size={15} />} onClick={exportLog} disabled={!shown.length}>تصدير</Button>}
      />

      <Card className="mdd-grid mdd-grid--3" style={{ gap: 10, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Select value={sub} onChange={(e) => setSub(e.target.value)} aria-label="المشترك">
          <option value="">كلّ المشتركين</option>
          {[...(data?.names.entries() || [])].map(([id, name]) => <option key={id} value={id}>{name}</option>)}
        </Select>
        <Select value={type} onChange={(e) => setType(e.target.value)} aria-label="نوع الحدث">
          <option value="">كلّ الأحداث</option>
          {types.map((t) => <option key={t} value={t}>{EVENT_AR[t] || t}</option>)}
        </Select>
        <Select value={range} onChange={(e) => setRange(e.target.value)} aria-label="المدى الزمنيّ">
          {RANGES.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
        </Select>
      </Card>

      {loading ? <SkeletonRows n={8} /> : shown.length === 0 ? (
        <EmptyState title="لا أحداث في هذا المدى" line="وسّع المدى الزمنيّ أو امسح الفلاتر." />
      ) : (
        <Card className="mdd-col" style={{ gap: 0 }}>
          {shown.map((r) => (
            <div key={r.id} style={{ borderBlockEnd: '1px solid var(--mdd-border)' }}>
              <button
                onClick={() => setOpen(open === r.id ? null : r.id)}
                className="mdd-row"
                style={{
                  width: '100%', gap: 12, padding: '13px 4px', background: 'transparent',
                  border: 'none', cursor: 'pointer', textAlign: 'start', color: 'inherit', font: 'inherit',
                }}>
                <span style={{
                  width: 34, height: 34, borderRadius: 10, flex: 'none', display: 'grid', placeItems: 'center',
                  background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
                }}>{iconFor(r.event_type)}</span>
                <span style={{ flex: 1, minWidth: 0 }}>
                  <span style={{ display: 'block', fontSize: 13.5, fontWeight: 600 }}>{r.message_ar}</span>
                  <span style={{ display: 'block', fontSize: 11.5, color: 'var(--mdd-text-3)', marginBlockStart: 3 }}>
                    {fmtRelative(r.created_at)}
                    {r.subscriber_id && data?.names.get(r.subscriber_id) ? ` · ${data.names.get(r.subscriber_id)}` : ''}
                  </span>
                </span>
                <Badge>{EVENT_AR[r.event_type] || r.event_type}</Badge>
              </button>
              {open === r.id && (
                <div style={{ padding: '0 4px 14px 4px' }}>
                  <div className="mdd-card" style={{ background: 'var(--mdd-sunken)', fontSize: 12 }}>
                    <div className="mdd-row mdd-row--between" style={{ paddingBlockEnd: 6 }}>
                      <span className="mdd-muted">الوقت</span><span>{fmtBoth(r.created_at)}</span>
                    </div>
                    <div className="mdd-row mdd-row--between" style={{ paddingBlockEnd: 6 }}>
                      <span className="mdd-muted">الفاعل</span><span>{r.actor_name || '— النظام —'}</span>
                    </div>
                    <div className="mdd-row mdd-row--between" style={{ paddingBlockEnd: 6 }}>
                      <span className="mdd-muted">المشترك</span>
                      <span>
                        {r.subscriber_id
                          ? <Link to={`/admin/subscriber/${r.subscriber_id}`} style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>
                              {data?.names.get(r.subscriber_id) || r.subscriber_id}
                            </Link>
                          : '—'}
                      </span>
                    </div>
                    {r.meta && Object.keys(r.meta).length > 0 && (
                      <pre className="mdd-mono" style={{
                        margin: 0, fontSize: 11, whiteSpace: 'pre-wrap', wordBreak: 'break-all',
                        color: 'var(--mdd-text-2)',
                      }}>{JSON.stringify(r.meta, null, 2)}</pre>
                    )}
                  </div>
                </div>
              )}
            </div>
          ))}
        </Card>
      )}
    </>
  )
}
