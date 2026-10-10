import { describe, expect, it } from 'vitest';
import type { LeadTask } from '@/data/lead';
import { addDays } from '@/lib/dates';
import { parseChecklist } from '@/lib/scoring';
import {
  canMarkReplied,
  consentView,
  contactNameText,
  draftPath,
  dueText,
  eventTime,
  hasOpenMeetingTask,
  headerSub,
  instagramView,
  leadTabs,
  lostToast,
  meetingAt,
  meetingToast,
  messageStatus,
  moreWeaknessesText,
  nextAction,
  noTaskView,
  openFollowupCount,
  parseAmount,
  quoteStatus,
  registeredLong,
  registeredText,
  repliedIntro,
  repliedToast,
  stageToast,
  tabFromParam,
  tabKeyTarget,
  taskClosedText,
  taskHeadline,
  weaknessTexts,
  whatsappPath,
  wonToast,
} from './text';

// Wednesday 7 October 2026, 10:45 in Riyadh
const NOW = new Date('2026-10-07T07:45:00Z');
const at = (iso: string) => new Date(iso);

function task(over: Partial<LeadTask> = {}): LeadTask {
  return { id: 't1', kind: 'followup_3', title: 'متابعة أولى', createdAt: NOW, dueAt: addDays(NOW, 3), doneAt: null, cancelledAt: null, ...over };
}

describe('times (Lead.dc.html)', () => {
  it('timeline time: today, yesterday, then the day and time', () => {
    expect(eventTime(at('2026-10-07T08:20:00Z'), NOW)).toBe('اليوم 11:20 ص');
    expect(eventTime(at('2026-10-06T12:10:00Z'), NOW)).toBe('أمس 3:10 م');
    expect(eventTime(at('2026-10-03T08:20:00Z'), NOW)).toBe('السبت 3 أكتوبر · 11:20 ص');
    expect(eventTime(at('2026-10-08T07:00:00Z'), NOW)).toBe('غداً 10:00 ص');
  });

  it('registered: mobile short form and desktop long form', () => {
    expect(registeredText(at('2026-10-07T07:30:00Z'), NOW)).toBe('سُجّل اليوم');
    expect(registeredText(addDays(NOW, -1), NOW)).toBe('سُجّل أمس');
    expect(registeredText(addDays(NOW, -2), NOW)).toBe('سُجّل قبل يومين');
    expect(registeredText(addDays(NOW, -3), NOW)).toBe('سُجّل قبل 3 أيام');
    expect(registeredLong(at('2026-10-07T07:30:00Z'), NOW, 'visit')).toBe('سُجّل من زيارة ميدانية اليوم 10:30 ص');
    expect(registeredLong(at('2026-10-04T07:30:00Z'), NOW, 'import')).toBe('سُجّل من الاستيراد يوم الأحد 4 أكتوبر');
  });

  it('header sub line skips what is missing', () => {
    const lead = { activity: 'مطعم', address: 'حي الحمدانية', createdAt: at('2026-10-07T07:30:00Z'), source: 'visit' as const };
    expect(headerSub(lead, NOW, false)).toBe('مطعم · حي الحمدانية · سُجّل اليوم');
    expect(headerSub({ ...lead, address: null }, NOW, false)).toBe('مطعم · سُجّل اليوم');
    expect(headerSub(lead, NOW, true)).toBe('مطعم · حي الحمدانية · سُجّل من زيارة ميدانية اليوم 10:30 ص');
  });

  it('due text: ahead, today, tomorrow and overdue', () => {
    expect(dueText(addDays(NOW, 3), NOW)).toEqual({ text: 'السبت 10 أكتوبر · بعد 3 أيام', overdue: false });
    expect(dueText(addDays(NOW, 2), NOW)).toEqual({ text: 'الجمعة 9 أكتوبر · بعد يومين', overdue: false });
    expect(dueText(at('2026-10-07T13:00:00Z'), NOW)).toEqual({ text: 'اليوم 4:00 م', overdue: false });
    expect(dueText(at('2026-10-07T05:00:00Z'), NOW)).toEqual({ text: 'اليوم 8:00 ص', overdue: false });
    expect(dueText(at('2026-10-08T07:00:00Z'), NOW)).toEqual({ text: 'غداً 10:00 ص', overdue: false });
    expect(dueText(addDays(NOW, -2), NOW)).toEqual({ text: 'الاثنين 5 أكتوبر · متأخرة منذ يومين', overdue: true });
  });
});

