// «مطعم ريدان» as in design/screens/Lead.dc.html and DeskLead.dc.html: registered
// at 10:30, visited at 10:45, first message sent at 11:20, two follow-ups after it.
import { ACTIVITIES, SERVICES } from './fixtures-catalog';
import type { Handler } from './mock-supabase';

export const LEAD_ID = 'feed0000-0000-4000-8000-00000000100a';
export const TASK_FIRST = 'feed0000-0000-4000-8000-0000000010f1';
export const TASK_F3 = 'feed0000-0000-4000-8000-0000000010f3';
export const TASK_F7 = 'feed0000-0000-4000-8000-0000000010f7';
export const TASK_MEET = 'feed0000-0000-4000-8000-0000000010aa';
export const MSG_FIRST = 'feed0000-0000-4000-8000-0000000010b1';
export const MSG_DRAFT = 'feed0000-0000-4000-8000-0000000010b3';

export const FIRST_BODY =
  'السلام عليكم أستاذ خالد، معك أحمد، زرتكم اليوم في مطعم ريدان.\nلاحظت أن أطباقكم شكلها ممتاز، لكن حسابكم في إنستقرام بدون أي فيديو لها.\nعندي فكرة ريل قصير كل أسبوع يوصل المطعم لسكان الحي.\nيناسبك أرسل لك عينة مجانية من تصويري اليوم؟';
export const DRAFT_BODY = 'هلا أستاذ خالد، جهزت لك عينة ريل من تصوير يوم الزيارة.\nتحب أرسلها لك هنا؟';

const MIN = 60_000;
const H = 60 * MIN;
const D = 24 * H;
const OFFSET = 3 * H;

/** Midnight in Riyadh (UTC+3) of the day containing `d`, as epoch ms. */
export function riyadhDayStart(d: Date): number {
  return Math.floor((d.getTime() + OFFSET) / D) * D - OFFSET;
}

/** A moment `days` after today at hh:mm in Riyadh. */
export function riyadhAt(now: Date, days: number, hour: number, minute = 0): Date {
  return new Date(riyadhDayStart(now) + days * D + hour * H + minute * MIN);
}

/** YYYY-MM-DD in Riyadh for <input type="date">. */
export function riyadhDate(d: Date): string {
  return new Date(d.getTime() + OFFSET).toISOString().slice(0, 10);
}

/** «السبت 10 أكتوبر», the same way the app writes it. */
export function dayLong(d: Date): string {
  return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { timeZone: 'Asia/Riyadh', weekday: 'long', day: 'numeric', month: 'long' })
    .format(d)
    .replace(/[\u202f\u00a0]/g, ' ')
    .replace('،', '');
}

type Row = Record<string, unknown>;

export interface LeadDb {
  leads: Row[];
  visits: Row[];
  assessments: Row[];
  messages: Row[];
  tasks: Row[];
  stage_history: Row[];
  lead_services: Row[];
  quotes: Row[];
}

function service(id: string, status: 'suggested' | 'dropped'): Row {
  const s = SERVICES.find((x) => x.id === id);
  if (!s) throw new Error(`fixture service ${id}`);
  return { status, service: { id: s.id, name: s.name, billing: s.billing, price_from: s.price_from, sort_order: s.sort_order } };
}

export function leadDb(now = new Date(), lead: Row = {}): LeadDb {
  const iso = (days: number, hour: number, minute = 0) => riyadhAt(now, days, hour, minute).toISOString();
  const created = iso(0, 10, 30);
  const visited = iso(0, 10, 45);
  const sent = iso(0, 11, 20);
  const followupsAt = iso(0, 11, 21);
  return {
    leads: [
      {
        id: LEAD_ID,
        business_name: 'مطعم ريدان',
        activity_type_id: 'a-rest',
        contact_name: 'خالد العتيبي',
        contact_role: 'owner',
        is_decision_maker: true,
        phone_e164: '966551234567',
        best_contact_time: 'evening',
        wa_consent: true,
        do_not_contact: false,
        address: 'حي الحمدانية',
        instagram_url: 'https://www.instagram.com/raydan.rest/',
        source: 'visit',
        stage: 'contacted',
        score: 48,
        priority: 'hot',
        expected_value: 2500,
        lost_reason: null,
        lost_note: null,
        won_value: null,
        won_billing: null,
        created_at: created,
        stage_changed_at: sent,
        last_contact_at: sent,
        activity: { name: 'مطعم', checklist: ACTIVITIES[0]?.checklist },
        ...lead,
      },
    ],
    visits: [{ id: 'v1', visited_at: visited, key_observation: 'أطباق ممتازة وحساب بدون فيديو', visit_media: [{ count: 3 }] }],
    assessments: [{ visit_id: 'v1', score: 48, weaknesses: ['s3', 'g3', 'web'], created_at: visited }],
    messages: [
      { id: MSG_FIRST, kind: 'first', status: 'sent', body: FIRST_BODY, tone: 'friendly', generated_by: 'ai', sent_at: sent, replied_at: null, created_at: iso(0, 11, 15), task_id: null },
    ],
    tasks: [
      { id: TASK_FIRST, lead_id: LEAD_ID, kind: 'first_message', title: 'أرسل الرسالة الأولى', created_at: created, due_at: created, done_at: sent, cancelled_at: null },
      { id: TASK_F3, lead_id: LEAD_ID, kind: 'followup_3', title: 'متابعة أولى', created_at: followupsAt, due_at: iso(3, 19), done_at: null, cancelled_at: null },
      { id: TASK_F7, lead_id: LEAD_ID, kind: 'followup_7', title: 'متابعة ثانية وأخيرة', created_at: followupsAt, due_at: iso(7, 19), done_at: null, cancelled_at: null },
    ],
    stage_history: [
      { id: 'h0', from_stage: null, to_stage: 'visited', changed_at: created },
      { id: 'h1', from_stage: 'visited', to_stage: 'contacted', changed_at: sent },
    ],
    lead_services: [service('s-maps', 'suggested'), service('s-video', 'suggested'), service('s-ads', 'dropped')],
    quotes: [],
  };
}

