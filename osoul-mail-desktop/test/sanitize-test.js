/* تنظيف أجسام الرسائل.
 *
 * المستخدم رأى قواعد CSS مكتوبة كنصّ أعلى كل رسالة في سلسلة ردود كاملة.
 * هذه الفحوص تحرس الحدّين: أن تُحذف الأنماط المسرَّبة، وألّا يُمسّ نصّ عادي
 * يصادف أن فيه أقواسًا معقوفة.
 */

'use strict';

const { sanitizeHTML, looksLikeStylesheet } = require('../src/main/sanitize');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d).slice(0, 220)}`; failures++; }
};

const LEAK = "html,body{margin:0;padding:16px 18px;background:#ffffff; color:#22333c;"
  + "font-family:Cairo,'Segoe UI',Tahoma,sans-serif; font-size:14px;line-height:1.8;"
  + "word-wrap:break-word;overflow-wrap:break-word;} img{max-width:100%!important;height:auto;} "
  + "table{max-width:100%!important;} a{color:#0d6f9f;} "
  + "blockquote{margin:12px 0;padding-inline-start:12px; border-inline-start:2px solid #d2dade; "
  + "color:#5a707c;} pre,.om-plain{white-space:pre-wrap;font-family:inherit;margin:0;} "
  + "::selection{background:rgba(22,131,189,.35);} *{max-width:100%;}";

/* --- الأنماط المسرَّبة تُحذف والنصّ يبقى --- */
const leaked = sanitizeHTML(`<div>${LEAK}</div><div>السلام عليكم، هذا نص الرسالة.</div>`, false);
check('leak.removed', !/font-family:Cairo|max-width:100%/.test(leaked.html), leaked.html.slice(0, 120));
check('leak.keepsMessage', leaked.html.includes('السلام عليكم، هذا نص الرسالة.'), leaked.html);

// نفس التسريب داخل اقتباس ردّ — وهو موضعه المعتاد.
const quoted = sanitizeHTML(`<blockquote><div>${LEAK}</div><p>نص مقتبس</p></blockquote>`, false);
check('leak.removedInsideQuote', !quoted.html.includes('font-family:Cairo'), quoted.html.slice(0, 120));
check('leak.keepsQuotedText', quoted.html.includes('نص مقتبس'), quoted.html);

/* --- نصّ عادي لا يُمسّ --- */
const braces = sanitizeHTML('<div>القيم {a} و {b} و {c} مذكورة في الجدول</div>', false);
check('normal.bracesKept', braces.html.includes('{a}') && braces.html.includes('{c}'), braces.html);

const code = sanitizeHTML('<div>if (x) { return 1; } else { return 2; } // ملاحظة</div>', false);
check('normal.codeSnippetKept', code.html.includes('return 1'), code.html);

check('detector.needsThreeRules', !looksLikeStylesheet('a{margin:0} b{padding:0}'));
check('detector.needsCssWords', !looksLikeStylesheet('{one} {two} {three} {four}'));
check('detector.catchesRealSheet', looksLikeStylesheet(LEAK));

/* --- ما كان يجب أن يبقى يبقى --- */
const realStyle = sanitizeHTML('<style>p{color:red}</style><p>مرحبا</p>', false);
check('style.elementKept', /<style>/.test(realStyle.html), realStyle.html);
check('style.textKept', realStyle.html.includes('مرحبا'), realStyle.html);

const script = sanitizeHTML('<script>alert(1)</script><p>نص</p>', false);
check('script.removed', !/alert/.test(script.html), script.html);

const onclick = sanitizeHTML('<p onclick="bad()">نص</p>', false);
check('handler.removed', !/onclick/i.test(onclick.html), onclick.html);

const js = sanitizeHTML('<a href="javascript:bad()">رابط</a>', false);
check('protocol.blocked', !/javascript:/i.test(js.html), js.html);

console.log(JSON.stringify(out, null, 1));
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
