import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { isStage, type Stage } from '@/components/ui/stages';
import type { Json } from '@/lib/database.types';
import type { ContactTime } from '@/lib/followups';
import { parseChecklist } from '@/lib/scoring';
import { supabase } from '@/lib/supabase';
import { DEFAULT_TEMPLATES, templateDrafts, TONES, type Drafts, type MessageKind, type Tone } from '../../supabase/functions/_shared/message';
import { isTaskKind, type TaskKind } from './tasks-meta';

export type { Drafts, MessageKind, Tone };

export type Role = 'owner' | 'manager' | 'employee';
const ROLES: Role[] = ['owner', 'manager', 'employee'];
const TIMES: ContactTime[] = ['morning', 'afternoon', 'evening'];
const KINDS: MessageKind[] = ['first', 'followup_3', 'followup_7', 'custom'];

export interface MessageLead {
  id: string;
  businessName: string;
  contactName: string | null;
  contactRole: Role | null;
  phone: string | null;
  stage: Stage;
  waConsent: boolean;
  doNotContact: boolean;
  bestTime: ContactTime | null;
  activityTypeId: string | null;
}

export interface SavedDraft {
  id: string;
  body: string;
  tone: Tone;
  generatedBy: 'ai' | 'fallback' | 'manual';
}

export interface MessageContext {
  lead: MessageLead;
  kind: MessageKind;
  task: { id: string; kind: TaskKind; title: string } | null;
  /** the open draft for this lead + kind + task, to resume instead of regenerating */
  draft: SavedDraft | null;
  defaultTone: Tone;
  senderName: string;
  observation: string;
  weaknesses: string[];
  templates: { id: string; body: string; activityTypeId: string | null }[];
}

/** Which message a task asks for. Meetings and quotes have no draft rules, so they are free text. */
export function kindForTask(kind: TaskKind): MessageKind {
  if (kind === 'first_message') return 'first';
  if (kind === 'followup_3' || kind === 'followup_7') return kind;
  return 'custom';
}

/** ?kind= wins, then the task, then the stage: before any message it is the first one. */
export function resolveKind(param: string | null, taskKind: TaskKind | null, stage: Stage): MessageKind {
  const fromParam = KINDS.find((k) => k === param);
  if (fromParam) return fromParam;
  if (taskKind) return kindForTask(taskKind);
  return stage === 'not_visited' || stage === 'visited' ? 'first' : 'custom';
}

/** Weakness ids from the assessment → their phrases from the activity checklist. */
export function weaknessPhrases(ids: unknown, checklist: unknown): string[] {
  if (!Array.isArray(ids)) return [];
  const general = parseChecklist(checklist ?? {}).general;
  return ids
    .filter((x): x is string => typeof x === 'string')
    .slice(0, 2)
    .map((id) => {
      const item = general.find((g) => g.id === id);
      return item?.weakness ?? item?.label ?? id;
    });
}

/**
 * The task a draft belongs to. A first message is one per lead whether it is
 * opened right after the visit or from its «أرسل الرسالة الأولى» task (the
 * RPC closes that task by kind), so it never carries a task id.
 */
export function messageTaskId(kind: MessageKind, task: { id: string } | null): string | null {
  return kind === 'first' ? null : (task?.id ?? null);
}

function isTone(v: unknown): v is Tone {
  return TONES.some((t) => t === v);
}

