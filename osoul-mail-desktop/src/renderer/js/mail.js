/**
 * Osoul Mail — واجهة صندوق البريد.
 *
 * ثلاثة ألواح: المجلدات، القائمة، القارئ. القائمة تُبنى من الترويسات فقط
 * (لا تُحمّل الأجسام أبدًا) فتفتح فورًا، والجسم يُطلب عند فتح الرسالة وحدها.
 */

import { icon, folderIcon } from './icons.js';
import {
  $, $$, on, delegate, esc, call, errText, toast, menu, closeMenu,
  fmtBytes, fmtWhen, fmtFull, groupOf, avatarHTML, who, paintBadge, ask,
} from './util.js';
import { openCompose } from './compose.js';
import { openSettings } from './settings.js';

/** حالة التطبيق. مصدر واحد للحقيقة، وكل رسم يقرأ منه. */
export const S = {
  account: null,
  folders: [],
  quota: null,
  settings: {},
  policy: {},

  folder: 'INBOX',
  page: 0,
  total: 0,
  messages: [],

  openUid: 0,
  message: null,

  search: '',
  filter: '',

  listBusy: false,
  msgBusy: false,
  conn: 'connected',
  sideOpen: false,
};

let refreshTimer = 0;

/* ===================================================================
 *  الإقلاع
 * ================================================================ */

export function startMail(data, boot) {
  S.account = data.account;
  S.folders = data.folders || [];
  S.quota = data.quota || null;
  S.settings = boot.settings || {};
  S.policy = boot.policy || {};

  const inbox = data.inbox || {};
  S.folder = inbox.folder || (S.folders[0] && S.folders[0].raw) || 'INBOX';
  S.messages = inbox.messages || [];
  S.total = inbox.total || 0;
  S.page = 0;

  paintShell();
  paintSidebar();
  paintList();
  paintReader();
  updateBadge();
  bindEvents();
  scheduleRefresh();

  $('#login').hidden = true;
  $('#boot').hidden = true;
  $('#app').hidden = false;
}

/* ===================================================================
 *  الهيكل
 * ================================================================ */

function paintShell() {
  $('#app').innerHTML = `
    <div class="titlebar">
      <button class="icon-btn only-narrow" id="t-menu" title="المجلدات">${icon('menu')}</button>
      <div class="brand">
        <img src="assets/logo.png" alt="">
        <div>
          <div class="t1">بريد أصول البناء</div>
          <div class="t2">OSOUL MAIL</div>
        </div>
      </div>

      <div class="search">
        ${icon('search', 'sm')}
        <input id="t-search" type="search" placeholder="ابحث في البريد…" spellcheck="false" autocomplete="off">
        <span class="kbd" id="t-kbd">Ctrl K</span>
        <button class="icon-btn clear" id="t-clear" hidden title="مسح">${icon('x', 'sm')}</button>
      </div>

      <div class="actions">
        <button class="icon-btn" id="t-refresh" title="تحديث (F5)">${icon('refresh')}</button>
        <button class="icon-btn" id="t-theme" title="تبديل السمة">${icon(S.settings.theme === 'light' ? 'moon' : 'sun')}</button>
        <button class="icon-btn" id="t-settings" title="الإعدادات">${icon('settings')}</button>
        <button class="icon-btn" id="t-account" title="${esc(S.account.email)}" style="width:auto;padding:0 4px">
          ${avatarHTML(S.account.fromName, S.account.email, 'sm')}
        </button>
      </div>
    </div>

    <div id="connbar" hidden></div>

    <div class="shell">
      <aside class="side" id="side"></aside>
      <section class="list" id="list"></section>
      <section class="reader" id="reader"></section>
    </div>
  `;

  // الصورة الرمزية في الشريط العلوي أصغر قليلًا.
  const av = $('#t-account .avatar');
  if (av) { av.style.width = '28px'; av.style.height = '28px'; av.style.fontSize = '11px'; }
}

/* ===================================================================
 *  الشريط الجانبي
 * ================================================================ */

