/* اختبار الإرسال: خادم SMTP مصغّر + حفظ نسخة في المرسل عبر IMAP. */
'use strict';
const net = require('net');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { startServer: startImap } = require('./fake-imap');
const { EmployeeSession } = require('../src/main/session.js');
const smtp = require('../src/main/smtp.js');

const out = {};
let failures = 0;
function check(name, cond, detail) {
  if (cond) out[name] = 'PASS';
  else { out[name] = `FAIL ${detail == null ? '' : JSON.stringify(String(detail)).slice(0, 300)}`; failures++; }
}

/* ---------- خادم SMTP مصغّر ---------- */
function startSmtp() {
  return new Promise((resolve) => {
    const captured = [];
    const server = net.createServer((socket) => {
      let buf = '';
      let inData = false;
      let msg = { rcpt: [], body: '' };
      const write = (s) => socket.write(s + '\r\n');
      write('220 fake.smtp ESMTP ready');

      socket.on('error', () => {});
      socket.on('data', (chunk) => {
        buf += chunk.toString('binary');
        let i;
        while ((i = buf.indexOf('\r\n')) !== -1) {
          const line = buf.slice(0, i);
          buf = buf.slice(i + 2);

          if (inData) {
            if (line === '.') {
              inData = false;
              captured.push(msg);
              msg = { rcpt: [], body: '' };
              write('250 2.0.0 Ok: queued as ABC123');
            } else {
              msg.body += (line.startsWith('..') ? line.slice(1) : line) + '\r\n';
            }
            continue;
          }

          const cmd = line.split(' ')[0].toUpperCase();
          if (cmd === 'EHLO' || cmd === 'HELO') {
            write('250-fake.smtp');
            write('250-SIZE 35882577');
            write('250-8BITMIME');
            write('250 AUTH PLAIN LOGIN');
          } else if (cmd === 'AUTH') {
            const parts = line.split(' ');
            const mech = (parts[1] || '').toUpperCase();
            if (mech === 'PLAIN') {
              const creds = Buffer.from(parts[2] || '', 'base64').toString().split('\0');
              write(creds[2] === 'wrongpass'
                ? '535 5.7.8 Authentication credentials invalid'
                : '235 2.7.0 Authentication successful');
            } else { write('334 VXNlcm5hbWU6'); socket.authStep = 1; }
          } else if (socket.authStep === 1) {
            socket.authStep = 2;
            write('334 UGFzc3dvcmQ6');
          } else if (socket.authStep === 2) {
            socket.authStep = 0;
            const pass = Buffer.from(line, 'base64').toString();
            if (pass === 'wrongpass') write('535 5.7.8 Authentication credentials invalid');
            else write('235 2.7.0 Authentication successful');
          } else if (cmd === 'MAIL') {
            msg.from = /FROM:\s*<([^>]*)>/i.exec(line)?.[1] || '';
            write('250 2.1.0 Ok');
          } else if (cmd === 'RCPT') {
            msg.rcpt.push(/TO:\s*<([^>]*)>/i.exec(line)?.[1] || '');
            write('250 2.1.5 Ok');
          } else if (cmd === 'DATA') {
            inData = true;
            write('354 End data with <CR><LF>.<CR><LF>');
          } else if (cmd === 'RSET') {
            msg = { rcpt: [], body: '' };
            write('250 2.0.0 Ok');
          } else if (cmd === 'QUIT') {
            write('221 2.0.0 Bye');
            socket.end();
          } else {
            write('250 2.0.0 Ok');
          }
        }
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port, captured }));
  });
}

(async () => {
  const imap = await startImap();
  const mail = await startSmtp();

  const account = {
    email: 'ahmed@osoulalbinaa.com',
    password: 'secret',
    imapHost: '127.0.0.1', imapPort: imap.port, imapSecure: false,
    smtpHost: '127.0.0.1', smtpPort: mail.port, smtpSecure: false,
    fromName: 'Ahmed Salem',
  };

  /* --- التحقق من بيانات SMTP --- */
  try {
    await smtp.verify(account);
    check('smtp.verify', true);
  } catch (e) { check('smtp.verify', false, e.message); }

  try {
    await smtp.verify({ ...account, password: 'wrongpass' });
    check('smtp.verifyRejectsBadPassword', false, 'should have thrown');
  } catch (e) { check('smtp.verifyRejectsBadPassword', /535|invalid|credential/i.test(e.message), e.message); }

  /* --- إرسال حقيقي مع مرفق --- */
  const tmp = path.join(os.tmpdir(), 'osoul-att.txt');
  fs.writeFileSync(tmp, 'attachment contents');

  const session = new EmployeeSession(account, {});
  try {
    await session.mail.connect();
    const res = await session.send({
      fromName: 'أحمد سالم',
      to: 'client@example.com, Second <two@example.com>',
      cc: 'boss@osoulalbinaa.com',
      bcc: 'archive@osoulalbinaa.com',
      subject: 'عرض سعر — مشروع الرياض',
      html: '<p>السلام عليكم<br>مرفق العرض.</p>',
      attachments: [{ path: tmp, filename: 'عرض.txt' }],
      inReplyTo: '<a101@alfahd.com>',
      references: '<a101@alfahd.com>',
    });

    check('send.accepted', res.accepted.length === 4, res.accepted);
    check('send.filedToSent', res.filed === true, res.filed);
    check('send.messageId', /@/.test(res.messageId), res.messageId);

    const sent = mail.captured[mail.captured.length - 1];
    check('smtp.envelopeFrom', sent.from === 'ahmed@osoulalbinaa.com', sent.from);
    check('smtp.envelopeRcpt',
      sent.rcpt.join(',') === 'client@example.com,two@example.com,boss@osoulalbinaa.com,archive@osoulalbinaa.com',
      sent.rcpt);

    const body = sent.body;
    check('mime.subjectEncoded', /^Subject: =\?UTF-8\?/m.test(body), body.match(/^Subject:.*/m));
    check('mime.fromNameEncoded', /^From: =\?UTF-8\?[^\n]*<ahmed@osoulalbinaa\.com>/m.test(body), body.match(/^From:.*/m));
    check('mime.toHeader', /^To: client@example\.com, Second <two@example\.com>/m.test(body), body.match(/^To:.*/m));
    check('mime.ccHeader', /^Cc: boss@osoulalbinaa\.com/m.test(body), body.match(/^Cc:.*/m));
    check('mime.bccHidden', !/^Bcc:/m.test(body), 'Bcc leaked into the message');
    check('mime.inReplyTo', /^In-Reply-To: <a101@alfahd\.com>/m.test(body), body.match(/^In-Reply-To:.*/m));
    check('mime.references', /^References: <a101@alfahd\.com>/m.test(body), body.match(/^References:.*/m));
    check('mime.multipart', /Content-Type: multipart\/mixed/i.test(body));
    check('mime.htmlPart', /Content-Type: text\/html/i.test(body));
    check('mime.textAlternative', /Content-Type: text\/plain/i.test(body), 'plain-text fallback missing');
    // اسم عربي => ترميز RFC 2231 (filename*0*=utf-8''…) وهو الصحيح
    check('mime.attachmentName',
      /Content-Disposition: attachment/i.test(body) &&
      /filename\*0\*=utf-8''%D8%B9%D8%B1%D8%B6\.txt/i.test(body),
      body.match(/Content-Disposition:.*/g));
    check('mime.attachmentBody', /YXR0YWNobWVudCBjb250ZW50cw==/.test(body.replace(/\r\n/g, '')),
      'attachment bytes not found');

    /* النص البديل مشتق من الـ HTML */
    check('mime.textDerived', /السلام عليكم|2KfZhNiz/.test(body) || /=D8=A7/.test(body), 'derived text missing');

  } catch (e) {
    out.EXCEPTION = `${e.message}\n${e.stack}`;
    failures++;
  } finally {
    await session.close().catch(() => {});
    imap.server.close();
    mail.server.close();
    try { fs.unlinkSync(tmp); } catch (_) {}
  }

  /* --- تطبيع العناوين --- */
  const norm = smtp.normalizeAddresses('a@b.com, "Name X" <x@y.com>; bad-entry, c@d.org');
  check('addr.normalize', norm.length === 3 && norm[1].name === 'Name X' && norm[1].address === 'x@y.com', norm);
  check('addr.dropsInvalid', !norm.some((a) => a.address === 'bad-entry'), norm);

  console.log(JSON.stringify(out, null, 1));
  console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
  process.exit(failures ? 1 : 0);
})();
