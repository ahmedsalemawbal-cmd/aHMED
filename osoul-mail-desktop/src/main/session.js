/**
 * Osoul Mail — بوابة الدخول وجلسة الموظف.
 *
 * البوابة تتحقق من ثلاث طبقات قبل فتح صندوق البريد:
 *   1) النطاق  — بريد الشركة فقط (الموظفون المعتمدون)، قابل للضبط في policy.json
 *   2) كشف الموظفين — نداء اختياري لخادم الشركة إن ضُبط rosterUrl
 *   3) IMAP + SMTP — التحقق الحقيقي: لا دخول إلا ببيانات صندوق بريد فعّال
 *
 * الطبقة الثالثة هي الحاسمة: إن أوقف المسؤول صندوق البريد على الخادم يفشل
 * الدخول فورًا دون أي إجراء داخل التطبيق.
 */

'use strict';

const { MailSession } = require('./imap');
const smtp = require('./smtp');

/** رسائل خطأ مفهومة للموظف بدل نصوص الخادم الخام. */
const ERRORS = {
  BAD_EMAIL: { code: 'BAD_EMAIL', ar: 'صيغة البريد الإلكتروني غير صحيحة.', en: 'That email address is not valid.' },
  DOMAIN: { code: 'DOMAIN', ar: 'هذا البريد ليس من نطاق الشركة. الدخول مقصور على الموظفين المعتمدين.', en: 'This mailbox is outside the company domain. Access is limited to approved employees.' },
  NOT_APPROVED: { code: 'NOT_APPROVED', ar: 'هذا الحساب غير معتمد للدخول. راجع مسؤول النظام.', en: 'This account is not approved. Contact your administrator.' },
  AUTH: { code: 'AUTH', ar: 'البريد أو كلمة المرور غير صحيحة.', en: 'Incorrect email or password.' },
  NETWORK: { code: 'NETWORK', ar: 'تعذّر الوصول إلى خادم البريد. تحقّق من اتصال الإنترنت.', en: 'Could not reach the mail server. Check your connection.' },
  SMTP: { code: 'SMTP', ar: 'تم التحقق من الاستقبال، لكن الإرسال مرفوض. راجع مسؤول النظام.', en: 'Receiving works, but sending was refused. Contact your administrator.' },
  UNKNOWN: { code: 'UNKNOWN', ar: 'تعذّر تسجيل الدخول. حاول مرة أخرى.', en: 'Sign-in failed. Please try again.' },
};

function fail(kind, detail) {
  const e = ERRORS[kind] || ERRORS.UNKNOWN;
  const err = new Error(e.code);
  err.osoul = { ...e, detail: detail || '' };
  return err;
}

/** تصنيف خطأ الاتصال إلى رسالة يفهمها الموظف. */
function classify(err) {
  const raw = String((err && (err.responseText || err.message)) || err || '');
  const code = String((err && (err.code || err.authenticationFailed)) || '');
  if (/AUTHENTICATIONFAILED|Invalid credentials|LOGIN failed|auth.*fail|535|534/i.test(raw) || err?.authenticationFailed) {
    return fail('AUTH', raw);
  }
  if (/ENOTFOUND|EAI_AGAIN|ECONNREFUSED|ETIMEDOUT|ECONNRESET|ESOCKET|timeout|getaddrinfo/i.test(raw + code)) {
    return fail('NETWORK', raw);
  }
  return fail('UNKNOWN', raw);
}

/** التحقق من النطاق المعتمد. */
function checkDomain(email, policy) {
  const domains = policy.allowedDomains || [];
  if (!domains.length) return true;
  const domain = String(email).split('@')[1] || '';
  return domains.includes(domain.toLowerCase());
}

/**
 * تحقق اختياري من كشف الموظفين المعتمدين على خادم الشركة.
 * لا يُستدعى إلا إذا ضُبط rosterUrl في policy.json؛ وأي عطل في الشبكة
 * لا يمنع الدخول لأن IMAP هو مصدر الحقيقة النهائي.
 */
async function checkRoster(email, policy) {
  if (!policy.rosterUrl) return true;
  try {
    const url = new URL(policy.rosterUrl);
    url.searchParams.set('email', email);
    const res = await fetch(url.toString(), {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(8000),
    });
    if (!res.ok) return true; // الخادم غير متاح — نترك القرار لـ IMAP
    const data = await res.json();
    if (data && data.approved === false) return false;
    return true;
  } catch (_) {
    return true;
  }
}