function paintSidebar() {
  const special = S.folders.filter((f) => f.special);
  const custom = S.folders.filter((f) => !f.special);

  $('#side').innerHTML = `
    <div class="compose-wrap">
      <button class="btn primary wide" id="s-compose">${icon('plus', 'sm')}<span>رسالة جديدة</span></button>
    </div>
    <nav>
      ${special.map(folderRow).join('')}
      ${custom.length ? `<div class="sec">مجلدات</div>${custom.map(folderRow).join('')}` : ''}
      <button class="folder" id="s-newfolder">${icon('plus', 'sm')}<span class="name">مجلد جديد</span></button>
    </nav>
    <div class="foot">${quotaHTML()}</div>
  `;
}

function folderRow(f) {
  const on = f.raw === S.folder;
  const unread = f.unseen > 0;
  return `
    <button class="folder ${on ? 'on' : ''} ${unread ? 'unread' : ''}" data-folder="${esc(f.raw)}" title="${esc(f.display.ar)}">
      ${icon(folderIcon(f.special), 'sm')}
      <span class="name">${esc(f.display.ar)}</span>
      ${unread ? `<span class="count num">${f.unseen}</span>` : ''}
    </button>`;
}

function quotaHTML() {
  if (!S.quota || !S.quota.limit) {
    return `<div class="quota">${esc(S.account.email)}</div>`;
  }
  const pct = Math.min(100, Math.round((S.quota.used / S.quota.limit) * 100));
  return `
    <div class="quota">
      <div class="bar"><i class="${pct > 88 ? 'hot' : ''}" style="width:${pct}%"></i></div>
      <div class="num">${fmtBytes(S.quota.used)} من ${fmtBytes(S.quota.limit)} — ${pct}%</div>
    </div>`;
}

/* ===================================================================
 *  قائمة الرسائل
 * ================================================================ */

function currentFolder() {
  return S.folders.find((f) => f.raw === S.folder) || { display: { ar: 'البريد' }, special: '', raw: S.folder };
}

function paintList() {
  const f = currentFolder();
  const pages = Math.max(1, Math.ceil(S.total / (S.policy.pageSize || 50)));

  $('#list').innerHTML = `
    <div class="head">
      <div class="grow">
        <span class="title">${esc(S.search ? 'نتائج البحث' : f.display.ar)}</span>
        <span class="sub num">${S.total ? `${S.total}` : ''}</span>
      </div>
      <button class="icon-btn" id="l-refresh" title="تحديث">${icon('refresh', 'sm')}</button>
    </div>
    <div class="filters">
      <button class="chip ${!S.filter ? 'on' : ''}" data-filter="">الكل</button>
      <button class="chip ${S.filter === 'unread' ? 'on' : ''}" data-filter="unread">غير المقروء</button>
      <button class="chip ${S.filter === 'starred' ? 'on' : ''}" data-filter="starred">المميّز</button>
    </div>
    <div class="rows" id="rows">${rowsHTML()}</div>
    ${pages > 1 ? pagerHTML(pages) : ''}
  `;
}

function rowsHTML() {
  if (S.listBusy) return '<div class="loading"><div class="spinner"></div></div>';
  if (!S.messages.length) return emptyHTML();

  let out = '';
  let lastGroup = '';
  for (const m of S.messages) {
    const g = S.search ? '' : groupOf(m.ts);
    if (g && g !== lastGroup) {
      out += `<div class="group-label">${esc(g)}</div>`;
      lastGroup = g;
    }
    out += rowHTML(m);
  }
  return out;
}

function rowHTML(m) {
  const isSent = ['sent', 'drafts'].includes(currentFolder().special);
  const person = isSent ? (m.to[0] || { name: '', email: '' }) : m.from;
  const label = who(person) || '(بدون مرسل)';

  return `
    <div class="row ${m.seen ? '' : 'unread'} ${m.uid === S.openUid ? 'on' : ''}" data-uid="${m.uid}">
      ${avatarHTML(person.name, person.email, 'av')}
      <div class="body">
        <div class="line1">
          <span class="who">${esc(label)}</span>
          <span class="when num">${esc(fmtWhen(m.ts))}</span>
        </div>
        <div class="subject">${esc(m.subject || '(بدون موضوع)')}</div>
        <div class="meta">
          ${m.seen ? '' : '<span class="dot"></span>'}
          ${m.hasAttachments ? icon('clip', 'sm') : ''}
          ${m.answered ? icon('reply', 'sm') : ''}
        </div>
      </div>
      <button class="icon-btn star ${m.flagged ? 'on' : ''}" data-star="${m.uid}"
              title="${m.flagged ? 'إزالة التمييز' : 'تمييز'}">${icon('star', '', m.flagged)}</button>
    </div>`;
}