export async function fetchMessageContext(leadId: string, taskId: string | null, kindParam: string | null): Promise<MessageContext | null> {
  const [lead, task, profile, visit, assessment] = await Promise.all([
    supabase
      .from('leads')
      .select('id, business_name, contact_name, contact_role, phone_e164, stage, wa_consent, do_not_contact, best_contact_time, activity_type_id, activity:activity_types(checklist)')
      .eq('id', leadId)
      .maybeSingle(),
    taskId ? supabase.from('tasks').select('id, kind, title, lead_id').eq('id', taskId).maybeSingle() : Promise.resolve(null),
    supabase.from('profiles').select('full_name, default_tone').maybeSingle(),
    supabase.from('visits').select('key_observation').eq('lead_id', leadId).order('visited_at', { ascending: false }).limit(1).maybeSingle(),
    supabase.from('assessments').select('weaknesses').eq('lead_id', leadId).order('created_at', { ascending: false }).limit(1).maybeSingle(),
  ]);
  if (lead.error) throw new Error(lead.error.message);
  if (task?.error) throw new Error(task.error.message);
  if (profile.error) throw new Error(profile.error.message);
  if (!lead.data) return null;
  const l = lead.data;
  if (!isStage(l.stage)) throw new Error('مرحلة غير معروفة');

  const t = task?.data && task.data.lead_id === l.id && isTaskKind(task.data.kind) ? { id: task.data.id, kind: task.data.kind, title: task.data.title } : null;
  const kind = resolveKind(kindParam, t?.kind ?? null, l.stage);

  let draftQuery = supabase.from('messages').select('id, body, tone, generated_by').eq('lead_id', l.id).eq('kind', kind).eq('status', 'draft');
  const draftTask = messageTaskId(kind, t);
  draftQuery = draftTask ? draftQuery.eq('task_id', draftTask) : draftQuery.is('task_id', null);
  const templateKind = kind === 'custom' ? null : kind;
  const [draft, templates] = await Promise.all([
    draftQuery.order('created_at', { ascending: false }).limit(1).maybeSingle(),
    templateKind ? supabase.from('message_templates').select('id, body, activity_type_id').eq('kind', templateKind) : Promise.resolve(null),
  ]);
  if (draft.error) throw new Error(draft.error.message);

    const d = draft.data;
  return {
    lead: {
      id: l.id,
      businessName: l.business_name,
      contactName: l.contact_name?.trim() || null,
      contactRole: ROLES.find((r) => r === l.contact_role) ?? null,
      phone: l.phone_e164,
      stage: l.stage,
      waConsent: l.wa_consent,
      doNotContact: l.do_not_contact,
      bestTime: TIMES.find((x) => x === l.best_contact_time) ?? null,
      activityTypeId: l.activity_type_id,
    },
    kind,
    task: t,
    draft: d
      ? {
          id: d.id,
          body: d.body,
          tone: isTone(d.tone) ? d.tone : 'friendly',
          generatedBy: d.generated_by === 'ai' || d.generated_by === 'manual' ? d.generated_by : 'fallback',
        }
      : null,
    defaultTone: isTone(profile.data?.default_tone) ? profile.data.default_tone : 'friendly',
    senderName: (profile.data?.full_name ?? '').trim().split(/\s+/)[0] ?? '',
    observation: visit.data?.key_observation?.trim() ?? '',
    weaknesses: weaknessPhrases(assessment.data?.weaknesses, l.activity?.checklist),
    templates: (templates?.data ?? []).map((x) => ({ id: x.id, body: x.body, activityTypeId: x.activity_type_id })),
  };
}

export function useMessageContext(leadId: string, taskId: string | null, kindParam: string | null) {
  return useQuery({
    queryKey: ['message-context', leadId, taskId, kindParam],
    queryFn: () => fetchMessageContext(leadId, taskId, kindParam),
    // the screen keeps its own editing state; never refetch under the user's fingers
    staleTime: Infinity,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  });
}

export interface Generated {
  messageId: string | null;
  tone: Tone;
  drafts: Drafts;
  generatedBy: 'ai' | 'fallback';
  observation: string;
  weaknesses: string[];
}

function parseGenerated(raw: unknown): Generated | null {
  if (!raw || typeof raw !== 'object') return null;
  const r = raw as Record<string, unknown>;
  const d = r.drafts as Record<string, unknown> | null | undefined;
  if (typeof r.message_id !== 'string' || !isTone(r.tone) || !d || typeof d.friendly !== 'string' || typeof d.formal !== 'string' || typeof d.short !== 'string') return null;
  return {
    messageId: r.message_id,
    tone: r.tone,
    drafts: { friendly: d.friendly, formal: d.formal, short: d.short },
    generatedBy: r.generated_by === 'ai' ? 'ai' : 'fallback',
    observation: typeof r.observation === 'string' ? r.observation : '',
    weaknesses: Array.isArray(r.weaknesses) ? r.weaknesses.filter((w): w is string => typeof w === 'string') : [],
  };
}

/** The same ready template the function falls back to, filled in the browser. */
export function localDrafts(ctx: MessageContext): { drafts: Drafts; templateId: string | null } {
  const kind = ctx.kind === 'custom' ? 'first' : ctx.kind;
  const tpl = ctx.templates.find((t) => t.activityTypeId && t.activityTypeId === ctx.lead.activityTypeId) ?? ctx.templates.find((t) => !t.activityTypeId) ?? null;
  const drafts = templateDrafts(tpl?.body ?? DEFAULT_TEMPLATES[kind], {
    senderName: ctx.senderName,
    signature: null,
    businessName: ctx.lead.businessName,
    activity: '',
    contactName: ctx.lead.contactName ?? '',
    contactRole: ctx.lead.contactRole,
    observation: ctx.observation,
    weaknesses: ctx.weaknesses,
    services: [],
    templateBody: tpl?.body ?? null,
  });
  return { drafts, templateId: tpl?.id ?? null };
}

