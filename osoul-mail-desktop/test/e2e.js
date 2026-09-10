/* اختبار شامل داخل تطبيق Electron الحقيقي: دخول → قراءة → رد → خروج. */
'use strict';
const fs = require('fs');
const path = require('path');
const { app } = require('electron');

const IMAP_PORT = 21993;
const SMTP_PORT = 21465;

/* سياسة اختبارية توجّه التطبيق إلى الخوادم الوهمية — تُكتب قبل جاهزية التطبيق. */
const userData = app.getPath('userData');
fs.mkdirSync(userData, { recursive: true });
fs.writeFileSync(path.join(userData, 'policy.json'), JSON.stringify({
  allowedDomains: ['osoulalbinaa.com'],
  imapHost: '127.0.0.1', imapPort: IMAP_PORT, imapSecure: false,
  smtpHost: '127.0.0.1', smtpPort: SMTP_PORT, smtpSecure: false,
  refreshSeconds: 0, pageSize: 10,
}));
// بداية نظيفة: لا بيانات دخول محفوظة من تشغيل سابق.
try { fs.unlinkSync(path.join(userData, 'credentials.dat')); } catch (_) {}
try { fs.unlinkSync(path.join(userData, 'settings.json')); } catch (_) {}

const { startServer: startImap } = require('./fake-imap');
const { startSmtp } = require('./fake-smtp');

require('../src/main/main.js');
const { BrowserWindow } = require('electron');

const out = {};
let failures = 0;
function check(name, cond, detail) {
  if (cond) out[name] = 'PASS';
  else { out[name] = `FAIL ${JSON.stringify(detail == null ? '' : detail).slice(0, 400)}`; failures++; }
}

