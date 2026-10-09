import { describe, expect, it } from 'vitest';
import { confirmIntro, contactParts, counterText, firstMovesStage, followupHeading, isOverLimit, linesText, sentToast, weaknessesSentence } from './text';

describe('message screen text', () => {
  it('header contact line: honorific, role, local phone', () => {
    expect(contactParts({ contactName: 'خالد العتيبي', contactRole: 'owner', phone: '966551234567' })).toEqual({ text: 'أ. خالد العتيبي · مالك', phone: '055 123 4567' });
    expect(contactParts({ contactName: null, contactRole: null, phone: null })).toEqual({ text: '', phone: null });
    expect(contactParts({ contactName: null, contactRole: 'manager', phone: null }).text).toBe('مدير');
  });

  it('weaknesses sentence agrees with the count (Message.dc.html)', () => {
    expect(weaknessesSentence(['الفيديو', 'الرد على المراجعات'])).toBe('، ونقطتي الضعف: الفيديو، والرد على المراجعات.');
    expect(weaknessesSentence(['الفيديو'])).toBe('، ونقطة الضعف: الفيديو.');
    expect(weaknessesSentence([])).toBe('.');
  });

  it('line counter with Arabic agreement and the limit per kind', () => {
    expect(linesText(1)).toBe('سطر واحد');
    expect(linesText(2)).toBe('سطران');
    expect(linesText(4)).toBe('4 أسطر');
    expect(linesText(11)).toBe('11 سطراً');
    expect(counterText(4, 'first')).toBe('4 أسطر · الحد 5');
    expect(counterText(3, 'followup_7')).toBe('3 أسطر · الحد 3');
    expect(counterText(3, 'custom')).toBe('3 أسطر');
    expect(isOverLimit(6, 'first')).toBe(true);
    expect(isOverLimit(5, 'first')).toBe(false);
    expect(isOverLimit(4, 'followup_7')).toBe(true);
    expect(isOverLimit(40, 'custom')).toBe(false);
  });

  it('only a first message from visited/not_visited moves the stage', () => {
    expect(firstMovesStage('first', 'visited')).toBe(true);
    expect(firstMovesStage('first', 'not_visited')).toBe(true);
    expect(firstMovesStage('first', 'contacted')).toBe(false);
    expect(firstMovesStage('followup_3', 'visited')).toBe(false);
  });

  it('confirm sheet sentence (SentConfirm.dc.html)', () => {
    expect(confirmIntro({ kind: 'first', stage: 'visited', businessName: 'مطعم ريدان', followups: 2, taskTitle: null })).toBe('عند التأكيد تصبح مرحلة مطعم ريدان «تم الإرسال»، وتُنشأ متابعتان:');
    expect(confirmIntro({ kind: 'followup_3', stage: 'contacted', businessName: 'مطعم ريدان', followups: 0, taskTitle: 'متابعة أولى' })).toBe('عند التأكيد نسجّل الرسالة في سجل مطعم ريدان، وتُغلق مهمة «متابعة أولى».');
    expect(confirmIntro({ kind: 'custom', stage: 'replied', businessName: 'مطعم ريدان', followups: 0, taskTitle: null })).toBe('عند التأكيد نسجّل الرسالة في سجل مطعم ريدان.');
  });

  it('follow-up card heading uses the Riyadh day', () => {
    // 2026-10-10 07:00 UTC = Saturday 10:00 in Riyadh
    expect(followupHeading(0, new Date('2026-10-10T07:00:00Z'))).toBe('متابعة أولى · السبت 10 أكتوبر');
    expect(followupHeading(1, new Date('2026-10-14T07:00:00Z'))).toBe('متابعة ثانية · الأربعاء 14 أكتوبر');
  });

  it('success toast', () => {
    expect(sentToast({ movedStage: true, followups: 2, taskTitle: null })).toEqual({ title: 'المرحلة الآن: تم الإرسال', message: 'أُنشئت متابعتان بعد 3 و7 أيام.' });
    expect(sentToast({ movedStage: false, followups: 0, taskTitle: 'متابعة أولى' })).toEqual({ title: 'سُجّلت الرسالة كمرسلة', message: 'أُغلقت مهمة «متابعة أولى».' });
    expect(sentToast({ movedStage: false, followups: 0, taskTitle: null })).toEqual({ title: 'سُجّلت الرسالة كمرسلة' });
  });
});
