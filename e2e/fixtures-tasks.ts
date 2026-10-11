// Tasks as the REST API returns them for «المهام» (src/data/tasks.ts): the
// rows of design/screens/Tasks.dc.html, plus a last follow-up due yesterday
// (flow 2.4 decision on its row) and one done days ago with nothing planned
// after it («تحتاج قراراً»). Times are anchored to the Riyadh day.
import { leadId, phoneOf, taskId } from './fixtures-leads';

const H = 3600 * 1000;
const D = 24 * H;

function riyadhDayStart(d: Date): number {
  const OFFSET = 3 * H;
  return Math.floor((d.getTime() + OFFSET) / D) * D - OFFSET;
}

export interface RawTaskFixture {
  id: string;
  title: string;
  kind: string;
  due_at: string;
  done_at: string | null;
  lead: { id: string; business_name: string; stage: string; phone_e164: string | null; do_not_contact: boolean; contact_name: string | null };
}

interface Spec {
  n: number;
  title: string;
  kind: string;
  shop: string;
  stage: string;
  day: number;
  hour: number;
  done?: boolean;
}

/** In design order (Tasks.dc.html t1…t8), then the day-7 follow-up. */
const SPECS: Spec[] = [
  { n: 1, title: 'أرسل الرسالة الأولى', kind: 'first_message', shop: 'كافيه نسمة الحمدانية', stage: 'visited', day: -2, hour: 16 },
  { n: 2, title: 'متابعة ثانية وأخيرة', kind: 'followup_7', shop: 'مطعم بيت المندي', stage: 'contacted', day: 0, hour: 10 },
  { n: 3, title: 'حدد اجتماعاً', kind: 'schedule_meeting', shop: 'مجمع ابتسامة لطب الأسنان', stage: 'replied', day: 0, hour: 12.5 },
  { n: 4, title: 'متابعة أولى: رابط التقرير', kind: 'followup_3', shop: 'مركز رواء لطب الأسنان', stage: 'contacted', day: 0, hour: 16 },
  { n: 5, title: 'أرسل ريل العينة خلال 24 ساعة', kind: 'custom', shop: 'مطعم الديرة', stage: 'visited', day: 0, hour: 9, done: true },
  { n: 6, title: 'اجتماع في المحل', kind: 'meeting', shop: 'مخبز الحي', stage: 'meeting', day: 2, hour: 16.5 },
  { n: 7, title: 'متابعة أولى: عينة الريل', kind: 'followup_3', shop: 'مطعم ريدان', stage: 'contacted', day: 3, hour: 10 },
  { n: 8, title: 'تابع رد العرض', kind: 'quote_followup', shop: 'كوفي بلاك روز', stage: 'proposal', day: 9, hour: 10 },
  { n: 9, title: 'متابعة ثانية وأخيرة', kind: 'followup_7', shop: 'حلويات السنبلة', stage: 'contacted', day: -1, hour: 19 },
];

export function tasksFixtures(now = new Date()): RawTaskFixture[] {
  const day = riyadhDayStart(now);
  const at = (d: number, hour: number) => new Date(day + d * D + hour * H).toISOString();
  return SPECS.map((s) => ({
    id: taskId(s.n),
    title: s.title,
    kind: s.kind,
    due_at: at(s.day, s.hour),
    done_at: s.done ? new Date(Math.min(now.getTime(), day + 9.5 * H)).toISOString() : null,
    lead: { id: leadId(s.n), business_name: s.shop, stage: s.stage, phone_e164: phoneOf(s.n), do_not_contact: false, contact_name: null },
  }));
}

/** «مطعم شاورما الحي»: the last follow-up went 3 days ago and nothing is planned. */
export function lastFollowupFixtures(now = new Date()): RawTaskFixture[] {
  const day = riyadhDayStart(now);
  return [
    {
      id: taskId(11),
      title: 'متابعة ثانية وأخيرة',
      kind: 'followup_7',
      due_at: new Date(day - 3 * D + 10 * H).toISOString(),
      done_at: new Date(day - 3 * D + 11 * H).toISOString(),
      lead: { id: leadId(11), business_name: 'مطعم شاورما الحي', stage: 'contacted', phone_e164: phoneOf(11), do_not_contact: false, contact_name: null },
    },
  ];
}
