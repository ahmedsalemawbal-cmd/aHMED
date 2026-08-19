/* ═══════════════════════════════════════════════════════════════
   لبنات الواجهة.

   هذه هي نقطة الالتقاء مع كلود ديزاين: التصميم الذي يخرج منه يصف
   بطاقات وجداول وشارات — فإن كانت موجودة هنا بأسمائها ورموزها، صار
   تحويل التصميم إلى شاشة عاملة **لصقًا لا إعادة بناء**.

   ولذلك لا لون مكتوب هنا بقيمته: كلّها رموز من tokens.css، فتتبدّل
   الهوية كلّها من ملفّ واحد، ويعمل الوضع الليلي بلا سطر إضافي.
   ═══════════════════════════════════════════════════════════════ */

import type { CSSProperties, ReactNode } from 'react';

type Tone = 'accent' | 'success' | 'warn' | 'danger' | 'gold' | 'mute';

const TONE: Record<Tone, { bg: string; fg: string }> = {
  accent:  { bg: 'var(--sch-accent-soft)',  fg: 'var(--sch-accent-ink)' },
  success: { bg: 'var(--sch-success-soft)', fg: 'var(--sch-success)' },
  warn:    { bg: 'var(--sch-warn-soft)',    fg: 'var(--sch-warn)' },
  danger:  { bg: 'var(--sch-danger-soft)',  fg: 'var(--sch-danger)' },
  gold:    { bg: 'var(--sch-gold-soft)',    fg: 'var(--sch-gold-ink)' },
  mute:    { bg: 'var(--sch-surface-alt)',  fg: 'var(--sch-muted)' },
};

// ── بطاقة ────────────────────────────────────────────────────────

export function Card({ children, style, pad = true }: {
  children: ReactNode; style?: CSSProperties; pad?: boolean;
}) {
  return (
    <section
      style={{
        background: 'var(--sch-surface)',
        border: '1px solid var(--sch-line)',
        borderRadius: 'var(--sch-radius-lg)',
        boxShadow: 'var(--sch-e1)',
        padding: pad ? 'var(--sch-s5)' : 0,
        ...style,
      }}
    >
      {children}
    </section>
  );
}

// ── عنوان قسم ────────────────────────────────────────────────────

export function Title({ children, sub }: { children: ReactNode; sub?: ReactNode }) {
  return (
    <header style={{ marginBottom: 'var(--sch-s5)' }}>
      <h1 style={{
        margin: 0, fontSize: 'var(--sch-t5)', fontWeight: 700,
        lineHeight: 'var(--sch-lh-tight)', color: 'var(--sch-ink)',
      }}>
        {children}
      </h1>
      {sub && (
        <p style={{
          margin: 'var(--sch-s2) 0 0', fontSize: 'var(--sch-t2)',
          color: 'var(--sch-muted)', lineHeight: 'var(--sch-lh-snug)',
        }}>
          {sub}
        </p>
      )}
    </header>
  );
}

// ── شارة ─────────────────────────────────────────────────────────

export function Tag({ children, tone = 'mute' }: { children: ReactNode; tone?: Tone }) {
  const t = TONE[tone];
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 'var(--sch-s1)',
      padding: '3px 10px', borderRadius: 999,
      background: t.bg, color: t.fg,
      fontSize: 'var(--sch-t0)', fontWeight: 600, whiteSpace: 'nowrap',
    }}>
      {children}
    </span>
  );
}

// ── رقم مع وحدته ─────────────────────────────────────────────────
//
// الرقم والوحدة كتلة واحدة لا تنكسر: «١٬٢٥٠ ر.س» تُقرأ معًا، وفصلهما
// بسطرٍ يجعل المبلغ يبدو رقمين.

export function Stat({ label, value, unit, tone = 'accent', hint }: {
  label: string; value: ReactNode; unit?: string; tone?: Tone; hint?: string;
}) {
  return (
    <Card style={{ padding: 'var(--sch-s4) var(--sch-s5)' }}>
      <div style={{ fontSize: 'var(--sch-t0)', color: 'var(--sch-muted)', fontWeight: 600 }}>
        {label}
      </div>
      <div className="num" style={{
        display: 'inline-flex', alignItems: 'baseline', gap: 5,
        whiteSpace: 'nowrap', marginTop: 'var(--sch-s1)',
        fontSize: 'var(--sch-t5)', fontWeight: 700, color: TONE[tone].fg,
      }}>
        {value}
        {unit && <span style={{ fontSize: 'var(--sch-t1)', fontWeight: 600 }}>{unit}</span>}
      </div>
      {hint && (
        <div style={{ fontSize: 'var(--sch-t0)', color: 'var(--sch-muted)', marginTop: 2 }}>
          {hint}
        </div>
      )}
    </Card>
  );
}

