import { STAGE_LABEL, type Stage } from '@/components/ui/stages';
import type { LeadDetail, LeadMessage, LeadQuote, LeadTask } from '@/data/lead';
import type { RepliedResult } from '@/data/lead-actions';
import { TASK_KIND_LABEL, taskAction, type TaskKind } from '@/data/tasks-meta';
import { arDays, calendarDaysBetween, formatDayLong, formatTime, overdueText } from '@/lib/dates';
import type { ContactTime } from '@/lib/followups';
import { formatSAR } from '@/lib/format';
import { latinDigits, type Role, type Source } from '@/lib/leads-list';
import { riyadhAt } from '@/lib/schedule';
import type { Checklist } from '@/lib/scoring';
import { followupsWord, LOST_REASON_LABEL } from '@/lib/timeline';

/* ------------------------------------------------------------------ times */

/** Timeline, message and task times: «اليوم 11:20 ص»، «أمس 3:10 م»، «السبت 10 أكتوبر · 11:20 ص». */
export function eventTime(at: Date, now: Date): string {
  const days = calendarDaysBetween(at, now);
  const time = formatTime(at);
  if (days === 0) return `اليوم ${time}`;
  if (days === 1) return `أمس ${time}`;
  if (days === -1) return `غداً ${time}`;
  return `${formatDayLong(at)} · ${time}`;
}

/** Mobile header: «سُجّل اليوم»، «سُجّل أمس»، «سُجّل قبل 3 أيام». */
export function registeredText(createdAt: Date, now: Date): string {
  const days = calendarDaysBetween(createdAt, now);
  if (days <= 0) return 'سُجّل اليوم';
  if (days === 1) return 'سُجّل أمس';
  return `سُجّل قبل ${arDays(days)}`;
}

/** Desktop header (DeskLead.dc.html): «سُجّل من زيارة ميدانية اليوم 10:30 ص». */
export function registeredLong(createdAt: Date, now: Date, source: Source): string {
  const from = source === 'import' ? 'من الاستيراد' : 'من زيارة ميدانية';
  const days = calendarDaysBetween(createdAt, now);
  const when = days <= 0 ? `اليوم ${formatTime(createdAt)}` : days === 1 ? `أمس ${formatTime(createdAt)}` : `يوم ${formatDayLong(createdAt)}`;
  return `سُجّل ${from} ${when}`;
}

/** «مطعم · حي الحمدانية · سُجّل اليوم» */
export function headerSub(lead: Pick<LeadDetail, 'activity' | 'address' | 'createdAt' | 'source'>, now: Date, desktop: boolean): string {
  const reg = desktop ? registeredLong(lead.createdAt, now, lead.source) : registeredText(lead.createdAt, now);
  return [lead.activity, lead.address, reg].filter(Boolean).join(' · ');
}

/** Due moment of an open task: «السبت 10 أكتوبر · بعد 3 أيام»، «اليوم 4:00 م»، «الأربعاء 7 أكتوبر · متأخرة منذ يومين». */
export function dueText(due: Date, now: Date): { text: string; overdue: boolean } {
  const late = calendarDaysBetween(due, now);
  if (late > 0) return { text: `${formatDayLong(due)} · ${overdueText(due, now)}`, overdue: true };
  const ahead = -late;
  if (ahead === 0) return { text: `اليوم ${formatTime(due)}`, overdue: false };
  if (ahead === 1) return { text: `غداً ${formatTime(due)}`, overdue: false };
  return { text: `${formatDayLong(due)} · بعد ${arDays(ahead)}`, overdue: false };
}

/* -------------------------------------------------------------- next step */

const STEP_HINT: Partial<Record<TaskKind, string>> = { followup_3: 'أرسل عينة الريل', followup_7: 'تذكير أخير بدون ضغط' };

/** «متابعة أولى: أرسل عينة الريل»: the task title, with its kind when that adds meaning. */
export function taskHeadline(t: Pick<LeadTask, 'kind' | 'title'>): string {
  const label = TASK_KIND_LABEL[t.kind];
  const title = t.title.trim() || label;
  const hint = STEP_HINT[t.kind];
  if (hint && title.startsWith(label)) return `${label}: ${hint}`;
  if (t.kind === 'meeting' && !title.includes('اجتماع')) return `${label}: ${title}`;
  return title;
}

