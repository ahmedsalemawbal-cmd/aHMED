/**
 * Osoul Mail — نصوص العملية الرئيسية بلغتين.
 *
 * قوائم النظام وحوارات فتح الملفات والإشعارات تُرسم من هنا لا من الواجهة،
 * فتحتاج لغتها الخاصة. تتبع اللغة التي اختارها الموظف في الإعدادات.
 *
 * رسائل الأخطاء ليست هنا: تلك تُرسل إلى الواجهة بلغتين معًا فتختار منها،
 * لأن الخطأ قد يحدث قبل أن تُقرأ الإعدادات أصلًا.
 */

'use strict';

const STRINGS = {
  ar: {
    windowTitle: 'بريد أصول البناء',
    menuFile: 'ملف', menuReload: 'تحديث', menuQuit: 'خروج',
    menuEdit: 'تحرير', menuUndo: 'تراجع', menuRedo: 'إعادة',
    menuCut: 'قص', menuCopy: 'نسخ', menuPaste: 'لصق', menuSelectAll: 'تحديد الكل',
    menuView: 'عرض', menuResetZoom: 'حجم افتراضي', menuZoomIn: 'تكبير',
    menuZoomOut: 'تصغير', menuFullScreen: 'ملء الشاشة',
    attachDialog: 'إرفاق ملفات',
    saveAttachment: 'حفظ المرفق',
    newMessageFallback: 'رسالة جديدة',
    noSubject: '(بدون موضوع)',
    unreadBadge: '{n} رسالة غير مقروءة',
  },
  en: {
    windowTitle: 'Osoul Albinaa Mail',
    menuFile: 'File', menuReload: 'Reload', menuQuit: 'Quit',
    menuEdit: 'Edit', menuUndo: 'Undo', menuRedo: 'Redo',
    menuCut: 'Cut', menuCopy: 'Copy', menuPaste: 'Paste', menuSelectAll: 'Select all',
    menuView: 'View', menuResetZoom: 'Actual size', menuZoomIn: 'Zoom in',
    menuZoomOut: 'Zoom out', menuFullScreen: 'Full screen',
    attachDialog: 'Attach files',
    saveAttachment: 'Save attachment',
    newMessageFallback: 'New message',
    noSubject: '(no subject)',
    unreadBadge: '{n} unread message(s)',
  },
};

/** يُضبط من ipc عند قراءة الإعدادات أو تغييرها. */
let lang = 'ar';

function setLang(next) {
  lang = next === 'en' ? 'en' : 'ar';
  return lang;
}

function getLang() {
  return lang;
}

function s(key, vars) {
  const table = STRINGS[lang] || STRINGS.ar;
  let text = table[key];
  if (text == null) text = STRINGS.ar[key];
  if (text == null) return key;
  if (!vars) return text;
  return String(text).replace(/\{(\w+)\}/g, (m, name) => (vars[name] == null ? m : String(vars[name])));
}

module.exports = { s, setLang, getLang, STRINGS };
