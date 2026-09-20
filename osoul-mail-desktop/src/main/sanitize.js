/**
 * Osoul Mail — تنظيف محتوى الرسائل قبل عرضها.
 *
 * رسالة البريد محتوى غير موثوق: قد تحتوي على سكربتات أو صور تتبّع أو روابط
 * خبيثة. ننظّفها هنا في العملية الرئيسية قبل أن تصل إلى الواجهة، ثم تُعرض
 * داخل إطار معزول (sandbox) بلا سكربتات — طبقتا حماية لا واحدة.
 */

'use strict';

/** وسوم تُحذف مع محتواها بالكامل. */
const KILL_TAGS = ['script', 'iframe', 'object', 'embed', 'applet', 'form', 'noscript', 'frameset', 'frame'];
/** وسوم فردية تُحذف (بلا محتوى). */
const DROP_VOID = ['link', 'meta', 'base'];

/** بروتوكولات ممنوعة داخل الروابط. */
const BAD_PROTO = /^\s*(javascript|vbscript|data|file|about)\s*:/i;

function stripBlock(html, tag) {
  const re = new RegExp(`<${tag}\\b[\\s\\S]*?<\\/${tag}\\s*>`, 'gi');
  let out = html;
  let prev;
  do {
    prev = out;
    out = out.replace(re, '');
  } while (out !== prev);
  // وسم مفتوح بلا إغلاق (رسالة مشوّهة عمدًا)
  return out.replace(new RegExp(`<${tag}\\b[\\s\\S]*$`, 'gi'), '');
}

