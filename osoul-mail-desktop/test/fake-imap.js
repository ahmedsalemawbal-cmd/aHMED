/* خادم IMAP4rev1 مصغّر لاختبار src/main/imap.js فعليًا (بلا شبكة خارجية). */
'use strict';
const net = require('net');

/* ---------- محتوى الأجزاء بترميزات حقيقية ---------- */

const HTML_AR = '<html><body><p>مرحبا</p><img src="cid:logo@osoul"></body></html>';
const TEXT_PLAIN = 'Plain fallback';
const PNG_BYTES = Buffer.from('89504e470d0a1a0a0000000d49484452', 'hex');
const PDF_BYTES = Buffer.from('%PDF-1.4 fake', 'ascii');

/* ترميز نص عربي قصير إلى windows-1256 لاختبار تحويل الترميز. */
const CP1256 = { 'م': 0xe3, 'ر': 0xd1, 'ح': 0xcd, 'ب': 0xc8, 'ا': 0xc7 };
function toCp1256(str) {
  return Buffer.from([...str].map((ch) => (CP1256[ch] != null ? CP1256[ch] : ch.charCodeAt(0) & 0xff)));
}
function qpEncode(buf) {
  let out = '';
  for (const b of buf) {
    if ((b >= 33 && b <= 126 && b !== 61) || b === 32 || b === 9) out += String.fromCharCode(b);
    else out += '=' + b.toString(16).toUpperCase().padStart(2, '0');
  }
  return out;
}

const HTML_CP1256 = Buffer.concat([
  Buffer.from('<html><body><p>', 'ascii'),
  toCp1256('مرحبا'),
  Buffer.from('</p></body></html>', 'ascii'),
]);

/**
 * جدول الأجزاء لكل بنية: رقم الجزء ← { type, encoding, headers, bytes }
 * bytes هي البايتات الأصلية؛ الخادم يرسلها مُرمَّزة والعميل يفكّها.
 */
const PARTS = {
  'text-only': {
    1: { headers: 'Content-Type: text/plain; charset="UTF-8"\r\nContent-Transfer-Encoding: 7bit\r\n',
         encoding: '7bit', bytes: Buffer.from(TEXT_PLAIN, 'utf8') },
  },
  alternative: {
    1: { headers: 'Content-Type: text/plain; charset="UTF-8"\r\nContent-Transfer-Encoding: 7bit\r\n',
         encoding: '7bit', bytes: Buffer.from(TEXT_PLAIN, 'utf8') },
    2: { headers: 'Content-Type: text/html; charset="windows-1256"\r\nContent-Transfer-Encoding: quoted-printable\r\n',
         encoding: 'quoted-printable', bytes: HTML_CP1256 },
  },
  'multipart-full': {
    '1.1.1': { headers: 'Content-Type: text/plain; charset="UTF-8"\r\nContent-Transfer-Encoding: 7bit\r\n',
               encoding: '7bit', bytes: Buffer.from(TEXT_PLAIN, 'utf8') },
    '1.1.2': { headers: 'Content-Type: text/html; charset="UTF-8"\r\nContent-Transfer-Encoding: base64\r\n',
               encoding: 'base64', bytes: Buffer.from(HTML_AR, 'utf8') },
    '1.2': { headers: 'Content-Type: image/png; name="logo.png"\r\nContent-ID: <logo@osoul>\r\n'
               + 'Content-Transfer-Encoding: base64\r\nContent-Disposition: inline; filename="logo.png"\r\n',
             encoding: 'base64', bytes: PNG_BYTES },
    2: { headers: 'Content-Type: application/pdf; name="=?UTF-8?B?2LnYsdi2LnBkZg==?="\r\n'
           + 'Content-Transfer-Encoding: base64\r\n'
           + 'Content-Disposition: attachment; filename="=?UTF-8?B?2LnYsdi2LnBkZg==?="\r\n',
         encoding: 'base64', bytes: PDF_BYTES },
  },
};

function encodePart(part) {
  if (part.encoding === 'base64') return part.bytes.toString('base64').replace(/(.{76})/g, '$1\r\n');
  if (part.encoding === 'quoted-printable') return qpEncode(part.bytes);
  return part.bytes.toString('binary');
}

