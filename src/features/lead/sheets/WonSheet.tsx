import { useState } from 'react';
import { Segmented } from '@/components/app/Segmented';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { formatSAR } from '@/lib/format';
import { parseAmount } from '../text';

type Billing = 'monthly' | 'one_time';

const BILLING: { value: Billing; label: string }[] = [
  { value: 'monthly', label: 'شهري' },
  { value: 'one_time', label: 'مرة واحدة' },
];

/** Flow 3.4: the deal value and its kind (QUESTIONS Q3). */
export function WonSheet({
  open,
  expectedValue,
  busy,
  onClose,
  onSubmit,
}: {
  open: boolean;
  expectedValue: number | null;
  busy: boolean;
  onClose: () => void;
  onSubmit: (input: { value: number; billing: Billing }) => void;
}) {
  const [value, setValue] = useState(() => (expectedValue !== null ? expectedValue.toString() : ''));
  const [billing, setBilling] = useState<Billing>('monthly');
  const [error, setError] = useState<string | null>(null);

  const submit = () => {
    const n = parseAmount(value);
    if (n === null || n <= 0) {
      setError('اكتب قيمة الصفقة بالأرقام، مثل 2500.');
      return;
    }
    onSubmit({ value: n, billing });
  };

  return (
    <BottomSheet
      open={open}
      onClose={busy ? undefined : onClose}
      title="تم الإغلاق"
      actions={
        <>
          <Button variant="primary" block loading={busy} onClick={submit}>
            {busy ? 'جارٍ الحفظ' : 'احفظ الإغلاق'}
          </Button>
          <Button variant="ghost" block disabled={busy} onClick={onClose}>
            إلغاء
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        <TextField
          label="قيمة الصفقة"
          ltr
          required
          inputMode="numeric"
          suffix="ر.س"
          value={value}
          error={error ?? undefined}
          hint={expectedValue !== null ? `القيمة المتوقعة ${formatSAR(expectedValue)} شهرياً.` : undefined}
          onChange={(e) => {
            setValue(e.target.value);
            setError(null);
          }}
        />
        <div className="flex flex-col gap-2">
          <span className="text-[15px] font-bold leading-[22px] text-ink">نوع الصفقة</span>
          <Segmented label="نوع الصفقة" value={billing} options={BILLING} onChange={setBilling} />
        </div>
      </div>
    </BottomSheet>
  );
}
