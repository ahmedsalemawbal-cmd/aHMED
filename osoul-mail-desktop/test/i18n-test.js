/* فحص الترجمة: لا نص ظاهر للمستخدم مكتوب مباشرة في كود الواجهة.
 *
 * الشركة فيها موظفون عرب وأجانب، ونص عربي واحد منسيّ داخل الكود يعني موظفًا
 * أجنبيًا يرى كلمة لا يفهمها في واجهة إنجليزية. هذا الفحص يمسك ذلك قبل البناء،
 * ويتحقق كذلك من تطابق مفاتيح القاموسين حتى لا يظهر مفتاح خام مكان النص.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d).slice(0, 600)}`; failures++; }
};

const RENDERER = path.join(__dirname, '..', 'src', 'renderer', 'js');
const ARABIC = /[؀-ۿ]/;

/**
 * تجريد التعليقات سطرًا سطرًا.
 *
 * الغرض هو النصوص الظاهرة للمستخدم، وشرح الكود بالعربية مقصود. نتعامل مع
 * الأسطر لا مع الكود كاملًا: تمييز نص من تعبير نمطي يحتاج مُحلّلًا كاملًا،
 * وهذا القدر يكفي لأسلوب هذا المشروع.
 */
function stripLineComments(lines) {
  const out = [];
  let inBlock = false;

  for (let line of lines) {
    if (inBlock) {
      const close = line.indexOf('*/');
      if (close === -1) { out.push(''); continue; }
      line = line.slice(close + 2);
      inBlock = false;
    }

    // تعليقات /* … */ داخل السطر نفسه
    for (;;) {
      const open = line.indexOf('/*');
      if (open === -1) break;
      const close = line.indexOf('*/', open + 2);
      if (close === -1) { line = line.slice(0, open); inBlock = true; break; }
      line = line.slice(0, open) + line.slice(close + 2);
    }

    // تعليق // في آخر السطر — نتجاهل // داخل رابط مثل https://
    const at = line.indexOf('//');
    if (at !== -1 && line[at - 1] !== ':') line = line.slice(0, at);

    out.push(line);
  }
  return out;
}

/* --- 1) لا نص عربي مكتوب مباشرة في ملفات الواجهة (عدا القاموس) --- */
const files = fs.readdirSync(RENDERER).filter((f) => f.endsWith('.js') && f !== 'i18n.js');
const offenders = [];

for (const file of files) {
  const raw = fs.readFileSync(path.join(RENDERER, file), 'utf8').split('\n');
  stripLineComments(raw).forEach((line, i) => {
    if (!ARABIC.test(line)) return;
    offenders.push(`${file}:${i + 1}  ${raw[i].trim().slice(0, 90)}`);
  });
}
check('noHardcodedArabic', offenders.length === 0, offenders.slice(0, 12));

/* --- 2) القاموسان متطابقان في المفاتيح --- */
const dictSrc = fs.readFileSync(path.join(RENDERER, 'i18n.js'), 'utf8');
const keysOf = (langTag) => {
  const start = dictSrc.indexOf(`  ${langTag}: {`);
  if (start === -1) return [];
  let depth = 0;
  let i = dictSrc.indexOf('{', start);
  const from = i;
  for (; i < dictSrc.length; i++) {
    if (dictSrc[i] === '{') depth++;
    else if (dictSrc[i] === '}') { depth--; if (depth === 0) break; }
  }
  const body = dictSrc.slice(from, i);
  return [...body.matchAll(/^\s{4}([A-Za-z][A-Za-z0-9]*):/gm)].map((m) => m[1]);
};

const ar = keysOf('ar');
const en = keysOf('en');
check('dictionariesFound', ar.length > 50 && en.length > 50, { ar: ar.length, en: en.length });
check('noMissingEnglish', ar.every((k) => en.includes(k)), ar.filter((k) => !en.includes(k)));
check('noExtraEnglish', en.every((k) => ar.includes(k)), en.filter((k) => !ar.includes(k)));
check('noDuplicateKeys', new Set(ar).size === ar.length && new Set(en).size === en.length);

/* --- 3) المتغيّرات داخل النص متطابقة بين اللغتين --- */
const varsOf = (langTag, key) => {
  const re = new RegExp(`^\\s{4}${key}: (['\`])((?:[^\\\\]|\\\\.)*?)\\1`, 'm');
  const start = dictSrc.indexOf(`  ${langTag}: {`);
  const body = dictSrc.slice(start);
  const m = re.exec(body);
  return m ? [...m[2].matchAll(/\{(\w+)\}/g)].map((x) => x[1]).sort() : null;
};
const varMismatch = [];
for (const k of ar) {
  const a = varsOf('ar', k);
  const e = varsOf('en', k);
  if (a && e && a.join(',') !== e.join(',')) varMismatch.push(`${k}: ar[${a}] en[${e}]`);
}
check('placeholdersMatch', varMismatch.length === 0, varMismatch);

console.log(JSON.stringify(out, null, 1));
console.log(`\nملفات مفحوصة: ${files.length} · مفاتيح: ${ar.length}`);
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
