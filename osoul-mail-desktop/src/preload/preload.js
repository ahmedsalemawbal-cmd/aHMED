/**
 * Osoul Mail — سطح التخاطب المسموح للواجهة.
 *
 * لا شيء من Node يصل إلى الصفحة: هذه القائمة الصريحة هي كل ما تستطيع الواجهة
 * طلبه. أي شيء خارجها ببساطة غير موجود بالنسبة لها.
 */

'use strict';

const { contextBridge, ipcRenderer } = require('electron');

const invoke = (channel, payload) => ipcRenderer.invoke(channel, payload);

/** الأحداث التي ترسلها العملية الرئيسية إلى الواجهة. */
const EVENTS = ['conn:state', 'conn:warning', 'mail:new', 'mail:changed', 'mail:open'];

contextBridge.exposeInMainWorld('osoul', {
  /* إقلاع ودخول */
  boot: () => invoke('app:boot'),
  login: (payload) => invoke('auth:login', payload),
  resume: () => invoke('auth:resume'),
  logout: (payload) => invoke('auth:logout', payload || {}),

  /* صندوق البريد */
  folders: (force) => invoke('mail:folders', { force: !!force }),
  list: (payload) => invoke('mail:list', payload),
  message: (payload) => invoke('mail:message', payload),
  flag: (payload) => invoke('mail:flag', payload),
  remove: (payload) => invoke('mail:delete', payload),
  move: (payload) => invoke('mail:move', payload),
  unseen: (folder) => invoke('mail:unseen', { folder }),
  quota: () => invoke('mail:quota'),
  createFolder: (name) => invoke('mail:folderCreate', { name }),

  /* الإرسال والمرفقات */
  send: (payload) => invoke('mail:send', payload),
  saveDraft: (payload) => invoke('mail:saveDraft', payload),
  pickFiles: () => invoke('compose:pickFiles'),
  saveAttachment: (payload) => invoke('mail:attachmentSave', payload),
  openAttachment: (payload) => invoke('mail:attachmentOpen', payload),

  /* الإعدادات والواجهة */
  getSettings: () => invoke('settings:get'),
  saveSettings: (payload) => invoke('settings:save', payload),
  setTheme: (theme) => invoke('ui:theme', { theme }),
  setBadge: (payload) => invoke('ui:badge', payload),
  openExternal: (url) => invoke('shell:openExternal', { url }),

  /**
   * الاشتراك في حدث. يعيد دالة لإلغاء الاشتراك.
   * القناة تُتحقق من قائمة ثابتة حتى لا تُستخدم لتنصت عام.
   */
  on: (channel, handler) => {
    if (!EVENTS.includes(channel) || typeof handler !== 'function') return () => {};
    const listener = (_event, payload) => handler(payload);
    ipcRenderer.on(channel, listener);
    return () => ipcRenderer.removeListener(channel, listener);
  },
});
