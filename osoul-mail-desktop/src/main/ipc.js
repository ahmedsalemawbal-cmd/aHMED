/**
 * Osoul Mail — جسر IPC بين الواجهة والعملية الرئيسية.
 *
 * الواجهة لا تلمس الشبكة ولا القرص إطلاقًا: كل عملية IMAP/SMTP وكل قراءة
 * ملف تمرّ من هنا. هذا ما يجعل تفعيل sandbox في نافذة العرض ممكنًا، ويعني
 * أن محتوى رسالة خبيثة يبقى محتوى لا كودًا.
 */

'use strict';

const path = require('path');
const fs = require('fs');
const os = require('os');
const { ipcMain, dialog, shell, app, Notification, nativeImage, nativeTheme } = require('electron');

const store = require('./store');
const { login } = require('./session');
const { sanitizeHTML, textToHTML, htmlToSnippet } = require('./sanitize');
const { WINDOW_BG } = require('./theme');

/** الجلسة الحالية (موظف واحد لكل نافذة). */
let current = null;
let mainWindow = null;
let policy = null;

/** رد موحد: لا نرمي استثناءات عبر IPC، بل نعيد { ok, error }. */
function wrap(handler) {
  return async (_event, payload) => {
    try {
      const data = await handler(payload || {});
      return { ok: true, data };
    } catch (err) {
      const info = err && err.osoul
        ? err.osoul
        : { code: String((err && err.message) || 'UNKNOWN'), ar: 'حدث خطأ غير متوقع.', en: 'Something went wrong.' };
      return { ok: false, error: info };
    }
  };
}

function requireSession() {
  if (!current) {
    const err = new Error('NO_SESSION');
    err.osoul = { code: 'NO_SESSION', ar: 'انتهت الجلسة. سجل الدخول من جديد.', en: 'Session ended. Please sign in again.' };
    throw err;
  }
  return current;
}

function send(channel, payload) {
  if (mainWindow && !mainWindow.isDestroyed()) mainWindow.webContents.send(channel, payload);
}

/* ---------------------------------------------------------- ربط الأحداث */

function attachSessionEvents(session) {
  session.mail.on('connected', () => send('conn:state', { state: 'connected' }));
  session.mail.on('reconnected', () => send('conn:state', { state: 'reconnected' }));
  session.mail.on('disconnected', () => send('conn:state', { state: 'disconnected' }));
  session.mail.on('warning', (msg) => send('conn:warning', { message: msg }));
  session.mail.on('changed', () => send('mail:changed', {}));

  session.mail.on('mail', async (info) => {
    send('mail:new', info);
    if (!store.getSettings().notifications || !Notification.isSupported()) return;
    try {
      // نعرض عنوان آخر رسالة فقط — إشعار واحد هادئ لا سيل إشعارات.
      const page = await session.mail.list({ folder: info.path || 'INBOX', page: 0, pageSize: 1 });
      const top = page.messages[0];
      if (!top || top.seen) return;
      const n = new Notification({
        title: top.from.name || top.from.email || 'رسالة جديدة',
        body: top.subject || '(بدون موضوع)',
        silent: false,
      });
      n.on('click', () => {
        if (mainWindow && !mainWindow.isDestroyed()) {
          if (mainWindow.isMinimized()) mainWindow.restore();
          mainWindow.focus();
          send('mail:open', { folder: info.path || 'INBOX', uid: top.uid });
        }
      });
      n.show();
    } catch (_) { /* الإشعار رفاهية، لا يستحق كسر الجلسة */ }
  });
}

/* ------------------------------------------------------------ التسجيل */

