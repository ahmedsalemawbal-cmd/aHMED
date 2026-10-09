import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { FOLLOWUP_HINT, followupHeading } from './text';

/** SentConfirm.dc.html: «هل أرسلت الرسالة؟» after coming back from WhatsApp. */
export function SentConfirmSheet({
  open,
  intro,
  followups,
  busy,
  onYes,
  onNotYet,
}: {
  open: boolean;
  intro: string;
  followups: { dueAt: Date; days: number }[];
  busy: boolean;
  onYes: () => void;
  onNotYet: () => void;
}) {
  return (
    <BottomSheet
      open={open}
      title="هل أرسلت الرسالة؟"
      onClose={busy ? undefined : onNotYet}
      actions={
        <>
          <Button variant="primary" block loading={busy} onClick={onYes}>
            {busy ? 'جارٍ التسجيل' : 'نعم، أرسلتها'}
          </Button>
          <Button variant="ghost" block disabled={busy} onClick={onNotYet}>
            ليس بعد
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        <p className="m-0">{intro}</p>
        {followups.length > 0 ? (
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {followups.map((f, i) => (
              <li key={f.days} className="flex items-center gap-3 rounded-md bg-surface-sunken px-[14px] py-3 text-ink">
                <span className="grid size-10 flex-none place-items-center rounded-full bg-surface-raised font-bold tabular-nums" aria-hidden="true">
                  {f.days}
                </span>
                <span className="flex flex-col text-body-sm leading-5">
                  <b className="text-[15px]">{followupHeading(i, f.dueAt)}</b>
                  <span className="text-ink-muted">{FOLLOWUP_HINT[i] ?? ''}</span>
                </span>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </BottomSheet>
  );
}
