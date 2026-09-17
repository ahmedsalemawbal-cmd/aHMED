/* لقطات من التطبيق الحقيقي على خوادم وهمية — للتحقق البصري من الصقل. */
'use strict';
const fs = require('fs');
const path = require('path');
const { app } = require('electron');

const OUT = process.env.SHOT_DIR;
const IMAP_PORT = 22993; const SMTP_PORT = 22465;
const userData = app.getPath('userData');
fs.mkdirSync(userData, { recursive: true });
fs.writeFileSync(path.join(userData, 'policy.json'), JSON.stringify({
  allowedDomains: ['osoulalbinaa.com'],
  imapHost: '127.0.0.1', imapPort: IMAP_PORT, imapSecure: false,
  smtpHost: '127.0.0.1', smtpPort: SMTP_PORT, smtpSecure: false,
  refreshSeconds: 0, pageSize: 12,
}));
try { fs.unlinkSync(path.join(userData, 'credentials.dat')); } catch (_) {}
try { fs.unlinkSync(path.join(userData, 'settings.json')); } catch (_) {}

const { startServer: startImap } = require(path.join(__dirname, 'fake-imap.js'));
const { startSmtp } = require(path.join(__dirname, 'fake-smtp.js'));
require(path.join(__dirname, '..', 'src', 'main', 'main.js'));
const { BrowserWindow } = require('electron');

(async () => {
  await startImap({ port: IMAP_PORT });
  await startSmtp({ port: SMTP_PORT });
  await app.whenReady();
  await new Promise((r) => setTimeout(r, 2500));
  const w = BrowserWindow.getAllWindows()[0];
  w.setSize(1360, 880);
  const run = (js) => w.webContents.executeJavaScript(js);
  const shot = async (name) => {
    await new Promise((r) => setTimeout(r, 600));
    const img = await w.webContents.capturePage();
    fs.writeFileSync(path.join(OUT, `${name}.png`), img.toPNG());
    console.log('shot', name);
  };

  await shot('1-gate');
  await run(`(async () => {
    document.getElementById('lg-email').value = 'ahmed@osoulalbinaa.com';
    document.getElementById('lg-pass').value = 'secret';
    document.querySelector('form.gate').requestSubmit();
    for (let i = 0; i < 60 && document.getElementById('app').hidden; i++) await new Promise(r => setTimeout(r, 200));
  })()`);
  await shot('2-inbox');

  await run(`(async () => {
    const mail = await import('./js/mail.js');
    await mail.openMessage(101, 'INBOX');
    await new Promise(r => setTimeout(r, 900));
  })()`);
  await shot('3-reader');

  await run(`(async () => {
    const mail = await import('./js/mail.js');
    const c = await import('./js/compose.js');
    c.openCompose({ mode: 'new' }, mail.S, null, null);
    await new Promise(r => setTimeout(r, 400));
    const to = document.getElementById('c-to');
    to.value = 'محمد';
    to.setSelectionRange(4, 4);
    to.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise(r => setTimeout(r, 300));
  })()`);
  await shot('4-picker');

  await run(`document.getElementById('c-close').click()`);
  await run(`(async () => {
    const mail = await import('./js/mail.js');
    const s = await import('./js/settings.js');
    s.openSettings(mail.S, { onChange: mail.applySettings, onLogout: () => {} });
    await new Promise(r => setTimeout(r, 400));
    const steps = document.querySelector('.sheet .steps');
    if (steps) steps.scrollIntoView({ block: 'center' });
    await new Promise(r => setTimeout(r, 400));
  })()`);
  await shot('5-settings-password');

  await run(`document.getElementById('st-close').click()`);
  await run(`(async () => { document.getElementById('s-contacts').click(); await new Promise(r=>setTimeout(r,1600)); })()`);
  await shot('6-contacts');

  await run(`(async () => {
    const mail = await import('./js/mail.js');
    mail.applySettings({ theme: 'light' });
    await new Promise(r => setTimeout(r, 400));
    await mail.loadList({ folder: 'INBOX', search: '', filter: '' });
    await new Promise(r => setTimeout(r, 600));
  })()`);
  await shot('7-light');

  app.exit(0);
})();
