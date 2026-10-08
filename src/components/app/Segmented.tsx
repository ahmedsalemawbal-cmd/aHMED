import { useRef, type KeyboardEvent } from 'react';

export interface SegmentOption<T extends string> {
  value: T;
  label: string;
}

/**
 * Segmented control as a real radio group (NewLead1 «صفته», Message «النبرة»,
 * Settings «المظهر»): one tab stop, arrow keys move and select, RTL aware.
 */
export function Segmented<T extends string>({
  label,
  value,
  options,
  onChange,
  size = 'md',
}: {
  label: string;
  value: T | null;
  options: SegmentOption<T>[];
  onChange: (v: T) => void;
  size?: 'md' | 'lg';
}) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  const current = options.findIndex((o) => o.value === value);
  const focusIndex = current >= 0 ? current : 0;
  const move = (e: KeyboardEvent, i: number) => {
    const rtl = getComputedStyle(e.currentTarget).direction === 'rtl';
    const next = { ArrowDown: 1, ArrowUp: -1, ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1 }[e.key];
    if (next === undefined) return;
    e.preventDefault();
    const j = (i + next + options.length) % options.length;
    const opt = options[j];
    if (!opt) return;
    onChange(opt.value);
    refs.current[j]?.focus();
  };
  return (
    <div
      role="radiogroup"
      aria-label={label}
      className={`grid gap-[2px] rounded-md border-[1.5px] border-line-strong bg-surface-sunken p-[3px] ${size === 'lg' ? 'min-h-[52px]' : 'min-h-[52px]'}`}
      style={{ gridTemplateColumns: `repeat(${options.length.toString()}, minmax(0, 1fr))` }}
    >
      {options.map((o, i) => {
        const on = o.value === value;
        return (
          <button
            key={o.value}
            ref={(el) => {
              refs.current[i] = el;
            }}
            type="button"
            role="radio"
            aria-checked={on}
            tabIndex={i === focusIndex ? 0 : -1}
            onClick={() => {
              onChange(o.value);
            }}
            onKeyDown={(e) => {
              move(e, i);
            }}
            className={`app-seg min-h-[44px] rounded-[7px] border-0 text-body-sm ${on ? 'bg-surface-raised font-bold text-ink shadow-card' : 'bg-transparent font-normal text-ink-muted'}`}
          >
            {o.label}
          </button>
        );
      })}
    </div>
  );
}
