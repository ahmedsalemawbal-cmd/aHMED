import { STAGE_LABEL, type Stage } from '@/components/ui/stages';
import type { TaskKind } from '@/data/tasks-meta';
import { formatDayLong } from './dates';
import { formatSAR } from './format';

/** Colour of the dot: a stage colour, or neutral for bookkeeping events. */
export type EventTone = Stage | 'neutral';

export interface TimelineEvent {
  id: string;
  at: Date;
  title: string;
  body: string;
  /** quoted text: the observation or the message itself */
  quote?: string;
  tone: EventTone;
}

export interface TimelineInput {
  lead: {
    createdAt: Date;
    source: 'visit' | 'import';
    contactName: string | null;
    contactRole: 'owner' | 'manager' | 'employee' | null;
    stage: Stage;
    lostReason: LostReason | null;
    lostNote: string | null;
    wonValue: number | null;
    wonBilling: 'monthly' | 'one_time' | null;
  };
  visits: { id: string; visitedAt: Date; keyObservation: string | null; mediaCount: number; score: number | null }[];
  messages: {
    id: string;
    kind: 'first' | 'followup_3' | 'followup_7' | 'custom';
    status: 'draft' | 'sent' | 'replied';
    body: string;
    tone: 'friendly' | 'formal' | 'short' | null;
    generatedBy: 'ai' | 'fallback' | 'manual' | null;
    sentAt: Date | null;
    repliedAt: Date | null;
  }[];
  tasks: { id: string; kind: TaskKind; title: string; createdAt: Date; dueAt: Date; doneAt: Date | null; cancelledAt: Date | null }[];
  history: { id: string; fromStage: Stage | null; toStage: Stage; changedAt: Date }[];
}

export type LostReason = 'price' | 'not_now' | 'has_marketer' | 'no_reply' | 'not_interested' | 'other';

/** Closed list from flow 4 (QUESTIONS Q6). */
export const LOST_REASON_LABEL: Record<LostReason, string> = {
  price: 'السعر',
  not_now: 'لا يحتاج الآن',
  has_marketer: 'عنده مسوّق',
  no_reply: 'لم يرد',
  not_interested: 'غير مهتم',
  other: 'أخرى',
};

const ROLE: Record<'owner' | 'manager' | 'employee', string> = { owner: 'مالك', manager: 'مدير', employee: 'موظف' };
const TONE: Record<'friendly' | 'formal' | 'short', string> = { friendly: 'نبرة ودّية', formal: 'نبرة رسمية', short: 'نبرة مختصرة' };
export const MESSAGE_KIND_LABEL: Record<'first' | 'followup_3' | 'followup_7' | 'custom', string> = {
  first: 'الرسالة الأولى',
  followup_3: 'المتابعة الأولى',
  followup_7: 'المتابعة الثانية',
  custom: 'رسالة',
};
const FOLLOWUP_HINT: Partial<Record<TaskKind, string>> = { followup_3: 'عينة ريل', followup_7: 'تذكير أخير' };
const FOLLOWUP_KINDS = new Set<TaskKind>(['followup_3', 'followup_7']);

/** Events this close together are the same moment (one RPC, one transaction). */
const SAME_MOMENT_MS = 2 * 60_000;

function near(a: Date, b: Date): boolean {
  return Math.abs(a.getTime() - b.getTime()) <= SAME_MOMENT_MS;
}

/** «متابعة»، «متابعتان»، «3 متابعات» */
export function followupsWord(n: number): string {
  if (n === 1) return 'متابعة';
  if (n === 2) return 'متابعتان';
  if (n <= 10) return `${n.toString()} متابعات`;
  return `${n.toString()} متابعة`;
}

/** First two lines of a message, with «…» when there is more. */
export function excerpt(body: string, lines = 2): string {
  const all = body.split('\n').filter((l) => l.trim());
  return all.length > lines ? `${all.slice(0, lines).join('\n')}…` : all.join('\n');
}

/** Groups tasks whose timestamps fall in the same moment. */
function groupByMoment<T>(items: T[], at: (t: T) => Date): T[][] {
  const sorted = [...items].sort((a, b) => at(a).getTime() - at(b).getTime());
  const groups: T[][] = [];
  for (const it of sorted) {
    const last = groups[groups.length - 1];
    const head = last?.[0];
    if (last && head && near(at(head), at(it))) last.push(it);
    else groups.push([it]);
  }
  return groups;
}

/**
 * The lead's history, newest first (brief.md screen 6: visits, messages and
 * stage changes). A stage change caused by an event already shown (a visit, a
 * sent message, a reply, a meeting) is not repeated.
 */