/** The draft of the first follow-up, saved from its task. */
export function withDraft(db: LeadDb, now = new Date()): LeadDb {
  const draft = { id: MSG_DRAFT, kind: 'followup_3', status: 'draft', body: DRAFT_BODY, tone: 'friendly', generated_by: 'ai', sent_at: null, replied_at: null, created_at: riyadhAt(now, 0, 12).toISOString(), task_id: TASK_F3 };
  return { ...db, messages: [draft, ...db.messages] };
}

/** After «العميل رد»: the follow-ups are cancelled and «حدد اجتماعاً» is open. */
export function repliedDb(now = new Date()): LeadDb {
  const db = leadDb(now, { stage: 'replied' });
  const replied = riyadhAt(now, 0, 12).toISOString();
  return {
    ...db,
    messages: db.messages.map((m) => ({ ...m, status: 'replied', replied_at: replied })),
    tasks: [
      ...db.tasks.map((t) => (t.done_at ? t : { ...t, cancelled_at: replied })),
      { id: TASK_MEET, lead_id: LEAD_ID, kind: 'schedule_meeting', title: 'حدد اجتماعاً', created_at: replied, due_at: replied, done_at: null, cancelled_at: null },
    ],
    stage_history: [...db.stage_history, { id: 'h2', from_stage: 'contacted', to_stage: 'replied', changed_at: replied }],
  };
}

const RPC: Record<string, unknown> = {
  'rpc/mark_replied': { lead_id: LEAD_ID, stage_before: 'contacted', cancelled_task_ids: [TASK_F3, TASK_F7], task_id: TASK_MEET },
  'rpc/set_meeting': { lead_id: LEAD_ID, stage_before: 'replied', task_id: 'feed0000-0000-4000-8000-0000000010ab' },
  'rpc/mark_won': { lead_id: LEAD_ID, stage_before: 'contacted' },
  'rpc/mark_lost': { updated: 1 },
  'rpc/set_stage': { updated: 1 },
};

function eqValue(params: URLSearchParams, key: string): string | null {
  const v = params.get(key);
  return v?.startsWith('eq.') ? v.slice(3) : null;
}

export interface HandlerOpts {
  /** answer for the lead row: a status code to fail with */
  leadStatus?: () => number;
  leadDelayMs?: number;
  rpcStatus?: number;
}

/** REST stand-in: rows by table, filtered by `id=eq.` and `lead_id=eq.`; writes succeed. */
export function leadHandler(db: () => LeadDb, o: HandlerOpts = {}): Handler {
  return (table, req, params) => {
    const method = req.method();
    if (table.startsWith('rpc/')) return o.rpcStatus ? { status: o.rpcStatus } : { body: RPC[table] ?? {} };
    if (method === 'PATCH' || method === 'DELETE') return { status: 204, body: [] };
    if (method === 'POST') return { status: 201, body: [] };
    if (table === 'profiles') return { body: [{ full_name: 'أحمد السالم', brand_name: 'استوديو الحي', default_tone: 'friendly', weekly_visit_goal: 20 }] };
    const all = db();
    const rows = (all as unknown as Record<string, Row[] | undefined>)[table];
    if (!rows) return { body: [] };
    if (method === 'HEAD') return { count: rows.length };
    const id = eqValue(params, 'id');
    const leadId = eqValue(params, 'lead_id');
    const body = rows.filter((r) => (id === null || r.id === id) && (leadId === null || table === 'leads' || r.lead_id === undefined || r.lead_id === leadId));
    if (table === 'leads') {
      const status = o.leadStatus?.() ?? 200;
      if (status !== 200) return { status };
      return { body, delayMs: o.leadDelayMs };
    }
    return { body };
  };
}
