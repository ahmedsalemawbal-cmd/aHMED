import React, { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { supabase, callFunction } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fmtShort, fmtNum } from '../../lib/format'
import type { Subscriber } from '../../lib/types'
import {
  Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, PageHead,
  SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcChevron, IcFileExcel } from '../../ui/icons'
import { ACCOUNT_AR, STATUS_AR, STATUS_TONE, effectiveStatus } from './adminUtil'
import { buildXlsx, download } from '../../lib/export'

const PAGE = 25

export default function Subscribers() {
  const { plans, toast } = useApp()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [planId, setPlanId] = useState('')
  const [page, setPage] = useState(0)
  const [busy, setBusy] = useState<string | null>(null)
  const [confirm, setConfirm] = useState<{ sub: Subscriber; next: 'suspended' | 'active' } | null>(null)
  const dq = useDebounced(q)

  const { data, loading, error, reload } = useAsync(async () => {
    const [subs, members] = await Promise.all([
      supabase.from('subscribers').select('*').order('created_at', { ascending: false }),
      supabase.from('profiles').select('subscriber_id').eq('status', 'active'),
    ])
    if (subs.error) throw new Error(subs.error.message)
    const counts = new Map<string, number>()
    for (const m of (members.data || []) as any[]) {
      if (m.subscriber_id) counts.set(m.subscriber_id, (counts.get(m.subscriber_id) || 0) + 1)
    }
    return { subs: (subs.data || []) as Subscriber[], counts }
  }, [])

  const filtered = useMemo(() => {
    let list = data?.subs || []
    const term = dq.trim()
    if (term) list = list.filter((s) => s.name.includes(term) || (s.contact_phone || '').includes(term))
    if (type) list = list.filter((s) => s.account_type === type)
    if (status) list = list.filter((s) => effectiveStatus(s) === status)
    if (planId) list = list.filter((s) => s.plan_id === planId)
    return list
  }, [data, dq, type, status, planId])

  const pageRows = filtered.slice(page * PAGE, page * PAGE + PAGE)
  const planName = (id: string | null) => plans.find((p) => p.id === id)?.name_ar || '—'

  const applyStatus = async () => {
    if (!confirm) return
    setBusy(confirm.sub.id)
    try {
      await callFunction('admin', {
        action: 'set_subscriber_status', subscriber_id: confirm.sub.id, status: confirm.next,
        reason: confirm.next === 'suspended' ? 'بقرار من الإدارة' : null,
      })
      toast(confirm.next === 'suspended' ? 'أُوقف المشترك' : 'أُعيد التفعيل')
      setConfirm(null); reload()
    } catch (e: any) { toast(e?.message || 'تعذّر التنفيذ', 'danger') } finally { setBusy(null) }
  }

  const exportList = () => {
    const headers = ['المشترك', 'النوع', 'الجوّال', 'الحالة', 'الباقة', 'الأعضاء', 'تاريخ التسجيل']
    const rows = filtered.map((s) => [
      s.name, ACCOUNT_AR[s.account_type], s.contact_phone || '',
      STATUS_AR[effectiveStatus(s)], planName(s.plan_id),
      String(data?.counts.get(s.id) || 0), fmtShort(s.created_at),
    ])
    download(buildXlsx('المشتركون', headers, rows), 'المشتركون.xlsx')
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="المشتركون"
        sub={loading ? 'جارٍ التحميل…' : `${fmtNum(filtered.length)} مشتركًا`}
        actions={<Button auto icon={<IcFileExcel size={17} />} onClick={exportList} disabled={!filtered.length}>تصدير القائمة</Button>}
      />

      <Card className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <SearchInput value={q} onChange={(v) => { setQ(v); setPage(0) }} placeholder="ابحث بالاسم أو الجوّال" />
        <div className="mdd-grid mdd-grid--3" style={{ gap: 10 }}>
          <Select value={type} onChange={(e) => { setType(e.target.value); setPage(0) }} aria-label="النوع">
            <option value="">كلّ الأنواع</option>
            <option value="school">مدرسة</option>
            <option value="teacher">معلّم</option>
          </Select>
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(0) }} aria-label="الحالة">
            <option value="">كلّ الحالات</option>
            {Object.entries(STATUS_AR).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </Select>
          <Select value={planId} onChange={(e) => { setPlanId(e.target.value); setPage(0) }} aria-label="الباقة">
            <option value="">كلّ الباقات</option>
            {plans.map((p) => <option key={p.id} value={p.id}>{p.name_ar}</option>)}
          </Select>
        </div>
      </Card>

      {loading ? <SkeletonRows n={8} /> : filtered.length === 0 ? (
        <EmptyState
          title={data?.subs.length ? 'لا نتيجة لهذه الفلاتر' : 'لا مشتركين بعد'}
          line={data?.subs.length ? 'وسّع البحث أو امسح الفلاتر.' : 'أوّل مشترك سيظهر هنا فور تسجيله.'}
          action={data?.subs.length ? <Button variant="primary" onClick={() => { setQ(''); setType(''); setStatus(''); setPlanId('') }}>امسح الفلاتر</Button> : undefined}
        />
      ) : (
        <>
          <div className="mdd-table-wrap mdd-table-wrap--cards">
            <table className="mdd-table">
              <thead>
                <tr>
                  <th>المشترك</th><th>الجوّال</th><th>الحالة</th><th>الباقة</th>
                  <th>الأعضاء</th><th>التسجيل</th><th aria-label="أفعال" />
                </tr>
              </thead>
              <tbody>
                {pageRows.map((s) => {
                  const st = effectiveStatus(s)
                  return (
                    <tr key={s.id}>
                      <td data-label="المشترك">
                        <div style={{ fontWeight: 700 }}>{s.name}</div>
                        <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{ACCOUNT_AR[s.account_type]}{s.city ? ` · ${s.city}` : ''}</div>
                      </td>
                      <td data-label="الجوّال"><span className="mdd-mono">{s.contact_phone || '—'}</span></td>
                      <td data-label="الحالة"><Badge tone={STATUS_TONE[st]} dot>{STATUS_AR[st]}</Badge></td>
                      <td data-label="الباقة">{planName(s.plan_id)}</td>
                      <td data-label="الأعضاء"><span className="mdd-num">{data?.counts.get(s.id) || 0}</span></td>
                      <td data-label="التسجيل">{fmtShort(s.created_at)}</td>
                      <td>
                        <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                          {st === 'suspended'
                            ? <Button size="sm" auto variant="soft" loading={busy === s.id} onClick={() => setConfirm({ sub: s, next: 'active' })}>تفعيل</Button>
                            : <Button size="sm" auto variant="danger" loading={busy === s.id} onClick={() => setConfirm({ sub: s, next: 'suspended' })}>إيقاف</Button>}
                          <Button size="sm" auto onClick={() => nav(`/admin/subscriber/${s.id}`)} icon={<IcChevron size={13} />}>فتح</Button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {filtered.length > PAGE && (
            <div className="mdd-row" style={{ justifyContent: 'center', gap: 10, marginBlockStart: 'var(--mdd-s-5)' }}>
              <Button auto size="sm" disabled={page === 0} onClick={() => setPage((p) => p - 1)}>السابق</Button>
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-2)' }} className="mdd-num">
                {page + 1} / {Math.ceil(filtered.length / PAGE)}
              </span>
              <Button auto size="sm" disabled={(page + 1) * PAGE >= filtered.length} onClick={() => setPage((p) => p + 1)}>التالي</Button>
            </div>
          )}
        </>
      )}

      <ConfirmModal
        open={!!confirm} onClose={() => setConfirm(null)} onConfirm={applyStatus}
        danger={confirm?.next === 'suspended'}
        confirmLabel={confirm?.next === 'suspended' ? 'أوقف المشترك' : 'أعد التفعيل'}
        title={confirm?.next === 'suspended' ? 'إيقاف مشترك؟' : 'إعادة تفعيل؟'}
        body={confirm?.next === 'suspended'
          ? `يُمنع «${confirm?.sub.name}» من الدخول، وتبقى كلّ بياناته وملفّاته كما هي. ولن يُعرض عليه زرّ دفع — بل وسيلة تواصل.`
          : `يعود «${confirm?.sub.name}» إلى حالته السابقة ويستطيع الدخول فورًا.`}
      />
    </>
  )
}
