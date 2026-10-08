import type { ReactNode } from 'react';

const STEP_LABELS = ['البيانات', 'التقييم', 'الملاحظات', 'الخدمات'] as const;

const CLOSE = 'M6 6l12 12M18 6L6 18';
/** «رجوع» chevron as drawn in NewLead2–4 (points to the right, the way back in RTL). */
const BACK = 'M9 6l6 6-6 6';

/** Header of NewLead1–4: close/back, title, «N من 4», progress bars. */
export function WizardHeader({
  step,
  title,
  onLead,
  children,
}: {
  step: 1 | 2 | 3 | 4;
  title: string;
  onLead: () => void;
  /** extra content under the bars (step 1: labels, step 2: ScoreBar) */
  children?: ReactNode;
}) {
  return (
    <header className="sticky top-0 z-20 flex flex-col gap-3 border-b border-line bg-surface-raised px-4 pb-3 pt-4">
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={onLead}
          aria-label={step === 1 ? 'إغلاق' : 'رجوع'}
          className="-ms-3 grid size-touch place-items-center border-0 bg-transparent text-ink"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={step === 1 ? CLOSE : BACK} />
          </svg>
        </button>
        <h1 className="m-0 truncate px-2 text-title-3 font-bold">{title}</h1>
        <span className="min-w-9 text-end text-body-sm text-ink-muted">{`${step.toString()} من 4`}</span>
      </div>
      <div className="grid grid-cols-4 gap-[6px]" role="progressbar" aria-label="خطوات عميل جديد" aria-valuemin={1} aria-valuemax={4} aria-valuenow={step} aria-valuetext={`الخطوة ${step.toString()} من 4: ${STEP_LABELS[step - 1]}`}>
        {[1, 2, 3, 4].map((i) => (
          <span key={i} className={`h-1 rounded-full ${i <= step ? 'bg-action' : 'bg-surface-sunken'}`} />
        ))}
      </div>
      {children}
    </header>
  );
}

export function StepLabels({ step }: { step: 1 | 2 | 3 | 4 }) {
  return (
    <div className="grid grid-cols-4 gap-[6px] text-caption leading-4 text-ink-muted" aria-hidden="true">
      {STEP_LABELS.map((l, i) => (
        <span key={l} className={i + 1 === step ? 'font-bold text-ink' : ''}>
          {l}
        </span>
      ))}
    </div>
  );
}

/** Fixed bottom bar: the main action in the bottom third (design-system.md). */
export function WizardFooter({ children, note }: { children: ReactNode; note?: string }) {
  return (
    <footer className="sticky bottom-0 z-20 flex flex-col gap-[6px] border-t border-line bg-surface-raised px-4 pb-[calc(var(--space-5)+env(safe-area-inset-bottom,0px))] pt-3">
      <div className="flex gap-2">{children}</div>
      {note ? <span className="text-center text-caption text-ink-muted" aria-live="polite">{note}</span> : null}
    </footer>
  );
}
