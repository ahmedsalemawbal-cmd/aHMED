import { useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { formatDayLong } from '@/lib/dates';
import { toDateInput } from '@/lib/postpone';
import { retryAt, type RetryOption } from '@/lib/schedule';
import { LOST_REASON_LABEL, type LostReason } from '@/lib/timeline';

const REASONS = Object.keys(LOST_REASON_LABEL) as LostReason[];
const RETRY: { value: RetryOption; label: string }[] = [
  { value: 'none', label: 'بدون' },
  { value: '30', label: 'بعد شهر' },
  { value: '90', label: 'بعد 3 أشهر' },
  { value: 'date', label: 'تاريخ' },
];

export interface LostInput {
  reason: LostReason;
  note: string;
  retryAt: Date | null;
}

/** Flow 4: a closed reason, an optional note, and an optional «أعد المحاولة» date. */
export function LostSheet({
  open,
  title,
  busy,
  onClose,
  onSubmit,
}: {
  open: boolean;
  /** «نقل مطعم ريدان إلى خسارة» or «نقل 3 عملاء إلى خسارة» */
  title: string;
  busy?: boolean;
  onClose: () => void;
  onSubmit: (input: LostInput) => void;
}) {
  const [now] = useState(() => new Date());
  const [reason, setReason] = useState<LostReason | null>(null);
  const [note, setNote] = useState('');
  const [retry, setRetry] = useState<RetryOption>('none');
  const [date, setDate] = useState(() => toDateInput(new Date(now.getTime() + 30 * 86_400_000)));
  const [error, setError] = useState<{ reason?: string; date?: string }>({});
  const when = retryAt(retry, now, date);

  const submit = () => {
    if (!reason) {
      setError({ reason: 'اختر سبب الخسارة.' });
      return;
    }
    if (retry === 'date' && (!when || when.getTime() <= now.getTime())) {
      setError({ date: 'اختر تاريخاً قادماً لإعادة المحاولة.' });
      return;
    }
    onSubmit({ reason, note: note.trim(), retryAt: when });
  };

  return (
    <BottomSheet
      open={open}
      onClose={busy ? undefined : onClose}
      title={title}
      actions={
        <>
          <Button variant="danger" block loading={busy} onClick={submit}>
            {busy ? 'جارٍ النقل' : 'انقل إلى خسارة'}
          </Button>
          <Button variant="ghost" block disabled={busy} onClick={onClose}>
            إلغاء
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        <fieldset className="m-0 flex flex-col gap-2 border-0 p-0" aria-describedby={error.reason ? 'lost-reason-err' : undefined}>
          <legend className="mb-2 p-0 text-[15px] font-bold leading-[22px]">
            السبب <span className="text-danger" aria-hidden="true">*</span>
          </legend>
          <div className="grid grid-cols-2 gap-2">
            {REASONS.map((r) => (
              <label
                key={r}
                className={`flex min-h-touch cursor-pointer items-center gap-2 rounded-md border-[1.5px] px-3 text-body-sm ${reason === r ? 'border-ink bg-ink font-bold text-surface-raised' : 'border-line-strong bg-surface-raised text-ink'}`}
              >
                <input
                  type="radio"
                  name="lost-reason"
                  value={r}
                  checked={reason === r}
                  onChange={() => {
                    setReason(r);
                    setError({});
                  }}
                  className="sr-only"
                />
                {LOST_REASON_LABEL[r]}
              </label>
            ))}
          </div>
          {error.reason ? (
            <p id="lost-reason-err" className="m-0 text-label-sm text-danger" role="alert">
              {error.reason}
            </p>
          ) : null}
        </fieldset>

        <TextField
          label="ملاحظة"
          hint="اختياري. مثلاً: يرجع بعد رمضان."
          value={note}
          maxLength={200}
          onChange={(e) => {
            setNote(e.target.value);
          }}
        />

        <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
          <legend className="mb-2 p-0 text-[15px] font-bold leading-[22px]">أعد المحاولة</legend>
          <div className="flex flex-wrap gap-2">
            {RETRY.map((o) => (
              <label
                key={o.value}
                className={`app-seg flex min-h-[44px] cursor-pointer items-center rounded-full border-[1.5px] px-4 text-body-sm ${retry === o.value ? 'border-ink bg-ink font-bold text-surface-raised' : 'border-line-strong bg-surface-raised text-ink'}`}
              >
                <input
                  type="radio"
                  name="lost-retry"
                  value={o.value}
                  checked={retry === o.value}
                  onChange={() => {
                    setRetry(o.value);
                    setError({});
                  }}
                  className="sr-only"
                />
                {o.label}
              </label>
            ))}
          </div>
          {retry === 'date' ? (
            <TextField
              label="تاريخ إعادة المحاولة"
              type="date"
              ltr
              value={date}
              min={toDateInput(new Date(now.getTime() + 86_400_000))}
              error={error.date}
              onChange={(e) => {
                setDate(e.target.value);
                setError({});
              }}
            />
          ) : null}
          <p className="m-0 text-label-sm text-ink-muted" aria-live="polite">
            {when ? `تُنشأ مهمة «أعد المحاولة» يوم ${formatDayLong(when)}.` : 'لا مهمة لإعادة المحاولة. تُلغى المهام المفتوحة.'}
          </p>
        </fieldset>
      </div>
    </BottomSheet>
  );
}
