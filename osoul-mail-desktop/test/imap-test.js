/* اختبار MailSession مقابل خادم IMAP حقيقي (مصغّر). */
'use strict';
const { startServer } = require('./fake-imap');
const { MailSession } = require('../src/main/imap.js');

const out = {};
let failures = 0;

function check(name, cond, detail) {
  if (cond) { out[name] = 'PASS'; }
  else { out[name] = `FAIL ${detail == null ? '' : JSON.stringify(detail)}`; failures++; }
}

(async () => {
  const { server, port } = await startServer();

  const s = new MailSession({
    email: 'ahmed@osoulalbinaa.com',
    password: 'secret',
    imapHost: '127.0.0.1',
    imapPort: port,
    imapSecure: false,
  });

  try {
    await s.connect();
    check('connect', s.connected);

    /* --- المجلدات --- */
    const folders = await s.folders();
    check('folders.count', folders.length === 6, folders.length);
    check('folders.inboxFirst', folders[0].special === 'inbox', folders[0]);
    const specials = folders.map((f) => f.special);
    check('folders.specialUse', ['inbox', 'drafts', 'sent', 'junk', 'trash'].every((x) => specials.includes(x)), specials);
    check('folders.arabicNames', folders[0].display.ar === 'البريد الوارد', folders[0].display);
    check('folders.unseen', folders[0].unseen === 1, folders[0].unseen);
    check('folders.customKeepsName',
      folders.some((f) => f.raw === 'INBOX.Projects' && !f.special && f.display.ar === 'Projects'), specials);
    check('folders.order', specials.indexOf('inbox') < specials.indexOf('trash'), specials);

    const sentPath = await s.specialPath('sent');
    check('specialPath.sent', sentPath === 'INBOX.Sent', sentPath);

    /* --- قائمة الرسائل --- */
    const page = await s.list({ folder: 'INBOX', page: 0, pageSize: 50 });
    check('list.total', page.total === 12, page.total);
    check('list.count', page.messages.length === 12, page.messages.length);
    check('list.newestFirst', page.messages[0].uid === 112, page.messages.map((m) => m.uid));

    const m101 = page.messages.find((x) => x.uid === 101);
    check('list.decodedSubject', m101.subject === 'عرض سعر', m101.subject);
    check('list.from', m101.from.email === 'sales@alfahd.com' && m101.from.name === 'Al Fahd Co', m101.from);
    check('list.seenFlag', m101.seen === true, m101.seen);
    check('list.hasAttachment', m101.hasAttachments === true, m101.hasAttachments);
    check('list.cc', m101.cc.length === 1 && m101.cc[0].email === 'finance@osoulalbinaa.com', m101.cc);
    check('list.date', new Date(m101.ts).getUTCFullYear() === 2026, m101.date);
    check('list.size', m101.size === 5120, m101.size);

    const m102 = page.messages.find((x) => x.uid === 102);
    check('list.unseen', m102.seen === false, m102.seen);
    check('list.noAttachment', m102.hasAttachments === false, m102.hasAttachments);

    const m103 = page.messages.find((x) => x.uid === 103);
    check('list.flagged', m103.flagged === true, m103.flagged);
    check('list.emptySubject', m103.subject === '', m103.subject);
    check('list.altNoAttachment', m103.hasAttachments === false, m103.hasAttachments);

    /* --- الترقيم --- */
    const p1 = await s.list({ folder: 'INBOX', page: 0, pageSize: 5 });
    check('page1.size', p1.messages.length === 5, p1.messages.length);
    check('page1.newest', p1.messages[0].uid === 112, p1.messages.map((m) => m.uid));
    check('page1.oldest', p1.messages[4].uid === 108, p1.messages.map((m) => m.uid));
    const p2 = await s.list({ folder: 'INBOX', page: 1, pageSize: 5 });
    check('page2.uids', p2.messages.map((m) => m.uid).join(',') === '107,106,105,104,103', p2.messages.map((m) => m.uid));
    const p3 = await s.list({ folder: 'INBOX', page: 2, pageSize: 5 });
    check('page3.remainder', p3.messages.map((m) => m.uid).join(',') === '102,101', p3.messages.map((m) => m.uid));
    const p4 = await s.list({ folder: 'INBOX', page: 3, pageSize: 5 });
    check('page4.empty', p4.messages.length === 0, p4.messages.length);

    /* --- البحث --- */
    const found = await s.list({ folder: 'INBOX', page: 0, search: 'عرض' });
    check('search.total', found.total === 2, found.total);
    check('search.uids', found.messages.map((m) => m.uid).join(',') === '103,101', found.messages.map((m) => m.uid));
    check('search.envelopesLoaded', found.messages.every((m) => m.subject !== undefined && m.from), found.messages);

    /* --- رسالة كاملة: متعددة الأجزاء --- */
    const full = await s.message('INBOX', 101, { allowRemote: false });
    check('msg.subject', full.subject === 'عرض سعر', full.subject);
    check('msg.htmlChosen', full.html.includes('مرحبا'), full.html.slice(0, 60));
    check('msg.noTextWhenHtml', full.text === '', full.text);
    check('msg.inlineCid', !!full.inline['logo@osoul'], Object.keys(full.inline));
    check('msg.inlineIsDataUri', String(full.inline['logo@osoul']).startsWith('data:image/png;base64,'),
      String(full.inline['logo@osoul']).slice(0, 30));
    check('msg.attachmentCount', full.attachments.length === 1, full.attachments);
    check('msg.attachmentName', full.attachments[0].filename === 'عرض.pdf', full.attachments[0].filename);
    check('msg.attachmentMime', full.attachments[0].mimeType === 'application/pdf', full.attachments[0].mimeType);
    check('msg.attachmentPart', full.attachments[0].part === '2', full.attachments[0].part);
    check('msg.inlineNotListedAsAttachment',
      !full.attachments.some((a) => a.filename === 'logo.png'), full.attachments.map((a) => a.filename));
    check('msg.replyTo', /sales@alfahd\.com/.test(full.replyTo), full.replyTo);

    /* --- رسالة نصية فقط --- */
    const plain = await s.message('INBOX', 102, {});
    check('msg.plainText', plain.text === 'Plain fallback' && plain.html === '', { t: plain.text, h: plain.html });
    check('msg.plainSeenFlagKept', plain.seen === false, plain.seen);
    check('msg.plainNoAtt', plain.attachments.length === 0, plain.attachments);

    /* --- alternative: يختار HTML --- */
    const alt = await s.message('INBOX', 103, {});
    check('msg.altPrefersHtml', alt.text === '' && alt.html.startsWith('<html>'), { h: alt.html.slice(0, 40) });
    check('msg.cp1256Decoded', alt.html.includes('مرحبا'), alt.html);

    /* --- تنزيل مرفق --- */
    const att = await s.attachment('INBOX', 101, '2');
    check('attachment.bytes', att.buffer.toString('ascii') === '%PDF-1.4 fake', att.buffer.toString('ascii'));

    /* --- الأعلام والنقل والحذف --- */
    check('flag.set', (await s.setFlag('INBOX', [101], '\\Flagged', true)).ok === true);
    check('flag.unset', (await s.setFlag('INBOX', [101], '\\Seen', false)).ok === true);
    check('move', (await s.move('INBOX', [101], 'INBOX.Projects')).ok === true);
    const del = await s.remove('INBOX', [102]);
    check('delete.toTrash', del.ok === true && !del.permanent, del);
    const perm = await s.remove('INBOX.Trash', [102]);
    check('delete.permanentInTrash', perm.permanent === true, perm);

    /* --- الحصة --- */
    const quota = await s.quota();
    check('quota', quota && quota.used === 716800 * 1024 && quota.limit === 2097152 * 1024, quota);

    /* --- عدّاد غير المقروء --- */
    const un = await s.unseenCount('INBOX');
    check('unseenCount', un.unseen === 1 && un.total === 12, un);

    /* --- إنشاء مجلد --- */
    await s.createFolder('INBOX.Test');
    check('createFolder', true);

    /* --- إضافة إلى المرسل --- */
    await s.append('INBOX.Sent', Buffer.from('From: a@b\r\n\r\nhi'), ['\\Seen']);
    check('append', true);

  } catch (e) {
    out.EXCEPTION = `${e.message}\n${e.stack}`;
    failures++;
  } finally {
    await s.close().catch(() => {});
    server.close();
  }

  /* --- فشل المصادقة يعطي رسالة واضحة --- */
  const { server: srv2, port: port2 } = await startServer();
  const { login } = require('../src/main/session.js');
  try {
    await login(
      { email: 'ahmed@osoulalbinaa.com', password: 'wrongpass', imapHost: '127.0.0.1', imapPort: port2 },
      { allowedDomains: ['osoulalbinaa.com'], imapHost: '127.0.0.1', imapPort: port2, imapSecure: false,
        smtpHost: '127.0.0.1', smtpPort: 1, smtpSecure: false },
    );
    check('login.wrongPassword', false, 'should have thrown');
  } catch (e) {
    check('login.wrongPassword', e.osoul && e.osoul.code === 'AUTH', e.osoul);
  } finally {
    srv2.close();
  }

  console.log(JSON.stringify(out, null, 1));
  console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
  process.exit(failures ? 1 : 0);
})();