describe('next step', () => {
  it('headline: kind label with the design hint for follow-ups', () => {
    expect(taskHeadline({ kind: 'followup_3', title: 'متابعة أولى' })).toBe('متابعة أولى: أرسل عينة الريل');
    expect(taskHeadline({ kind: 'followup_7', title: 'متابعة ثانية وأخيرة' })).toBe('متابعة ثانية: تذكير أخير بدون ضغط');
    expect(taskHeadline({ kind: 'first_message', title: 'أرسل الرسالة الأولى' })).toBe('أرسل الرسالة الأولى');
    expect(taskHeadline({ kind: 'meeting', title: 'اجتماع في المحل' })).toBe('اجتماع في المحل');
    expect(taskHeadline({ kind: 'meeting', title: 'مع المالك في الفرع' })).toBe('اجتماع: مع المالك في الفرع');
    expect(taskHeadline({ kind: 'custom', title: 'اطبع المنيو' })).toBe('اطبع المنيو');
    expect(taskHeadline({ kind: 'custom', title: '  ' })).toBe('مهمة');
  });

  it('action by kind', () => {
    const lead = { id: 'L', doNotContact: false };
    expect(nextAction({ id: 'a', kind: 'first_message' }, lead)).toEqual({ kind: 'message', label: 'جهّز الرسالة الأولى', to: '/leads/L/message?task=a' });
    expect(nextAction({ id: 'b', kind: 'followup_3' }, lead)).toEqual({ kind: 'message', label: 'جهّز رسالة المتابعة', to: '/leads/L/message?task=b' });
    expect(nextAction({ id: 'c', kind: 'followup_7' }, lead)).toMatchObject({ label: 'جهّز رسالة المتابعة' });
    expect(nextAction({ id: 'd', kind: 'quote_followup' }, lead)).toMatchObject({ kind: 'message', label: 'جهّز الرسالة' });
    expect(nextAction({ id: 'e', kind: 'retry' }, lead)).toMatchObject({ kind: 'message', label: 'جهّز الرسالة', to: '/leads/L/message?task=e' });
    expect(nextAction({ id: 'f', kind: 'schedule_meeting' }, lead)).toEqual({ kind: 'meeting', label: 'حدد اجتماعاً' });
    expect(nextAction({ id: 'g', kind: 'meeting' }, lead)).toEqual({ kind: 'quote', label: 'أنشئ عرض سعر', to: '/leads/L/quotes/new' });
    expect(nextAction({ id: 'h', kind: 'custom' }, lead)).toEqual({ kind: 'done', label: 'تم' });
    // do_not_contact: never a message
    expect(nextAction({ id: 'a', kind: 'followup_3' }, { id: 'L', doNotContact: true })).toEqual({ kind: 'done', label: 'تم' });
  });

  it('without an open task: won, lost, or nothing', () => {
    const base = { stage: 'contacted' as const, wonValue: null, wonBilling: null, lostReason: null, lostNote: null };
    expect(noTaskView({ ...base, stage: 'won', wonValue: 2500, wonBilling: 'monthly' })).toEqual({ title: 'تم الإغلاق · 2,500 ر.س شهرياً', body: null });
    expect(noTaskView({ ...base, stage: 'won', wonValue: 4000, wonBilling: 'one_time' }).title).toBe('تم الإغلاق · 4,000 ر.س مرة واحدة');
    expect(noTaskView({ ...base, stage: 'lost', lostReason: 'not_now', lostNote: 'يرجع بعد رمضان' })).toEqual({ title: 'خسارة · لا يحتاج الآن', body: 'يرجع بعد رمضان' });
    expect(noTaskView(base)).toEqual({ title: 'لا خطوة قادمة', body: 'غيّر المرحلة أو سجّل زيارة جديدة.' });
  });

  it('header WhatsApp opens the next message task, else the plain screen', () => {
    expect(whatsappPath('L', { id: 't3', kind: 'followup_3' })).toBe('/leads/L/message?task=t3');
    expect(whatsappPath('L', { id: 'm', kind: 'meeting' })).toBe('/leads/L/message');
    expect(whatsappPath('L', null)).toBe('/leads/L/message');
  });
});