function emptyHTML() {
  if (S.search) {
    return `<div class="empty">${icon('search', 'lg')}<p>لا نتائج لهذا البحث</p>
      <div class="sub">جرّب كلمة أخرى أو ابحث في مجلد مختلف.</div></div>`;
  }
  if (S.filter === 'unread') {
    return `<div class="empty">${icon('check', 'lg')}<p>لا توجد رسائل غير مقروءة</p>
      <div class="sub">قرأت كل شيء في هذا المجلد.</div></div>`;
  }
  return `<div class="empty">${icon('mailOpen', 'lg')}<p>لا رسائل هنا</p>
    <div class="sub">هذا المجلد فارغ حاليًا.</div></div>`;
}

function pagerHTML(pages) {
  return `
    <div class="pager">
      <button class="icon-btn" id="p-prev" ${S.page === 0 ? 'disabled' : ''} title="أحدث">${icon('up', 'sm')}</button>
      <span class="num">${S.page + 1} / ${pages}</span>
      <button class="icon-btn" id="p-next" ${S.page + 1 >= pages ? 'disabled' : ''} title="أقدم">${icon('down', 'sm')}</button>
    </div>`;
}

/** تحميل صفحة من المجلد الحالي. */
export async function loadList(opts) {
  const o = opts || {};
  if (o.folder != null) { S.folder = o.folder; S.page = 0; S.openUid = 0; S.message = null; }
  if (o.page != null) S.page = o.page;
  if (o.filter != null) { S.filter = o.filter; S.page = 0; }
  if (o.search != null) { S.search = o.search; S.page = 0; }

  S.listBusy = true;
  paintList();
  paintSidebar();
  if (o.folder != null) paintReader();

  try {
    const data = await call(window.osoul.list, {
      folder: S.folder, page: S.page, search: S.search, filter: S.filter,
    });
    S.messages = data.messages || [];
    S.total = data.total || 0;
  } catch (err) {
    S.messages = [];
    S.total = 0;
    toast(errText(err), 'err');
  } finally {
    S.listBusy = false;
    paintList();
  }
}

/** تحديث عدادات المجلدات دون إعادة تحميل القائمة. */
async function refreshFolders() {
  try {
    const data = await call(window.osoul.folders, true);
    S.folders = data.folders || S.folders;
    paintSidebar();
    updateBadge();
  } catch (_) { /* عطل عابر — العدادات تُحدَّث في الدورة القادمة */ }
}

function updateBadge() {
  const inbox = S.folders.find((f) => f.special === 'inbox');
  paintBadge(inbox ? inbox.unseen : 0);
}

/* ===================================================================
 *  القارئ
 * ================================================================ */

function paintReader() {
  const box = $('#reader');
  if (!box) return;

  if (S.msgBusy) {
    box.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    return;
  }
  if (!S.message) {
    box.innerHTML = `<div class="empty" style="margin:auto">${icon('mail', 'lg')}
      <p>اختر رسالة لقراءتها</p><div class="sub">أو اضغط "رسالة جديدة" للكتابة.</div></div>`;
    box.style.display = 'grid';
    return;
  }
  box.style.display = 'flex';

  const m = S.message;
  const canDelete = true;

  box.innerHTML = `
    <div class="bar">
      <button class="icon-btn only-narrow" id="r-back" title="رجوع">${icon('back', 'sm')}</button>
      <button class="icon-btn" id="r-reply" title="رد (R)">${icon('reply', 'sm')}</button>
      <button class="icon-btn" id="r-replyall" title="رد على الجميع (A)">${icon('replyAll', 'sm')}</button>
      <button class="icon-btn" id="r-forward" title="تمرير (F)">${icon('forward', 'sm')}</button>
      <div class="sep"></div>
      <button class="icon-btn star ${m.flagged ? 'on' : ''}" id="r-star"
              title="تمييز">${icon('star', 'sm', m.flagged)}</button>
      <button class="icon-btn" id="r-unread" title="تعليم كغير مقروء">${icon('mailOpen', 'sm')}</button>
      <button class="icon-btn" id="r-move" title="نقل إلى مجلد">${icon('move', 'sm')}</button>
      ${canDelete ? `<button class="icon-btn" id="r-delete" title="حذف (Del)">${icon('trash', 'sm')}</button>` : ''}
      <div class="grow"></div>
      <button class="icon-btn" id="r-more" title="المزيد">${icon('more', 'sm')}</button>
    </div>

    <div class="scroll">
      <div class="doc">
        <div class="msg-head">
          <h2>${esc(m.subject || '(بدون موضوع)')}</h2>
          <div class="msg-from">
            ${avatarHTML(m.from.name, m.from.email, 'av')}
            <div class="who">
              <div class="n">${esc(who(m.from) || '(بدون مرسل)')}</div>
              <div class="e">${esc(m.from.email)}</div>
              <div class="to">إلى: ${esc(recipientsText(m))}</div>
            </div>
            <div class="when">${esc(fmtFull(m.ts))}</div>
          </div>
        </div>

        ${m.blockedImages ? `
          <div class="notice">
            ${icon('image', 'sm')}
            <span>حُجبت ${m.blockedImages} صورة خارجية لحماية خصوصيتك.</span>
            <button class="btn" id="r-images">عرض الصور</button>
          </div>` : ''}

        <iframe class="mail-frame" id="r-frame" sandbox="allow-same-origin" title="محتوى الرسالة"></iframe>

        ${m.attachments && m.attachments.length ? attachmentsHTML(m.attachments) : ''}

        <div class="reply-bar">
          <button class="btn" id="r-reply2">${icon('reply', 'sm')}<span>رد</span></button>
          <button class="btn" id="r-forward2">${icon('forward', 'sm')}<span>تمرير</span></button>
        </div>
      </div>
    </div>
  `;

  fillFrame(m);
  wireReader();
}