/* ---------- الرسائل ---------- */

function envelope(subject, fromName, fromLocal, fromDomain, dateStr, id) {
  return {
    date: dateStr,
    subject,
    from: [[fromName, null, fromLocal, fromDomain]],
    to: [['Ahmed', null, 'ahmed', 'osoulalbinaa.com']],
    cc: [], bcc: [], replyTo: [],
    inReplyTo: null, messageId: id,
  };
}

const MESSAGES = [
  {
    uid: 101, seq: 1, flags: ['\\Seen'],
    internalDate: '05-Sep-2026 10:14:00 +0300', size: 5120,
    envelope: {
      ...envelope('=?UTF-8?B?2LnYsdi2INiz2LnYsQ==?=', 'Al Fahd Co', 'sales', 'alfahd.com',
        'Sat, 05 Sep 2026 10:14:00 +0300', '<a101@alfahd.com>'),
      replyTo: [['Al Fahd Co', null, 'sales', 'alfahd.com']],
      cc: [['Finance', null, 'finance', 'osoulalbinaa.com']],
    },
    structure: 'multipart-full',
  },
  {
    uid: 102, seq: 2, flags: [],
    internalDate: '06-Sep-2026 08:00:00 +0300', size: 900,
    envelope: envelope('Simple text only', 'Mohammed', 'm.ali', 'client.com',
      'Sun, 06 Sep 2026 08:00:00 +0300', '<a102@client.com>'),
    structure: 'text-only',
  },
  {
    uid: 103, seq: 3, flags: ['\\Flagged'],
    internalDate: '07-Sep-2026 09:30:00 +0300', size: 2048,
    envelope: envelope(null, null, 'noreply', 'system.local',
      'Mon, 07 Sep 2026 09:30:00 +0300', '<a103@system.local>'),
    structure: 'alternative',
  },
];

// رسائل إضافية لاختبار الترقيم (المجموع 12).
for (let i = 0; i < 9; i++) {
  const uid = 104 + i;
  MESSAGES.push({
    uid, seq: 4 + i, flags: ['\\Seen'],
    internalDate: `${8 + i}-Sep-2026 12:00:00 +0300`, size: 700,
    envelope: envelope(`Bulk ${uid}`, `Sender ${uid}`, `s${uid}`, 'bulk.test',
      `Tue, ${8 + i} Sep 2026 12:00:00 +0300`, `<a${uid}@bulk.test>`),
    structure: 'text-only',
  });
}

const FOLDERS = [
  { path: 'INBOX', flags: '\\HasNoChildren', delim: '.' },
  { path: 'INBOX.Sent', flags: '\\HasNoChildren \\Sent', delim: '.' },
  { path: 'INBOX.Drafts', flags: '\\HasNoChildren \\Drafts', delim: '.' },
  { path: 'INBOX.Trash', flags: '\\HasNoChildren \\Trash', delim: '.' },
  { path: 'INBOX.Junk', flags: '\\HasNoChildren \\Junk', delim: '.' },
  { path: 'INBOX.Projects', flags: '\\HasNoChildren', delim: '.' },
];

/* ---------- بناء الاستجابات ---------- */

function q(s) { return `"${String(s).replace(/(["\\])/g, '\\$1')}"`; }
function nil(v) { return v == null ? 'NIL' : q(v); }

function addrList(list) {
  if (!list || !list.length) return 'NIL';
  return '(' + list.map((a) => `(${nil(a[0])} ${nil(a[1])} ${nil(a[2])} ${nil(a[3])})`).join(' ') + ')';
}

function envelopeStr(e) {
  return `(${q(e.date)} ${nil(e.subject)} ${addrList(e.from)} ${addrList(e.from)} ` +
    `${addrList(e.replyTo.length ? e.replyTo : e.from)} ${addrList(e.to)} ${addrList(e.cc)} ${addrList(e.bcc)} ` +
    `${nil(e.inReplyTo)} ${nil(e.messageId)})`;
}

