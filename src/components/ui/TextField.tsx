import { useId, type InputHTMLAttributes, type ReactNode, type TextareaHTMLAttributes } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';

type NativeInput = Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> &
  Pick<TextareaHTMLAttributes<HTMLTextAreaElement>, 'rows'>;

export interface TextFieldProps extends NativeInput {
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
  onChange?: (event: { target: { value: string } }) => void;
}

export function TextField({ label, hint, error, multiline, rows, suffix, ltr, className, id, required, onChange, ...rest }: TextFieldProps) {
  const autoId = useId();
  const fieldId = id ?? `md-f-${autoId}`;
  const describedBy = error || hint ? `${fieldId}-d` : undefined;
  const common = {
    id: fieldId,
    className: 'md-input',
    dir: ltr ? 'ltr' : undefined,
    required,
    'aria-invalid': error ? true : undefined,
    'aria-describedby': describedBy,
  } as const;

  const input = multiline ? (
    <textarea
      {...common}
      rows={rows ?? 3}
      name={rest.name}
      value={rest.value}
      defaultValue={rest.defaultValue}
      placeholder={rest.placeholder}
      maxLength={rest.maxLength}
      disabled={rest.disabled}
      autoComplete={rest.autoComplete}
      onChange={onChange}
    />
  ) : (
    <input {...rest} {...common} onChange={onChange} />
  );

  return (
    <div className={cx('md-field', Boolean(error) && 'has-error', className)}>
      <label className="md-field-label" htmlFor={fieldId}>
        {label}
        {required ? (
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
          {error ?? hint}
        </p>
      ) : null}
    </div>
  );
}
