import type { ReactNode } from 'react';

/** «عميل جديد» steps (NewLead1–4). The visit of a registered lead passes its own three. */
const LEAD_STEPS = ['البيانات', 'التقييم', 'الملاحظات', 'الخدمات'] as const;

const CLOSE = 'M6 6l12 12M18 6L6 18';
/** «رجوع» chevron as drawn in NewLead2–4 (points to the right, the way back in RTL). */
const BACK = 'M9 6l6 6-6 6';

function columns(n: number) {
  return { gridTemplateColumns: `repeat(${n.toString()}, minmax(0, 1fr))` };
}

/** Header of NewLead1–4: close/back, title, «N من 4», progress bars. */
export function WizardHeader({
  step,
  title,
  onLead,
  children,
  steps = LEAD_STEPS,
  label = 'خطوات عميل جديد',
}: {
  /** 1-based */
  step: number;
  title: string;
  onLead: () => void;
  /** extra content under the bars (step 1: labels, step 2: ScoreBar) */
  children?: ReactNode;
  steps?: readonly string[];
  /** accessible name of the progress bar */
  label?: string;
}) {
  const total = steps.length;
  return (
    <header className="sticky top-0 z-20 flex flex-col gap-3 border-b border-line bg-surface-raised px-4 pb-3 pt-4 desk:px-8 desk:pt-5">
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={onLead}
          aria-label={step === 1 ? 'إغلاق' : 'رجوع'}
          className="-ms-3 grid size-touch flex-none place-items-center border-0 bg-transparent text-ink"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={step === 1 ? CLOSE : BACK} />
          </svg>
        </button>
        <h1 className="m-0 min-w-0 truncate px-2 text-title-3 font-bold">{title}</h1>
        <span className="min-w-9 flex-none text-end text-body-sm text-ink-muted">{`${step.toString()} من ${total.toString()}`}</span>
      </div>
      <div
        className="grid gap-[6px]"
        style={columns(total)}
        role="progressbar"
        aria-label={label}
        aria-valuemin={1}
        aria-valuemax={total}
        aria-valuenow={step}
        aria-valuetext={`الخطوة ${step.toString()} من ${total.toString()}: ${steps[step - 1] ?? ''}`}
      >
        {steps.map((s, i) => (
          <span key={s} className={`h-1 rounded-full ${i < step ? 'bg-action' : 'bg-surface-sunken'}`} />
        ))}
      </div>
      {children}
    </header>
  );
}

export function StepLabels({ step, steps = LEAD_STEPS }: { step: number; steps?: readonly string[] }) {
  return (
    <div className="grid gap-[6px] text-caption leading-4 text-ink-muted" style={columns(steps.length)} aria-hidden="true">
      {steps.map((l, i) => (
        <span key={l} className={i + 1 === step ? 'font-bold text-ink' : ''}>
          {l}
        </span>
      ))}
    </div>
  );
}

/** NewLead1–4 footer. */
export { StickyFooter as WizardFooter } from '@/components/app/StickyFooter';
