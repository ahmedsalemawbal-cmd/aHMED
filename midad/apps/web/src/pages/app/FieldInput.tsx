import React from 'react'
import type { TemplateField } from '../../lib/types'
import { Field, Input, Select, Textarea, Button, IconButton } from '../../ui/kit'
import { IcPlus, IcTrash, IcSpark } from '../../ui/icons'
import { emptyRow } from '../../lib/template'

export default function FieldInput({ field, value, onChange, onImprove, onFocus, disabled }: {
  field: TemplateField
  value: any
  onChange: (v: any) => void
  onImprove?: () => void
  onFocus?: () => void
  disabled?: boolean
}) {
  const common = { onFocus, disabled, id: `f-${field.key}` }

  if (field.type === 'table') {
    const cols = field.columns || []
    const rows: any[] = Array.isArray(value) ? value : []
    const setRow = (i: number, key: string, v: string) => {
      const next = rows.map((r, ri) => (ri === i ? { ...r, [key]: v } : r))
      onChange(next)
    }
    return (
      <div className="mdd-field">
        <span className="mdd-field__label">{field.label}</span>
        <div className="mdd-table-wrap">
          <table className="mdd-table">
            <thead>
              <tr>
                <th style={{ width: 40 }}>م</th>
                {cols.map((c) => <th key={c.key}>{c.label}</th>)}
                <th style={{ width: 46 }} aria-label="حذف" />
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr><td colSpan={cols.length + 2} style={{ textAlign: 'center', color: 'var(--mdd-text-3)', fontSize: 12.5 }}>
                  لا صفوف بعد — أضف الصفّ الأوّل.
                </td></tr>
              )}
              {rows.map((r, i) => (
                <tr key={i}>
                  <td className="mdd-num">{i + 1}</td>
                  {cols.map((c) => (
                    <td key={c.key} data-label={c.label}>
                      {c.type === 'select' ? (
                        <Select value={r?.[c.key] ?? ''} onChange={(e) => setRow(i, c.key, e.target.value)} disabled={disabled}>
                          <option value="">—</option>
                          {(c.options || []).map((o) => <option key={o} value={o}>{o}</option>)}
                        </Select>
                      ) : (
                        <Input type={c.type === 'number' ? 'number' : c.type === 'date' ? 'date' : 'text'}
                          value={r?.[c.key] ?? ''} onChange={(e) => setRow(i, c.key, e.target.value)}
                          onFocus={onFocus} disabled={disabled} style={{ minHeight: 38, padding: '8px 10px' }} />
                      )}
                    </td>
                  ))}
                  <td>
                    <IconButton label="حذف الصفّ" onClick={() => onChange(rows.filter((_, ri) => ri !== i))} disabled={disabled}>
                      <IcTrash size={15} />
                    </IconButton>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Button size="sm" auto icon={<IcPlus size={14} />} disabled={disabled}
          onClick={() => onChange([...rows, emptyRow(cols)])} style={{ alignSelf: 'flex-start' }}>
          أضف صفًّا
        </Button>
        {field.help && <span className="mdd-field__help">{field.help}</span>}
      </div>
    )
  }

  if (field.type === 'textarea') {
    return (
      <div className="mdd-field">
        <div className="mdd-row mdd-row--between">
          <label className="mdd-field__label" htmlFor={`f-${field.key}`}>
            {field.label}{field.required && <span style={{ color: 'var(--mdd-danger-fg)' }}> *</span>}
          </label>
          {onImprove && (
            <Button size="sm" auto variant="soft" icon={<IcSpark size={13} />} onClick={onImprove} disabled={disabled}>حسّن</Button>
          )}
        </div>
        <Textarea {...common} rows={4} value={value ?? ''} placeholder={field.placeholder}
          onChange={(e) => onChange(e.target.value)} />
        {field.help && <span className="mdd-field__help">{field.help}</span>}
      </div>
    )
  }

  if (field.type === 'select' || field.type === 'radio') {
    if (field.type === 'radio' && (field.options || []).length <= 4) {
      return (
        <div className="mdd-field">
          <span className="mdd-field__label">{field.label}{field.required && <span style={{ color: 'var(--mdd-danger-fg)' }}> *</span>}</span>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
            {(field.options || []).map((o) => (
              <button key={o} type="button" className="mdd-chip" aria-pressed={value === o}
                onClick={() => onChange(o)} onFocus={onFocus} disabled={disabled}>{o}</button>
            ))}
          </div>
          {field.help && <span className="mdd-field__help">{field.help}</span>}
        </div>
      )
    }
    return (
      <Field label={field.label + (field.required ? ' *' : '')} help={field.help}>
        <Select {...common} value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
          <option value="">اختر…</option>
          {(field.options || []).map((o) => <option key={o} value={o}>{o}</option>)}
        </Select>
      </Field>
    )
  }

  return (
    <Field label={field.label + (field.required ? ' *' : '')} help={field.help}>
      <Input {...common}
        type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
        ltr={field.type === 'date' || field.type === 'number'}
        value={value ?? ''} placeholder={field.placeholder}
        onChange={(e) => onChange(e.target.value)} />
    </Field>
  )
}
