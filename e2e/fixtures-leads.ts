// Leads as the REST API returns them for «العملاء» (src/data/leads.ts), with
// the names, stages, scores and values of design/screens/Leads.dc.html and
// DeskLeads.dc.html. Times are anchored to the Riyadh day so texts do not
// depend on the hour the test runs.
const H = 3600 * 1000;
const D = 24 * H;

function riyadhDayStart(d: Date): number {
  const OFFSET = 3 * H;
  return Math.floor((d.getTime() + OFFSET) / D) * D - OFFSET;
}

export const leadId = (n: number) => `0e000000-0000-4000-8000-${n.toString().padStart(12, '0')}`;
export const taskId = (n: number) => `0f000000-0000-4000-8000-${n.toString().padStart(12, '0')}`;

export interface RawLeadFixture {
  id: string;
  business_name: string;
  activity_type_id: string | null;
  contact_name: string | null;
  contact_role: string | null;
  phone_e164: string | null;
  stage: string;
  score: number | null;
  priority: string | null;
  source: string;
  expected_value: number | null;
  created_at: string;
  last_contact_at: string | null;
  stage_changed_at: string;
  do_not_contact: boolean;
  wa_consent: boolean;
  activity: { name: string } | null;
  tasks: { id: string; title: string; kind: string; due_at: string }[];
}

const ACTIVITY: Record<string, string> = { 'a-cafe': 'كافيه', 'a-rest': 'مطعم', 'a-dent': 'مركز أسنان', 'a-bake': 'مخبز', 'a-sweet': 'حلويات' };

interface Spec {
  name: string;
  act: keyof typeof ACTIVITY;
  contact: string | null;
  stage: string;
  score: number | null;
  priority: string | null;
  lastDays: number | null;
  task: { title: string; kind: string; day: number; hour: number } | null;
  value: number | null;
  source?: 'visit' | 'import';
  dnc?: boolean;
  phone?: boolean;
}

/** In design order; index + 1 is the lead and task number. */
const SPECS: Spec[] = [
  { name: 'كافيه نسمة الحمدانية', act: 'a-cafe', contact: 'أ. سارة', stage: 'visited', score: 58, priority: 'warm', lastDays: 4, task: { title: 'أرسل الرسالة الأولى', kind: 'first_message', day: -2, hour: 16 }, value: 1800 },
  { name: 'مطعم ريدان', act: 'a-rest', contact: 'أ. خالد', stage: 'contacted', score: 48, priority: 'hot', lastDays: 0, task: { title: 'متابعة أولى: عينة الريل', kind: 'followup_3', day: 1, hour: 10 }, value: 2500 },
  { name: 'مجمع ابتسامة لطب الأسنان', act: 'a-dent', contact: 'د. ريم', stage: 'replied', score: 44, priority: 'hot', lastDays: 1, task: { title: 'حدد اجتماعاً', kind: 'schedule_meeting', day: 1, hour: 16 }, value: 4000 },
  { name: 'مخبز الحي', act: 'a-bake', contact: 'أ. ماجد', stage: 'meeting', score: 63, priority: 'warm', lastDays: 2, task: { title: 'اجتماع في المحل', kind: 'meeting', day: 2, hour: 16.5 }, value: 1500 },
  { name: 'مطعم بيت المندي', act: 'a-rest', contact: 'أ. فهد', stage: 'contacted', score: 52, priority: 'warm', lastDays: 7, task: { title: 'متابعة ثانية وأخيرة', kind: 'followup_7', day: 3, hour: 10 }, value: 2000 },
  { name: 'كوفي بلاك روز', act: 'a-cafe', contact: 'أ. نواف', stage: 'proposal', score: 39, priority: 'hot', lastDays: 3, task: { title: 'تابع رد العرض', kind: 'quote_followup', day: 4, hour: 10 }, value: 2700 },
  { name: 'مركز رواء لطب الأسنان', act: 'a-dent', contact: 'د. نوف', stage: 'contacted', score: 41, priority: 'warm', lastDays: 3, task: { title: 'متابعة أولى', kind: 'followup_3', day: 5, hour: 16 }, value: 4500 },
  { name: 'مطعم الديرة', act: 'a-rest', contact: 'أ. سلمان', stage: 'visited', score: 55, priority: 'warm', lastDays: 1, task: { title: 'أرسل ريل العينة', kind: 'custom', day: 6, hour: 18 }, value: 2200 },
  { name: 'حلويات السنبلة', act: 'a-sweet', contact: 'أ. هند', stage: 'contacted', score: 72, priority: 'cold', lastDays: 9, task: { title: 'أعد المحاولة', kind: 'retry', day: -1, hour: 10 }, value: 1500 },
  { name: 'كافيه سحابة', act: 'a-cafe', contact: 'أ. عبدالله', stage: 'won', score: 47, priority: 'hot', lastDays: 5, task: { title: 'تسليم أول ريل', kind: 'custom', day: 7, hour: 10 }, value: 1900, dnc: true },
  { name: 'مطعم شاورما الحي', act: 'a-rest', contact: null, stage: 'not_visited', score: null, priority: null, lastDays: null, task: null, value: null, source: 'import', phone: false },
];

