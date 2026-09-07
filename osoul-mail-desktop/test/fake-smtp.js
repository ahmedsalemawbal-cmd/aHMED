/* خادم SMTP مصغّر للاختبار. */
'use strict';
const net = require('net');

function startSmtp(opts) {
  const options = opts || {};
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
    server.listen(options.port || 0, '127.0.0.1', () => resolve({ server, port: server.address().port, captured }));
  });
}

module.exports = { startSmtp };
