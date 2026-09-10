/**
 * Osoul Mail — مساعد كتابة الرسائل.
 *
 * يعمل من العملية الرئيسية لا من الواجهة: مفتاح OpenAI سرّ لا يجوز أن يصل
 * إلى صفحة تعرض محتوى رسائل قادمة من الخارج، وسياسة أمن المحتوى في الواجهة
 * تمنع أي اتصال شبكي منها أصلًا.
 *
 * وضعان فقط، كما في نسخة الويب: كتابة رسالة من فكرة قصيرة، وتحسين صياغة نص
 * موجود. النموذج يُطلب منه إرجاع الموضوع في أول سطر ثم النص، فنفصلهما.
 */

'use strict';

const API_URL = 'https://api.openai.com/v1/chat/completions';
const DEFAULT_MODEL = 'gpt-4o-mini';
const TIMEOUT_MS = 45000;

/** تعليمات النظام لكل وضع. */
function systemPrompt(mode, lang) {
  const arabic = lang !== 'en';
  const tongue = arabic
    ? 'اكتب بالعربية الفصحى المهنية ما لم يكن نص المستخدم بالإنجليزية فحينها اكتب بالإنجليزية.'
    : 'Write in professional English unless the user text is Arabic, in which case write in Arabic.';

  const shared = [
    'أنت مساعد كتابة بريد إلكتروني لموظفي شركة أصول البناء الصناعية (مقاولات وصناعة في السعودية).',
    tongue,
    'اجعل الرسالة موجزة ومباشرة ومهذبة، بلا مبالغة ولا حشو.',
    'لا تخترع أرقامًا ولا أسعارًا ولا تواريخ ولا أسماء لم يذكرها المستخدم.',
    'أعد الناتج هكذا: السطر الأول "الموضوع: ..." ثم سطر فارغ ثم نص الرسالة.',
    'لا تضع أي شرح أو تعليق خارج الرسالة نفسها.',
  ];

  if (mode === 'improve') {
    shared.push('حسّن صياغة النص المعطى مع الحفاظ على معناه ومعلوماته كما هي تمامًا.');
  } else {
    shared.push('حوّل فكرة المستخدم المختصرة إلى رسالة كاملة جاهزة للإرسال.');
  }
  return shared.join(' ');
}

/**
 * فصل الموضوع عن نص الرسالة.
 * النموذج قد يستعمل "الموضوع:" أو "Subject:" أو يهملهما، فنتعامل مع الحالات.
 */
function splitSubject(content) {
  const text = String(content || '').trim();
  const m = /^(?:الموضوع|subject)\s*[:：]\s*(.+?)(?:\r?\n|$)/i.exec(text);
  if (!m) return { subject: '', body: text };
  return {
    subject: m[1].trim(),
    body: text.slice(m[0].length).replace(/^\s*\n/, '').trim(),
  };
}

/** نص عادي إلى HTML بسيط يحافظ على الفقرات. */
function toHTML(text) {
  return String(text || '')
    .split(/\n{2,}/)
    .map((para) => `<div>${para.split('\n').map(escapeHTML).join('<br>')}</div>`)
    .join('<div><br></div>');
}

function escapeHTML(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function fail(code, ar, en) {
  const err = new Error(code);
  err.osoul = { code, ar, en };
  return err;
}

/**
 * توليد رسالة أو تحسينها.
 *
 * @param {{key:string, model?:string}} config
 * @param {{mode:'draft'|'improve', text:string, subject?:string, lang?:string}} input
 * @returns {Promise<{subject:string, html:string, text:string}>}
 */
async function generate(config, input) {
  const key = String((config && config.key) || '').trim();
  if (!key) {
    throw fail('AI_NO_KEY',
      'مساعد الذكاء الاصطناعي غير مفعّل. أضف مفتاح OpenAI من الإعدادات.',
      'The AI assistant is not set up. Add an OpenAI key in Settings.');
  }

  const mode = input.mode === 'improve' ? 'improve' : 'draft';
  const text = String(input.text || '').trim();
  if (!text) {
    throw mode === 'improve'
      ? fail('AI_NO_TEXT', 'اكتب نص الرسالة أولًا حتى أحسّنه.', 'Write the message first so I can improve it.')
      : fail('AI_NO_IDEA', 'اكتب فكرة أو طلبًا قصيرًا أولًا.', 'Jot down a short idea or request first.');
  }

  const user = input.subject
    ? `الموضوع الحالي: ${input.subject}\n\n${text}`
    : text;

  let res;
  try {
    res = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${key}`,
      },
      body: JSON.stringify({
        model: (config && config.model) || DEFAULT_MODEL,
        temperature: mode === 'improve' ? 0.3 : 0.6,
        max_tokens: 900,
        messages: [
          { role: 'system', content: systemPrompt(mode, input.lang) },
          { role: 'user', content: user },
        ],
      }),
      signal: AbortSignal.timeout(TIMEOUT_MS),
    });
  } catch (_) {
    throw fail('AI_NETWORK',
      'تعذّر الوصول إلى مساعد الذكاء الاصطناعي. تحقّق من اتصال الإنترنت.',
      'Could not reach the AI assistant. Check your connection.');
  }

  if (res.status === 401) {
    throw fail('AI_BAD_KEY',
      'مفتاح OpenAI غير صالح. راجعه في الإعدادات.',
      'The OpenAI key is not valid. Check it in Settings.');
  }
  if (res.status === 429) {
    throw fail('AI_BUSY',
      'مساعد الذكاء الاصطناعي مشغول أو تجاوز الحصة. حاول بعد قليل.',
      'The AI assistant is rate limited or out of quota. Try again shortly.');
  }
  if (!res.ok) {
    throw fail('AI_FAILED',
      'تعذّر توليد الرسالة. حاول مرة أخرى.',
      'Could not generate the message. Please try again.');
  }

  let data;
  try {
    data = await res.json();
  } catch (_) {
    throw fail('AI_FAILED', 'رد غير مفهوم من الخدمة.', 'Unreadable response from the service.');
  }

  const content = data && data.choices && data.choices[0] && data.choices[0].message
    ? data.choices[0].message.content
    : '';
  if (!String(content || '').trim()) {
    throw fail('AI_EMPTY', 'لم تُرجع الخدمة نصًا.', 'The service returned nothing.');
  }

  const { subject, body } = splitSubject(content);
  return { subject, text: body, html: toHTML(body) };
}

/** إخفاء المفتاح للعرض: نُظهر أوله وآخره فقط. */
function mask(key) {
  const k = String(key || '');
  if (!k) return '';
  if (k.length <= 10) return '•'.repeat(k.length);
  return `${k.slice(0, 5)}${'•'.repeat(12)}${k.slice(-4)}`;
}

module.exports = { generate, mask, splitSubject, toHTML, DEFAULT_MODEL };