/** جلسة موظف مسجَّل الدخول: اتصال IMAP + ناقل SMTP + الحساب. */
class EmployeeSession {
  constructor(account, policy) {
    this.account = account;
    this.policy = policy;
    this.mail = new MailSession(account);
    this.transport = null;
  }

  get email() { return this.account.email; }

  transportOrCreate() {
    if (!this.transport) this.transport = smtp.createTransport(this.account);
    return this.transport;
  }

  async send(msg) {
    const result = await smtp.send(this.transportOrCreate(), this.account, msg);
    // نسخة "المرسل" — فشل الأرشفة لا يعني فشل الإرسال.
    let filed = false;
    try {
      const sent = await this.mail.specialPath('sent');
      if (sent) {
        await this.mail.append(sent, result.raw, ['\\Seen']);
        filed = true;
      }
    } catch (_) { /* الرسالة وصلت؛ النسخة فقط لم تُحفظ */ }
    return { messageId: result.messageId, accepted: result.accepted, rejected: result.rejected, filed };
  }

  /**
   * حفظ مسودة في مجلد المسودات. نبني نفس البايتات التي كانت ستُرسل ثم
   * نضيفها بعلم \\Draft، فتفتح المسودة في أي عميل بريد آخر كما تركها الموظف.
   */
  async saveDraft(msg) {
    const raw = await smtp.build(this.account, msg);
    const drafts = await this.mail.specialPath('drafts');
    if (!drafts) {
      const err = new Error('NO_DRAFTS');
      err.osoul = { code: 'NO_DRAFTS', ar: 'لا يوجد مجلد مسودات في صندوق بريدك.', en: 'No Drafts folder on this mailbox.' };
      throw err;
    }
    await this.mail.append(drafts, raw, ['\\Draft', '\\Seen']);
    return { saved: true, folder: drafts };
  }

  async close() {
    if (this.transport) {
      try { this.transport.close(); } catch (_) { /* مغلق أصلًا */ }
      this.transport = null;
    }
    await this.mail.close();
  }
}

/**
 * تنفيذ تسجيل الدخول كاملًا.
 *
 * @param {{email:string,password:string,imapHost?:string,imapPort?:number,smtpHost?:string,smtpPort?:number}} input
 * @param {object} policy
 * @returns {Promise<EmployeeSession>}
 */
async function login(input, policy) {
  const email = String(input.email || '').trim().toLowerCase();
  const password = String(input.password || '');

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw fail('BAD_EMAIL');
  if (!password) throw fail('AUTH');
  if (!checkDomain(email, policy)) throw fail('DOMAIN');
  if (!(await checkRoster(email, policy))) throw fail('NOT_APPROVED');

  const account = {
    email,
    password,
    imapHost: input.imapHost || policy.imapHost,
    imapPort: Number(input.imapPort) || policy.imapPort,
    imapSecure: policy.imapSecure !== false,
    smtpHost: input.smtpHost || policy.smtpHost,
    smtpPort: Number(input.smtpPort) || policy.smtpPort,
    smtpSecure: policy.smtpSecure !== false,
    fromName: input.fromName || deriveName(email),
  };

  const session = new EmployeeSession(account, policy);

  try {
    await session.mail.connect();
  } catch (err) {
    await session.close().catch(() => {});
    throw classify(err);
  }

  // الاستقبال يعمل. نتحقق من الإرسال أيضًا حتى لا يكتشف الموظف العطل
  // لاحقًا وهو يكتب رسالة.
  try {
    await smtp.verify(account);
  } catch (err) {
    const c = classify(err);
    if (c.osoul.code === 'AUTH') {
      await session.close().catch(() => {});
      throw fail('SMTP', c.osoul.detail);
    }
    // عطل شبكي عابر في SMTP لا يمنع قراءة البريد.
  }

  return session;
}

/** "ahmed.ali@osoulalbinaa.com" → "Ahmed Ali" — اسم مرسل معقول بلا إعداد. */
function deriveName(email) {
  const local = String(email).split('@')[0] || '';
  return local
    .split(/[._-]+/)
    .filter(Boolean)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ') || local;
}

module.exports = { login, EmployeeSession, ERRORS, deriveName, classify };
