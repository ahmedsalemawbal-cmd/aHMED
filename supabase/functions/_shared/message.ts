/**
 * Rules for the WhatsApp drafts (brief.md «رسائل الواتساب والذكاء الاصطناعي»).
 * Pure code shared by the Edge Function (Deno) and the app/tests (Node): no I/O.
 */
export type MessageKind = 'first' | 'followup_3' | 'followup_7' | 'custom';
export type Tone = 'friendly' | 'formal' | 'short';
export const TONES: Tone[] = ['friendly', 'formal', 'short'];

export interface MessageFacts {
  senderName: string;
  signature: string | null;
  businessName: string;
  activity: string;
  contactName: string;
  contactRole: 'owner' | 'manager' | 'employee' | null;
  /** «الملاحظة الأبرز» from the visit */
  observation: string;
  /** the two most important weaknesses, as phrases */
  weaknesses: string[];
  /** one or two suggested services */
  services: string[];
  /** template chosen for this lead/kind, if any */
  templateBody: string | null;
}

const ROLE_AR = { owner: 'مالك المحل', manager: 'مدير المحل', employee: 'موظف في المحل' } as const;
const TONE_AR: Record<Tone, string> = {
  friendly: 'ودّية ودافئة',
  formal: 'رسمية ومهذبة',
  short: 'مختصرة جداً: ثلاثة أسطر قصيرة',
};

export const LINE_LIMITS: Record<MessageKind, [number, number]> = {
  first: [3, 5],
  followup_3: [2, 4],
  followup_7: [2, 3],
  custom: [1, 6],
};

/** System prompt: the fixed rules (kept byte-stable so it can be cached). */
export const SYSTEM_PROMPT = `أنت تكتب مسودات رسائل واتساب لمسوّق محلي في حي الحمدانية يقدم التسويق وصناعة المحتوى وتصوير الفيديو للمحلات.
المسوّق يراجع المسودة ويرسلها بنفسه، فاكتب نصاً جاهزاً للإرسال فقط.

قواعد ثابتة لكل رسالة:
- لهجة سعودية بيضاء ومحترمة.
- بدون أسعار أو أرقام مالية، وبدون وعود بأرقام أو نتائج مضمونة.
- بدون إيموجي، وبدون روابط، وبدون علامات تعجب متتالية.
- كل سطر جملة قصيرة، والأسطر مفصولة بسطر جديد.

الرسالة الأولى (first):
- من 3 إلى 5 أسطر قصيرة.
- تبدأ بالسلام واسم المسؤول، ثم تعريف بالمرسل في سطر واحد.
- ملاحظة واحدة محددة من الزيارة، ليست مدحاً عاماً.
- فائدة واحدة واضحة مرتبطة بالملاحظة.
- تنتهي بسؤال واحد سهل الإجابة.

المتابعة الأولى (followup_3): من سطرين إلى 4، تقدم قيمة جديدة (عينة ريل أو فكرة محتوى) مرتبطة بالملاحظة، وتنتهي بسؤال واحد.
المتابعة الثانية (followup_7): من سطرين إلى 3، تذكير قصير وأخير بدون ضغط، يترك الباب مفتوحاً.

أعد ثلاث صيغ للرسالة نفسها: ودّية (friendly)، رسمية (formal)، مختصرة (short).`;

/** The per-lead request: facts only, no instructions that change between leads. */
export function buildUserPrompt(f: MessageFacts, kind: MessageKind): string {
  const lines = [
    `نوع الرسالة: ${kind}`,
    `المرسل: ${f.senderName || 'المسوّق'}${f.signature ? ` (التوقيع: ${f.signature})` : ''}`,
    `المحل: ${f.businessName} (${f.activity || 'نشاط تجاري'})`,
    `المسؤول: ${f.contactName || 'غير معروف'}${f.contactRole ? `، ${ROLE_AR[f.contactRole]}` : ''}`,
    `الملاحظة الأبرز من الزيارة: ${f.observation || 'لا توجد'}`,
    `أهم نقاط الضعف: ${f.weaknesses.slice(0, 2).join('، ') || 'لا توجد'}`,
    `الخدمات المقترحة: ${f.services.slice(0, 2).join('، ') || 'لا توجد'}`,
  ];
  if (f.templateBody) lines.push(`قالب يفضله المسوّق (استلهم منه ولا تنسخه حرفياً):\n${f.templateBody}`);
  lines.push(`النبرات المطلوبة: ${TONES.map((t) => `${t} = ${TONE_AR[t]}`).join('، ')}`);
  return lines.join('\n');
}