describe('stage actions', () => {
  it('counts the open follow-ups that a reply cancels', () => {
    const tasks = [
      task({ id: 'a', kind: 'first_message', doneAt: NOW }),
      task({ id: 'b', kind: 'followup_3' }),
      task({ id: 'c', kind: 'followup_7' }),
      task({ id: 'd', kind: 'retry', cancelledAt: NOW }),
      task({ id: 'e', kind: 'custom' }),
      task({ id: 'f', kind: 'schedule_meeting', doneAt: NOW }),
    ];
    expect(openFollowupCount(tasks)).toBe(2);
    expect(hasOpenMeetingTask(tasks)).toBe(false);
    expect(hasOpenMeetingTask([...tasks, task({ kind: 'meeting' })])).toBe(true);
    expect(canMarkReplied('contacted')).toBe(true);
    expect(canMarkReplied('won')).toBe(false);
    expect(canMarkReplied('lost')).toBe(false);
  });

  it('replied sentence agrees with the count', () => {
    expect(repliedIntro('contacted', 2, true)).toBe('عند التأكيد تصبح المرحلة «رد»، وتُلغى المتابعتان الباقيتان، وتُنشأ مهمة «حدد اجتماعاً».');
    expect(repliedIntro('contacted', 1, true)).toBe('عند التأكيد تصبح المرحلة «رد»، وتُلغى المتابعة الباقية، وتُنشأ مهمة «حدد اجتماعاً».');
    expect(repliedIntro('visited', 3, true)).toBe('عند التأكيد تصبح المرحلة «رد»، وتُلغى 3 متابعات باقية، وتُنشأ مهمة «حدد اجتماعاً».');
    expect(repliedIntro('contacted', 0, false)).toBe('عند التأكيد تصبح المرحلة «رد».');
    expect(repliedIntro('proposal', 0, true)).toBe('عند التأكيد يُسجَّل رد العميل ويصبح حاراً، وتُنشأ مهمة «حدد اجتماعاً».');
  });

  it('success toasts', () => {
    expect(repliedToast({ stageBefore: 'contacted', cancelledTaskIds: ['a', 'b'], taskId: 'm' })).toEqual({ title: 'المرحلة الآن: رد', message: 'أُلغيت المتابعات الباقية. حدد اجتماعاً.' });
    expect(repliedToast({ stageBefore: 'meeting', cancelledTaskIds: [], taskId: null })).toEqual({ title: 'سُجّل رد العميل' });
    expect(meetingToast(at('2026-10-08T13:30:00Z'))).toEqual({ title: 'حُدد الاجتماع', message: 'الخميس 8 أكتوبر · 4:30 م' });
    expect(wonToast(2500, 'monthly')).toEqual({ title: 'تم الإغلاق', message: '2,500 ر.س شهرياً' });
    expect(wonToast(12000, 'one_time').message).toBe('12,000 ر.س مرة واحدة');
    expect(lostToast(null)).toEqual({ title: 'نُقل إلى خسارة' });
    expect(lostToast(at('2026-11-06T07:00:00Z'))).toEqual({ title: 'نُقل إلى خسارة', message: 'تذكير «أعد المحاولة» يوم الجمعة 6 نوفمبر.' });
    expect(stageToast('proposal')).toBe('المرحلة الآن: عرض سعر');
  });

  it('meeting time is read in Riyadh and must be ahead', () => {
    const r = meetingAt('2026-10-08', '16:30', NOW);
    expect('at' in r && r.at.toISOString()).toBe('2026-10-08T13:30:00.000Z');
    expect(meetingAt('2026-10-07', '10:00', NOW)).toEqual({ error: 'الموعد مضى. اختر وقتاً قادماً.', field: 'time' });
    expect(meetingAt('', '10:00', NOW)).toEqual({ error: 'التاريخ ناقص. اختره من التقويم.', field: 'date' });
    expect(meetingAt('2026-10-08', '', NOW)).toEqual({ error: 'الوقت ناقص. اختر ساعة الاجتماع.', field: 'time' });
  });

  it('deal value accepts separators and Arabic digits', () => {
    expect(parseAmount('2500')).toBe(2500);
    expect(parseAmount('2,500')).toBe(2500);
    expect(parseAmount(' ٢٥٠٠ ')).toBe(2500);
    expect(parseAmount('1500.5')).toBe(1500.5);
    expect(parseAmount('')).toBeNull();
    expect(parseAmount('ألفين')).toBeNull();
    expect(parseAmount('-5')).toBeNull();
  });
});

