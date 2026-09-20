/**
 * Osoul Mail — قائمة الزر الأيمن.
 *
 * Electron لا يرسم قائمة سياق افتراضية: الصفحة تُظهر تحديدًا بالفأرة ثم لا
 * يجد الموظف ما ينسخ به إلا لوحة المفاتيح. نرسمها هنا.
 *
 * الحدث يصل من الصفحة ومن إطار عرض الرسالة معًا، فينسخ الموظف من الرسالة
 * نفسها كما ينسخ من أي بريد آخر.
 */

'use strict';

const { Menu, clipboard, shell } = require('electron');
const { s: T } = require('./strings');

/** أول خمسة اقتراحات إملائية تكفي؛ ما بعدها قائمة لا تُقرأ. */
const MAX_SUGGESTIONS = 5;

function attach(contents) {
  contents.on('context-menu', (_event, params) => {
    const items = [];
    const push = (item) => items.push(item);
    const sep = () => {
      if (items.length && items[items.length - 1].type !== 'separator') push({ type: 'separator' });
    };

    // تصحيح إملائي: يأتي أولًا لأنه ما يُقصد غالبًا حين يُنقر على كلمة محمرّة.
    if (params.isEditable && params.misspelledWord) {
      for (const word of (params.dictionarySuggestions || []).slice(0, MAX_SUGGESTIONS)) {
        push({ label: word, click: () => contents.replaceMisspelling(word) });
      }
      if (!params.dictionarySuggestions || !params.dictionarySuggestions.length) {
        push({ label: T('ctxNoSuggestions'), enabled: false });
      }
      sep();
      push({
        label: T('ctxAddToDictionary'),
        click: () => contents.session.addWordToSpellCheckerDictionary(params.misspelledWord),
      });
      sep();
    }

    if (params.linkURL) {
      push({ label: T('ctxOpenLink'), click: () => shell.openExternal(params.linkURL).catch(() => {}) });
      push({ label: T('ctxCopyLink'), click: () => clipboard.writeText(params.linkURL) });
      sep();
    }

    if (params.mediaType === 'image' && params.srcURL) {
      push({ label: T('ctxCopyImage'), click: () => contents.copyImageAt(params.x, params.y) });
      sep();
    }

    if (params.isEditable) {
      push({ role: 'undo', label: T('menuUndo'), enabled: params.editFlags.canUndo });
      push({ role: 'redo', label: T('menuRedo'), enabled: params.editFlags.canRedo });
      sep();
      push({ role: 'cut', label: T('menuCut'), enabled: params.editFlags.canCut });
    }

    // النسخ متاح كلما كان هناك تحديد، داخل الحقول وخارجها — وهذا هو المقصد
    // الأول من الزر الأيمن في صفحة بريد.
    push({ role: 'copy', label: T('menuCopy'), enabled: !!params.selectionText });

    if (params.isEditable) {
      push({ role: 'paste', label: T('menuPaste'), enabled: params.editFlags.canPaste });
      push({ role: 'pasteAndMatchStyle', label: T('ctxPastePlain'), enabled: params.editFlags.canPaste });
    }

    sep();
    push({ role: 'selectAll', label: T('menuSelectAll') });

    // بحث في البريد عن النص المحدَّد — اختصار يوفّر نسخًا ولصقًا.
    const selection = String(params.selectionText || '').trim();
    if (selection && selection.length <= 80) {
      sep();
      push({
        label: T('ctxSearchMail', { text: selection.length > 28 ? `${selection.slice(0, 28)}…` : selection }),
        click: () => contents.send('ui:search', { text: selection }),
      });
    }

    Menu.buildFromTemplate(items).popup({ window: contents.getOwnerBrowserWindow() || undefined });
  });
}

module.exports = { attach };
