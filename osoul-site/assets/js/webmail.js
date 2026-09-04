/* =========================================================================
 * Osoul Albinaa Mail — بريد أصول البناء
 * Employee webmail SPA. UI ported 1:1 from the approved design canvas and
 * wired to the osoul/v1/mail REST API (IMAP/SMTP backend).
 * ====================================================================== */
(function () {
  'use strict';
  var CFG = window.OSOUL_MAIL || {};
  var ROOT = document.getElementById('osoul-mail');
  if (!ROOT) return;

  /* ------------------------------- i18n ------------------------------- */
  var LANG = (function () {
    try { var l = localStorage.getItem('osoul_lang'); if (l === 'ar' || l === 'en') return l; } catch (e) {}
    return (CFG.lang === 'en') ? 'en' : 'ar';
  })();
  var STR = {
    ar: {
      brand: 'بريد أصول البناء', brandSub: 'OSOUL ALBINAA MAIL',
      compose: 'إنشاء رسالة', searchPh: 'ابحث في البريد — الاسم، الموضوع، المرفق…', searchKey: 'للبحث',
      labelsTitle: 'التصنيفات', contacts: 'جهات الاتصال', stats: 'إحصائيات البريد', clients: 'قائمة العملاء',
      settings: 'الإعدادات', storage: 'مساحة التخزين', notifs: 'الإشعارات', markAll: 'تعليم الكل كمقروء',
      noNotifs: 'لا إشعارات جديدة', liveSync: 'مزامنة مباشرة', simulate: 'تحديث', newMail: 'رسالة جديدة',
      now: 'الآن', open: 'فتح', unreadWord: 'غير مقروءة', reply: 'رد', replyAll: 'رد للكل', forward: 'تحويل',
      archive: 'أرشفة', snooze: 'تأجيل', label: 'تصنيف', markUnread: 'غير مقروء', del: 'حذف',
      newest: 'الأحدث', oldest: 'الأقدم', noMailFolder: 'لا رسائل في هذا المجلد', selectAll: 'تحديد الكل',
      saveClient: 'حفظ كعميل', savedClient: 'عميل محفوظ', print: 'طباعة', backInbox: 'رجوع للوارد',
      to: 'إلى', cc: 'نسخة', bcc: 'مخفية', subject: 'الموضوع', send: 'إرسال', saveDraft: 'حفظ كمسودة',
      schedule: 'جدولة الإرسال', discard: 'حذف المسودة', attach: 'إرفاق ملف', ai: 'الذكاء الاصطناعي',
      aiHeading: 'ما الذي تريده؟', writeSubject: 'اكتب لي الموضوع', writeBody: 'اكتب لي نص الرسالة',
      improveBoth: 'حسّن الموضوع والنص', shorten: 'اختصر النص', improveText: 'حسّن الصياغة',
      newMessage: 'رسالة جديدة', highPriority: 'أهمية عالية', dragResize: 'اسحب لتغيير الحجم',
      searchTitle: 'نتائج البحث', searchBtn: 'بحث', saveSearch: 'حفظ كبحث ذكي', contactsTitle: 'جهات الاتصال',
      addContact: 'إضافة جهة', statsTitle: 'إحصائيات البريد', last30: 'آخر 30 يومًا',
      shortcutsTitle: 'اختصارات لوحة المفاتيح', appearance: 'المظهر', appearanceDesc: 'الوضع الليلي والنهاري وكثافة القائمة',
      mailOptions: 'خيارات البريد', account: 'الحساب', signature: 'التوقيع الرسمي', security: 'الأمان',
      language: 'اللغة', langTitle: 'لغة الواجهة', composeLangName: 'لغة كتابة الرسائل',
      clientTag: 'عميل', savedOn: 'حُفظ في', msgsWord: 'رسالة', message: 'رسالة', remove: 'حذف من العملاء',
      clientsHint: 'أي رسالة تصل من خارج نطاق الشركة يمكن حفظ مرسلها كعميل بضغطة واحدة، فيظهر اسمه هنا مع تاريخ الحفظ وعدد رسائله. جهات الاتصال تبقى مخصصة للموظفين.',
      clientsEmpty: 'لا عملاء محفوظون بعد — افتح رسالة واضغط «حفظ كعميل».',
      folders: { inbox: 'الوارد', starred: 'المميّزة', snoozed: 'المؤجّلة', sent: 'المرسل', drafts: 'المسودات', archive: 'المؤرشفة', junk: 'المزعج', trash: 'المهملات' },
      filters: ['الكل', 'غير مقروء', 'مميّز', 'له مرفقات', 'يحتاج ردًا'],
      kpis: ['رسائل مستلمة', 'رسائل مرسلة', 'غير مقروءة', 'بانتظار ردك'],
      changePw: 'تغيير كلمة مرور الدخول', curPw: 'كلمة المرور الحالية', newPw: 'كلمة المرور الجديدة',
      confPw: 'تأكيد كلمة المرور', doChangePw: 'تغيير كلمة المرور', displayName: 'الاسم الظاهر',
      writeReplyTo: 'اكتب ردًا على', connectTitle: 'اربط صندوق بريدك', connectSub: 'أدخل بريدك على هوستنجر وكلمة مروره مرة واحدة، ثم استخدم النظام بالكامل.',
      email: 'البريد الإلكتروني', password: 'كلمة مرور البريد', connect: 'ربط البريد', connecting: 'جارٍ الربط…',
      advanced: 'إعدادات الخادم (متقدّم)', hosterNote: 'الخوادم مضبوطة مسبقًا على هوستنجر. لا تغيّرها إلا إذا طلب منك ذلك.',
      imapHost: 'خادم الوارد IMAP', imapPort: 'منفذ IMAP', smtpHost: 'خادم الصادر SMTP', smtpPort: 'منفذ SMTP',
      logout: 'تسجيل الخروج', density: 'كثافة القائمة', textSize: 'حجم نص الرسالة', reset: 'إعادة',
      themeName: { dark: 'داكن', light: 'فاتح' }, densityNames: { cozy: 'مريحة', medium: 'متوسطة', compact: 'مضغوطة' },
      composeLangDesc: 'لغة المساعد الذكي عند إنشاء رسالة. «تلقائي» يتبع لغة الواجهة.',
      auto: 'تلقائي', ready_templates: 'القوالب الجاهزة', attachments: 'المرفقات', dropHere: 'اسحب الملفات هنا',
      needRcpt: 'أضف مستلماً واحداً على الأقل', sent_ok: 'تم إرسال الرسالة', draft_ok: 'تم حفظ المسودة',
      moved: 'تم نقل الرسالة', archived: 'تم أرشفة الرسالة', deleted: 'نُقلت إلى المهملات', starredOn: 'تم التمييز بنجمة',
      starredOff: 'أُزيل التمييز', markedRead: 'وُسمت كمقروءة', markedUnread: 'وُسمت كغير مقروءة', downloading: 'جارٍ التنزيل',
      folderName: 'اسم المجلد', newFolderOk: 'تم إنشاء المجلد', addr_placeholder: 'اكتب البريد ثم Enter',
      writing: 'يكتب…', aiOff: 'ميزة الذكاء الاصطناعي غير مفعّلة — راجع المسؤول', saved: 'تم الحفظ',
      moreRecip: 'عرض كل المستلمين', hideRecip: 'إخفاء القائمة', roleLabel: 'المسمى', addName: 'الاسم',
      cancel: 'إلغاء', add: 'إضافة', signatureDesc: 'يُذيّل تلقائيًا على الرسائل الصادرة', saveSig: 'حفظ التوقيع',
      correspondence: 'حجم المراسلات بحسب المجلد', noResults: 'لا نتائج', threadN: 'رسالة'
    },
    en: {
      brand: 'Osoul Albinaa Mail', brandSub: 'OSOUL ALBINAA MAIL',
      compose: 'Compose', searchPh: 'Search mail — sender, subject, attachment…', searchKey: 'to search',
      labelsTitle: 'LABELS', contacts: 'Contacts', stats: 'Mail insights', clients: 'Clients',
      settings: 'Settings', storage: 'Storage', notifs: 'Notifications', markAll: 'Mark all read',
      noNotifs: 'No new notifications', liveSync: 'Live sync', simulate: 'Refresh', newMail: 'NEW MESSAGE',
      now: 'now', open: 'Open', unreadWord: 'unread', reply: 'Reply', replyAll: 'Reply all', forward: 'Forward',
      archive: 'Archive', snooze: 'Snooze', label: 'Label', markUnread: 'Mark unread', del: 'Delete',
      newest: 'Newest', oldest: 'Oldest', noMailFolder: 'No messages in this folder', selectAll: 'Select all',
      saveClient: 'Save as client', savedClient: 'Saved client', print: 'Print', backInbox: 'Back to inbox',
      to: 'To', cc: 'Cc', bcc: 'Bcc', subject: 'Subject', send: 'Send', saveDraft: 'Save draft',
      schedule: 'Schedule send', discard: 'Discard', attach: 'Attach file', ai: 'AI assistant',
      aiHeading: 'What do you need?', writeSubject: 'Write the subject', writeBody: 'Write the message',
      improveBoth: 'Improve subject and body', shorten: 'Shorten', improveText: 'Improve wording',
      newMessage: 'New message', highPriority: 'High priority', dragResize: 'Drag to resize',
      searchTitle: 'Search results', searchBtn: 'Search', saveSearch: 'Save smart search', contactsTitle: 'Contacts',
      addContact: 'Add contact', statsTitle: 'Mail insights', last30: 'Last 30 days',
      shortcutsTitle: 'Keyboard shortcuts', appearance: 'Appearance', appearanceDesc: 'Dark and light mode, and list density',
      mailOptions: 'Mail options', account: 'Account', signature: 'Official signature', security: 'Security',
      language: 'Language', langTitle: 'Interface language', composeLangName: 'Writing language',
      clientTag: 'Client', savedOn: 'Saved', msgsWord: 'messages', message: 'Message', remove: 'Remove client',
      clientsHint: 'Any message from outside the company domain can be saved as a client in one click. The name appears here with the date saved and message count. Contacts stay reserved for employees.',
      clientsEmpty: 'No clients saved yet — open a message and press “Save as client”.',
      folders: { inbox: 'Inbox', starred: 'Starred', snoozed: 'Snoozed', sent: 'Sent', drafts: 'Drafts', archive: 'Archive', junk: 'Spam', trash: 'Trash' },
      filters: ['All', 'Unread', 'Starred', 'Has attachment', 'Needs reply'],
      kpis: ['Received', 'Sent', 'Unread', 'Awaiting reply'],
      changePw: 'Change login password', curPw: 'Current password', newPw: 'New password',
      confPw: 'Confirm password', doChangePw: 'Change password', displayName: 'Display name',
      writeReplyTo: 'Write a reply to', connectTitle: 'Connect your mailbox', connectSub: 'Enter your Hostinger email and its password once, then use the full system.',
      email: 'Email address', password: 'Email password', connect: 'Connect mailbox', connecting: 'Connecting…',
      advanced: 'Server settings (advanced)', hosterNote: 'Servers are pre-set to Hostinger. Do not change unless told to.',
      imapHost: 'IMAP host', imapPort: 'IMAP port', smtpHost: 'SMTP host', smtpPort: 'SMTP port',
      logout: 'Sign out', density: 'List density', textSize: 'Message text size', reset: 'Reset',
      themeName: { dark: 'Dark', light: 'Light' }, densityNames: { cozy: 'Cozy', medium: 'Medium', compact: 'Compact' },
      composeLangDesc: 'Language of the AI assistant when composing. “Auto” follows the interface.',
      auto: 'Auto', ready_templates: 'Templates', attachments: 'Attachments', dropHere: 'Drop files here',
      needRcpt: 'Add at least one recipient', sent_ok: 'Message sent', draft_ok: 'Draft saved',
      moved: 'Message moved', archived: 'Message archived', deleted: 'Moved to Trash', starredOn: 'Starred',
      starredOff: 'Unstarred', markedRead: 'Marked read', markedUnread: 'Marked unread', downloading: 'Downloading',
      folderName: 'Folder name', newFolderOk: 'Folder created', addr_placeholder: 'Type email then Enter',
      writing: 'Writing…', aiOff: 'AI is not enabled — ask your admin', saved: 'Saved',
      moreRecip: 'Show all recipients', hideRecip: 'Hide', roleLabel: 'Role', addName: 'Name',
      cancel: 'Cancel', add: 'Add', signatureDesc: 'Appended automatically to outgoing messages', saveSig: 'Save signature',
      correspondence: 'Messages by folder', noResults: 'No results', threadN: 'messages'
    }
  };
  function t(k) { var v = STR[LANG][k]; return v == null ? (STR.ar[k] != null ? STR.ar[k] : k) : v; }

  /* ------------------------------ helpers ----------------------------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function mi(name) { return '<span class="mi">' + name + '</span>'; }
  function q(sel, root) { return (root || document).querySelector(sel); }
  function qa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function on(node, ev, fn, opt) { if (node) node.addEventListener(ev, fn, opt); }
  function fmtBytes(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(0) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }
  var AV = ['#0a5c86', '#2f7d5c', '#8a5a2b', '#5b4b8a', '#1683bd', '#7a3550'];
  function avColor(s) { var h = 0, i; s = String(s || '?'); for (i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % AV.length; return AV[h]; }
  function initials(name, email) {
    name = (name || '').trim();
    if (name && !/@/.test(name)) { var p = name.split(/\s+/); return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase(); }
    return (email || name || '?').charAt(0).toUpperCase();
  }
  function avatarHTML(name, email, cls, sz) {
    var key = email || name || '?';
    return '<span class="om-av ' + (cls || '') + '"' + (sz ? ' style="width:' + sz + 'px;height:' + sz + 'px;background:' + avColor(key) + '"' : ' style="background:' + avColor(key) + '"') + '>' + esc(initials(name, email)) + '</span>';
  }
  function dirOf(txt) {
    var clean = String(txt || '').replace(/^\s*((re|fwd|fw|رد|إعادة توجيه)\s*:\s*)+/i, '').replace(/^[\s"'(\[\-–—.…،,:;!?]+/, '');
    var m = clean.match(/[A-Za-z؀-ۿ]/);
    if (!m) return LANG === 'en' ? 'ltr' : 'rtl';
    return /[A-Za-z]/.test(m[0]) ? 'ltr' : 'rtl';
  }
  function toast(msg, type) {
    var old = q('.om-toast'); if (old) old.remove();
    var el = document.createElement('div'); el.className = 'om-toast' + (type === 'err' ? ' err' : '');
    el.innerHTML = mi(type === 'err' ? 'error' : 'check_circle') + '<span class="msg">' + esc(msg) + '</span><button class="x">' + mi('close') + '</button>';
    document.body.appendChild(el);
    on(q('.x', el), 'click', function () { el.remove(); });
    clearTimeout(toast._t); toast._t = setTimeout(function () { el.remove(); }, 3000);
  }

  /* -------------------------------- api ------------------------------- */
  function api(path, opts) {
    opts = opts || {};
    var url = CFG.rest + path;
    if (opts.query) {
      var qs = Object.keys(opts.query).filter(function (k) { return opts.query[k] !== '' && opts.query[k] != null; })
        .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]); }).join('&');
      if (qs) url += '?' + qs;
    }
    var method = opts.method || ((opts.body || opts.form) ? 'POST' : 'GET');
    var init = { method: method, headers: { 'X-WP-Nonce': CFG.nonce }, credentials: 'same-origin' };
    if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    if (opts.form) { init.method = 'POST'; init.body = opts.form; }
    return fetch(url, init).then(function (r) {
      return r.text().then(function (txt) {
        var j; try { j = txt ? JSON.parse(txt) : {}; } catch (e) { j = {}; }
        if (!r.ok) { var m = (j && j.message) ? j.message : ('HTTP ' + r.status); var err = new Error(m); err.status = r.status; throw err; }
        return j;
      });
    });
  }

  /* ------------------------------- state ------------------------------ */
  var S = {
    screen: 'inbox', folder: 'INBOX', special: 'inbox', display: '',
    theme: document.documentElement.getAttribute('data-theme') || 'dark',
    density: 'medium', group: false, sort: 'newest', filter: 0, zoom: 1,
    page: 0, total: 0, per: 25, messages: [], folders: [], sideFolders: [], labels: [],
    currentUid: 0, currentMsg: null, sel: {}, query: '', showRecipients: false,
    contacts: [], clients: [], stats: null, quota: null,
    from_name: CFG.name || '', signature: '', email: CFG.email || '', name: CFG.name || '', avatar: '', ai: false,
    settingsTab: 'appearance', composeLang: 'auto', notifs: [], notifsOpen: false, acctOpen: false,
    lastTopUid: 0, sideOpen: false, mobileTab: 'mail', toggleState: [true, true, false, true, true], security: [true, true, false]
  };
  try { var d = localStorage.getItem('osoul_density'); if (d) S.density = d; } catch (e) {}
  try { var z = parseFloat(localStorage.getItem('osoul_zoom')); if (z >= 0.8 && z <= 1.6) S.zoom = z; } catch (e) {}

  var LABEL_DEFS = [
    { slug: 'projects', color: '#1683bd', ar: 'مشاريع', en: 'Projects' },
    { slug: 'procurement', color: '#2f7d5c', ar: 'مشتريات', en: 'Procurement' },
    { slug: 'quality', color: '#c9922f', ar: 'جودة وسلامة', en: 'Quality & safety' },
    { slug: 'hr', color: '#5b4b8a', ar: 'موارد بشرية', en: 'HR' },
    { slug: 'finance', color: '#7a3550', ar: 'مالية', en: 'Finance' }
  ];
  function labelName(l) { return LANG === 'en' ? l.en : l.ar; }

  /* -------------------------- theme / lang / density ------------------ */
  function setTheme(th) {
    S.theme = th; document.documentElement.setAttribute('data-theme', th);
    try { localStorage.setItem('osoul_theme', th); } catch (e) {}
    paintShell();
  }
  function setLang(l) {
    l = (l === 'en') ? 'en' : 'ar';
    try { localStorage.setItem('osoul_lang', l); } catch (e) {}
    document.cookie = 'osoul_lang=' + l + ';path=/;max-age=31536000;SameSite=Lax';
    location.reload();
  }
  function setDensity(d) { S.density = d; try { localStorage.setItem('osoul_density', d); } catch (e) {} paintMain(); }
  function setZoom(z) { S.zoom = Math.max(0.8, Math.min(1.6, z)); try { localStorage.setItem('osoul_zoom', String(S.zoom)); } catch (e) {} paintMain(); }

  /* ============================ SHELL RENDER =========================== */
  function paintShell() {
    ROOT.className = 'om-app' + (S.currentUid && window.innerWidth < 1000 ? ' reading' : '');
    ROOT.innerHTML =
      topbarHTML() +
      '<div class="om-body">' +
        sidebarHTML() +
        '<div class="om-main" id="om-main"></div>' +
      '</div>' +
      '<div class="om-scrim" id="om-scrim"></div>' +
      mobileChromeHTML();
    wireTopbar(); wireSidebar(); wireMobileChrome();
    paintMain();
  }

  function topbarHTML() {
    var mark = CFG.mark || CFG.logo || '';
    return '<header class="om-top">' +
      '<button class="om-menu-btn om-ib" id="om-menu">' + mi('menu') + '</button>' +
      '<div class="om-brand">' + (mark ? '<img src="' + esc(mark) + '" alt="">' : '') +
        '<div class="txt"><div class="t1">' + esc(t('brand')) + '</div><div class="t2">' + esc(t('brandSub')) + '</div></div></div>' +
      '<div class="om-search">' + mi('search') +
        '<input id="om-search-in" value="' + esc(S.query) + '" placeholder="' + esc(t('searchPh')) + '">' +
        '<span class="kbd"><span class="om-kbd">/</span><span>' + esc(t('searchKey')) + '</span></span></div>' +
      '<div class="om-top-actions">' +
        '<button class="om-ticon" id="om-theme" title="' + esc(S.theme === 'dark' ? t('themeName').light : t('themeName').dark) + '">' + mi(S.theme === 'dark' ? 'light_mode' : 'dark_mode') + '</button>' +
        '<div style="position:relative"><button class="om-ticon' + (S.notifsOpen ? ' on' : '') + '" id="om-notif-btn" title="' + esc(t('notifs')) + '">' + mi('notifications') + notifBadge() + '</button>' + (S.notifsOpen ? notifDropdownHTML() : '') + '</div>' +
        '<button class="om-ticon" id="om-shortcuts" title="' + esc(t('shortcutsTitle')) + '">' + mi('keyboard') + '</button>' +
        '<button class="om-ticon" id="om-settings" title="' + esc(t('settings')) + '">' + mi('settings') + '</button>' +
        '<div class="om-sep"></div>' +
        '<div class="om-account" id="om-account"><div class="who"><div class="n">' + esc(S.from_name || S.name) + '</div><div class="e">' + esc(S.email) + '</div></div>' + accountAv() + (S.acctOpen ? acctMenuHTML() : '') + '</div>' +
      '</div></header>';
  }
  function accountAv() {
    if (S.avatar) return '<span class="om-av av" style="background-image:url(\'' + esc(S.avatar) + '\')"></span>';
    return '<span class="om-av av">' + esc(initials(S.from_name || S.name, S.email)) + '</span>';
  }
  function notifBadge() {
    var n = S.notifs.filter(function (x) { return !x.read; }).length;
    if (!n) return '';
    return '<span class="om-cnt-badge">' + n + '</span>';
  }
  function notifDropdownHTML() {
    var unread = S.notifs.filter(function (x) { return !x.read; }).length;
    var list = S.notifs.length ? S.notifs.map(function (n, i) {
      return '<div class="om-nrow' + (n.read ? '' : ' unread') + '" data-ni="' + i + '">' + avatarHTML(n.sender, n.email, '', 32) +
        '<div style="flex:1;min-width:0"><div style="display:flex;align-items:baseline;gap:7px"><span class="n om-nowrap">' + esc(n.sender) + '</span><span class="ago">' + esc(n.ago || t('now')) + '</span></div><div class="s om-nowrap">' + esc(n.subject) + '</div></div></div>';
    }).join('') : '<div class="empty">' + esc(t('noNotifs')) + '</div>';
    return '<div class="om-pop om-notif"><div class="h"><span class="t">' + esc(t('notifs')) + '</span><span class="m">' + unread + ' ' + esc(t('unreadWord')) + '</span><button class="clr" id="om-notif-clr">' + esc(t('markAll')) + '</button></div>' +
      '<div class="list">' + list + '</div>' +
      '<div class="foot"><span class="om-live-dot"></span><span style="flex:1">' + esc(t('liveSync')) + '</span><button class="om-btn" style="height:28px;padding:0 11px" id="om-notif-refresh">' + esc(t('simulate')) + '</button></div></div>';
  }
  function acctMenuHTML() {
    return '<div class="om-pop om-acct-menu">' +
      '<button data-act="settings">' + mi('settings') + esc(t('settings')) + '</button>' +
      '<button data-act="stats">' + mi('insights') + esc(t('stats')) + '</button>' +
      '<button data-act="logout" style="color:var(--err)">' + mi('logout') + esc(t('logout')) + '</button></div>';
  }

  function sidebarHTML() {
    var folds = S.sideFolders.map(function (f) {
      var active = S.screen === 'inbox' && S.special === f.special && (f.special !== 'inbox' || S.filter !== 2 || f.special === 'inbox');
      // starred filter view lights the starred pseudo-folder
      if (f.special === 'starred') active = (S.screen === 'inbox' && S.filter === 2);
      else if (S.screen === 'inbox' && S.filter === 2 && f.special === 'inbox') active = false;
      return '<button class="om-fold' + (active ? ' active' : '') + '" data-fk="' + esc(f.special) + '" data-raw="' + esc(f.raw || '') + '">' +
        mi(f.icon) + '<span class="nm">' + esc(f.name) + '</span><span class="ct">' + (f.count ? f.count : '') + '</span><span class="mark"></span></button>';
    }).join('');
    var labels = S.labels.map(function (l) {
      return '<button class="om-label" data-lslug="' + esc(l.slug) + '"><span class="dot" style="background:' + l.color + '"></span><span class="nm">' + esc(labelName(l)) + '</span><span class="ct">' + (l.count ? l.count : '') + '</span></button>';
    }).join('');
    var q_ = S.quota; var pct = (q_ && q_.total) ? Math.min(100, Math.round(q_.used / q_.total * 100)) : 0;
    var useLine = q_ && q_.total ? (fmtBytes(q_.used) + ' / ' + fmtBytes(q_.total)) : '—';
    return '<aside class="om-side" id="om-side">' +
      '<div class="om-compose-wrap"><button class="om-compose-btn" id="om-compose-btn">' + mi('edit_square') + '<span>' + esc(t('compose')) + '</span></button></div>' +
      '<nav class="om-nav">' + folds +
        '<div class="om-nav-div"></div>' +
        '<div class="om-labels-h"><span>' + esc(t('labelsTitle')) + '</span><button id="om-add-label">' + mi('add') + '</button></div>' + labels +
        '<div class="om-nav-div"></div>' +
        '<button class="om-navlink' + (S.screen === 'contacts' ? ' active' : '') + '" data-nav="contacts">' + mi('group') + '<span class="nm">' + esc(t('contacts')) + '</span></button>' +
        '<button class="om-navlink' + (S.screen === 'stats' ? ' active' : '') + '" data-nav="stats">' + mi('insights') + '<span class="nm">' + esc(t('stats')) + '</span></button>' +
        '<button class="om-navlink' + (S.screen === 'clients' ? ' active' : '') + '" data-nav="clients">' + mi('badge') + '<span class="nm">' + esc(t('clients')) + '</span><span class="ct">' + (S.clients.length ? S.clients.length : '') + '</span></button>' +
      '</nav>' +
      '<div class="om-storage-wrap"><div class="om-storage"><div class="row"><span>' + esc(t('storage')) + '</span><span class="v">' + pct + '%</span></div>' +
        '<div class="bar"><i style="width:' + pct + '%"></i></div><div class="use">' + esc(useLine) + '</div></div></div>' +
      '</aside>';
  }

  function mobileChromeHTML() {
    var tabs = [['mail', 'mail', t('folders') && (LANG === 'en' ? 'Mail' : 'البريد')], ['search', 'search', LANG === 'en' ? 'Search' : 'بحث'], ['clients', 'badge', t('clients')], ['settings', 'person', LANG === 'en' ? 'Account' : 'حسابي']];
    return '<button class="om-mobile-fab om-ib" id="om-fab">' + mi('edit_square') + '</button>' +
      '<div class="om-mobile-tabs">' + tabs.map(function (tb) {
        var active = (tb[0] === 'mail' && S.screen === 'inbox') || S.screen === tb[0];
        return '<button data-mtab="' + tb[0] + '" class="' + (active ? 'active' : '') + '">' + mi(tb[1]) + '<span class="l">' + esc(tb[2]) + '</span></button>';
      }).join('') + '</div>';
  }

  /* ---- shell wiring ---- */
  function wireTopbar() {
    on(q('#om-menu'), 'click', function () { toggleSide(true); });
    var si = q('#om-search-in');
    on(si, 'input', function () { S.query = si.value; });
    on(si, 'keydown', function (e) { if (e.key === 'Enter') { go('search'); doSearch(); } });
    on(si, 'focus', function () { if (S.screen !== 'search') { /* keep inbox until submit */ } });
    on(q('#om-theme'), 'click', function () { setTheme(S.theme === 'dark' ? 'light' : 'dark'); });
    on(q('#om-notif-btn'), 'click', function () { S.notifsOpen = !S.notifsOpen; S.acctOpen = false; paintShell(); });
    on(q('#om-notif-clr'), 'click', function () { S.notifs.forEach(function (n) { n.read = true; }); paintShell(); });
    on(q('#om-notif-refresh'), 'click', function () { refreshFolders(); pollNew(true); });
    qa('.om-nrow').forEach(function (r) { on(r, 'click', function () { var n = S.notifs[+r.getAttribute('data-ni')]; if (n) { n.read = true; } S.notifsOpen = false; go('inbox'); }); });
    on(q('#om-shortcuts'), 'click', openShortcuts);
    on(q('#om-settings'), 'click', function () { go('settings'); });
    on(q('#om-account'), 'click', function (e) { if (e.target.closest('.om-acct-menu')) return; S.acctOpen = !S.acctOpen; S.notifsOpen = false; paintShell(); });
    qa('.om-acct-menu button').forEach(function (b) {
      on(b, 'click', function (e) {
        e.stopPropagation(); var a = b.getAttribute('data-act'); S.acctOpen = false;
        if (a === 'settings') go('settings'); else if (a === 'stats') go('stats'); else if (a === 'logout') location.href = CFG.logout;
      });
    });
  }
  function wireSidebar() {
    on(q('#om-compose-btn'), 'click', function () { openCompose('new'); });
    qa('.om-fold').forEach(function (b) {
      on(b, 'click', function () {
        var fk = b.getAttribute('data-fk'), raw = b.getAttribute('data-raw');
        openFolder(fk, raw);
      });
    });
    qa('.om-label').forEach(function (b) { on(b, 'click', function () { openLabel(b.getAttribute('data-lslug')); }); });
    qa('.om-navlink').forEach(function (b) { on(b, 'click', function () { go(b.getAttribute('data-nav')); }); });
    on(q('#om-add-label'), 'click', function () { toast(LANG === 'en' ? 'Labels are managed here; open a message to apply one.' : 'التصنيفات تُدار هنا؛ افتح رسالة لتطبيق تصنيف.'); });
  }
  function wireMobileChrome() {
    on(q('#om-scrim'), 'click', function () { toggleSide(false); });
    on(q('#om-fab'), 'click', function () { openCompose('new'); });
    qa('.om-mobile-tabs button').forEach(function (b) { on(b, 'click', function () { var m = b.getAttribute('data-mtab'); go(m === 'mail' ? 'inbox' : m); }); });
  }
  function toggleSide(open) { S.sideOpen = open; var s = q('#om-side'), sc = q('#om-scrim'); if (s) s.classList.toggle('open', open); if (sc) sc.classList.toggle('open', open); }

  /* ============================ MAIN RENDER =========================== */
  function go(screen) { S.screen = screen; S.acctOpen = false; S.notifsOpen = false; toggleSide(false); if (screen === 'contacts' && !S.contacts.length) loadContacts(); if (screen === 'clients') loadClients(); if (screen === 'stats') loadStats(); paintShell(); }
  function paintMain() {
    var m = q('#om-main'); if (!m) return;
    if (S.screen === 'inbox') m.innerHTML = inboxHTML();
    else if (S.screen === 'search') m.innerHTML = searchHTML();
    else if (S.screen === 'contacts') m.innerHTML = contactsHTML();
    else if (S.screen === 'settings') m.innerHTML = settingsHTML();
    else if (S.screen === 'stats') m.innerHTML = statsHTML();
    else if (S.screen === 'clients') m.innerHTML = clientsHTML();
    else if (S.screen === 'composeFull') m.innerHTML = composeFullHTML();
    else m.innerHTML = inboxHTML();
    wireMain();
    ROOT.classList.toggle('reading', !!S.currentUid && window.innerWidth < 1000);
    if (S.screen === 'inbox' && S.currentMsg) fillReaderBody();
  }

  /* ------------------------------ INBOX ------------------------------- */
  function inboxHTML() {
    var showReader = window.innerWidth >= 1000;
    var listW = window.innerWidth >= 1400 ? '420px' : '372px';
    var listStyle = showReader ? ('width:' + listW + ';flex:0 0 ' + listW) : 'flex:1 1 auto';
    return listHTML(listStyle) + (showReader || S.currentUid ? readerHTML() : '');
  }
  function listHTML(style) {
    var selCount = Object.keys(S.sel).length;
    var allIcon = selCount && selCount === S.messages.length ? 'check_box' : (selCount ? 'indeterminate_check_box' : 'check_box_outline_blank');
    var unread = S.messages.filter(function (m) { return !m.seen; }).length;
    var filters = t('filters').map(function (nm, i) {
      return '<button class="om-chip' + (S.filter === i ? ' active' : '') + '" data-fi="' + i + '">' + esc(nm) + '</button>';
    }).join('');
    var rows = renderRows();
    var bulk = selCount ? bulkHTML(selCount) : '';
    var densCls = S.density === 'compact' ? 'compact' : (S.density === 'cozy' ? 'cozy' : '');
    return '<section class="om-list" style="' + style + '">' +
      '<div class="om-list-head">' +
        '<div class="om-list-top"><div class="left">' +
          '<button class="om-ib" id="om-selall" title="' + esc(t('selectAll')) + '" style="color:' + (selCount ? 'var(--brand3)' : 'var(--text3)') + '">' + mi(allIcon) + '</button>' +
          '<h2>' + esc(S.display || t('folders').inbox) + '</h2><span class="unread">' + unread + ' ' + esc(t('unreadWord')) + '</span></div>' +
          '<div class="tools">' +
            '<button class="om-icon-btn' + (S.group ? ' on' : '') + '" id="om-group" title="calendar">' + mi('calendar_view_day') + '</button>' +
            '<button class="om-icon-btn" id="om-refresh" title="refresh">' + mi('refresh') + '</button>' +
            '<button class="om-icon-btn" id="om-density" title="density">' + mi('density_medium') + '</button>' +
          '</div></div>' +
        '<div class="om-filters">' + filters +
          '<button class="om-sort" id="om-sort">' + mi('swap_vert') + '<span>' + esc(S.sort === 'newest' ? t('newest') : t('oldest')) + '</span></button></div>' +
      '</div>' + bulk +
      '<div class="om-rows" data-dens="' + densCls + '">' + (rows || emptyRowsHTML()) + '</div>' +
    '</section>';
  }
  function renderRows() {
    if (!S.messages.length) return '';
    var lastGroup = null;
    return S.messages.map(function (m) {
      var sender = m.from ? (m.from.name || m.from.email) : '';
      var densCls = S.density === 'compact' ? ' compact' : (S.density === 'cozy' ? ' cozy' : '');
      var groupHTML = '';
      if (S.group) {
        var g = groupLabel(m);
        if (g !== lastGroup) { groupHTML = '<div class="om-group-h"><span>' + esc(g) + '</span></div>'; lastGroup = g; }
      }
      var checked = !!S.sel[m.uid];
      var lbl = m.label ? LABEL_DEFS.filter(function (L) { return L.slug === m.label; })[0] : null;
      return groupHTML + '<div class="om-row' + densCls + (m.uid === S.currentUid ? ' sel' : '') + (m.seen ? '' : ' unread') + '" data-uid="' + m.uid + '">' +
        '<span class="selbar"></span>' +
        '<button class="om-check' + (checked ? ' on' : '') + '" data-check="' + m.uid + '">' + mi(checked ? 'check_box' : 'check_box_outline_blank') + '</button>' +
        avatarHTML(sender, m.from && m.from.email, 'av', 36) +
        '<div class="om-r-main">' +
          '<div class="om-r-top"><span class="om-r-sender">' + esc(sender) + '</span>' + (m.seen ? '' : '<span class="om-dot"></span>') + '<span class="om-r-time">' + esc(m.date_fmt || '') + '</span></div>' +
          '<div class="om-r-subject" dir="' + dirOf(m.subject) + '">' + esc(m.subject || (LANG === 'en' ? '(no subject)' : '(بدون موضوع)')) + '</div>' +
          '<div class="om-r-meta">' +
            (lbl ? '<span class="om-lbl-chip" style="color:' + lbl.color + ';border:1px solid ' + lbl.color + '">' + esc(labelName(lbl)) + '</span>' : '') +
            (m.has_attach ? mi('attach_file') : '') +
            (m.answered ? '<span class="thread">' + mi('reply') + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div class="om-r-side">' +
          '<button class="om-star' + (m.flagged ? ' on' : '') + '" data-star="' + m.uid + '">' + mi(m.flagged ? 'star' : 'star_outline') + '</button>' +
          '<button data-arch="' + m.uid + '" style="opacity:.55">' + mi('archive') + '</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }
  function groupLabel(m) {
    if (!m.ts) return LANG === 'en' ? 'Earlier' : 'أقدم';
    var d = new Date(m.ts * 1000), now = new Date();
    var sameDay = d.toDateString() === now.toDateString();
    var y = new Date(now.getTime() - 86400000);
    if (sameDay) return LANG === 'en' ? 'Today' : 'اليوم';
    if (d.toDateString() === y.toDateString()) return LANG === 'en' ? 'Yesterday' : 'أمس';
    return LANG === 'en' ? 'Earlier' : 'أقدم';
  }
  function emptyRowsHTML() {
    if (S.loading) return '<div class="om-empty"><span class="om-spin" style="margin:0 auto 10px"></span></div>';
    return '<div class="om-empty">' + mi('inbox') + '<div class="h">' + esc(t('noMailFolder')) + '</div></div>';
  }
  function bulkHTML(n) {
    var acts = [['read', 'drafts'], ['star', 'star'], ['archive', 'archive'], ['delete', 'delete']];
    return '<div class="om-bulk"><span class="txt">' + n + ' ' + esc(LANG === 'en' ? 'selected' : 'محدّدة') + '</span><div class="acts">' +
      acts.map(function (a) { return '<button data-bulk="' + a[0] + '">' + mi(a[1]) + '</button>'; }).join('') +
      '<button data-bulk="clear">' + mi('close') + '</button></div></div>';
  }

  /* ------------------------------ READER ------------------------------ */
  function readerHTML() {
    if (!S.currentUid) return '<section class="om-reader"><div class="om-reader-empty">' + mi('mail') + '<div>' + esc(LANG === 'en' ? 'Select a message to read' : 'اختر رسالة لقراءتها') + '</div></div></section>';
    var m = S.currentMsg;
    var idx = S.messages.map(function (x) { return x.uid; }).indexOf(S.currentUid);
    var counter = (idx >= 0 ? idx + 1 : 1) + ' ' + (LANG === 'en' ? 'of' : 'من') + ' ' + Math.max(S.messages.length, 1);
    var actions = [['replyall', 'reply_all', t('replyAll')], ['forward', 'forward', t('forward')], ['archive', 'archive', t('archive')], ['snooze', 'schedule', t('snooze')], ['label', 'label', t('label')], ['unread', 'mark_email_unread', t('markUnread')], ['delete', 'delete', t('del')]];
    var bar = '<div class="om-reader-bar">' +
      '<button class="om-back-btn om-ib" id="om-back">' + mi('arrow_forward') + '</button>' +
      '<button class="om-reply-btn" id="om-reply">' + mi('reply') + '<span>' + esc(t('reply')) + '</span></button>' +
      actions.map(function (a) { return '<button class="om-ract" data-ract="' + a[0] + '" title="' + esc(a[2]) + '">' + mi(a[1]) + '</button>'; }).join('') +
      '<div class="om-reader-nav"><div class="om-zoom"><button class="b" id="om-zout">' + mi('remove') + '</button><button class="pct" id="om-zreset">' + Math.round(S.zoom * 100) + '%</button><button class="b" id="om-zin">' + mi('add') + '</button></div>' +
        '<span class="om-num">' + counter + '</span>' +
        '<button class="nb" id="om-prev">' + mi('chevron_right') + '</button><button class="nb" id="om-next">' + mi('chevron_left') + '</button></div>' +
    '</div>';
    if (!m) return '<section class="om-reader" id="om-reader">' + bar + '<div class="om-reader-empty"><span class="om-spin"></span></div></section>';
    var sender = m.from ? (m.from.name || m.from.email) : '';
    var toChips = (m.to || []).slice(0, 4).map(function (a) { return '<span class="chip">' + esc(a.email) + '</span>'; }).join('');
    var ccChips = (m.cc || []).slice(0, 4).map(function (a) { return '<span class="chip">' + esc(a.email) + '</span>'; }).join('');
    var extra = ((m.to || []).length + (m.cc || []).length) - Math.min((m.to || []).length, 4);
    var isClient = S.clients.some(function (c) { return c.email && m.from && c.email.toLowerCase() === (m.from.email || '').toLowerCase(); });
    var atts = (m.attachments || []).filter(function (a) { return !a.inline; });
    var attHTML = atts.length ? '<div class="om-atts">' + atts.map(function (a) {
      return '<button class="om-att" data-att="' + a.index + '" data-name="' + esc(a.name) + '">' + mi(attIcon(a.mime, a.name)) + '<div><div class="nm">' + esc(a.name) + '</div><div class="sz">' + fmtBytes(a.size) + '</div></div></button>';
    }).join('') + '<span class="count">' + atts.length + ' ' + esc(LANG === 'en' ? 'attachment(s)' : 'مرفق') + '</span></div>' : '';
    var labelCur = m.label ? LABEL_DEFS.filter(function (L) { return L.slug === m.label; })[0] : null;
    return '<section class="om-reader" id="om-reader">' + bar +
      '<div class="om-reader-body"><div class="om-reader-w">' +
        '<div class="om-msg-head"><div style="flex:1">' +
          '<div class="om-msg-tags">' + (labelCur ? '<span class="om-lbl-chip" style="color:' + labelCur.color + ';border:1px solid ' + labelCur.color + '">' + esc(labelName(labelCur)) + '</span>' : '') + '</div>' +
          '<h1 class="om-msg-title" dir="' + dirOf(m.subject) + '" style="font-size:' + (21 * S.zoom).toFixed(1) + 'px">' + esc(m.subject || (LANG === 'en' ? '(no subject)' : '(بدون موضوع)')) + '</h1>' +
        '</div>' +
        '<div class="om-msg-tools">' +
          '<button id="om-star-open" title="' + esc(t('label')) + '">' + mi(m.flagged ? 'star' : 'star_outline') + (m.flagged ? '' : '') + '</button>' +
          '<button id="om-print" title="' + esc(t('print')) + '">' + mi('print') + '</button>' +
          '<button class="om-save-client' + (isClient ? ' saved' : '') + '" id="om-save-client">' + mi(isClient ? 'how_to_reg' : 'person_add') + '<span>' + esc(isClient ? t('savedClient') : t('saveClient')) + '</span></button>' +
        '</div></div>' +
        '<article class="om-card">' +
          '<div class="om-card-head">' + avatarHTML(sender, m.from && m.from.email, 'av', 42) +
            '<div class="who"><div class="top"><span class="nm">' + esc(sender) + '</span><span class="em">' + esc(m.from && m.from.email) + '</span><span class="dt">' + esc(m.date_fmt || m.date || '') + '</span></div>' +
              '<div class="om-recip"><span class="lbl">' + esc(t('to')) + ':</span>' + toChips + (ccChips ? '<span class="lbl" style="margin-inline-start:8px">' + esc(t('cc')) + ':</span>' + ccChips : '') +
                (extra > 0 ? '<button class="more" id="om-more-recip">' + (S.showRecipients ? esc(t('hideRecip')) : ('+' + extra)) + '</button>' : '') + '</div>' +
              (S.showRecipients ? '<div class="om-recip-all">' + (m.to || []).concat(m.cc || []).map(function (a) { return esc(a.email); }).join(' · ') + '</div>' : '') +
            '</div></div>' +
          '<div class="om-mail-body" id="om-mail-body"></div>' + attHTML +
        '</article>' +
        smartRepliesHTML() +
        '<div class="om-reply-box" id="om-reply-box">' + mi('reply') + '<span>' + esc(t('writeReplyTo')) + ' ' + esc(sender) + '…</span></div>' +
      '</div></div>' +
    '</section>';
  }
  function smartRepliesHTML() {
    var chips = LANG === 'en'
      ? ['Noted, thank you', 'Will do today', 'Please send a signed PDF']
      : ['تم الاطلاع، شكرًا لكم', 'سأنفّذ ذلك اليوم', 'أرجو تزويدي بنسخة PDF معتمدة'];
    return '<div class="om-smart">' + chips.map(function (c) { return '<button class="om-smart-chip" data-smart="' + esc(c) + '">' + esc(c) + '</button>'; }).join('') + '</div>';
  }
  function fillReaderBody() {
    var host = q('#om-mail-body'); if (!host || !S.currentMsg) return;
    var m = S.currentMsg;
    if (m.html) {
      var iframe = document.createElement('iframe');
      iframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
      var css = 'html,body{margin:0}body{font-family:Cairo,system-ui,sans-serif;color:' + (S.theme === 'dark' ? '#dbe6eb' : '#22333c') + ';background:transparent;padding:26px 30px 30px;line-height:2.15;word-wrap:break-word;overflow-x:auto;font-size:' + (14.5 * S.zoom).toFixed(1) + 'px}img{max-width:100%;height:auto}a{color:#1683bd}table{max-width:100%}blockquote{border-inline-start:3px solid rgba(22,131,189,.42);padding-inline-start:14px;color:#8a9ca5;margin:8px 0}';
      iframe.srcdoc = '<!doctype html><html dir="' + dirOf(m.subject) + '"><head><meta charset="utf-8"><base target="_blank"><style>' + css + '</style></head><body>' + m.html + '</body></html>';
      host.innerHTML = ''; host.appendChild(iframe);
      on(iframe, 'load', function () {
        function fit() { try { var d = iframe.contentDocument; iframe.style.height = (Math.max(d.body.scrollHeight, d.documentElement.scrollHeight) + 30) + 'px'; } catch (e) { iframe.style.height = '600px'; } }
        fit(); try { var imgs = iframe.contentDocument.images || []; for (var i = 0; i < imgs.length; i++) { if (!imgs[i].complete) { on(imgs[i], 'load', fit); on(imgs[i], 'error', fit); } } } catch (e) {}
        setTimeout(fit, 250); setTimeout(fit, 900); setTimeout(fit, 1800);
      });
    } else {
      host.innerHTML = '<div class="om-mail-plain" dir="' + dirOf(m.text) + '" style="font-size:' + (14.5 * S.zoom).toFixed(1) + 'px">' + esc(m.text || '') + '</div>';
    }
  }
  function attIcon(mime, name) {
    mime = (mime || '').toLowerCase(); name = (name || '').toLowerCase();
    if (/pdf/.test(mime) || /\.pdf$/.test(name)) return 'picture_as_pdf';
    if (/image\//.test(mime) || /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name)) return 'image';
    if (/(zip|rar|7z|compressed)/.test(mime) || /\.(zip|rar|7z)$/.test(name)) return 'folder_zip';
    if (/(sheet|excel|csv)/.test(mime) || /\.(xlsx?|csv)$/.test(name)) return 'table_view';
    if (/(word|document)/.test(mime) || /\.docx?$/.test(name)) return 'description';
    if (/message\/rfc822|\.eml$/.test(mime + name)) return 'mail';
    return 'attach_file';
  }

  /* ---- inbox wiring ---- */
  function wireMain() {
    if (S.screen === 'inbox') wireList();
    else if (S.screen === 'search') wireSearch();
    else if (S.screen === 'contacts') wireContacts();
    else if (S.screen === 'settings') wireSettings();
    else if (S.screen === 'stats') { }
    else if (S.screen === 'clients') wireClients();
    else if (S.screen === 'composeFull') wireComposeFull();
  }
  function wireList() {
    on(q('#om-selall'), 'click', function () {
      var all = S.messages.length && Object.keys(S.sel).length === S.messages.length;
      S.sel = {}; if (!all) S.messages.forEach(function (m) { S.sel[m.uid] = true; }); paintMain();
    });
    on(q('#om-group'), 'click', function () { S.group = !S.group; paintMain(); });
    on(q('#om-refresh'), 'click', function () { loadMessages(); refreshFolders(); });
    on(q('#om-density'), 'click', function () { setDensity(S.density === 'medium' ? 'compact' : (S.density === 'compact' ? 'cozy' : 'medium')); });
    on(q('#om-sort'), 'click', function () { S.sort = S.sort === 'newest' ? 'oldest' : 'newest'; loadMessages(); });
    qa('.om-chip[data-fi]').forEach(function (b) { on(b, 'click', function () { S.filter = +b.getAttribute('data-fi'); loadMessages(); }); });
    qa('.om-row').forEach(function (r) {
      on(r, 'click', function (e) {
        if (e.target.closest('[data-check]') || e.target.closest('[data-star]') || e.target.closest('[data-arch]')) return;
        openMessage(+r.getAttribute('data-uid'));
      });
    });
    qa('[data-check]').forEach(function (b) { on(b, 'click', function (e) { e.stopPropagation(); var u = +b.getAttribute('data-check'); if (S.sel[u]) delete S.sel[u]; else S.sel[u] = true; paintMain(); }); });
    qa('[data-star]').forEach(function (b) { on(b, 'click', function (e) { e.stopPropagation(); toggleStar(+b.getAttribute('data-star')); }); });
    qa('[data-arch]').forEach(function (b) { on(b, 'click', function (e) { e.stopPropagation(); archiveMsg(+b.getAttribute('data-arch')); }); });
    qa('[data-bulk]').forEach(function (b) { on(b, 'click', function () { bulkAction(b.getAttribute('data-bulk')); }); });
    // reader
    on(q('#om-back'), 'click', function () { S.currentUid = 0; S.currentMsg = null; ROOT.classList.remove('reading'); paintMain(); });
    on(q('#om-reply'), 'click', function () { if (S.currentMsg) openCompose('reply', S.currentMsg); });
    on(q('#om-reply-box'), 'click', function () { if (S.currentMsg) openCompose('reply', S.currentMsg); });
    qa('[data-ract]').forEach(function (b) { on(b, 'click', function () { readerAction(b.getAttribute('data-ract')); }); });
    qa('[data-smart]').forEach(function (b) { on(b, 'click', function () { openCompose('reply', S.currentMsg, b.getAttribute('data-smart')); }); });
    on(q('#om-star-open'), 'click', function () { if (S.currentMsg) toggleStar(S.currentMsg.uid); });
    on(q('#om-print'), 'click', function () { toast(LANG === 'en' ? 'Preparing print view…' : 'جارٍ تحضير نسخة للطباعة'); window.print(); });
    on(q('#om-save-client'), 'click', saveCurrentClient);
    on(q('#om-more-recip'), 'click', function () { S.showRecipients = !S.showRecipients; paintMain(); });
    qa('[data-att]').forEach(function (b) { on(b, 'click', function () { downloadAtt(+b.getAttribute('data-att'), b.getAttribute('data-name')); }); });
    on(q('#om-zin'), 'click', function () { setZoom(S.zoom + 0.1); });
    on(q('#om-zout'), 'click', function () { setZoom(S.zoom - 0.1); });
    on(q('#om-zreset'), 'click', function () { setZoom(1); });
    on(q('#om-prev'), 'click', function () { moveMsg(-1); });
    on(q('#om-next'), 'click', function () { moveMsg(1); });
  }

  /* ----------------------------- SEARCH ------------------------------- */
  function searchHTML() {
    var fields = [['from', t('to') === 'إلى' ? 'من' : 'From', LANG === 'en' ? 'sender name or email' : 'اسم أو بريد المرسل'], ['to', t('to'), LANG === 'en' ? 'recipient' : 'المستلم'], ['words', LANG === 'en' ? 'Words' : 'الكلمات', LANG === 'en' ? 'circular, invoice…' : 'تعميم، فاتورة…'], ['folder', LANG === 'en' ? 'Folder' : 'المجلد', LANG === 'en' ? 'all folders' : 'كل المجلدات']];
    var chips = [['attach', 'attach_file', LANG === 'en' ? 'Has attachment' : 'له مرفق'], ['recent', 'date_range', LANG === 'en' ? 'Last 30 days' : 'آخر 30 يومًا'], ['unread', 'mark_email_unread', LANG === 'en' ? 'Unread' : 'غير مقروء']];
    var results = (S.searchResults || []).map(function (m) {
      var sender = m.from ? (m.from.name || m.from.email) : '';
      return '<div class="om-result" data-suid="' + m.uid + '"><div class="who">' + avatarHTML(sender, m.from && m.from.email, 'av', 28) + '<span class="om-nowrap">' + esc(sender) + '</span></div>' +
        '<div style="min-width:0"><div class="subj om-nowrap">' + esc(m.subject) + '</div></div>' +
        '<span class="fold">' + esc(S.display || t('folders').inbox) + '</span><span class="dt">' + esc(m.date_fmt || '') + '</span></div>';
    }).join('');
    return '<div class="om-page"><div class="om-page-w">' +
      '<div class="om-page-h"><h2>' + esc(t('searchTitle')) + '</h2><span class="meta">' + (S.searchResults ? (S.searchResults.length + ' ' + (LANG === 'en' ? 'results' : 'نتيجة')) : '') + '</span><button class="om-btn" style="margin-inline-start:auto" data-back="inbox">' + esc(t('backInbox')) + '</button></div>' +
      '<div class="om-fcard" style="margin-bottom:16px"><div class="om-grid4">' +
        fields.map(function (f) { return '<label class="om-field"><span>' + esc(f[1]) + '</span><input data-sf="' + f[0] + '" placeholder="' + esc(f[2]) + '"></label>'; }).join('') +
      '</div><div class="om-search-chips">' +
        chips.map(function (c) { return '<button class="om-schip" data-schip="' + c[0] + '">' + mi(c[1]) + esc(c[2]) + '</button>'; }).join('') +
        '<button class="om-btn primary" style="margin-inline-start:auto" id="om-do-search">' + esc(t('searchBtn')) + '</button>' +
        '<button class="om-btn" id="om-save-search">' + esc(t('saveSearch')) + '</button>' +
      '</div></div>' +
      '<div class="om-results">' + (results || '<div class="om-empty" style="padding:40px">' + mi('search') + '<div class="h">' + esc(S.searchResults ? t('noResults') : (LANG === 'en' ? 'Type and search' : 'اكتب ثم ابحث')) + '</div></div>') + '</div>' +
    '</div></div>';
  }
  function wireSearch() {
    qa('[data-back]').forEach(function (b) { on(b, 'click', function () { go('inbox'); }); });
    var wf = q('[data-sf="words"]'); if (wf) { wf.value = S.query; on(wf, 'keydown', function (e) { if (e.key === 'Enter') doSearch(); }); }
    qa('[data-schip]').forEach(function (b) { on(b, 'click', function () { b.classList.toggle('on'); }); });
    on(q('#om-do-search'), 'click', doSearch);
    on(q('#om-save-search'), 'click', function () { toast(LANG === 'en' ? 'Smart search saved' : 'تم حفظ البحث الذكي'); });
    qa('[data-suid]').forEach(function (r) { on(r, 'click', function () { S.currentUid = 0; go('inbox'); openMessage(+r.getAttribute('data-suid')); }); });
  }
  function doSearch() {
    var wf = q('[data-sf="words"]'); var term = wf ? wf.value.trim() : S.query;
    S.query = term; S.searchResults = null; paintMain();
    api('messages', { query: { folder: S.folder, page: 0, search: term } }).then(function (r) {
      (r.messages || []).forEach(function (m) { m.date_fmt = m.date_fmt || ''; });
      S.searchResults = r.messages || []; if (S.screen === 'search') paintMain();
    }).catch(function (e) { toast(e.message, 'err'); });
  }

  /* ---------------------------- CONTACTS ------------------------------ */
  function contactsHTML() {
    var cards = S.contacts.map(function (c) {
      var nm = (c.name && c.name.toLowerCase() !== (c.email || '').toLowerCase()) ? c.name : (c.email || '');
      return '<div class="om-contact-card"><div class="top">' + avatarHTML(c.name, c.email, 'av', 42) +
        '<div style="min-width:0"><div class="nm">' + esc(nm) + '</div>' + (c.role ? '<div class="role">' + esc(c.role) + '</div>' : '') + '</div></div>' +
        '<div class="em">' + esc(c.email) + '</div>' +
        '<div class="om-card-actions"><button class="msg" data-mail="' + esc(c.email) + '">' + esc(t('message')) + '</button>' +
        '<button class="ic" data-call="' + esc(c.name) + '">' + mi('call') + '</button></div></div>';
    }).join('');
    return '<div class="om-page"><div class="om-page-w">' +
      '<div class="om-page-h"><h2>' + esc(t('contactsTitle')) + '</h2><span class="meta">' + S.contacts.length + ' ' + esc(LANG === 'en' ? 'contacts' : 'جهة') + '</span>' +
        '<button class="om-btn primary" style="margin-inline-start:auto" id="om-add-contact">' + esc(t('addContact')) + '</button>' +
        '<button class="om-btn" data-back="inbox">' + esc(t('backInbox')) + '</button></div>' +
      '<div id="om-add-contact-form"></div>' +
      '<div class="om-cards-grid">' + (cards || '<div class="om-dashed-empty" style="grid-column:1/-1">' + mi('group') + '<div>' + esc(LANG === 'en' ? 'No contacts yet' : 'لا جهات اتصال بعد') + '</div></div>') + '</div>' +
    '</div></div>';
  }
  function wireContacts() {
    qa('[data-back]').forEach(function (b) { on(b, 'click', function () { go('inbox'); }); });
    qa('[data-mail]').forEach(function (b) { on(b, 'click', function () { openCompose('new', null, null, b.getAttribute('data-mail')); }); });
    qa('[data-call]').forEach(function (b) { on(b, 'click', function () { toast((LANG === 'en' ? 'Call ' : 'اتصال بـ ') + b.getAttribute('data-call')); }); });
    on(q('#om-add-contact'), 'click', function () {
      var host = q('#om-add-contact-form');
      if (host.innerHTML) { host.innerHTML = ''; return; }
      host.innerHTML = '<div class="om-fcard" style="margin-bottom:14px;border-color:var(--brandLine)"><div class="ct">' + esc(LANG === 'en' ? 'New contact' : 'جهة اتصال جديدة') + '</div>' +
        '<div class="om-grid3"><label class="om-field"><span>' + esc(t('addName')) + '</span><input id="nc-name"></label>' +
        '<label class="om-field"><span>' + esc(t('roleLabel')) + '</span><input id="nc-role"></label>' +
        '<label class="om-field"><span>' + esc(t('email')) + '</span><input id="nc-email" dir="ltr"></label></div>' +
        '<div style="display:flex;gap:8px;margin-top:14px"><button class="om-btn primary" id="nc-save">' + esc(t('add')) + '</button><button class="om-btn" id="nc-cancel">' + esc(t('cancel')) + '</button></div></div>';
      on(q('#nc-cancel'), 'click', function () { host.innerHTML = ''; });
      on(q('#nc-save'), 'click', function () {
        var name = q('#nc-name').value.trim(), role = q('#nc-role').value.trim(), email = q('#nc-email').value.trim();
        if (!name) { toast(LANG === 'en' ? 'Enter a name' : 'أدخل الاسم', 'err'); return; }
        api('contact-add', { body: { name: name, role: role, email: email } }).then(function () {
          toast(LANG === 'en' ? 'Contact added' : 'تمت إضافة الجهة'); host.innerHTML = ''; loadContacts(true);
        }).catch(function (e) { toast(e.message, 'err'); });
      });
    });
  }

  /* ----------------------------- CLIENTS ------------------------------ */
  function clientsHTML() {
    var cards = S.clients.map(function (c, i) {
      return '<div class="om-client-card"><div class="top">' + avatarHTML(c.name, c.email, 'av', 42) +
        '<div style="min-width:0;flex:1"><div class="nm">' + esc(c.name) + '</div><div class="em">' + esc(c.email) + '</div></div>' +
        '<span class="om-client-tag">' + esc(t('clientTag')) + '</span></div>' +
        '<div class="meta"><span>' + esc(t('savedOn')) + ' ' + esc(c.added || '') + '</span><span>' + (c.msgs || 1) + ' ' + esc(t('msgsWord')) + '</span></div>' +
        '<div class="om-card-actions"><button class="msg" data-cmail="' + esc(c.email) + '">' + esc(t('message')) + '</button>' +
        '<button class="ic danger" data-crm="' + i + '" title="' + esc(t('remove')) + '">' + mi('person_remove') + '</button></div></div>';
    }).join('');
    return '<div class="om-page"><div class="om-page-w">' +
      '<div class="om-page-h" style="margin-bottom:6px"><h2>' + esc(t('clients')) + '</h2><span class="meta">' + S.clients.length + ' ' + esc(LANG === 'en' ? 'saved' : 'عميل محفوظ') + '</span>' +
        '<button class="om-btn" style="margin-inline-start:auto" data-back="inbox">' + esc(t('backInbox')) + '</button></div>' +
      '<p class="om-clients-hint">' + esc(t('clientsHint')) + '</p>' +
      (S.clients.length ? '<div class="om-cards-grid">' + cards + '</div>' : '<div class="om-dashed-empty">' + mi('badge') + '<div>' + esc(t('clientsEmpty')) + '</div></div>') +
    '</div></div>';
  }
  function wireClients() {
    qa('[data-back]').forEach(function (b) { on(b, 'click', function () { go('inbox'); }); });
    qa('[data-cmail]').forEach(function (b) { on(b, 'click', function () { openCompose('new', null, null, b.getAttribute('data-cmail')); }); });
    qa('[data-crm]').forEach(function (b) { on(b, 'click', function () { var c = S.clients[+b.getAttribute('data-crm')]; if (!c) return; api('client-remove', { body: { email: c.email } }).then(function () { toast(LANG === 'en' ? 'Client removed' : 'تم حذف العميل'); loadClients(true); }).catch(function (e) { toast(e.message, 'err'); }); }); });
  }

  /* ------------------------------ STATS ------------------------------- */
  function statsHTML() {
    var st = S.stats || {};
    var kpis = [[t('kpis')[0], st.received != null ? st.received : '—', ''], [t('kpis')[1], st.sent != null ? st.sent : '—', ''], [t('kpis')[2], st.unread != null ? st.unread : '—', ''], [t('kpis')[3], st.awaiting != null ? st.awaiting : '—', '']];
    var bars = (st.folders || []).slice(0, 8);
    var max = bars.reduce(function (a, b) { return Math.max(a, b.count || 0); }, 1);
    return '<div class="om-page"><div class="om-page-w">' +
      '<div class="om-page-h"><h2>' + esc(t('statsTitle')) + '</h2><span class="meta">' + esc(t('last30')) + '</span><button class="om-btn" style="margin-inline-start:auto" data-back="inbox">' + esc(t('backInbox')) + '</button></div>' +
      '<div class="om-grid4" style="margin-bottom:16px">' + kpis.map(function (k) { return '<div class="om-kpi"><div class="l">' + esc(k[0]) + '</div><div class="v">' + esc(String(k[1])) + '</div></div>'; }).join('') + '</div>' +
      '<div class="om-fcard"><div class="ct" style="margin-bottom:16px">' + esc(t('correspondence')) + '</div><div style="display:flex;flex-direction:column;gap:12px">' +
        (bars.length ? bars.map(function (b) { return '<div class="om-bar-row"><span class="nm">' + esc(b.name) + '</span><div class="track"><i style="width:' + Math.round((b.count || 0) / max * 100) + '%"></i></div><span class="v">' + (b.count || 0) + '</span></div>'; }).join('') : '<div style="color:var(--text3);font-size:13px">—</div>') +
      '</div></div>' +
    '</div></div>';
  }

  /* ----------------------------- SETTINGS ----------------------------- */
  function settingsHTML() {
    var tabs = [['appearance', t('appearance')], ['account', t('account')], ['signature', t('signature')], ['language', t('language')]];
    var tabBar = '<div class="om-tabs">' + tabs.map(function (tb) { return '<button class="om-tab' + (S.settingsTab === tb[0] ? ' active' : '') + '" data-stab="' + tb[0] + '">' + esc(tb[1]) + '</button>'; }).join('') + '</div>';
    var body = '';
    if (S.settingsTab === 'appearance') body = settingsAppearance();
    else if (S.settingsTab === 'account') body = settingsAccount();
    else if (S.settingsTab === 'signature') body = settingsSignature();
    else if (S.settingsTab === 'language') body = settingsLanguage();
    return '<div class="om-page"><div class="om-page-w" style="max-width:980px">' +
      '<div class="om-page-h"><h2>' + esc(t('settings')) + '</h2><button class="om-btn" style="margin-inline-start:auto" data-back="inbox">' + esc(t('backInbox')) + '</button></div>' +
      tabBar + '<div class="om-fcol">' + body + '</div></div></div>';
  }
  function settingsAppearance() {
    var themes = [['dark', '#111c22', '#1683bd'], ['light', '#ffffff', '#0a5c86']];
    var dens = ['cozy', 'medium', 'compact'];
    var toggles = LANG === 'en'
      ? [['Reading pane', 'Show message beside the list'], ['Group conversations', 'Merge replies into a thread'], ['Undo send', '10s window to unsend'], ['Desktop notifications', 'Instant alerts for important mail'], ['Smart replies', 'Quick suggestions under a message']]
      : [['نافذة معاينة القراءة', 'عرض الرسالة بجانب القائمة'], ['تجميع المحادثات', 'دمج الردود في خيط واحد'], ['إلغاء الإرسال بعد الضغط', 'مهلة 10 ثوانٍ لاستدراك الرسالة'], ['إشعارات سطح المكتب', 'تنبيه فوري للرسائل المهمة'], ['الردود الذكية المقترحة', 'اقتراحات أسفل الرسالة']];
    return '<div class="om-fcard"><div class="ct">' + esc(t('appearance')) + '</div><div class="cd">' + esc(t('appearanceDesc')) + '</div>' +
      '<div style="display:flex;gap:22px;flex-wrap:wrap"><div style="display:flex;gap:9px">' +
        themes.map(function (o) { return '<button class="om-theme-opt' + (S.theme === o[0] ? ' active' : '') + '" data-theme="' + o[0] + '"><div class="bar" style="background:' + o[2] + '"></div><span class="nm">' + esc(t('themeName')[o[0]]) + '</span></button>'; }).join('') +
      '</div><div style="display:flex;flex-direction:column;gap:8px;min-width:240px">' +
        '<span style="font-size:11px;font-weight:700;color:var(--text3)">' + esc(t('density')) + '</span><div class="om-seg">' +
          dens.map(function (d) { return '<button class="' + (S.density === d ? 'active' : '') + '" data-dens="' + d + '">' + esc(t('densityNames')[d]) + '</button>'; }).join('') + '</div>' +
        '<span style="font-size:11px;font-weight:700;color:var(--text3);margin-top:8px">' + esc(t('textSize')) + '</span>' +
        '<div class="om-zoom-ctl"><button data-z="-1">' + mi('text_decrease') + '</button><span class="pct">' + Math.round(S.zoom * 100) + '%</span><button data-z="1">' + mi('text_increase') + '</button><button class="om-btn" style="height:34px" data-z="0">' + esc(t('reset')) + '</button></div>' +
      '</div></div></div>' +
      '<div class="om-fcard"><div class="ct" style="margin-bottom:14px">' + esc(t('mailOptions')) + '</div>' +
        toggles.map(function (tg, i) { return '<div class="om-toggle-row"><div class="info"><div class="nm">' + esc(tg[0]) + '</div><div class="desc">' + esc(tg[1]) + '</div></div><button class="om-switch' + (S.toggleState[i] ? ' on' : '') + '" data-tg="' + i + '"><i></i></button></div>'; }).join('') +
      '</div>';
  }
  function settingsAccount() {
    return '<div class="om-fcard"><div class="ct">' + esc(LANG === 'en' ? 'Account details' : 'بيانات الحساب') + '</div><div class="cd">' + esc(LANG === 'en' ? 'Shown to recipients inside the company' : 'تظهر هذه البيانات للمستلمين داخل الشركة') + '</div>' +
      '<div class="om-grid2"><label class="om-field"><span>' + esc(t('displayName')) + '</span><input id="ac-name" value="' + esc(S.from_name || S.name) + '"></label>' +
      '<label class="om-field"><span>' + esc(t('email')) + '</span><input value="' + esc(S.email) + '" dir="ltr" readonly></label></div>' +
      '<div style="display:flex;gap:8px;margin-top:16px"><button class="om-btn primary" id="ac-save">' + esc(LANG === 'en' ? 'Save changes' : 'حفظ التغييرات') + '</button></div>' +
      '</div>' +
      '<div class="om-fcard"><div class="ct" style="margin-bottom:12px">' + esc(t('changePw')) + '</div><div class="om-pw-row">' +
        '<input type="password" class="om-input" id="pw-cur" placeholder="' + esc(t('curPw')) + '" autocomplete="current-password">' +
        '<input type="password" class="om-input" id="pw-new" placeholder="' + esc(t('newPw')) + '" autocomplete="new-password">' +
        '<input type="password" class="om-input" id="pw-conf" placeholder="' + esc(t('confPw')) + '" autocomplete="new-password">' +
        '<button class="om-btn primary" id="pw-btn" style="align-self:flex-start">' + esc(t('doChangePw')) + '</button></div></div>';
  }
  function settingsSignature() {
    return '<div class="om-fcard"><div class="ct">' + esc(t('signature')) + '</div><div class="cd">' + esc(t('signatureDesc')) + '</div>' +
      '<textarea class="om-input" id="sig-text" style="min-height:120px" placeholder="' + esc(LANG === 'en' ? 'Your signature…' : 'توقيعك…') + '">' + esc(S.signature) + '</textarea>' +
      '<div style="display:flex;gap:8px;margin-top:14px"><button class="om-btn primary" id="sig-save">' + esc(t('saveSig')) + '</button></div></div>';
  }
  function settingsLanguage() {
    var langs = [['ar', 'العربية', LANG === 'en' ? 'Arabic interface, right-to-left.' : 'القوائم والأزرار بالعربية، والتخطيط من اليمين إلى اليسار.', 'language'], ['en', 'English', LANG === 'en' ? 'English interface, left-to-right.' : 'القوائم والأزرار بالإنجليزية، من اليسار إلى اليمين.', 'translate']];
    var cl = [['auto', t('auto')], ['ar', 'العربية'], ['en', 'English']];
    return '<div class="om-fcard"><div class="ct">' + esc(t('langTitle')) + '</div><div class="cd">' + esc(LANG === 'en' ? 'Pick one language — page direction follows automatically.' : 'اختر لغة واحدة — يتغير معها اتجاه الصفحة تلقائيًا.') + '</div>' +
      '<div style="display:flex;gap:10px;flex-wrap:wrap">' + langs.map(function (l) { return '<button class="om-lang-opt' + (LANG === l[0] ? ' active' : '') + '" data-lang="' + l[0] + '"><div class="top">' + mi(l[3]) + '<span class="nm">' + esc(l[1]) + '</span></div><div class="desc">' + esc(l[2]) + '</div></button>'; }).join('') + '</div>' +
      '<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line);display:flex;align-items:center;gap:14px;flex-wrap:wrap"><div style="flex:1"><div style="font-size:13px;font-weight:600">' + esc(t('composeLangName')) + '</div><div style="font-size:11.5px;color:var(--text3);margin-top:2px">' + esc(t('composeLangDesc')) + '</div></div>' +
        '<div class="om-seg">' + cl.map(function (o) { return '<button class="' + (S.composeLang === o[0] ? 'active' : '') + '" data-clang="' + o[0] + '">' + esc(o[1]) + '</button>'; }).join('') + '</div></div></div>';
  }
  function wireSettings() {
    qa('[data-back]').forEach(function (b) { on(b, 'click', function () { go('inbox'); }); });
    qa('[data-stab]').forEach(function (b) { on(b, 'click', function () { S.settingsTab = b.getAttribute('data-stab'); paintMain(); }); });
    qa('[data-theme]').forEach(function (b) { on(b, 'click', function () { setTheme(b.getAttribute('data-theme')); }); });
    qa('[data-dens]').forEach(function (b) { on(b, 'click', function () { setDensity(b.getAttribute('data-dens')); }); });
    qa('[data-z]').forEach(function (b) { on(b, 'click', function () { var z = +b.getAttribute('data-z'); setZoom(z === 0 ? 1 : S.zoom + z * 0.1); }); });
    qa('[data-tg]').forEach(function (b) { on(b, 'click', function () { var i = +b.getAttribute('data-tg'); S.toggleState[i] = !S.toggleState[i]; b.classList.toggle('on', S.toggleState[i]); }); });
    qa('[data-lang]').forEach(function (b) { on(b, 'click', function () { setLang(b.getAttribute('data-lang')); }); });
    qa('[data-clang]').forEach(function (b) { on(b, 'click', function () { S.composeLang = b.getAttribute('data-clang'); paintMain(); }); });
    on(q('#ac-save'), 'click', function () {
      var nm = q('#ac-name').value.trim();
      api('profile', { body: { from_name: nm, signature: S.signature } }).then(function (r) { S.from_name = r.from_name || nm; toast(t('saved')); paintShell(); }).catch(function (e) { toast(e.message, 'err'); });
    });
    on(q('#sig-save'), 'click', function () {
      var sig = q('#sig-text').value;
      api('profile', { body: { from_name: S.from_name, signature: sig } }).then(function (r) { S.signature = r.signature; toast(t('saved')); }).catch(function (e) { toast(e.message, 'err'); });
    });
    on(q('#pw-btn'), 'click', function () {
      var cur = q('#pw-cur').value, nw = q('#pw-new').value, cf = q('#pw-conf').value;
      if (!cur) { toast(LANG === 'en' ? 'Enter current password' : 'أدخل كلمة المرور الحالية', 'err'); return; }
      if (nw.length < 8) { toast(LANG === 'en' ? 'At least 8 characters' : 'كلمة المرور 8 أحرف على الأقل', 'err'); return; }
      if (nw !== cf) { toast(LANG === 'en' ? 'Passwords do not match' : 'كلمتا المرور غير متطابقتين', 'err'); return; }
      var btn = q('#pw-btn'); btn.disabled = true;
      api('password', { body: { current: cur, 'new': nw } }).then(function () { toast(LANG === 'en' ? 'Password changed' : 'تم تغيير كلمة المرور'); setTimeout(function () { location.reload(); }, 1100); }).catch(function (e) { btn.disabled = false; toast(e.message, 'err'); });
    });
  }

  /* --------------------------- COMPOSE FULL --------------------------- */
  function composeFullHTML() {
    var tmpls = LANG === 'en' ? ['Admin circular', 'Request quotation', 'Approve invoice', 'Acknowledgement', 'Meeting invite'] : ['تعميم إداري', 'طلب عرض سعر', 'اعتماد مستخلص', 'رد استلام وتأكيد', 'دعوة اجتماع'];
    return '<section class="om-cfull">' +
      '<div class="om-cfull-bar"><span class="t">' + esc(t('newMessage')) + '</span><button class="om-btn" style="margin-inline-start:auto" id="cf-close">' + mi('close_fullscreen') + '</button></div>' +
      '<div class="om-cfull-body"><div class="om-cfull-grid"><div class="om-cfull-main"><div class="om-cfull-rows">' +
        '<div class="om-cfull-row"><span class="lbl">' + esc(t('to')) + '</span><input id="cf-to" dir="ltr"></div>' +
        '<div class="om-cfull-row"><span class="lbl">' + esc(t('subject')) + '</span><input id="cf-subj"></div>' +
      '</div>' +
      '<div class="om-c-toolbar"><button class="om-ai-btn" id="cf-ai">' + mi('auto_awesome') + esc(t('improveText')) + '</button>' + toolbarIconsHTML() + '</div>' +
      '<div class="om-cfull-editor" id="cf-editor" contenteditable="true" dir="' + composeDir() + '" data-ph="' + esc(LANG === 'en' ? 'Write your message…' : 'اكتب نص الرسالة هنا…') + '"></div>' +
      '<div class="om-c-foot"><button class="om-c-send" id="cf-send">' + mi('send') + esc(t('send')) + '</button><button class="om-btn" id="cf-draft" style="margin-inline-start:8px">' + esc(t('saveDraft')) + '</button></div>' +
      '</div><div style="display:flex;flex-direction:column;gap:12px">' +
        '<div class="om-side-card"><div class="t">' + esc(t('ready_templates')) + '</div><div style="display:flex;flex-direction:column;gap:6px">' + tmpls.map(function (x) { return '<button class="om-tmpl" data-tmpl="' + esc(x) + '">' + esc(x) + '</button>'; }).join('') + '</div></div>' +
        '<div class="om-side-card"><div class="t">' + esc(t('attachments')) + '</div><div class="om-dropzone" id="cf-drop">' + mi('cloud_upload') + esc(t('dropHere')) + '</div></div>' +
      '</div></div></div></section>';
  }
  function wireComposeFull() {
    on(q('#cf-close'), 'click', function () { go('inbox'); });
    var ed = q('#cf-editor'); if (ed && S.signature) ed.innerHTML = '<br><br>--<br>' + esc(S.signature);
    wireToolbar(q('.om-cfull-main'), ed);
    on(q('#cf-ai'), 'click', function () { aiImprove(ed); });
    on(q('#cf-send'), 'click', function () { doSendFull(false); });
    on(q('#cf-draft'), 'click', function () { doSendFull(true); });
    qa('[data-tmpl]').forEach(function (b) { on(b, 'click', function () { insertTemplate(ed, b.getAttribute('data-tmpl')); }); });
    on(q('#cf-drop'), 'click', function () { toast(LANG === 'en' ? 'Use the popup composer to attach' : 'استخدم نافذة الكتابة لإرفاق الملفات'); });
  }
  function doSendFull(draft) {
    var to = q('#cf-to').value.trim(), subj = q('#cf-subj').value.trim(), body = q('#cf-editor').innerHTML;
    if (!draft && !to) { toast(t('needRcpt'), 'err'); return; }
    sendMail({ to: to, subject: subj, body_html: body, draft: draft }, null, function () { go('inbox'); });
  }

  /* =========================== COMPOSE POPUP ========================== */
  var CS = null;
  function composeDir() { return S.composeLang === 'en' ? 'ltr' : (S.composeLang === 'ar' ? 'rtl' : (LANG === 'en' ? 'ltr' : 'rtl')); }
  function toolbarIconsHTML() {
    var icons = [['bold', 'format_bold'], ['italic', 'format_italic'], ['underline', 'format_underlined'], ['ul', 'format_list_bulleted'], ['ol', 'format_list_numbered'], ['right', 'format_align_right'], ['center', 'format_align_center'], ['left', 'format_align_left'], ['quote', 'format_quote'], ['link', 'link']];
    return icons.map(function (i) { return '<button class="om-tool" data-cmd="' + i[0] + '">' + mi(i[1]) + '</button>'; }).join('');
  }
  function wireToolbar(scope, editor) {
    qa('.om-tool[data-cmd]', scope).forEach(function (b) {
      on(b, 'mousedown', function (e) { e.preventDefault(); });
      on(b, 'click', function () {
        var c = b.getAttribute('data-cmd'); if (editor) editor.focus();
        var map = { bold: 'bold', italic: 'italic', underline: 'underline', ul: 'insertUnorderedList', ol: 'insertOrderedList', right: 'justifyRight', center: 'justifyCenter', left: 'justifyLeft' };
        if (map[c]) document.execCommand(map[c], false, null);
        else if (c === 'quote') document.execCommand('formatBlock', false, 'blockquote');
        else if (c === 'link') { var u = prompt(LANG === 'en' ? 'Link URL:' : 'الرابط:', 'https://'); if (u) document.execCommand('createLink', false, u); }
      });
    });
  }
  function insertTemplate(editor, name) {
    if (!editor) return; editor.focus();
    var txt = LANG === 'en' ? '<p>With reference to the subject above, kindly find our note below.</p>' : '<p>بالإشارة إلى الموضوع أعلاه، نرجو التكرم بالاطلاع والإفادة.</p>';
    document.execCommand('insertHTML', false, txt); toast((LANG === 'en' ? 'Template: ' : 'أُدرج قالب: ') + name);
  }

  function openCompose(mode, m, smart, presetTo) {
    if (q('.om-compose')) q('.om-compose').remove();
    var to = presetTo || '', cc = '', subject = '', quoted = '', irt = '', refs = '', quoteRef = null;
    var sig = S.signature ? ('<br><br>--<br>' + esc(S.signature).replace(/\n/g, '<br>')) : '';
    if ((mode === 'reply' || mode === 'replyall') && m) {
      to = (m.reply_to && m.reply_to.email) ? m.reply_to.email : (m.from ? m.from.email : '');
      if (mode === 'replyall') {
        var others = (m.to || []).concat(m.cc || []).map(function (a) { return a.email; }).filter(function (e) { return e && e.toLowerCase() !== (S.email || '').toLowerCase() && e.toLowerCase() !== to.toLowerCase(); });
        cc = Array.from(new Set(others)).join(', ');
      }
      subject = /^re:/i.test(m.subject || '') ? m.subject : ('Re: ' + (m.subject || ''));
      irt = m.message_id; refs = (m.references || '') + ' ' + (m.message_id || '');
      quoteRef = { uid: m.uid, folder: S.folder, mode: 'reply' };
    } else if (mode === 'forward' && m) {
      subject = /^fwd:/i.test(m.subject || '') ? m.subject : ('Fwd: ' + (m.subject || ''));
      quoteRef = { uid: m.uid, folder: S.folder, mode: 'forward' };
    }
    CS = { atts: [], irt: irt, refs: refs, mode: mode, cc: cc, bcc: '', ccOpen: !!cc, priority: false, aiOpen: false, w: 640, h: 520, left: 26, min: false, zoom: 1, quote: quoteRef, to: [] };
    if (to) to.split(/[,;]+/).forEach(function (e) { e = e.trim(); if (e) CS.to.push(e); });

    var dock = document.createElement('div'); dock.className = 'om-compose';
    dock.style.width = CS.w + 'px'; dock.style.height = CS.h + 'px';
    dock.innerHTML = composePopupHTML(subject, smart, sig);
    document.body.appendChild(dock);
    wireCompose(dock, subject);
    var ed = q('.om-c-editor', dock);
    if (mode === 'reply' || mode === 'replyall') { ed.innerHTML = (smart ? '<p>' + esc(smart) + '</p>' : '<p><br></p>') + sig; ed.focus(); }
    else if (mode === 'forward') { ed.innerHTML = '<p><br></p>' + sig; }
    else { ed.innerHTML = sig; var toi = q('.om-c-to-input', dock); if (toi && !presetTo) toi.focus(); }
  }
  function composePopupHTML(subject, smart, sig) {
    var title = CS.priority ? (t('newMessage') + ' — ' + t('highPriority')) : t('newMessage');
    return '<div class="om-c-grip-corner" data-grip="corner"></div><div class="om-c-grip-top" data-grip="top"></div><div class="om-c-grip-side" data-grip="side"></div>' +
      '<div class="om-c-head" id="om-c-move"><span class="t">' + esc(title) + '</span><span class="hint">' + esc(t('dragResize')) + '</span>' +
        '<div class="win"><button data-win="shrink" title="−">' + mi('close_fullscreen') + '</button><span class="sz">' + CS.w + '×' + CS.h + '</span><button data-win="grow">' + mi('open_in_full') + '</button>' +
          '<span class="vsep"></span><button data-win="full" title="fullscreen">' + mi('fullscreen') + '</button><button data-win="min">' + mi(CS.min ? 'expand_less' : 'remove') + '</button><button data-win="close">' + mi('close') + '</button></div></div>' +
      '<div class="om-c-inner"' + (CS.min ? ' hidden' : '') + '>' +
        '<div class="om-c-recips"><div class="om-c-recip-row"><span class="lbl">' + esc(t('to')) + '</span>' +
          CS.to.map(function (e, i) { return '<span class="om-c-pill"><span>' + esc(e) + '</span><button data-rmto="' + i + '">' + mi('close') + '</button></span>'; }).join('') +
          '<input class="om-c-to-input" placeholder="' + esc(t('addr_placeholder')) + '"><button class="om-c-cc" id="om-c-cc">' + esc(CS.ccOpen ? (LANG === 'en' ? 'Hide Cc' : 'إخفاء النسخ') : (t('cc') + ' · ' + t('bcc'))) + '</button></div>' +
          (CS.ccOpen ? '<div class="om-c-recip-row"><span class="lbl">' + esc(t('cc')) + '</span><input class="om-c-cc-in" dir="ltr" value="' + esc(CS.cc) + '"></div><div class="om-c-recip-row"><span class="lbl">' + esc(t('bcc')) + '</span><input class="om-c-bcc-in" dir="ltr"></div>' : '') +
          '<div class="om-c-recip-row"><span class="lbl">' + esc(t('subject')) + '</span><input class="om-c-subject" value="' + esc(subject) + '" placeholder="' + esc(t('subject')) + '"><button class="om-c-prio' + (CS.priority ? ' on' : '') + '" id="om-c-prio" title="' + esc(t('highPriority')) + '">' + mi('priority_high') + '</button></div>' +
        '</div>' +
        '<div class="om-c-toolbar"><div style="position:relative"><button class="om-ai-btn' + (CS.aiOpen ? ' on' : '') + '" id="om-c-ai">' + mi('auto_awesome') + '<span>' + esc(t('ai')) + '</span>' + mi('expand_more') + '</button>' + (CS.aiOpen ? aiMenuHTML() : '') + '</div>' +
          toolbarIconsHTML() +
          '<button class="om-tool om-attach" title="' + esc(t('attach')) + '">' + mi('attach_file') + '</button>' +
          '<div class="om-c-textzoom"><button data-cz="-1">' + mi('text_decrease') + '</button><span class="pct">' + Math.round(CS.zoom * 100) + '%</span><button data-cz="1">' + mi('text_increase') + '</button></div></div>' +
        '<input type="file" class="om-c-file" multiple hidden>' +
        '<div class="om-c-body"><div class="om-c-editor" contenteditable="true" dir="' + composeDir() + '" data-ph="' + esc(LANG === 'en' ? 'Write your message…' : 'اكتب نص الرسالة هنا…') + '" style="font-size:' + (13.5 * CS.zoom).toFixed(1) + 'px"></div><div class="om-c-atts"></div></div>' +
        '<div class="om-c-foot"><button class="om-c-send" id="om-c-send">' + mi('send') + esc(t('send')) + '</button>' +
          '<button class="om-c-fbtn icon36" id="om-c-sched" title="' + esc(t('schedule')) + '">' + mi('schedule_send') + '</button><span class="vsep"></span>' +
          '<button class="om-c-fbtn om-attach2" title="' + esc(t('attach')) + '">' + mi('attach_file') + '</button>' +
          '<div class="right"><button class="om-btn" id="om-c-draft">' + esc(t('saveDraft')) + '</button><button class="om-c-discard" id="om-c-discard">' + mi('delete') + '</button></div></div>' +
      '</div>';
  }
  function aiMenuHTML() {
    var acts = [['subject', 'title', t('writeSubject')], ['body', 'edit_note', t('writeBody')], ['both', 'auto_fix_high', t('improveBoth')], ['shorten', 'compress', t('shorten')]];
    return '<div class="om-ai-menu"><div class="h">' + esc(t('aiHeading')) + '</div>' +
      acts.map(function (a) { return '<button data-ai="' + a[0] + '">' + mi(a[1]) + '<span class="nm">' + esc(a[2]) + '</span></button>'; }).join('') +
      '<div style="padding:9px 12px;display:flex;align-items:center;gap:8px;background:var(--panel2)">' + mi('translate') + '<span style="font-size:11px;color:var(--text3);flex:1">' + esc(t('composeLangName')) + '</span><span style="font-size:11px;font-weight:700;color:var(--brand3)">' + esc(S.composeLang === 'en' ? 'English' : (S.composeLang === 'ar' ? 'العربية' : t('auto'))) + '</span></div></div>';
  }
  function wireCompose(dock, subject) {
    var ed = q('.om-c-editor', dock);
    qa('[data-win]', dock).forEach(function (b) {
      on(b, 'click', function () {
        var w = b.getAttribute('data-win');
        if (w === 'close') { dock.remove(); CS = null; }
        else if (w === 'min') { CS.min = !CS.min; rerenderCompose(dock, subject); }
        else if (w === 'grow') { CS.w = Math.min(CS.w + 120, window.innerWidth - 60); CS.h = Math.min(CS.h + 90, window.innerHeight - 70); applyComposeSize(dock); }
        else if (w === 'shrink') { CS.w = Math.max(CS.w - 120, 380); CS.h = Math.max(CS.h - 90, 240); applyComposeSize(dock); }
        else if (w === 'full') { var html = ed.innerHTML; dock.remove(); CS = null; go('composeFull'); setTimeout(function () { var e2 = q('#cf-editor'); if (e2) e2.innerHTML = html; }, 30); }
      });
    });
    on(q('#om-c-cc', dock), 'click', function () { CS.ccOpen = !CS.ccOpen; if (!CS.ccOpen) { CS.cc = ''; } else { var e = q('.om-c-cc-in', dock); } rerenderCompose(dock, subject); });
    on(q('#om-c-prio', dock), 'click', function () { CS.priority = !CS.priority; q('#om-c-prio', dock).classList.toggle('on', CS.priority); var tt = q('.om-c-head .t', dock); if (tt) tt.textContent = CS.priority ? (t('newMessage') + ' — ' + t('highPriority')) : t('newMessage'); });
    on(q('#om-c-ai', dock), 'click', function () { if (!S.ai) { toast(t('aiOff'), 'err'); return; } CS.aiOpen = !CS.aiOpen; rerenderCompose(dock, subject); });
    qa('[data-ai]', dock).forEach(function (b) { on(b, 'click', function () { aiAction(b.getAttribute('data-ai'), dock); }); });
    // recipient input
    var toi = q('.om-c-to-input', dock);
    on(toi, 'keydown', function (e) {
      if (e.key === 'Enter' || e.key === ',' || (e.key === ' ' && toi.value.indexOf('@') > 0)) {
        var v = toi.value.trim().replace(/,$/, ''); if (!v) return; e.preventDefault();
        CS.to.push(v); toi.value = ''; rerenderCompose(dock, subject);
      } else if (e.key === 'Backspace' && !toi.value && CS.to.length) { CS.to.pop(); rerenderCompose(dock, subject); }
    });
    qa('[data-rmto]', dock).forEach(function (b) { on(b, 'click', function () { CS.to.splice(+b.getAttribute('data-rmto'), 1); rerenderCompose(dock, subject); }); });
    // toolbar
    wireToolbar(dock, ed);
    // text zoom
    qa('[data-cz]', dock).forEach(function (b) { on(b, 'click', function () { CS.zoom = Math.max(0.8, Math.min(1.6, CS.zoom + (+b.getAttribute('data-cz')) * 0.1)); ed.style.fontSize = (13.5 * CS.zoom).toFixed(1) + 'px'; var p = q('.om-c-textzoom .pct', dock); if (p) p.textContent = Math.round(CS.zoom * 100) + '%'; }); });
    // attach
    var file = q('.om-c-file', dock);
    qa('.om-attach, .om-attach2', dock).forEach(function (b) { on(b, 'click', function () { file.click(); }); });
    on(file, 'change', function () { Array.prototype.forEach.call(file.files, function (f) { uploadAttachment(f, dock); }); file.value = ''; });
    // send / draft / discard / schedule
    on(q('#om-c-send', dock), 'click', function () { doSendPopup(dock, false); });
    on(q('#om-c-draft', dock), 'click', function () { doSendPopup(dock, true); });
    on(q('#om-c-discard', dock), 'click', function () { dock.remove(); CS = null; toast(LANG === 'en' ? 'Draft discarded' : 'تم حذف المسودة'); });
    on(q('#om-c-sched', dock), 'click', function () { toast(LANG === 'en' ? 'Schedule: tomorrow 08:00' : 'حدد وقت الإرسال: غدًا 08:00'); });
    // drag / resize
    setupComposeDrag(dock);
    // restore CC value binding
    var ccin = q('.om-c-cc-in', dock); if (ccin) on(ccin, 'input', function () { CS.cc = ccin.value; });
  }
  function rerenderCompose(dock, subject) {
    var html = q('.om-c-editor', dock) ? q('.om-c-editor', dock).innerHTML : '';
    var atts = CS.atts.slice();
    var subj = q('.om-c-subject', dock) ? q('.om-c-subject', dock).value : subject;
    var bcc = q('.om-c-bcc-in', dock) ? q('.om-c-bcc-in', dock).value : CS.bcc;
    dock.innerHTML = composePopupHTML(subj, null, '');
    wireCompose(dock, subj);
    var ed = q('.om-c-editor', dock); if (ed) ed.innerHTML = html;
    CS.atts = atts; renderAtts(dock);
    var bi = q('.om-c-bcc-in', dock); if (bi) bi.value = bcc;
  }
  function applyComposeSize(dock) { dock.style.width = CS.w + 'px'; dock.style.height = CS.min ? '44px' : CS.h + 'px'; var sz = q('.om-c-head .sz', dock); if (sz) sz.textContent = CS.w + '×' + CS.h; }
  function setupComposeDrag(dock) {
    var drag = null, rtl = (document.documentElement.getAttribute('dir') || 'rtl') === 'rtl';
    function down(mode, e) { e.preventDefault(); var pt = e.touches ? e.touches[0] : e; drag = { mode: mode, x: pt.clientX, y: pt.clientY, w: CS.w, h: CS.h, left: CS.left }; document.body.style.userSelect = 'none'; }
    function move(e) {
      if (!drag) return; var pt = e.touches ? e.touches[0] : e; var dx = pt.clientX - drag.x, dy = pt.clientY - drag.y;
      if (drag.mode === 'side' || drag.mode === 'corner') CS.w = Math.max(380, Math.min(rtl ? drag.w - dx : drag.w + dx, window.innerWidth - 40));
      if (drag.mode === 'top' || drag.mode === 'corner') CS.h = Math.max(240, Math.min(drag.h - dy, window.innerHeight - 60));
      if (drag.mode === 'move') { CS.left = Math.max(8, Math.min(drag.left + (rtl ? -dx : dx), window.innerWidth - 200)); dock.style.insetInlineStart = CS.left + 'px'; }
      dock.style.width = CS.w + 'px'; dock.style.height = CS.h + 'px'; var sz = q('.om-c-head .sz', dock); if (sz) sz.textContent = CS.w + '×' + CS.h;
      if (e.cancelable) e.preventDefault();
    }
    function up() { drag = null; document.body.style.userSelect = ''; }
    qa('[data-grip]', dock).forEach(function (g) { on(g, 'mousedown', function (e) { down(g.getAttribute('data-grip'), e); }); });
    var head = q('#om-c-move', dock); on(head, 'mousedown', function (e) { if (e.target.closest('button')) return; down('move', e); });
    on(document, 'mousemove', move); on(document, 'mouseup', up);
    dock._cleanup = function () { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); };
  }
  function uploadAttachment(file, dock) {
    var host = q('.om-c-atts', dock); var chip = document.createElement('span'); chip.className = 'om-c-att';
    chip.innerHTML = mi('hourglass_top') + '<span>' + esc(file.name) + '</span><span class="om-spin sm"></span>';
    host.appendChild(chip);
    var fd = new FormData(); fd.append('file', file);
    api('upload', { form: fd }).then(function (r) {
      CS.atts.push({ token: r.token, name: r.name, size: r.size });
      renderAtts(dock);
    }).catch(function (e) { chip.remove(); toast(e.message, 'err'); });
  }
  function renderAtts(dock) {
    var host = q('.om-c-atts', dock); if (!host) return;
    host.innerHTML = CS.atts.map(function (a, i) { return '<span class="om-c-att">' + mi('attach_file') + '<span>' + esc(a.name) + '</span><span style="color:var(--text3)">' + fmtBytes(a.size) + '</span><button class="rm" data-rmatt="' + i + '">' + mi('close') + '</button></span>'; }).join('');
    qa('[data-rmatt]', dock).forEach(function (b) { on(b, 'click', function () { CS.atts.splice(+b.getAttribute('data-rmatt'), 1); renderAtts(dock); }); });
  }
  function doSendPopup(dock, draft) {
    var to = CS.to.slice(); var toi = q('.om-c-to-input', dock); if (toi && toi.value.trim()) to.push(toi.value.trim());
    var cc = q('.om-c-cc-in', dock) ? q('.om-c-cc-in', dock).value.trim() : '';
    var bcc = q('.om-c-bcc-in', dock) ? q('.om-c-bcc-in', dock).value.trim() : '';
    var subj = q('.om-c-subject', dock).value.trim();
    var body = q('.om-c-editor', dock).innerHTML;
    if (!draft && !to.length && !cc && !bcc) { toast(t('needRcpt'), 'err'); return; }
    var btn = q('#om-c-send', dock); if (btn) { btn.disabled = true; btn.innerHTML = '<span class="om-spin sm"></span> ' + esc(LANG === 'en' ? 'Sending…' : 'جارٍ الإرسال…'); }
    sendMail({
      to: to.join(', '), cc: cc, bcc: bcc, subject: subj, body_html: body, draft: draft,
      attachments: CS.atts.map(function (a) { return a.token; }), in_reply_to: CS.irt, references: CS.refs,
      priority: CS.priority ? 1 : 0, quote: CS.quote
    }, dock, function () { if (dock._cleanup) dock._cleanup(); dock.remove(); CS = null; });
  }
  function sendMail(args, dock, done) {
    var body = { to: args.to, cc: args.cc || '', bcc: args.bcc || '', subject: args.subject, body_html: args.body_html, draft: !!args.draft };
    if (args.attachments) body.attachments = args.attachments;
    if (args.in_reply_to) body.in_reply_to = args.in_reply_to;
    if (args.references) body.references = args.references;
    if (args.priority) body.priority = args.priority;
    if (args.quote) { body.quote_uid = args.quote.uid; body.quote_folder = args.quote.folder; body.quote_mode = args.quote.mode; }
    api('send', { body: body }).then(function () {
      toast(args.draft ? t('draft_ok') : t('sent_ok'));
      if (done) done();
      refreshFolders();
      if (S.special === 'sent' || S.special === 'drafts') loadMessages();
    }).catch(function (e) {
      if (dock) { var btn = q('#om-c-send', dock); if (btn) { btn.disabled = false; btn.innerHTML = mi('send') + esc(t('send')); } }
      toast(e.message, 'err');
    });
  }

  /* -------------------------------- AI -------------------------------- */
  function aiAction(kind, dock) {
    CS.aiOpen = false; rerenderCompose(dock, q('.om-c-subject', dock) ? q('.om-c-subject', dock).value : '');
    var ed = q('.om-c-editor', dock), subjEl = q('.om-c-subject', dock);
    if (kind === 'subject') { aiCall('draft', subjEl.value || (LANG === 'en' ? 'Write a concise subject' : 'اكتب موضوعًا موجزًا'), function (r) { if (r.subject) subjEl.value = r.subject; }); }
    else if (kind === 'body') { var idea = (ed.innerText || '').trim() || subjEl.value; aiCall('draft', idea, function (r) { ed.innerHTML = r.html + (S.signature ? '<br>--<br>' + esc(S.signature) : ''); if (r.subject && !subjEl.value.trim()) subjEl.value = r.subject; }); }
    else if (kind === 'both') { var idea2 = subjEl.value || (ed.innerText || '').trim(); aiCall('draft', idea2, function (r) { if (r.subject) subjEl.value = r.subject; ed.innerHTML = r.html; }); }
    else if (kind === 'shorten' || kind === 'improve') { aiImprove(ed); }
  }
  function aiImprove(ed) {
    if (!S.ai) { toast(t('aiOff'), 'err'); return; }
    var text = (ed.innerText || ed.textContent || '').trim();
    if (!text) { toast(LANG === 'en' ? 'Write something first' : 'اكتب نص الرسالة أولاً', 'err'); return; }
    toast(t('writing'));
    api('ai', { body: { mode: 'improve', text: text } }).then(function (r) { ed.innerHTML = r.html; toast(LANG === 'en' ? 'Improved' : 'تم التحسين'); }).catch(function (e) { toast(e.message, 'err'); });
  }
  function aiCall(mode, text, cb) {
    if (!S.ai) { toast(t('aiOff'), 'err'); return; }
    if (!text) { toast(LANG === 'en' ? 'Type an idea first' : 'اكتب فكرة أولاً', 'err'); return; }
    toast(t('writing'));
    api('ai', { body: { mode: mode, text: text } }).then(function (r) { cb(r); toast(LANG === 'en' ? 'Ready' : 'جاهز'); }).catch(function (e) { toast(e.message, 'err'); });
  }

  /* ------------------------------ actions ----------------------------- */
  function toggleStar(uid) {
    var m = S.messages.filter(function (x) { return x.uid === uid; })[0];
    var on_ = m ? !m.flagged : true; if (m) m.flagged = on_;
    if (S.currentMsg && S.currentMsg.uid === uid) S.currentMsg.flagged = on_;
    var row = q('.om-star[data-star="' + uid + '"]'); if (row) { row.classList.toggle('on', on_); row.innerHTML = mi(on_ ? 'star' : 'star_outline'); }
    var so = q('#om-star-open'); if (so && S.currentUid === uid) so.innerHTML = mi(on_ ? 'star' : 'star_outline');
    api('flag', { body: { folder: S.folder, uid: uid, flag: 'flagged', on: on_ } }).catch(function () {});
    toast(on_ ? t('starredOn') : t('starredOff'));
  }
  function archiveMsg(uid) {
    api('move', { body: { folder: S.folder, uid: uid, dest: 'archive' } }).then(function () {
      removeFromList(uid); toast(t('archived')); refreshFolders();
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function deleteMsg(uid) {
    api('delete', { body: { folder: S.folder, uid: uid } }).then(function (r) {
      removeFromList(uid); toast(r.mode === 'purged' ? (LANG === 'en' ? 'Deleted' : 'تم الحذف') : t('deleted')); refreshFolders();
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function moveToSpecial(uid, special, msg) {
    api('move', { body: { folder: S.folder, uid: uid, dest: special } }).then(function () { removeFromList(uid); toast(msg); refreshFolders(); }).catch(function (e) { toast(e.message, 'err'); });
  }
  function readerAction(a) {
    var m = S.currentMsg; if (!m) return;
    if (a === 'replyall') openCompose('replyall', m);
    else if (a === 'forward') openCompose('forward', m);
    else if (a === 'archive') archiveMsg(m.uid);
    else if (a === 'delete') deleteMsg(m.uid);
    else if (a === 'snooze') snoozeMsg(m.uid);
    else if (a === 'label') openLabelMenu();
    else if (a === 'unread') { api('flag', { body: { folder: S.folder, uid: m.uid, flag: 'seen', on: false } }).then(function () { m.seen = false; var lm = S.messages.filter(function (x) { return x.uid === m.uid; })[0]; if (lm) lm.seen = false; S.currentUid = 0; S.currentMsg = null; bumpUnread(1); paintMain(); toast(t('markedUnread')); }).catch(function (e) { toast(e.message, 'err'); }); }
  }
  function snoozeMsg(uid) {
    api('snooze', { body: { folder: S.folder, uid: uid } }).then(function () { removeFromList(uid); toast(LANG === 'en' ? 'Snoozed to tomorrow 08:00' : 'تم تأجيل الرسالة إلى غدًا 08:00'); refreshFolders(); }).catch(function () { moveToSpecial(uid, 'archive', LANG === 'en' ? 'Snoozed' : 'تم التأجيل'); });
  }
  function openLabelMenu() {
    var m = S.currentMsg; if (!m) return;
    var host = document.createElement('div'); host.className = 'om-modal'; host.id = 'om-label-modal';
    host.innerHTML = '<div class="om-modal-card" style="width:360px"><div class="om-modal-head"><span class="t">' + esc(t('labelsTitle')) + '</span><button class="x" id="lm-x">' + mi('close') + '</button></div><div class="om-modal-body" style="display:flex;flex-direction:column;gap:4px">' +
      LABEL_DEFS.map(function (l) { return '<button class="om-label" data-setl="' + l.slug + '" style="height:40px"><span class="dot" style="background:' + l.color + '"></span><span class="nm">' + esc(labelName(l)) + '</span>' + (m.label === l.slug ? mi('check') : '') + '</button>'; }).join('') +
      '<button class="om-label" data-setl="" style="height:40px"><span class="dot" style="background:var(--text3)"></span><span class="nm">' + esc(LANG === 'en' ? 'No label' : 'بدون تصنيف') + '</span></button>' +
      '</div></div>';
    document.body.appendChild(host);
    on(host, 'click', function (e) { if (e.target === host) host.remove(); });
    on(q('#lm-x', host), 'click', function () { host.remove(); });
    qa('[data-setl]', host).forEach(function (b) {
      on(b, 'click', function () {
        var slug = b.getAttribute('data-setl'); host.remove();
        api('label', { body: { folder: S.folder, uid: m.uid, label: slug } }).then(function () {
          m.label = slug; var lm = S.messages.filter(function (x) { return x.uid === m.uid; })[0]; if (lm) lm.label = slug;
          paintMain(); refreshFolders();
          var L = LABEL_DEFS.filter(function (x) { return x.slug === slug; })[0];
          toast(L ? ((LANG === 'en' ? 'Labelled: ' : 'التصنيف: ') + labelName(L)) : (LANG === 'en' ? 'Label removed' : 'أُزيل التصنيف'));
        }).catch(function (e) { toast(e.message, 'err'); });
      });
    });
  }
  function bulkAction(action) {
    var uids = Object.keys(S.sel).map(Number); if (!uids.length && action !== 'clear') return;
    if (action === 'clear') { S.sel = {}; paintMain(); return; }
    var map = { read: 'read', star: 'star', archive: 'move', delete: 'delete' };
    var body = { folder: S.folder, action: map[action], uids: uids };
    if (action === 'archive') body.dest = 'archive';
    api('batch', { body: body }).then(function () {
      if (action === 'read') { uids.forEach(function (u) { var m = S.messages.filter(function (x) { return x.uid === u; })[0]; if (m) m.seen = true; }); }
      else if (action === 'star') { uids.forEach(function (u) { var m = S.messages.filter(function (x) { return x.uid === u; })[0]; if (m) m.flagged = true; }); }
      else { uids.forEach(function (u) { S.messages = S.messages.filter(function (x) { return x.uid !== u; }); }); }
      S.sel = {}; paintMain(); refreshFolders();
      toast(LANG === 'en' ? 'Done' : 'تم التنفيذ');
      if (!S.messages.length) loadMessages();
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function removeFromList(uid) {
    S.messages = S.messages.filter(function (x) { return x.uid !== uid; });
    if (S.currentUid === uid) { S.currentUid = 0; S.currentMsg = null; ROOT.classList.remove('reading'); }
    paintMain();
    if (!S.messages.length) loadMessages();
  }
  function bumpUnread(d) {
    var f = S.sideFolders.filter(function (x) { return x.special === S.special; })[0];
    if (f) { f.count = Math.max(0, (parseInt(f.count) || 0) + d) || ''; if (typeof f.count === 'number' && f.count === 0) f.count = ''; }
    var side = q('#om-side'); if (side) { /* light refresh */ }
  }
  function downloadAtt(index, name) {
    toast(t('downloading'));
    api('attachment', { query: { folder: S.folder, uid: S.currentUid, index: index } }).then(function (r) {
      var bin = atob(r.b64), len = bin.length, arr = new Uint8Array(len);
      for (var i = 0; i < len; i++) arr[i] = bin.charCodeAt(i);
      var blob = new Blob([arr], { type: r.mime || 'application/octet-stream' });
      var url = URL.createObjectURL(blob), a = document.createElement('a');
      a.href = url; a.download = r.name || name || 'attachment'; document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function saveCurrentClient() {
    var m = S.currentMsg; if (!m || !m.from) return;
    api('client-save', { body: { email: m.from.email, name: m.from.name || m.from.email } }).then(function () {
      toast(LANG === 'en' ? 'Saved to clients' : 'تم حفظ العميل في القائمة'); loadClients(true); paintMain();
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function moveMsg(step) {
    var idx = S.messages.map(function (x) { return x.uid; }).indexOf(S.currentUid);
    var next = S.messages[idx + step]; if (next) openMessage(next.uid);
  }

  /* --------------------------- data loaders --------------------------- */
  function buildSideFolders(folders) {
    var icons = { inbox: 'inbox', sent: 'send', drafts: 'draft', archive: 'archive', junk: 'report', trash: 'delete' };
    var byspecial = {}; folders.forEach(function (f) { if (f.special) byspecial[f.special] = f; });
    var out = [];
    var inbox = byspecial.inbox || folders[0] || { raw: 'INBOX', special: 'inbox', unseen: 0, total: 0 };
    out.push({ special: 'inbox', raw: inbox.raw, icon: 'inbox', name: t('folders').inbox, count: inbox.unseen || '' });
    out.push({ special: 'starred', raw: inbox.raw, icon: 'star', name: t('folders').starred, count: '' });
    var snoozed = byspecial.snoozed || folders.filter(function (f) { return /snooz/i.test(f.raw); })[0];
    out.push({ special: 'snoozed', raw: snoozed ? snoozed.raw : '', icon: 'schedule', name: t('folders').snoozed, count: snoozed ? (snoozed.total || '') : '' });
    ['sent', 'drafts', 'archive', 'junk', 'trash'].forEach(function (sp) {
      var f = byspecial[sp]; if (!f) return;
      out.push({ special: sp, raw: f.raw, icon: icons[sp], name: t('folders')[sp], count: (sp === 'drafts' || sp === 'junk' || sp === 'trash') ? (f.total || '') : (f.unseen || '') });
    });
    // extra custom folders (non-special) shown too
    folders.forEach(function (f) { if (!f.special && f.raw && f.raw.toUpperCase() !== 'INBOX') out.push({ special: 'custom:' + f.raw, raw: f.raw, icon: 'folder', name: f.display || f.raw, count: f.unseen || '' }); });
    S.sideFolders = out;
  }
  function openFolder(fk, raw) {
    S.acctOpen = false; S.notifsOpen = false; S.currentUid = 0; S.currentMsg = null; S.sel = {}; toggleSide(false);
    S.screen = 'inbox';
    if (fk === 'starred') { S.special = 'inbox'; S.folder = raw || 'INBOX'; S.filter = 2; S.display = t('folders').starred; }
    else { S.special = fk.indexOf('custom:') === 0 ? fk : fk; S.folder = raw || 'INBOX'; S.filter = 0; S.display = folderDisplayName(fk); }
    paintShell(); loadMessages();
  }
  function folderDisplayName(fk) {
    if (fk.indexOf('custom:') === 0) { var f = S.sideFolders.filter(function (x) { return x.special === fk; })[0]; return f ? f.name : fk; }
    return t('folders')[fk] || t('folders').inbox;
  }
  function openLabel(slug) {
    S.screen = 'inbox'; S.special = 'label:' + slug; S.folder = (S.sideFolders[0] && S.sideFolders[0].raw) || 'INBOX'; S.filter = 0; S.sel = {}; S.currentUid = 0; S.currentMsg = null;
    var L = LABEL_DEFS.filter(function (x) { return x.slug === slug; })[0]; S.display = L ? labelName(L) : t('labelsTitle');
    toggleSide(false); paintShell();
    S.loading = true; paintMain();
    api('messages', { query: { folder: S.folder, page: 0, filter: 'label:' + slug } }).then(function (r) { onMessages(r); }).catch(function (e) { S.loading = false; toast(e.message, 'err'); });
  }
  function loadMessages() {
    S.loading = true; if (S.screen === 'inbox') paintMain();
    var filterMap = { 0: '', 1: 'unread', 2: 'starred', 3: 'attach', 4: 'needsreply' };
    var qy = { folder: S.folder, page: 0, sort: S.sort, filter: filterMap[S.filter] || '' };
    if (S.special === 'starred') qy.filter = 'starred';
    api('messages', { query: qy }).then(function (r) { onMessages(r); }).catch(function (e) { S.loading = false; toast(e.message, 'err'); if (S.screen === 'inbox') paintMain(); });
  }
  function onMessages(r) {
    S.loading = false; S.total = r.total || 0;
    S.messages = (r.messages || []).map(function (m) { m.label = m.label || null; return m; });
    if (S.messages.length) S.lastTopUid = S.messages[0].uid;
    if (S.screen === 'inbox') paintMain();
  }
  function openMessage(uid) {
    S.currentUid = uid;
    var lm = S.messages.filter(function (x) { return x.uid === uid; })[0];
    var wasUnread = lm && !lm.seen;
    if (lm) lm.seen = true;
    S.currentMsg = null; ROOT.classList.toggle('reading', window.innerWidth < 1000); paintMain();
    api('message', { query: { folder: S.folder, uid: uid } }).then(function (m) {
      if (S.currentUid !== uid) return;
      if (lm && lm.label && !m.label) m.label = lm.label; S.currentMsg = m; paintMain();
      if (wasUnread) bumpUnread(-1);
    }).catch(function (e) { toast(e.message, 'err'); });
  }
  function refreshFolders() { api('folders').then(function (r) { S.folders = r.folders || S.folders; buildSideFolders(S.folders); loadLabels(); var side = q('#om-side'); if (side) side.outerHTML = sidebarHTML(); wireSidebar(); loadQuota(); }).catch(function () {}); }
  function loadLabels() {
    api('labels').then(function (r) {
      var counts = r.counts || {};
      S.labels = LABEL_DEFS.map(function (l) { return { slug: l.slug, color: l.color, ar: l.ar, en: l.en, count: counts[l.slug] || '' }; });
    }).catch(function () { S.labels = LABEL_DEFS.map(function (l) { return { slug: l.slug, color: l.color, ar: l.ar, en: l.en, count: '' }; }); });
  }
  function loadContacts(force) { if (S.contacts.length && !force) { return; } api('contacts').then(function (r) { S.contacts = r.contacts || []; if (S.screen === 'contacts') paintMain(); }).catch(function () {}); }
  function loadClients(force) { api('clients').then(function (r) { S.clients = r.clients || []; if (S.screen === 'clients') paintMain(); var side = q('#om-side'); if (side) { side.outerHTML = sidebarHTML(); wireSidebar(); } }).catch(function () {}); }
  function loadStats() { api('stats').then(function (r) { S.stats = r; if (S.screen === 'stats') paintMain(); }).catch(function () {}); }
  function loadQuota() { api('quota').then(function (r) { S.quota = r; }).catch(function () {}); }

  /* ---------------------------- polling ------------------------------- */
  function pollNew(manual) {
    if (document.hidden && !manual) return;
    var qy = { folder: (S.sideFolders[0] && S.sideFolders[0].raw) || 'INBOX', page: 0 };
    api('messages', { query: qy }).then(function (r) {
      var msgs = r.messages || []; if (!msgs.length) return;
      var top = msgs[0];
      if (S.lastTopUid && top.uid > S.lastTopUid) {
        var fresh = msgs.filter(function (m) { return m.uid > S.lastTopUid; });
        fresh.reverse().forEach(function (m) {
          S.notifs.unshift({ sender: m.from ? (m.from.name || m.from.email) : '', email: m.from ? m.from.email : '', subject: m.subject, ago: t('now'), read: false, uid: m.uid });
        });
        S.notifs = S.notifs.slice(0, 15);
        showIncoming(fresh[fresh.length - 1] || top);
        if (S.screen === 'inbox' && S.special === 'inbox' && S.filter === 0) { S.messages = msgs; }
        paintShell();
      }
      S.lastTopUid = top.uid;
    }).catch(function () {});
  }
  function showIncoming(m) {
    var old = q('.om-incoming'); if (old) old.remove();
    var sender = m.from ? (m.from.name || m.from.email) : '';
    var el = document.createElement('div'); el.className = 'om-incoming';
    el.innerHTML = '<div class="top-line"></div><div class="body">' + avatarHTML(sender, m.from && m.from.email, 'av', 36) +
      '<div style="flex:1;min-width:0"><div style="display:flex;align-items:baseline"><span class="tag">' + esc(t('newMail')) + '</span><span class="ago">' + esc(t('now')) + '</span></div>' +
      '<div class="nm om-nowrap">' + esc(sender) + '</div><div class="s om-nowrap">' + esc(m.subject) + '</div>' +
      '<div class="acts"><button class="open">' + esc(t('open')) + '</button><button class="save">' + esc(t('saveClient')) + '</button><button class="x">' + mi('close') + '</button></div></div></div>';
    document.body.appendChild(el);
    on(q('.open', el), 'click', function () { el.remove(); go('inbox'); openMessage(m.uid); });
    on(q('.save', el), 'click', function () { if (m.from) api('client-save', { body: { email: m.from.email, name: sender } }).then(function () { toast(LANG === 'en' ? 'Saved to clients' : 'تم حفظ العميل'); loadClients(true); }); el.remove(); });
    on(q('.x', el), 'click', function () { el.remove(); });
    clearTimeout(showIncoming._t); showIncoming._t = setTimeout(function () { el.remove(); }, 8000);
  }

  /* -------------------------- keyboard / modal ------------------------ */
  function openShortcuts() {
    var host = document.createElement('div'); host.className = 'om-modal';
    var sc = [['C', LANG === 'en' ? 'Compose' : 'رسالة جديدة'], ['R', t('reply')], ['A', t('replyAll')], ['F', t('forward')], ['E', t('archive')], ['S', LANG === 'en' ? 'Star' : 'تمييز بنجمة'], ['#', t('del')], ['H', t('snooze')], ['/', LANG === 'en' ? 'Search' : 'بحث'], ['J / K', LANG === 'en' ? 'Next / Prev' : 'التالي / السابق'], ['G I', LANG === 'en' ? 'Go to inbox' : 'الانتقال للوارد'], ['?', LANG === 'en' ? 'This menu' : 'هذه القائمة']];
    host.innerHTML = '<div class="om-modal-card"><div class="om-modal-head"><span class="t">' + esc(t('shortcutsTitle')) + '</span><button class="x" id="sc-x">' + mi('close') + '</button></div><div class="om-shortcuts">' +
      sc.map(function (s) { return '<div class="om-sc-row"><kbd class="om-sc-key">' + esc(s[0]) + '</kbd><span class="om-sc-desc">' + esc(s[1]) + '</span></div>'; }).join('') + '</div></div>';
    document.body.appendChild(host);
    on(host, 'click', function (e) { if (e.target === host) host.remove(); });
    on(q('#sc-x', host), 'click', function () { host.remove(); });
  }
  function keyHandler(e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) { if (e.key === 'Escape') e.target.blur(); return; }
    if (q('.om-compose') || q('.om-modal')) { if (e.key === 'Escape') { var c = q('.om-compose'); if (c) { c.remove(); CS = null; } var md = q('.om-modal'); if (md) md.remove(); } return; }
    if (e.key === 'c' || e.key === 'C') { openCompose('new'); }
    else if (e.key === '?') { openShortcuts(); }
    else if (e.key === '/') { e.preventDefault(); var si = q('#om-search-in'); if (si) si.focus(); }
    else if ((e.key === 'j' || e.key === 'k') && S.screen === 'inbox') { moveMsg(e.key === 'j' ? 1 : -1); }
    else if ((e.key === 'r' || e.key === 'R') && S.currentMsg) { openCompose('reply', S.currentMsg); }
    else if ((e.key === 'f' || e.key === 'F') && S.currentMsg) { openCompose('forward', S.currentMsg); }
    else if ((e.key === 'e' || e.key === 'E') && S.currentMsg) { archiveMsg(S.currentMsg.uid); }
    else if (e.key === '#' && S.currentMsg) { deleteMsg(S.currentMsg.uid); }
    else if ((e.key === 's' || e.key === 'S') && S.currentMsg) { toggleStar(S.currentMsg.uid); }
  }

  /* --------------------------- onboarding ----------------------------- */
  function renderOnboard(err) {
    var d = S.bootstrap || {};
    var def = d.defaults || { imap_host: 'imap.hostinger.com', imap_port: 993, smtp_host: 'smtp.hostinger.com', smtp_port: 465 };
    ROOT.className = 'om-app';
    ROOT.innerHTML = topbarHTML() + '<div class="om-body"><div class="om-onb"><div class="om-onb-card">' +
      '<div class="ic">' + mi('mail') + '</div><h2>' + esc(t('connectTitle')) + '</h2><p class="sub">' + esc(t('connectSub')) + '</p>' +
      '<div class="om-alert' + (err ? ' show' : '') + '" id="ob-alert">' + esc(err || '') + '</div>' +
      '<div class="om-note">' + mi('info') + '<span>' + esc(t('hosterNote')) + '</span></div>' +
      '<label class="om-field"><span>' + esc(t('email')) + '</span><input class="om-input" id="ob-email" dir="ltr" value="' + esc(d.email || S.email || '') + '" placeholder="name@osoulalbinaa.com"></label>' +
      '<label class="om-field" style="margin-top:12px"><span>' + esc(t('password')) + '</span><div class="om-pass-wrap"><input class="om-input" id="ob-pass" type="password" dir="ltr" autocomplete="off"><button class="om-eye" id="ob-eye">' + mi('visibility') + '</button></div></label>' +
      '<label class="om-field" style="margin-top:12px"><span>' + esc(t('displayName')) + '</span><input class="om-input" id="ob-name" value="' + esc(d.from_name || S.name || '') + '"></label>' +
      '<button class="om-adv-toggle" id="ob-adv-t" style="margin-top:8px">' + mi('tune') + ' ' + esc(t('advanced')) + '</button>' +
      '<div class="om-adv" id="ob-adv">' +
        '<label class="om-field"><span>' + esc(t('imapHost')) + '</span><input class="om-input" id="ob-ih" dir="ltr" value="' + esc(d.imap_host || def.imap_host) + '"></label>' +
        '<label class="om-field"><span>' + esc(t('imapPort')) + '</span><input class="om-input" id="ob-ip" dir="ltr" value="' + esc(d.imap_port || def.imap_port) + '"></label>' +
        '<label class="om-field"><span>' + esc(t('smtpHost')) + '</span><input class="om-input" id="ob-sh" dir="ltr" value="' + esc(d.smtp_host || def.smtp_host) + '"></label>' +
        '<label class="om-field"><span>' + esc(t('smtpPort')) + '</span><input class="om-input" id="ob-sp" dir="ltr" value="' + esc(d.smtp_port || def.smtp_port) + '"></label>' +
      '</div>' +
      '<button class="om-btn primary om-full" id="ob-connect" style="margin-top:16px;height:44px">' + esc(t('connect')) + '</button>' +
      '</div></div></div>';
    wireTopbar();
    on(q('#ob-eye'), 'click', function () { var p = q('#ob-pass'); p.type = p.type === 'password' ? 'text' : 'password'; });
    on(q('#ob-adv-t'), 'click', function () { q('#ob-adv').classList.toggle('open'); });
    on(q('#ob-pass'), 'keydown', function (e) { if (e.key === 'Enter') q('#ob-connect').click(); });
    on(q('#ob-connect'), 'click', function () {
      var btn = q('#ob-connect'), al = q('#ob-alert');
      var payload = { email: q('#ob-email').value.trim(), password: q('#ob-pass').value, from_name: q('#ob-name').value.trim(), imap_host: q('#ob-ih').value.trim(), imap_port: q('#ob-ip').value.trim(), smtp_host: q('#ob-sh').value.trim(), smtp_port: q('#ob-sp').value.trim() };
      al.classList.remove('show'); btn.disabled = true; btn.innerHTML = '<span class="om-spin sm"></span> ' + esc(t('connecting'));
      api('connect', { body: payload }).then(function () { start(); }).catch(function (e) { btn.disabled = false; btn.textContent = t('connect'); al.textContent = e.message; al.classList.add('show'); });
    });
  }

  /* ------------------------------ start ------------------------------- */
  function start() {
    ROOT.innerHTML = '<div class="om-boot"><span class="om-spin"></span></div>';
    api('bootstrap').then(function (b) {
      S.bootstrap = b; S.from_name = b.from_name || S.name; S.signature = b.signature || ''; S.email = b.email || S.email; S.avatar = b.avatar || ''; S.ai = !!b.ai; S.name = b.name || S.name;
      if (!b.connected) { renderOnboard(b.conn_error || ''); return; }
      S.folders = b.folders || []; buildSideFolders(S.folders);
      S.labels = LABEL_DEFS.map(function (l) { return { slug: l.slug, color: l.color, ar: l.ar, en: l.en, count: '' }; });
      var inbox = S.sideFolders[0]; if (inbox) { S.folder = inbox.raw; S.special = 'inbox'; S.display = t('folders').inbox; }
      paintShell();
      loadMessages(); loadLabels(); loadContacts(); loadClients(); loadQuota();
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(pollNew, CFG.poll || 20000);
    }).catch(function (e) {
      ROOT.innerHTML = '<div class="om-onb"><div class="om-onb-card"><h2>⚠</h2><p class="sub">' + esc(e.message) + '</p><button class="om-btn primary om-full" onclick="location.reload()">' + esc(LANG === 'en' ? 'Reload' : 'إعادة') + '</button></div></div>';
    });
  }
  var pollTimer = null;
  on(window, 'keydown', keyHandler);
  on(window, 'resize', function () { if (S.screen === 'inbox') paintMain(); });
  start();
})();
