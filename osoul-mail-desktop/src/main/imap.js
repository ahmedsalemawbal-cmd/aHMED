/**
 * Osoul Mail — جلسة IMAP.
 *
 * مبدأ التصميم: **اتصال واحد فقط** يُعاد استخدامه طوال عمر الجلسة، وكل الأوامر
 * تمرّ عبر طابور تسلسلي. خوادم الاستضافة (Hostinger) تحدّ عدد اتصالات IMAP
 * المتزامنة، وفتح اتصال لكل طلب هو أسرع طريق لرسالة "too many connections".
 * اتصال واحد ثابت = أسرع استجابة وأقل ضجيج على الخادم.
 *
 * نقرأ من الرسالة ما يلزم فقط: قائمة الرسائل تعتمد على الترويسات (envelope)
 * دون تحميل الأجسام، والجسم يُحمَّل عند الفتح، والمرفقات عند الطلب وحدها.
 */

'use strict';

const { EventEmitter } = require('events');
const { ImapFlow } = require('imapflow');

/** ترتيب المجلدات المميزة في الشريط الجانبي. */
const SPECIAL_ORDER = ['inbox', 'starred', 'drafts', 'sent', 'archive', 'junk', 'trash'];

/** أسماء عربية/إنجليزية للمجلدات المعروفة. */
const SPECIAL_NAMES = {
  inbox: { ar: 'البريد الوارد', en: 'Inbox' },
  sent: { ar: 'المرسل', en: 'Sent' },
  drafts: { ar: 'المسودات', en: 'Drafts' },
  trash: { ar: 'المحذوفات', en: 'Trash' },
  junk: { ar: 'المزعج', en: 'Spam' },
  archive: { ar: 'الأرشيف', en: 'Archive' },
};

/** أسماء مجلدات شائعة لا يعلن عنها الخادم عبر SPECIAL-USE. */
const NAME_HINTS = {
  inbox: ['inbox'],
  sent: ['sent', 'sent items', 'sent messages', 'sentmail', 'المرسل'],
  drafts: ['drafts', 'draft', 'المسودات'],
  trash: ['trash', 'deleted', 'deleted items', 'المحذوفات', 'bin'],
  junk: ['junk', 'spam', 'bulk mail', 'المزعج'],
  archive: ['archive', 'الأرشيف'],
};

class MailSession extends EventEmitter {
  /**
   * @param {{email:string,password:string,imapHost:string,imapPort:number,imapSecure?:boolean}} account
   */
  constructor(account) {
    super();
    this.account = account;
    this.client = null;
    this.connected = false;
    this.closing = false;
    this._queue = Promise.resolve();
    this._folders = null;
    this._reconnectTimer = null;
    this._reconnectDelay = 2000;
  }

  /* ------------------------------------------------------------- الاتصال */

  _build() {
    const client = new ImapFlow({
      host: this.account.imapHost,
      port: Number(this.account.imapPort) || 993,
      secure: this.account.imapSecure !== false,
      auth: { user: this.account.email, pass: this.account.password },
      logger: false,
      emitLogs: false,
      clientInfo: { name: 'Osoul Mail', vendor: 'Osoul Albinaa' },
      // مهلة معقولة: خادم بطيء يجب أن يعطي خطأ لا أن يعلّق الواجهة.
      socketTimeout: 90 * 1000,
      greetingTimeout: 20 * 1000,
      connectionTimeout: 25 * 1000,
    });

    client.on('error', (err) => {
      this.emit('warning', String((err && err.message) || err));
    });
    client.on('close', () => {
      this.connected = false;
      if (!this.closing) {
        this.emit('disconnected');
        this._scheduleReconnect();
      }
    });
    // رسالة جديدة وصلت إلى المجلد المفتوح.
    client.on('exists', (data) => {
      if (!data || !data.count || data.count <= (data.prevCount || 0)) return;
      this.emit('mail', { path: data.path, count: data.count, added: data.count - (data.prevCount || 0) });
    });
    client.on('expunge', () => this.emit('changed'));
    client.on('flags', () => this.emit('changed'));

    return client;
  }

  async connect() {
    if (this.connected && this.client && this.client.usable) return;
    this.closing = false;
    this.client = this._build();
    await this.client.connect();
    // الإبقاء على INBOX مفتوحًا يجعل الخادم يدفع إشعار الرسائل الجديدة (IDLE).
    await this.client.mailboxOpen('INBOX');
    this.connected = true;
    this._reconnectDelay = 2000;
    this.emit('connected');
  }

