import { useQuery } from '@tanstack/react-query';
import { isPriority, isStage, type Priority, type Stage } from '@/components/ui/stages';
import type { ContactTime } from '@/lib/followups';
import type { Role, Source } from '@/lib/leads-list';
import { parseChecklist, type Checklist } from '@/lib/scoring';
import { supabase } from '@/lib/supabase';
import type { LostReason, TimelineInput } from '@/lib/timeline';
import { isTaskKind, type TaskKind } from './tasks-meta';

const ROLES: Role[] = ['owner', 'manager', 'employee'];
const TIMES: ContactTime[] = ['morning', 'afternoon', 'evening'];
const REASONS: LostReason[] = ['price', 'not_now', 'has_marketer', 'no_reply', 'not_interested', 'other'];
const MSG_KINDS = ['first', 'followup_3', 'followup_7', 'custom'] as const;
const MSG_STATUS = ['draft', 'sent', 'replied'] as const;
const TONES = ['friendly', 'formal', 'short'] as const;
const GEN = ['ai', 'fallback', 'manual'] as const;

export interface LeadDetail {
  id: string;
  businessName: string;
  activityId: string | null;
  activity: string;
  checklist: Checklist;
  contactName: string | null;
  contactRole: Role | null;
  isDecisionMaker: boolean;
  phone: string | null;
  bestTime: ContactTime | null;
  waConsent: boolean;
  doNotContact: boolean;
  address: string | null;
  instagramUrl: string | null;
  source: Source;
  stage: Stage;
  score: number | null;
  priority: Priority | null;
  expectedValue: number | null;
  lostReason: LostReason | null;
  lostNote: string | null;
  wonValue: number | null;
  wonBilling: 'monthly' | 'one_time' | null;
  createdAt: Date;
  stageChangedAt: Date;
  lastContactAt: Date | null;
}

export interface LeadTask {
  id: string;
  kind: TaskKind;
  title: string;
  createdAt: Date;
  dueAt: Date;
  doneAt: Date | null;
  cancelledAt: Date | null;
}

export type LeadMessage = TimelineInput['messages'][number] & { createdAt: Date; taskId: string | null };

export interface LeadService {
  id: string;
  name: string;
  billing: 'monthly' | 'one_time';
  priceFrom: number | null;
  status: 'suggested' | 'dropped';
}

export interface LeadQuote {
  id: string;
  number: string;
  total: number;
  status: 'draft' | 'sent';
  createdAt: Date;
  sentAt: Date | null;
}

export interface LeadData {
  lead: LeadDetail;
  /** ids of the latest assessment's weaknesses (most points lost first) */
  weaknessIds: string[];
  visits: TimelineInput['visits'];
  messages: LeadMessage[];
  tasks: LeadTask[];
  history: TimelineInput['history'];
  services: LeadService[];
  quotes: LeadQuote[];
}

function pick<T extends string>(list: readonly T[], v: unknown): T | null {
  return list.find((x) => x === v) ?? null;
}

function date(v: string | null): Date | null {
  return v ? new Date(v) : null;
}

function one<T>(v: T | T[] | null): T | null {
  return Array.isArray(v) ? (v[0] ?? null) : v;
}

