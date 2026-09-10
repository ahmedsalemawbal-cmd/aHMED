/**
 * Osoul Mail — خزنة بيانات الدخول والإعدادات.
 *
 * كلمة مرور البريد يجب أن تبقى قابلة لفك التشفير لأن IMAP/SMTP يطلبانها نصًا
 * في كل اتصال. لذلك نستخدم safeStorage الذي يعتمد على DPAPI في ويندوز:
 * التشفير مربوط بحساب المستخدم على الجهاز، فلا يمكن نسخ الملف واستخدامه على
 * جهاز آخر. إن تعذّر التشفير لأي سبب لا نحفظ كلمة المرور إطلاقًا.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { app, safeStorage } = require('electron');

const FILES = { creds: 'credentials.dat', settings: 'settings.json', ai: 'ai-key.dat' };

function filePath(name) {
  return path.join(app.getPath('userData'), name);
}

function readJSON(name, fallback) {
  try {
    const raw = fs.readFileSync(filePath(name), 'utf8');
    const val = JSON.parse(raw);
    return val && typeof val === 'object' ? val : fallback;
  } catch (_) {
    return fallback;
  }
}

function writeJSON(name, value) {
  try {
    fs.mkdirSync(app.getPath('userData'), { recursive: true });
    fs.writeFileSync(filePath(name), JSON.stringify(value, null, 2), { mode: 0o600 });
    return true;
  } catch (_) {
    return false;
  }
}

/* ---------------------------------------------------------------- الإعدادات */

const SETTINGS_DEFAULTS = {
  theme: 'dark',
  lang: 'ar',
  density: 'cozy',
  signature: '',
  fromName: '',
  showRemoteImages: false,
  notifications: true,
  sound: true,
  soundSeconds: 5,
  lastFolder: 'INBOX',
  windowBounds: null,
};

function getSettings() {
  return { ...SETTINGS_DEFAULTS, ...readJSON(FILES.settings, {}) };
}

function saveSettings(patch) {
  const next = { ...getSettings(), ...(patch || {}) };
  writeJSON(FILES.settings, next);
  return next;
}

/* ----------------------------------------------------------- خزنة الاعتماد */

/** هل التشفير المرتبط بنظام التشغيل متاح؟ */
function canEncrypt() {
  try {
    return safeStorage.isEncryptionAvailable();
  } catch (_) {
    return false;
  }
}

/**
 * حفظ بيانات الدخول (تذكّرني). لا نكتب شيئًا إذا لم يتوفّر تشفير النظام.
 * @param {{email:string,password:string,imapHost:string,imapPort:number,smtpHost:string,smtpPort:number,fromName?:string}} account
 * @returns {boolean} هل تم الحفظ فعلًا
 */
function saveAccount(account) {
  if (!canEncrypt()) return false;
  try {
    const payload = JSON.stringify(account);
    const blob = safeStorage.encryptString(payload).toString('base64');
    fs.mkdirSync(app.getPath('userData'), { recursive: true });
    fs.writeFileSync(filePath(FILES.creds), blob, { mode: 0o600 });
    return true;
  } catch (_) {
    return false;
  }
}

/** استرجاع بيانات الدخول المحفوظة، أو null. */
function loadAccount() {
  if (!canEncrypt()) return null;
  try {
    const blob = fs.readFileSync(filePath(FILES.creds), 'utf8');
    const json = safeStorage.decryptString(Buffer.from(blob, 'base64'));
    const account = JSON.parse(json);
    return account && account.email && account.password ? account : null;
  } catch (_) {
    return null;
  }
}

/** مسح بيانات الدخول عند تسجيل الخروج. */
function clearAccount() {
  try {
    fs.unlinkSync(filePath(FILES.creds));
  } catch (_) {
    /* الملف غير موجود أصلًا */
  }
}

/* ----------------------------------------------------- مفتاح الذكاء الاصطناعي
 *
 * مفتاح OpenAI يُخزَّن بنفس تشفير كلمة المرور (DPAPI في ويندوز): سرّ قابل
 * لإعادة الاستخدام، فلا يصلح تجزئته، ولا يجوز تركه نصًا صريحًا.
 */

/** حفظ المفتاح (سلسلة فارغة تمسحه). يعود false إن تعذّر التشفير. */
function saveAiKey(key) {
  const clean = String(key || '').trim();
  if (!clean) {
    try { fs.unlinkSync(filePath(FILES.ai)); } catch (_) { /* غير موجود */ }
    return true;
  }
  if (!canEncrypt()) return false;
  try {
    const blob = safeStorage.encryptString(clean).toString('base64');
    fs.mkdirSync(app.getPath('userData'), { recursive: true });
    fs.writeFileSync(filePath(FILES.ai), blob, { mode: 0o600 });
    return true;
  } catch (_) {
    return false;
  }
}

/** المفتاح المحفوظ، أو '' — يُفضَّل مفتاح السياسة العامة إن وُجد. */
function loadAiKey(policyKey) {
  const fromPolicy = String(policyKey || '').trim();
  if (fromPolicy) return fromPolicy;
  if (!canEncrypt()) return '';
  try {
    const blob = fs.readFileSync(filePath(FILES.ai), 'utf8');
    return safeStorage.decryptString(Buffer.from(blob, 'base64'));
  } catch (_) {
    return '';
  }
}

module.exports = {
  saveAiKey,
  loadAiKey,
  getSettings,
  saveSettings,
  saveAccount,
  loadAccount,
  clearAccount,
  canEncrypt,
};
