import { useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import type { CatalogService } from '@/lib/suggest';
import { priceText } from './helpers';
import type { Derived } from './payload';

const INFO = 'M12 11v6M12 7.5v.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z';
const TICK = 'M5 12.5l4.5 4.5L19 7.5';

function servicesWord(n: number) {
  if (n === 0) return 'لا خدمات محددة';
  if (n === 1) return 'خدمة واحدة محددة';
  if (n === 2) return 'خدمتان محددتان';
  return `${n.toString()} خدمات محددة`;
}

export function Step4Services({
  x,
  catalog,
  expectedValue,
  onToggle,
  onAdd,
  onExpected,
}: {
  x: Derived;
  catalog: CatalogService[];
  expectedValue: string | null;
  onToggle: (serviceId: string, selected: boolean) => void;
  onAdd: (serviceId: string) => void;
  onExpected: (v: string) => void;
}) {
  const [adding, setAdding] = useState(false);
  const selectedCount = x.shown.filter((s) => s.selected).length;
  const available = catalog.filter((s) => s.isActive && !x.shown.some((y) => y.service.id === s.id));
  return (
    <div className="flex flex-col gap-[18px]">
      <p className="m-0 flex gap-[10px] rounded-md bg-surface-sunken px-[14px] py-3 text-body-sm text-ink-muted">
        <svg className="mt-[1px] flex-none" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d={INFO} />
        </svg>
        <span>{`اخترناها من نقاط ضعف التقييم ونشاط «${x.activity?.name ?? ''}». ألغِ أو أضف ما يناسب.`}</span>
      </p>

      <div role="group" aria-label="الخدمات" className="flex flex-col gap-[10px]">
        {x.shown.length === 0 ? <p className="m-0 text-body-sm text-ink-muted">لا خدمات مقترحة بعد. أضف خدمة من الكتالوج.</p> : null}
        {x.shown.map((s) => (
          <button
            key={s.service.id}
            type="button"
            role="checkbox"
            aria-checked={s.selected}
            onClick={() => {
              onToggle(s.service.id, !s.selected);
            }}
            className={`flex min-h-[72px] items-center gap-3 rounded-lg bg-surface-raised px-[14px] py-3 text-start ${s.selected ? 'border-2 border-ink' : 'border border-line opacity-85'}`}
          >
            <span
              className={`grid size-[26px] flex-none place-items-center rounded-[7px] ${s.selected ? 'bg-action text-on-action' : 'border-2 border-line-strong bg-surface-raised text-transparent'}`}
              aria-hidden="true"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <path d={TICK} />
              </svg>
            </span>
            <span className="flex grow flex-col gap-[2px]">
              <span className="text-label font-bold text-ink">{s.service.name}</span>
              <span className="text-label-sm font-normal text-ink-muted">{s.reason}</span>
            </span>
            <span className="whitespace-nowrap text-end text-label-sm font-normal text-ink-muted">{priceText(s.service)}</span>
          </button>
        ))}
      </div>

      <Button
        variant="ghost"
        block
        icon="plus"
        className="border-[1.5px] border-dashed border-line-strong"
        disabled={available.length === 0}
        onClick={() => {
          setAdding(true);
        }}
      >
        أضف خدمة من الكتالوج
      </Button>

      <TextField
        label="القيمة المتوقعة"
        ltr
        inputMode="numeric"
        suffix="ر.س / شهرياً"
        value={expectedValue ?? (x.expected ? x.expected.toString() : '')}
        hint={`${servicesWord(selectedCount)} · تدخل أول خدمتين في الرسالة`}
        onChange={(e) => {
          onExpected(e.target.value.replace(/[^\d٠-٩]/g, ''));
        }}
      />

      <BottomSheet
        open={adding}
        onClose={() => {
          setAdding(false);
        }}
        title="أضف خدمة"
        actions={available.map((s) => (
          <Button
            key={s.id}
            variant="secondary"
            block
            onClick={() => {
              onAdd(s.id);
              setAdding(false);
            }}
          >
            {`${s.name} · ${priceText(s)}`}
          </Button>
        ))}
      />
    </div>
  );
}