function registerIPC(ctx) {
  mainWindow = ctx.win;
  policy = ctx.policy;

  /* ---- الإقلاع ---- */
  ipcMain.handle('app:boot', wrap(async () => {
    const saved = store.loadAccount();
    return {
      settings: store.getSettings(),
      canRemember: store.canEncrypt(),
      version: app.getVersion(),
      policy: {
        allowedDomains: policy.allowedDomains,
        allowAdvancedServers: policy.allowAdvancedServers,
        pageSize: policy.pageSize,
        refreshSeconds: policy.refreshSeconds,
        imapHost: policy.imapHost,
        imapPort: policy.imapPort,
        smtpHost: policy.smtpHost,
        smtpPort: policy.smtpPort,
      },
      saved: saved ? { email: saved.email, fromName: saved.fromName || '' } : null,
    };
  }));

  /* ---- بوابة الدخول ---- */
  ipcMain.handle('auth:login', wrap(async (p) => {
    if (current) { await current.close().catch(() => {}); current = null; }

    const session = await login(p, policy);
    current = session;
    attachSessionEvents(session);

    if (p.remember) {
      store.saveAccount({
        email: session.account.email,
        password: session.account.password,
        imapHost: session.account.imapHost,
        imapPort: session.account.imapPort,
        smtpHost: session.account.smtpHost,
        smtpPort: session.account.smtpPort,
        fromName: session.account.fromName,
      });
    } else {
      store.clearAccount();
    }

    const settings = store.getSettings();
    if (!settings.fromName) store.saveSettings({ fromName: session.account.fromName });

    return buildBootPayload(session);
  }));

  /** دخول صامت ببيانات محفوظة عند فتح التطبيق. */
  ipcMain.handle('auth:resume', wrap(async () => {
    const saved = store.loadAccount();
    if (!saved) return { resumed: false };
    const session = await login({ ...saved, remember: true }, policy);
    current = session;
    attachSessionEvents(session);
    return { resumed: true, ...(await buildBootPayload(session)) };
  }));

  ipcMain.handle('auth:logout', wrap(async (p) => {
    if (p.forget !== false) store.clearAccount();
    if (current) { await current.close().catch(() => {}); current = null; }
    return { ok: true };
  }));

  /* ---- صندوق البريد ---- */
  ipcMain.handle('mail:folders', wrap(async (p) => {
    const s = requireSession();
    return { folders: await s.mail.folders(!!p.force) };
  }));

  ipcMain.handle('mail:list', wrap(async (p) => {
    const s = requireSession();
    return s.mail.list({
      folder: p.folder,
      page: p.page,
      pageSize: p.pageSize || policy.pageSize,
      search: p.search,
      filter: p.filter,
    });
  }));

  ipcMain.handle('mail:message', wrap(async (p) => {
    const s = requireSession();
    const allowRemote = p.allowRemote != null ? !!p.allowRemote : !!store.getSettings().showRemoteImages;
    const msg = await s.mail.message(p.folder, p.uid, { allowRemote });

    let html;
    let blockedImages = 0;
    if (msg.html) {
      const clean = sanitizeHTML(msg.html, allowRemote);
      html = clean.html;
      blockedImages = clean.blockedImages;
      // الصور المضمنة تستبدل بمحتواها فتظهر الرسالة دون أي طلب خارجي.
      html = html.replace(/(["'])cid:([^"']+)\1/gi, (m, q, cid) => {
        const hit = msg.inline[cid] || msg.inline[decodeURIComponent(cid)];
        return hit ? `${q}${hit}${q}` : m;
      });
    } else {
      html = textToHTML(msg.text);
    }

    // قراءة الرسالة تعني أنها مقروءة.
    if (!msg.seen) {
      s.mail.setFlag(p.folder, [p.uid], '\\Seen', true).catch(() => {});
      msg.seen = true;
    }

    delete msg.inline;
    delete msg.html;
    delete msg.text;
    return { ...msg, body: html, blockedImages, snippet: htmlToSnippet(html, 200) };
  }));

  ipcMain.handle('mail:flag', wrap(async (p) => {
    const s = requireSession();
    return s.mail.setFlag(p.folder, p.uids, p.flag, !!p.on);
  }));

  ipcMain.handle('mail:delete', wrap(async (p) => {
    const s = requireSession();
    return s.mail.remove(p.folder, p.uids, !!p.permanent);
  }));

  ipcMain.handle('mail:move', wrap(async (p) => {
    const s = requireSession();
    return s.mail.move(p.folder, p.uids, p.dest);
  }));

  ipcMain.handle('mail:unseen', wrap(async (p) => {
    const s = requireSession();
    return s.mail.unseenCount(p.folder);
  }));

  ipcMain.handle('mail:quota', wrap(async () => {
    const s = requireSession();
    return { quota: await s.mail.quota() };
  }));

  ipcMain.handle('mail:folderCreate', wrap(async (p) => {
    const s = requireSession();
    await s.mail.createFolder(String(p.name || '').trim());
    return { folders: await s.mail.folders(true) };
  }));

  /* ---- الإرسال ---- */
  ipcMain.handle('mail:send', wrap(async (p) => {
    const s = requireSession();
    const settings = store.getSettings();
    return s.send({
      fromName: settings.fromName || s.account.fromName,
      to: p.to,
      cc: p.cc,
      bcc: p.bcc,
      subject: p.subject,
      html: p.html,
      text: p.text,
      attachments: p.attachments,
      inReplyTo: p.inReplyTo,
      references: p.references,
      priority: p.priority,
    });
  }));

  ipcMain.handle('compose:pickFiles', wrap(async () => {
    const res = await dialog.showOpenDialog(mainWindow, {
      title: 'إرفاق ملفات',
      properties: ['openFile', 'multiSelections'],
    });
    if (res.canceled) return { files: [] };
    const files = res.filePaths.map((f) => {
      let size = 0;
      try { size = fs.statSync(f).size; } catch (_) { /* ملف اختفى بين الاختيار والقراءة */ }
      return { path: f, filename: path.basename(f), size };
    });
    return { files };
  }));

  /* ---- المرفقات ---- */
  ipcMain.handle('mail:attachmentSave', wrap(async (p) => {
    const s = requireSession();
    const res = await dialog.showSaveDialog(mainWindow, {
      title: 'حفظ المرفق',
      defaultPath: path.join(app.getPath('downloads'), safeName(p.filename)),
    });
    if (res.canceled || !res.filePath) return { saved: false };
    const att = await s.mail.attachment(p.folder, p.uid, p.part);
    fs.writeFileSync(res.filePath, att.buffer);
    return { saved: true, path: res.filePath };
  }));

  ipcMain.handle('mail:attachmentOpen', wrap(async (p) => {
    const s = requireSession();
    const att = await s.mail.attachment(p.folder, p.uid, p.part);
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'osoul-mail-'));
    const file = path.join(dir, safeName(p.filename));
    fs.writeFileSync(file, att.buffer);
    const err = await shell.openPath(file);
    return { opened: !err, error: err || '' };
  }));

  /* ---- الإعدادات والواجهة ---- */
  ipcMain.handle('settings:get', wrap(async () => store.getSettings()));

  ipcMain.handle('settings:save', wrap(async (p) => {
    const next = store.saveSettings(p);
    if (p.theme) applyTheme(p.theme);
    return next;
  }));

  ipcMain.handle('shell:openExternal', wrap(async (p) => {
    const url = String(p.url || '');
    const u = new URL(url);
    if (!['http:', 'https:', 'mailto:'].includes(u.protocol)) return { opened: false };
    await shell.openExternal(url);
    return { opened: true };
  }));

  /** شارة عدد غير المقروء على أيقونة شريط المهام. */
  ipcMain.handle('ui:badge', wrap(async (p) => {
    if (!mainWindow || mainWindow.isDestroyed()) return { ok: false };
    if (!p.dataUrl) {
      mainWindow.setOverlayIcon(null, '');
      app.setBadgeCount(0);
      return { ok: true };
    }
    const img = nativeImage.createFromDataURL(p.dataUrl);
    mainWindow.setOverlayIcon(img, `${p.count} رسالة غير مقروءة`);
    app.setBadgeCount(Number(p.count) || 0);
    return { ok: true };
  }));

  ipcMain.handle('ui:theme', wrap(async (p) => {
    applyTheme(p.theme);
    return { ok: true };
  }));
}

