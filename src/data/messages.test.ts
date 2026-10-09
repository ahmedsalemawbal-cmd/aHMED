import { describe, expect, it } from 'vitest';
import { kindForTask, localDrafts, messageTaskId, parseSentResult, resolveKind, weaknessPhrases, type MessageContext } from './messages';

const checklist = {
  general: [
    { id: 'g2', label: 'عدد مراجعات كافٍ', weight: 5, group: 'reviews', weakness: 'قلة المراجعات' },
    { id: 'g3', label: 'يرد على المراجعات', weight: 10, group: 'reviews', weakness: 'لا يرد على مراجعات قوقل' },
    { id: 's3', label: 'ينشر فيديو أو ريلز', weight: 10, group: 'social' },
  ],
  specific: [],
};

function ctx(over: Partial<MessageContext> = {}): MessageContext {
  return {
    lead: {
      id: 'l1',
      businessName: 'مطعم ريدان',
      contactName: 'خالد',
      contactRole: 'owner',
      phone: '966551234567',
      stage: 'visited',
      waConsent: true,
      doNotContact: false,
      bestTime: null,
      activityTypeId: 'a-rest',
    },
    kind: 'first',
    task: null,
    draft: null,
    defaultTone: 'friendly',
    senderName: 'أحمد',
    observation: 'أطباقهم ممتازة وحسابهم بدون فيديو.',
    weaknesses: [],
    templates: [],
    ...over,
  };
}

describe('message data helpers', () => {
  it('task kind → message kind', () => {
    expect(kindForTask('first_message')).toBe('first');
    expect(kindForTask('followup_3')).toBe('followup_3');
    expect(kindForTask('followup_7')).toBe('followup_7');
    expect(kindForTask('quote_followup')).toBe('custom');
    expect(kindForTask('retry')).toBe('custom');
  });

  it('kind: ?kind= wins, then the task, then the stage', () => {
    expect(resolveKind('followup_7', 'followup_3', 'contacted')).toBe('followup_7');
    expect(resolveKind('nonsense', 'followup_3', 'contacted')).toBe('followup_3');
    expect(resolveKind(null, null, 'visited')).toBe('first');
    expect(resolveKind(null, null, 'not_visited')).toBe('first');
    expect(resolveKind(null, null, 'replied')).toBe('custom');
  });

  it('a first message never belongs to a task; follow-ups do', () => {
    expect(messageTaskId('first', { id: 't1' })).toBeNull();
    expect(messageTaskId('followup_3', { id: 't1' })).toBe('t1');
    expect(messageTaskId('custom', null)).toBeNull();
  });

  it('weakness ids become phrases, two at most, label when no phrase', () => {
    expect(weaknessPhrases(['s3', 'g3', 'g2'], checklist)).toEqual(['ينشر فيديو أو ريلز', 'لا يرد على مراجعات قوقل']);
    expect(weaknessPhrases(['g2'], checklist)).toEqual(['قلة المراجعات']);
    expect(weaknessPhrases(null, checklist)).toEqual([]);
    expect(weaknessPhrases(['x'], null)).toEqual(['x']);
  });

  it('local fallback prefers the activity template, then the general one, then the built-in', () => {
    const general = { id: 't-gen', body: 'السلام عليكم {contact_name}، معك {sender_name}.\nلاحظت {observation}.\nيناسبك؟', activityTypeId: null };
    const rest = { id: 't-rest', body: 'هلا {contact_name}، زرت {business_name}.\nلاحظت {observation}.\nفكرة ريل؟', activityTypeId: 'a-rest' };
    const a = localDrafts(ctx({ templates: [general, rest] }));
    expect(a.templateId).toBe('t-rest');
    expect(a.drafts.friendly).toBe('هلا خالد، زرت مطعم ريدان.\nلاحظت أطباقهم ممتازة وحسابهم بدون فيديو.\nفكرة ريل؟');
    expect(localDrafts(ctx({ templates: [general] })).templateId).toBe('t-gen');
    const builtIn = localDrafts(ctx());
    expect(builtIn.templateId).toBeNull();
    expect(builtIn.drafts.friendly.startsWith('السلام عليكم خالد، معك أحمد، زرتكم اليوم في مطعم ريدان.')).toBe(true);
    // the short tone keeps three lines
    expect(builtIn.drafts.short.split('\n')).toHaveLength(3);
  });

  it('parses the confirm RPC answer and rejects anything else', () => {
    expect(
      parseSentResult({
        message_id: 'm1',
        lead_id: 'l1',
        already_sent: false,
        stage_before: 'visited',
        created_task_ids: ['t1', 't2'],
        closed_task_ids: ['t0'],
        last_contact_before: null,
      }),
    ).toEqual({ messageId: 'm1', leadId: 'l1', alreadySent: false, stageBefore: 'visited', createdTaskIds: ['t1', 't2'], closedTaskIds: ['t0'], lastContactBefore: null });
    expect(parseSentResult({ message_id: 'm1', lead_id: 'l1', already_sent: true })).toMatchObject({ alreadySent: true, stageBefore: null, createdTaskIds: [] });
    expect(() => parseSentResult(null)).toThrow();
    expect(() => parseSentResult({ lead_id: 'l1' })).toThrow();
  });
});