export function buildTimeline(input: TimelineInput): TimelineEvent[] {
  const { lead } = input;
  const events: TimelineEvent[] = [];

  events.push({
    id: 'created',
    at: lead.createdAt,
    title: 'سُجّل العميل',
    body: [
      lead.source === 'import' ? 'من الاستيراد' : 'من زيارة ميدانية',
      [lead.contactName, lead.contactRole ? ROLE[lead.contactRole] : null].filter(Boolean).join('، '),
    ]
      .filter(Boolean)
      .join(' · '),
    tone: 'not_visited',
  });

  for (const v of input.visits) {
    const parts = [v.score !== null ? `الدرجة ${v.score.toString()}` : null, v.mediaCount === 1 ? 'ملف واحد' : v.mediaCount === 2 ? 'ملفان' : v.mediaCount > 2 ? `${v.mediaCount.toString()} ملفات` : null];
    events.push({
      id: `visit:${v.id}`,
      at: v.visitedAt,
      title: 'تمت الزيارة',
      body: parts.filter(Boolean).join(' · '),
      quote: v.keyObservation ? `الملاحظة الأبرز: ${v.keyObservation}` : undefined,
      tone: 'visited',
    });
  }

  for (const m of input.messages) {
    if (m.sentAt) {
      const via = ['عبر واتساب', m.tone ? TONE[m.tone] : null, m.generatedBy === 'fallback' ? 'القالب الجاهز' : null].filter(Boolean).join(' · ');
      events.push({ id: `sent:${m.id}`, at: m.sentAt, title: `تم الإرسال · ${MESSAGE_KIND_LABEL[m.kind]}`, body: via, quote: excerpt(m.body), tone: 'contacted' });
    }
    if (m.repliedAt) {
      events.push({ id: `replied:${m.id}`, at: m.repliedAt, title: 'رد العميل', body: `على ${MESSAGE_KIND_LABEL[m.kind]}`, tone: 'replied' });
    }
  }

  const followups = input.tasks.filter((t) => FOLLOWUP_KINDS.has(t.kind));
  for (const g of groupByMoment(followups, (t) => t.createdAt)) {
    const first = g[0];
    if (!first) continue;
    const days = g
      .sort((a, b) => a.dueAt.getTime() - b.dueAt.getTime())
      .map((t) => `${formatDayLong(t.dueAt)}${FOLLOWUP_HINT[t.kind] ? ` (${FOLLOWUP_HINT[t.kind] ?? ''})` : ''}`);
    events.push({ id: `fu:${first.id}`, at: first.createdAt, title: `أُنشئت ${followupsWord(g.length)}`, body: days.join(' و'), tone: 'neutral' });
  }
  const cancelled = input.tasks.filter((t) => t.cancelledAt && !t.doneAt && (FOLLOWUP_KINDS.has(t.kind) || t.kind === 'first_message'));
  for (const g of groupByMoment(cancelled, (t) => t.cancelledAt ?? t.createdAt)) {
    const first = g[0];
    if (!first?.cancelledAt) continue;
    events.push({ id: `cancel:${first.id}`, at: first.cancelledAt, title: `أُلغيت ${followupsWord(g.length)}`, body: g.map((t) => t.title).join('، '), tone: 'neutral' });
  }

  const meetings = input.tasks.filter((t) => t.kind === 'meeting');
  for (const t of meetings) {
    events.push({ id: `meeting:${t.id}`, at: t.createdAt, title: `حُدد اجتماع · ${formatDayLong(t.dueAt)}`, body: t.title, tone: 'meeting' });
  }

  const lastLost = [...input.history].filter((h) => h.toStage === 'lost').sort((a, b) => b.changedAt.getTime() - a.changedAt.getTime())[0];
  const lastWon = [...input.history].filter((h) => h.toStage === 'won').sort((a, b) => b.changedAt.getTime() - a.changedAt.getTime())[0];
  for (const h of input.history) {
    if (h.fromStage === null) continue; // the stage at creation
    const covered =
      (h.toStage === 'visited' && input.visits.some((v) => near(v.visitedAt, h.changedAt))) ||
      (h.toStage === 'contacted' && input.messages.some((m) => m.sentAt && near(m.sentAt, h.changedAt))) ||
      (h.toStage === 'replied' && input.messages.some((m) => m.repliedAt && near(m.repliedAt, h.changedAt))) ||
      (h.toStage === 'meeting' && meetings.some((t) => near(t.createdAt, h.changedAt)));
    if (covered) continue;
    let title = `المرحلة: ${STAGE_LABEL[h.toStage]}`;
    let body = `من ${STAGE_LABEL[h.fromStage]}`;
    if (h.toStage === 'lost' && h === lastLost && lead.stage === 'lost' && lead.lostReason) {
      title = `خسارة · ${LOST_REASON_LABEL[lead.lostReason]}`;
      body = lead.lostNote ?? body;
    }
    if (h.toStage === 'won' && h === lastWon && lead.stage === 'won' && lead.wonValue !== null) {
      title = 'تم الإغلاق';
      body = `${formatSAR(lead.wonValue)} ${lead.wonBilling === 'one_time' ? 'مرة واحدة' : 'شهرياً'}`;
    }
    events.push({ id: `stage:${h.id}`, at: h.changedAt, title, body, tone: h.toStage });
  }

  // newest first; at the same moment the later step of the flow comes first
  const order = (e: TimelineEvent) => (e.id.startsWith('fu:') || e.id.startsWith('cancel:') ? 1 : e.id === 'created' ? -1 : 0);
  return events.sort((a, b) => b.at.getTime() - a.at.getTime() || order(b) - order(a));
}
