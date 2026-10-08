import { useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { formatDayLong } from '@/lib/dates';
import { postponeTo, toDateInput, type PostponeOption } from '@/lib/postpone';

/** «أجّل»: غداً، بعد 3 أيام، أو تاريخ محدد (brief.md §8). */
export function PostponeSheet({
  open,
  due,
  onClose,
  onConfirm,
}: {
  open: boolean;
  due: Date;
  onClose: () => void;
  onConfirm: (to: Date) => void;
}) {
  const now = new Date();
  const [date, setDate] = useState(toDateInput(new Date(now.getTime() + 7 * 86_400_000)));
  const [error, setError] = useState<string | null>(null);
  const pick = (o: PostponeOption) => {
    try {
      const to = postponeTo(due, o, now);
      if (to.getTime() <= now.getTime()) {
        setError('التاريخ مضى. اختر يوماً قادماً.');
        return;
      }
      onConfirm(to);
    } catch {
      setError('التاريخ غير مكتمل. اختره من التقويم.');
    }
  };
  return (
    <BottomSheet
      open={open}
      onClose={onClose}
      title="أجّل المهمة"
      actions={
        <>
          <Button
            variant="secondary"
            block
            onClick={() => {
              pick({ kind: 'tomorrow' });
            }}
          >
            {`غداً · ${formatDayLong(postponeTo(due, { kind: 'tomorrow' }, now))}`}
          </Button>
          <Button
            variant="secondary"
            block
            onClick={() => {
              pick({ kind: 'in3' });
            }}
          >
            {`بعد 3 أيام · ${formatDayLong(postponeTo(due, { kind: 'in3' }, now))}`}
          </Button>
          <div className="flex items-end gap-2">
            <TextField
              className="grow"
              label="تاريخ محدد"
              type="date"
              ltr
              value={date}
              min={toDateInput(now)}
              error={error ?? undefined}
              onChange={(e) => {
                setDate(e.target.value);
                setError(null);
              }}
            />
            <Button
              variant="primary"
              onClick={() => {
                pick({ kind: 'date', date });
              }}
            >
              أجّل
            </Button>
          </div>
        </>
      }
    />
  );
}