  _scheduleReconnect() {
    if (this._reconnectTimer || this.closing) return;
    const delay = this._reconnectDelay;
    this._reconnectDelay = Math.min(60000, delay * 2);
    this._reconnectTimer = setTimeout(async () => {
      this._reconnectTimer = null;
      if (this.closing) return;
      try {
        await this.connect();
        this._folders = null;
        this.emit('reconnected');
      } catch (_) {
        this._scheduleReconnect();
      }
    }, delay);
  }

  async close() {
    this.closing = true;
    if (this._reconnectTimer) {
      clearTimeout(this._reconnectTimer);
      this._reconnectTimer = null;
    }
    try {
      if (this.client) await this.client.logout();
    } catch (_) {
      try { this.client.close(); } catch (_) { /* المقبس مغلق فعلًا */ }
    }
    this.connected = false;
    this.client = null;
  }

  /** تنفيذ عملية داخل الطابور التسلسلي مع ضمان وجود اتصال حيّ. */
  _run(fn) {
    const task = this._queue.then(async () => {
      if (!this.client || !this.client.usable) await this.connect();
      return fn(this.client);
    });
    // الطابور يجب ألا ينكسر عند فشل عملية واحدة.
    this._queue = task.then(() => undefined, () => undefined);
    return task;
  }

  /** تنفيذ عملية داخل قفل مجلد معيّن. */
  _inFolder(path, fn) {
    return this._run(async (client) => {
      const lock = await client.getMailboxLock(path || 'INBOX');
      try {
        return await fn(client, client.mailbox);
      } finally {
        lock.release();
      }
    });
  }

  /* ------------------------------------------------------------- المجلدات */

  async folders(force) {
    if (this._folders && !force) return this._folders;

    const list = await this._run((client) => client.list({ statusQuery: { unseen: true, messages: true } }));
    const out = [];

    for (const box of list) {
      if (box.flags && box.flags.has && box.flags.has('\\Noselect')) continue;

      let special = '';
      const su = (box.specialUse || '').replace('\\', '').toLowerCase();
      if (su && SPECIAL_NAMES[su]) special = su;
      if (!special && /^inbox$/i.test(box.path)) special = 'inbox';
      if (!special) {
        const leaf = String(box.name || box.path).toLowerCase();
        for (const [key, hints] of Object.entries(NAME_HINTS)) {
          if (hints.includes(leaf)) { special = key; break; }
        }
      }

      out.push({
        raw: box.path,
        name: box.name,
        display: special && SPECIAL_NAMES[special] ? SPECIAL_NAMES[special] : { ar: box.name, en: box.name },
        special,
        unseen: box.status ? Number(box.status.unseen) || 0 : 0,
        total: box.status ? Number(box.status.messages) || 0 : 0,
        delimiter: box.delimiter || '/',
      });
    }

    out.sort((a, b) => {
      const ai = a.special ? SPECIAL_ORDER.indexOf(a.special) : 99;
      const bi = b.special ? SPECIAL_ORDER.indexOf(b.special) : 99;
      if (ai !== bi) return ai - bi;
      return String(a.raw).localeCompare(String(b.raw));
    });

    this._folders = out;
    return out;
  }

  /** المسار الفعلي لمجلد مميّز (المرسل/المحذوفات/…) أو '' إن لم يوجد. */
  async specialPath(special) {
    const folders = await this.folders();
    const hit = folders.find((f) => f.special === special);
    return hit ? hit.raw : '';
  }

  async createFolder(path) {
    const res = await this._run((client) => client.mailboxCreate(path));
    this._folders = null;
    return res;
  }

  async quota() {
    return this._run(async (client) => {
      try {
        const q = await client.getQuota('INBOX');
        if (!q || !q.storage) return null;
        const used = Number(q.storage.usage ?? q.storage.used ?? 0);
        const limit = Number(q.storage.limit ?? 0);
        return limit > 0 ? { used, limit } : null;
      } catch (_) {
        return null; // الخادم لا يدعم QUOTA — ليس خطأ.
      }
    });
  }

  /* -------------------------------------------------------- قائمة الرسائل */