/** حمل الإقلاع كاملا في نداء واحد: مجلدات + أول صفحة + الحصة. */
async function buildBootPayload(session) {
  const folders = await session.mail.folders(true);
  const inbox = folders.find((f) => f.special === 'inbox') || folders[0];
  const inboxPage = inbox
    ? await session.mail.list({ folder: inbox.raw, page: 0, pageSize: policy.pageSize })
    : { folder: '', total: 0, page: 0, pageSize: policy.pageSize, messages: [] };
  const quota = await session.mail.quota().catch(() => null);
  const settings = store.getSettings();

  return {
    account: { email: session.account.email, fromName: settings.fromName || session.account.fromName },
    folders,
    inbox: inboxPage,
    quota,
  };
}

function applyTheme(theme) {
  const dark = theme !== 'light';
  nativeTheme.themeSource = dark ? 'dark' : 'light';
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.setBackgroundColor(dark ? WINDOW_BG.dark : WINDOW_BG.light);
  }
}

function safeName(name) {
  return String(name || 'attachment').replace(/[<>:"/\\|?*]/g, '_').slice(0, 180) || 'attachment';
}

async function teardownSession() {
  if (current) {
    await current.close().catch(() => {});
    current = null;
  }
}

module.exports = { registerIPC, teardownSession };