// ── زرّ ───────────────────────────────────────────────────────────

export function Button({ children, onClick, tone = 'accent', type = 'button', disabled, wide }: {
  children: ReactNode; onClick?: () => void; tone?: Tone;
  type?: 'button' | 'submit'; disabled?: boolean; wide?: boolean;
}) {
  const solid = tone === 'accent';
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      style={{
        font: 'inherit', fontWeight: 600, fontSize: 'var(--sch-t2)',
        padding: '10px 20px', borderRadius: 'var(--sch-radius)',
        border: solid ? 'none' : '1px solid var(--sch-line-strong)',
        background: disabled ? 'var(--sch-surface-alt)'
          : solid ? 'var(--sch-accent)' : 'var(--sch-surface)',
        color: disabled ? 'var(--sch-muted)'
          : solid ? 'var(--sch-on-accent)' : TONE[tone].fg,
        cursor: disabled ? 'not-allowed' : 'pointer',
        width: wide ? '100%' : undefined,
        transition: 'filter .15s',
      }}
      onMouseOver={(e) => { if (!disabled) e.currentTarget.style.filter = 'brightness(.94)'; }}
      onMouseOut={(e) => { e.currentTarget.style.filter = 'none'; }}
    >
      {children}
    </button>
  );
}

// ── حقل ──────────────────────────────────────────────────────────
//
// التسمية عنصر <label> مربوط بالحقل لا نصٌّ فوقه: قارئ الشاشة يقرؤها،
// والنقر عليها يضع المؤشّر في الحقل.

export function Field({ label, ...rest }: {
  label: string;
} & React.InputHTMLAttributes<HTMLInputElement>) {
  const id = `f-${label.replace(/\s+/g, '-')}`;
  return (
    <label htmlFor={id} style={{ display: 'block', marginBottom: 'var(--sch-s4)' }}>
      <span style={{
        display: 'block', fontSize: 'var(--sch-t1)', fontWeight: 600,
        color: 'var(--sch-ink-soft)', marginBottom: 6,
      }}>
        {label}
      </span>
      <input
        id={id}
        {...rest}
        style={{
          font: 'inherit', fontSize: 'var(--sch-t2)', width: '100%',
          padding: '11px 14px', borderRadius: 'var(--sch-radius)',
          border: '1px solid var(--sch-line-strong)',
          background: 'var(--sch-surface)', color: 'var(--sch-ink)',
        }}
      />
    </label>
  );
}

// ── جدول ─────────────────────────────────────────────────────────

export function Table<T>({ rows, cols, empty = 'لا توجد بيانات بعد.' }: {
  rows: T[];
  cols: { key: string; head: string; cell: (r: T) => ReactNode; w?: string }[];
  empty?: string;
}) {
  if (!rows.length) {
    return (
      <div style={{
        padding: 'var(--sch-s7) var(--sch-s5)', textAlign: 'center',
        color: 'var(--sch-muted)', fontSize: 'var(--sch-t2)',
      }}>
        {empty}
      </div>
    );
  }

  return (
    // الجدول العريض يمرّر داخل حاويته — لا تمرّر الصفحة كلّها أفقيًّا
    <div style={{ overflowX: 'auto' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--sch-t2)' }}>
        <thead>
          <tr>
            {cols.map((c) => (
              <th key={c.key} style={{
                textAlign: 'right', padding: '10px 14px', width: c.w,
                fontSize: 'var(--sch-t1)', fontWeight: 600, color: 'var(--sch-muted)',
                borderBottom: '1px solid var(--sch-line-strong)', whiteSpace: 'nowrap',
              }}>
                {c.head}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              {cols.map((c) => (
                <td key={c.key} style={{
                  padding: '12px 14px', borderBottom: '1px solid var(--sch-line)',
                  color: 'var(--sch-ink)', verticalAlign: 'middle',
                }}>
                  {c.cell(r)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

// ── حالات ────────────────────────────────────────────────────────

export function Loading({ what = 'جارٍ التحميل' }: { what?: string }) {
  return (
    <div style={{ padding: 'var(--sch-s7)', textAlign: 'center', color: 'var(--sch-muted)' }}>
      {what}…
    </div>
  );
}

export function Problem({ children }: { children: ReactNode }) {
  return (
    <Card style={{ borderColor: 'var(--sch-danger)', background: 'var(--sch-danger-soft)' }}>
      <div style={{ color: 'var(--sch-danger)', fontWeight: 600 }}>{children}</div>
    </Card>
  );
}

export function Grid({ children, min = 190 }: { children: ReactNode; min?: number }) {
  return (
    <div style={{
      display: 'grid', gap: 'var(--sch-s4)',
      gridTemplateColumns: `repeat(auto-fit, minmax(${min}px, 1fr))`,
    }}>
      {children}
    </div>
  );
}