(async () => {
  const imap = await startImap({ port: IMAP_PORT });
  const smtp = await startSmtp({ port: SMTP_PORT });
  const rendererErrors = [];

  await app.whenReady();
  await new Promise((r) => setTimeout(r, 2500));

  const w = BrowserWindow.getAllWindows()[0];
  if (!w) { console.log('FAIL: no window'); return app.exit(1); }
      // Electron ≥36 يمرّر كائنًا واحدًا فيه level/message، والأقدم يمرّر
      // (event, level, message) مع level رقمي. نقبل الشكلين.
      w.webContents.on('console-message', (...a) => {
        const o = a[0] && typeof a[0] === 'object' && 'message' in a[0] ? a[0] : null;
        const level = o ? o.level : a[1];
        const message = o ? o.message : a[2];
        const bad = level === 'error' || level === 'warning' || Number(level) >= 2;
        if (bad) rendererErrors.push(String(message));
      });
  const run = (js) => w.webContents.executeJavaScript(js);

  try {
    /* 1) بوابة الدخول ظاهرة */
    check('gate.visible', await run(`!document.getElementById('login').hidden`));

    /* 2) دخول حقيقي عبر الواجهة (نملأ الحقول ونضغط الزر) */
    const login = await run(`(async () => {
      document.getElementById('lg-email').value = 'ahmed@osoulalbinaa.com';
      document.getElementById('lg-pass').value = 'secret';
      document.querySelector('form.gate').requestSubmit();
      for (let i = 0; i < 60 && document.getElementById('app').hidden; i++) {
        await new Promise(r => setTimeout(r, 200));
      }
      return {
        appVisible: !document.getElementById('app').hidden,
        loginHidden: document.getElementById('login').hidden,
        error: (document.getElementById('lg-err') || {}).hidden === false
          ? document.getElementById('lg-err').textContent : ''
      };
    })()`);
    check('login.succeeded', login.appVisible && login.loginHidden, login);

    /* 3) الواجهة مبنية من بيانات الخادم الحقيقية */
    const ui = await run(`(() => {
      const t = s => { const e = document.querySelector(s); return e ? e.textContent.trim() : null; };
      return {
        folders: Array.from(document.querySelectorAll('#side [data-folder]')).map(b => b.dataset.folder),
        folderLabels: Array.from(document.querySelectorAll('#side [data-folder] .name')).map(e => e.textContent),
        unreadBadge: t('#side [data-folder="INBOX"] .count'),
        rows: document.querySelectorAll('#list .row').length,
        firstSubject: t('#list .row .subject'),
        firstWho: t('#list .row .who'),
        pager: t('.pager span'),
        quota: t('.quota .num'),
        account: (document.getElementById('t-account') || {}).title
      };
    })()`);
    check('ui.folders', ui.folders.length === 6 && ui.folders[0] === 'INBOX', ui.folders);
    check('ui.folderArabic', ui.folderLabels[0] === 'البريد الوارد', ui.folderLabels);
    check('ui.pageSizeApplied', ui.rows === 10, ui.rows);
    check('ui.pager', ui.pager === '1 / 2', ui.pager);
    check('ui.quota', /700\.0 م\.ب/.test(ui.quota || ''), ui.quota);
    check('ui.account', ui.account === 'ahmed@osoulalbinaa.com', ui.account);

    /* 4) فتح رسالة متعددة الأجزاء وقراءة جسمها من داخل الإطار */
    const msg = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.openMessage(101, 'INBOX');
      for (let i = 0; i < 40; i++) {
        const f = document.getElementById('r-frame');
        if (f && f.contentDocument && f.contentDocument.body && f.contentDocument.body.innerHTML.length > 10) break;
        await new Promise(r => setTimeout(r, 150));
      }
      const frame = document.getElementById('r-frame');
      const doc = frame && frame.contentDocument;
      const t = s => { const e = document.querySelector(s); return e ? e.textContent.trim() : null; };
      return {
        subject: t('.msg-head h2'),
        from: t('.msg-from .who .n'),
        fromEmail: t('.msg-from .who .e'),
        to: t('.msg-from .who .to'),
        bodyText: doc ? doc.body.innerText.trim() : null,
        imgSrcPrefix: doc && doc.querySelector('img') ? doc.querySelector('img').getAttribute('src').slice(0, 21) : null,
        frameHeight: frame ? parseInt(frame.style.height) : 0,
        attachments: Array.from(document.querySelectorAll('.att .n')).map(e => e.textContent),
        attSize: t('.att .s'),
        toolbar: ['r-reply','r-replyall','r-forward','r-star','r-unread','r-move','r-delete']
          .filter(id => document.getElementById(id)).length
      };
    })()`);
    check('msg.subject', msg.subject === 'عرض سعر', msg.subject);
    check('msg.from', msg.from === 'Al Fahd Co' && msg.fromEmail === 'sales@alfahd.com', msg);
    check('msg.recipients', /finance@osoulalbinaa\.com|Finance/.test(msg.to || ''), msg.to);
    check('msg.bodyRendered', (msg.bodyText || '').includes('مرحبا'), msg.bodyText);
    check('msg.inlineImageInlined', msg.imgSrcPrefix === 'data:image/png;base64', msg.imgSrcPrefix);
    check('msg.noRemoteRequests', await run(`(() => { const d=document.getElementById('r-frame').contentDocument; return Array.from(d.querySelectorAll('img')).every(i => (i.getAttribute('src')||'').startsWith('data:')); })()`));
    check('msg.frameSized', msg.frameHeight >= 120, msg.frameHeight);
    check('msg.attachment', msg.attachments.join() === 'عرض.pdf', msg.attachments);
    check('msg.toolbarComplete', msg.toolbar === 7, msg.toolbar);

    /* 5) رسالة نصية عادية تُعرض كنص مع روابط */
    const plain = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.openMessage(102, 'INBOX');
      for (let i = 0; i < 40; i++) {
        const f = document.getElementById('r-frame');
        if (f && f.contentDocument && f.contentDocument.body.innerText.trim()) break;
        await new Promise(r => setTimeout(r, 150));
      }
      const doc = document.getElementById('r-frame').contentDocument;
      return { text: doc.body.innerText.trim(), plainWrapper: !!doc.querySelector('.om-plain') };
    })()`);
    check('plain.rendered', plain.text === 'Plain fallback' && plain.plainWrapper, plain);

    /* 6) رد فعلي يمر عبر SMTP */
    const reply = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.openMessage(101, 'INBOX');
      await new Promise(r => setTimeout(r, 600));
      document.getElementById('r-reply').click();
      await new Promise(r => setTimeout(r, 400));
      const to = document.getElementById('c-to').value;
      const subject = document.getElementById('c-subject').value;
      const quoted = document.getElementById('c-body').innerHTML;
      document.getElementById('c-body').innerHTML = '<p>تم الاستلام، شكرًا.</p>' + quoted;
      document.getElementById('c-send').click();
      for (let i = 0; i < 60 && document.querySelector('.compose'); i++) {
        await new Promise(r => setTimeout(r, 200));
      }
      return {
        to, subject,
        quotedOriginal: /مرحبا/.test(quoted),
        blockquote: /<blockquote>/.test(quoted),
        closed: !document.querySelector('.compose'),
        toast: (document.querySelector('.toast') || {}).textContent || ''
      };
    })()`);
    check('reply.to', /sales@alfahd\.com/.test(reply.to), reply.to);
    check('reply.subject', reply.subject === 'Re: عرض سعر', reply.subject);
    check('reply.quotesOriginal', reply.quotedOriginal && reply.blockquote, reply);
    check('reply.sent', reply.closed, reply);
    check('reply.toast', /أُرسلت/.test(reply.toast), reply.toast);

    const delivered = smtp.captured[smtp.captured.length - 1];
    check('smtp.received', !!delivered, smtp.captured.length);
    if (delivered) {
      check('smtp.rcpt', delivered.rcpt.includes('sales@alfahd.com'), delivered.rcpt);
      check('smtp.subjectEncoded', /^Subject: =\?UTF-8\?/m.test(delivered.body), delivered.body.match(/^Subject:.*/m));
      check('smtp.inReplyTo', /^In-Reply-To: <a101@alfahd\.com>/m.test(delivered.body),
        delivered.body.match(/^In-Reply-To:.*/m));
      check('smtp.replyBody', /2KrZhSDYp|=D8=AA=D9=85/.test(delivered.body.replace(/\r\n/g, '')),
        'reply text missing');
    }

    /* 6.5) أدوات نافذة الكتابة + حفظ المسودة */
    const composeTools = await run(`(async () => {
      const c = await import('./js/compose.js');
      const m = await import('./js/mail.js');
      c.openCompose({ mode: 'new' }, m.S);
      await new Promise(r => setTimeout(r, 300));
      const dock = document.querySelector('.compose');
      const tools = Array.from(dock.querySelectorAll('.toolbar button'))
        .map(b => b.dataset.cmd || b.id).filter(Boolean);
      return {
        tools,
        zoom: !!dock.querySelector('#c-zoom-in') && !!dock.querySelector('#c-zoom-out'),
        zoomLabel: (dock.querySelector('#c-zoom-val') || {}).textContent,
        important: !!dock.querySelector('#c-important'),
        draftButton: !!dock.querySelector('#c-draft'),
      };
    })()`);
    const wanted = ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList',
      'c-quote', 'justifyRight', 'justifyCenter', 'justifyLeft', 'c-link'];
    check('compose.toolbar', wanted.every((t) => composeTools.tools.includes(t)),
      { missing: wanted.filter((t) => !composeTools.tools.includes(t)), got: composeTools.tools });
    check('compose.fontZoom', composeTools.zoom && composeTools.zoomLabel === '100%', composeTools);
    check('compose.importantFlag', composeTools.important);
    check('compose.draftButton', composeTools.draftButton);

    const zoomWorks = await run(`(async () => {
      document.getElementById('c-zoom-in').click();
      document.getElementById('c-zoom-in').click();
      const after = document.getElementById('c-zoom-val').textContent;
      const size = document.getElementById('c-body').style.fontSize;
      return { after, size };
    })()`);
    check('compose.zoomChanges', zoomWorks.after === '120%' && zoomWorks.size !== '', zoomWorks);

    const draft = await run(`(async () => {
      document.getElementById('toasts').innerHTML = '';  // إشعارات خطوة سابقة قد تكون ظاهرة
      document.getElementById('c-to').value = 'someone@example.com';
      document.getElementById('c-subject').value = 'مسودة اختبار';
      document.getElementById('c-body').innerHTML = '<p>نص المسودة</p>';
      document.getElementById('c-important').click();
      document.getElementById('c-draft').click();
      for (let i = 0; i < 60 && document.querySelector('.compose'); i++) {
        await new Promise(r => setTimeout(r, 200));
      }
      const toasts = Array.from(document.querySelectorAll('.toast')).map(t => t.textContent);
      return { closed: !document.querySelector('.compose'), toasts };
    })()`);
    check('compose.savesDraft',
      draft.closed && draft.toasts.some((t) => /المسودات/.test(t)), draft);

    /* 7) البحث */
    const search = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.loadList({ search: 'عرض' });
      await new Promise(r => setTimeout(r, 400));
      return {
        rows: document.querySelectorAll('#list .row').length,
        title: (document.querySelector('#list .head .title') || {}).textContent
      };
    })()`);
    check('search.rows', search.rows === 2, search);
    check('search.title', search.title === 'نتائج البحث', search.title);

    /* 8) تبديل السمة */
    const theme = await run(`(async () => {
      document.getElementById('t-theme').click();
      await new Promise(r => setTimeout(r, 200));
      return { theme: document.documentElement.dataset.theme,
               bg: getComputedStyle(document.body).backgroundColor };
    })()`);
    check('theme.toggled', theme.theme === 'light' && theme.bg === 'rgb(238, 241, 243)', theme);

    /* 9) التصنيفات */
    const labels = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.loadList({ folder: 'INBOX', search: '', filter: '' });
      await new Promise(r => setTimeout(r, 500));
      const rows = Array.from(document.querySelectorAll('#side [data-label]'));
      const counts = await window.osoul.labelCounts('INBOX');
      const sup = await window.osoul.labelsSupported('INBOX');
      const set = await window.osoul.setLabel({ folder: 'INBOX', uids: [101], slug: 'projects' });
      if (rows[0]) rows[0].click();
      await new Promise(r => setTimeout(r, 500));
      return {
        slugs: rows.map(b => b.dataset.label),
        names: rows.map(b => (b.querySelector('.name') || {}).textContent),
        dots: rows.filter(b => b.querySelector('.dot')).length,
        counts: sup.ok && counts.ok ? Object.keys(counts.data.counts) : [],
        supported: sup.ok && sup.data.supported,
        setOk: set.ok,
        filter: mail.S.filter,
        activeRow: !!document.querySelector('#side [data-label].on'),
      };
    })()`);
    check('labels.sidebarRows', labels.slugs.join() === 'projects,procurement,quality,hr,finance', labels.slugs);
    check('labels.names', labels.names[0] === 'مشاريع', labels.names);
    check('labels.colourDots', labels.dots === 5, labels.dots);
    check('labels.supported', labels.supported === true, labels.supported);
    check('labels.counts', labels.counts.join() === 'projects,procurement,quality,hr,finance', labels.counts);
    check('labels.assign', labels.setOk === true, labels);
    check('labels.filters', labels.filter === 'label:projects', labels.filter);
    check('labels.rowHighlighted', labels.activeRow === true, labels);

    /* 10) جهات الاتصال */
    const contacts = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.loadList({ folder: 'INBOX', search: '', filter: '' });
      await new Promise(r => setTimeout(r, 400));
      document.getElementById('s-contacts').click();
      await new Promise(r => setTimeout(r, 1800));
      const rows = Array.from(document.querySelectorAll('#list .row.contact'));
      const emails = rows.map(r => r.dataset.contact);
      // بحث داخل الدفتر
      const find = document.getElementById('k-find');
      find.value = 'alfahd';
      find.dispatchEvent(new Event('input'));
      await new Promise(r => setTimeout(r, 120));
      const filtered = document.querySelectorAll('#list .row.contact').length;
      find.value = '';
      find.dispatchEvent(new Event('input'));
      await new Promise(r => setTimeout(r, 120));
      // فتح بطاقة أول جهة
      document.querySelector('#list .row.contact').click();
      await new Promise(r => setTimeout(r, 250));
      return {
        screen: mail.S.screen,
        count: rows.length,
        emails,
        noSelf: !emails.includes('ahmed@osoulalbinaa.com'),
        noRobots: !emails.includes('noreply@system.local'),
        filtered,
        card: !!document.querySelector('.contact-card'),
        cardEmail: (document.querySelector('.contact-card .e') || {}).textContent,
        writeBtn: !!document.getElementById('k-write'),
      };
    })()`);
    check('contacts.screen', contacts.screen === 'contacts', contacts.screen);
    check('contacts.built', contacts.count >= 3, contacts);
    check('contacts.excludesSelf', contacts.noSelf, contacts.emails);
    check('contacts.excludesNoReply', contacts.noRobots, contacts.emails);
    check('contacts.search', contacts.filtered === 1, contacts.filtered);
    check('contacts.card', contacts.card && /@/.test(contacts.cardEmail || ''), contacts);
    check('contacts.writeButton', contacts.writeBtn, contacts);

    const cwrite = await run(`(async () => {
      document.getElementById('k-write').click();
      await new Promise(r => setTimeout(r, 350));
      const to = (document.getElementById('c-to') || {}).value;
      const btn = document.getElementById('c-close');
      if (btn) btn.click();
      await new Promise(r => setTimeout(r, 200));
      return { to, closed: !document.querySelector('.compose-back') };
    })()`);
    check('contacts.composeTo', /@/.test(cwrite.to || ''), cwrite);

    /* 11) مساعد الكتابة */
    const aiUI = await run(`(async () => {
      const mail = await import('./js/mail.js');
      await mail.loadList({ folder: 'INBOX', search: '', filter: '' });
      await new Promise(r => setTimeout(r, 400));
      const compose = await import('./js/compose.js');
      compose.openCompose({ mode: 'new' }, mail.S, null, null);
      await new Promise(r => setTimeout(r, 250));
      const btn = document.getElementById('c-ai');
      btn.click();
      await new Promise(r => setTimeout(r, 150));
      const items = Array.from(document.querySelectorAll('.menu button')).map(b => b.textContent.trim());
      // بلا فكرة مكتوبة: يجب أن يعتذر لا أن يتصل بالشبكة
      document.getElementById('toasts').innerHTML = '';
      document.getElementById('c-body').innerHTML = '';
      btn.click();
      await new Promise(r => setTimeout(r, 120));
      document.querySelector('.menu button').click();
      await new Promise(r => setTimeout(r, 250));
      const toasts = Array.from(document.querySelectorAll('.toast')).map(t => t.textContent);
      const noKey = await window.osoul.aiGenerate({ mode: 'draft', text: 'اجتماع غدًا' });
      const close = document.getElementById('c-close');
      if (close) close.click();
      await new Promise(r => setTimeout(r, 200));
      return { items, toasts, noKeyCode: noKey.ok ? '' : noKey.error.code };
    })()`);
    check('ai.button', aiUI.items.length >= 2, aiUI.items);
    check('ai.menuItems', aiUI.items.some(i => /اكتب لي/.test(i)) && aiUI.items.some(i => /حسّن/.test(i)), aiUI.items);
    check('ai.needsIdeaFirst', aiUI.toasts.some(t => /فكرة أو طلب/.test(t)), aiUI.toasts);
    check('ai.noKeyReported', aiUI.noKeyCode === 'AI_NO_KEY', aiUI.noKeyCode);

    /* 12) تغيير كلمة المرور */
    const pw = await run(`(async () => {
      const mail = await import('./js/mail.js');
      const settings = await import('./js/settings.js');
      settings.openSettings(mail.S, { onChange: mail.applySettings, onLogout: () => {} });
      await new Promise(r => setTimeout(r, 250));
      const fields = ['st-pw-now', 'st-pw-new', 'st-pw-new2'].map(id => !!document.getElementById(id));
      const types = ['st-pw-now', 'st-pw-new'].map(id => document.getElementById(id).type);

      // الواجهة تمسك عدم التطابق قبل أي نداء للخادم
      document.getElementById('toasts').innerHTML = '';
      document.getElementById('st-pw-now').value = 'secret';
      document.getElementById('st-pw-new').value = 'abc12345';
      document.getElementById('st-pw-new2').value = 'zzz99999';
      document.getElementById('st-pw-save').click();
      await new Promise(r => setTimeout(r, 300));
      const mismatch = Array.from(document.querySelectorAll('.toast')).map(t => t.textContent);

      const wrong = await window.osoul.changePassword({ current: 'nope', next: 'abc12345' });
      const short = await window.osoul.changePassword({ current: 'secret', next: 'abc' });
      const same = await window.osoul.changePassword({ current: 'secret', next: 'secret' });
      // 'wrongpass' هي الكلمة الوحيدة التي يرفضها الخادم الوهمي
      const notOnServer = await window.osoul.changePassword({ current: 'secret', next: 'wrongpass' });
      const done = await window.osoul.changePassword({ current: 'secret', next: 'newsecret123' });
      // بعد التغيير لم تعد القديمة مقبولة
      const stale = await window.osoul.changePassword({ current: 'secret', next: 'another12345' });
      // والجلسة ما زالت تعمل بلا تسجيل خروج
      const stillWorks = await window.osoul.folders(true);

      const close = document.getElementById('st-close');
      if (close) close.click();
      return {
        fields, types, mismatch,
        wrong: wrong.ok ? '' : wrong.error.code,
        short: short.ok ? '' : short.error.code,
        same: same.ok ? '' : same.error.code,
        notOnServer: notOnServer.ok ? '' : notOnServer.error.code,
        done: done.ok,
        stale: stale.ok ? '' : stale.error.code,
        folders: stillWorks.ok ? stillWorks.data.folders.length : 0,
      };
    })()`);
    check('password.formPresent', pw.fields.every(Boolean), pw.fields);
    check('password.masked', pw.types.join() === 'password,password', pw.types);
    check('password.mismatchCaught', pw.mismatch.some(t => /غير متطابقتين/.test(t)), pw.mismatch);
    check('password.wrongCurrent', pw.wrong === 'PW_WRONG', pw.wrong);
    check('password.tooShort', pw.short === 'PW_SHORT', pw.short);
    check('password.rejectsSame', pw.same === 'PW_SAME', pw.same);
    check('password.notChangedOnServer', pw.notOnServer === 'PW_NOT_ON_SERVER', pw.notOnServer);
    check('password.changed', pw.done === true, pw);
    check('password.oldRejectedAfter', pw.stale === 'PW_WRONG', pw.stale);
    check('password.sessionSurvives', pw.folders === 6, pw.folders);

    /* 13) تبديل اللغة — عربي ⇄ إنجليزي */
    const lang = await run(`(async () => {
      document.getElementById('t-lang').click();
      await new Promise(r => setTimeout(r, 700));
      const en = {
        lang: document.documentElement.lang,
        dir: document.documentElement.dir,
        inbox: (document.querySelector('#side [data-folder] .name') || {}).textContent,
        compose: (document.querySelector('#s-compose span') || {}).textContent,
        label: (document.querySelector('#side [data-label] .name') || {}).textContent,
        arabicLeft: /[؀-ۿ]/.test(document.getElementById('side').textContent),
      };
      document.getElementById('t-lang').click();
      await new Promise(r => setTimeout(r, 700));
      const ar = {
        lang: document.documentElement.lang,
        dir: document.documentElement.dir,
        inbox: (document.querySelector('#side [data-folder] .name') || {}).textContent,
      };
      return { en, ar };
    })()`);
    check('lang.switchesToEnglish', lang.en.lang === 'en' && lang.en.dir === 'ltr', lang.en);
    check('lang.foldersTranslated', lang.en.inbox === 'Inbox', lang.en.inbox);
    check('lang.buttonsTranslated', /Compose|New/i.test(lang.en.compose || ''), lang.en.compose);
    check('lang.labelsTranslated', lang.en.label === 'Projects', lang.en.label);
    check('lang.noArabicLeftBehind', lang.en.arabicLeft === false, lang.en);
    check('lang.switchesBack', lang.ar.lang === 'ar' && lang.ar.dir === 'rtl' && lang.ar.inbox === 'البريد الوارد', lang.ar);

    /* 14) استئناف الجلسة بعد إعادة التشغيل (بيانات محفوظة مشفّرة) */
    // safeStorage يعتمد على DPAPI في ويندوز (متاح دائمًا)، وعلى حلقة مفاتيح
    // سطح المكتب في لينكس. بلا حلقة مفاتيح لا يحفظ التطبيق كلمة المرور
    // إطلاقًا — وهو السلوك الصحيح — فنتخطى هذه الفحوص بدل تسجيل فشل كاذب.
    const { safeStorage } = require('electron');
    const canEncrypt = safeStorage.isEncryptionAvailable();
    out._encryptionAvailable = canEncrypt;

    if (canEncrypt) {
      check('credentials.saved', fs.existsSync(path.join(userData, 'credentials.dat')));
      const resumed = await run(`(async () => {
        const r = await window.osoul.resume();
        return { ok: r.ok, resumed: r.ok && r.data.resumed, email: r.ok && r.data.account && r.data.account.email };
      })()`);
      check('session.resume', resumed.resumed && resumed.email === 'ahmed@osoulalbinaa.com', resumed);
    } else {
      out['credentials.saved'] = 'SKIP (لا تشفير على هذا النظام)';
      out['session.resume'] = 'SKIP (لا تشفير على هذا النظام)';
      check('credentials.notStoredWithoutEncryption', !fs.existsSync(path.join(userData, 'credentials.dat')));
    }

    /* 15) الخروج يمسح البيانات */
    await run(`window.osoul.logout({ forget: true })`);
    await new Promise((r) => setTimeout(r, 400));
    check('logout.clearsCredentials', !fs.existsSync(path.join(userData, 'credentials.dat')));

  } catch (e) {
    out.EXCEPTION = `${e.message}\n${e.stack}`;
    failures++;
  }

  console.log('E2E ' + JSON.stringify(out, null, 1));
  console.log('RENDERER_ERRORS ' + (rendererErrors.length ? JSON.stringify(rendererErrors) : 'none'));
  console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');

  imap.server.close();
  smtp.server.close();
  app.exit(failures ? 1 : 0);
})();
