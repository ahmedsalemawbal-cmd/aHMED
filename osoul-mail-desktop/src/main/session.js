/**
 * Osoul Mail — بوابة الدخول وجلسة الموظف.
 *
 * البوابة تتحقق من ثلاث طبقات قبل فتح صندوق البريد:
 *   1) النطاق  — بريد الشركة فقط (الموظفون المعتمدون)، قابل للضبط في policy.json
 *   2) كشف الموظفين — نداء اختياري لخادم الشركة إن ضُبط rosterUrl
 *   3) IMAP — التحقق الحقيقي: لا دخول إلا ببيانات صندوق بريد فعّال
 *
 * الطبقة الثالثة هي الحاسمة: إن أوقف المسؤول صندوق البريد على الخادم يفشل
 * الدخول فورًا دون أي إجراء داخل التطبيق. أما الإرسال (SMTP) فيُجرَّب ويُبلَّغ
 * عنه ولا يمنع الدخول: موظف يقرأ بريده ولا يرسل أفضل من موظف بلا بريد.
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

  // الاستقبال يعمل ⇒ الموظف داخل. نجرّب الإرسال لنُنبّهه مبكرًا إن كان
  // معطّلًا، لكنه لا يمنع الدخول: نسخة الويب تفتح البريد بـ IMAP وحده،
  // وفشل مصادقة SMTP (حدّ إرسال، أو حظر مؤقت للمنفذ) يترك الموظف بلا بريد
  // إطلاقًا بدل أن يقرأ رسائله ويؤجّل الإرسال.
  session.warnings = [];
  try {
    await smtp.verify(account);
  } catch (err) {
    const c = classify(err);
    session.warnings.push({
      code: c.osoul.code === 'AUTH' ? 'SMTP_AUTH' : 'SMTP_NETWORK',
      ar: c.osoul.code === 'AUTH'
        ? 'الاستقبال يعمل، لكن خادم الإرسال رفض كلمة المرور. القراءة متاحة والإرسال قد يفشل.'
        : 'الاستقبال يعمل، لكن تعذّر الوصول لخادم الإرسال. القراءة متاحة والإرسال قد يفشل.',
      en: c.osoul.code === 'AUTH'
        ? 'Receiving works, but the sending server refused the password. You can read mail; sending may fail.'
        : 'Receiving works, but the sending server could not be reached. You can read mail; sending may fail.',
      detail: c.osoul.detail || '',
    });
  }

  return session;
}

/* =========================================================================
 *  فحص الاتصال
 *
 *  حين يفشل الدخول لا يكفي "البريد أو كلمة المرور غير صحيحة": قد يكون
 *  المنفذ محجوبًا بجدار حماية، أو الاسم لا يُترجم، أو الخادم يرفض المصادقة.
 *  هذا الفحص يفصل المراحل ويعيد نتيجة كل مرحلة مع ردّ الخادم الحرفي، فيُرسل
 *  الموظف صورة واحدة تكفي لمعرفة السبب بدل جولات تخمين.
 * ====================================================================== */

const net = require('net');
const tls = require('tls');
const dns = require('dns').promises;

const STEP_TIMEOUT = 12000;

function step(id, ar, en) {
  return { id, ar, en, ok: false, detail: '', skipped: false };
}

/** فتح المقبس وقراءة أول سطر ترحيب. مشفّر أو صريح حسب إعداد الخادم. */
function greet(host, port, timeoutMs, secure) {
  return new Promise((resolve, reject) => {
    const socket = secure
      ? tls.connect({ host, port, servername: host, timeout: timeoutMs })
      : net.connect({ host, port, timeout: timeoutMs });
    let done = false;
    const finish = (err, line) => {
      if (done) return;
      done = true;
      socket.destroy();
      if (err) reject(err); else resolve(line);
    };
    socket.setTimeout(timeoutMs, () => finish(new Error('timeout')));
    socket.once('error', (e) => finish(e));
    socket.once('data', (buf) => finish(null, String(buf).split(/\r?\n/)[0]));
  });
}

/** اتصال TCP خام — يفصل "المنفذ محجوب" عن "TLS فشل". */
function reach(host, port, timeoutMs) {
  return new Promise((resolve, reject) => {
    const socket = net.connect({ host, port, timeout: timeoutMs });
    let done = false;
    const finish = (err) => {
      if (done) return;
      done = true;
      socket.destroy();
      if (err) reject(err); else resolve(true);
    };
    socket.setTimeout(timeoutMs, () => finish(new Error('timeout')));
    socket.once('error', (e) => finish(e));
    socket.once('connect', () => finish(null));
  });
}

/**
 * فحص كامل بلا فتح جلسة. كلمة المرور اختيارية: بدونها نفحص الشبكة فقط.
 *
 * @returns {Promise<{steps:Array, verdict:string}>}
 */
