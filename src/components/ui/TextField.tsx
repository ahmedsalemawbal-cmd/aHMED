import { useId, type ChangeEventHandler, type InputHTMLAttributes, type ReactNode } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';

type Field = HTMLInputElement | HTMLTextAreaElement;

export interface TextFieldProps extends Omit<InputHTMLAttributes<Field>, 'onChange'> {
  label: ReactNode;
  hint?: ReactNode;
  /** replaces the hint; says what happened and how to fix it */
  error?: ReactNode;
  multiline?: boolean;
  rows?: number;
  /** unit shown at the end, e.g. 'ر.س' */
  suffix?: ReactNode;
  /** left-to-right input for phone numbers and URLs */
  ltr?: boolean;
  /** React's handler types are bivariant, so ChangeEvent<HTMLInputElement> handlers are accepted */
  onChange?: ChangeEventHandler<Field>;
}

export function TextField({ label, hint, error, multiline, rows, suffix, ltr, className, id, onChange, type, ...rest }: TextFieldProps) {
  const autoId = useId();
  const fieldId = id || `md-f-${autoId}`;
  const describedBy = error || hint ? `${fieldId}-d` : undefined;
  const common = {
    id: fieldId,
    className: 'md-input',
    dir: ltr ? 'ltr' : undefined,
    'aria-invalid': error ? true : undefined,
    'aria-describedby': describedBy,
  } as const;

  const input = multiline ? (
    <textarea rows={rows || 3} {...rest} {...common} onChange={onChange} />
  ) : (
    <input type={type} {...rest} {...common} onChange={onChange} />
  );

  return (
    <div className={cx('md-field', Boolean(error) && 'has-error', className)}>
      <label className="md-field-label" htmlFor={fieldId}>
        {label}
        {rest.required ? (
          <span className="md-req" aria-hidden="true">
            {' *'}
          </span>
        ) : null}
      </label>
      {suffix ? (
        <div className="md-input-wrap">
          {input}
          <span className="md-suffix">{suffix}</span>
        </div>
      ) : (
        input
      )}
      {describedBy ? (
        <p id={describedBy} className="md-field-help">
          {error ? <Icon name="alert" size={16} /> : null}
          {error || hint}
        </p>
      ) : null}
    </div>
  );
}
