/**
 * كُتلُ المستند — تحريرٌ بلا DOM.
 *
 * المتصفّح يعطي المحرّرَ شجرةً كاملةً فيتصرّف فيها كما يشاء. والجوّال لا
 * شجرةَ فيه: لا `document`، ولا `innerHTML`، ولا محرّرَ نصٍّ غنيّ.
 *
 * وبناءُ محرّرٍ كاملٍ في React Native مشروعٌ قائمٌ بذاته — وليس هو ما
 * يحتاجه الموظّف بعد أن يُركّب الذكاءُ ملفَّه. هو يحتاج ثلاثًا: أن يحذف
 * فقرةً لا تعجبه، وأن يرفع سطرًا، وأن يصحّح كلمة.
 *
 *     ثلاثُ عمليّاتٍ تعمل خيرٌ من محرّرٍ كاملٍ لا يُبنى.
 *
 * فيُقسَّم المتنُ إلى **كُتلٍ من المستوى الأوّل**: عنوانٌ، فقرةٌ، قائمةٌ،
 * جدولُ صور. تُرفع الكتلةُ وتُنزَّل وتُحذف ويُعدَّل نصُّها — ثمّ تُلحَم
 * كما كانت. والصفحاتُ تبقى صفحاتٍ لأنّ حدودَها كُتلٌ هي الأخرى.
 *
 * ولا يُلمس ما لا يُفهم: كتلةٌ لم يُعرف نوعُها تُعرض كما هي وتُنقل كما
 * هي. فالتحريرُ لا يُتلف ما لم يُصمَّم له.
 */

export interface Block {
  /** رقمُ الصفحة التي جاءت منها — تُعاد إليها عند اللحم. */
  page: number
  /** الوسم: h1 · h2 · p · ul · table · div … */
  tag: string
  /** المتن الأصليّ كاملًا بوسمه وسماته. */
  html: string
  /** نصُّها المجرّد — للعرض والتحرير. فارغٌ لما لا نصَّ فيه (الصور). */
  text: string
  /** عددُ الصور فيها — تُعرض بدلًا من النصّ. */
  images: number
}

const VOID = new Set(['img', 'br', 'hr', 'input', 'meta', 'link'])

/** يقرأ أبناءَ المستوى الأوّل لمتنٍ ما — بعدّ العمق لا بالتعبير النمطيّ. */
function topLevel(html: string): { tag: string; html: string }[] {
  const out: { tag: string; html: string }[] = []
  let i = 0
  while (i < html.length) {
    const lt = html.indexOf('<', i)
    if (lt < 0) break
    const m = /^<([a-zA-Z][\w-]*)/.exec(html.slice(lt, lt + 40))
    if (!m) { i = lt + 1; continue }

    const tag = m[1].toLowerCase()
    const open = html.indexOf('>', lt)
    if (open < 0) break

    /* الوسمُ الفارغ أو المغلق ذاتيًّا كتلةٌ بنفسه. */
    if (VOID.has(tag) || html[open - 1] === '/') {
      out.push({ tag, html: html.slice(lt, open + 1) })
      i = open + 1
      continue
    }

    /* وإلّا يُبحث عن مُغلِقه على العمق نفسه: كلُّ `<tag` يزيد، وكلُّ
       `</tag>` ينقص. وهذا ما يجعل جدولًا داخل جدولٍ لا يقطع الكتلة. */
    let depth = 1
    let j = open + 1
    const oRe = new RegExp(`<${tag}(?=[\\s>/])`, 'gi')
    const cRe = new RegExp(`</${tag}\\s*>`, 'gi')
    while (depth > 0 && j < html.length) {
      oRe.lastIndex = j; cRe.lastIndex = j
      const o = oRe.exec(html)
      const cl = cRe.exec(html)
      if (!cl) { j = html.length; break }
      if (o && o.index < cl.index) { depth++; j = o.index + 1 }
      else { depth--; j = cl.index + cl[0].length }
    }
    out.push({ tag, html: html.slice(lt, j) })
    i = j
  }
  return out
}

const strip = (h: string) => h
  .replace(/<br\s*\/?>/gi, '\n')
  .replace(/<\/(p|li|h[1-6]|div|tr)>/gi, '\n')
  .replace(/<[^>]*>/g, '')
  .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&')
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
  .replace(/[ \t]+/g, ' ')
  .replace(/\n{3,}/g, '\n\n')
  .trim()

