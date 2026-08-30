import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtNum } from '../../lib/format'
import {
  Alert, Badge, Button, Card, ErrorState, Field, Input, PageHead,
  Progress, SkeletonRows, Stat,
} from '../../ui/kit'
import { BarChart } from './AdminChart'

export default function AiUsage() {
  const { plans, toast } = useApp()
  const [cap, setCap] = useState<string>('')
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const start = new Date(); start.setDate(1); start.setHours(0, 0, 0, 0)
    const [usage, cfg, subs] = await Promise.all([
      supabase.from('ai_usage').select('subscriber_id,tokens_in,tokens_out,created_at').gte('created_at', start.toISOString()),
      supabase.from('platform_settings').select('value').eq('key', 'ai').maybeSingle(),
      supabase.from('subscribers').select('id,name,plan_id,ai_quota_override'),
    ])
    if (usage.error) throw new Error(usage.error.message)
    return {
      rows: (usage.data || []) as any[],
      cfg: (cfg.data?.value as any) || {},
      subs: (subs.data || []) as any[],
      monthStart: start,
    }
  }, [])

  const stats = useMemo(() => {
    const rows = data?.rows || []
    const calls = rows.length
    const tokens = rows.reduce((a, r) => a + (r.tokens_in || 0) + (r.tokens_out || 0), 0)
    const byDay = new Map<string, number>()
    const now = new Date()
    const days: { label: string; value: number }[] = []
    for (const r of rows) {
      const d = String(r.created_at).slice(0, 10)
      byDay.set(d, (byDay.get(d) || 0) + 1)
    }
    for (let d = 1; d <= now.getDate(); d++) {
      const key = new Date(now.getFullYear(), now.getMonth(), d).toISOString().slice(0, 10)
      days.push({ label: String(d), value: byDay.get(key) || 0 })
    }
    const bySub = new Map<string, number>()
    for (const r of rows) bySub.set(r.subscriber_id, (bySub.get(r.subscriber_id) || 0) + 1)
    const top = [...bySub.entries()].sort((a, b) => b[1] - a[1]).slice(0, 10)
    return { calls, tokens, days, top }
  }, [data])

  const capValue = Number(data?.cfg?.monthly_cap_calls ?? 20000)
  const pct = capValue > 0 ? (stats.calls / capValue) * 100 : 0

  const saveCap = async () => {
    const next = Number(cap)
    if (!next || next < 1) { toast('أدخل رقمًا صحيحًا', 'danger'); return }
    setBusy(true)
    const { error: e } = await supabase.from('platform_settings')
      .update({ value: { ...(data?.cfg || {}), monthly_cap_calls: next }, updated_at: new Date().toISOString() })
      .eq('key', 'ai')
    setBusy(false)
    if (e) { toast(e.message, 'danger'); return }
    toast('حُفظ السقف'); setCap(''); reload()
  }

  const quotaOf = (s: any) =>
    s.ai_quota_override ?? plans.find((p) => p.id === s.plan_id)?.ai_quota_monthly ?? 0

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead title="استعمال الذكاء الاصطناعيّ" sub="الفاتورة التي تنمو مع نجاح المنتج — والسقف صمّام أمانها." />

      {loading ? <SkeletonRows n={5} /> : (
        <>
          {pct >= 100 ? (
            <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
              <Alert tone="danger">بلغت المنصّة سقفها الشهريّ — التحسين موقوفٌ للجميع الآن حتى ترفع السقف أو يبدأ الشهر القادم.</Alert>
            </div>
          ) : pct >= 80 ? (
            <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
              <Alert tone="warn">بلغ الاستعمال <span className="mdd-num">{Math.round(pct)}</span>٪ من السقف الشهريّ.</Alert>
            </div>
          ) : null}

          <div className="mdd-grid mdd-grid--3" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
            <Stat label="نداءات هذا الشهر" value={fmtNum(stats.calls)} hint="كلّ نداءٍ تحسينُ حقلٍ واحد" />
            <Stat label="الرموز المستهلكة" value={fmtNum(stats.tokens)} hint="دخلًا وخرجًا" />
            <Card className="mdd-col" style={{ gap: 8 }}>
              <span className="mdd-stat__label">من السقف الشهريّ</span>
              <span className="mdd-stat__value">{Math.round(pct)}٪</span>
              <Progress value={pct} tone={pct >= 100 ? 'danger' : pct >= 80 ? 'warn' : undefined} />
              <span className="mdd-stat__hint">
                <span className="mdd-num">{fmtNum(stats.calls)}</span> من <span className="mdd-num">{fmtNum(capValue)}</span>
              </span>
            </Card>
          </div>

          <Card className="mdd-col" style={{ gap: 14, marginBlockEnd: 'var(--mdd-s-5)' }}>
            <h2 className="mdd-card__title">الاستعمال اليوميّ — هذا الشهر</h2>
            <BarChart data={stats.days} />
          </Card>

          <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
            <Card className="mdd-col">
              <h2 className="mdd-card__title">أعلى المستهلكين</h2>
              {stats.top.length === 0 ? (
                <p className="mdd-muted" style={{ fontSize: 13 }}>لا استعمال هذا الشهر بعد.</p>
              ) : (
                <div className="mdd-table-wrap mdd-table-wrap--cards">
                  <table className="mdd-table">
                    <thead><tr><th>المشترك</th><th>الباقة</th><th>نداءاته</th><th>حصّته</th><th>النسبة</th></tr></thead>
                    <tbody>
                      {stats.top.map(([sid, n]) => {
                        const s = data?.subs.find((x) => x.id === sid)
                        const quota = s ? quotaOf(s) : 0
                        const p = quota > 0 ? Math.round((n / quota) * 100) : 0
                        return (
                          <tr key={sid}>
                            <td data-label="المشترك">
                              <Link to={`/admin/subscriber/${sid}`} style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>
                                {s?.name || '—'}
                              </Link>
                            </td>
                            <td data-label="الباقة">{plans.find((p2) => p2.id === s?.plan_id)?.name_ar || '—'}</td>
                            <td data-label="نداءاته"><span className="mdd-num">{fmtNum(n)}</span></td>
                            <td data-label="حصّته"><span className="mdd-num">{fmtNum(quota)}</span></td>
                            <td data-label="النسبة">
                              <Badge tone={p >= 100 ? 'danger' : p >= 80 ? 'warn' : 'neutral'}>
                                <span className="mdd-num">{p}</span>٪
                              </Badge>
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>

            <Card className="mdd-col">
              <h2 className="mdd-card__title">السقف العامّ</h2>
              <p className="mdd-prose" style={{ fontSize: 13 }}>
                مشتركٌ واحد يكتب بلا توقّف يستطيع أن يستهلك فاتورة الشهر كلّها. حين يبلغ مجموع النداءات هذا السقف
                يتوقّف التحسين للجميع، ويظهر للمشترك سبب واضح — ولا يُخصم منه شيء.
              </p>
              <Field label="السقف الشهريّ (عدد النداءات)">
                <Input ltr type="number" value={cap} placeholder={String(capValue)}
                  onChange={(e) => setCap(e.target.value)} />
              </Field>
              <Button auto variant="primary" loading={busy} onClick={saveCap} disabled={!cap}
                style={{ alignSelf: 'flex-start' }}>احفظ السقف</Button>
            </Card>
          </div>
        </>
      )}
    </>
  )
}