const TEXT_PART = (sub, enc, len, lines) =>
  `("TEXT" "${sub}" ("CHARSET" "UTF-8") NIL NIL "${enc}" ${len} ${lines} NIL NIL NIL NIL)`;

const STRUCTURES = {
  'text-only': TEXT_PART('PLAIN', '7BIT', 14, 1),

  alternative:
    `(${TEXT_PART('PLAIN', '7BIT', 14, 1)} ` +
    `("TEXT" "HTML" ("CHARSET" "windows-1256") NIL NIL "QUOTED-PRINTABLE" 60 3 NIL NIL NIL NIL) ` +
    `"ALTERNATIVE" ("BOUNDARY" "b1") NIL NIL NIL)`,

  'multipart-full':
    '((' +
      `(${TEXT_PART('PLAIN', '7BIT', 14, 1)} ${TEXT_PART('HTML', 'BASE64', 90, 3)} ` +
      '"ALTERNATIVE" ("BOUNDARY" "alt") NIL NIL NIL) ' +
      '("IMAGE" "PNG" ("NAME" "logo.png") "<logo@osoul>" NIL "BASE64" 120 NIL ("INLINE" ("FILENAME" "logo.png")) NIL NIL)' +
      ' "RELATED" ("BOUNDARY" "rel") NIL NIL NIL) ' +
      '("APPLICATION" "PDF" ("NAME" "=?UTF-8?B?2LnYsdi2LnBkZg==?=") NIL NIL "BASE64" 2048 NIL ' +
      '("ATTACHMENT" ("FILENAME" "=?UTF-8?B?2LnYsdi2LnBkZg==?=")) NIL NIL)' +
    ' "MIXED" ("BOUNDARY" "mix") NIL NIL NIL)',
};

const TOP_HEADERS = 'Reply-To: sales@alfahd.com\r\nX-Priority: 3\r\n\r\n';

/** محتوى جزء مطلوب (بعد الترميز، كما يرسله الخادم). */
function partSection(msg, section) {
  const table = PARTS[msg.structure];
  const upper = section.toUpperCase();

  if (upper === '' || upper === 'TEXT') {
    return Object.values(table).map((p) => encodePart(p)).join('\r\n');
  }
  if (upper.startsWith('HEADER')) return TOP_HEADERS;

  const mimeMatch = /^(.+)\.MIME$/i.exec(section);
  if (mimeMatch) {
    const p = table[mimeMatch[1]];
    return p ? p.headers + '\r\n' : '';
  }
  const p = table[section];
  return p ? encodePart(p) : '';
}

function literal(str) {
  const buf = Buffer.from(str, 'binary');
  return `{${buf.length}}\r\n${buf.toString('binary')}`;
}

/* ---------- الخادم ---------- */

