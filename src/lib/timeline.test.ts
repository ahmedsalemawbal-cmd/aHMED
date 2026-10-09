import { describe, expect, it } from 'vitest';
import { buildTimeline, excerpt, followupsWord, type TimelineInput } from './timeline';

const t = (iso: string) => new Date(iso);
const created = t('2026-10-07T07:30:00Z'); // 10:30 Riyadh
const sentAt = t('2026-10-07T08:20:00Z');

function base(over: Partial<TimelineInput> = {}): TimelineInput {
  return {
    lead: { createdAt: created, source: 'visit', contactName: 'خالد العتيبي', contactRole: 'owner', stage: 'contacted', lostReason: null, lostNote: null, wonValue: null, wonBilling: null },
    visits: [{ id: 'v1', visitedAt: created, keyObservation: 'أطباقهم ممتازة وحسابهم بدون فيديو', mediaCount: 3, score: 48 }],
    messages: [
      {
        id: 'm1',
        kind: 'first',
        status: 'sent',
        body: 'السلام عليكم\nلاحظت\nعندي فكرة\nيناسبك؟',
        tone: 'friendly',
        generatedBy: 'ai',
        sentAt,
        repliedAt: null,
      },
    ],
    tasks: [
      { id: 'tf', kind: 'first_message', title: 'أرسل الرسالة الأولى', createdAt: created, dueAt: created, doneAt: sentAt, cancelledAt: null },
      { id: 't3', kind: 'followup_3', title: 'متابعة أولى', createdAt: sentAt, dueAt: t('2026-10-10T16:00:00Z'), doneAt: null, cancelledAt: null },
      { id: 't7', kind: 'followup_7', title: 'متابعة ثانية وأخيرة', createdAt: sentAt, dueAt: t('2026-10-14T16:00:00Z'), doneAt: null, cancelledAt: null },
    ],
    history: [
      { id: 'h0', fromStage: null, toStage: 'visited', changedAt: created },
      { id: 'h1', fromStage: 'visited', toStage: 'contacted', changedAt: sentAt },
    ],
    ...over,
  };
}

describe('timeline (DeskLead.dc.html)', () => {
  it('the first-message flow, newest first, without repeating stage changes', () => {
    const ev = buildTimeline(base());
    expect(ev.map((e) => e.title)).toEqual(['أُنشئت متابعتان', 'تم الإرسال · الرسالة الأولى', 'تمت الزيارة', 'سُجّل العميل']);
    expect(ev[0]?.body).toBe('السبت 10 أكتوبر (عينة ريل) والأربعاء 14 أكتوبر (تذكير أخير)');
    expect(ev[1]).toMatchObject({ body: 'عبر واتساب · نبرة ودّية', quote: 'السلام عليكم\nلاحظت…', tone: 'contacted' });
    expect(ev[2]).toMatchObject({ body: 'الدرجة 48 · 3 ملفات', quote: 'الملاحظة الأبرز: أطباقهم ممتازة وحسابهم بدون فيديو', tone: 'visited' });
    expect(ev[3]).toMatchObject({ body: 'من زيارة ميدانية · خالد العتيبي، مالك', tone: 'not_visited' });
  });

  it('a reply cancels the follow-ups; the reply is shown once', () => {
    const replied = t('2026-10-08T09:00:00Z');
    const input = base();
    const m = input.messages[0];
    if (!m) throw new Error('fixture');
    input.messages = [{ ...m, status: 'replied', repliedAt: replied }];
    input.tasks = input.tasks.map((x) => (x.kind === 'first_message' ? x : { ...x, cancelledAt: replied }));
    input.history.push({ id: 'h2', fromStage: 'contacted', toStage: 'replied', changedAt: replied });
    const ev = buildTimeline(input);
    expect(ev.slice(0, 2).map((e) => e.title)).toEqual(['أُلغيت متابعتان', 'رد العميل']);
    expect(ev.filter((e) => e.title.includes('رد'))).toHaveLength(1);
  });

  it('fallback template, meeting, lost with reason and won with value', () => {
    const input = base();
    const m = input.messages[0];
    if (!m) throw new Error('fixture');
    input.messages = [{ ...m, generatedBy: 'fallback', tone: null }];
    const meetAt = t('2026-10-09T10:00:00Z');
    input.tasks.push({ id: 'tm', kind: 'meeting', title: 'اجتماع في المحل', createdAt: meetAt, dueAt: t('2026-10-12T13:30:00Z'), doneAt: null, cancelledAt: null });
    input.history.push({ id: 'h3', fromStage: 'contacted', toStage: 'meeting', changedAt: meetAt });
    expect(buildTimeline(input).find((e) => e.id === 'sent:m1')?.body).toBe('عبر واتساب · القالب الجاهز');
    const ev = buildTimeline(input);
    expect(ev[0]).toMatchObject({ title: 'حُدد اجتماع · الاثنين 12 أكتوبر', body: 'اجتماع في المحل', tone: 'meeting' });
    expect(ev.some((e) => e.id === 'stage:h3')).toBe(false);

    const lostAt = t('2026-10-15T10:00:00Z');
    const lost = base({ history: [...base().history, { id: 'h4', fromStage: 'contacted', toStage: 'lost', changedAt: lostAt }] });
    lost.lead = { ...lost.lead, stage: 'lost', lostReason: 'not_now', lostNote: 'بعد رمضان' };
    expect(buildTimeline(lost)[0]).toMatchObject({ title: 'خسارة · لا يحتاج الآن', body: 'بعد رمضان', tone: 'lost' });

    const won = base({ history: [...base().history, { id: 'h5', fromStage: 'proposal', toStage: 'won', changedAt: lostAt }] });
    won.lead = { ...won.lead, stage: 'won', wonValue: 2500, wonBilling: 'monthly' };
    expect(buildTimeline(won)[0]).toMatchObject({ title: 'تم الإغلاق', body: '2,500 ر.س شهرياً', tone: 'won' });
  });

  it('a manual stage change without an event is shown with where it came from', () => {
    const at = t('2026-10-20T10:00:00Z');
    const ev = buildTimeline(base({ history: [...base().history, { id: 'h6', fromStage: 'contacted', toStage: 'proposal', changedAt: at }] }));
    expect(ev[0]).toMatchObject({ title: 'المرحلة: عرض سعر', body: 'من تم الإرسال', tone: 'proposal' });
  });

  it('helpers', () => {
    expect([1, 2, 3, 11].map(followupsWord)).toEqual(['متابعة', 'متابعتان', '3 متابعات', '11 متابعة']);
    expect(excerpt('أ\n\nب')).toBe('أ\nب');
  });
});
