/**
 * Osoul Mail — الإرسال عبر SMTP.
 *
 * نبني الرسالة أولًا كبايتات خام (MailComposer) ثم نرسلها ونضيف *نفس*
 * البايتات إلى مجلد "المرسل" عبر IMAP. هكذا تكون النسخة المحفوظة مطابقة
 * تمامًا لما استلمه الطرف الآخر، لا إعادة بناء تقريبية.
 */

'use strict';

const nodemailer = require('nodemailer');
const MailComposer = require('nodemailer/lib/mail-composer');

/** إنشاء ناقل SMTP لحساب موظف. */
function createTransport(account) {
  return nodemailer.createTransport({
    host: account.smtpHost,
    port: Number(account.smtpPort) || 465,
    secure: account.smtpSecure !== false,
    auth: { user: account.email, pass: account.password },
    connectionTimeout: 25 * 1000,
    greetingTimeout: 20 * 1000,
    socketTimeout: 120 * 1000,
    // اتصال واحد يُعاد استخدامه بدل فتح جلسة SMTP لكل رسالة.
    pool: true,
    maxConnections: 1,
    maxMessages: 50,
  });
}

/** التحقق من صحة بيانات SMTP (يُستدعى مرة عند تسجيل الدخول). */
async function verify(account) {
  const transport = createTransport(account);
  try {
    await transport.verify();
    return true;
  } finally {
    transport.close();
  }
}

/**
 * بناء + إرسال رسالة.
 *
 * @param {object} transport ناقل نشط
 * @param {object} account   بيانات المرسل
 * @param {object} msg       { to, cc, bcc, subject, html, text, attachments, inReplyTo, references, priority }
 * @returns {Promise<{messageId:string, raw:Buffer, accepted:string[]}>}
 */
/** تحويل حقول الواجهة إلى خيارات MailComposer — يشترك فيها الإرسال والمسودة. */
function composeOptions(account, msg) {
  const from = msg.fromName
    ? { name: msg.fromName, address: account.email }
    : account.email;

  const options = {
    from,
    to: normalizeAddresses(msg.to),
    cc: normalizeAddresses(msg.cc),
    bcc: normalizeAddresses(msg.bcc),
    subject: String(msg.subject || ''),
    attachments: (msg.attachments || []).map((a) => (
      a.path ? { filename: a.filename, path: a.path } : { filename: a.filename, content: Buffer.from(a.content, 'base64') }
    )),
  };

  if (msg.html) {
    options.html = msg.html;
    options.text = msg.text || htmlToText(msg.html);
  } else {
    options.text = msg.text || '';
  }

  if (msg.inReplyTo) options.inReplyTo = msg.inReplyTo;
  if (msg.references) options.references = msg.references;
  if (msg.priority === 'high') options.priority = 'high';

  return options;
}

/**
 * بناء الرسالة كبايتات خام دون إرسالها — تُستخدم لحفظ مسودة في مجلد
 * المسودات، فتكون المسودة بنفس بنية الرسالة التي ستُرسل لاحقًا.
 */
async function build(account, msg) {
  return new MailComposer(composeOptions(account, msg)).compile().build();
}

async function send(transport, account, msg) {
  const compiled = new MailComposer(composeOptions(account, msg)).compile();
  const envelope = compiled.getEnvelope();
  const raw = await compiled.build();

  const info = await transport.sendMail({ envelope, raw });

  return {
    messageId: (info && info.messageId) || '',
    accepted: (info && info.accepted) || [],
    rejected: (info && info.rejected) || [],
    raw,
  };
}

/** "اسم <بريد>, بريد2" → مصفوفة عناوين تفهمها nodemailer. */
function normalizeAddresses(input) {
  if (!input) return [];
  const list = Array.isArray(input) ? input : String(input).split(/[,;]+/);
  return list
    .map((x) => {
      if (x && typeof x === 'object') return x.email ? { name: x.name || '', address: x.email } : null;
      const s = String(x).trim();
      if (!s) return null;
      const m = /^(.*?)<([^>]+)>$/.exec(s);
      if (m) return { name: m[1].trim().replace(/^["']|["']$/g, ''), address: m[2].trim() };
      return { name: '', address: s };
    })
    .filter(Boolean)
    .filter((a) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.address));
}

/** نسخة نصية بسيطة تُرفق مع كل رسالة HTML (توافق مع كل القارئات). */
function htmlToText(html) {
  return String(html || '')
    .replace(/<(script|style)[\s\S]*?<\/\1>/gi, '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|tr|li|h[1-6])>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

module.exports = { createTransport, verify, send, build, normalizeAddresses, htmlToText };
