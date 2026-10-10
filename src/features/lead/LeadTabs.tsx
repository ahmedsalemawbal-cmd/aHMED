import { useRef, type KeyboardEvent, type ReactNode } from 'react';
import { tabKeyTarget, type LeadTab } from './text';

/**
 * Real tabs with the look of Lead.dc.html / DeskLead.dc.html: a 3px ink line
 * under the active tab. One tab stop; arrow keys follow the reading direction.
 */
export function LeadTabs({
  tabs,
  active,
  desktop,
  onChange,
  children,
}: {
  tabs: { id: LeadTab; label: string }[];
  active: LeadTab;
  desktop: boolean;
  onChange: (tab: LeadTab) => void;
  children: ReactNode;
}) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  const onKey = (e: KeyboardEvent<HTMLButtonElement>, i: number) => {
    const rtl = getComputedStyle(e.currentTarget).direction === 'rtl';
    const j = tabKeyTarget(e.key, i, tabs.length, rtl);
    const tab = j === null ? undefined : tabs[j];
    if (j === null || !tab) return;
    e.preventDefault();
    onChange(tab.id);
    refs.current[j]?.focus();
  };
  return (
    <>
      <div
        role="tablist"
        aria-label="أقسام صفحة العميل"
        className={`flex gap-1 overflow-x-auto border-b border-line ${desktop ? 'px-4' : '-mx-4 px-4'}`}
      >
        {tabs.map((t, i) => {
          const on = t.id === active;
          return (
            <button
              key={t.id}
              ref={(el) => {
                refs.current[i] = el;
              }}
              id={`lead-tab-${t.id}`}
              type="button"
              role="tab"
              aria-selected={on}
              // only the shown panel is in the page; a reference to a missing id is invalid
              aria-controls={on ? `lead-panel-${t.id}` : undefined}
              tabIndex={on ? 0 : -1}
              onClick={() => {
                onChange(t.id);
              }}
              onKeyDown={(e) => {
                onKey(e, i);
              }}
              className={`-mb-px flex-none whitespace-nowrap border-0 border-b-[3px] border-solid bg-transparent text-[15px] ${desktop ? 'min-h-[52px] px-[14px]' : 'min-h-touch px-3'} ${on ? 'border-ink font-bold text-ink' : 'border-transparent font-normal text-ink-muted'}`}
            >
              {t.label}
            </button>
          );
        })}
      </div>
      <div id={`lead-panel-${active}`} role="tabpanel" aria-labelledby={`lead-tab-${active}`} tabIndex={0} className={desktop ? 'p-6' : 'pt-0'}>
        {children}
      </div>
    </>
  );
}
