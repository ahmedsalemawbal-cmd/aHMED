import { useId, type ReactNode } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';

export type TriValue = 'yes' | 'partial' | 'no';

export interface TriSelectProps {
  label: ReactNode;
  value: TriValue | null;
  onChange?: (value: TriValue) => void;
  weight?: number | null;
  /** yes / no only (activity-specific items) */
  binary?: boolean;
  name?: string;
  className?: string;
}

const TRI: { v: TriValue; label: string; icon: 'check' | 'minus' | 'x' }[] = [
  { v: 'yes', label: 'نعم', icon: 'check' },
  { v: 'partial', label: 'جزئي', icon: 'minus' },
  { v: 'no', label: 'لا', icon: 'x' },
];

/** Real radio group: arrow keys move between options; no default value. */
export function TriSelect({ label, value, onChange, weight, binary, name, className }: TriSelectProps) {
  const autoName = useId();
  const groupName = name || `md-tri-${autoName}`;
  const options = binary ? TRI.filter((o) => o.v !== 'partial') : TRI;
  return (
    <fieldset className={cx('md-tri', className)}>
      <legend className="md-tri-label">
        <span>{label}</span>
        {weight != null ? <span className="md-tri-weight">{`${weight.toString()} نقاط`}</span> : null}
      </legend>
      <div className="md-tri-options">
        {options.map((o) => {
          const on = value === o.v;
          return (
            <label key={o.v} className={cx('md-tri-opt', `md-tri-${o.v}`, on && 'is-on')}>
              <input
                type="radio"
                name={groupName}
                value={o.v}
                checked={on}
                onChange={() => {
                  onChange?.(o.v);
                }}
              />
              <Icon name={o.icon} size={18} />
              <span>{o.label}</span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
