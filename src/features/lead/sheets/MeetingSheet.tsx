import { useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { toDateInput } from '@/lib/postpone';
import { defaultMeetingSlot } from '@/lib/schedule';
import { meetingAt } from '../text';

const DEFAULT_MEETING_TITLE = 'اجتماع في المحل';

/** Flow 3.1: a meeting date creates its reminder task (RPC set_meeting). */
export function MeetingSheet({ open, busy, onClose, onSubmit }: { open: boolean; busy: boolean; onClose: () => void; onSubmit: (input: { at: Date; title: string }) => void }) {
  const [now] = useState(() => new Date());
  const [date, setDate] = useState(() => defaultMeetingSlot(now).date);
  const [time, setTime] = useState(() => defaultMeetingSlot(now).time);
  const [title, setTitle] = useState(DEFAULT_MEETING_TITLE);
  const [error, setError] = useState<{ text: string; field: 'date' | 'time' } | null>(null);

  const submit = () => {
    const r = meetingAt(date, time, new Date());
    if ('error' in r) {
      setError({ text: r.error, field: r.field });
      return;
    }
    onSubmit({ at: r.at, title: title.trim() || 'اجتماع' });
  };

  return (
    <BottomSheet
      open={open}
      onClose={busy ? undefined : onClose}
      title="حدد اجتماعاً"
      actions={
        <>
          <Button variant="primary" block loading={busy} onClick={submit}>
            {busy ? 'جارٍ الحفظ' : 'احفظ الموعد'}
          </Button>
          <Button variant="ghost" block disabled={busy} onClick={onClose}>
            إلغاء
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <TextField
            label="التاريخ"
            type="date"
            ltr
            required
            value={date}
            min={toDateInput(now)}
            error={error?.field === 'date' ? error.text : undefined}
            onChange={(e) => {
              setDate(e.target.value);
              setError(null);
            }}
          />
          <TextField
            label="الوقت"
            type="time"
            ltr
            required
            value={time}
            error={error?.field === 'time' ? error.text : undefined}
            onChange={(e) => {
              setTime(e.target.value);
              setError(null);
            }}
          />
        </div>
        <TextField
          label="عنوان الاجتماع"
          value={title}
          maxLength={80}
          onChange={(e) => {
            setTitle(e.target.value);
          }}
        />
        <p className="m-0 text-label-sm text-ink-muted">تُنشأ مهمة تذكير بالموعد، وتُلغى المتابعات الباقية.</p>
      </div>
    </BottomSheet>
  );
}