/** Everything the lead page shows, in parallel; every list is read under RLS. */
export async function fetchLead(id: string): Promise<LeadData | null> {
  const [lead, visits, assessments, messages, tasks, history, services, quotes] = await Promise.all([
    supabase
      .from('leads')
      .select(
        'id, business_name, activity_type_id, contact_name, contact_role, is_decision_maker, phone_e164, best_contact_time, wa_consent, do_not_contact, address, instagram_url, source, stage, score, priority, expected_value, lost_reason, lost_note, won_value, won_billing, created_at, stage_changed_at, last_contact_at, activity:activity_types(name, checklist)',
      )
      .eq('id', id)
      .maybeSingle(),
    supabase.from('visits').select('id, visited_at, key_observation, visit_media(count)').eq('lead_id', id).order('visited_at', { ascending: false }),
    supabase.from('assessments').select('visit_id, score, weaknesses, created_at').eq('lead_id', id).order('created_at', { ascending: false }),
    supabase.from('messages').select('id, kind, status, body, tone, generated_by, sent_at, replied_at, created_at, task_id').eq('lead_id', id).order('created_at', { ascending: false }),
    supabase.from('tasks').select('id, kind, title, created_at, due_at, done_at, cancelled_at').eq('lead_id', id).order('due_at'),
    supabase.from('stage_history').select('id, from_stage, to_stage, changed_at').eq('lead_id', id).order('changed_at'),
    supabase.from('lead_services').select('status, service:services(id, name, billing, price_from, sort_order)').eq('lead_id', id),
    supabase.from('quotes').select('id, number, total, status, created_at, sent_at').eq('lead_id', id).order('created_at', { ascending: false }),
  ]);
  for (const r of [lead, visits, assessments, messages, tasks, history, services, quotes]) {
    if (r.error) throw new Error(r.error.message);
  }
  const l = lead.data;
  if (!l) return null;
  if (!isStage(l.stage)) throw new Error('مرحلة غير معروفة');
  const act = one(l.activity);

  const scoreByVisit = new Map<string, number>();
  for (const a of assessments.data ?? []) if (a.visit_id && !scoreByVisit.has(a.visit_id)) scoreByVisit.set(a.visit_id, a.score);
  const latest = (assessments.data ?? [])[0];

  return {
    lead: {
      id: l.id,
      businessName: l.business_name,
      activityId: l.activity_type_id,
      activity: act?.name ?? '',
      checklist: parseChecklist(act?.checklist ?? {}),
      contactName: l.contact_name?.trim() || null,
      contactRole: pick(ROLES, l.contact_role),
      isDecisionMaker: l.is_decision_maker,
      phone: l.phone_e164,
      bestTime: pick(TIMES, l.best_contact_time),
      waConsent: l.wa_consent,
      doNotContact: l.do_not_contact,
      address: l.address?.trim() || null,
      instagramUrl: l.instagram_url?.trim() || null,
      source: l.source === 'import' ? 'import' : 'visit',
      stage: l.stage,
      score: l.score,
      priority: l.priority && isPriority(l.priority) ? l.priority : null,
      expectedValue: l.expected_value,
      lostReason: pick(REASONS, l.lost_reason),
      lostNote: l.lost_note,
      wonValue: l.won_value,
      wonBilling: l.won_billing === 'monthly' || l.won_billing === 'one_time' ? l.won_billing : null,
      createdAt: new Date(l.created_at),
      stageChangedAt: new Date(l.stage_changed_at),
      lastContactAt: date(l.last_contact_at),
    },
    weaknessIds: Array.isArray(latest?.weaknesses) ? latest.weaknesses.filter((w): w is string => typeof w === 'string') : [],
    visits: (visits.data ?? []).map((v) => {
      const media = one(v.visit_media as { count: number } | { count: number }[] | null);
      return { id: v.id, visitedAt: new Date(v.visited_at), keyObservation: v.key_observation, mediaCount: media?.count ?? 0, score: scoreByVisit.get(v.id) ?? null };
    }),
    messages: (messages.data ?? []).flatMap((m): LeadMessage[] => {
      const kind = pick(MSG_KINDS, m.kind);
      const status = pick(MSG_STATUS, m.status);
      if (!kind || !status) return [];
      return [
        {
          id: m.id,
          kind,
          status,
          body: m.body,
          tone: pick(TONES, m.tone),
          generatedBy: pick(GEN, m.generated_by),
          sentAt: date(m.sent_at),
          repliedAt: date(m.replied_at),
          createdAt: new Date(m.created_at),
          taskId: m.task_id,
        },
      ];
    }),
    tasks: (tasks.data ?? []).flatMap((t): LeadTask[] =>
      isTaskKind(t.kind)
        ? [{ id: t.id, kind: t.kind, title: t.title, createdAt: new Date(t.created_at), dueAt: new Date(t.due_at), doneAt: date(t.done_at), cancelledAt: date(t.cancelled_at) }]
        : [],
    ),
    history: (history.data ?? []).flatMap((h) => {
      const to = h.to_stage;
      if (!isStage(to)) return [];
      const from = h.from_stage && isStage(h.from_stage) ? h.from_stage : null;
      return [{ id: h.id, fromStage: from, toStage: to, changedAt: new Date(h.changed_at) }];
    }),
    services: (services.data ?? [])
      .flatMap((s) => {
        const svc = one(s.service);
        if (!svc) return [];
        return [{ svc, status: s.status === 'dropped' ? ('dropped' as const) : ('suggested' as const) }];
      })
      .sort((a, b) => a.svc.sort_order - b.svc.sort_order)
      .map(({ svc, status }) => ({ id: svc.id, name: svc.name, billing: svc.billing === 'one_time' ? 'one_time' : 'monthly', priceFrom: svc.price_from, status })),
    quotes: (quotes.data ?? []).map((q) => ({
      id: q.id,
      number: q.number,
      total: q.total,
      status: q.status === 'sent' ? 'sent' : 'draft',
      createdAt: new Date(q.created_at),
      sentAt: date(q.sent_at),
    })),
  };
}

export function useLead(id: string) {
  return useQuery({ queryKey: ['lead', id], queryFn: () => fetchLead(id) });
}

/** The earliest open task: «الخطوة القادمة». */
export function nextTask(tasks: LeadTask[]): LeadTask | null {
  return tasks.filter((t) => !t.doneAt && !t.cancelledAt).sort((a, b) => a.dueAt.getTime() - b.dueAt.getTime())[0] ?? null;
}