  /**
   * صفحة من الرسائل، الأحدث أولًا.
   *
   * @param {{folder:string, page?:number, pageSize?:number, search?:string, filter?:string}} opts
   */
  async list(opts) {
    const folder = opts.folder || 'INBOX';
    const page = Math.max(0, parseInt(opts.page, 10) || 0);
    const size = Math.min(200, Math.max(5, parseInt(opts.pageSize, 10) || 50));
    const search = String(opts.search || '').trim();
    const filter = String(opts.filter || '');

    return this._inFolder(folder, async (client, mailbox) => {
      let uids = null;
      let total = Number(mailbox.exists) || 0;

      if (search || filter) {
        const query = {};
        if (filter === 'unread') query.seen = false;
        if (filter === 'starred') query.flagged = true;
        if (filter === 'attachments') query.header = { 'content-type': 'multipart/mixed' };
        if (search) {
          query.or = [
            { from: search },
            { to: search },
            { subject: search },
            { body: search },
          ];
        }
        uids = await client.search(query, { uid: true });
        uids = (uids || []).sort((a, b) => b - a); // الأحدث أولًا
        total = uids.length;
      }

      if (total === 0) return { folder, total, page, pageSize: size, messages: [] };

      let range;
      if (uids) {
        const slice = uids.slice(page * size, page * size + size);
        if (!slice.length) return { folder, total, page, pageSize: size, messages: [] };
        range = slice.join(',');
      } else {
        const end = total - page * size;
        if (end <= 0) return { folder, total, page, pageSize: size, messages: [] };
        const start = Math.max(1, end - size + 1);
        range = `${start}:${end}`;
      }

      const messages = [];
      const fetchQuery = {
        uid: true,
        flags: true,
        envelope: true,
        size: true,
        internalDate: true,
        bodyStructure: true,
      };
      const fetchOpts = uids ? { uid: true } : {};

      for await (const msg of client.fetch(range, fetchQuery, fetchOpts)) {
        messages.push(shapeHeader(msg));
      }

      messages.sort((a, b) => b.ts - a.ts || b.uid - a.uid);
      return { folder, total, page, pageSize: size, messages };
    });
  }

  /** عدّاد غير المقروء لمجلد واحد (تحديث سريع دون إعادة سرد كل المجلدات). */
  async unseenCount(folder) {
    return this._run(async (client) => {
      const st = await client.status(folder || 'INBOX', { unseen: true, messages: true });
      return { unseen: Number(st.unseen) || 0, total: Number(st.messages) || 0 };
    });
  }

  /* ---------------------------------------------------------- رسالة واحدة */

  /**
   * رسالة كاملة: الترويسات + الجسم النصي + قائمة المرفقات (دون تنزيلها).
   * الصور المضمّنة (cid:) تُحوَّل إلى data: حتى تظهر الرسالة كما أُرسلت بلا
   * أي طلب شبكة خارجي.
   */
  async message(folder, uid, opts) {
    const wantRemote = !!(opts && opts.allowRemote);

    return this._inFolder(folder, async (client) => {
      const msg = await client.fetchOne(String(uid), {
        uid: true,
        flags: true,
        envelope: true,
        size: true,
        internalDate: true,
        bodyStructure: true,
        headers: ['reply-to', 'list-unsubscribe', 'x-priority', 'importance'],
      }, { uid: true });

      if (!msg) throw new Error('MESSAGE_NOT_FOUND');

      const head = shapeHeader(msg);
      const parts = walkStructure(msg.bodyStructure);

      // اختيار أفضل جسم: HTML أولًا ثم النص العادي.
      const htmlPart = parts.body.find((p) => p.type === 'text/html');
      const textPart = parts.body.find((p) => p.type === 'text/plain');
      const chosen = htmlPart || textPart;

      let body = '';
      let isHTML = false;
      if (chosen) {
        body = await downloadText(client, uid, chosen);
        isHTML = chosen.type === 'text/html';
      }

      // الصور المضمّنة داخل الرسالة.
      const inline = {};
      if (isHTML && parts.inline.length) {
        let budget = 6 * 1024 * 1024; // سقف أمان لحجم الصور المضمّنة
        for (const p of parts.inline) {
          if (!p.cid || p.size > budget) continue;
          try {
            const { content } = await client.download(String(uid), p.part, { uid: true });
            const buf = await streamToBuffer(content, budget);
            budget -= buf.length;
            inline[p.cid] = `data:${p.type};base64,${buf.toString('base64')}`;
          } catch (_) {
            // صورة مضمّنة تعذّر تحميلها لا تُفشل عرض الرسالة.
          }
        }
      }

      const replyToHdr = msg.headers ? parseHeaderLine(msg.headers.toString(), 'reply-to') : '';

      return {
        ...head,
        html: isHTML ? body : '',
        text: isHTML ? '' : body,
        inline,
        attachments: parts.attachments.map((a) => ({
          part: a.part,
          filename: a.filename || 'attachment',
          mimeType: a.type,
          size: a.size,
        })),
        replyTo: replyToHdr || (head.from.email ? `${head.from.name || ''} <${head.from.email}>`.trim() : ''),
        allowRemote: wantRemote,
      };
    });
  }