/** يفكّ المستندَ إلى كُتلٍ مرقّمةٍ بصفحاتها. */
export function toBlocks(html: string): Block[] {
  const pages = topLevel(html).filter((n) => /data-page/.test(n.html))
  /* متنٌ بلا صناديقِ صفحاتٍ صفحةٌ واحدة — لا صفر. */
  const src = pages.length
    ? pages
    : [{ tag: 'div', html: `<div data-page="true">${html}</div>` }]

  const out: Block[] = []
  src.forEach((pg, pi) => {
    const inner = pg.html.replace(/^<div[^>]*>/i, '').replace(/<\/div>\s*$/i, '')
    for (const n of topLevel(inner)) {
      out.push({
        page: pi,
        tag: n.tag,
        html: n.html,
        text: strip(n.html),
        images: (n.html.match(/<img\b/gi) || []).length,
      })
    }
  })
  return out
}

/**
 * ويلحمها كما كانت — صفحةً صفحة.
 *
 * والصفحاتُ تُشتقّ من **تتابع** أرقامها لا من قيمتها: كُتلٌ متجاورةٌ
 * تحمل الرقم نفسه صفحةٌ واحدة، وأوّلُ رقمٍ مختلفٍ يفتح صفحةً جديدة.
 * فلو حُذفت كلُّ كُتل صفحةٍ لم تبقَ صفحةٌ فارغةٌ في الملفّ.
 */
export function fromBlocks(blocks: Block[], pageStyle = 'padding:56px 48px'): string {
  if (!blocks.length) return ''
  const pages: string[][] = []
  let prev: number | null = null
  for (const b of blocks) {
    if (prev === null || b.page !== prev) pages.push([])
    pages[pages.length - 1].push(b.html)
    prev = b.page
  }
  return pages.map((p) => `<div data-page="true" style="${pageStyle}">${p.join('')}</div>`).join('')
}

/* ═══════════════ العمليّات الثلاث ═══════════════ */

export function moveBlock(blocks: Block[], from: number, dir: -1 | 1): Block[] {
  const to = from + dir
  if (to < 0 || to >= blocks.length) return blocks
  /* والكتلةُ تأخذ صفحةَ من بادلته: رفعُ أوّلِ كتلةٍ في صفحةٍ يُلحقها
     بالصفحة السابقة — وهذا ما يراه المستعمل حين يضغط السهم.
     ولا تُبدَّل الكتلةُ في مكانها: التبديلُ الموضعيّ يجعل الحالةَ
     القديمة والجديدة كائنًا واحدًا، فلا يُعيد React الرسم. */
  const moved = { ...blocks[from], page: blocks[to].page }
  const out = blocks.slice()
  out.splice(from, 1)
  out.splice(to, 0, moved)
  return out
}

export function removeBlock(blocks: Block[], at: number): Block[] {
  return blocks.filter((_, i) => i !== at)
}

/**
 * تعديلُ نصّ كتلةٍ — بحفظ وسمها وسماتها.
 *
 * ولا يُعاد بناءُ الكتلة من الصفر: لو استُبدلت `<p style="…">` بـ`<p>`
 * ضاع مقاسُ الخطّ ومحاذاتُه، وخرج الملفّ متفاوتًا. فيُستبدل **المحتوى
 * وحده** بين الوسمين.
 *
 * وما فيه بنيةٌ داخليّة (قائمةٌ أو جدول) لا يُعدَّل نصُّه سطرًا واحدًا:
 * القائمةُ تُعدَّل بندًا بندًا، والسطرُ الواحد يمحو بنودَها.
 */
export function editable(b: Block): boolean {
  return !b.images && /^(p|h[1-6]|li|td|th|figcaption)$/.test(b.tag)
}

const esc = (s: string) => s
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

export function setText(b: Block, text: string): Block {
  if (!editable(b)) return b
  const open = b.html.indexOf('>')
  const close = b.html.lastIndexOf('<')
  if (open < 0 || close <= open) return b
  const body = esc(text).replace(/\n/g, '<br>')
  const html = b.html.slice(0, open + 1) + body + b.html.slice(close)
  return { ...b, html, text }
}
