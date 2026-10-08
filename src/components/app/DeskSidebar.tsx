import { NavLink } from 'react-router-dom';
import { LogoMark } from './LogoMark';
import { NavIcon } from './NavIcon';
import type { NavIconName } from './nav-icons';

export type DeskSection = 'today' | 'leads' | 'pipeline' | 'tasks' | 'numbers' | 'quotes' | 'settings';

export interface SidebarCounts {
  today?: number;
  leads?: number;
  /** overdue tasks: shown in warning colours */
  tasksOverdue?: number;
}

const ITEMS: { id: DeskSection; label: string; to: string | null; icon: NavIconName }[] = [
  { id: 'today', label: 'اليوم', to: '/', icon: 'today' },
  { id: 'leads', label: 'العملاء', to: '/leads', icon: 'leads' },
  { id: 'pipeline', label: 'Pipeline', to: '/pipeline', icon: 'pipeline' },
  { id: 'tasks', label: 'المهام', to: '/tasks', icon: 'tasks' },
  { id: 'numbers', label: 'لوحة الأرقام', to: null, icon: 'numbers' },
  { id: 'quotes', label: 'عروض الأسعار', to: '/quotes', icon: 'quotes' },
  { id: 'settings', label: 'الإعدادات', to: '/more', icon: 'settings' },
];

/** design/screens/DeskSidebar.dc.html — right-hand sidebar (inline-start in RTL). */
export function DeskSidebar({ active, counts, name, brand }: { active: DeskSection | null; counts: SidebarCounts; name: string; brand: string | null }) {
  const count = (id: DeskSection): { n: number; warn: boolean } | null => {
    if (id === 'today' && counts.today != null) return { n: counts.today, warn: false };
    if (id === 'leads' && counts.leads != null) return { n: counts.leads, warn: false };
    if (id === 'tasks' && counts.tasksOverdue) return { n: counts.tasksOverdue, warn: true };
    return null;
  };
  return (
    <aside className="sticky top-0 flex h-dvh w-sidebar shrink-0 flex-col gap-6 border-e border-line bg-surface-raised px-4 py-6 text-ink">
      <NavLink to="/" className="flex items-center gap-[10px] px-2 text-ink no-underline">
        <LogoMark small />
        <span className="text-title-2 font-bold leading-7">ميداني</span>
      </NavLink>
      <nav aria-label="الأقسام" className="flex grow flex-col gap-[2px]">
        {ITEMS.map((it) => {
          const on = it.id === active;
          const c = count(it.id);
          const cls = `flex min-h-touch items-center gap-3 rounded-md px-3 text-[15px] leading-[22px] no-underline ${on ? 'bg-surface-sunken font-bold text-ink' : 'font-normal text-ink-muted'}`;
          const body = (
            <>
              <NavIcon name={it.icon} size={20} />
              <span className="grow">{it.label}</span>
              {it.to === null ? <span className="text-caption text-ink-muted">قريباً</span> : null}
              {c ? (
                <span
                  className={`rounded-full px-2 py-px text-caption font-bold tabular-nums ${c.warn ? 'bg-warning-soft text-warning' : 'bg-surface-sunken text-ink-muted'}`}
                >
                  {c.n}
                </span>
              ) : null}
            </>
          );
          return it.to === null ? (
            <span key={it.id} className={`${cls} cursor-not-allowed opacity-70`} aria-disabled="true">
              {body}
            </span>
          ) : (
            <NavLink key={it.id} to={it.to} aria-current={on ? 'page' : undefined} className={cls}>
              {body}
            </NavLink>
          );
        })}
      </nav>
      <div className="flex items-center gap-[10px] border-t border-line px-2 pt-3">
        <span aria-hidden="true" className="grid size-9 place-items-center rounded-full bg-action font-bold text-on-action">
          {name.slice(0, 1) || 'م'}
        </span>
        <span className="flex flex-col text-body-sm leading-5">
          <b>{name || 'حسابي'}</b>
          {brand ? <span className="text-label-sm font-normal text-ink-muted">{brand}</span> : null}
        </span>
      </div>
    </aside>
  );
}
