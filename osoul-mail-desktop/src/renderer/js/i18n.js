/**
 * Osoul Mail — العربية والإنجليزية.
 *
 * الشركة فيها موظفون عرب وأجانب، فاللغة اختيار شخصي لكل موظف لا إعداد عام.
 * تبديل اللغة يقلب اتجاه الواجهة كاملة (RTL/LTR) ويعيد رسمها فورًا دون
 * إعادة تشغيل، ويُحفظ مع بقية إعدادات الموظف.
 *
 * كل نص ظاهر للمستخدم يمرّ من هنا. النصوص القادمة من العملية الرئيسية
 * (رسائل الأخطاء) تصل بلغتين معًا، وتختار الواجهة منها حسب اللغة الحالية.
 */

const DICT = {
  ar: {
    /* عام */
    appName: 'بريد أصول البناء',
    appTagline: 'OSOUL ALBINAA MAIL',
    ok: 'موافق', cancel: 'إلغاء', save: 'حفظ', close: 'إغلاق', back: 'رجوع',
    search: 'ابحث في البريد…', searchResults: 'نتائج البحث', clear: 'مسح',
    refresh: 'تحديث', settings: 'الإعدادات', theme: 'تبديل السمة',
    loading: 'جارٍ التحميل…', unexpected: 'حدث خطأ غير متوقع.',
    appBootFailed: 'تعذّر تشغيل التطبيق. أعد فتحه من جديد.',
    unitB: 'بايت', unitKB: 'ك.ب', unitMB: 'م.ب', unitGB: 'ج.ب',
    listSep: '، ',

    /* بوابة الدخول */
    email: 'البريد الإلكتروني', password: 'كلمة المرور',
    keepSignedIn: 'إبقائي مسجلًا للدخول',
    signIn: 'دخول إلى بريدي', checking: 'جارٍ التحقق…',
    showPassword: 'إظهار كلمة المرور',
    advancedServers: 'إعدادات الخادم المتقدمة',
    imapServer: 'خادم الاستقبال IMAP', smtpServer: 'خادم الإرسال SMTP',
    enterEmail: 'اكتب بريدك الإلكتروني.', enterPassword: 'اكتب كلمة المرور.',
    gateNoteDomain: 'الدخول مقصور على الموظفين المعتمدين في نطاق {domains}.',
    gateNote: 'الدخول مقصور على الموظفين المعتمدين.',
    gateHelp: 'للحصول على حساب أو إعادة تعيين كلمة المرور راجع مسؤول النظام.',
    booting: 'جارٍ فتح صندوق البريد…',

    /* المجلدات */
    inbox: 'البريد الوارد', sent: 'المرسل', drafts: 'المسودات',
    trash: 'المحذوفات', junk: 'المزعج', archive: 'الأرشيف',
    foldersTitle: 'مجلدات', newFolder: 'مجلد جديد', newFolderPrompt: 'اسم المجلد الجديد:',
    newFolderExample: 'مثال: العروض', folderCreated: 'أُنشئ المجلد',

    /* القائمة */
    compose: 'رسالة جديدة',
    filterAll: 'الكل', filterUnread: 'غير المقروء', filterStarred: 'المميّز',
    noSubject: '(بدون موضوع)', noSender: '(بدون مرسل)',
    today: 'اليوم', yesterday: 'أمس', thisWeek: 'هذا الأسبوع', thisMonth: 'هذا الشهر', older: 'أقدم',
    emptyFolder: 'لا رسائل هنا', emptyFolderHint: 'هذا المجلد فارغ حاليًا.',
    emptySearch: 'لا نتائج لهذا البحث', emptySearchHint: 'جرّب كلمة أخرى أو ابحث في مجلد مختلف.',
    emptyUnread: 'لا توجد رسائل غير مقروءة', emptyUnreadHint: 'قرأت كل شيء في هذا المجلد.',
    newest: 'أحدث', oldest: 'أقدم',

    /* القارئ */
    pickMessage: 'اختر رسالة لقراءتها', pickMessageHint: 'أو اضغط "رسالة جديدة" للكتابة.',
    to: 'إلى', andOthers: 'و{n} آخرين',
    reply: 'رد', replyAll: 'رد على الجميع', forward: 'تمرير',
    star: 'تمييز', unstar: 'إزالة التمييز', markUnread: 'تعليم كغير مقروء',
    moveTo: 'نقل إلى', moveToFolder: 'نقل إلى مجلد', deleteMsg: 'حذف',
    deleteForever: 'حذف نهائي', more: 'المزيد',
    attachmentsCount: '{n} مرفق',
    imagesBlocked: 'حُجبت {n} صورة خارجية لحماية خصوصيتك.', showImages: 'عرض الصور',
    markedUnread: 'عُلّمت كغير مقروءة', movedToTrash: 'نُقلت إلى المحذوفات',
    deletedForever: 'حُذفت نهائيًا', movedTo: 'نُقلت إلى {folder}',
    saving: 'جارٍ الحفظ…', opening: 'جارٍ الفتح…',
    attachmentSaved: 'حُفظ المرفق', noAppForFile: 'لا يوجد برنامج يفتح هذا الملف',

    /* الكتابة */
    newMessage: 'رسالة جديدة',
    cc: 'نسخة', bcc: 'مخفية', subject: 'الموضوع',
    bodyPlaceholder: 'اكتب رسالتك…',
    send: 'إرسال', sending: 'جارٍ الإرسال…', attach: 'إرفاق',
    saveDraft: 'حفظ كمسودة', discard: 'تجاهل',
    bold: 'عريض', italic: 'مائل', underline: 'تسطير',
    bulletList: 'قائمة نقطية', numberList: 'قائمة مرقّمة', quote: 'اقتباس',
    alignRight: 'محاذاة يمين', alignCenter: 'توسيط', alignLeft: 'محاذاة يسار',
    link: 'رابط', linkPrompt: 'عنوان الرابط:',
    textDirection: 'اتجاه النص (عربي/إنجليزي)',
    zoomIn: 'تكبير الخط', zoomOut: 'تصغير الخط',
    markImportant: 'تعليم كرسالة مهمة', importantOn: 'مهمة — اضغط للإلغاء',
    needRecipient: 'اكتب مستلمًا واحدًا على الأقل',
    attachTooBig: 'حجم المرفقات {size} يتجاوز الحد المسموح ({max})',
    confirmNoSubject: 'إرسال الرسالة بدون موضوع؟',
    confirmDiscard: 'إغلاق الرسالة دون إرسالها؟',
    sentFiled: 'أُرسلت الرسالة وحُفظت نسخة في المرسل', sentOk: 'أُرسلت الرسالة',
    rejected: 'رُفض {n} مستلم: {list}',
    draftSaved: 'حُفظت في المسودات',
    attachmentsSize: '{size} من المرفقات',
    removeFile: 'إزالة', attachFiles: 'إرفاق ملفات',
    quotedOn: 'في {date}، كتب {who}:',
    forwardedHeader: '---------- رسالة ممرّرة ----------',
    fwdFrom: 'من', fwdDate: 'التاريخ', fwdSubject: 'الموضوع', fwdTo: 'إلى',

    /* الإعدادات */
    account: 'الحساب', appearance: 'المظهر', signature: 'التوقيع',
    mailSection: 'البريد', session: 'الجلسة', language: 'اللغة',
    dark: 'داكن', light: 'فاتح',
    displayName: 'الاسم الظاهر للمستلمين',
    displayNameHint: 'يظهر بدل عنوان بريدك في صندوق وارد الطرف الآخر.',
    fullName: 'الاسم الكامل',
    signatureHint: 'يُضاف تلقائيًا أسفل كل رسالة جديدة.',
    notifTitle: 'إشعارات الرسائل الجديدة', notifHint: 'إشعار من النظام عند وصول رسالة.',
    bellTitle: 'جرس التنبيه', bellHint: 'رنين عند وصول رسالة جديدة. اضغط للتجربة.',
    bellTest: 'تجربة الجرس',
    bellSecs: 'مدة الرنين', bellSecsHint: 'كم ثانية يستمر الجرس.', secs: '{n} ث',
    remoteImages: 'تحميل الصور الخارجية تلقائيًا',
    remoteImagesHint: 'إبقاؤه مغلقًا يمنع المُرسِل من معرفة أنك فتحت رسالته.',
    passwordTitle: 'كلمة المرور',
    pwOnServer: 'تغيير كلمة المرور عند مزوّد البريد',
    pwOnServerHint: 'كلمة مرور صندوق البريد تُغيَّر في صفحة المزوّد، ثم تُحدَّث هنا.',
    pwOpenProvider: 'فتح صفحة المزوّد',
    pwUpdateHere: 'تحديث كلمة المرور في التطبيق',
    pwUpdateHereHint: 'بعد تغييرها عند المزوّد، اكتبها هنا ليتابع التطبيق عمله بلا تسجيل خروج.',
    pwCurrent: 'كلمة المرور الحالية',
    pwNew: 'كلمة المرور الجديدة',
    pwConfirm: 'تأكيد كلمة المرور الجديدة',
    pwSave: 'تحديث',
    pwChecking: 'جارٍ التحقق…',
    pwFillAll: 'اكتب كلمة المرور الحالية والجديدة.',
    pwMismatch: 'كلمتا المرور الجديدتان غير متطابقتين.',
    pwUpdated: 'حُدِّثت كلمة المرور',
    signOut: 'تسجيل الخروج', signOutHint: 'يمسح بيانات الدخول المحفوظة على هذا الجهاز.',
    signOutBtn: 'خروج', confirmSignOut: 'تسجيل الخروج ومسح بيانات الدخول من هذا الجهاز؟',
    version: 'بريد أصول البناء — الإصدار {v}',

    /* الاتصال */
    disconnected: 'انقطع الاتصال بخادم البريد — جارٍ إعادة الاتصال…',
    reconnected: 'عاد الاتصال.',
    sessionEnded: 'انتهت الجلسة. سجل الدخول من جديد.',

    /* التصنيفات */
    labels: 'التصنيفات', labelNone: 'بلا تصنيف', labelSet: 'صُنّفت: {name}',
    labelRemoved: 'أُزيل التصنيف', labelPick: 'التصنيف',
    labelsUnsupported: 'خادم البريد لا يدعم التصنيفات.',
    lblProjects: 'مشاريع', lblProcurement: 'مشتريات',
    lblQuality: 'جودة وسلامة', lblHr: 'موارد بشرية', lblFinance: 'مالية',

    /* جهات الاتصال */
    contacts: 'جهات الاتصال', contactsCount: '{n} جهة',
    contactsEmpty: 'لا توجد جهات اتصال بعد',
    contactsEmptyHint: 'يمتلئ الدفتر تلقائيًا ممّن تراسلهم.',
    contactsHint: 'مبني تلقائيًا من الوارد والمرسل.',
    message: 'رسالة', copyEmail: 'نسخ البريد', copied: 'نُسخ',
    backToInbox: 'رجوع للوارد', msgCount: '{n} رسالة',

    /* الذكاء الاصطناعي */
    ai: 'الذكاء الاصطناعي',
    aiDraft: 'اكتب لي الرسالة', aiImprove: 'حسّن الصياغة',
    aiWorking: 'جارٍ الكتابة…',
    aiNeedIdea: 'اكتب فكرة أو طلبًا قصيرًا أولًا، ثم اضغط "اكتب لي الرسالة".',
    aiNeedText: 'اكتب نص الرسالة أولًا حتى أحسّنه.',
    aiNoKey: 'مساعد الذكاء الاصطناعي غير مفعّل. أضف مفتاح OpenAI من الإعدادات.',
    aiFailed: 'تعذّر الوصول إلى مساعد الذكاء الاصطناعي.',
    aiKeyTitle: 'مفتاح OpenAI', aiKeyHint: 'يُحفظ مشفّرًا على هذا الجهاز ولا يُرسل لأي جهة أخرى.',
    aiKeyPlaceholder: 'sk-…', aiKeySaved: 'حُفظ المفتاح', aiKeyCleared: 'أُزيل المفتاح',
    aiDone: 'جاهزة — راجعها قبل الإرسال',
    aiManagedTitle: 'مفعّل من الشركة',
    aiManagedHint: 'المفتاح مضبوط مركزيًا؛ لا حاجة لإدخال شيء.',
    aiOn: 'مفعّل',
  },

  en: {
    appName: 'Osoul Albinaa Mail',
    appTagline: 'OSOUL ALBINAA MAIL',
    ok: 'OK', cancel: 'Cancel', save: 'Save', close: 'Close', back: 'Back',
    search: 'Search mail…', searchResults: 'Search results', clear: 'Clear',
    refresh: 'Refresh', settings: 'Settings', theme: 'Toggle theme',
    loading: 'Loading…', unexpected: 'Something went wrong.',
    appBootFailed: 'The app could not start. Please open it again.',
    unitB: 'B', unitKB: 'KB', unitMB: 'MB', unitGB: 'GB',
    listSep: ', ',

    email: 'Email address', password: 'Password',
    keepSignedIn: 'Keep me signed in',
    signIn: 'Sign in to my mail', checking: 'Checking…',
    showPassword: 'Show password',
    advancedServers: 'Advanced server settings',
    imapServer: 'Incoming server (IMAP)', smtpServer: 'Outgoing server (SMTP)',
    enterEmail: 'Enter your email address.', enterPassword: 'Enter your password.',
    gateNoteDomain: 'Sign-in is limited to approved employees on {domains}.',
    gateNote: 'Sign-in is limited to approved employees.',
    gateHelp: 'For an account or a password reset, contact your administrator.',
    booting: 'Opening your mailbox…',

    inbox: 'Inbox', sent: 'Sent', drafts: 'Drafts',
    trash: 'Trash', junk: 'Spam', archive: 'Archive',
    foldersTitle: 'Folders', newFolder: 'New folder', newFolderPrompt: 'Name of the new folder:',
    newFolderExample: 'e.g. Quotes', folderCreated: 'Folder created',

    compose: 'New message',
    filterAll: 'All', filterUnread: 'Unread', filterStarred: 'Starred',
    noSubject: '(no subject)', noSender: '(no sender)',
    today: 'Today', yesterday: 'Yesterday', thisWeek: 'This week', thisMonth: 'This month', older: 'Older',
    emptyFolder: 'Nothing here', emptyFolderHint: 'This folder is empty.',
    emptySearch: 'No results', emptySearchHint: 'Try another word, or search a different folder.',
    emptyUnread: 'No unread messages', emptyUnreadHint: 'You have read everything in this folder.',
    newest: 'Newer', oldest: 'Older',

    pickMessage: 'Select a message to read', pickMessageHint: 'Or hit “New message” to write one.',
    to: 'To', andOthers: 'and {n} others',
    reply: 'Reply', replyAll: 'Reply all', forward: 'Forward',
    star: 'Star', unstar: 'Remove star', markUnread: 'Mark as unread',
    moveTo: 'Move to', moveToFolder: 'Move to folder', deleteMsg: 'Delete',
    deleteForever: 'Delete permanently', more: 'More',
    attachmentsCount: '{n} attachment(s)',
    imagesBlocked: '{n} remote image(s) blocked to protect your privacy.', showImages: 'Show images',
    markedUnread: 'Marked as unread', movedToTrash: 'Moved to Trash',
    deletedForever: 'Deleted permanently', movedTo: 'Moved to {folder}',
    saving: 'Saving…', opening: 'Opening…',
    attachmentSaved: 'Attachment saved', noAppForFile: 'No app can open this file type',

    newMessage: 'New message',
    cc: 'Cc', bcc: 'Bcc', subject: 'Subject',
    bodyPlaceholder: 'Write your message…',
    send: 'Send', sending: 'Sending…', attach: 'Attach',
    saveDraft: 'Save as draft', discard: 'Discard',
    bold: 'Bold', italic: 'Italic', underline: 'Underline',
    bulletList: 'Bulleted list', numberList: 'Numbered list', quote: 'Quote',
    alignRight: 'Align right', alignCenter: 'Center', alignLeft: 'Align left',
    link: 'Link', linkPrompt: 'Link address:',
    textDirection: 'Text direction (Arabic/English)',
    zoomIn: 'Larger text', zoomOut: 'Smaller text',
    markImportant: 'Mark as important', importantOn: 'Important — click to clear',
    needRecipient: 'Add at least one recipient',
    attachTooBig: 'Attachments are {size}, over the {max} limit',
    confirmNoSubject: 'Send without a subject?',
    confirmDiscard: 'Close this message without sending?',
    sentFiled: 'Sent, and filed a copy in Sent', sentOk: 'Message sent',
    rejected: '{n} recipient(s) rejected: {list}',
    draftSaved: 'Saved to Drafts',
    attachmentsSize: '{size} of attachments',
    removeFile: 'Remove', attachFiles: 'Attach files',
    quotedOn: 'On {date}, {who} wrote:',
    forwardedHeader: '---------- Forwarded message ----------',
    fwdFrom: 'From', fwdDate: 'Date', fwdSubject: 'Subject', fwdTo: 'To',

    account: 'Account', appearance: 'Appearance', signature: 'Signature',
    mailSection: 'Mail', session: 'Session', language: 'Language',
    dark: 'Dark', light: 'Light',
    displayName: 'Name recipients see',
    displayNameHint: 'Shown instead of your address in their inbox.',
    fullName: 'Full name',
    signatureHint: 'Appended to every new message.',
    notifTitle: 'New mail notifications', notifHint: 'A system notification when mail arrives.',
    bellTitle: 'Alert bell', bellHint: 'Rings when new mail arrives. Click to hear it.',
    bellTest: 'Test the bell',
    bellSecs: 'Ring length', bellSecsHint: 'How long the bell keeps ringing.', secs: '{n}s',
    remoteImages: 'Load remote images automatically',
    remoteImagesHint: 'Leaving this off stops senders from knowing you opened their mail.',
    passwordTitle: 'Password',
    pwOnServer: 'Change it at your mail provider',
    pwOnServerHint: 'A mailbox password is changed on the provider’s page, then updated here.',
    pwOpenProvider: 'Open provider page',
    pwUpdateHere: 'Update the password in this app',
    pwUpdateHereHint: 'After changing it at the provider, type it here so the app keeps working without signing out.',
    pwCurrent: 'Current password',
    pwNew: 'New password',
    pwConfirm: 'Confirm new password',
    pwSave: 'Update',
    pwChecking: 'Checking…',
    pwFillAll: 'Enter both the current and the new password.',
    pwMismatch: 'The two new passwords do not match.',
    pwUpdated: 'Password updated',
    signOut: 'Sign out', signOutHint: 'Clears the saved credentials on this computer.',
    signOutBtn: 'Sign out', confirmSignOut: 'Sign out and clear saved credentials on this computer?',
    version: 'Osoul Albinaa Mail — version {v}',

    disconnected: 'Lost the connection to the mail server — reconnecting…',
    reconnected: 'Back online.',
    sessionEnded: 'Session ended. Please sign in again.',

    labels: 'Labels', labelNone: 'No label', labelSet: 'Labelled: {name}',
    labelRemoved: 'Label removed', labelPick: 'Label',
    labelsUnsupported: 'This mail server does not support labels.',
    lblProjects: 'Projects', lblProcurement: 'Procurement',
    lblQuality: 'Quality & safety', lblHr: 'HR', lblFinance: 'Finance',

    contacts: 'Contacts', contactsCount: '{n} contacts',
    contactsEmpty: 'No contacts yet',
    contactsEmptyHint: 'The book fills itself from the people you write to.',
    contactsHint: 'Built automatically from your Inbox and Sent mail.',
    message: 'Message', copyEmail: 'Copy address', copied: 'Copied',
    backToInbox: 'Back to Inbox', msgCount: '{n} messages',

    ai: 'AI assistant',
    aiDraft: 'Write this for me', aiImprove: 'Improve the wording',
    aiWorking: 'Writing…',
    aiNeedIdea: 'Jot down an idea or a short request first, then hit “Write this for me”.',
    aiNeedText: 'Write the message first so I can improve it.',
    aiNoKey: 'The AI assistant is not set up. Add an OpenAI key in Settings.',
    aiFailed: 'Could not reach the AI assistant.',
    aiKeyTitle: 'OpenAI key', aiKeyHint: 'Stored encrypted on this computer and sent nowhere else.',
    aiKeyPlaceholder: 'sk-…', aiKeySaved: 'Key saved', aiKeyCleared: 'Key removed',
    aiDone: 'Ready — read it over before sending',
    aiManagedTitle: 'Enabled by your company',
    aiManagedHint: 'The key is set centrally — nothing to enter here.',
    aiOn: 'On',
  },
};