describe('overview', () => {
  const checklist = parseChecklist({
    general: [
      { id: 's3', label: 'ينشر فيديو أو ريلز', weight: 10, group: 'س', weakness: 'لا ينشر فيديو أو ريلز' },
      { id: 'g3', label: 'يرد على المراجعات', weight: 5, group: 'ق' },
    ],
  });

  it('weakness phrases from the checklist, label as fallback, unknown ids skipped', () => {
    expect(weaknessTexts(['s3', 'g3', 'zz'], checklist)).toEqual(['لا ينشر فيديو أو ريلز', 'يرد على المراجعات']);
    expect(moreWeaknessesText(1)).toBe('ونقطة ضعف أخرى');
    expect(moreWeaknessesText(2)).toBe('ونقطتا ضعف أخريان');
    expect(moreWeaknessesText(4)).toBe('و4 نقاط ضعف أخرى');
    expect(moreWeaknessesText(11)).toBe('و11 نقطة ضعف أخرى');
  });

  it('contact data (DeskLead.dc.html «المسؤول»)', () => {
    expect(contactNameText({ contactName: 'خالد العتيبي', contactRole: 'owner' })).toBe('خالد العتيبي · مالك');
    expect(contactNameText({ contactName: null, contactRole: null })).toBe('غير مسجّل');
    expect(consentView({ waConsent: true, doNotContact: false })).toEqual({ text: 'وافق شفهياً في الزيارة', tone: 'success' });
    expect(consentView({ waConsent: false, doNotContact: false })).toEqual({ text: 'لم يوافق بعد', tone: 'muted' });
    expect(consentView({ waConsent: true, doNotContact: true })).toEqual({ text: 'طلب عدم التواصل', tone: 'danger' });
    expect(instagramView('https://www.instagram.com/raydan.rest/')).toEqual({ href: 'https://www.instagram.com/raydan.rest/', text: 'instagram.com/raydan.rest' });
    expect(instagramView('javascript:alert(1)').href).toBeNull();
  });
});

describe('tabs', () => {
  it('mobile has the overview first; the desktop starts at the timeline', () => {
    expect(leadTabs(false).map((t) => t.label)).toEqual(['نظرة عامة', 'الخط الزمني', 'الرسائل', 'المهام', 'العروض']);
    expect(leadTabs(true).map((t) => t.label)).toEqual(['الخط الزمني', 'الرسائل', 'المهام', 'العروض']);
    expect(tabFromParam(null, false)).toBe('overview');
    expect(tabFromParam('tasks', false)).toBe('tasks');
    expect(tabFromParam('nope', false)).toBe('overview');
    expect(tabFromParam('overview', true)).toBe('timeline');
    expect(tabFromParam(null, true)).toBe('timeline');
  });

  it('arrow keys follow the reading direction and wrap', () => {
    expect(tabKeyTarget('ArrowLeft', 0, 5, true)).toBe(1);
    expect(tabKeyTarget('ArrowRight', 0, 5, true)).toBe(4);
    expect(tabKeyTarget('ArrowRight', 0, 5, false)).toBe(1);
    expect(tabKeyTarget('ArrowLeft', 4, 5, true)).toBe(0);
    expect(tabKeyTarget('Home', 3, 5, true)).toBe(0);
    expect(tabKeyTarget('End', 0, 5, true)).toBe(4);
    expect(tabKeyTarget('Enter', 0, 5, true)).toBeNull();
  });
});

describe('messages, tasks and quotes tabs', () => {
  const m = { status: 'sent' as const, sentAt: at('2026-10-07T08:20:00Z'), repliedAt: null, createdAt: at('2026-10-07T08:00:00Z') };
  it('message status', () => {
    expect(messageStatus({ ...m, status: 'draft', sentAt: null }, NOW)).toEqual({ text: 'مسودة', tone: 'draft' });
    expect(messageStatus(m, NOW)).toEqual({ text: 'أُرسلت اليوم 11:20 ص', tone: 'sent' });
    expect(messageStatus({ ...m, status: 'replied', repliedAt: at('2026-10-06T12:10:00Z') }, NOW)).toEqual({ text: 'ردّ أمس 3:10 م', tone: 'replied' });
  });

  it('a draft reopens its own message screen', () => {
    expect(draftPath('L', { kind: 'followup_3', taskId: 't3' })).toBe('/leads/L/message?task=t3');
    expect(draftPath('L', { kind: 'first', taskId: null })).toBe('/leads/L/message?kind=first');
    expect(draftPath('L', { kind: 'custom', taskId: null })).toBe('/leads/L/message?kind=custom');
  });

  it('closed tasks and quotes', () => {
    expect(taskClosedText({ doneAt: at('2026-10-07T08:20:00Z'), cancelledAt: null }, NOW)).toBe('أُنجزت اليوم 11:20 ص');
    expect(taskClosedText({ doneAt: null, cancelledAt: at('2026-10-06T12:10:00Z') }, NOW)).toBe('أُلغيت أمس 3:10 م');
    expect(quoteStatus({ status: 'draft', sentAt: null }, NOW)).toBe('مسودة');
    expect(quoteStatus({ status: 'sent', sentAt: at('2026-10-07T08:20:00Z') }, NOW)).toBe('أُرسل اليوم 11:20 ص');
  });
});
