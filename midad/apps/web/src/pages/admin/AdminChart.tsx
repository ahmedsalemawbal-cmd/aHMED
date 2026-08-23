import React from 'react'

/** رسوم بيانية مرسومة يدويًّا — بلا مكتبة. الزمن يمشي من اليمين إلى اليسار. */
export interface ChartPoint { label: string; value: number }

const W = 720

export function niceMax(v: number): number {
  if (!isFinite(v) || v <= 0) return 1
  const mag = Math.pow(10, Math.floor(Math.log10(v)))
  const s = v / mag
  const m = s <= 1 ? 1 : s <= 2 ? 2 : s <= 5 ? 5 : 10
  return m * mag
}

function ticks(max: number, n = 4): number[] {
  const out: number[] = []
  for (let i = 0; i <= n; i++) out.push((max / n) * i)
  return out
}

/** آخر n شهرًا — الأقدم أوّلًا. */
export function lastMonths(n: number): { key: string; label: string; start: Date; end: Date }[] {
  const out: { key: string; label: string; start: Date; end: Date }[] = []
  const now = new Date()
  for (let i = n - 1; i >= 0; i--) {
    const start = new Date(now.getFullYear(), now.getMonth() - i, 1, 0, 0, 0, 0)
    const end = new Date(now.getFullYear(), now.getMonth() - i + 1, 1, 0, 0, 0, 0)
    out.push({
      key: `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}`,
      label: `${start.getMonth() + 1}/${String(start.getFullYear()).slice(2)}`,
      start, end,
    })
  }
  return out
}

export function monthKey(v: string | Date | null | undefined): string {
  if (!v) return ''
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return ''
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

/** أعمدة — الأقدم على اليمين. */
export function BarChart({ data, height = 190, fmt, label, tone = 'accent' }: {
  data: ChartPoint[]; height?: number; fmt?: (n: number) => string; label?: string
  tone?: 'accent' | 'success' | 'info'
}) {
  const f = fmt || ((n: number) => String(Math.round(n)))
  const pad = { t: 12, r: 54, b: 28, l: 8 }
  const plotW = W - pad.l - pad.r
  const plotH = height - pad.t - pad.b
  const max = niceMax(Math.max(0, ...data.map((d) => d.value)))
  const step = plotW / Math.max(1, data.length)
  const bw = Math.min(40, step * 0.56)
  const y = (v: number) => pad.t + plotH - (v / max) * plotH
  const fill = tone === 'success' ? 'var(--mdd-success-fg)' : tone === 'info' ? 'var(--mdd-info-fg)' : 'var(--mdd-accent)'

  return (
    <svg viewBox={`0 0 ${W} ${height}`} width="100%" height={height} role="img"
      aria-label={label || 'رسم بيانيّ'} style={{ display: 'block' }}>
      {ticks(max).map((t, i) => (
        <g key={i}>
          <line x1={pad.l} x2={W - pad.r} y1={y(t)} y2={y(t)} stroke="var(--mdd-border)" strokeWidth={1} />
          <text className="mdd-num" x={W - pad.r + 9} y={y(t) + 4} fontSize={11} fill="var(--mdd-text-3)">{f(t)}</text>
        </g>
      ))}
      {data.map((d, i) => {
        const cx = W - pad.r - (i + 0.5) * step
        const h = d.value > 0 ? Math.max(3, (d.value / max) * plotH) : 0
        return (
          <g key={d.label + i}>
            {h > 0 && (
              <rect x={cx - bw / 2} y={pad.t + plotH - h} width={bw} height={h} rx={4} fill={fill} opacity={0.92}>
                <title>{`${d.label} — ${f(d.value)}`}</title>
              </rect>
            )}
            <text className="mdd-num" x={cx} y={height - 8} fontSize={10.5} textAnchor="middle" fill="var(--mdd-text-3)">{d.label}</text>
          </g>
        )
      })}
      <line x1={pad.l} x2={W - pad.r} y1={pad.t + plotH} y2={pad.t + plotH} stroke="var(--mdd-border-strong)" strokeWidth={1} />
    </svg>
  )
}

/** خطّ بمساحة — الأقدم على اليمين. */
export function LineChart({ data, height = 190, fmt, label, everyLabel = 1 }: {
  data: ChartPoint[]; height?: number; fmt?: (n: number) => string; label?: string; everyLabel?: number
}) {
  const f = fmt || ((n: number) => String(Math.round(n)))
  const pad = { t: 12, r: 54, b: 28, l: 10 }
  const plotW = W - pad.l - pad.r
  const plotH = height - pad.t - pad.b
  const max = niceMax(Math.max(0, ...data.map((d) => d.value)))
  const n = Math.max(1, data.length)
  const x = (i: number) => W - pad.r - (n === 1 ? plotW / 2 : (i * plotW) / (n - 1))
  const y = (v: number) => pad.t + plotH - (v / max) * plotH
  const line = data.map((d, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(d.value).toFixed(1)}`).join(' ')
  const area = data.length
    ? `${line} L${x(data.length - 1).toFixed(1)},${(pad.t + plotH).toFixed(1)} L${x(0).toFixed(1)},${(pad.t + plotH).toFixed(1)} Z`
    : ''

  return (
    <svg viewBox={`0 0 ${W} ${height}`} width="100%" height={height} role="img"
      aria-label={label || 'رسم بيانيّ'} style={{ display: 'block' }}>
      {ticks(max).map((t, i) => (
        <g key={i}>
          <line x1={pad.l} x2={W - pad.r} y1={y(t)} y2={y(t)} stroke="var(--mdd-border)" strokeWidth={1} />
          <text className="mdd-num" x={W - pad.r + 9} y={y(t) + 4} fontSize={11} fill="var(--mdd-text-3)">{f(t)}</text>
        </g>
      ))}
      {area && <path d={area} fill="var(--mdd-accent)" opacity={0.14} />}
      {line && <path d={line} fill="none" stroke="var(--mdd-accent)" strokeWidth={2.2} strokeLinejoin="round" strokeLinecap="round" />}
      {data.map((d, i) => (
        <g key={d.label + i}>
          <circle cx={x(i)} cy={y(d.value)} r={2.8} fill="var(--mdd-accent)">
            <title>{`${d.label} — ${f(d.value)}`}</title>
          </circle>
          {i % everyLabel === 0 && (
            <text className="mdd-num" x={x(i)} y={height - 8} fontSize={10} textAnchor="middle" fill="var(--mdd-text-3)">{d.label}</text>
          )}
        </g>
      ))}
      <line x1={pad.l} x2={W - pad.r} y1={pad.t + plotH} y2={pad.t + plotH} stroke="var(--mdd-border-strong)" strokeWidth={1} />
    </svg>
  )
}
