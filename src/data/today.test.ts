import { describe, expect, it } from 'vitest';
import { goalRemainingText, splitDue, toTaskItem, visitsWord } from './today';
import { rowAction, type TaskItem } from './tasks-meta';

const NOW = new Date('2026-10-07T07:45:00Z');
const lead = { id: 'l1', business_name: 'مطعم الديرة', stage: 'contacted', phone_e164: '966551234567', do_not_contact: false, contact_name: 'سلمان' };

describe('toTaskItem', () => {
  it('takes the first non-empty line of the latest draft as preview', () => {
    const t = toTaskItem({
      id: 't1',
      title: 'متابعة أولى',
      kind: 'followup_3',
      due_at: '2026-10-07T09:00:00Z',
      lead,
      messages: [
        { body: 'قديم', status: 'draft', created_at: '2026-10-01T00:00:00Z' },
        { body: '\nهلا أستاذ سلمان، جهزت لك عينة ريل\nسطر ثانٍ', status: 'draft', created_at: '2026-10-05T00:00:00Z' },
        { body: 'مرسلة', status: 'sent', created_at: '2026-10-06T00:00:00Z' },
      ],
    });
    expect(t?.preview).toBe('هلا أستاذ سلمان، جهزت لك عينة ريل');
    expect(t?.kind).toBe('followup_3');
  });
  it('unknown kinds become custom; unknown stages are dropped', () => {
    expect(toTaskItem({ id: 't', title: 'x', kind: 'weird', due_at: '2026-10-07T09:00:00Z', lead, messages: [] })?.kind).toBe('custom');
    expect(toTaskItem({ id: 't', title: 'x', kind: 'custom', due_at: '2026-10-07T09:00:00Z', lead: { ...lead, stage: 'qr' }, messages: [] })).toBeNull();
  });
});

describe('splitDue', () => {
  const mk = (id: string, iso: string): TaskItem => ({
    id,
    title: id,
    kind: 'followup_3',
    dueAt: new Date(iso),
    preview: null,
    lead: { id: 'l', businessName: 'x', stage: 'contacted', phone: '966551234567', doNotContact: false, contactName: null },
  });
  it('before Riyadh midnight is overdue, after is today, sorted by time', () => {
    const r = splitDue([mk('b', '2026-10-07T10:00:00Z'), mk('late', '2026-10-06T20:59:00Z'), mk('a', '2026-10-06T21:00:00Z')], NOW);
    expect(r.overdue.map((t) => t.id)).toEqual(['late']);
    expect(r.today.map((t) => t.id)).toEqual(['a', 'b']);
  });
  it('row actions: WhatsApp for messages, call for meetings, none without phone or with do_not_contact', () => {
    const t = mk('x', '2026-10-07T10:00:00Z');
    expect(rowAction(t)).toBe('whatsapp');
    expect(rowAction({ ...t, kind: 'schedule_meeting' })).toBe('call');
    expect(rowAction({ ...t, lead: { ...t.lead, doNotContact: true } })).toBeNull();
    expect(rowAction({ ...t, kind: 'meeting', lead: { ...t.lead, doNotContact: true } })).toBe('call');
    expect(rowAction({ ...t, lead: { ...t.lead, phone: null } })).toBeNull();
  });
});

describe('weekly goal text', () => {
  it.each([
    [14, 20, 3, 'باقي 6 زيارات حتى الخميس'],
    [19, 20, 1, 'باقي زيارة واحدة حتى الخميس'],
    [18, 20, 4, 'باقي زيارتان حتى الخميس'],
    [5, 20, 0, 'باقي 15 زيارة حتى الخميس'],
    [20, 20, 2, 'حققت هدف الأسبوع.'],
    [25, 20, 2, 'حققت هدف الأسبوع.'],
    [10, 20, 5, 'باقي 10 زيارات من هدف هذا الأسبوع'],
  ])('%i of %i on weekday %i', (done, goal, wd, text) => {
    expect(goalRemainingText(done, goal, wd)).toBe(text);
  });
  it('visitsWord agreement', () => {
    expect([1, 2, 3, 10, 11].map(visitsWord)).toEqual(['زيارة واحدة', 'زيارتان', '3 زيارات', '10 زيارات', '11 زيارة']);
  });
});