export type NextAction =
  | { kind: 'message'; label: string; to: string }
  | { kind: 'meeting'; label: string }
  | { kind: 'quote'; label: string; to: string }
  | { kind: 'done'; label: string };

const MESSAGE_ACTION: Partial<Record<TaskKind, string>> = {
  first_message: 'جهّز الرسالة الأولى',
  followup_3: 'جهّز رسالة المتابعة',
  followup_7: 'جهّز رسالة المتابعة',
  quote_followup: 'جهّز الرسالة',
  retry: 'جهّز الرسالة',
};

/** The button of «الخطوة القادمة» for each kind of task. */
export function nextAction(task: Pick<LeadTask, 'id' | 'kind'>, lead: Pick<LeadDetail, 'id' | 'doNotContact'>): NextAction {
  const msg = MESSAGE_ACTION[task.kind];
  if (msg) return lead.doNotContact ? { kind: 'done', label: 'تم' } : { kind: 'message', label: msg, to: `/leads/${lead.id}/message?task=${task.id}` };
  if (task.kind === 'schedule_meeting') return { kind: 'meeting', label: 'حدد اجتماعاً' };
  if (task.kind === 'meeting') return { kind: 'quote', label: 'أنشئ عرض سعر', to: `/leads/${lead.id}/quotes/new` };
  return { kind: 'done', label: 'تم' };
}

/** «الخطوة القادمة» without an open task. */
export function noTaskView(lead: Pick<LeadDetail, 'stage' | 'wonValue' | 'wonBilling' | 'lostReason' | 'lostNote'>): { title: string; body: string | null } {
  if (lead.stage === 'won') {
    return { title: lead.wonValue !== null ? `تم الإغلاق · ${formatSAR(lead.wonValue)} ${billingWord(lead.wonBilling ?? 'monthly')}` : 'تم الإغلاق', body: null };
  }
  if (lead.stage === 'lost') return { title: lead.lostReason ? `خسارة · ${LOST_REASON_LABEL[lead.lostReason]}` : 'خسارة', body: lead.lostNote };
  return { title: 'لا خطوة قادمة', body: 'غيّر المرحلة أو سجّل زيارة جديدة.' };
}

export function billingWord(b: 'monthly' | 'one_time'): string {
  return b === 'one_time' ? 'مرة واحدة' : 'شهرياً';
}

/** Header «واتساب»: the message of the next task when it is a message, else the screen picks by stage. */
export function whatsappPath(leadId: string, next: Pick<LeadTask, 'id' | 'kind'> | null): string {
  return next && taskAction(next.kind) === 'whatsapp' ? `/leads/${leadId}/message?task=${next.id}` : `/leads/${leadId}/message`;
}

/* ---------------------------------------------------------- stage actions */

const MESSAGE_TASKS = new Set<TaskKind>(['first_message', 'followup_3', 'followup_7', 'retry']);
const MEETING_TASKS = new Set<TaskKind>(['schedule_meeting', 'meeting']);
/** mark_replied moves only these stages to «رد» (migration 9). */
const MOVES_TO_REPLIED = new Set<Stage>(['not_visited', 'visited', 'contacted']);

function isOpen(t: Pick<LeadTask, 'doneAt' | 'cancelledAt'>): boolean {
  return !t.doneAt && !t.cancelledAt;
}

/** Open message tasks that «العميل رد» cancels. */
export function openFollowupCount(tasks: Pick<LeadTask, 'kind' | 'doneAt' | 'cancelledAt'>[]): number {
  return tasks.filter((t) => isOpen(t) && MESSAGE_TASKS.has(t.kind)).length;
}

export function hasOpenMeetingTask(tasks: Pick<LeadTask, 'kind' | 'doneAt' | 'cancelledAt'>[]): boolean {
  return tasks.some((t) => isOpen(t) && MEETING_TASKS.has(t.kind));
}

/** «العميل رد» is not offered once the deal is closed (PLAN §1.6). */
export function canMarkReplied(stage: Stage): boolean {
  return stage !== 'won' && stage !== 'lost';
}

