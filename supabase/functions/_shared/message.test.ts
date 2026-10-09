import { describe, expect, it } from 'vitest';
import { buildUserPrompt, DEFAULT_TEMPLATES, fillTemplate, lineCount, parseDrafts, SYSTEM_PROMPT, templateDrafts, validateDraft, type MessageFacts } from './message';

const FACTS: MessageFacts = {
  senderName: 'أحمد',
  signature: null,
  businessName: 'مطعم ريدان',
  activity: 'مطعم',
  contactName: 'خالد',
  contactRole: 'owner',
  observation: 'أن أطباقكم شكلها ممتاز لكن حسابكم في إنستقرام بدون أي فيديو.',
  weaknesses: ['لا ينشر فيديو أو ريلز', 'لا يرد على مراجعات قوقل', 'ثالثة لا تدخل'],
  services: ['تصوير وإنتاج فيديو وريلز', 'إدارة ملف خرائط قوقل', 'ثالثة'],
  templateBody: null,
};

const EXAMPLE =
  'السلام عليكم أستاذ خالد، معك أحمد، زرتكم اليوم في مطعم ريدان.\nلاحظت أن أطباقكم شكلها ممتاز، لكن حسابكم في إنستقرام بدون أي فيديو لها.\nعندي فكرة ريل قصير كل أسبوع يوصل المطعم لسكان الحي.\nيناسبك أرسل لك عينة مجانية من تصويري اليوم؟';

describe('prompt', () => {
  it('system prompt carries the first-message rules and never changes per lead', () => {
    for (const rule of ['من 3 إلى 5 أسطر', 'تبدأ بالسلام واسم المسؤول', 'ملاحظة واحدة محددة', 'فائدة واحدة', 'سؤال واحد', 'بدون أسعار', 'لهجة سعودية بيضاء']) {
      expect(SYSTEM_PROMPT).toContain(rule);
    }
    expect(SYSTEM_PROMPT).not.toContain('مطعم ريدان');
  });
  it('user prompt carries the facts: observation, top two weaknesses and services', () => {
    const p = buildUserPrompt(FACTS, 'first');
    expect(p).toContain('مطعم ريدان');
    expect(p).toContain('خالد، مالك المحل');
    expect(p).toContain('أطباقكم شكلها ممتاز');
    expect(p).toContain('لا ينشر فيديو أو ريلز، لا يرد على مراجعات قوقل');
    expect(p).not.toContain('ثالثة');
  });
});

describe('validateDraft', () => {
  it('the brief example passes', () => {
    expect(validateDraft(EXAMPLE, 'first', 'friendly')).toEqual([]);
  });
  it('rejects prices, emoji, links, too many lines and a missing question', () => {
    expect(validateDraft('سطر\nالباقة بـ 1500 ر.س\nيناسبك؟', 'first', 'friendly')).toContain('فيها سعر');
    expect(validateDraft('سطر\nسطر 🙂\nيناسبك؟', 'first', 'friendly')).toContain('فيها إيموجي');
    expect(validateDraft('سطر\nhttps://x.y\nيناسبك؟', 'first', 'friendly')).toContain('فيها رابط');
    expect(validateDraft('١\n٢\n٣\n٤\n٥\n٦؟', 'first', 'friendly')[0]).toMatch(/عدد الأسطر 6/);
    expect(validateDraft('سطر\nسطر\nسطر.', 'first', 'friendly')).toContain('لا تنتهي بسؤال');
  });
  it('the short tone may use two lines', () => {
    expect(validateDraft('السلام عليكم خالد، معك أحمد.\nيناسبك أرسل عينة؟', 'first', 'short')).toEqual([]);
  });
});

describe('templates (fallback when generation fails)', () => {
  it('fills the variables and reads naturally', () => {
    const t = fillTemplate(DEFAULT_TEMPLATES.first, FACTS);
    expect(t).toBe(
      'السلام عليكم خالد، معك أحمد، زرتكم اليوم في مطعم ريدان.\nلاحظت أن أطباقكم شكلها ممتاز لكن حسابكم في إنستقرام بدون أي فيديو.\nعندي فكرة بسيطة تخدمكم في هذي النقطة بالذات.\nيناسبك أرسل لك عينة مجانية؟',
    );
    expect(validateDraft(t, 'first', 'friendly')).toEqual([]);
  });
  it('without a contact name the greeting stays polite', () => {
    expect(fillTemplate(DEFAULT_TEMPLATES.first, { ...FACTS, contactName: '' }).startsWith('السلام عليكم، معك أحمد')).toBe(true);
  });
  it('every default template passes the rules for its kind', () => {
    for (const kind of ['first', 'followup_3', 'followup_7'] as const) {
      const d = templateDrafts(DEFAULT_TEMPLATES[kind], FACTS);
      expect(validateDraft(d.friendly, kind, 'friendly')).toEqual([]);
      expect(validateDraft(d.short, kind, 'short')).toEqual([]);
    }
  });
  it('short tone keeps three lines', () => {
    expect(lineCount(templateDrafts(DEFAULT_TEMPLATES.first, FACTS).short)).toBe(3);
  });
});

describe('parseDrafts', () => {
  it('accepts the three tones and tidies blank lines', () => {
    expect(parseDrafts({ friendly: 'أ\n\n\nب', formal: 'ج', short: 'د' })).toEqual({ friendly: 'أ\nب', formal: 'ج', short: 'د' });
  });
  it('rejects anything else', () => {
    expect(parseDrafts({ friendly: 'أ' })).toBeNull();
    expect(parseDrafts(null)).toBeNull();
    expect(parseDrafts('text')).toBeNull();
  });
});
