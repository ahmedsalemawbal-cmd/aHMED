/**
 * Osoul Mail — صفحة مزوّد البريد داخل التطبيق.
 *
 * كلمة مرور الصندوق يملكها المزوّد: لا IMAP ولا واجهة Hostinger البرمجية
 * تملك أمرًا لتغييرها (تسعة عشر أمرًا فيها للرسائل والمجلدات والحصة
 * والإرسال، وليس فيها كلمة مرور). الموظف يغيّرها بنفسه من بريد الويب.
 *
 * كان التطبيق يفتح المتصفح ويترك الموظف هناك، فينقطع العمل ويعود بلا شيء
 * يربط ما فعله بالتطبيق. صار يفتحها في نافذة تابعة، فيبقى في مكان واحد،
 * وحين يغلقها يُطلب منه تحديثها هنا مباشرة.
 *
 * احترازات النافذة — فيها يكتب الموظف كلمة مرور، فلا تُعامَل كصفحة عادية:
 *   • جلسة معزولة تمامًا عن جلسة التطبيق وعن أي بيانات فيه
 *   • بلا preload وبلا Node: الصفحة لا تملك جسرًا إلى التطبيق إطلاقًا
 *   • التنقّل محبوس في نطاق المزوّد؛ أي رابط خارجي يخرج للمتصفح
 *   • العنوان الحقيقي معروض في شريط النافذة ليراه الموظف ويتحقق منه
 */

'use strict';

const { BrowserWindow, shell, session } = require('electron');

const PARTITION = 'persist:osoul-provider';

/** هل هذا العنوان داخل نطاق المزوّد نفسه (أو نطاق فرعي منه)؟ */
function sameSite(url, baseHost) {
  try {
    const host = new URL(url).hostname.toLowerCase();
    const base = String(baseHost).toLowerCase();
    return host === base || host.endsWith(`.${base}`);
  } catch (_) {
    return false;
  }
}

/**
 * فتح صفحة المزوّد وانتظار إغلاقها.
 *
 * @param {BrowserWindow} parent
 * @param {string} url
 * @param {string} title
 * @returns {Promise<{opened:boolean}>}
 */
function openPasswordPage(parent, url, title) {
  let base;
  try {
    base = new URL(url);
  } catch (_) {
    return Promise.resolve({ opened: false });
  }
  if (!['http:', 'https:'].includes(base.protocol)) return Promise.resolve({ opened: false });

  const view = session.fromPartition(PARTITION);
  // الصفحة لا تحتاج كاميرا ولا ميكروفون ولا موقعًا لتغيير كلمة مرور.
  view.setPermissionRequestHandler((_wc, _perm, cb) => cb(false));

  const win = new BrowserWindow({
    parent,
    modal: false,
    width: 1060,
    height: 780,
    minWidth: 720,
    minHeight: 560,
    title,
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      session: view,
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      webviewTag: false,
      // لا preload: لا جسر بين صفحة المزوّد وبين التطبيق بأي حال.
    },
  });

  // العنوان الحقيقي ظاهر دائمًا: نافذة بلا شريط عنوان تخفي أين يكتب الموظف
  // كلمة مروره، وهذا ما تستغلّه صفحات التصيّد.
  const showUrl = () => {
    const current = win.webContents.getURL();
    win.setTitle(`${title} — ${current}`);
  };
  win.webContents.on('did-navigate', showUrl);
  win.webContents.on('did-navigate-in-page', showUrl);
  win.webContents.on('page-title-updated', (e) => e.preventDefault());

  win.webContents.on('will-navigate', (event, next) => {
    if (sameSite(next, base.hostname)) return;
    event.preventDefault();
    shell.openExternal(next).catch(() => {});
  });
  win.webContents.setWindowOpenHandler(({ url: next }) => {
    if (sameSite(next, base.hostname)) return { action: 'allow' };
    shell.openExternal(next).catch(() => {});
    return { action: 'deny' };
  });

  win.once('ready-to-show', () => win.show());
  win.loadURL(url);

  return new Promise((resolve) => {
    win.on('closed', () => resolve({ opened: true }));
  });
}

module.exports = { openPasswordPage, sameSite, PARTITION };
