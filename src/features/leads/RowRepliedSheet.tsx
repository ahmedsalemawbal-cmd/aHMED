import { useLead } from '@/data/lead';
import { RepliedSheet } from '@/features/lead/sheets/RepliedSheet';
import { hasOpenMeetingTask, openFollowupCount, repliedIntro } from '@/features/lead/text';
import type { LeadRow } from '@/lib/leads-list';

/**
 * «هل رد العميل؟» for one selected row: says what the confirmation cancels,
 * as on the lead page, once that lead's open tasks are loaded.
 */
export function RowRepliedSheet({ row, busy, onClose, onConfirm }: { row: LeadRow; busy: boolean; onClose: () => void; onConfirm: () => void }) {
  const lead = useLead(row.id);
  const tasks = lead.data?.tasks;
  const intro = repliedIntro(row.stage, tasks ? openFollowupCount(tasks) : 0, tasks ? !hasOpenMeetingTask(tasks) : false);
  return <RepliedSheet open intro={intro} busy={busy} onClose={onClose} onConfirm={onConfirm} />;
}
