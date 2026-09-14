/**
 * Osoul Mail — الإكمال التلقائي للمستلمين.
 *
 * الموظف يكتب اسم زميله لا عنوانه. "omar" تكفي ليجد عمر حلمي، و"مبيعات"
 * تعرض قسم المبيعات كاملًا. من يحفظ عنوان كل زميل في شركة فيها ثلاثون
 * صندوقًا؟
 *
 * الحقل يبقى حقل نص عاديًا يقبل عدة مستلمين مفصولين بفواصل — لا نستبدله
 * بشرائح تمنع اللصق من ملف أو تعديل عنوان بيد. الاقتراح يعمل على المقطع
 * الذي يكتبه الآن فقط، فلا يعبث بما سبقه.
 */

import { esc, avatarHTML } from './util.js';
import { t, pick, isRTL } from './i18n.js';

const MAX_ROWS = 7;

/** المصدر الموحّد: دليل الشركة + من راسلهم فعلًا، بلا تكرار. */
function people(state) {
  const map = new Map();
  for (const c of state.contacts || []) {
    if (c && c.email) map.set(String(c.email).toLowerCase(), c);
  }
  for (const d of state.directory || []) {
    const key = String(d.email || '').toLowerCase();
    if (!key) continue;
    const found = map.get(key);
    if (found) {
      // الدفتر المبني من الصندوق لا يعرف إلا ما كتبه المرسلون: لا اسم عربي
      // ولا قسم. نكملهما من الدليل وإلا بحث الموظف بالعربية فلم يجد أحدًا.
      if (!found.dept && d.dept) found.dept = d.dept;
      if (!found.name) found.name = d.name;
      if (!found.nameAr && d.nameAr) found.nameAr = d.nameAr;
    } else {
      map.set(key, { ...d, count: 0, lastTs: 0 });
    }
  }
  return [...map.values()];
}

/** تطبيع البحث: تجاهل التشكيل، ووحّد الألف والتاء المربوطة والياء. */
function norm(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFKD')
    // أحرف عربية مكتوبة بترميزها لا بشكلها: هذه بيانات محارف لا نص واجهة،
    // وفحص الترجمة يمنع النص العربي داخل كود الواجهة عن حق.
    .replace(/[\u064B-\u0652\u0640]/g, '')       // تشكيل وتطويل
    .replace(/[\u0623\u0625\u0622\u0671]/g, '\u0627') // أ إ آ ٱ ← ا
    .replace(/\u0629/g, '\u0647')                  // ة ← ه
    .replace(/[\u0649\u064A]/g, '\u064A')          // ى ← ي
    .replace(/\p{M}/gu, '')
    .trim();
}

/**
 * ترتيب النتائج بما يخدم الكتابة: بداية الاسم أولًا لأنها ما يقصده الكاتب
 * غالبًا، ثم بداية العنوان، ثم أي تطابق داخلي. وداخل كل مرتبة: من راسلته
 * أكثر أولًا.
 */
function score(person, q) {
  // الاسم بلغتيه سواء في البحث: الموظف يكتب بالتي يفكّر بها، لا بالتي
  // كُتب بها كشف الموارد البشرية.
  const names = [norm(person.name), norm(person.nameAr)].filter(Boolean);
  const email = norm(person.email);
  const local = email.split('@')[0];
  const dept = norm(person.dept ? `${person.dept.ar} ${person.dept.en}` : '');
  const words = names.flatMap((n) => n.split(/\s+/)).filter(Boolean);

  if (names.some((n) => n.startsWith(q))) return 100;
  if (words.some((w) => w.startsWith(q))) return 90;
  if (local.startsWith(q)) return 80;
  if (dept.split(/\s+/).some((w) => w.startsWith(q))) return 70;
  if (names.some((n) => n.includes(q))) return 50;
  if (email.includes(q)) return 40;
  if (dept.includes(q)) return 30;
  return 0;
}

/** أفضل المطابقات لنص البحث، مستبعدةً من أُضيف للحقل من قبل. */
export function match(state, query, taken) {
  const q = norm(query);
  if (!q) return [];
  const skip = new Set((taken || []).map((e) => String(e).toLowerCase()));

  return people(state)
    .filter((p) => !skip.has(String(p.email).toLowerCase()))
    .map((p) => ({ p, s: score(p, q) }))
    .filter((x) => x.s > 0)
    .sort((a, b) => b.s - a.s
      || (b.p.count || 0) - (a.p.count || 0)
      || String(a.p.name).localeCompare(String(b.p.name)))
    .slice(0, MAX_ROWS)
    .map((x) => x.p);
}

/** المقطع الجاري كتابته: ما بعد آخر فاصلة. */
function currentPart(value, caret) {
  const upto = value.slice(0, caret);
  const start = Math.max(upto.lastIndexOf(','), upto.lastIndexOf(';')) + 1;
  return { start, text: value.slice(start, caret) };
}

/** العناوين المكتوبة في الحقل أصلًا — لا نقترح مستلمًا مضافًا مرتين. */
function already(value) {
  return String(value || '')
    .split(/[,;]/)
    .map((part) => (part.match(/<([^>]+)>/) || [])[1] || part)
    .map((x) => x.trim().toLowerCase())
    .filter(Boolean);
}

