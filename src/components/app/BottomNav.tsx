import { NavLink } from 'react-router-dom';
import { NavIcon } from './NavIcon';
import type { NavIconName } from './nav-icons';

export type MobileSection = 'today' | 'leads' | 'pipeline' | 'more';

const LEFT: { id: MobileSection; label: string; to: string; icon: NavIconName }[] = [
  { id: 'today', label: 'اليوم', to: '/', icon: 'today' },
  { id: 'leads', label: 'العملاء', to: '/leads', icon: 'leads' },
];
const RIGHT: { id: MobileSection; label: string; to: string; icon: NavIconName }[] = [
  { id: 'pipeline', label: 'Pipeline', to: '/pipeline', icon: 'pipeline' },
  { id: 'more', label: 'المزيد', to: '/more', icon: 'more' },
];

/** design/screens/BottomNav.dc.html: five items, «عميل جديد» raised in the middle. */
export function BottomNav({ active }: { active: MobileSection | null }) {
  const item = (it: (typeof LEFT)[number]) => {
    const on = it.id === active;
    return (
      <NavLink
        key={it.id}
        to={it.to}
        aria-current={on ? 'page' : undefined}
        className={`flex min-h-[56px] flex-col items-center justify-end gap-[2px] text-caption no-underline ${on ? 'font-bold text-ink' : 'font-normal text-ink-muted'}`}
      >
        <NavIcon name={it.icon} />
        <span className="leading-4">{it.label}</span>
      </NavLink>
    );
  };
  return (
    <nav
      aria-label="التنقل الرئيسي"
      className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 items-end border-t border-line bg-surface-raised px-2 pb-[calc(var(--space-2)+env(safe-area-inset-bottom,0px))]"
    >
      {LEFT.map(item)}
      <NavLink to="/leads/new" className="flex flex-col items-center gap-[2px] text-caption font-bold leading-4 text-ink no-underline">
        <span className="-mt-6 grid size-[56px] place-items-center rounded-full border-4 border-surface-raised bg-action text-on-action shadow-float">
          <NavIcon name="plus" size={26} strokeWidth={2.4} />
        </span>
        <span>عميل جديد</span>
      </NavLink>
      {RIGHT.map(item)}
    </nav>
  );
}
