import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { StageBadge } from '@/components/ui/StageBadge';
import { STAGES, type Stage } from '@/components/ui/stages';

/** Stages that need one lead's details (a reply, a meeting time, a deal value). */
const SINGLE_ONLY = new Set<Stage>(['replied', 'meeting', 'won']);

/**
 * «تغيير المرحلة»: every stage in order. The parent routes the choice: direct
 * stages save at once; reply, meeting, won and lost open their own sheet.
 * With several leads selected, reply/meeting/won are done per lead.
 */
export function StageSheet({
  open,
  current,
  count = 1,
  busy,
  onClose,
  onPick,
}: {
  open: boolean;
  /** the lead's stage, or null for a selection */
  current: Stage | null;
  count?: number;
  busy?: boolean;
  onClose: () => void;
  onPick: (stage: Stage) => void;
}) {
  const bulk = count > 1;
  return (
    <BottomSheet
      open={open}
      onClose={busy ? undefined : onClose}
      title={bulk ? `تغيير المرحلة · ${count.toString()} عملاء` : 'تغيير المرحلة'}
      actions={
        <Button variant="ghost" block disabled={busy} onClick={onClose}>
          إلغاء
        </Button>
      }
    >
      <div className="flex flex-col gap-2" role="list">
        {STAGES.map((s) => {
          const isCurrent = s.id === current;
          const disabled = Boolean(busy) || isCurrent || (bulk && SINGLE_ONLY.has(s.id));
          return (
            <button
              key={s.id}
              type="button"
              role="listitem"
              disabled={disabled}
              aria-current={isCurrent ? 'true' : undefined}
              onClick={() => {
                onPick(s.id);
              }}
              className="flex min-h-touch items-center justify-between gap-3 rounded-md border-[1.5px] border-line bg-surface-raised px-4 text-start text-body-sm text-ink disabled:cursor-not-allowed disabled:opacity-60"
            >
              <StageBadge stage={s.id} />
              <span className="text-ink-muted">{isCurrent ? 'الحالية' : bulk && SINGLE_ONLY.has(s.id) ? 'من صفحة كل عميل' : ''}</span>
            </button>
          );
        })}
      </div>
    </BottomSheet>
  );
}
