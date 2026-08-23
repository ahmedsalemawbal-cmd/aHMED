import React, { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { supabase, callFunction } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtBoth, fmtMoney, fmtNum, fmtRelative, fmtShort, daysLabel } from '../../lib/format'
import type { AuditEntry, Invoice, Profile, Subscriber, Subscription } from '../../lib/types'
import {
  Alert, Avatar, Badge, Button, Card, ConfirmModal, ErrorState, Field, Modal,
  PageHead, Select, SkeletonRows, Tabs,
} from '../../ui/kit'
import { IcBack, IcClock } from '../../ui/icons'
import { ACCOUNT_AR, INVOICE_AR, INVOICE_TONE, STATUS_AR, STATUS_TONE, effectiveStatus } from './adminUtil'

type Tab = 'overview' | 'members' | 'money' | 'log'

export default function SubscriberDetail() {
  const { id } = useParams()
  const nav = useNavigate()
  const { plans, roles, toast } = useApp()
  const [tab, setTab] = useState<Tab>('overview')
  const [confirm, setConfirm] = useState<'suspend' | 'activate' | null>(null)
  const [extendOpen, setExtendOpen] = useState(false)
  const [planOpen, setPlanOpen] = useState(false)
  const [days, setDays] = useState('7')
  const [newPlan, setNewPlan] = useState('')
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const [sub, members, invoices, subscriptions, log, docs, noor, ai] = await Promise.all([
      supabase.from('subscribers').select('*').eq('id', id).maybeSingle(),
      supabase.from('profiles').select('*').eq('subscriber_id', id).order('is_owner', { ascending: false }),
      supabase.from('invoices').select('*').eq('subscriber_id', id).order('issued_at', { ascending: false }),
      supabase.from('subscriptions').select('*').eq('subscriber_id', id).order('ends_at', { ascending: false }),
      supabase.from('audit_log').select('*').eq('subscriber_id', id).order('created_at', { ascending: false }).limit(60),
      supabase.from('documents').select('id', { count: 'exact', head: true }).eq('subscriber_id', id),
      supabase.from('noor_tables').select('id', { count: 'exact', head: true }).eq('subscriber_id', id),
      supabase.from('ai_usage').select('id', { count: 'exact', head: true }).eq('subscriber_id', id),
    ])
    if (!sub.data) throw new Error('لم نجد هذا المشترك')
    return {
      sub: sub.data as Subscriber,
      members: (members.data || []) as Profile[],
      invoices: (invoices.data || []) as Invoice[],
      subscriptions: (subscriptions.data || []) as Subscription[],
      log: (log.data || []) as AuditEntry[],
      docs: docs.count || 0, noor: noor.count || 0, ai: ai.count || 0,
    }
  }, [id])

  if (loading) return <SkeletonRows n={7} />
  if (error || !data) return <ErrorState onRetry={reload} message={error || undefined} />

  const s = data.sub
  const st = effectiveStatus(s)
  const plan = plans.find((p) => p.id === s.plan_id)
  const activeSubscription = data.subscriptions.find((x) => x.status === 'active' && new Date(x.ends_at) > new Date())

  const run = async (body: any, okMsg: string) => {
    setBusy(true)
    try { await callFunction('admin', body); toast(okMsg); reload() }
    catch (e: any) { toast(e?.message || 'تعذّر التنفيذ', 'danger') }
    finally { setBusy(false); setConfirm(null); setExtendOpen(false); setPlanOpen(false) }
  }

  return (
    <>
      <Button auto size="sm" icon={<IcBack size={14} />} onClick={() => nav('/admin/subscribers')}
        style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>كلّ المشتركين</Button>

      <Card style={{
        marginBlockEnd: 'var(--mdd-s-5)',
        borderColor: st === 'suspended' ? 'var(--mdd-danger-fg)' : undefined,
      }}>
        <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 16 }}>
          <div className="mdd-row" style={{ gap: 14 }}>
            <Avatar name={s.name} size="lg" />
            <div>
              <h1 style={{ fontSize: 22 }}>{s.name}</h1>
              <div className="mdd-row mdd-row--wrap" style={{ gap: 8, marginBlockStart: 8 }}>
                <Badge tone={STATUS_TONE[st]} dot>{STATUS_AR[st]}</Badge>
                <Badge>{ACCOUNT_AR[s.account_type]}</Badge>
                {plan && <Badge tone="accent">{plan.name_ar}</Badge>}
                <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>سجّل {fmtShort(s.created_at)}</span>
              </div>
            </div>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
            <Button auto size="sm" icon={<IcClock size={14} />} onClick={() => setExtendOpen(true)}>مدّد التجربة</Button>
            <Button auto size="sm" onClick={() => { setNewPlan(s.plan_id || ''); setPlanOpen(true) }}>غيّر الباقة</Button>
            {st === 'suspended'
              ? <Button auto size="sm" variant="primary" onClick={() => setConfirm('activate')}>تفعيل</Button>
              : <Button auto size="sm" variant="danger" onClick={() => setConfirm('suspend')}>إيقاف</Button>}
          </div>
        </div>
        {st === 'suspended' && s.suspended_reason && (
          <div style={{ marginBlockStart: 14 }}>
            <Alert tone="danger">سبب الإيقاف: {s.suspended_reason}</Alert>
          </div>
        )}
      </Card>

      <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs
          value={tab} onChange={setTab}
          tabs={[
            { key: 'overview', label: 'نظرة عامّة' },
            { key: 'members', label: 'الأعضاء', count: data.members.length },
            { key: 'money', label: 'الفواتير والاشتراكات', count: data.invoices.length },
            { key: 'log', label: 'السجلّ', count: data.log.length },
          ]}
        />
      </div>

      {tab === 'overview' && (
        <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
          <Card className="mdd-col">
            <h2 className="mdd-card__title">البيانات</h2>
            <Row k="الجوّال" v={<span className="mdd-mono">{s.contact_phone || '—'}</span>} />
            <Row k="المدينة" v={s.city || '—'} />
            <Row k="نوع المدرسة" v={s.school_type || '—'} />
            <Row k="إدارة التعليم" v={s.education_dept || '—'} />
            <Row k="العام الدراسي" v={s.academic_year || '—'} />
            <Row k="مدير المدرسة" v={s.principal_name || '—'} />
          </Card>
          <Card className="mdd-col">
            <h2 className="mdd-card__title">الاشتراك والاستعمال</h2>
            <Row k="الباقة" v={plan?.name_ar || '— لا باقة —'} />
            <Row k="نهاية التجربة" v={fmtBoth(s.trial_ends_at)} />
            <Row k="نهاية الاشتراك" v={activeSubscription ? fmtBoth(activeSubscription.ends_at) : '—'} />
            <Row k="الملفّات" v={<span className="mdd-num">{fmtNum(data.docs)}</span>} />
            <Row k="جداول نور" v={<span className="mdd-num">{fmtNum(data.noor)}</span>} />
            <Row k="نداءات الذكاء" v={<span className="mdd-num">{fmtNum(data.ai)}</span>} />
            <Row k="آخر دخول" v={data.members.map((m) => m.last_login_at).filter(Boolean).sort().reverse()[0]
              ? fmtRelative(data.members.map((m) => m.last_login_at).filter(Boolean).sort().reverse()[0]!) : 'لم يدخل بعد'} />
          </Card>
        </div>
      )}

      {tab === 'members' && (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead><tr><th>العضو</th><th>الجوّال</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th></tr></thead>
            <tbody>
              {data.members.map((m) => (
                <tr key={m.id}>
                  <td data-label="العضو">
                    <div className="mdd-row" style={{ gap: 10 }}>
                      <Avatar name={m.full_name} size="sm" />
                      <span style={{ fontWeight: 600 }}>{m.full_name}</span>
                      {m.is_owner && <Badge tone="accent">المالك</Badge>}
                    </div>
                  </td>
                  <td data-label="الجوّال"><span className="mdd-mono">{m.phone}</span></td>
                  <td data-label="الدور">{roles.find((r) => r.key === m.role_key)?.name_ar || m.role_key}</td>
                  <td data-label="الحالة">
                    <Badge tone={m.status === 'active' ? 'success' : 'neutral'} dot>
                      {m.status === 'active' ? 'نشط' : 'موقوف'}
                    </Badge>
                  </td>
                  <td data-label="آخر دخول">{m.last_login_at ? fmtRelative(m.last_login_at) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'money' && (
        <div className="mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-card__title">الاشتراكات</h2>
            <div className="mdd-table-wrap mdd-table-wrap--cards">
              <table className="mdd-table">
                <thead><tr><th>الباقة</th><th>البداية</th><th>النهاية</th><th>القيمة</th><th>الحالة</th></tr></thead>
                <tbody>
                  {data.subscriptions.length === 0 && (
                    <tr><td colSpan={5} style={{ textAlign: 'center', color: 'var(--mdd-text-3)' }}>لا اشتراكات بعد</td></tr>
                  )}
                  {data.subscriptions.map((x) => (
                    <tr key={x.id}>
                      <td data-label="الباقة">{plans.find((p) => p.id === x.plan_id)?.name_ar || '—'}</td>
                      <td data-label="البداية">{fmtShort(x.starts_at)}</td>
                      <td data-label="النهاية">{fmtShort(x.ends_at)}</td>
                      <td data-label="القيمة">{fmtMoney(x.amount_sar)}</td>
                      <td data-label="الحالة">
                        <Badge tone={x.status === 'active' ? 'success' : 'neutral'} dot>
                          {x.status === 'active' ? 'ساري' : x.status === 'expired' ? 'منتهٍ' : 'ملغى'}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-card__title">الفواتير</h2>
            <div className="mdd-table-wrap mdd-table-wrap--cards">
              <table className="mdd-table">
                <thead><tr><th>الرقم</th><th>التاريخ</th><th>الوصف</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
                <tbody>
                  {data.invoices.length === 0 && (
                    <tr><td colSpan={5} style={{ textAlign: 'center', color: 'var(--mdd-text-3)' }}>لا فواتير بعد</td></tr>
                  )}
                  {data.invoices.map((inv) => (
                    <tr key={inv.id}>
                      <td data-label="الرقم"><span className="mdd-mono">{inv.number}</span></td>
                      <td data-label="التاريخ">{fmtShort(inv.issued_at)}</td>
                      <td data-label="الوصف">{inv.description_ar || '—'}</td>
                      <td data-label="الإجمالي">{fmtMoney(inv.total_sar)}</td>
                      <td data-label="الحالة"><Badge tone={INVOICE_TONE[inv.status]} dot>{INVOICE_AR[inv.status]}</Badge></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {tab === 'log' && (
        <Card className="mdd-col" style={{ gap: 0 }}>
          {data.log.length === 0 && <p className="mdd-muted" style={{ fontSize: 13 }}>لا أحداث بعد.</p>}
          {data.log.map((e) => (
            <div key={e.id} className="mdd-row" style={{ gap: 12, padding: '12px 0', borderBlockEnd: '1px solid var(--mdd-border)' }}>
              <span style={{ color: 'var(--mdd-accent)' }}><IcClock size={16} /></span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{e.message_ar}</div>
                <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{fmtRelative(e.created_at)} · {e.event_type}</div>
              </div>
            </div>
          ))}
        </Card>
      )}

      <ConfirmModal
        open={!!confirm} onClose={() => setConfirm(null)} loading={busy}
        danger={confirm === 'suspend'}
        confirmLabel={confirm === 'suspend' ? 'أوقف' : 'فعّل'}
        title={confirm === 'suspend' ? 'إيقاف المشترك؟' : 'إعادة التفعيل؟'}
        body={confirm === 'suspend'
          ? `يُمنع «${s.name}» من الدخول وتبقى بياناته كما هي. ولن يُعرض عليه زرّ دفع.`
          : `يعود «${s.name}» إلى الحالة السابقة ويستطيع الدخول فورًا.`}
        onConfirm={() => run({
          action: 'set_subscriber_status', subscriber_id: s.id,
          status: confirm === 'suspend' ? 'suspended' : 'active',
          reason: confirm === 'suspend' ? 'بقرار من الإدارة' : null,
        }, confirm === 'suspend' ? 'أُوقف المشترك' : 'أُعيد التفعيل')}
      />

      <Modal open={extendOpen} onClose={() => setExtendOpen(false)} title="تمديد التجربة"
        footer={<>
          <Button variant="secondary" block onClick={() => setExtendOpen(false)}>إلغاء</Button>
          <Button variant="primary" block loading={busy}
            onClick={() => run({ action: 'extend_trial', subscriber_id: s.id, days: Number(days) || 7 }, 'مُدّدت التجربة')}>
            مدّد {daysLabel(Number(days) || 7)}
          </Button>
        </>}>
        <Field label="كم يومًا؟" help={`تنتهي التجربة الآن في ${fmtShort(s.trial_ends_at)}. التمديد يُضاف إلى ما تبقّى إن كانت سارية.`}>
          <Select value={days} onChange={(e) => setDays(e.target.value)}>
            {['3', '7', '14', '30'].map((d) => <option key={d} value={d}>{daysLabel(Number(d))}</option>)}
          </Select>
        </Field>
      </Modal>

      <Modal open={planOpen} onClose={() => setPlanOpen(false)} title="تغيير الباقة"
        footer={<>
          <Button variant="secondary" block onClick={() => setPlanOpen(false)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} disabled={!newPlan}
            onClick={() => run({ action: 'set_plan', subscriber_id: s.id, plan_id: newPlan }, 'غُيّرت الباقة')}>
            احفظ
          </Button>
        </>}>
        <Field label="الباقة" help="تغيير الباقة يبدّل ما يراه المشترك من الفئات وعدد مقاعده — ولا يفتح اشتراكًا بذاته.">
          <Select value={newPlan} onChange={(e) => setNewPlan(e.target.value)}>
            <option value="">— اختر —</option>
            {plans.map((p) => <option key={p.id} value={p.id}>{p.name_ar} · {fmtMoney(p.price_sar)}</option>)}
          </Select>
        </Field>
      </Modal>
    </>
  )
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
  return (
    <div className="mdd-row mdd-row--between" style={{ gap: 12, paddingBlock: 7, borderBlockEnd: '1px solid var(--mdd-border)' }}>
      <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', fontWeight: 600 }}>{k}</span>
      <span style={{ fontSize: 13, fontWeight: 600, textAlign: 'end' }}>{v}</span>
    </div>
  )
}