/** RepliedSheet: what the confirmation does, in one sentence. */
export function repliedIntro(stage: Stage, cancel: number, createsTask: boolean): string {
  const parts = [MOVES_TO_REPLIED.has(stage) ? 'عند التأكيد تصبح المرحلة «رد»' : 'عند التأكيد يُسجَّل رد العميل ويصبح حاراً'];
  if (cancel === 1) parts.push('وتُلغى المتابعة الباقية');
  else if (cancel === 2) parts.push('وتُلغى المتابعتان الباقيتان');
  else if (cancel > 2) parts.push(`وتُلغى ${followupsWord(cancel)} باقية`);
  if (createsTask) parts.push('وتُنشأ مهمة «حدد اجتماعاً»');
  return `${parts.join('، ')}.`;
}

export interface ToastText {
  title: string;
  message?: string;
}

export function repliedToast(r: RepliedResult): ToastText {
  const title = r.stageBefore && MOVES_TO_REPLIED.has(r.stageBefore) ? 'المرحلة الآن: رد' : 'سُجّل رد العميل';
  const message = [r.cancelledTaskIds.length > 0 ? 'أُلغيت المتابعات الباقية.' : null, r.taskId ? 'حدد اجتماعاً.' : null].filter(Boolean).join(' ');
  return message ? { title, message } : { title };
}

export function meetingToast(at: Date): ToastText {
  return { title: 'حُدد الاجتماع', message: `${formatDayLong(at)} · ${formatTime(at)}` };
}

export function wonToast(value: number, billing: 'monthly' | 'one_time'): ToastText {
  return { title: 'تم الإغلاق', message: `${formatSAR(value)} ${billingWord(billing)}` };
}

export function lostToast(retryAt: Date | null): ToastText {
  return retryAt ? { title: 'نُقل إلى خسارة', message: `تذكير «أعد المحاولة» يوم ${formatDayLong(retryAt)}.` } : { title: 'نُقل إلى خسارة' };
}

export function stageToast(stage: Stage): string {
  return `المرحلة الآن: ${STAGE_LABEL[stage]}`;
}

/** MeetingSheet: the chosen moment in Riyadh, or what to fix. */
export function meetingAt(date: string, time: string, now: Date): { at: Date } | { error: string; field: 'date' | 'time' } {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return { error: 'التاريخ ناقص. اختره من التقويم.', field: 'date' };
  const at = riyadhAt(date, time);
  if (!at) return { error: 'الوقت ناقص. اختر ساعة الاجتماع.', field: 'time' };
  if (at.getTime() <= now.getTime()) return { error: 'الموعد مضى. اختر وقتاً قادماً.', field: 'time' };
  return { at };
}

/** «2,500» or «٢٥٠٠» → 2500; anything else → null. */
export function parseAmount(input: string): number | null {
  const s = latinDigits(input).replace(/[\s,٬]/g, '').replace('٫', '.');
  if (!/^\d+(\.\d+)?$/.test(s)) return null;
  const n = Number(s);
  return Number.isFinite(n) ? n : null;
}

/* --------------------------------------------------------------- overview */

/** Weakness ids of the latest assessment → their phrases («لا ينشر فيديو أو ريلز»), unknown ids skipped. */
export function weaknessTexts(ids: string[], checklist: Checklist): string[] {
  return ids.flatMap((id) => {
    const item = checklist.general.find((g) => g.id === id);
    return item ? [item.weakness ?? item.label] : [];
  });
}

/** Overview shows the three that cost the most; the rest as a count. */
export const WEAKNESSES_SHOWN = 3;

export function moreWeaknessesText(n: number): string {
  if (n === 1) return 'ونقطة ضعف أخرى';
  if (n === 2) return 'ونقطتا ضعف أخريان';
  if (n <= 10) return `و${n.toString()} نقاط ضعف أخرى`;
  return `و${n.toString()} نقطة ضعف أخرى`;
}

export const ROLE_LABEL: Record<Role, string> = { owner: 'مالك', manager: 'مدير', employee: 'موظف' };
export const TIME_LABEL: Record<ContactTime, string> = { morning: 'الصباح', afternoon: 'بعد العصر', evening: 'المساء' };