function recipientsText(m) {
  const all = [...(m.to || []), ...(m.cc || [])];
  if (!all.length) return '—';
  const names = all.slice(0, 3).map((a) => a.name || a.email);
  return names.join('، ') + (all.length > 3 ? ` و${all.length - 3} آخرين` : '');
}

function attachmentsHTML(list) {
  return `
    <div class="atts">
      <h3>${list.length} مرفق</h3>
      <div class="grid">
        ${list.map((a, i) => `
          <div class="att" data-att="${i}" title="${esc(a.filename)}">
            ${icon(a.mimeType.startsWith('image/') ? 'image' : 'file', 'sm')}
            <div class="info">
              <div class="n">${esc(a.filename)}</div>
              <div class="s num">${fmtBytes(a.size)}</div>
            </div>
            <button class="icon-btn dl" data-save="${i}" title="حفظ">${icon('download', 'sm')}</button>
          </div>`).join('')}
      </div>
    </div>`;
}

/** كتابة جسم الرسالة داخل الإطار المعزول وضبط ارتفاعه. */
function fillFrame(m) {
  const frame = $('#r-frame');
  if (!frame) return;

  const light = document.documentElement.dataset.theme === 'light';
  const css = `
    html,body{margin:0;padding:16px 18px;background:${light ? '#ffffff' : '#0f1a20'};
      color:${light ? '#22333c' : '#dbe6eb'};font-family:Cairo,'Segoe UI',Tahoma,sans-serif;
      font-size:14px;line-height:1.8;word-wrap:break-word;overflow-wrap:break-word;}
    img{max-width:100%!important;height:auto;}
    table{max-width:100%!important;}
    a{color:${light ? '#0d6f9f' : '#35a7e0'};}
    blockquote{margin:12px 0;padding-inline-start:12px;
      border-inline-start:2px solid ${light ? '#d2dade' : 'rgba(255,255,255,.14)'};
      color:${light ? '#5a707c' : '#9fb2bc'};}
    pre,.om-plain{white-space:pre-wrap;font-family:inherit;margin:0;}
    ::selection{background:rgba(22,131,189,.35);}
    *{max-width:100%;}
  `;

  frame.srcdoc = `<!doctype html><html dir="auto"><head><meta charset="utf-8">
    <style>${css}</style></head><body>${m.body}</body></html>`;

  frame.onload = () => {
    let doc;
    try { doc = frame.contentDocument; } catch (_) { frame.style.height = '520px'; return; }
    if (!doc) { frame.style.height = '520px'; return; }

    const fit = () => {
      const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
      frame.style.height = `${Math.min(Math.max(h + 24, 120), 20000)}px`;
    };
    fit();
    // الصور المضمّنة قد تصل بعد أول قياس.
    $$('img', doc).forEach((img) => { img.onload = fit; img.onerror = fit; });
    setTimeout(fit, 250);

    // كل رابط داخل الرسالة يفتح في المتصفح، لا داخل التطبيق.
    doc.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a) return;
      e.preventDefault();
      window.osoul.openExternal(a.getAttribute('href'));
    });
  };
}

