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
const { ipcMain, dialog, shell, app, Notification, nativeImage, nativeTheme, Menu } = require('electron');

const store = require('./store');
const { login } = require('./session');
const { sanitizeHTML, textToHTML, htmlToSnippet } = require('./sanitize');
const { WINDOW_BG } = require('./theme');
const { s: T, setLang: setMainLang } = require('./strings');
const ai = require('./ai');
const labelDefs = require('./labels');
const contacts = require('./contacts');

/** الجلسة الحالية (موظف واحد لكل نافذة). */
let current = null;
let mainWindow = null;
let policy = null;
/** دفتر العناوين يُبنى مرة لكل جلسة: مسح الصندوق مكلف ولا يتغيّر كل دقيقة. */
let contactsCache = null;

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
        title: top.from.name || top.from.email || T('newMessageFallback'),
        body: top.subject || T('noSubject'),
        // الصوت من جرس التطبيق (خمس ثوانٍ)، فلا نضيف نغمة النظام فوقه.
        silent: true,
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
    const settings = store.getSettings();
    setMainLang(settings.lang);
    const aiKey = store.loadAiKey(policy.aiKey);
    return {
      settings,
      labels: labelDefs.LABELS,
      aiReady: !!aiKey,
      aiKeyMask: policy.aiKey ? '' : ai.mask(aiKey),
      aiManaged: !!policy.aiKey,
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
        passwordChangeUrl: policy.passwordChangeUrl || '',
      },
      saved: saved ? { email: saved.email, fromName: saved.fromName || '' } : null,
    };
  }));

  /* ---- بوابة الدخول ---- */
  ipcMain.handle('auth:login', wrap(async (p) => {
    if (current) { await current.close().catch(() => {}); current = null; }
    contactsCache = null;

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

  /**
   * تحديث كلمة المرور.
   *
   * بروتوكول IMAP لا يملك أمرًا لتغيير كلمة المرور؛ التغيير الحقيقي يتم عند
   * مزوّد البريد. ما يفعله هذا المعالج: يتأكد أن الكلمة الجديدة تعمل فعلًا
   * على الخادم، ثم يبني جلسة جديدة بها ويحدّث الخزنة المشفّرة — فيواصل
   * الموظف عمله بلا تسجيل خروج وبلا إعادة إدخال بيانات.
   */
  ipcMain.handle('auth:changePassword', wrap(async (p) => {
    const session = requireSession();
    const now = String(p.current || '');
    const next = String(p.next || '');

    const bad = (code, ar, en) => {
      const err = new Error(code);
      err.osoul = { code, ar, en };
      return err;
    };

    if (now !== session.account.password) {
      throw bad('PW_WRONG', 'كلمة المرور الحالية غير صحيحة.', 'The current password is not correct.');
    }
    if (next === now) {
      throw bad('PW_SAME',
        'كلمة المرور الجديدة مطابقة للحالية.',
        'The new password is the same as the current one.');
    }
    if (next.length < 8) {
      throw bad('PW_SHORT',
        'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.',
        'The new password must be at least 8 characters.');
    }

    const acc = session.account;
    let fresh;
    try {
      fresh = await login({
        email: acc.email,
        password: next,
        imapHost: acc.imapHost,
        imapPort: acc.imapPort,
        smtpHost: acc.smtpHost,
        smtpPort: acc.smtpPort,
        fromName: acc.fromName,
      }, policy);
    } catch (err) {
      // الخادم رفض الكلمة الجديدة: لم تُغيَّر هناك بعد.
      if (err && err.osoul && err.osoul.code === 'AUTH') {
        throw bad('PW_NOT_ON_SERVER',
          'خادم البريد لا يعرف كلمة المرور الجديدة. غيّرها أولًا عند مزوّد البريد ثم حدّثها هنا.',
          'The mail server does not know this new password yet. Change it at your mail provider first, then update it here.');
      }
      throw err;
    }

    // بيانات محفوظة؟ نُبقيها محفوظة بالكلمة الجديدة.
    const remembered = !!store.loadAccount();

    await current.close().catch(() => {});
    current = fresh;
    contactsCache = null;
    attachSessionEvents(fresh);

    if (remembered) {
      store.saveAccount({
        email: fresh.account.email,
        password: fresh.account.password,
        imapHost: fresh.account.imapHost,
        imapPort: fresh.account.imapPort,
        smtpHost: fresh.account.smtpHost,
        smtpPort: fresh.account.smtpPort,
        fromName: fresh.account.fromName,
      });
    }

    return { ok: true, remembered };
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

  ipcMain.handle('mail:saveDraft', wrap(async (p) => {
    const s = requireSession();
    const settings = store.getSettings();
    const res = await s.saveDraft({
      fromName: settings.fromName || s.account.fromName,
      to: p.to, cc: p.cc, bcc: p.bcc,
      subject: p.subject, html: p.html, text: p.text,
      attachments: p.attachments,
      inReplyTo: p.inReplyTo, references: p.references, priority: p.priority,
    });
    s.mail.folders(true).catch(() => {});
    return res;
  }));

  ipcMain.handle('compose:pickFiles', wrap(async () => {
    const res = await dialog.showOpenDialog(mainWindow, {
      title: T('attachDialog'),
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
      title: T('saveAttachment'),
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

  /* ---- التصنيفات ---- */
  ipcMain.handle('mail:setLabel', wrap(async (p) => {
    const s = requireSession();
    return s.mail.setLabel(p.folder, p.uids, p.slug || '');
  }));

  ipcMain.handle('mail:labelCounts', wrap(async (p) => {
    const s = requireSession();
    return { counts: await s.mail.labelCounts(p.folder) };
  }));

  ipcMain.handle('mail:labelsSupported', wrap(async (p) => {
    const s = requireSession();
    return { supported: await s.mail.supportsKeywords(p.folder) };
  }));

  /* ---- جهات الاتصال ---- */
  ipcMain.handle('mail:contacts', wrap(async (p) => {
    const s = requireSession();
    if (!contactsCache || p.force) {
      contactsCache = await contacts.build(s.mail, s.account.email);
    }
    return { contacts: contactsCache };
  }));

  /* ---- مساعد الكتابة ---- */
  ipcMain.handle('ai:generate', wrap(async (p) => {
    const key = store.loadAiKey(policy.aiKey);
    return ai.generate(
      { key, model: policy.aiModel },
      { mode: p.mode, text: p.text, subject: p.subject, lang: store.getSettings().lang },
    );
  }));

  ipcMain.handle('ai:setKey', wrap(async (p) => {
    if (policy.aiKey) {
      // المفتاح مضبوط مركزيًا في policy.json — لا يعبث به الموظف.
      return { ready: true, mask: '', managed: true };
    }
    const okSave = store.saveAiKey(p.key);
    if (!okSave && p.key) {
      const err = new Error('NO_ENCRYPTION');
      err.osoul = {
        code: 'NO_ENCRYPTION',
        ar: 'تعذّر حفظ المفتاح: التشفير غير متاح على هذا الجهاز.',
        en: 'Could not store the key: encryption is unavailable on this computer.',
      };
      throw err;
    }
    const key = store.loadAiKey('');
    return { ready: !!key, mask: ai.mask(key), managed: false };
  }));

  /* ---- الإعدادات والواجهة ---- */
  ipcMain.handle('settings:get', wrap(async () => store.getSettings()));

  ipcMain.handle('settings:save', wrap(async (p) => {
    const next = store.saveSettings(p);
    if (p.theme) applyTheme(p.theme);
    if (p.lang) {
      setMainLang(p.lang);
      // القائمة تُبنى مرة واحدة عند الإقلاع، فنعيد بناءها بلغتها الجديدة.
      const { buildMenu } = require('./main');
      Menu.setApplicationMenu(buildMenu());
    }
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
    mainWindow.setOverlayIcon(img, T('unreadBadge', { n: p.count }));
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
  contactsCache = null;
  if (current) {
    await current.close().catch(() => {});
    current = null;
  }
}

module.exports = { registerIPC, teardownSession };
