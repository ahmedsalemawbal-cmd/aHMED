import type { ReactNode } from 'react';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { AppShell, type Section } from './AppShell';
import { OfflineBanner } from './OfflineBanner';

/**
 * Frame of the full-screen flows (new lead, new visit, edit lead, message).
 * Phones and tablets: a focused screen without the bottom nav, as designed in
 * NewLead1–4 and Message, centred at a readable width on a tablet. Desktop: the
 * same flow in a wide card inside the shell, so the sidebar stays and the page
 * uses the screen instead of looking like a phone.
 *
 * The card clips its corners with `overflow: clip`, which keeps the flow's
 * sticky header and footer working against the window scroll.
 */
export function FlowFrame({ section, children, width = 'md' }: { section: Section; children: ReactNode; width?: 'md' | 'lg' }) {
  const desktop = useIsDesktop();
  if (desktop) {
    return (
      <AppShell section={section}>
        <div className="flex grow items-start justify-center px-10 py-8">
          <div
            data-flow-card
            className={`flex w-full flex-col overflow-clip rounded-lg border border-line bg-surface shadow-card ${width === 'lg' ? 'max-w-[1040px]' : 'max-w-[800px]'}`}
          >
            {children}
          </div>
        </div>
      </AppShell>
    );
  }
  return (
    <div className="flex min-h-dvh flex-col bg-surface text-ink">
      <OfflineBanner />
      <div className="mx-auto flex w-full max-w-[680px] grow flex-col">{children}</div>
    </div>
  );
}