/** فتح رسالة. */
export async function openMessage(uid, folder) {
  const f = folder || S.folder;
  S.openUid = uid;
  S.msgBusy = true;
  paintList();
  paintReader();

  try {
    const msg = await call(window.osoul.message, { folder: f, uid });
    S.message = msg;
    // تعليم مقروءة محليًا حتى لا ننتظر دورة تحديث.
    const row = S.messages.find((x) => x.uid === uid);
    if (row && !row.seen) {
      row.seen = true;
      const inbox = S.folders.find((x) => x.raw === f);
      if (inbox && inbox.unseen > 0) inbox.unseen--;
      paintSidebar();
      updateBadge();
    }
  } catch (err) {
    S.message = null;
    toast(errText(err), 'err');
  } finally {
    S.msgBusy = false;
    paintList();
    paintReader();
    const reader = $('#reader');
    if (reader) reader.classList.remove('hidden');
  }
}

/* ===================================================================
 *  الإجراءات
 * ================================================================ */

async function toggleStar(uid) {
  const m = S.messages.find((x) => x.uid === uid) || (S.message && S.message.uid === uid ? S.message : null);
  if (!m) return;
  const next = !m.flagged;
  m.flagged = next;
  if (S.message && S.message.uid === uid) S.message.flagged = next;
  paintList();
  if (S.message && S.message.uid === uid) paintReader();

  try {
    await call(window.osoul.flag, { folder: S.folder, uids: [uid], flag: '\\Flagged', on: next });
  } catch (err) {
    m.flagged = !next;
    paintList();
    toast(errText(err), 'err');
  }
}

async function markUnread(uid) {
  try {
    await call(window.osoul.flag, { folder: S.folder, uids: [uid], flag: '\\Seen', on: false });
    const row = S.messages.find((x) => x.uid === uid);
    if (row) row.seen = false;
    S.openUid = 0;
    S.message = null;
    paintList();
    paintReader();
    refreshFolders();
    toast('عُلّمت كغير مقروءة', 'ok');
  } catch (err) {
    toast(errText(err), 'err');
  }
}

async function deleteMessage(uid) {
  const inTrash = currentFolder().special === 'trash';
  try {
    await call(window.osoul.remove, { folder: S.folder, uids: [uid], permanent: inTrash });
    S.messages = S.messages.filter((x) => x.uid !== uid);
    S.total = Math.max(0, S.total - 1);
    if (S.openUid === uid) { S.openUid = 0; S.message = null; }
    paintList();
    paintReader();
    refreshFolders();
    toast(inTrash ? 'حُذفت نهائيًا' : 'نُقلت إلى المحذوفات', 'ok');
  } catch (err) {
    toast(errText(err), 'err');
  }
}

async function moveMessage(uid, anchor) {
  const targets = S.folders.filter((f) => f.raw !== S.folder);
  menu(anchor, [
    { cap: 'نقل إلى' },
    ...targets.map((f) => ({
      label: f.display.ar,
      icon: folderIcon(f.special),
      onClick: async () => {
        try {
          await call(window.osoul.move, { folder: S.folder, uids: [uid], dest: f.raw });
          S.messages = S.messages.filter((x) => x.uid !== uid);
          S.total = Math.max(0, S.total - 1);
          if (S.openUid === uid) { S.openUid = 0; S.message = null; }
          paintList();
          paintReader();
          refreshFolders();
          toast(`نُقلت إلى ${f.display.ar}`, 'ok');
        } catch (err) {
          toast(errText(err), 'err');
        }
      },
    })),
  ]);
}

async function showRemoteImages() {
  if (!S.message) return;
  const uid = S.message.uid;
  S.msgBusy = true;
  paintReader();
  try {
    S.message = await call(window.osoul.message, { folder: S.folder, uid, allowRemote: true });
  } catch (err) {
    toast(errText(err), 'err');
  } finally {
    S.msgBusy = false;
    paintReader();
  }
}

