/**
 * Osoul Mail — سياسة التشغيل وإعدادات الخوادم الافتراضية.
 *
 * الموظف لا يكتب سوى البريد وكلمة المرور؛ كل ما عدا ذلك مُعبّأ مسبقًا هنا
 * (نفس إعدادات Hostinger المستخدمة في نسخة الويب) حتى تبقى بوابة الدخول نظيفة.
 *
 * يمكن لمسؤول تقنية المعلومات تعديل السياسة دون إعادة بناء التطبيق عبر ملف
 * policy.json بجانب ملفات التطبيق أو داخل مجلد بيانات المستخدم.
 */

'use strict';

const fs = require('fs');
const path = require('path');

/** إعدادات الخوادم الافتراضية — مطابقة لـ osoul_mail_server_defaults() في الموقع. */
const SERVER_DEFAULTS = Object.freeze({
  imapHost: 'imap.hostinger.com',
  imapPort: 993,
  imapSecure: true,
  smtpHost: 'smtp.hostinger.com',
  smtpPort: 465,
  smtpSecure: true,
});

/** السياسة الافتراضية: بوابة الدخول مقصورة على بريد الشركة (الموظفون المعتمدون). */
const POLICY_DEFAULTS = Object.freeze({
  // نطاقات البريد المسموح لها بالدخول. مصفوفة فارغة = السماح لأي نطاق.
  allowedDomains: ['osoulalbinaa.com'],
  // نقطة تحقق اختيارية من كشف الموظفين المعتمدين على الخادم.
  // اتركها فارغة وسيكتفي التطبيق بالنطاق + تحقق IMAP الحقيقي.
  rosterUrl: '',
  // السماح للموظف بفتح "إعدادات متقدمة" وتغيير خوادم البريد.
  allowAdvancedServers: true,
  // تحديث صندوق الوارد تلقائيًا (ثانية). 0 = الاعتماد على IDLE فقط.
  refreshSeconds: 120,
  // عدد الرسائل في الصفحة الواحدة.
  pageSize: 50,
  ...SERVER_DEFAULTS,
});

let cached = null;

/** قراءة policy.json إن وُجد، ودمجه فوق الافتراضيات. */
function loadPolicy(userDataDir) {
  if (cached) return cached;

  const candidates = [
    // 1) بجانب ملفات التطبيق المثبّتة (يضبطها مسؤول النظام مرة واحدة)
    path.join(process.resourcesPath || '', 'policy.json'),
    // 2) بجانب المصدر أثناء التطوير
    path.join(__dirname, '..', '..', 'policy.json'),
    // 3) داخل مجلد بيانات المستخدم
    userDataDir ? path.join(userDataDir, 'policy.json') : '',
  ].filter(Boolean);

  let merged = { ...POLICY_DEFAULTS };
  for (const file of candidates) {
    try {
      if (!fs.existsSync(file)) continue;
      const raw = JSON.parse(fs.readFileSync(file, 'utf8'));
      if (raw && typeof raw === 'object') merged = { ...merged, ...raw };
    } catch (_) {
      // ملف سياسة تالف يجب ألا يمنع التطبيق من العمل.
    }
  }

  merged.allowedDomains = Array.isArray(merged.allowedDomains)
    ? merged.allowedDomains.map((d) => String(d).trim().toLowerCase()).filter(Boolean)
    : [];
  merged.pageSize = Math.min(200, Math.max(10, parseInt(merged.pageSize, 10) || 50));
  merged.refreshSeconds = Math.max(0, parseInt(merged.refreshSeconds, 10) || 0);

  cached = Object.freeze(merged);
  return cached;
}

module.exports = { SERVER_DEFAULTS, POLICY_DEFAULTS, loadPolicy };
