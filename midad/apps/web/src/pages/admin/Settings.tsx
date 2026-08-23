import React, { useEffect, useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import {
  Alert, Button, Card, ErrorState, Field, Input, PageHead, Select,
  SkeletonRows, Switch, Tabs,
} from '../../ui/kit'
import { IcEye, IcEyeOff } from '../../ui/icons'

type Tab = 'general' | 'payment' | 'tax' | 'ai' | 'trial'
const KEYS = ['general', 'payment_public', 'payment_secret', 'ai', 'ai_secret', 'trial'] as const

export default function Settings() {
  const { toast, refresh } = useApp()
  const [tab, setTab] = useState<Tab>('general')
  const [v, setV] = useState<Record<string, any>>({})
  const [busy, setBusy] = useState<string | null>(null)
  const [showKey, setShowKey] = useState<Record<string, boolean>>({})

  const { data, loading, error, reload } = useAsync(async () => {
    const { data: rows, error: e } = await supabase.from('platform_settings').select('key,value').in('key', KEYS as any)
    if (e) throw new Error(e.message)
    const out: Record<string, any> = {}
    for (const r of (rows || []) as any[]) out[r.key] = r.value || {}
    return out
  }, [])

  useEffect(() => { if (data) setV(JSON.parse(JSON.stringify(data))) }, [data])

  const set = (key: string, field: string, value: any) =>
    setV((s) => ({ ...s, [key]: { ...(s[key] || {}), [field]: value } }))

  const save = async (keys: string[]) => {
    setBusy(keys[0])
    for (const k of keys) {
      const { error: e } = await supabase.from('platform_settings')
        .upsert({ key: k, value: v[k] || {}, updated_at: new Date().toISOString() }, { onConflict: 'key' })
      if (e) { setBusy(null); toast(e.message, 'danger'); return }
    }
    setBusy(null); toast('حُفظت الإعدادات'); reload(); refresh()
  }

  const secretField = (key: string, field: string, label: string, help?: string) => (
    <Field label={label} help={help}>
      <span style={{ position: 'relative', display: 'block' }}>
        <Input ltr type={showKey[key + field] ? 'text' : 'password'}
          value={v[key]?.[field] ?? ''} onChange={(e) => set(key, field, e.target.value)}
          placeholder="••••••••••••••••" style={{ paddingInlineEnd: 44 }} />
        <button type="button" onClick={() => setShowKey((s) => ({ ...s, [key + field]: !s[key + field] }))}
          aria-label={showKey[key + field] ? 'إخفاء المفتاح' : 'إظهار المفتاح'}
          style={{
            position: 'absolute', insetInlineEnd: 8, insetBlockStart: '50%', transform: 'translateY(-50%)',
            background: 'none', border: 'none', cursor: 'pointer', color: 'var(--mdd-text-3)', padding: 6,
          }}>
          {showKey[key + field] ? <IcEyeOff size={17} /> : <IcEye size={17} />}
        </button>
      </span>
    </Field>
  )

  if (error) return <ErrorState onRetry={reload} message={error} />
  if (loading) return <SkeletonRows n={6} />

  const paymentsOn = !!v.payment_public?.payments_enabled

  return (
    <>
      <PageHead title="إعدادات المنصّة" sub="المفاتيح والأرقام التي تُدير كلّ شيء." />

      <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs value={tab} onChange={setTab} tabs={[
          { key: 'general', label: 'عامّ' },
          { key: 'payment', label: 'الدفع' },
          { key: 'tax', label: 'الضريبة' },
          { key: 'ai', label: 'الذكاء' },
          { key: 'trial', label: 'التجربة' },
        ]} />
      </div>

      {tab === 'general' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="اسم المنصّة">
              <Input value={v.general?.platform_name ?? ''} onChange={(e) => set('general', 'platform_name', e.target.value)} />
            </Field>
            <Field label="السطر التعريفيّ">
              <Input value={v.general?.tagline ?? ''} onChange={(e) => set('general', 'tagline', e.target.value)} />
            </Field>
            <Field label="رقم واتساب" help="بصيغة دولية بلا + — مثال: 966500000000">
              <Input ltr value={v.general?.whatsapp ?? ''} onChange={(e) => set('general', 'whatsapp', e.target.value)} />
            </Field>
            <Field label="بريد الدعم">
              <Input ltr type="email" value={v.general?.email ?? ''} onChange={(e) => set('general', 'email', e.target.value)} />
            </Field>
          </div>
          <Field label="ساعات العمل">
            <Input value={v.general?.working_hours ?? ''} onChange={(e) => set('general', 'working_hours', e.target.value)} />
          </Field>
          <Button auto variant="primary" loading={busy === 'general'} onClick={() => save(['general'])}
            style={{ alignSelf: 'flex-start' }}>احفظ</Button>
        </Card>
      )}

      {tab === 'payment' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <Alert tone={paymentsOn ? 'success' : 'info'}>
            {paymentsOn
              ? 'الدفع الإلكترونيّ مفعّل — المشتركون يرون «ادفع الآن» بالبطاقة.'
              : 'الدفع الإلكترونيّ مطفأ — المشتركون يرون التحويل البنكيّ، وأنت تؤكّد الدفع يدويًّا من شاشة الفواتير.'}
          </Alert>

          <Switch checked={paymentsOn} onChange={(x) => set('payment_public', 'payments_enabled', x)}
            label="فعّل الدفع الإلكترونيّ (ميسر)" />

          {secretField('payment_secret', 'moyasar_key', 'مفتاح ميسر السرّي',
            'لا يُعرض لأحد غيرك، ولا يصل إلى المتصفّح. الصقه هنا يوم يصل، ثمّ افتح المبدّل أعلاه.')}

          <div style={{ height: 1, background: 'var(--mdd-border)' }} />
          <h3 style={{ fontSize: 14 }}>بيانات التحويل البنكيّ</h3>
          <p className="mdd-field__help">تظهر للمشترك في شاشة طلب الاشتراك — تأكّد من صحّتها حرفًا حرفًا.</p>
          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="اسم المستفيد">
              <Input value={v.payment_public?.beneficiary ?? ''} onChange={(e) => set('payment_public', 'beneficiary', e.target.value)} />
            </Field>
            <Field label="البنك">
              <Input value={v.payment_public?.bank ?? ''} onChange={(e) => set('payment_public', 'bank', e.target.value)} />
            </Field>
          </div>
          <Field label="الآيبان">
            <Input ltr className="mdd-mono" value={v.payment_public?.iban ?? ''}
              onChange={(e) => set('payment_public', 'iban', e.target.value.toUpperCase())} placeholder="SA00 0000 0000 0000 0000 0000" />
          </Field>
          <Button auto variant="primary" loading={busy === 'payment_public'}
            onClick={() => save(['payment_public', 'payment_secret'])} style={{ alignSelf: 'flex-start' }}>احفظ</Button>
        </Card>
      )}

      {tab === 'tax' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <Alert tone="info">
            الحقول موجودة من اليوم حتى لو كانت النسبة صفرًا — إضافتها بعد مئة فاتورة تعني تعديل وثائق مالية صدرت، وهي لا تُعدَّل بأثرٍ رجعيّ.
          </Alert>
          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="الرقم الضريبيّ">
              <Input ltr value={v.payment_public?.tax_number ?? ''} onChange={(e) => set('payment_public', 'tax_number', e.target.value)} />
            </Field>
            <Field label="نسبة الضريبة" help="اكتبها كسرًا: 0.15 تعني 15٪ — و 0 تعني بلا ضريبة.">
              <Input ltr type="number" step="0.01" value={v.payment_public?.tax_rate ?? 0}
                onChange={(e) => set('payment_public', 'tax_rate', Number(e.target.value) || 0)} />
            </Field>
          </div>
          <Switch checked={!!v.payment_public?.show_tax} onChange={(x) => set('payment_public', 'show_tax', x)}
            label="أظهر سطر الضريبة في الفواتير" />
          <Button auto variant="primary" loading={busy === 'payment_public'}
            onClick={() => save(['payment_public'])} style={{ alignSelf: 'flex-start' }}>احفظ</Button>
        </Card>
      )}

      {tab === 'ai' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <Alert tone={v.ai_secret?.api_key ? 'success' : 'warn'}>
            {v.ai_secret?.api_key
              ? 'المفتاح مضبوط — زرّ «حسّن» يعمل للمشتركين.'
              : 'لا مفتاح بعد — زرّ «حسّن» يعرض للمشترك رسالةً واضحة ولا يُخصم من حصّته شيء.'}
          </Alert>
          <Switch checked={v.ai?.enabled !== false} onChange={(x) => set('ai', 'enabled', x)}
            label="التحسين بالذكاء الاصطناعيّ مفعّل" />
          {secretField('ai_secret', 'api_key', 'مفتاح Anthropic', 'يُستعمل في الخادم وحده ولا يصل إلى المتصفّح.')}
          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="النموذج">
              <Select value={v.ai?.model ?? 'claude-sonnet-4-5'} onChange={(e) => set('ai', 'model', e.target.value)}>
                <option value="claude-sonnet-4-5">claude-sonnet-4-5</option>
                <option value="claude-opus-4-5">claude-opus-4-5</option>
                <option value="claude-haiku-4-5-20251001">claude-haiku-4-5</option>
              </Select>
            </Field>
            <Field label="السقف الشهريّ (نداءات)" help="حين يُبلَغ يتوقّف التحسين للجميع حتى الشهر القادم.">
              <Input ltr type="number" value={v.ai?.monthly_cap_calls ?? 20000}
                onChange={(e) => set('ai', 'monthly_cap_calls', Number(e.target.value) || 0)} />
            </Field>
          </div>
          <Button auto variant="primary" loading={busy === 'ai'}
            onClick={() => save(['ai', 'ai_secret'])} style={{ alignSelf: 'flex-start' }}>احفظ</Button>
        </Card>
      )}

      {tab === 'trial' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="أيّام التجربة" help="تُطبَّق على المسجّلين الجدد فقط — ولا تغيّر تجربة من سجّل قبل اليوم.">
              <Input ltr type="number" value={v.trial?.days ?? 7}
                onChange={(e) => set('trial', 'days', Number(e.target.value) || 7)} />
            </Field>
            <Field label="نصّ العلامة المائية" help="يظهر قُطريًّا على كلّ ملفّ يُصدَّر أثناء التجربة.">
              <Input value={v.trial?.watermark_text ?? ''} onChange={(e) => set('trial', 'watermark_text', e.target.value)} />
            </Field>
          </div>
          <Button auto variant="primary" loading={busy === 'trial'}
            onClick={() => save(['trial'])} style={{ alignSelf: 'flex-start' }}>احفظ</Button>
        </Card>
      )}
    </>
  )
}
