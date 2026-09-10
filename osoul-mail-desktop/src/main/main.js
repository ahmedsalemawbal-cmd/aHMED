/**
 * Osoul Mail — نقطة الدخول للتطبيق على ويندوز.
 *
 * الواجهة تُقدَّم عبر بروتوكول داخلي (osoul://) بدل file:// حتى يكون للصفحة
 * أصل حقيقي: بذلك تعمل سياسة أمن المحتوى (CSP) كما ينبغي، ويستطيع التطبيق
 * قياس إطار عرض الرسالة والتحكّم بروابطها.
 */

'use strict';

const path = require('path');
const fs = require('fs');
const { app, BrowserWindow, shell, protocol, net, nativeTheme, Menu } = require('electron');

const store = require('./store');
const { loadPolicy } = require('./config');
const { registerIPC, teardownSession } = require('./ipc');
const { WINDOW_BG } = require('./theme');
const { s: T, setLang: setMainLang } = require('./strings');

const RENDERER_DIR = path.join(__dirname, '..', 'renderer');
const SCHEME = 'osoul';
const isDev = process.argv.includes('--dev');

/** نسخة واحدة فقط: تشغيل ثانٍ يُظهر النافذة القائمة. */
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  bootstrap();
}

function bootstrap() {
  protocol.registerSchemesAsPrivileged([
    { scheme: SCHEME, privileges: { standard: true, secure: true, supportFetchAPI: true, corsEnabled: false } },
  ]);

  app.on('second-instance', () => {
    const win = BrowserWindow.getAllWindows()[0];
    if (!win) return;
    if (win.isMinimized()) win.restore();
    win.focus();
  });

  app.whenReady().then(async () => {
    // معرّف ثابت حتى تظهر الإشعارات باسم التطبيق لا باسم Electron.
    app.setAppUserModelId('com.osoulalbinaa.mail');

    registerProtocol();

    const policy = loadPolicy(app.getPath('userData'));
    setMainLang(store.getSettings().lang);
    Menu.setApplicationMenu(buildMenu());
    const win = createWindow();
    registerIPC({ win, policy });

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
  });

  app.on('window-all-closed', async () => {
    await teardownSession();
    app.quit();
  });

  app.on('before-quit', async () => {
    await teardownSession();
  });
}

/* ------------------------------------------------------- بروتوكول الواجهة */

function registerProtocol() {
  protocol.handle(SCHEME, (request) => {
    const url = new URL(request.url);
    // osoul://app/<path> → src/renderer/<path>
    let rel = decodeURIComponent(url.pathname).replace(/^\/+/, '');
    if (!rel || rel.endsWith('/')) rel = 'index.html';

    const full = path.join(RENDERER_DIR, rel);
    // منع الخروج من مجلد الواجهة مهما كان المسار المطلوب.
    if (!full.startsWith(RENDERER_DIR)) {
      return new Response('Forbidden', { status: 403 });
    }
    if (!fs.existsSync(full)) {
      return new Response('Not found', { status: 404 });
    }
    return net.fetch(`file://${full.replace(/\\/g, '/')}`);
  });
}

/* --------------------------------------------------------------- النافذة */

function createWindow() {
  const settings = store.getSettings();
  const bounds = settings.windowBounds || {};
  const dark = settings.theme !== 'light';

  const win = new BrowserWindow({
    width: bounds.width || 1280,
    height: bounds.height || 820,
    x: bounds.x,
    y: bounds.y,
    minWidth: 940,
    minHeight: 600,
    show: false,
    backgroundColor: dark ? WINDOW_BG.dark : WINDOW_BG.light,
    autoHideMenuBar: true,
    title: T('windowTitle'),
    icon: path.join(__dirname, '..', '..', 'build', 'icon.png'),
    // إطار النظام القياسي: أزرار النافذة تبقى حيث يتوقعها المستخدم مهما كان
    // اتجاه الواجهة أو لغة ويندوز — شريط عنوان مخصص يتصادم مع تخطيط RTL.
    webPreferences: {
      preload: path.join(__dirname, '..', 'preload', 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      webviewTag: false,
      spellcheck: true,
      // إطار عرض الرسالة يحتاج أصلًا مشتركًا لقياس ارتفاعه واعتراض روابطه.
      allowRunningInsecureContent: false,
    },
  });

  if (bounds.maximized) win.maximize();

  win.once('ready-to-show', () => win.show());
  win.loadURL(`${SCHEME}://app/index.html`);

  // أي رابط خارجي يفتح في المتصفح، ولا تُفتح نوافذ Electron جديدة أبدًا.
  win.webContents.setWindowOpenHandler(({ url }) => {
    openExternal(url);
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', (event, url) => {
    if (!url.startsWith(`${SCHEME}://`)) {
      event.preventDefault();
      openExternal(url);
    }
  });
  // لا صلاحيات جهاز (كاميرا/ميكروفون/موقع) — التطبيق لا يحتاج شيئًا منها.
  win.webContents.session.setPermissionRequestHandler((_wc, _perm, cb) => cb(false));

  const persist = debounce(() => {
    if (win.isDestroyed()) return;
    const b = win.getNormalBounds();
    store.saveSettings({ windowBounds: { ...b, maximized: win.isMaximized() } });
  }, 500);
  win.on('resize', persist);
  win.on('move', persist);
  win.on('maximize', persist);
  win.on('unmaximize', persist);

  nativeTheme.themeSource = dark ? 'dark' : 'light';

  if (isDev) win.webContents.openDevTools({ mode: 'detach' });

  return win;
}

/** فتح رابط خارجي — بروتوكولات الويب والبريد فقط. */
function openExternal(url) {
  try {
    const u = new URL(url);
    if (['http:', 'https:', 'mailto:'].includes(u.protocol)) shell.openExternal(url);
  } catch (_) {
    /* رابط تالف داخل رسالة — نتجاهله */
  }
}

/* ---------------------------------------------------------------- القائمة */

function buildMenu() {
  return Menu.buildFromTemplate([
    {
      label: T('menuFile'),
      submenu: [
        { role: 'reload', label: T('menuReload') },
        { type: 'separator' },
        { role: 'quit', label: T('menuQuit') },
      ],
    },
    {
      label: T('menuEdit'),
      submenu: [
        { role: 'undo', label: T('menuUndo') },
        { role: 'redo', label: T('menuRedo') },
        { type: 'separator' },
        { role: 'cut', label: T('menuCut') },
        { role: 'copy', label: T('menuCopy') },
        { role: 'paste', label: T('menuPaste') },
        { role: 'selectAll', label: T('menuSelectAll') },
      ],
    },
    {
      label: T('menuView'),
      submenu: [
        { role: 'resetZoom', label: T('menuResetZoom') },
        { role: 'zoomIn', label: T('menuZoomIn') },
        { role: 'zoomOut', label: T('menuZoomOut') },
        { type: 'separator' },
        { role: 'togglefullscreen', label: T('menuFullScreen') },
      ],
    },
  ]);
}

module.exports = { buildMenu };

function debounce(fn, ms) {
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}