async function handleAttachment(index, save) {
  const att = S.message && S.message.attachments[index];
  if (!att) return;
  const payload = { folder: S.folder, uid: S.message.uid, part: att.part, filename: att.filename };
  toast(save ? 'جارٍ الحفظ…' : 'جارٍ الفتح…');
  try {
    const res = save
      ? await call(window.osoul.saveAttachment, payload)
      : await call(window.osoul.openAttachment, payload);
    if (save && res.saved) toast('حُفظ المرفق', 'ok');
    if (!save && !res.opened) toast('لا يوجد برنامج يفتح هذا الملف', 'err');
  } catch (err) {
    toast(errText(err), 'err');
  }
}

/* ===================================================================
 *  الردود
 * ================================================================ */

function replyPayload(mode) {
  const m = S.message;
  if (!m) return null;

  const me = S.account.email.toLowerCase();
  const quoteHeader = `في ${fmtFull(m.ts)}، كتب ${esc(who(m.from) || m.from.email)}:`;
  const quoted = `<br><br><div>${quoteHeader}</div><blockquote>${m.body}</blockquote>`;

  if (mode === 'forward') {
    return {
      mode,
      subject: /^fwd?:/i.test(m.subject) ? m.subject : `Fwd: ${m.subject}`,
      to: '',
      cc: '',
      body: `<br><br><div>---------- رسالة ممرّرة ----------</div>
        <div>من: ${esc(who(m.from))} &lt;${esc(m.from.email)}&gt;</div>
        <div>التاريخ: ${esc(fmtFull(m.ts))}</div>
        <div>الموضوع: ${esc(m.subject)}</div>
        <div>إلى: ${esc(recipientsText(m))}</div><br>${m.body}`,
      inReplyTo: '',
      references: '',
    };
  }

  const to = m.replyTo || `${m.from.name ? `${m.from.name} ` : ''}<${m.from.email}>`;
  let cc = '';
  if (mode === 'replyAll') {
    cc = [...(m.to || []), ...(m.cc || [])]
      .filter((a) => a.email && a.email !== me && a.email !== m.from.email)
      .map((a) => (a.name ? `${a.name} <${a.email}>` : a.email))
      .join(', ');
  }

  return {
    mode,
    subject: /^re:/i.test(m.subject) ? m.subject : `Re: ${m.subject || ''}`,
    to,
    cc,
    body: quoted,
    inReplyTo: m.messageId,
    references: [m.inReplyTo, m.messageId].filter(Boolean).join(' '),
  };
}

function startReply(mode) {
  const payload = replyPayload(mode);
  if (!payload) return;
  openCompose(payload, S, () => {
    // بعد الإرسال: تعليم الأصل كمُجاب عليه.
    if (mode !== 'forward' && S.message) {
      window.osoul.flag({ folder: S.folder, uids: [S.message.uid], flag: '\\Answered', on: true });
      const row = S.messages.find((x) => x.uid === S.message.uid);
      if (row) { row.answered = true; paintList(); }
    }
  });
}

/* ===================================================================
 *  الأحداث
 * ================================================================ */

