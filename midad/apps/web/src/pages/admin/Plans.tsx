import React, { useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtMoney, fmtNum } from '../../lib/format'
import type { Plan } from '../../lib/types'
import {
  Alert, Badge, Button, Card, Checkbox, ErrorState, Field, Input, Modal,
  PageHead, Select, SkeletonRows, Switch,
} from '../../ui/kit'
import { IcPlus, IcEdit } from '../../ui/icons'

const BLANK = {
  key: '', name_ar: '', account_type: 'school', price_sar: 0, price_before_sar: '', period_months: 12, seats: 1,
  template_categories: [] as string[], noor_enabled: true, ai_quota_monthly: 100,
  features_ar: [] as string[], is_active: true, is_default: false, sort: 10,
}

export default function Plans() {
  const { roles, toast } = useApp()
  const [editing, setEditing] = useState<any | null>(null)
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const [plans, subs] = await Promise.all([
      supabase.from('plans').select('*').order('sort'),
      supabase.from('subscribers').select('plan_id'),
    ])
    if (plans.error) throw new Error(plans.error.message)
    const counts = new Map<string, number>()
    for (const s of (subs.data || []) as any[]) {
      if (s.plan_id) counts.set(s.plan_id, (counts.get(s.plan_id) || 0) + 1)
    }
    return { plans: (plans.data || []) as Plan[], counts }
  }, [])

  const save = async () => {
    if (!editing) return
    if (!editing.name_ar?.trim() || !editing.key?.trim()) { toast('الاسم والمفتاح مطلوبان', 'danger'); return }
    setBusy(true)
    const payload = {
      key: editing.key.trim(), name_ar: editing.name_ar.trim(), account_type: editing.account_type,
      price_sar: Number(editing.price_sar) || 0,
      price_before_sar: String(editing.price_before_sar ?? '').trim() === '' ? null : Number(editing.price_before_sar),
      period_months: Number(editing.period_months) || 12,
      seats: Number(editing.seats) || 1, template_categories: editing.template_categories || [],
      noor_enabled: !!editing.noor_enabled, ai_quota_monthly: Number(editing.ai_quota_monthly) || 0,
      features_ar: (editing.features_text || '').split('\n').map((x: string) => x.trim()).filter(Boolean),
      is_active: !!editing.is_active, is_default: !!editing.is_default, sort: Number(editing.sort) || 0,
    }
    const res = editing.id
      ? await supabase.from('plans').update(payload).eq('id', editing.id)
      : await supabase.from('plans').insert(payload)
    setBusy(false)
    if (res.error) { toast(res.error.message, 'danger'); return }
    toast(editing.id ? 'حُفظت الباقة' : 'أُنشئت الباقة')
    setEditing(null); reload()
  }

  const open = (p?: Plan) => setEditing(p
    ? { ...p, features_text: (p.features_ar || []).join('\n') }
    : { ...BLANK, features_text: '' })

  const toggleCat = (key: string) => {
    const cur: string[] = editing.template_categories || []
    setEditing({ ...editing, template_categories: cur.includes(key) ? cur.filter((c) => c !== key) : [...cur, key] })
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الباقات" sub="ما يُباع وبكم — والسعر قابل للتغيير في أيّ وقت."
        actions={<Button auto variant="primary" icon={<IcPlus size={15} />} onClick={() => open()}>باقة جديدة</Button>}
      />

      {loading ? <SkeletonRows n={3} /> : (
        <div className="mdd-grid mdd-grid--2">
          {(data?.plans || []).map((p) => {
            const used = data?.counts.get(p.id) || 0
            return (
              <Card key={p.id} className="mdd-col" style={{ gap: 12, opacity: p.is_active ? 1 : 0.65 }}>
                <div className="mdd-row mdd-row--between">
                  <div>
                    <h2 style={{ fontSize: 17 }}>{p.name_ar}</h2>
                    <span className="mdd-mono" style={{ fontSize: 11, color: 'var(--mdd-text-3)' }}>{p.key}</span>
                  </div>
                  <div className="mdd-row" style={{ gap: 6 }}>
                    {p.is_default && <Badge tone="accent">الافتراضية</Badge>}
                    <Badge tone={p.is_active ? 'success' : 'neutral'} dot>{p.is_active ? 'مفعّلة' : 'معطّلة'}</Badge>
                  </div>
                </div>
                <div style={{ fontSize: 26, fontWeight: 700 }}>{fmtMoney(p.price_sar)}
                  <span style={{ fontSize: 13, fontWeight: 500, color: 'var(--mdd-text-3)' }}> / {p.period_months} شهرًا</span>
                </div>
                <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
                  <Badge>{p.account_type === 'school' ? 'مدرسة' : 'معلّم'}</Badge>
                  <Badge><span className="mdd-num">{p.seats}</span> مقاعد</Badge>
                  <Badge>{p.noor_enabled ? 'نور مفتوحة' : 'بلا نور'}</Badge>
                  <Badge><span className="mdd-num">{fmtNum(p.ai_quota_monthly)}</span> تحسين/شهر</Badge>
                </div>
                <p style={{ fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
                  الفئات: {p.template_categories?.length
                    ? p.template_categories.map((c) => roles.find((r) => r.key === c)?.name_ar || c).join(' · ')
                    : 'كلّ الفئات'}
                </p>
                <div className="mdd-row mdd-row--between" style={{ marginBlockStart: 'auto' }}>
                  <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>
                    <span className="mdd-num">{used}</span> مشتركًا عليها
                  </span>
                  <Button size="sm" auto icon={<IcEdit size={13} />} onClick={() => open(p)}>تعديل</Button>
                </div>
              </Card>
            )
          })}
        </div>
      )}

      <Modal open={!!editing} onClose={() => setEditing(null)} wide title={editing?.id ? 'تعديل باقة' : 'باقة جديدة'}
        footer={<>
          <Button variant="secondary" block onClick={() => setEditing(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={save}>احفظ</Button>
        </>}>
        {editing && (
          <>
            {editing.id && !editing.is_active && (data?.counts.get(editing.id) || 0) > 0 && (
              <Alert tone="warn">
                <span className="mdd-num">{data?.counts.get(editing.id)}</span> مشتركًا على هذه الباقة —
                يبقون كما هم ولا تُباع للجدد.
              </Alert>
            )}
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="الاسم المعروض">
                <Input value={editing.name_ar} onChange={(e) => setEditing({ ...editing, name_ar: e.target.value })} />
              </Field>
              <Field label="المفتاح (إنجليزي)">
                <Input ltr value={editing.key} onChange={(e) => setEditing({ ...editing, key: e.target.value })} />
              </Field>
              <Field label="النوع">
                <Select value={editing.account_type} onChange={(e) => setEditing({ ...editing, account_type: e.target.value })}>
                  <option value="school">مدرسة</option><option value="teacher">معلّم</option>
                </Select>
              </Field>
              <Field label="السعر (ر.س)">
                <Input ltr type="number" value={editing.price_sar} onChange={(e) => setEditing({ ...editing, price_sar: e.target.value })} />
              </Field>
              <Field label="السعر قبل الخصم (ر.س)" help="اتركه فارغًا إن لا عرض. يُعرض مشطوبًا بجانب السعر، ولا بدّ أن يزيد عليه.">
                <Input ltr type="number" value={editing.price_before_sar ?? ''}
                  onChange={(e) => setEditing({ ...editing, price_before_sar: e.target.value })} />
              </Field>
              <Field label="المدّة بالأشهر">
                <Input ltr type="number" value={editing.period_months} onChange={(e) => setEditing({ ...editing, period_months: e.target.value })} />
              </Field>
              <Field label="المقاعد">
                <Input ltr type="number" value={editing.seats} onChange={(e) => setEditing({ ...editing, seats: e.target.value })} />
              </Field>
              <Field label="حصّة التحسين الشهرية">
                <Input ltr type="number" value={editing.ai_quota_monthly} onChange={(e) => setEditing({ ...editing, ai_quota_monthly: e.target.value })} />
              </Field>
              <Field label="الترتيب">
                <Input ltr type="number" value={editing.sort} onChange={(e) => setEditing({ ...editing, sort: e.target.value })} />
              </Field>
            </div>

            <Field label="فئات القوالب المسموحة" help="اتركها فارغة لتعني كلّ الفئات — لا لا شيء.">
              <div className="mdd-row mdd-row--wrap" style={{ gap: 10, paddingBlockStart: 4 }}>
                {roles.map((r) => (
                  <Checkbox key={r.key}
                    checked={(editing.template_categories || []).includes(r.key)}
                    onChange={() => toggleCat(r.key)}>{r.name_ar}</Checkbox>
                ))}
              </div>
            </Field>

            <Field label="المزايا — سطر لكلّ ميزة">
              <textarea className="mdd-textarea" rows={5} value={editing.features_text}
                onChange={(e) => setEditing({ ...editing, features_text: e.target.value })} />
            </Field>

            <div className="mdd-col" style={{ gap: 12 }}>
              <Switch checked={!!editing.noor_enabled} onChange={(v) => setEditing({ ...editing, noor_enabled: v })} label="جداول نور مفتوحة" />
              <Switch checked={!!editing.is_active} onChange={(v) => setEditing({ ...editing, is_active: v })} label="الباقة مفعّلة وتُباع" />
              <Switch checked={!!editing.is_default} onChange={(v) => setEditing({ ...editing, is_default: v })} label="الباقة الافتراضية لهذا النوع" />
            </div>
          </>
        )}
      </Modal>
    </>
  )
}