/** 05512345NN as stored: 9665512345NN */
export const phoneOf = (n: number) => `9665512345${n.toString().padStart(2, '0')}`;

export function leadsFixtures(now = new Date()): RawLeadFixture[] {
  const day = riyadhDayStart(now);
  const at = (d: number, hour: number) => new Date(day + d * D + hour * H).toISOString();
  return SPECS.map((s, i) => {
    const n = i + 1;
    return {
      id: leadId(n),
      business_name: s.name,
      activity_type_id: s.act,
      contact_name: s.contact,
      contact_role: s.contact ? 'owner' : null,
      phone_e164: s.phone === false ? null : phoneOf(n),
      stage: s.stage,
      score: s.score,
      priority: s.priority,
      source: s.source ?? 'visit',
      expected_value: s.value,
      // newest first in design order
      created_at: new Date(now.getTime() - n * H).toISOString(),
      last_contact_at: s.lastDays === null ? null : s.lastDays === 0 ? new Date(now.getTime() - 60_000).toISOString() : at(-s.lastDays, 11),
      stage_changed_at: at(-1, 9),
      do_not_contact: s.dnc ?? false,
      wa_consent: true,
      activity: { name: ACTIVITY[s.act] ?? '' },
      tasks: s.task ? [{ id: taskId(n), title: s.task.title, kind: s.task.kind, due_at: at(s.task.day, s.task.hour) }] : [],
    };
  });
}

export const NAMES = SPECS.map((s) => s.name);

/** `count` plain leads for pagination: «محل 01» … with scores 1…count. */
export function manyLeads(count: number, now = new Date()): RawLeadFixture[] {
  const day = riyadhDayStart(now);
  return Array.from({ length: count }, (_, i) => {
    const n = i + 1;
    const label = n.toString().padStart(2, '0');
    return {
      id: leadId(100 + n),
      business_name: `محل ${label}`,
      activity_type_id: 'a-rest',
      contact_name: null,
      contact_role: null,
      phone_e164: null,
      stage: 'visited',
      score: n,
      priority: 'warm',
      source: 'visit',
      expected_value: null,
      created_at: new Date(now.getTime() - n * H).toISOString(),
      last_contact_at: null,
      stage_changed_at: new Date(day).toISOString(),
      do_not_contact: false,
      wa_consent: true,
      activity: { name: 'مطعم' },
      // due in order, so «الإجراء القادم» lists them 01, 02, …
      tasks: [{ id: taskId(100 + n), title: `مهمة ${label}`, kind: 'custom', due_at: new Date(day + 2 * D + n * 60_000).toISOString() }],
    };
  });
}