async function diagnose(input, policy) {
  const email = String(input.email || '').trim().toLowerCase();
  const password = String(input.password || '');
  const imapHost = input.imapHost || policy.imapHost;
  const imapPort = Number(input.imapPort) || policy.imapPort;
  const smtpHost = input.smtpHost || policy.smtpHost;
  const smtpPort = Number(input.smtpPort) || policy.smtpPort;

  const steps = [
    step('domain', 'نطاق البريد معتمد', 'Email domain allowed'),
    step('dns', `ترجمة اسم الخادم (${imapHost})`, `Resolving mail server (${imapHost})`),
    step('tcp', `فتح المنفذ ${imapPort}`, `Opening port ${imapPort}`),
    step('tls', policy.imapSecure !== false ? 'تشفير الاتصال' : 'ردّ الخادم',
      policy.imapSecure !== false ? 'Securing the connection' : 'Server greeting'),
    step('imap', 'تسجيل الدخول للاستقبال (IMAP)', 'Signing in to receive (IMAP)'),
    step('smtp', `الإرسال (SMTP ${smtpPort})`, `Sending (SMTP ${smtpPort})`),
  ];
  const at = (id) => steps.find((x) => x.id === id);

  /**
   * حين يتعذّر الوصول إلى الخادم الافتراضي، نفحص أسماء الخوادم الأخرى
   * المعروفة لنفس المزوّد ونفس النطاق — فحص وصول فقط: مقبس يُفتح ويُغلق.
   *
   * لا نجرّب كلمة المرور على أي خادم لم يضبطه مسؤول النظام: خادم غير مُتحقَّق
   * منه لا يجوز أن يرى سرّ الموظف. النتيجة هنا دليل يسلّمه للمسؤول لا أكثر.
   */
  async function probeAlternates(verdict) {
    const domain = email.split('@')[1] || '';
    const others = ['imap.hostinger.com', 'imap.titan.email', `mail.${domain}`]
      .filter((h, i, all) => h && h !== imapHost && all.indexOf(h) === i);

    const reachable = [];
    for (const host of others) {
      try {
        // eslint-disable-next-line no-await-in-loop
        await reach(host, imapPort, STEP_TIMEOUT);
        reachable.push(host);
      } catch (_) { /* اسم غير موجود أو منفذ مغلق — ليس بديلًا */ }
    }
    if (!reachable.length) return { steps, verdict };

    const st = step('alt', 'خادم آخر لنفس النطاق يستجيب', 'Another server for this domain answers');
    st.ok = true;
    st.detail = reachable.join(', ');
    steps.push(st);
    return { steps, verdict: 'ALT_HOST' };
  }

  /* 1) النطاق */
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    at('domain').detail = 'BAD_EMAIL';
    return { steps, verdict: 'BAD_EMAIL' };
  }
  at('domain').ok = checkDomain(email, policy);
  if (!at('domain').ok) {
    at('domain').detail = (policy.allowedDomains || []).join(', ');
    return { steps, verdict: 'DOMAIN' };
  }

  /* 2) DNS */
  try {
    const found = await dns.lookup(imapHost, { all: true });
    at('dns').ok = true;
    at('dns').detail = found.map((a) => a.address).join(', ');
  } catch (err) {
    at('dns').detail = String(err.code || err.message);
    return probeAlternates('DNS');
  }

  /* 3) المنفذ */
  try {
    await reach(imapHost, imapPort, STEP_TIMEOUT);
    at('tcp').ok = true;
  } catch (err) {
    at('tcp').detail = String(err.code || err.message);
    return probeAlternates('BLOCKED');
  }

  /* 4) TLS + ترحيب الخادم */
  try {
    at('tls').detail = await greet(imapHost, imapPort, STEP_TIMEOUT, policy.imapSecure !== false);
    at('tls').ok = true;
  } catch (err) {
    at('tls').detail = String(err.code || err.message);
    return probeAlternates('TLS');
  }

  /* 5) IMAP */
  if (!password) {
    at('imap').skipped = true;
    at('smtp').skipped = true;
    return { steps, verdict: 'NETWORK_OK' };
  }

  const account = {
    email, password,
    imapHost, imapPort, imapSecure: policy.imapSecure !== false,
    smtpHost, smtpPort, smtpSecure: policy.smtpSecure !== false,
    fromName: deriveName(email),
  };

  const probe = new MailSession(account);
  try {
    await probe.connect();
    at('imap').ok = true;
  } catch (err) {
    at('imap').detail = String((err && (err.responseText || err.message)) || err).slice(0, 200);
    await probe.close().catch(() => {});
    return { steps, verdict: classify(err).osoul.code === 'AUTH' ? 'IMAP_AUTH' : 'IMAP_FAIL' };
  }
  await probe.close().catch(() => {});

  /* 6) SMTP — يُبلَّغ عنه ولا يمنع الدخول */
  try {
    await smtp.verify(account);
    at('smtp').ok = true;
  } catch (err) {
    at('smtp').detail = String((err && (err.response || err.message)) || err).slice(0, 200);
    return { steps, verdict: 'SMTP_ONLY' };
  }

  return { steps, verdict: 'OK' };
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

module.exports = { login, diagnose, EmployeeSession, ERRORS, deriveName, classify };
