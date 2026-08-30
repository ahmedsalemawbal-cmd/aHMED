import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { supabase, callFunction } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtMoney, fmtShort, fmtNum, daysLabel } from '../../lib/format'
import type { Subscription } from '../../lib/types'
import {
  Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, PageHead,
  Progress, Select, SkeletonRows, Tabs,
} from '../../ui/kit'

type Tab = 'active' | 'soon' | 'expired' | 'cancelled'

export default function Subscriptions() {
  const { plans, toast } = useApp()
  const [tab, setTab] = useState<Tab>('active')
  const [planId, setPlanId] = useState('')
  const [month, setMonth] = useState('')
  const [cancelRow, setCancelRow] = useState<Subscription | null>(null)
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const [subs, owners] = await Promise.all([
      supabase.from('subscriptions').select('*').order('ends_at', { ascending: false }),
      supabase.from('subscribers').select('id,name'),
    ])
    if (subs.error) throw new Error(subs.error.message)
    return {
      rows: (subs.data || []) as Subscription[],
      names: new Map<string, string>((owners.data || []).map((s: any) => [s.id, s.name])),
    }
  }, [])

  const rows = data?.rows || []
  const now = Date.now()
  const in30 = now + 30 * 86400000

  const buckets = useMemo(() => ({
    active: rows.filter((r) => r.status === 'active' && new Date(r.ends_at).getTime() > now),
    soon: rows.filter((r) => r.status === 'active' && new Date(r.ends_at).getTime() > now && new Date(r.ends_at).getTime() <= in30),
    expired: rows.filter((r) => r.status === 'expired' || (r.status === 'active' && new Date(r.ends_at).getTime() <= now)),
    cancelled: rows.filter((r) => r.status === 'cancelled'),
  }), [rows, now, in30])

  const shown = useMemo(() => {
    let list = buckets[tab]
    if (planId) list = list.filter((r) => r.plan_id === planId)
    if (month) list = list.filter((r) => r.ends_at.slice(0, 7) === month)
    return list
  }, [buckets, tab, planId, month])

  const months = useMemo(() => {
    const set = new Set(rows.map((r) => r.ends_at.slice(0, 7)))
    return [...set].sort().reverse()
  }, [rows])

  const cancel = async () => {
    if (!cancelRow) return
    setBusy(true)
    const { error } = await supabase.from('subscriptions')
      .update({ status: 'cancelled', cancelled_at: new Date().toISOString() }).eq('id', cancelRow.id)
    setBusy(false)
    if (error) { toast('تعذّر الإلغاء', 'danger'); return }
    toast('أُلغي الاشتراك'); setCancelRow(null); reload()
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead title="الاشتراكات" sub={loading ? 'جارٍ التحميل…' : `${fmtNum(rows.length)} اشتراكًا في المنصّة`} />

      <div className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs
          value={tab} onChange={setTab}
          tabs={[
            { key: 'active', label: 'سارية', count: buckets.active.length },
            { key: 'soon', label: 'تنتهي قريبًا', count: buckets.soon.length },
            { key: 'expired', label: 'منتهية', count: buckets.expired.length },
            { key: 'cancelled', label: 'ملغاة', count: buckets.cancelled.length },
          ]}
        />
        <div className="mdd-grid mdd-grid--2" style={{ gap: 10 }}>
          <Select value={planId} onChange={(e) => setPlanId(e.target.value)} aria-label="الباقة">
            <option value="">كلّ الباقات</option>
            {plans.map((p) => <option key={p.id} value={p.id}>{p.name_ar}</option>)}
          </Select>
          <Select value={month} onChange={(e) => setMonth(e.target.value)} aria-label="شهر الانتهاء">
            <option value="">كلّ شهور الانتهاء</option>
            {months.map((m) => <option key={m} value={m}>{m}</option>)}
          </Select>
        </div>
      </div>

      {loading ? <SkeletonRows n={6} /> : shown.length === 0 ? (
        <EmptyState title="لا اشتراكات هنا" line="جرّب تبويبًا آخر أو امسح الفلاتر." />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr><th>المشترك</th><th>الباقة</th><th>البداية</th><th>النهاية</th><th>المتبقّي</th><th>القيمة</th><th aria-label="أفعال" /></tr>
            </thead>
            <tbody>
              {shown.map((r) => {
                const total = new Date(r.ends_at).getTime() - new Date(r.starts_at).getTime()
                const left = new Date(r.ends_at).getTime() - now
                const pct = total > 0 ? Math.max(0, Math.min(100, (left / total) * 100)) : 0
                const daysLeft = Math.max(0, Math.ceil(left / 86400000))
                return (
                  <tr key={r.id}>
                    <td data-label="المشترك">
                      <Link to={`/admin/subscriber/${r.subscriber_id}`} style={{ fontWeight: 700, color: 'var(--mdd-accent)' }}>
                        {data?.names.get(r.subscriber_id) || '—'}
                      </Link>
                    </td>
                    <td data-label="الباقة">{plans.find((p) => p.id === r.plan_id)?.name_ar || '—'}</td>
                    <td data-label="البداية">{fmtShort(r.starts_at)}</td>
                    <td data-label="النهاية">{fmtShort(r.ends_at)}</td>
                    <td data-label="المتبقّي" style={{ minWidth: 130 }}>
                      <div style={{ fontSize: 11.5, marginBlockEnd: 4, color: 'var(--mdd-text-3)' }}>{daysLabel(daysLeft)}</div>
                      <Progress value={pct} tone={pct < 12 ? 'danger' : pct < 30 ? 'warn' : undefined} />
                    </td>
                    <td data-label="القيمة">{fmtMoney(r.amount_sar)}</td>
                    <td>
                      {r.status === 'active' && (
                        <Button size="sm" auto variant="danger" onClick={() => setCancelRow(r)}>إلغاء</Button>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmModal
        open={!!cancelRow} onClose={() => setCancelRow(null)} onConfirm={cancel} loading={busy} danger
        confirmLabel="ألغِ الاشتراك" title="إلغاء اشتراك؟"
        body="يُعلَّم الاشتراك ملغى ولا يُحتسب ساريًا بعد اليوم. ولا يُحذف شيء من ملفّات المشترك."
      />
    </>
  )
}