function bindEvents() {
  /* --- الشريط العلوي --- */
  on($('#t-refresh'), 'click', () => { loadList(); refreshFolders(); });
  on($('#t-theme'), 'click', toggleTheme);
  on($('#t-settings'), 'click', () => openSettings(S, { onChange: applySettings, onLogout: logout }));
  on($('#t-menu'), 'click', () => {
    S.sideOpen = !S.sideOpen;
    $('#side').classList.toggle('open', S.sideOpen);
  });
  on($('#t-account'), 'click', (e) => {
    menu(e.currentTarget, [
      { cap: S.account.email },
      { label: 'الإعدادات', icon: 'settings', onClick: () => openSettings(S, { onChange: applySettings, onLogout: logout }) },
      { sep: true },
      { label: 'تسجيل الخروج', icon: 'logout', danger: true, onClick: logout },
    ]);
  });

  const search = $('#t-search');
  let searchTimer = 0;
  on(search, 'input', () => {
    $('#t-clear').hidden = !search.value;
    $('#t-kbd').hidden = !!search.value;
    clearTimeout(searchTimer);
    const q = search.value.trim();
    searchTimer = setTimeout(() => {
      if (q !== S.search) loadList({ search: q });
    }, 420);
  });
  on(search, 'keydown', (e) => {
    if (e.key === 'Enter') { clearTimeout(searchTimer); loadList({ search: search.value.trim() }); }
    if (e.key === 'Escape') { search.value = ''; search.blur(); if (S.search) loadList({ search: '' }); }
  });
  on($('#t-clear'), 'click', () => {
    search.value = '';
    $('#t-clear').hidden = true;
    $('#t-kbd').hidden = false;
    loadList({ search: '' });
  });

  /* --- الشريط الجانبي --- */
  delegate($('#side'), 'click', '[data-folder]', (_e, btn) => {
    S.sideOpen = false;
    $('#side').classList.remove('open');
    loadList({ folder: btn.dataset.folder, search: '', filter: '' });
    const s = $('#t-search');
    if (s) { s.value = ''; $('#t-clear').hidden = true; $('#t-kbd').hidden = false; }
  });
  delegate($('#side'), 'click', '#s-compose', () => openCompose({ mode: 'new' }, S));
  delegate($('#side'), 'click', '#s-newfolder', createFolder);

  /* --- القائمة --- */
  const list = $('#list');
  delegate(list, 'click', '[data-star]', (e, btn) => {
    e.stopPropagation();
    toggleStar(Number(btn.dataset.star));
  });
  delegate(list, 'click', '.row', (_e, row) => openMessage(Number(row.dataset.uid)));
  delegate(list, 'click', '.chip', (_e, chip) => loadList({ filter: chip.dataset.filter }));
  delegate(list, 'click', '#l-refresh', () => { loadList(); refreshFolders(); });
  delegate(list, 'click', '#p-prev', () => loadList({ page: S.page - 1 }));
  delegate(list, 'click', '#p-next', () => loadList({ page: S.page + 1 }));

  /* --- أحداث العملية الرئيسية --- */
  window.osoul.on('mail:new', async () => {
    await refreshFolders();
    // نحدّث القائمة فقط إن كنّا في الوارد على الصفحة الأولى بلا بحث.
    if (S.page === 0 && !S.search && currentFolder().special === 'inbox') loadList();
  });
  window.osoul.on('mail:changed', () => refreshFolders());
  window.osoul.on('mail:open', (p) => { if (p && p.uid) openMessage(p.uid, p.folder); });
  window.osoul.on('conn:state', (p) => setConn(p.state));

  bindKeys();
}

/** الأحداث داخل القارئ — تُربط بعد كل إعادة رسم. */
function wireReader() {
  const box = $('#reader');
  on($('#r-back', box), 'click', () => box.classList.add('hidden'));
  on($('#r-reply', box), 'click', () => startReply('reply'));
  on($('#r-reply2', box), 'click', () => startReply('reply'));
  on($('#r-replyall', box), 'click', () => startReply('replyAll'));
  on($('#r-forward', box), 'click', () => startReply('forward'));
  on($('#r-forward2', box), 'click', () => startReply('forward'));
  on($('#r-star', box), 'click', () => toggleStar(S.message.uid));
  on($('#r-unread', box), 'click', () => markUnread(S.message.uid));
  on($('#r-delete', box), 'click', () => deleteMessage(S.message.uid));
  on($('#r-move', box), 'click', (e) => moveMessage(S.message.uid, e.currentTarget));
  on($('#r-images', box), 'click', showRemoteImages);
  on($('#r-more', box), 'click', (e) => {
    menu(e.currentTarget, [
      { label: 'تعليم كغير مقروءة', icon: 'mailOpen', onClick: () => markUnread(S.message.uid) },
      { label: 'نقل إلى مجلد', icon: 'move', onClick: () => moveMessage(S.message.uid, e.currentTarget) },
      { sep: true },
      { label: 'حذف نهائي', icon: 'trash', danger: true, onClick: () => permanentDelete(S.message.uid) },
    ]);
  });

  delegate(box, 'click', '[data-save]', (e, btn) => {
    e.stopPropagation();
    handleAttachment(Number(btn.dataset.save), true);
  });
  delegate(box, 'click', '.att', (_e, el) => handleAttachment(Number(el.dataset.att), false));
}

async function permanentDelete(uid) {
  try {
    await call(window.osoul.remove, { folder: S.folder, uids: [uid], permanent: true });
    S.messages = S.messages.filter((x) => x.uid !== uid);
    S.total = Math.max(0, S.total - 1);
    S.openUid = 0;
    S.message = null;
    paintList();
    paintReader();
    refreshFolders();
    toast('حُذفت نهائيًا', 'ok');
  } catch (err) {
    toast(errText(err), 'err');
  }
}