let lang = 'ar';

/** اللغة الحالية. */
export function getLang() {
  return lang;
}

/** هل الواجهة من اليمين لليسار؟ */
export function isRTL() {
  return lang === 'ar';
}

/** ضبط اللغة وقلب اتجاه الصفحة. لا يعيد الرسم — المتصل مسؤول عن ذلك. */
export function setLang(next) {
  lang = next === 'en' ? 'en' : 'ar';
  const html = document.documentElement;
  html.lang = lang;
  html.dir = isRTL() ? 'rtl' : 'ltr';
  return lang;
}

/**
 * ترجمة مفتاح مع استبدال المتغيّرات: t('movedTo', { folder: 'المرسل' }).
 * مفتاح غير موجود يعود بالعربية ثم بالمفتاح نفسه، فلا تظهر الواجهة فارغة أبدًا.
 */
export function t(key, vars) {
  const table = DICT[lang] || DICT.ar;
  let text = table[key];
  if (text == null) text = DICT.ar[key];
  if (text == null) return key;
  if (!vars) return text;
  return String(text).replace(/\{(\w+)\}/g, (m, name) => (vars[name] == null ? m : String(vars[name])));
}

/** نص خطأ قادم من العملية الرئيسية — يصل بلغتين فنختار المناسبة. */
export function pick(obj) {
  if (!obj) return t('unexpected');
  return (lang === 'en' ? obj.en : obj.ar) || obj.ar || obj.en || t('unexpected');
}

