/**
 * Osoul Mail — نافذة الإنشاء.
 *
 * محرّر واحد بسيط: مستلمون، موضوع، نص منسّق، مرفقات. لا مسودات سحابية ولا
 * قوالب — الهدف رسالة تُكتب وتُرسل بأقل عدد نقرات.
 */

import { icon } from './icons.js';
import { $, on, delegate, esc, call, errText, toast, menu, fmtBytes, ask } from './util.js';
import { t, getLang } from './i18n.js';

/** الحد الذي تقبله معظم الخوادم للرسالة الواحدة بمرفقاتها. */
const MAX_TOTAL = 24 * 1024 * 1024;

let open = false;

/**
 * فتح نافذة الإنشاء.
 *
 * @param {{mode:string, to?:string, cc?:string, subject?:string, body?:string, inReplyTo?:string, references?:string}} draft
 * @param {object} state حالة التطبيق (للتوقيع واسم المرسل)
 * @param {Function} [onSent] يُستدعى بعد إرسال ناجح
 * @param {Function} [onSaved] يُستدعى بعد حفظ مسودة
 */
export function openCompose(draft, state, onSent, onSaved) {
  if (open) return;
  open = true;

  const d = draft || {};
  const signature = (state.settings && state.settings.signature) || '';
  const sigHTML = signature
    ? `<div class="sig"><br><br>--<br>${esc(signature).replace(/\n/g, '<br>')}</div>`
    : '';
  const title = { reply: t('reply'), replyAll: t('replyAll'), forward: t('forward') }[d.mode] || t('newMessage');

  const back = document.createElement('div');
  back.className = 'compose-back';
  back.innerHTML = `
    <div class="compose" role="dialog" aria-label="${esc(title)}">
      <div class="head">
        <div class="t">${esc(title)}</div>
        <button class="icon-btn" id="c-close" title="${esc(t('close'))} (Esc)">${icon('x', 'sm')}</button>
      </div>

      <div class="fields">
        <div class="crow">
          <label for="c-to">${esc(t('to'))}</label>
          <input id="c-to" type="text" dir="ltr" spellcheck="false" value="${esc(d.to || '')}"
                 placeholder="name@example.com" autocomplete="off">
          <div class="toggles">
            <button type="button" id="c-cc-btn" class="${d.cc ? 'on' : ''}">${esc(t('cc'))}</button>
            <button type="button" id="c-bcc-btn">${esc(t('bcc'))}</button>
          </div>
        </div>
        <div class="crow" id="c-cc-row" ${d.cc ? '' : 'hidden'}>
          <label for="c-cc">${esc(t('cc'))}</label>
          <input id="c-cc" type="text" dir="ltr" spellcheck="false" value="${esc(d.cc || '')}" autocomplete="off">
        </div>
        <div class="crow" id="c-bcc-row" hidden>
          <label for="c-bcc">${esc(t('bcc'))}</label>
          <input id="c-bcc" type="text" dir="ltr" spellcheck="false" autocomplete="off">
        </div>
        <div class="crow">
          <label for="c-subject">${esc(t('subject'))}</label>
          <input id="c-subject" type="text" value="${esc(d.subject || '')}" autocomplete="off">
          <button type="button" class="icon-btn" id="c-important"
                  title="${esc(t('markImportant'))}">${icon('important', 'sm')}</button>
        </div>
      </div>

      <div class="editor" id="c-body" contenteditable="true" dir="auto"
           data-placeholder="${esc(t('bodyPlaceholder'))}">${(d.body || '') + sigHTML}</div>

      <div class="chips" id="c-files"></div>

      <div class="toolbar">
        <button class="icon-btn" data-cmd="bold" title="${esc(t('bold'))} (Ctrl+B)">${icon('bold')}</button>
        <button class="icon-btn" data-cmd="italic" title="${esc(t('italic'))} (Ctrl+I)">${icon('italic')}</button>
        <button class="icon-btn" data-cmd="underline" title="${esc(t('underline'))} (Ctrl+U)">${icon('underline')}</button>
        <div class="sep"></div>
        <button class="icon-btn" data-cmd="insertUnorderedList" title="${esc(t('bulletList'))}">${icon('listUl')}</button>
        <button class="icon-btn" data-cmd="insertOrderedList" title="${esc(t('numberList'))}">${icon('listOl')}</button>
        <button class="icon-btn" id="c-quote" title="${esc(t('quote'))}">${icon('quote')}</button>
        <div class="sep"></div>
        <button class="icon-btn" data-cmd="justifyRight" title="${esc(t('alignRight'))}">${icon('alignRight')}</button>
        <button class="icon-btn" data-cmd="justifyCenter" title="${esc(t('alignCenter'))}">${icon('alignCenter')}</button>
        <button class="icon-btn" data-cmd="justifyLeft" title="${esc(t('alignLeft'))}">${icon('alignLeft')}</button>
        <div class="sep"></div>
        <button class="icon-btn" id="c-link" title="${esc(t('link'))}">${icon('link')}</button>
        <button class="icon-btn" id="c-attach-2" title="${esc(t('attach'))}">${icon('clip')}</button>
        <div class="sep"></div>
        <button class="btn ai" id="c-ai" title="${esc(t('ai'))}">${icon('sparkle', 'sm')}<span>${esc(t('ai'))}</span></button>
        <div class="sep"></div>
        <button class="icon-btn" id="c-dir" title="${esc(t('textDirection'))}">${icon('textSize')}</button>
        <div class="zoom">
          <button class="icon-btn" id="c-zoom-out" title="${esc(t('zoomOut'))}">A−</button>
          <span id="c-zoom-val" class="num">100%</span>
          <button class="icon-btn" id="c-zoom-in" title="${esc(t('zoomIn'))}">A+</button>
        </div>
      </div>

      <div class="foot">
        <button class="btn primary" id="c-send">${icon('send', 'sm')}<span>${esc(t('send'))}</span></button>
        <button class="btn" id="c-attach">${icon('clip', 'sm')}<span>${esc(t('attach'))}</span></button>
        <button class="btn" id="c-draft">${icon('draft', 'sm')}<span>${esc(t('saveDraft'))}</span></button>
        <div class="grow"></div>
        <span class="num" id="c-size" style="font-size:11px;color:var(--text3)"></span>
        <button class="btn" id="c-discard">${icon('trash', 'sm')}<span>${esc(t('discard'))}</span></button>
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
    if (dirty && !window.confirm(t('confirmDiscard'))) return;
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
    const url = await ask(t('linkPrompt'), { value: 'https://', dir: 'ltr' });
    if (!url) return;
    body.focus();
    if (range) { sel.removeAllRanges(); sel.addRange(range); }
    document.execCommand('createLink', false, url);
  });
  on($('#c-dir', back), 'click', () => {
    body.dir = body.dir === 'ltr' ? 'rtl' : 'ltr';
    body.style.textAlign = body.dir === 'ltr' ? 'left' : 'right';
    body.focus();
  });

  /* اقتباس: execCommand('formatBlock') لا يزيل الاقتباس، فنبدّله يدويًا. */
  on($('#c-quote', back), 'click', () => {
    body.focus();
    const sel = window.getSelection();
    const node = sel.anchorNode;
    const inside = node && (node.nodeType === 1 ? node : node.parentElement)?.closest('blockquote');
    if (inside && body.contains(inside)) {
      const frag = document.createDocumentFragment();
      while (inside.firstChild) frag.appendChild(inside.firstChild);
      inside.replaceWith(frag);
    } else {
      document.execCommand('formatBlock', false, 'blockquote');
    }
    dirty = true;
  });

  /* حجم الخط داخل المحرّر فقط — لا يُرسل مع الرسالة، راحة للكاتب. */
  let zoom = 100;
  const applyZoom = () => {
    zoom = Math.max(70, Math.min(180, zoom));
    body.style.fontSize = `${(13.5 * zoom) / 100}px`;
    $('#c-zoom-val', back).textContent = `${zoom}%`;
  };
  on($('#c-zoom-in', back), 'click', () => { zoom += 10; applyZoom(); });
  on($('#c-zoom-out', back), 'click', () => { zoom -= 10; applyZoom(); });

  /* علامة الأهمية — ترويسة X-Priority في الرسالة المرسلة. */
  let important = false;
  on($('#c-important', back), 'click', (e) => {
    important = !important;
    e.currentTarget.classList.toggle('on', important);
    e.currentTarget.title = important ? t('importantOn') : t('markImportant');
    dirty = true;
  });

  on($('#c-attach-2', back), 'click', () => $('#c-attach', back).click());
  on(body, 'input', () => { dirty = true; });
  // اللصق نصًا عاديًا: يمنع تسرّب تنسيقات غريبة من صفحات الويب.
  on(body, 'paste', (e) => {
    const text = e.clipboardData.getData('text/plain');
    if (!text) return;
    e.preventDefault();
    document.execCommand('insertText', false, text);
  });

  /* ---- مساعد الكتابة ----
   *
   * يعمل على ما كتبه الموظف وحده: الاقتباس والتوقيع مُعلَّمان بصنفين فنستثنيهما
   * قراءةً وكتابةً، فلا يُعاد كتابة رسالة الطرف الآخر ولا يُمحى توقيع الشركة.
   */

  /** نص الموظف فقط. نُخفي المستثنى لحظةً لأن innerText يحترم الإخفاء. */
  function ownText() {
    const skip = [...body.querySelectorAll('.quoted, .sig')];
    skip.forEach((el) => { el.style.display = 'none'; });
    const text = String(body.innerText || '').replace(/\u00a0/g, ' ').trim();
    skip.forEach((el) => { el.style.display = ''; });
    return text;
  }

  /** استبدال نص الموظف وحده، مع إبقاء الاقتباس والتوقيع في مكانهما. */
  function setOwnHTML(html) {
    const kids = [...body.childNodes];
    const at = kids.findIndex((n) => n.nodeType === 1 && n.classList
      && (n.classList.contains('quoted') || n.classList.contains('sig')));
    const tail = at === -1 ? null : kids[at];

    (at === -1 ? kids : kids.slice(0, at)).forEach((n) => n.remove());

    const holder = document.createElement('div');
    holder.innerHTML = html;
    const frag = document.createDocumentFragment();
    while (holder.firstChild) frag.appendChild(holder.firstChild);
    body.insertBefore(frag, tail);
  }

  let aiBusy = false;

  on($('#c-ai', back), 'click', (e) => {
    if (aiBusy) return;
    menu(e.currentTarget, [
      { cap: t('ai') },
      { label: t('aiDraft'), icon: 'sparkle', onClick: () => runAI('draft') },
      { label: t('aiImprove'), icon: 'sparkle', onClick: () => runAI('improve') },
    ]);
  });

  async function runAI(mode) {
    const text = ownText();
    if (!text) {
      toast(mode === 'improve' ? t('aiNeedText') : t('aiNeedIdea'), 'err');
      body.focus();
      return;
    }

    const btn = $('#c-ai', back);
    aiBusy = true;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner sm"></span><span>${esc(t('aiWorking'))}</span>`;

    try {
      const res = await call(window.osoul.aiGenerate, {
        mode,
        text,
        subject: $('#c-subject', back).value.trim(),
        lang: getLang(),
      });
      setOwnHTML(res.html);
      const subj = $('#c-subject', back);
      // الموضوع الذي كتبه الموظف أولى؛ لا نستبدله بموضوع مقترح.
      if (res.subject && !subj.value.trim()) subj.value = res.subject;
      dirty = true;
      toast(t('aiDone'), 'ok');
    } catch (err) {
      toast(errText(err), 'err');
    } finally {
      aiBusy = false;
      btn.disabled = false;
      btn.innerHTML = `${icon('sparkle', 'sm')}<span>${esc(t('ai'))}</span>`;
    }
  }

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
        <button data-rm="${i}" title="${esc(t('removeFile'))}">${icon('x', 'sm')}</button>
      </span>`).join('');

    const total = files.reduce((s, f) => s + (f.size || 0), 0);
    const sizeEl = $('#c-size', back);
    sizeEl.textContent = total ? t('attachmentsSize', { size: fmtBytes(total) }) : '';
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
      toast(t('needRecipient'), 'err');
      $('#c-to', back).focus();
      return;
    }
    const total = files.reduce((s, f) => s + (f.size || 0), 0);
    if (total > MAX_TOTAL) {
      toast(t('attachTooBig', { size: fmtBytes(total), max: fmtBytes(MAX_TOTAL) }), 'err');
      return;
    }
    if (!subject && !window.confirm(t('confirmNoSubject'))) return;

    sending = true;
    const btn = $('#c-send', back);
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner sm"></span><span>${esc(t('sending'))}</span>`;

    try {
      const res = await call(window.osoul.send, {
        to, cc, bcc, subject,
        html: body.innerHTML,
        attachments: files.map((f) => ({ path: f.path, filename: f.filename })),
        inReplyTo: d.inReplyTo || '',
        references: d.references || '',
        priority: important ? 'high' : 'normal',
      });
      close();
      toast(res.filed ? t('sentFiled') : t('sentOk'), 'ok');
      if (res.rejected && res.rejected.length) {
        toast(t('rejected', { n: res.rejected.length, list: res.rejected.join(t('listSep')) }), 'err');
      }
      if (onSent) onSent(res);
    } catch (err) {
      toast(errText(err), 'err');
      sending = false;
      btn.disabled = false;
      btn.innerHTML = `${icon('send', 'sm')}<span>${esc(t('send'))}</span>`;
    }
  }

  /* ---- حفظ كمسودة ---- */
  on($('#c-draft', back), 'click', async () => {
    if (sending) return;
    const btn = $('#c-draft', back);
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner sm"></span><span>${esc(t('saving'))}</span>`;
    try {
      await call(window.osoul.saveDraft, {
        to: $('#c-to', back).value.trim(),
        cc: $('#c-cc', back).value.trim(),
        bcc: $('#c-bcc', back).value.trim(),
        subject: $('#c-subject', back).value.trim(),
        html: body.innerHTML,
        attachments: files.map((f) => ({ path: f.path, filename: f.filename })),
        inReplyTo: d.inReplyTo || '',
        references: d.references || '',
        priority: important ? 'high' : 'normal',
      });
      dirty = false;
      close();
      toast(t('draftSaved'), 'ok');
      if (onSaved) onSaved();
    } catch (err) {
      toast(errText(err), 'err');
      btn.disabled = false;
      btn.innerHTML = `${icon('draft', 'sm')}<span>${esc(t('saveDraft'))}</span>`;
    }
  });

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