/** JSON schema for structured output: one draft per tone. */
export const DRAFTS_SCHEMA = {
  type: 'object',
  properties: {
    friendly: { type: 'string' },
    formal: { type: 'string' },
    short: { type: 'string' },
  },
  required: ['friendly', 'formal', 'short'],
  additionalProperties: false,
} as const;

export type Drafts = Record<Tone, string>;

export function lineCount(text: string): number {
  return text.split('\n').filter((l) => l.trim()).length;
}

const PRICE = /(\d[\d,٬.]*\s*(ر\.?\s?س|ريال|SAR|﷼))|ريال|﷼|\bSAR\b/i;
const EMOJI = /\p{Extended_Pictographic}/u;
const URL_RE = /https?:\/\/|www\./i;

/** Checks a draft against the rules; problems are in Arabic for the logs/UI. */
export function validateDraft(text: string, kind: MessageKind, tone: Tone): string[] {
  const problems: string[] = [];
  const n = lineCount(text);
  const [min, max] = LINE_LIMITS[kind];
  const effMin = tone === 'short' ? Math.min(min, 2) : min;
  if (!text.trim()) problems.push('الرسالة فارغة');
  else if (n < effMin || n > max) problems.push(`عدد الأسطر ${n.toString()} والمطلوب ${effMin.toString()}–${max.toString()}`);
  if (PRICE.test(text)) problems.push('فيها سعر');
  if (EMOJI.test(text)) problems.push('فيها إيموجي');
  if (URL_RE.test(text)) problems.push('فيها رابط');
  if ((kind === 'first' || kind === 'followup_3') && !/[؟?]\s*$/.test(text.trim())) problems.push('لا تنتهي بسؤال');
  return problems;
}

export function parseDrafts(raw: unknown): Drafts | null {
  if (!raw || typeof raw !== 'object') return null;
  const r = raw as Record<string, unknown>;
  if (typeof r.friendly !== 'string' || typeof r.formal !== 'string' || typeof r.short !== 'string') return null;
  const clean = (s: string) => s.replace(/\r\n/g, '\n').replace(/\n{2,}/g, '\n').trim();
  return { friendly: clean(r.friendly), formal: clean(r.formal), short: clean(r.short) };
}

/** Default fallback bodies when no template row exists (same wording as bootstrap_owner). */
export const DEFAULT_TEMPLATES: Record<Exclude<MessageKind, 'custom'>, string> = {
  first: 'السلام عليكم {contact_name}، معك {sender_name}، زرتكم اليوم في {business_name}.\nلاحظت {observation}.\nعندي فكرة بسيطة تخدمكم في هذي النقطة بالذات.\nيناسبك أرسل لك عينة مجانية؟',
  followup_3: 'هلا {contact_name}، معك {sender_name} من زيارة {business_name}.\nجهزت لك فكرة محتوى قصيرة مبنية على اللي لاحظته في الزيارة.\nيناسبك أرسلها لك هنا؟',
  followup_7: '{contact_name}، ما أبي أثقل عليك.\nلو حاب نكمل الكلام عن {business_name} أنا موجود في أي وقت.\nيناسبك نتواصل الأسبوع الجاي؟',
};

/** Fills {contact_name} {business_name} {observation} {sender_name}; empty values read naturally. */
export function fillTemplate(body: string, f: Pick<MessageFacts, 'contactName' | 'businessName' | 'observation' | 'senderName'>): string {
  const observation = f.observation.trim().replace(/[.。]+$/, '');
  return body
    .replace(/\{contact_name\}/g, f.contactName.trim() || 'أهلاً')
    .replace(/\{business_name\}/g, f.businessName.trim())
    .replace(/\{observation\}/g, observation || 'أشياء جميلة في المحل')
    .replace(/\{sender_name\}/g, f.senderName.trim() || 'المسوّق')
    .replace(/السلام عليكم أهلاً،/, 'السلام عليكم،')
    .replace(/^أهلاً، /, '');
}

/** Template drafts for all tones: the short tone keeps the first, observation and last lines. */
export function templateDrafts(body: string, f: MessageFacts): Drafts {
  const full = fillTemplate(body, f);
  const lines = full.split('\n').filter((l) => l.trim());
  const short = lines.length > 3 ? [lines[0], lines[1], lines[lines.length - 1]].join('\n') : full;
  return { friendly: full, formal: full, short };
}
