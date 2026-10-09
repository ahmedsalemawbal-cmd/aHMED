import { STAGE_LABEL, type Stage } from '@/components/ui/stages';
import { formatDayLong } from '@/lib/dates';
import { formatPhone } from '@/lib/phone';
import type { MessageKind, Role } from '@/data/messages';

const ROLE_LABEL: Record<Role, string> = { owner: 'مالك', manager: 'مدير', employee: 'موظف' };

/** Message.dc.html header: «أ. خالد العتيبي · مالك · 055 123 4567» (the phone is shown LTR). */
export function contactParts(lead: { contactName: string | null; contactRole: Role | null; phone: string | null }): { text: string; phone: string | null } {
  const parts = [lead.contactName ? `أ. ${lead.contactName}` : null, lead.contactRole ? ROLE_LABEL[lead.contactRole] : null].filter((x): x is string => Boolean(x));
  return { text: parts.join(' · '), phone: lead.phone ? formatPhone(lead.phone) : null };
}

/** «ونقطتي الضعف: الفيديو، والرد على المراجعات.» */
export function weaknessesSentence(ws: string[]): string {
  const [a, b] = ws;
  if (a && b) return `، ونقطتي الضعف: ${a}، و${b}.`;
  if (a) return `، ونقطة الضعف: ${a}.`;
  return '.';
}

/** Arabic counted noun for lines: سطر واحد، سطران، 3–10 أسطر، 11+ سطراً. */
export function linesText(n: number): string {
  if (n === 0) return 'لا أسطر';
  if (n === 1) return 'سطر واحد';
  if (n === 2) return 'سطران';
  if (n <= 10) return `${n.toString()} أسطر`;
  return `${n.toString()} سطراً`;
}

/** Upper line limits shown under the editor (brief.md: first 3–5, follow-ups shorter). Free text has none. */
export const MAX_LINES: Record<MessageKind, number | null> = { first: 5, followup_3: 4, followup_7: 3, custom: null };

export function counterText(n: number, kind: MessageKind): string {
  const max = MAX_LINES[kind];
  return max === null ? linesText(n) : `${linesText(n)} · الحد ${max.toString()}`;
}

export function isOverLimit(n: number, kind: MessageKind): boolean {
  const max = MAX_LINES[kind];
  return max !== null && n > max;
}

/** First message moves the lead to «تم الإرسال» only from these stages (same rule as the RPC). */
export function firstMovesStage(kind: MessageKind, stage: Stage): boolean {
  return kind === 'first' && (stage === 'not_visited' || stage === 'visited');
}

/** SentConfirm.dc.html: the sentence above the follow-up cards. */
export function confirmIntro(input: { kind: MessageKind; stage: Stage; businessName: string; followups: number; taskTitle: string | null }): string {
  const moves = firstMovesStage(input.kind, input.stage);
  const stagePart = moves ? `تصبح مرحلة ${input.businessName} «${STAGE_LABEL.contacted}»` : `نسجّل الرسالة في سجل ${input.businessName}`;
  if (input.followups === 2) return `عند التأكيد ${stagePart}، وتُنشأ متابعتان:`;
  if (input.taskTitle) return `عند التأكيد ${stagePart}، وتُغلق مهمة «${input.taskTitle}».`;
  return `عند التأكيد ${stagePart}.`;
}

/** «متابعة أولى · السبت 10 أكتوبر» */
export function followupHeading(index: number, dueAt: Date): string {
  return `${index === 0 ? 'متابعة أولى' : 'متابعة ثانية'} · ${formatDayLong(dueAt)}`;
}

/** The second line of each card. «رابط تقرير المحل» is gone with the reports (decisions §1, C2). */
export const FOLLOWUP_HINT = ['عينة ريل أو فكرة محتوى', 'تذكير قصير وأخير بدون ضغط'] as const;

/** Success toast after «نعم، أرسلتها». */
export function sentToast(input: { movedStage: boolean; followups: number; taskTitle: string | null }): { title: string; message?: string } {
  const title = input.movedStage ? `المرحلة الآن: ${STAGE_LABEL.contacted}` : 'سُجّلت الرسالة كمرسلة';
  if (input.followups === 2) return { title, message: 'أُنشئت متابعتان بعد 3 و7 أيام.' };
  if (input.taskTitle) return { title, message: `أُغلقت مهمة «${input.taskTitle}».` };
  return { title };
}