/** Saves the text as the open draft: updates `messageId`, or inserts a new row. Returns its id. */
export async function saveDraft(
  ctx: MessageContext,
  input: { messageId: string | null; body: string; tone: Tone | null; generatedBy: 'ai' | 'fallback' | 'manual'; templateId?: string | null },
): Promise<string> {
  if (input.messageId) {
    const { error } = await supabase.from('messages').update({ body: input.body, tone: input.tone }).eq('id', input.messageId).eq('status', 'draft');
    if (error) throw new Error(error.message);
    return input.messageId;
  }
  const { data, error } = await supabase
    .from('messages')
    .insert({
      lead_id: ctx.lead.id,
      kind: ctx.kind,
      body: input.body,
      status: 'draft',
      tone: input.tone,
      generated_by: input.generatedBy,
      template_id: input.templateId ?? null,
      task_id: messageTaskId(ctx.kind, ctx.task),
    })
    .select('id')
    .single();
  if (error) throw new Error(error.message);
  return data.id;
}

/**
 * Asks generate-message for drafts in the three tones. Any failure (offline,
 * function down, bad answer) falls back to the ready template, filled here and
 * saved as the draft, so the screen always has text to send.
 */
export async function generateMessage(ctx: MessageContext, tone: Tone, current: { messageId: string | null }): Promise<Generated> {
  if (navigator.onLine) {
    try {
      const res = await supabase.functions.invoke<unknown>('generate-message', {
        body: { lead_id: ctx.lead.id, kind: ctx.kind, tone, task_id: messageTaskId(ctx.kind, ctx.task) },
      });
      if (!res.error) {
        const g = parseGenerated(res.data);
        if (g) return g;
      }
    } catch {
      // fall through to the template
    }
  }
  const { drafts, templateId } = localDrafts(ctx);
  let messageId = current.messageId;
  try {
    messageId = await saveDraft(ctx, { messageId, body: drafts[tone], tone, generatedBy: 'fallback', templateId });
  } catch {
    // offline: the draft is saved when the user confirms sending
  }
  return { messageId, tone, drafts, generatedBy: 'fallback', observation: ctx.observation, weaknesses: ctx.weaknesses };
}

export interface FollowupInput {
  kind: 'followup_3' | 'followup_7';
  title: string;
  dueAt: Date;
}

export interface SentResult {
  messageId: string;
  leadId: string;
  alreadySent: boolean;
  stageBefore: Stage | null;
  createdTaskIds: string[];
  closedTaskIds: string[];
  lastContactBefore: string | null;
}

function strings(v: unknown): string[] {
  return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
}

export function parseSentResult(data: Json): SentResult {
  if (!data || typeof data !== 'object' || Array.isArray(data) || typeof data.message_id !== 'string' || typeof data.lead_id !== 'string') {
    throw new Error('استجابة غير متوقعة من الخادم');
  }
  const stage = typeof data.stage_before === 'string' && isStage(data.stage_before) ? data.stage_before : null;
  return {
    messageId: data.message_id,
    leadId: data.lead_id,
    alreadySent: data.already_sent === true,
    stageBefore: stage,
    createdTaskIds: strings(data.created_task_ids),
    closedTaskIds: strings(data.closed_task_ids),
    lastContactBefore: typeof data.last_contact_before === 'string' ? data.last_contact_before : null,
  };
}

/** «نعم، أرسلتها»: the only place a message becomes sent (CLAUDE.md). One transaction on the server. */
export async function confirmSent(input: { messageId: string; body: string; tone: Tone | null; followups: FollowupInput[] }): Promise<SentResult> {
  const { data, error } = await supabase.rpc('confirm_message_sent', {
    p: {
      message_id: input.messageId,
      body: input.body,
      tone: input.tone,
      followups: input.followups.map((f) => ({ kind: f.kind, title: f.title, due_at: f.dueAt.toISOString() })),
    },
  });
  if (error) throw new Error(error.message);
  return parseSentResult(data);
}

export async function undoSent(r: SentResult): Promise<void> {
  const { error } = await supabase.rpc('undo_message_sent', {
    p: {
      message_id: r.messageId,
      stage_before: r.stageBefore,
      created_task_ids: r.createdTaskIds,
      closed_task_ids: r.closedTaskIds,
      last_contact_before: r.lastContactBefore,
    },
  });
  if (error) throw new Error(error.message);
}

/**
 * Refresh everything except the open message screen: it keeps its editing
 * state and leaves right after, and the next visit loads fresh data.
 */
function refreshAfterWrite(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ['message-context'], refetchType: 'none' });
  void qc.invalidateQueries({ predicate: (q) => q.queryKey[0] !== 'message-context' });
}

export function useConfirmSent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: confirmSent,
    onSuccess: () => {
      refreshAfterWrite(qc);
    },
  });
}

/** «سجّلت موافقته»: the user asked the owner and recorded it (C6). */
export function useRecordConsent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (leadId: string) => {
      const { error } = await supabase.from('leads').update({ wa_consent: true }).eq('id', leadId);
      if (error) throw new Error(error.message);
    },
    onSuccess: () => {
      refreshAfterWrite(qc);
    },
  });
}
