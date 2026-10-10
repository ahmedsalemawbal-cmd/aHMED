import { useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { PRIORITY, type Priority } from '@/components/ui/stages';
import { DEFAULT_DIR, DEFAULT_QUERY, type ListQuery, type SortKey, type Source } from '@/lib/leads-list';
import { SORT_NAME, sortChoices, SOURCE_LABEL } from './list-view';

const PRIORITIES: Priority[] = ['hot', 'warm', 'cold'];

export type SheetChoice = Pick<ListQuery, 'sort' | 'dir' | 'activity' | 'priority' | 'source'>;

function Pills<T extends string>({ name, legend, value, options, onChange }: { name: string; legend: string; value: T; options: { value: T; label: string }[]; onChange: (v: T) => void }) {
  return (
    <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
      <legend className="mb-2 p-0 text-[15px] font-bold leading-[22px]">{legend}</legend>
      <div className="flex flex-wrap gap-2">
        {options.map((o) => {
          const on = o.value === value;
          return (
            <label
              key={o.value}
              className={`app-seg flex min-h-[44px] items-center rounded-full border-[1.5px] px-4 text-body-sm has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus ${on ? 'border-ink bg-ink font-bold text-surface-raised' : 'border-line-strong bg-surface-raised text-ink'}`}
            >
              <input
                type="radio"
                name={name}
                value={o.value}
                checked={on}
                onChange={() => {
                  onChange(o.value);
                }}
                className="sr-only"
              />
              {o.label}
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}

/**
 * «ترتيب: …» on the phone: the three sorts of brief.md §5 and the filters
 * the chips do not cover (النشاط، الأولوية، المصدر). Applied with one button.
 */
export function SortSheet({
  initial,
  activities,
  onClose,
  onApply,
}: {
  initial: SheetChoice;
  activities: { id: string; name: string }[];
  onClose: () => void;
  onApply: (choice: SheetChoice) => void;
}) {
  const [c, setC] = useState<SheetChoice>(initial);
  const set = (patch: Partial<SheetChoice>) => {
    setC((prev) => ({ ...prev, ...patch }));
  };
  const sortOptions: { value: SortKey; label: string }[] = sortChoices(initial.sort).map((k) => ({ value: k, label: SORT_NAME[k] }));
  return (
    <BottomSheet
      open
      onClose={onClose}
      title="ترتيب وفلترة"
      actions={
        <>
          <Button
            variant="primary"
            block
            onClick={() => {
              onApply(c);
            }}
          >
            اعرض النتائج
          </Button>
          <Button
            variant="ghost"
            block
            onClick={() => {
              set({ activity: DEFAULT_QUERY.activity, priority: DEFAULT_QUERY.priority, source: DEFAULT_QUERY.source });
            }}
          >
            امسح الفلاتر
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        <Pills
          name="leads-sort"
          legend="الترتيب"
          value={c.sort}
          options={sortOptions}
          onChange={(sort) => {
            set({ sort, dir: DEFAULT_DIR[sort] });
          }}
        />
        {activities.length ? (
          <Pills
            name="leads-activity"
            legend="النشاط"
            value={c.activity}
            options={[{ value: 'all', label: 'الكل' }, ...activities.map((a) => ({ value: a.id, label: a.name }))]}
            onChange={(activity) => {
              set({ activity });
            }}
          />
        ) : null}
        <Pills<Priority | 'all'>
          name="leads-priority"
          legend="الأولوية"
          value={c.priority}
          options={[{ value: 'all', label: 'الكل' }, ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p].label }))]}
          onChange={(priority) => {
            set({ priority });
          }}
        />
        <Pills<Source | 'all'>
          name="leads-source"
          legend="المصدر"
          value={c.source}
          options={[
            { value: 'all', label: 'الكل' },
            { value: 'visit', label: SOURCE_LABEL.visit },
            { value: 'import', label: SOURCE_LABEL.import },
          ]}
          onChange={(source) => {
            set({ source });
          }}
        />
      </div>
    </BottomSheet>
  );
}
