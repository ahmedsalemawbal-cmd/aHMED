/**
 * Osoul Mail — نافذة الإنشاء.
 *
 * محرّر واحد بسيط: مستلمون، موضوع، نص منسّق، مرفقات. لا مسودات سحابية ولا
 * قوالب — الهدف رسالة تُكتب وتُرسل بأقل عدد نقرات.
 */

import { icon } from './icons.js';
import { $, on, delegate, esc, call, errText, toast, fmtBytes, ask } from './util.js';

/** الحد الذي تقبله معظم الخوادم للرسالة الواحدة بمرفقاتها. */
const MAX_TOTAL = 24 * 1024 * 1024;

let open = false;

/**
 * فتح نافذة الإنشاء.
 *
 * @param {{mode:string, to?:string, cc?:string, subject?:string, body?:string, inReplyTo?:string, references?:string}} draft
 * @param {object} state حالة التطبيق (للتوقيع واسم المرسل)
 * @param {Function} [onSent] يُستدعى بعد إرسال ناجح
 */
export function openCompose(draft, state, onSent) {
  if (open) return;
  open = true;

  const d = draft || {};
  const signature = (state.settings && state.settings.signature) || '';
  const sigHTML = signature ? `<br><br>--<br>${esc(signature).replace(/\n/g, '<br>')}` : '';
  const title = { reply: 'رد', replyAll: 'رد على الجميع', forward: 'تمرير' }[d.mode] || 'رسالة جديدة';

  const back = document.createElement('div');
  back.className = 'compose-back';
  back.innerHTML = `
    <div class="compose" role="dialog" aria-label="${esc(title)}">
      <div class="head">
        <div class="t">${esc(title)}</div>
        <button class="icon-btn" id="c-close" title="إغلاق (Esc)">${icon('x', 'sm')}</button>
      </div>

      <div class="fields">
        <div class="crow">
          <label for="c-to">إلى</label>
          <input id="c-to" type="text" dir="ltr" spellcheck="false" value="${esc(d.to || '')}"
                 placeholder="name@example.com" autocomplete="off">
          <div class="toggles">
            <button type="button" id="c-cc-btn" class="${d.cc ? 'on' : ''}">نسخة</button>
            <button type="button" id="c-bcc-btn">مخفية</button>
          </div>
        </div>
        <div class="crow" id="c-cc-row" ${d.cc ? '' : 'hidden'}>
          <label for="c-cc">نسخة</label>
          <input id="c-cc" type="text" dir="ltr" spellcheck="false" value="${esc(d.cc || '')}" autocomplete="off">
        </div>
        <div class="crow" id="c-bcc-row" hidden>
          <label for="c-bcc">مخفية</label>
          <input id="c-bcc" type="text" dir="ltr" spellcheck="false" autocomplete="off">
        </div>
        <div class="crow">
          <label for="c-subject">الموضوع</label>
          <input id="c-subject" type="text" value="${esc(d.subject || '')}" autocomplete="off">
        </div>
      </div>

      <div class="editor" id="c-body" contenteditable="true" dir="auto"
           data-placeholder="اكتب رسالتك…">${(d.body || '') + sigHTML}</div>

      <div class="chips" id="c-files"></div>

      <div class="toolbar">
        <button class="icon-btn" data-cmd="bold" title="عريض">${icon('bold')}</button>
        <button class="icon-btn" data-cmd="italic" title="مائل">${icon('italic')}</button>
        <button class="icon-btn" data-cmd="underline" title="تسطير">${icon('underline')}</button>
        <div class="sep"></div>
        <button class="icon-btn" data-cmd="insertUnorderedList" title="قائمة">${icon('listUl')}</button>
        <button class="icon-btn" id="c-link" title="رابط">${icon('link')}</button>
        <div class="sep"></div>
        <button class="icon-btn" id="c-dir" title="اتجاه النص">${icon('quote')}</button>
      </div>

      <div class="foot">
        <button class="btn primary" id="c-send">${icon('send', 'sm')}<span>إرسال</span></button>
        <button class="btn" id="c-attach">${icon('clip', 'sm')}<span>إرفاق</span></button>
        <div class="grow"></div>
        <span class="num" id="c-size" style="font-size:11px;color:var(--text3)"></span>
        <button class="btn" id="c-discard">تجاهل</button>
      </div>
    </div>
  `;

  document.body.appendChild(back);

  const body = $('#c-body', back);
  const files = [];
  let sending = false;
  let dirty = false;

  /* ---- الإغلاق ---- */
  function close() {
    open = false;
    back.remove();
    document.removeEventListener('keydown', keys);
  }
  function tryClose() {
    if (sending) return;
    if (dirty && !window.confirm('إغلاق الرسالة دون إرسالها؟')) return;
    close();
  }
  function keys(e) {
    if (e.key === 'Escape') { e.preventDefault(); tryClose(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); doSend(); }
  }
  on(document, 'keydown', keys);
  on($('#c-close', back), 'click', tryClose);
  on($('#c-discard', back), 'click', tryClose);
  on(back, 'mousedown', (e) => { if (e.target === back) tryClose(); });

  /* ---- المستلمون ---- */
  on($('#c-cc-btn', back), 'click', () => toggleRow('#c-cc-row', '#c-cc-btn', '#c-cc'));
  on($('#c-bcc-btn', back), 'click', () => toggleRow('#c-bcc-row', '#c-bcc-btn', '#c-bcc'));

  function toggleRow(rowSel, btnSel, inputSel) {
    const row = $(rowSel, back);
    row.hidden = !row.hidden;
    $(btnSel, back).classList.toggle('on', !row.hidden);
    if (!row.hidden) $(inputSel, back).focus();
  }

  /* ---- المحرّر ---- */
  delegate($('.toolbar', back), 'click', '[data-cmd]', (_e, btn) => {
    body.focus();
    document.execCommand(btn.dataset.cmd, false, null);
  });
  on($('#c-link', back), 'click', async () => {
    const sel = window.getSelection();
    const range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
    const url = await ask('عنوان الرابط:', { value: 'https://', dir: 'ltr' });
    if (!url) return;
    body.focus();
    if (range) { sel.removeAllRanges(); sel.addRange(range); }
    document.execCommand('createLink', false, url);
  });
  on($('#c-dir', back), 'click', () => {
    body.dir = body.dir === 'ltr' ? 'rtl' : 'ltr';
    body.style.textAlign = body.dir === 'ltr' ? 'left' : 'right';
  });
  on(body, 'input', () => { dirty = true; });
  // اللصق نصًا عاديًا: يمنع تسرّب تنسيقات غريبة من صفحات الويب.
  on(body, 'paste', (e) => {
    const text = e.clipboardData.getData('text/plain');
    if (!text) return;
    e.preventDefault();
    document.execCommand('insertText', false, text);
  });

  /* ---- المرفقات ---- */
  on($('#c-attach', back), 'click', async () => {
    try {
      const res = await call(window.osoul.pickFiles);
      for (const f of res.files) {
        if (!files.some((x) => x.path === f.path)) files.push(f);
      }
      dirty = true;
      paintFiles();
    } catch (err) {
      toast(errText(err), 'err');
    }
  });

  delegate($('#c-files', back), 'click', 'button[data-rm]', (_e, btn) => {
    files.splice(Number(btn.dataset.rm), 1);
    paintFiles();
  });

  function paintFiles() {
    $('#c-files', back).innerHTML = files.map((f, i) => `
      <span class="fchip" title="${esc(f.path)}">
        ${icon('file', 'sm')}
        <span class="n">${esc(f.filename)}</span>
        <span class="s num">${fmtBytes(f.size)}</span>
        <button data-rm="${i}" title="إزالة">${icon('x', 'sm')}</button>
      </span>`).join('');

    const total = files.reduce((s, f) => s + (f.size || 0), 0);
    const sizeEl = $('#c-size', back);
    sizeEl.textContent = total ? `${fmtBytes(total)} من المرفقات` : '';
    sizeEl.style.color = total > MAX_TOTAL ? 'var(--err)' : 'var(--text3)';
  }

  /* ---- الإرسال ---- */
  on($('#c-send', back), 'click', doSend);

  async function doSend() {
    if (sending) return;

    const to = $('#c-to', back).value.trim();
    const cc = $('#c-cc', back).value.trim();
    const bcc = $('#c-bcc', back).value.trim();
    const subject = $('#c-subject', back).value.trim();

    if (!to && !cc && !bcc) {
      toast('اكتب مستلمًا واحدًا على الأقل', 'err');
      $('#c-to', back).focus();
      return;
    }
    const total = files.reduce((s, f) => s + (f.size || 0), 0);
    if (total > MAX_TOTAL) {
      toast(`حجم المرفقات ${fmtBytes(total)} يتجاوز الحد المسموح (${fmtBytes(MAX_TOTAL)})`, 'err');
      return;
    }
    if (!subject && !window.confirm('إرسال الرسالة بدون موضوع؟')) return;

    sending = true;
    const btn = $('#c-send', back);
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner sm"></span><span>جارٍ الإرسال…</span>`;

    try {
      const res = await call(window.osoul.send, {
        to, cc, bcc, subject,
        html: body.innerHTML,
        attachments: files.map((f) => ({ path: f.path, filename: f.filename })),
        inReplyTo: d.inReplyTo || '',
        references: d.references || '',
      });
      close();
      toast(res.filed ? 'أُرسلت الرسالة وحُفظت نسخة في المرسل' : 'أُرسلت الرسالة', 'ok');
      if (res.rejected && res.rejected.length) {
        toast(`رُفض ${res.rejected.length} مستلم: ${res.rejected.join('، ')}`, 'err');
      }
      if (onSent) onSent(res);
    } catch (err) {
      toast(errText(err), 'err');
      sending = false;
      btn.disabled = false;
      btn.innerHTML = `${icon('send', 'sm')}<span>إرسال</span>`;
    }
  }

  /* ---- التركيز الأول ---- */
  setTimeout(() => {
    if (d.mode === 'reply' || d.mode === 'replyAll') {
      placeCursorAtStart(body);
    } else if (d.to) {
      $('#c-subject', back).focus();
    } else {
      $('#c-to', back).focus();
    }
  }, 60);
}

/** وضع المؤشر في أول المحرّر ليكتب الموظف فوق الاقتباس لا تحته. */
function placeCursorAtStart(el) {
  el.focus();
  const range = document.createRange();
  const sel = window.getSelection();
  range.setStart(el, 0);
  range.collapse(true);
  sel.removeAllRanges();
  sel.addRange(range);
}