async function createFolder() {
  const name = await ask('اسم المجلد الجديد:', { placeholder: 'مثال: العروض' });
  if (!name) return;
  try {
    const data = await call(window.osoul.createFolder, name.trim());
    S.folders = data.folders;
    paintSidebar();
    toast('أُنشئ المجلد', 'ok');
  } catch (err) {
    toast(errText(err), 'err');
  }
}

/* ------------------------------------------------------- اختصارات لوحة المفاتيح */

function bindKeys() {
  on(document, 'keydown', (e) => {
    const inField = /^(INPUT|TEXTAREA)$/.test(e.target.tagName) || e.target.isContentEditable;
    const composing = !!$('.compose-back');

    if (e.key === 'Escape') {
      closeMenu();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      $('#t-search').focus();
      $('#t-search').select();
      return;
    }
    if (e.key === 'F5') { e.preventDefault(); loadList(); refreshFolders(); return; }
    if (inField || composing) return;

    const idx = S.messages.findIndex((m) => m.uid === S.openUid);

    switch (e.key.toLowerCase()) {
      case '/':
        e.preventDefault();
        $('#t-search').focus();
        break;
      case 'c':
        e.preventDefault();
        openCompose({ mode: 'new' }, S);
        break;
      case 'r':
        if (S.message) { e.preventDefault(); startReply('reply'); }
        break;
      case 'a':
        if (S.message) { e.preventDefault(); startReply('replyAll'); }
        break;
      case 'f':
        if (S.message) { e.preventDefault(); startReply('forward'); }
        break;
      case 's':
        if (S.message) { e.preventDefault(); toggleStar(S.message.uid); }
        break;
      case 'u':
        if (S.message) { e.preventDefault(); markUnread(S.message.uid); }
        break;
      case 'j':
        if (idx > -1 && idx + 1 < S.messages.length) { e.preventDefault(); openMessage(S.messages[idx + 1].uid); }
        break;
      case 'k':
        if (idx > 0) { e.preventDefault(); openMessage(S.messages[idx - 1].uid); }
        break;
      case 'delete':
      case 'backspace':
        if (S.message) { e.preventDefault(); deleteMessage(S.message.uid); }
        break;
      default:
        break;
    }
  });
}

/* ------------------------------------------------------------ حالة الاتصال */

function setConn(state) {
  S.conn = state;
  const bar = $('#connbar');
  if (!bar) return;

  if (state === 'disconnected') {
    bar.className = 'connbar';
    bar.innerHTML = `${icon('wifiOff', 'sm')}<span>انقطع الاتصال بخادم البريد — جارٍ إعادة الاتصال…</span>`;
    bar.hidden = false;
  } else if (state === 'reconnected') {
    bar.className = 'connbar ok';
    bar.innerHTML = `${icon('check', 'sm')}<span>عاد الاتصال.</span>`;
    bar.hidden = false;
    setTimeout(() => { bar.hidden = true; }, 2600);
    loadList();
    refreshFolders();
  } else {
    bar.hidden = true;
  }
}

/* --------------------------------------------------------------- الإعدادات */

function toggleTheme() {
  const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
  applySettings({ theme: next });
  window.osoul.saveSettings({ theme: next });
}

export function applySettings(patch) {
  Object.assign(S.settings, patch);
  if (patch.theme) {
    document.documentElement.dataset.theme = patch.theme;
    window.osoul.setTheme(patch.theme);
    const btn = $('#t-theme');
    if (btn) btn.innerHTML = icon(patch.theme === 'light' ? 'moon' : 'sun');
    if (S.message) paintReader();
  }
  if (patch.refreshSeconds != null) scheduleRefresh();
}

function scheduleRefresh() {
  clearInterval(refreshTimer);
  const secs = Number(S.policy.refreshSeconds) || 0;
  if (!secs) return;
  refreshTimer = setInterval(() => {
    if (document.hidden) return;
    refreshFolders();
    if (S.page === 0 && !S.search) loadList();
  }, secs * 1000);
}

async function logout() {
  clearInterval(refreshTimer);
  try { await call(window.osoul.logout, { forget: true }); } catch (_) { /* الجلسة تُغلق محليًا على أي حال */ }
  window.location.reload();
}
