/**
 * Osoul Mail — شهادات الجذر من نظام التشغيل.
 *
 * برامج الحماية وجدران الحماية في الشركات تعترض الاتصالات المشفّرة: تفك
 * التشفير لتفحص المحتوى ثم تعيد تشفيره بشهادة من إصدارها هي. تُثبَّت شهادة
 * جذرها في مخزن ويندوز، فيثق بها المتصفح وأوتلوك وكل برنامج يقرأ من المخزن.
 *
 * لكن Node يحمل قائمة شهادات خاصة به لا يقرأ منها، فيرى شهادة لا يعرفها
 * ويرفض الاتصال بخطأ SELF_SIGNED_CERT_IN_CHAIN — والنتيجة أن البريد لا يفتح
 * على جهاز يفتح فيه كل شيء آخر.
 *
 * الحل: نقرأ جذور النظام ونضيفها إلى قائمة Node. نثق بما يثق به النظام، لا
 * أكثر — ولا نعطّل التحقق من الشهادات إطلاقًا.
 */

'use strict';

const tls = require('tls');
const { spawnSync } = require('child_process');

const READ_TIMEOUT = 8000;
const PEM = /-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/g;

let cached = null;

/** تشغيل أمر قراءة واحد. أي فشل يعني "لا جذور إضافية"، لا تعطّل التطبيق. */
function run(cmd, args) {
  try {
    const res = spawnSync(cmd, args, {
      timeout: READ_TIMEOUT,
      maxBuffer: 8 * 1024 * 1024,
      encoding: 'utf8',
      windowsHide: true,
    });
    return res && res.status === 0 ? String(res.stdout || '') : '';
  } catch (_) {
    return '';
  }
}

/** جذور ويندوز: مخزن الجهاز ومخزن المستخدم. */
function windowsRoots() {
  const script = [
    '$ErrorActionPreference = "SilentlyContinue";',
    'foreach ($scope in @("LocalMachine", "CurrentUser")) {',
    '  Get-ChildItem "Cert:\\$scope\\Root" | ForEach-Object {',
    '    "-----BEGIN CERTIFICATE-----";',
    '    [Convert]::ToBase64String($_.RawData, "InsertLineBreaks");',
    '    "-----END CERTIFICATE-----";',
    '  }',
    '}',
  ].join(' ');
  return run('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', script]);
}

/** جذور ماك: سلسلة مفاتيح النظام وسلسلة المستخدم. */
function macRoots() {
  const chains = [
    '/System/Library/Keychains/SystemRootCertificates.keychain',
    '/Library/Keychains/System.keychain',
  ];
  let out = chains.map((c) => run('/usr/bin/security', ['find-certificate', '-a', '-p', c])).join('\n');
  out += `\n${run('/usr/bin/security', ['find-certificate', '-a', '-p'])}`;
  return out;
}

/**
 * قائمة الثقة الكاملة: جذور Node المدمجة + جذور النظام.
 *
 * تُقرأ مرة واحدة وتُخزَّن: قراءة المخزن تستغرق جزءًا من الثانية ولا تتغيّر
 * أثناء تشغيل التطبيق.
 *
 * @returns {string[]} شهادات PEM
 */
function bundle() {
  if (cached) return cached;

  const builtin = tls.rootCertificates || [];
  let extra = '';
  if (process.platform === 'win32') extra = windowsRoots();
  else if (process.platform === 'darwin') extra = macRoots();

  const found = extra.match(PEM) || [];
  // مخازن النظام تكرّر الجذور الشائعة؛ التكرار يُبطئ بناء السلسلة بلا فائدة.
  const seen = new Set(builtin);
  const added = [];
  for (const pem of found) {
    const clean = pem.trim();
    if (seen.has(clean)) continue;
    seen.add(clean);
    added.push(clean);
  }

  cached = [...builtin, ...added];
  cached.systemAdded = added.length;
  return cached;
}

/** عدد الجذور التي جاءت من النظام — للتشخيص. */
function systemCount() {
  return bundle().systemAdded || 0;
}

module.exports = { bundle, systemCount };