  /** تنزيل مرفق واحد كـ Buffer. */
  async attachment(folder, uid, part) {
    return this._inFolder(folder, async (client) => {
      const { content, meta } = await client.download(String(uid), part, { uid: true });
      const buf = await streamToBuffer(content, 200 * 1024 * 1024);
      return { buffer: buf, filename: (meta && meta.filename) || 'attachment', mimeType: (meta && meta.contentType) || 'application/octet-stream' };
    });
  }

  /** المصدر الخام لرسالة (يُستخدم للرد/التمرير وحفظ .eml). */
  async source(folder, uid) {
    return this._inFolder(folder, async (client) => {
      const { content } = await client.download(String(uid), undefined, { uid: true });
      return streamToBuffer(content, 100 * 1024 * 1024);
    });
  }

  /* ------------------------------------------------------------- الإجراءات */

  async setFlag(folder, uids, flag, on) {
    const list = Array.isArray(uids) ? uids : [uids];
    if (!list.length) return { ok: true };
    return this._inFolder(folder, async (client) => {
      const range = list.join(',');
      const flags = [flag];
      if (on) await client.messageFlagsAdd(range, flags, { uid: true });
      else await client.messageFlagsRemove(range, flags, { uid: true });
      return { ok: true };
    });
  }

  async move(folder, uids, dest) {
    const list = Array.isArray(uids) ? uids : [uids];
    if (!list.length) return { ok: true };
    const res = await this._inFolder(folder, (client) => client.messageMove(list.join(','), dest, { uid: true }));
    this._folders = null;
    return { ok: true, res };
  }

  /** حذف: نقل إلى المحذوفات، أو حذف نهائي إن كنّا داخل المحذوفات. */
  async remove(folder, uids, permanent) {
    const list = Array.isArray(uids) ? uids : [uids];
    if (!list.length) return { ok: true };

    const trash = await this.specialPath('trash');
    const inTrash = trash && folder === trash;

    if (permanent || inTrash || !trash) {
      await this._inFolder(folder, (client) => client.messageDelete(list.join(','), { uid: true }));
      this._folders = null;
      return { ok: true, permanent: true };
    }
    return this.move(folder, list, trash);
  }

  /** إضافة رسالة إلى مجلد (نسخة المرسل / حفظ مسودة). */
  async append(path, raw, flags) {
    return this._run((client) => client.append(path, raw, flags || ['\\Seen'], new Date()));
  }
}

/* ------------------------------------------------------------- مساعدات */

function addr(a) {
  const one = Array.isArray(a) ? a[0] : a;
  if (!one) return { name: '', email: '' };
  return { name: String(one.name || '').trim(), email: String(one.address || '').trim().toLowerCase() };
}

function addrList(a) {
  if (!Array.isArray(a)) return [];
  return a.map((x) => ({ name: String(x.name || '').trim(), email: String(x.address || '').trim().toLowerCase() }))
    .filter((x) => x.email || x.name);
}

function shapeHeader(msg) {
  const env = msg.envelope || {};
  const date = env.date || msg.internalDate || null;
  const flags = msg.flags || new Set();
  return {
    uid: Number(msg.uid),
    seq: Number(msg.seq) || 0,
    seen: flags.has('\\Seen'),
    flagged: flags.has('\\Flagged'),
    answered: flags.has('\\Answered'),
    draft: flags.has('\\Draft'),
    subject: String(env.subject || ''),
    from: addr(env.from),
    to: addrList(env.to),
    cc: addrList(env.cc),
    bcc: addrList(env.bcc),
    messageId: String(env.messageId || ''),
    inReplyTo: String(env.inReplyTo || ''),
    date: date ? new Date(date).toISOString() : '',
    ts: date ? new Date(date).getTime() : 0,
    size: Number(msg.size) || 0,
    hasAttachments: structureHasAttachment(msg.bodyStructure),
  };
}

/** هل تحتوي البنية على مرفق حقيقي (وليس مجرد نسخة HTML)؟ */
function structureHasAttachment(node) {
  if (!node) return false;
  const disp = String(node.disposition || '').toLowerCase();
  const type = String(node.type || '').toLowerCase();
  const fname = (node.dispositionParameters && node.dispositionParameters.filename) || (node.parameters && node.parameters.name);
  if (disp === 'attachment' && fname) return true;
  if (fname && !type.startsWith('text/') && !type.startsWith('multipart/')) return true;
  if (Array.isArray(node.childNodes)) return node.childNodes.some(structureHasAttachment);
  return false;
}

