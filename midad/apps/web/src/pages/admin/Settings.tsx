import React, { useEffect, useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import {
  Alert, Button, Card, ErrorState, Field, Input, PageHead, Select,
  SkeletonRows, Switch, Tabs,
} from '../../ui/kit'
import { IcEye, IcEyeOff } from '../../ui/icons'

type Tab = 'general' | 'payment' | 'tax' | 'ai' | 'ext' | 'trial'
const KEYS = ['general', 'payment_public', 'payment_secret', 'ai', 'ext_tools', 'trial'] as const

/* والنماذج تتبع المزوّد: نموذجُ أحدهما لا يُنادى عند الآخر. */
const MODELS: Record<string, string[]> = {
  anthropic: ['claude-sonnet-4-5', 'claude-opus-4-5', 'claude-haiku-4-5-20251001'],
  openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini'],
}

export default function Settings() {
  const { toast, refresh } = useApp()
  const [tab, setTab] = useState<Tab>('general')
  const [v, setV] = useState<Record<string, any>>({})
  const [busy, setBusy] = useState<string | null>(null)
  const [showKey, setShowKey] = useState<Record<string, boolean>>({})
  /* المفتاح الجديد يعيش في هذه الحالة وحدها ولا يُحمَّل من القاعدة أبدًا. */
  const [aiKey, setAiKey] = useState('')

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

  /**
   * حفظُ إعدادات الذكاء — والمفتاح يمرّ بـ `set_ai_key` لا بكتابةٍ مباشرة،
   * لأنّ السرّ وحالتَه يجب أن يتحرّكا معًا: «مضبوط» بلا مفتاحٍ عطبٌ صامت.
   */
  const saveAi = async () => {
    const provider = v.ai?.provider ?? 'anthropic'
    const model = v.ai?.model ?? MODELS[provider][0]

    /* تبديلُ المزوّد بلا مفتاحٍ جديد يترك مفتاح الأوّل يُنادى عند الثاني —
       فيفشل النداء برسالةٍ لا تدلّ على السبب. يُمنع هنا لا هناك. */
    if (data?.ai?.configured && data?.ai?.provider !== provider && !aiKey.trim()) {
      toast('بدّلت المزوّد — اكتب مفتاح المزوّد الجديد، فمفتاح السابق لا يعمل عنده.', 'danger')
      return
    }

    setBusy('ai')
    const { error: e1 } = await supabase.from('platform_settings')
      .upsert({ key: 'ai', value: v.ai || {}, updated_at: new Date().toISOString() }, { onConflict: 'key' })
    if (e1) { setBusy(null); toast(e1.message, 'danger'); return }

    if (aiKey.trim()) {
      const { error: e2 } = await supabase.rpc('set_ai_key', {
        p_provider: provider, p_model: model, p_key: aiKey.trim(),
      })
      if (e2) { setBusy(null); toast(e2.message, 'danger'); return }
      setAiKey('')
    }
    setBusy(null); toast('حُفظت إعدادات الذكاء'); reload(); refresh()
  }

  const clearAi = async () => {
    if (!window.confirm('يُمسح المفتاح ويتوقّف الذكاء حتى تضع غيره. أأمضي؟')) return
    setBusy('ai-clear')
    const { error: e } = await supabase.rpc('set_ai_key', {
      p_provider: v.ai?.provider ?? 'anthropic',
      p_model: v.ai?.model ?? MODELS[v.ai?.provider ?? 'anthropic'][0],
      p_key: '',
    })
    setBusy(null)
    if (e) { toast(e.message, 'danger'); return }
    setAiKey(''); toast('مُسح المفتاح'); reload(); refresh()
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
          { key: 'ext', label: 'الإضافة' },
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
          <Alert tone={v.ai?.configured ? 'success' : 'warn'}>
            {v.ai?.configured
              ? `المفتاح مضبوط (ينتهي بـ ${v.ai?.hint || '؟؟؟؟'}) — الذكاء يعمل.`
              : 'لا مفتاح بعد — ما يحتاج الذكاء يعرض رسالةً واضحة ولا يُخصم من حصّة أحد شيء.'}
          </Alert>
          <Switch checked={v.ai?.enabled !== false} onChange={(x) => set('ai', 'enabled', x)}
            label="التحسين وتركيب ملفّ الإنجاز بالذكاء الاصطناعيّ مفعّلان" />

          <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
            <Field label="المزوّد">
              <Select value={v.ai?.provider ?? 'anthropic'} onChange={(e) => {
                const pr = e.target.value
                setV((st) => ({ ...st, ai: { ...(st.ai || {}), provider: pr, model: MODELS[pr][0] } }))
              }}>
                <option value="anthropic">Claude — Anthropic</option>
                <option value="openai">ChatGPT — OpenAI</option>
              </Select>
            </Field>
            <Field label="النموذج">
              <Select value={v.ai?.model ?? MODELS[v.ai?.provider ?? 'anthropic'][0]}
                onChange={(e) => set('ai', 'model', e.target.value)}>
                {MODELS[v.ai?.provider ?? 'anthropic'].map((m) => <option key={m} value={m}>{m}</option>)}
              </Select>
            </Field>
          </div>

          {/* حقلٌ يُكتب ولا يُقرأ.
              ولمَ لا يُقرأ ولو للمالك؟ لأنّ المفتاح إن بلغ المتصفّح مرّةً صار
              عرضةً لكلّ ما يبلغه. والذي ينادي المزوّد هو الخادم لا المتصفّح،
              فلا حاجة به هنا أصلًا. ويكفي المالكَ آخرُ أربعةِ أحرفٍ ليعرف
              أيَّ مفتاحٍ وضع.

                  ما لا يُقرأ لا يُسرَق. */}
          <Field label={v.ai?.configured ? 'استبدال المفتاح' : 'مفتاح المزوّد'}
            help={v.ai?.configured
              ? 'اتركه فارغًا ليبقى المفتاح الحاليّ. وما يُكتب هنا يُرسل إلى القاعدة ولا يُقرأ منها ثانيةً.'
              : (v.ai?.provider === 'openai'
                ? 'من platform.openai.com ← API keys. يبدأ بـ sk-'
                : 'من console.anthropic.com ← API keys. يبدأ بـ sk-ant-')}>
            <Input ltr type="password" value={aiKey} onChange={(e) => setAiKey(e.target.value)}
              placeholder={v.ai?.configured ? '•••• محفوظ — اكتب مفتاحًا جديدًا لتستبدله' : 'sk-…'} />
          </Field>

          <Field label="السقف الشهريّ (نداءات)" help="حين يُبلَغ يتوقّف الذكاء للجميع حتى الشهر القادم.">
            <Input ltr type="number" value={v.ai?.monthly_cap_calls ?? 20000}
              onChange={(e) => set('ai', 'monthly_cap_calls', Number(e.target.value) || 0)} />
          </Field>

          <div className="mdd-row" style={{ gap: 8, alignSelf: 'flex-start' }}>
            <Button auto variant="primary" loading={busy === 'ai'} onClick={saveAi}>احفظ</Button>
            {v.ai?.configured && (
              <Button auto variant="ghost" loading={busy === 'ai-clear'} onClick={clearAi}>امسح المفتاح</Button>
            )}
          </div>
        </Card>
      )}

      {tab === 'ext' && (
        <Card className="mdd-col" style={{ gap: 14 }}>
          <Alert tone="info">
            هذه الأزرارُ هي ما يراه المعلّم في لوحة مِداد داخل نور ومدرستي.
            تُعدّلها هنا فتصل الجميعَ في اللحظة — بلا نسخةٍ جديدةٍ من الإضافة
            ولا مراجعةِ متجر.
          </Alert>

          <Field label="سطر «الجديد»" help="يظهر في أسفل اللوحة. اتركه فارغًا فلا يظهر.">
            <Input value={v.ext_tools?.whats_new ?? ''}
              placeholder="مثال: إشعار الأولياء"
              onChange={(e) => set('ext_tools', 'whats_new', e.target.value)} />
          </Field>

          <ExtTools
            tools={Array.isArray(v.ext_tools?.tools) ? v.ext_tools.tools : []}
            onChange={(tools) => set('ext_tools', 'tools', tools)} />

          <Button auto variant="primary" loading={busy === 'ext_tools'}
            onClick={() => save(['ext_tools'])} style={{ alignSelf: 'flex-start' }}>احفظ</Button>
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

/**
 * أزرارُ لوحة الإضافة — وصفٌ يُحرَّر، لا شيفرةٌ تُكتب.
 *
 * منافسانا يُنزّلان شيفرتَهما من خادمهما ليُضيفا أداةً بلا مراجعة متجر.
 * والمكسبُ نفسه يُنال بالوصف: اللوحةُ تعرف كيف ترسم زرًّا ولا تعرف ما
 * الأزرار — تسألنا في كلّ فتحة.
 *
 *     ما يتبدّل يُوصَف. وما يُنفَّذ يُشحن ويُراجَع.
 *
 * والترتيبُ يهمّ: الأداةُ الأولى أوّلُ ما تقع عليه العين. فيُرفع الزرُّ
 * ويُنزَّل بسهمين — لا يُعاد كتابةُ القائمة لتبديل اثنين.
 */
function ExtTools({ tools, onChange }: {
  tools: any[]
  onChange: (t: any[]) => void
}) {
  const ICONS = ['table', 'files', 'doc', 'down', 'chat', 'chart']
  const patch = (i: number, k: string, val: any) =>
    onChange(tools.map((t, n) => (n === i ? { ...t, [k]: val } : t)))

  return (
    <div className="mdd-col" style={{ gap: 10 }}>
      <div className="mdd-row" style={{ justifyContent: 'space-between' }}>
        <strong style={{ fontSize: 14 }}>الأزرار ({tools.length})</strong>
        <Button auto size="sm" variant="soft" onClick={() => onChange([...tools, {
          key: `t${Date.now().toString(36)}`, name: '', icon: 'doc',
          kind: 'open', open: '/#/app/library',
        }])}>أضِف زرًّا</Button>
      </div>

      {tools.length === 0 && (
        <Alert tone="warn">لا أزرار — ستظهر اللوحةُ بزرّ السحب وحده.</Alert>
      )}

      {tools.map((t, i) => (
        <div key={t.key || i} className="mdd-col"
          style={{
            gap: 10, padding: 12, borderRadius: 'var(--mdd-r-md)',
            border: '1px solid var(--mdd-line)', background: 'var(--mdd-surface)',
          }}>
          <div className="mdd-row" style={{ gap: 8 }}>
            <span className="mdd-dim" style={{ fontSize: 12, minWidth: 18 }}>{i + 1}</span>
            <Input value={t.name ?? ''} placeholder="اسم الزرّ — مثال: شهادات الطلاب"
              onChange={(e) => patch(i, 'name', e.target.value)} style={{ flex: 1 }} />
            <Button auto size="sm" variant="ghost" disabled={i === 0}
              onClick={() => onChange(moveAt(tools, i, -1))} aria-label="ارفع">↑</Button>
            <Button auto size="sm" variant="ghost" disabled={i === tools.length - 1}
              onClick={() => onChange(moveAt(tools, i, 1))} aria-label="أنزِل">↓</Button>
            <Button auto size="sm" variant="danger"
              onClick={() => onChange(tools.filter((_, n) => n !== i))} aria-label="احذف">✕</Button>
          </div>

          <div className="mdd-grid mdd-grid--3" style={{ gap: 10 }}>
            <Field label="الأيقونة">
              <Select value={t.icon ?? 'doc'} onChange={(e) => patch(i, 'icon', e.target.value)}>
                {ICONS.map((ic) => <option key={ic} value={ic}>{ic}</option>)}
              </Select>
            </Field>
            <Field label="النوع">
              <Select value={t.kind ?? 'open'} onChange={(e) => patch(i, 'kind', e.target.value)}>
                <option value="open">يفتح صفحةً في مِداد</option>
                <option value="pull">يسحب الجدول المعروض</option>
              </Select>
            </Field>
            <Field label="يظهر في"
              help="اتركه «كلّ الصفحات» ما لم تكن الأداةُ خاصّةً بمنصّة.">
              <Select
                value={(t.on || []).join(',') || ''}
                onChange={(e) => patch(i, 'on', e.target.value ? e.target.value.split(',') : [])}>
                <option value="">كلّ الصفحات</option>
                <option value="noor">نور فقط</option>
                <option value="madrasati">مدرستي فقط</option>
                <option value="noor,madrasati">نور ومدرستي</option>
              </Select>
            </Field>
          </div>

          {t.kind !== 'pull' && (
            <Field label="الصفحة التي يفتحها"
              help="مسارٌ في مِداد يبدأ بـ /# — أو رابطٌ كامل.">
              <Input ltr value={t.open ?? ''} placeholder="/#/app/library"
                onChange={(e) => patch(i, 'open', e.target.value)} />
            </Field>
          )}

          <div className="mdd-grid mdd-grid--2" style={{ gap: 10 }}>
            <Field label="شارة (اختياريّة)" help="مثال: جديد">
              <Input value={t.badge ?? ''} onChange={(e) => patch(i, 'badge', e.target.value)} />
            </Field>
            <Field label="سطرٌ تحت الاسم" help="اتركه فارغًا فلا يظهر شيء.">
              <Input value={t.hint ?? ''} onChange={(e) => patch(i, 'hint', e.target.value)} />
            </Field>
          </div>
        </div>
      ))}
    </div>
  )
}

/** تبديلُ عنصرين — ولا تُمسّ المصفوفةُ الأصليّة، فيرى React تغيّرًا. */
function moveAt(arr: any[], i: number, d: -1 | 1): any[] {
  const j = i + d
  if (j < 0 || j >= arr.length) return arr
  const out = arr.slice()
  const tmp = out[i]; out[i] = out[j]; out[j] = tmp
  return out
}
