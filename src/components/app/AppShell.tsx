import type { ReactNode } from 'react';
import { useAuth } from '@/auth/AuthContext';
import { useNavData } from '@/data/nav-counts';
import { useRealtimeRefresh } from '@/data/realtime';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { BottomNav, type MobileSection } from './BottomNav';
import { DeskSidebar, type DeskSection } from './DeskSidebar';
import { OfflineBanner } from './OfflineBanner';

export type Section = 'today' | 'leads' | 'pipeline' | 'tasks' | 'numbers' | 'quotes' | 'more';

const MOBILE: Record<Section, MobileSection | null> = {
  today: 'today',
  leads: 'leads',
  pipeline: 'pipeline',
  tasks: 'today',
  numbers: 'more',
  quotes: 'more',
  more: 'more',
};
const DESK: Record<Section, DeskSection> = {
  today: 'today',
  leads: 'leads',
  pipeline: 'pipeline',
  tasks: 'tasks',
  numbers: 'numbers',
  quotes: 'quotes',
  more: 'settings',
};

/** Mobile: content + bottom nav. Desktop (≥1024px): right sidebar + content. */
export function AppShell({ section, children }: { section: Section; children: ReactNode }) {
  const desktop = useIsDesktop();
  const { session } = useAuth();
  const nav = useNavData();
  useRealtimeRefresh(Boolean(session));

  if (desktop) {
    return (
      <div className="flex min-h-dvh bg-surface text-ink">
        <DeskSidebar
          active={DESK[section]}
          counts={{ today: nav.data?.dueToday, leads: nav.data?.leads, tasksOverdue: nav.data?.overdue }}
          name={nav.data?.name ?? ''}
          brand={nav.data?.brand ?? null}
        />
        <div className="flex min-w-0 grow flex-col">
          <OfflineBanner />
          {children}
        </div>
      </div>
    );
  }
  return (
    <div className="min-h-dvh bg-surface text-ink">
      <OfflineBanner />
      <div className="pb-[calc(var(--bottom-nav-h)+var(--space-6)+env(safe-area-inset-bottom,0px))]">{children}</div>
      <BottomNav active={MOBILE[section]} />
    </div>
  );
}
