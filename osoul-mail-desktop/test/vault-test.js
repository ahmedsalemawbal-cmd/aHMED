/* اختبار خزنة الاعتماد. safeStorage غير متاح في هذه الحاوية (لا keyring)،
   وهو متاح دائمًا في ويندوز عبر DPAPI، فنحاكي واجهته للتحقق من منطق التخزين. */
'use strict';
const fs = require('fs');
const path = require('path');
const { app, safeStorage } = require('electron');

const out = {};
let failures = 0;
const check = (n, c, d) => { if (c) out[n] = 'PASS'; else { out[n] = `FAIL ${JSON.stringify(d)}`; failures++; } };

app.whenReady().then(() => {
  const userData = app.getPath('userData');
  const credFile = path.join(userData, 'credentials.dat');
  try { fs.unlinkSync(credFile); } catch (_) {}

  const store = require('../src/main/store.js');

  /* --- 1) بلا تشفير: لا يُكتب شيء إطلاقًا --- */
  check('vault.refusesWithoutEncryption', store.saveAccount({ email: 'a@b.com', password: 'p' }) === false);
  check('vault.noFileWritten', !fs.existsSync(credFile));
  check('vault.loadReturnsNull', store.loadAccount() === null);

  /* --- 2) محاكاة DPAPI --- */
  safeStorage.isEncryptionAvailable = () => true;
  safeStorage.encryptString = (s) => Buffer.concat([Buffer.from('DPAPI:'), Buffer.from(s, 'utf8')]);
  safeStorage.decryptString = (b) => Buffer.from(b).subarray(6).toString('utf8');

  const account = {
    email: 'ahmed@osoulalbinaa.com', password: 'p@ss wörd — عربي',
    imapHost: 'imap.hostinger.com', imapPort: 993,
    smtpHost: 'smtp.hostinger.com', smtpPort: 465, fromName: 'Ahmed Salem',
  };

  check('vault.saves', store.saveAccount(account) === true);
  check('vault.fileExists', fs.existsSync(credFile));

  const raw = fs.readFileSync(credFile, 'utf8');
  check('vault.notPlaintext', !raw.includes('p@ss') && !raw.includes('عربي'), raw.slice(0, 40));

  const mode = fs.statSync(credFile).mode & 0o777;
  check('vault.filePermissions', mode === 0o600, mode.toString(8));

  const loaded = store.loadAccount();
  check('vault.roundTrip', JSON.stringify(loaded) === JSON.stringify(account), loaded);
  check('vault.unicodePassword', loaded.password === account.password, loaded && loaded.password);

  store.clearAccount();
  check('vault.cleared', !fs.existsSync(credFile));
  check('vault.clearIdempotent', (() => { store.clearAccount(); return true; })());

  /* --- 3) ملف تالف لا يُسقط التطبيق --- */
  fs.writeFileSync(credFile, 'not-valid-base64-@@@');
  check('vault.corruptFileSafe', store.loadAccount() === null);
  try { fs.unlinkSync(credFile); } catch (_) {}

  /* --- 4) الإعدادات --- */
  const s1 = store.saveSettings({ theme: 'light', signature: 'أحمد' });
  check('settings.saved', s1.theme === 'light' && s1.signature === 'أحمد', s1);
  const s2 = store.saveSettings({ notifications: false });
  check('settings.merged', s2.theme === 'light' && s2.notifications === false, s2);
  check('settings.defaults', store.getSettings().lang === 'ar');

  console.log('VAULT ' + JSON.stringify(out, null, 1));
  console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
  app.exit(failures ? 1 : 0);
});
