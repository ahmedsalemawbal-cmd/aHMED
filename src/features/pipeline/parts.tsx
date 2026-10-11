import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';

/** No leads at all: the pipeline starts in the field. */
export function PipelineEmpty() {
  const navigate = useNavigate();
  return (
    <EmptyState
      icon="store"
      title="لا عملاء بعد"
      message="سجّل أول محل من الزيارة ليظهر في Pipeline."
      action={
        <Button
          variant="primary"
          icon="plus"
          onClick={() => {
            void navigate('/leads/new');
          }}
        >
          عميل جديد
        </Button>
      }
    />
  );
}

/** Leads exist but none of the chosen activity. */
export function NoActivityMatch({ onClear }: { onClear: () => void }) {
  return (
    <EmptyState
      icon="store"
      title="لا نتائج"
      message="لا عميل بهذا النشاط. امسح الفلتر لعرض كل العملاء."
      action={
        <Button variant="secondary" onClick={onClear}>
          امسح الفلتر
        </Button>
      }
    />
  );
}

/** «النشاط: الكل ⌄»: the design's filter button holding a native select (keyboard and screen readers for free). */
export function ActivitySelect({ value, options, onChange }: { value: string; options: { id: string; name: string }[]; onChange: (id: string) => void }) {
  const all = [{ id: 'all', name: 'الكل' }, ...options];
  if (value !== 'all' && !options.some((o) => o.id === value)) all.push({ id: value, name: 'غير موجود' });
  const current = all.find((o) => o.id === value)?.name ?? 'الكل';
  return (
    <label className="relative flex min-h-control-sm cursor-pointer items-center gap-[6px] rounded-md border-[1.5px] border-line-strong bg-surface-raised px-3 text-body-sm text-ink has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus">
      <span className="text-ink-muted">النشاط:</span>
      <b>{current}</b>
      <select
        aria-label="النشاط"
        value={value}
        onChange={(e) => {
          onChange(e.target.value);
        }}
        className="absolute inset-0 cursor-pointer opacity-0"
      >
        {all.map((o) => (
          <option key={o.id} value={o.id}>
            {o.name}
          </option>
        ))}
      </select>
    </label>
  );
}
