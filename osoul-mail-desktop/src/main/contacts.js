/**
 * Osoul Mail — دفتر العناوين.
 *
 * لا يوجد خادم CardDAV هنا، ولا نريد دفترًا يدويًا يبقى فارغًا. العناوين
 * تُستخرج من صندوق البريد نفسه: من أرسل إليك (الوارد) ومن أرسلت إليه
 * (المرسل). النتيجة دفتر يملأ نفسه ويعكس من تتعامل معه فعلًا.
 *
 * نقرأ الترويسات فقط (envelope) ولا نحمّل أي جسم رسالة، فالمسح رخيص حتى على
 * صندوق كبير، ويُخزَّن في الذاكرة لبقية الجلسة.
 */

'use strict';

/** كم رسالة نمسح من كل مجلد. يغطي أشهرًا من العمل دون إبطاء الفتح. */
const SCAN_LIMIT = 600;

/** عناوين لا تُضاف للدفتر: مرسلون آليون لا يُراسَلون. */
const NOREPLY = /^(no-?reply|do-?not-?reply|bounce|mailer-daemon|postmaster|notifications?)@|@(bounce|mailer)\./i;

function key(email) {
  return String(email || '').trim().toLowerCase();
}

/** الاسم الأفضل بين اسمين: نفضّل الاسم الحقيقي على البريد المكرّر. */
function betterName(current, candidate, email) {
  const c = String(candidate || '').trim();
  if (!c) return current;
  if (c.toLowerCase() === key(email)) return current; // الاسم هو البريد نفسه
  if (!current) return c;
  // اسم مكوّن من كلمتين أفضل من كلمة واحدة عادةً.
  return c.split(/\s+/).length > current.split(/\s+/).length ? c : current;
}

/**
 * بناء دفتر العناوين من صندوق البريد.
 *
 * @param {import('./imap').MailSession} session
 * @param {string} me بريد الموظف — يُستبعد من دفتره
 * @returns {Promise<Array<{email:string,name:string,count:number,lastTs:number,outgoing:boolean}>>}
 */
async function build(session, me) {
  const folders = await session.folders();
  const inbox = folders.find((f) => f.special === 'inbox');
  const sent = folders.find((f) => f.special === 'sent');
  const mine = key(me);

  const map = new Map();

  const add = (addr, ts, outgoing) => {
    const email = key(addr && addr.email);
    if (!email || email === mine) return;
    if (NOREPLY.test(email)) return;

    const found = map.get(email);
    if (found) {
      found.count++;
      found.name = betterName(found.name, addr.name, email);
      if (ts > found.lastTs) found.lastTs = ts;
      if (outgoing) found.outgoing = true;
    } else {
      map.set(email, {
        email,
        name: betterName('', addr.name, email),
        count: 1,
        lastTs: ts || 0,
        outgoing: !!outgoing,
      });
    }
  };

  // الوارد: من راسلك.
  if (inbox) {
    const page = await session.list({ folder: inbox.raw, page: 0, pageSize: SCAN_LIMIT });
    for (const m of page.messages) add(m.from, m.ts, false);
  }

  // المرسل: من راسلتَه — هؤلاء أهم، فهم من تكتب إليهم فعلًا.
  if (sent) {
    const page = await session.list({ folder: sent.raw, page: 0, pageSize: SCAN_LIMIT });
    for (const m of page.messages) {
      for (const a of [...(m.to || []), ...(m.cc || [])]) add(a, m.ts, true);
    }
  }

  const list = [...map.values()];
  // من راسلتَه أولًا، ثم الأكثر تكرارًا، ثم الأحدث.
  list.sort((a, b) => (Number(b.outgoing) - Number(a.outgoing)) || (b.count - a.count) || (b.lastTs - a.lastTs));
  return list;
}

module.exports = { build, SCAN_LIMIT };