function startServer(opts) {
  const options = opts || {};
  return new Promise((resolve) => {
    const state = { calls: [] };
    const server = net.createServer((socket) => {
      let buffer = '';
      let idling = false;
      let idleTag = '';
      let pendingLiteral = 0;   // بايتات حرفي APPEND المتبقية
      let pendingTag = '';

      const write = (s) => socket.write(s + '\r\n', 'binary');
      write('* OK [CAPABILITY IMAP4rev1 UIDPLUS MOVE LITERAL+ QUOTA] Fake IMAP ready');

      socket.on('error', () => {});
      socket.on('data', (chunk) => {
        buffer += chunk.toString('binary');
        for (;;) {
          if (pendingLiteral > 0) {
            const take = Math.min(pendingLiteral, buffer.length);
            buffer = buffer.slice(take);
            pendingLiteral -= take;
            if (pendingLiteral > 0) return;
            if (buffer.startsWith('\r\n')) buffer = buffer.slice(2);
            write(`${pendingTag} OK [APPENDUID 1757000000 200] APPEND done`);
            continue;
          }
          const idx = buffer.indexOf('\r\n');
          if (idx === -1) return;
          const line = buffer.slice(0, idx);
          buffer = buffer.slice(idx + 2);
          try { handle(line); } catch (e) { write(`* BAD internal ${e.message}`); }
        }
      });

      function listFolders(tag, reference, pattern, verb) {
        const ref = (reference || '').replace(/^"|"$/g, '');
        const pat = (pattern || '').replace(/^"|"$/g, '');
        // LIST "" "" هو استعلام عن الفاصل فقط، لا سرد للمجلدات.
        if (pat === '') {
          write(`* ${verb} (\\Noselect) "." ""`);
          write(`${tag} OK ${verb} done`);
          return;
        }
        for (const f of FOLDERS) {
          if (ref && !f.path.startsWith(ref)) continue;
          write(`* ${verb} (${f.flags}) "${f.delim}" ${q(f.path)}`);
        }
        write(`${tag} OK ${verb} done`);
      }

      function handle(line) {
        if (idling) {
          if (/^DONE/i.test(line)) { idling = false; write(`${idleTag} OK IDLE done`); }
          return;
        }
        const m = /^(\S+)\s+(.*)$/.exec(line);
        if (!m) return;
        const tag = m[1];
        const rest = m[2];
        const cmd = rest.split(' ')[0].toUpperCase();
        state.calls.push(cmd);

        switch (cmd) {
          case 'CAPABILITY':
            write('* CAPABILITY IMAP4rev1 UIDPLUS MOVE LITERAL+ QUOTA');
            write(`${tag} OK CAPABILITY done`);
            break;

          case 'LOGIN': {
            const parts = rest.match(/"([^"]*)"\s+"([^"]*)"/);
            if (parts && parts[2] === 'wrongpass') { write(`${tag} NO [AUTHENTICATIONFAILED] Invalid credentials`); break; }
            write(`${tag} OK [CAPABILITY IMAP4rev1 UIDPLUS MOVE QUOTA] Logged in`);
            break;
          }

          case 'LIST':
          case 'LSUB': {
            const args = rest.replace(/^(LIST|LSUB)\s+/i, '');
            const m2 = /^"([^"]*)"\s+"?([^"\s]*)"?/.exec(args) || [];
            listFolders(tag, m2[1] || '', m2[2] == null ? '*' : m2[2], cmd);
            break;
          }

          case 'STATUS': {
            const name = (rest.match(/STATUS\s+"?([^"\s]+)"?/i) || [])[1] || 'INBOX';
            const isInbox = name === 'INBOX';
            write(`* STATUS ${q(name)} (MESSAGES ${isInbox ? MESSAGES.length : 0} UNSEEN ${isInbox ? 1 : 0})`);
            write(`${tag} OK STATUS done`);
            break;
          }

          case 'SELECT':
          case 'EXAMINE': {
            const name = (rest.match(/(?:SELECT|EXAMINE)\s+"?([^"\s]+)"?/i) || [])[1];
            const n = name === 'INBOX' ? MESSAGES.length : 0;
            write('* FLAGS (\\Seen \\Answered \\Flagged \\Deleted \\Draft)');
            write('* OK [PERMANENTFLAGS (\\Seen \\Answered \\Flagged \\Deleted \\Draft \\*)] limited');
            write(`* ${n} EXISTS`);
            write('* 0 RECENT');
            write('* OK [UIDVALIDITY 1757000000] UIDs valid');
            write('* OK [UIDNEXT 200] Predicted next UID');
            write(`${tag} OK [READ-WRITE] Select completed`);
            break;
          }

          case 'FETCH':
          case 'UID': {
            const isUid = cmd === 'UID';
            const body = isUid ? rest.replace(/^UID\s+/i, '') : rest;
            const sub = body.split(' ')[0].toUpperCase();

            if (sub === 'FETCH') {
              const args = body.replace(/^FETCH\s+/i, '');
              const range = args.split(' ')[0];
              const query = args.slice(range.length).trim();

              for (const msg of pick(range, isUid)) {
                const items = [];
                if (/\bUID\b/i.test(query) || isUid) items.push(`UID ${msg.uid}`);
                if (/\bFLAGS\b/i.test(query)) items.push(`FLAGS (${msg.flags.join(' ')})`);
                if (/\bENVELOPE\b/i.test(query)) items.push(`ENVELOPE ${envelopeStr(msg.envelope)}`);
                if (/RFC822\.SIZE/i.test(query)) items.push(`RFC822.SIZE ${msg.size}`);
                if (/\bINTERNALDATE\b/i.test(query)) items.push(`INTERNALDATE "${msg.internalDate}"`);
                if (/\bBODYSTRUCTURE\b/i.test(query)) items.push(`BODYSTRUCTURE ${STRUCTURES[msg.structure]}`);

                // كل أقسام BODY المطلوبة، لا الأول فقط.
                const re = /BODY(?:\.PEEK)?\[([^\]]*)\](?:<(\d+)(?:\.(\d+))?>)?/gi;
                let pm;
                while ((pm = re.exec(query)) !== null) {
                  const section = pm[1];
                  const offset = pm[2] ? Number(pm[2]) : null;
                  let content = partSection(msg, section);
                  let label = `BODY[${section}]`;
                  if (offset !== null) {
                    const len = pm[3] ? Number(pm[3]) : undefined;
                    content = content.slice(offset, len === undefined ? undefined : offset + len);
                    label += `<${offset}>`;
                  }
                  items.push(`${label} ${literal(content)}`);
                }
                write(`* ${msg.seq} FETCH (${items.join(' ')})`);
              }
              write(`${tag} OK FETCH done`);
              break;
            }

            if (sub === 'STORE') { write(`${tag} OK STORE done`); break; }
            if (sub === 'SEARCH') { write('* SEARCH 101 103'); write(`${tag} OK SEARCH done`); break; }
            if (sub === 'MOVE' || sub === 'COPY') { write(`${tag} OK MOVE done`); break; }
            if (sub === 'EXPUNGE') { write(`${tag} OK EXPUNGE done`); break; }
            write(`${tag} OK done`);
            break;
          }

          case 'SEARCH':
            write('* SEARCH 1 3');
            write(`${tag} OK SEARCH done`);
            break;

          case 'STORE': write(`${tag} OK STORE done`); break;
          case 'EXPUNGE': write(`${tag} OK EXPUNGE done`); break;
          case 'CREATE': write(`${tag} OK CREATE done`); break;
          case 'APPEND': {
            const lit = /\{(\d+)(\+?)\}$/.exec(rest);
            if (lit) {
              pendingLiteral = Number(lit[1]);
              pendingTag = tag;
              if (!lit[2]) write('+ Ready for literal data');
            } else {
              write(`${tag} OK [APPENDUID 1757000000 200] APPEND done`);
            }
            break;
          }

          case 'GETQUOTAROOT':
            if (options.noQuota) { write(`${tag} NO Quota not supported`); break; }
            write('* QUOTAROOT "INBOX" "User quota"');
            write('* QUOTA "User quota" (STORAGE 716800 2097152)');
            write(`${tag} OK GETQUOTAROOT done`);
            break;

          case 'NOOP': write(`${tag} OK NOOP done`); break;

          case 'IDLE':
            idling = true;
            idleTag = tag;
            write('+ idling');
            break;

          case 'LOGOUT':
            write('* BYE Logging out');
            write(`${tag} OK LOGOUT done`);
            socket.end();
            break;

          default:
            write(`${tag} BAD Unknown command ${cmd}`);
        }
      }

      function pick(range, isUid) {
        const out = [];
        for (const spec of range.split(',')) {
          const [a, b] = spec.split(':');
          const lo = a === '*' ? Infinity : Number(a);
          const hi = b === undefined ? lo : (b === '*' ? Infinity : Number(b));
          for (const msg of MESSAGES) {
            const key = isUid ? msg.uid : msg.seq;
            if (key >= Math.min(lo, hi) && key <= Math.max(lo, hi)) out.push(msg);
          }
        }
        return [...new Set(out)];
      }
    });

    server.listen(options.port || 0, '127.0.0.1', () => resolve({ server, port: server.address().port, state }));
  });
}

module.exports = { startServer, MESSAGES, FOLDERS, HTML_AR, TEXT_PLAIN, PNG_BYTES, PDF_BYTES };