export function contactNameText(lead: Pick<LeadDetail, 'contactName' | 'contactRole'>): string {
  const parts = [lead.contactName, lead.contactRole ? ROLE_LABEL[lead.contactRole] : null].filter(Boolean);
  return parts.length ? parts.join(' · ') : 'غير مسجّل';
}

export function consentView(lead: Pick<LeadDetail, 'waConsent' | 'doNotContact'>): { text: string; tone: 'success' | 'muted' | 'danger' } {
  if (lead.doNotContact) return { text: 'طلب عدم التواصل', tone: 'danger' };
  if (lead.waConsent) return { text: 'وافق شفهياً في الزيارة', tone: 'success' };
  return { text: 'لم يوافق بعد', tone: 'muted' };
}

/** Instagram link only for http(s) URLs; shown without the scheme. */
export function instagramView(url: string): { href: string | null; text: string } {
  const safe = /^https?:\/\//i.test(url);
  return { href: safe ? url : null, text: url.replace(/^https?:\/\/(www\.)?/i, '').replace(/\/+$/, '') };
}

/* ------------------------------------------------------------------- tabs */

export type LeadTab = 'overview' | 'timeline' | 'messages' | 'tasks' | 'quotes';

const TAB_LABEL: Record<LeadTab, string> = { overview: 'نظرة عامة', timeline: 'الخط الزمني', messages: 'الرسائل', tasks: 'المهام', quotes: 'العروض' };
const MOBILE_TABS: LeadTab[] = ['overview', 'timeline', 'messages', 'tasks', 'quotes'];
const DESK_TABS: LeadTab[] = ['timeline', 'messages', 'tasks', 'quotes'];

export function leadTabs(desktop: boolean): { id: LeadTab; label: string }[] {
  return (desktop ? DESK_TABS : MOBILE_TABS).map((id) => ({ id, label: TAB_LABEL[id] }));
}

/** ?tab= → the tab to show; on the desktop the overview sits in the side column. */
export function tabFromParam(param: string | null, desktop: boolean): LeadTab {
  const tabs = desktop ? DESK_TABS : MOBILE_TABS;
  return tabs.find((t) => t === param) ?? (desktop ? 'timeline' : 'overview');
}

/** Arrow keys follow the reading direction: in RTL the left arrow goes to the next tab. */
export function tabKeyTarget(key: string, index: number, count: number, rtl: boolean): number | null {
  if (key === 'Home') return 0;
  if (key === 'End') return count - 1;
  const step = key === 'ArrowLeft' ? (rtl ? 1 : -1) : key === 'ArrowRight' ? (rtl ? -1 : 1) : 0;
  if (!step) return null;
  return (index + step + count) % count;
}

/* ---------------------------------------------------- messages and quotes */

export function messageStatus(m: Pick<LeadMessage, 'status' | 'sentAt' | 'repliedAt' | 'createdAt'>, now: Date): { text: string; tone: 'draft' | 'sent' | 'replied' } {
  if (m.status === 'draft') return { text: 'مسودة', tone: 'draft' };
  if (m.status === 'replied') return { text: `ردّ ${eventTime(m.repliedAt ?? m.sentAt ?? m.createdAt, now)}`, tone: 'replied' };
  return { text: `أُرسلت ${eventTime(m.sentAt ?? m.createdAt, now)}`, tone: 'sent' };
}

/** «أكمل المسودة»: the same message screen the draft was written in. */
export function draftPath(leadId: string, m: Pick<LeadMessage, 'kind' | 'taskId'>): string {
  return m.taskId ? `/leads/${leadId}/message?task=${m.taskId}` : `/leads/${leadId}/message?kind=${m.kind}`;
}

export function taskClosedText(t: Pick<LeadTask, 'doneAt' | 'cancelledAt'>, now: Date): string {
  if (t.doneAt) return `أُنجزت ${eventTime(t.doneAt, now)}`;
  if (t.cancelledAt) return `أُلغيت ${eventTime(t.cancelledAt, now)}`;
  return '';
}

export function quoteStatus(q: Pick<LeadQuote, 'status' | 'sentAt'>, now: Date): string {
  return q.status === 'sent' ? (q.sentAt ? `أُرسل ${eventTime(q.sentAt, now)}` : 'أُرسل') : 'مسودة';
}