/** "عمر حلمي <dpd@…>" — الاسم يبقى ظاهرًا للمستلم بدل عنوان جافّ. */
function formatted(person) {
  // العنوان المرسل يحمل الاسم الإنجليزي دائمًا: ترويسة الرسالة تُقرأ في
  // برامج بريد لا نعرف لغتها، والاسم اللاتيني أسلم في كل واحد منها.
  const name = String(person.name || person.nameAr || '').trim();
  if (!name || name.toLowerCase() === String(person.email).toLowerCase()) return person.email;
  return `${name} <${person.email}>`;
}

function highlight(text, q) {
  const raw = String(text || '');
  if (!q) return esc(raw);
  const at = norm(raw).indexOf(q);
  if (at === -1) return esc(raw);
  return `${esc(raw.slice(0, at))}<b>${esc(raw.slice(at, at + q.length))}</b>${esc(raw.slice(at + q.length))}`;
}

function rowHTML(person, q, active) {
  const dept = person.dept ? pick(person.dept) : '';
  const shown = displayName(person);
  // لو طابق الاسم بلغته الأخرى، أظهره: وإلا بدا الصف بلا سبب ظاهر للظهور.
  const other = [person.name, person.nameAr]
    .filter((n) => n && n !== shown && norm(n).includes(q))[0] || '';
  return `
    <button type="button" class="rc-row ${active ? 'on' : ''}" data-email="${esc(person.email)}" tabindex="-1">
      ${avatarHTML(shown, person.email, 'sm')}
      <span class="rc-text">
        <span class="rc-name">${highlight(shown, q)}${other ? ` <i class="rc-alt">${highlight(other, q)}</i>` : ''}</span>
        <span class="rc-mail" dir="ltr">${highlight(person.email, q)}</span>
      </span>
      ${dept ? `<span class="rc-dept">${esc(dept)}</span>` : ''}
    </button>`;
}

/** الاسم بلغة الواجهة إن توفّر، وإلا الاسم الموجود. */
function displayName(person) {
  const ar = String(person.nameAr || '').trim();
  const en = String(person.name || '').trim();
  if (isRTL() && ar) return ar;
  return en || ar || person.email;
}

/**
 * تعليق الإكمال على حقل مستلمين.
 *
 * @param {HTMLInputElement} input
 * @param {object} state حالة التطبيق (contacts + directory)
 * @returns {() => void} دالة فك التعليق
 */
export function attachRecipients(input, state) {
  if (!input) return () => {};

  const box = document.createElement('div');
  box.className = 'rc-pop';
  box.hidden = true;
  (input.closest('.crow') || input.parentElement).appendChild(box);

  let rows = [];
  let index = 0;
  let part = { start: 0, text: '' };

  const isOpen = () => !box.hidden;

  function close() {
    box.hidden = true;
    rows = [];
    input.removeAttribute('aria-expanded');
  }

  function paint() {
    const q = norm(part.text);
    box.innerHTML = rows.map((p, i) => rowHTML(p, q, i === index)).join('')
      + `<div class="rc-hint">${esc(t('pickHint'))}</div>`;
    box.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    const on = box.querySelector('.rc-row.on');
    if (on) on.scrollIntoView({ block: 'nearest' });
  }

  function refresh() {
    part = currentPart(input.value, input.selectionStart ?? input.value.length);
    const text = part.text.trim();
    if (text.length < 1) { close(); return; }
    rows = match(state, text, already(input.value));
    if (!rows.length) { close(); return; }
    index = 0;
    paint();
  }

  /** إدراج المختار مكان المقطع الجاري، مع فاصلة تجهّز المستلم التالي. */
  function choose(person) {
    const value = input.value;
    const before = value.slice(0, part.start);
    const after = value.slice(part.start + part.text.length);
    const lead = before && !/[\s]$/.test(before) ? ' ' : '';
    const next = `${before}${lead}${formatted(person)}, `;
    input.value = next + after.replace(/^\s*,\s*/, '');
    const caret = next.length;
    input.setSelectionRange(caret, caret);
    close();
    input.focus();
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  const onInput = () => refresh();
  const onClick = () => refresh();

  const onKey = (e) => {
    if (!isOpen()) {
      // سهم لأسفل على حقل فيه نص يفتح الاقتراحات دون كتابة حرف جديد.
      if (e.key === 'ArrowDown' && input.value.trim()) { refresh(); e.preventDefault(); }
      return;
    }
    if (e.key === 'ArrowDown') { index = (index + 1) % rows.length; paint(); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { index = (index - 1 + rows.length) % rows.length; paint(); e.preventDefault(); }
    else if (e.key === 'Enter' || e.key === 'Tab') { choose(rows[index]); e.preventDefault(); }
    else if (e.key === 'Escape') { close(); e.preventDefault(); e.stopPropagation(); }
  };

  // mousedown لا click: click يأتي بعد blur فيغلق اللوح قبل أن يُقرأ الاختيار.
  const onPick = (e) => {
    const row = e.target.closest('.rc-row');
    if (!row) return;
    e.preventDefault();
    const person = rows.find((p) => p.email === row.dataset.email);
    if (person) choose(person);
  };

  const onBlur = () => setTimeout(() => { if (!box.contains(document.activeElement)) close(); }, 120);

  input.addEventListener('input', onInput);
  input.addEventListener('click', onClick);
  input.addEventListener('keydown', onKey);
  input.addEventListener('blur', onBlur);
  box.addEventListener('mousedown', onPick);

  return () => {
    input.removeEventListener('input', onInput);
    input.removeEventListener('click', onClick);
    input.removeEventListener('keydown', onKey);
    input.removeEventListener('blur', onBlur);
    box.remove();
  };
}