/**
 * تفكيك بنية MIME إلى: أجسام نصية، صور مضمّنة، مرفقات.
 * نتجاهل أجسام النصوص البديلة داخل الأجزاء المرفقة (رسالة داخل رسالة).
 */
function walkStructure(root) {
  const out = { body: [], inline: [], attachments: [] };
  if (!root) return out;

  const visit = (node, insideAttachment) => {
    if (!node) return;
    const type = String(node.type || '').toLowerCase();

    if (Array.isArray(node.childNodes) && node.childNodes.length) {
      const nested = insideAttachment || type === 'message/rfc822';
      for (const child of node.childNodes) visit(child, nested);
      return;
    }

    const disp = String(node.disposition || '').toLowerCase();
    const filename = decodeMimeWord(
      (node.dispositionParameters && node.dispositionParameters.filename) ||
      (node.parameters && node.parameters.name) || ''
    );
    const cid = String(node.id || '').replace(/^<|>$/g, '');
    const item = {
      part: node.part || '1',
      type: type || 'application/octet-stream',
      size: Number(node.size) || 0,
      charset: (node.parameters && node.parameters.charset) || '',
      filename,
      cid,
    };

    const isText = type === 'text/plain' || type === 'text/html';

    if (!insideAttachment && isText && disp !== 'attachment' && !filename) {
      out.body.push(item);
      return;
    }
    if (disp === 'inline' && cid && type.startsWith('image/')) {
      out.inline.push(item);
      return;
    }
    if (cid && type.startsWith('image/') && !filename) {
      out.inline.push(item);
      return;
    }
    if (filename || disp === 'attachment' || !isText) {
      out.attachments.push(item);
    }
  };

  visit(root, false);
  return out;
}

async function downloadText(client, uid, part) {
  try {
    const { content, meta } = await client.download(String(uid), part.part, { uid: true });
    const buf = await streamToBuffer(content, 20 * 1024 * 1024);
    // ImapFlow يحوّل الأجزاء النصية إلى UTF-8 ويضع الترميز الناتج في meta.
    // نتبع meta لا BODYSTRUCTURE، وإلا فككنا الترميز مرتين وخرج نص مشوّه.
    // ما عجز ImapFlow عن تحويله يبقى بترميزه الأصلي، و TextDecoder يغطي
    // ترميزات أوسع فيلتقط الباقي.
    return decodeBuffer(buf, (meta && meta.charset) || part.charset || 'utf-8');
  } catch (_) {
    return '';
  }
}

function decodeBuffer(buf, charset) {
  const cs = String(charset || 'utf-8').toLowerCase().replace(/['"]/g, '');
  try {
    return new TextDecoder(cs, { fatal: false }).decode(buf);
  } catch (_) {
    try {
      return new TextDecoder('utf-8', { fatal: false }).decode(buf);
    } catch (_) {
      return buf.toString('utf8');
    }
  }
}

function streamToBuffer(stream, maxBytes) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    stream.on('data', (c) => {
      size += c.length;
      if (size > maxBytes) {
        stream.destroy();
        reject(new Error('ATTACHMENT_TOO_LARGE'));
        return;
      }
      chunks.push(c);
    });
    stream.on('end', () => resolve(Buffer.concat(chunks)));
    stream.on('error', reject);
  });
}

/** فك ترميز =?utf-8?B?…?= في أسماء الملفات. */
function decodeMimeWord(str) {
  const s = String(str || '');
  if (!s.includes('=?')) return s;
  return s.replace(/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/g, (m, cs, enc, data) => {
    try {
      if (/^b$/i.test(enc)) return decodeBuffer(Buffer.from(data, 'base64'), cs);
      const bytes = Buffer.from(data.replace(/_/g, ' ').replace(/=([0-9A-Fa-f]{2})/g, (x, h) => String.fromCharCode(parseInt(h, 16))), 'binary');
      return decodeBuffer(bytes, cs);
    } catch (_) {
      return m;
    }
  });
}

function parseHeaderLine(raw, name) {
  const re = new RegExp(`^${name}\\s*:\\s*(.*(?:\\r?\\n[ \\t].*)*)`, 'im');
  const m = re.exec(String(raw || ''));
  return m ? decodeMimeWord(m[1].replace(/\r?\n[ \t]/g, ' ').trim()) : '';
}

module.exports = { MailSession, SPECIAL_NAMES, decodeMimeWord };