/** تنظيف كتلة CSS داخل <style> أو خاصية style. */
function cleanCSS(css) {
  return String(css)
    .replace(/@import[^;]*;?/gi, '')
    .replace(/expression\s*\(/gi, 'x(')
    .replace(/behavior\s*:/gi, 'x:')
    .replace(/-moz-binding\s*:/gi, 'x:')
    .replace(/(javascript|vbscript)\s*:/gi, 'x:');
}


/* ------------------------------------------------------ ورقة أنماط مسرَّبة
 *
 * بعض العملاء يحوّلون كتلة <style> إلى نصّ عادي حين يُقتبس في ردّ، فتُرسل
 * قواعد CSS كما هي ويراها المستلم سطورًا من كود أعلى الرسالة. وما إن تدخل
 * سلسلة ردود حتى تتكرّر في كل ردّ بعدها.
 *
 * نحذفها عند العرض. الشرط ضيّق عمدًا — ثلاث قواعد فأكثر داخل نصّ واحد، مع
 * خاصيّتين معروفتين على الأقل — فنصٌّ عادي لا يجتمع فيه هذا.
 */
const CSS_RULE = /[^{}<>]{1,160}\{[^{}<>]{0,900}\}/g;
const CSS_HINTS = [
  'font-family', 'line-height', 'max-width', 'white-space', 'background',
  'margin', 'padding', 'border', 'overflow-wrap', 'word-wrap',
];

function looksLikeStylesheet(text) {
  const rules = text.match(CSS_RULE);
  if (!rules || rules.length < 3) return false;
  const hits = CSS_HINTS.filter((h) => text.includes(h)).length;
  return hits >= 2;
}

/**
 * حذف قواعد CSS التي تسرّبت إلى نصّ الرسالة.
 *
 * محتوى <style> الحقيقي يُستثنى: هو قواعد بطبيعته، وتفريغه يُفقد الرسالة
 * تنسيقها كاملًا. نحمي الكتلة أولًا ثم نعيدها بعد التنظيف.
 */
function dropLeakedCSS(html) {
  const kept = [];
  const guarded = String(html || '').replace(
    /<style\b[^>]*>[\s\S]*?<\/style\s*>/gi,
    (block) => `\u0000STYLE${kept.push(block) - 1}\u0000`,
  );

  const cleaned = guarded.replace(/>([^<]+)</g, (match, text) => {
    if (!looksLikeStylesheet(text)) return match;
    const stripped = text.replace(CSS_RULE, ' ').replace(/\s{2,}/g, ' ').trim();
    return `>${stripped}<`;
  });

  return cleaned.replace(/\u0000STYLE(\d+)\u0000/g, (_m, i) => kept[Number(i)]);
}

/**
 * تنظيف رسالة HTML.
 *
 * @param {string} html   جسم الرسالة الأصلي
 * @param {boolean} allowRemote  السماح بتحميل الصور الخارجية
 * @returns {{html:string, blockedImages:number}}
 */
function sanitizeHTML(html, allowRemote) {
  let out = String(html || '');
  let blocked = 0;

  for (const tag of KILL_TAGS) out = stripBlock(out, tag);
  for (const tag of DROP_VOID) out = out.replace(new RegExp(`<${tag}\\b[^>]*>`, 'gi'), '');

  // تعليقات HTML الشرطية تُستعمل أحيانًا لإخفاء وسوم — نزيلها.
  out = out.replace(/<!--[\s\S]*?-->/g, '');

  // قواعد CSS تسرّبت إلى النصّ في سلسلة ردود سابقة.
  out = dropLeakedCSS(out);

  // تنظيف محتوى <style> المتبقي.
  out = out.replace(/<style\b([^>]*)>([\s\S]*?)<\/style\s*>/gi, (m, attrs, css) => `<style>${cleanCSS(css)}</style>`);

  // المرور على كل وسم لتنقية خصائصه.
  out = out.replace(/<([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|'[^']*'|[^>"'])*)>/g, (match, tagName, attrs) => {
    const tag = tagName.toLowerCase();
    if (!attrs) return `<${tag}>`;

    const kept = [];
    const attrRe = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*("[^"]*"|'[^']*'|[^\s"'>]+)|([a-zA-Z_:][-a-zA-Z0-9_:.]*)/g;
    let m;
    while ((m = attrRe.exec(attrs)) !== null) {
      const rawName = (m[1] || m[3] || '').toLowerCase();
      if (!rawName) continue;
      let value = m[2] === undefined ? '' : m[2].replace(/^["']|["']$/g, '');

      // معالِجات الأحداث ممنوعة دائمًا.
      if (rawName.startsWith('on')) continue;
      if (rawName === 'srcdoc' || rawName === 'formaction' || rawName === 'xlink:href') continue;

      if (rawName === 'style') {
        value = cleanCSS(value);
      } else if (rawName === 'href') {
        if (BAD_PROTO.test(value) && !/^\s*mailto:/i.test(value)) continue;
        kept.push('target="_blank"', 'rel="noopener noreferrer nofollow"');
      } else if (rawName === 'src') {
        const isInlineImage = /^\s*(cid:|data:image\/)/i.test(value);
        if (!isInlineImage) {
          if (BAD_PROTO.test(value)) continue;
          if (!allowRemote && tag === 'img') {
            blocked++;
            kept.push(`data-osoul-blocked="${escapeAttr(value)}"`);
            continue; // نُسقط src فيبقى مكان الصورة فارغًا حتى يوافق المستخدم
          }
        }
      }

      kept.push(`${rawName}="${escapeAttr(value)}"`);
    }
    return `<${tag}${kept.length ? ' ' + kept.join(' ') : ''}>`;
  });

  return { html: out, blockedImages: blocked };
}

function escapeAttr(s) {
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeHTML(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** تحويل رسالة نصية بسيطة إلى HTML آمن مع تفعيل الروابط. */
function textToHTML(text) {
  const esc = escapeHTML(text);
  const linked = esc.replace(/\b(https?:\/\/[^\s<]+)/g, (url) => {
    const clean = url.replace(/[.,;:)]+$/, '');
    const tail = url.slice(clean.length);
    return `<a href="${escapeAttr(clean)}" target="_blank" rel="noopener noreferrer nofollow">${clean}</a>${tail}`;
  });
  return `<div class="om-plain">${linked}</div>`;
}

/** نص مختصر للمعاينة من HTML. */
function htmlToSnippet(html, max) {
  const text = String(html || '')
    .replace(/<(script|style)[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\s+/g, ' ')
    .trim();
  const limit = max || 160;
  return text.length > limit ? text.slice(0, limit) + '…' : text;
}

module.exports = { sanitizeHTML, dropLeakedCSS, looksLikeStylesheet, textToHTML, htmlToSnippet, escapeHTML };
