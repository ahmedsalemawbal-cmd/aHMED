import type { ReactNode } from 'react';

/** Fixed bottom bar for full-screen flows: the main action in the bottom third (design-system.md). */
export function StickyFooter({ children, note }: { children: ReactNode; note?: ReactNode }) {
  return (
    <footer className="sticky bottom-0 z-20 flex flex-col gap-[6px] border-t border-line bg-surface-raised px-4 pb-[calc(var(--space-5)+env(safe-area-inset-bottom,0px))] pt-3 desk:px-8 desk:pb-5">
      <div className="flex gap-2">{children}</div>
      {note ? <span className="text-center text-caption text-ink-muted" aria-live="polite">{note}</span> : null}
    </footer>
  );
}