/** اسم مجلد مميّز باللغة الحالية، أو الاسم كما جاء من الخادم. */
export function folderName(folder) {
  if (!folder) return '';
  const map = {
    inbox: 'inbox', sent: 'sent', drafts: 'drafts',
    trash: 'trash', junk: 'junk', archive: 'archive',
  };
  const key = map[folder.special];
  if (key) return t(key);
  return (folder.display && (lang === 'en' ? folder.display.en : folder.display.ar)) || folder.name || folder.raw;
}

/** اسم تصنيف باللغة الحالية. */
export function labelName(slug) {
  const key = { projects: 'lblProjects', procurement: 'lblProcurement',
    quality: 'lblQuality', hr: 'lblHr', finance: 'lblFinance' }[slug];
  return key ? t(key) : slug;
}

/* أسماء اللغات تُكتب دائمًا بلغتها هي: زر التبديل يجب أن يقرأه من لا يعرف
   اللغة الحالية، فالإنجليزي يرى "English" والعربي يرى "العربية". */
const LANG_NAMES = { ar: 'العربية', en: 'English' };
const LANG_SHORT = { ar: 'ع', en: 'EN' };

/** اسم اللغة الأخرى بلغتها — نص زر التبديل. */
export function otherLangName() {
  return LANG_NAMES[lang === 'ar' ? 'en' : 'ar'];
}

/** اختصار اللغة الأخرى — لزر ضيّق في الشريط العلوي. */
export function otherLangShort() {
  return LANG_SHORT[lang === 'ar' ? 'en' : 'ar'];
}

/** اسم لغة بعينها بلغتها — لأزرار الاختيار في الإعدادات. */
export function langName(which) {
  return LANG_NAMES[which] || which;
}

/** الوسم المناسب للتنسيق (التواريخ والأرقام). */
export function locale() {
  return lang === 'en' ? 'en-GB' : 'ar-SA-u-nu-latn-ca-gregory';
}
