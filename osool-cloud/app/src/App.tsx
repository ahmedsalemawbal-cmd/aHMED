import { useEffect, useState } from 'react';
import { supabase } from './lib/supabase';
import { useMe } from './lib/session';
import Login from './screens/Login';
import Overview from './screens/Overview';
import { Loading } from './ui/kit';

/* ═══════════════════════════════════════════════════════════════
   القشرة: شريط جانبي وشاشة.

   الأقسام تُصفّى بالصلاحية — فمن لم يُمنح شاشةً لا يعرف أنها موجودة.
   وهذا إخفاءٌ للترتيب لا حمايةٌ للبيانات: الحماية في القاعدة، وهذا
   كي لا يرى الموظّف عشرين بابًا مغلقًا فيظنّ النظام معطّلًا.
   ═══════════════════════════════════════════════════════════════ */

type Section = { key: string; label: string; cap: string; view: () => React.ReactNode };

const SECTIONS: Section[] = [
  { key: 'overview', label: 'نظرة عامة', cap: 'sch_view_students', view: () => <Overview /> },
];

function Sidebar({ name, roleLabel, active, onPick, items, onOut }: {
  name: string; roleLabel: string; active: string;
  onPick: (k: string) => void; items: Section[]; onOut: () => void;
}) {
  return (
    <nav style={{
      width: 'var(--sch-sbw)', flex: '0 0 auto', background: 'var(--sch-chrome)',
      color: 'var(--sch-chrome-ink)', display: 'flex', flexDirection: 'column',
      padding: 'var(--sch-s4)', gap: 'var(--sch-s2)',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '6px 8px 14px' }}>
        <span style={{
          width: 34, height: 34, borderRadius: 10, background: 'var(--sch-accent)',
          display: 'grid', placeItems: 'center', fontWeight: 700, color: 'var(--sch-on-accent)',
        }}>
          أ
        </span>
        <b style={{ fontSize: 'var(--sch-t3)' }}>أُصول</b>
      </div>

      {items.map((s) => (
        <button
          key={s.key}
          onClick={() => onPick(s.key)}
          style={{
            font: 'inherit', fontSize: 'var(--sch-t2)', fontWeight: 600, textAlign: 'right',
            padding: '10px 12px', borderRadius: 'var(--sch-radius)', border: 'none',
            background: active === s.key ? 'var(--sch-nav-hover)' : 'transparent',
            color: 'var(--sch-chrome-ink)', cursor: 'pointer',
          }}
        >
          {s.label}
        </button>
      ))}

      <div style={{ marginTop: 'auto', borderTop: '1px solid var(--sch-nav-line)', paddingTop: 'var(--sch-s3)' }}>
        <div style={{ fontSize: 'var(--sch-t1)', fontWeight: 600 }}>{name}</div>
        <div style={{ fontSize: 'var(--sch-t0)', color: 'var(--sch-chrome-mut)' }}>{roleLabel}</div>
        <button
          onClick={onOut}
          style={{
            font: 'inherit', fontSize: 'var(--sch-t1)', marginTop: 8, padding: '6px 0',
            background: 'none', border: 'none', color: 'var(--sch-nav-danger)', cursor: 'pointer',
          }}
        >
          خروج
        </button>
      </div>
    </nav>
  );
}

export default function App() {
  const { me, loading, can } = useMe();
  const [active, setActive] = useState('overview');
  const [dark, setDark] = useState(
    () => window.matchMedia('(prefers-color-scheme: dark)').matches,
  );

  useEffect(() => {
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  }, [dark]);

  if (loading) return <Loading what="جارٍ التحقّق من الحساب" />;
  if (!me) return <Login />;

  const items = SECTIONS.filter((s) => can(s.cap));
  const current = items.find((s) => s.key === active) ?? items[0];

  return (
    <div style={{ display: 'flex', minHeight: '100%' }}>
      <Sidebar
        name={me.displayName}
        roleLabel={me.roleLabel}
        active={current?.key ?? ''}
        onPick={setActive}
        items={items}
        onOut={() => void supabase.auth.signOut()}
      />

      <main style={{ flex: 1, minWidth: 0, padding: 'var(--sch-s6)' }}>
        <button
          onClick={() => setDark((d) => !d)}
          title="تبديل الوضع الليلي"
          style={{
            font: 'inherit', float: 'left', padding: '6px 12px',
            borderRadius: 999, border: '1px solid var(--sch-line-strong)',
            background: 'var(--sch-surface)', color: 'var(--sch-ink-soft)', cursor: 'pointer',
          }}
        >
          {dark ? '☀︎' : '☾'}
        </button>

        {current
          ? current.view()
          : (
            <p style={{ color: 'var(--sch-muted)' }}>
              لم تُمنح أي شاشة بعد. راجع إدارة المدرسة.
            </p>
          )}
      </main>
    </div>
  );
}
