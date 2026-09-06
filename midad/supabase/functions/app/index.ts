// رابط مِداد القصير — يحوّل إلى الاستضافة الفعلية.
//
// لماذا لا يُقدَّم الموقع من هنا مباشرة؟
// لأنّ Supabase تستبدل text/html بـ text/plain في دوالّ الحافّة والتخزين معًا
// (سياسة مكافحة تصيّد)، فيظهر الموقع نصًّا خامًا لا صفحةً مرسومة.
// فحصٌ مباشر: text/html → text/plain ، و text/css و application/json تمرّان سليمين.
//
// ولماذا نُثبّت رقم الإيداع (commit SHA) في الرابط؟
// لأنّ githack يخزّن رابط الفرع مؤقّتًا. فمن يفتح الموقع بعد نشرٍ جديد يبقى
// على النسخة القديمة حتّى ينتهي التخزين — أو حتّى يمسح ذاكرة متصفّحه، وهو
// ما لا يُطلب من مستخدم. وقع هذا فعلًا: نُشر محرّرٌ جديد وبقي المالك يرى
// القديم. فنسأل GitHub عن آخر إيداعٍ في الفرع ونوجّه إلى رابطٍ يحمله —
// ورابطُ إيداعٍ لا يتكرّر، فلا تخزينَ يُبقي على القديم.

const OWNER = 'ahmedsalemawbal-cmd'
const REPO = 'aHMED'
const BRANCH = 'claude/educational-platform-setup-ct0iwr'
const FILE = 'midad/deploy/index.html'

/** الرابط الاحتياطيّ: يعمل دائمًا وإن تعذّر سؤال GitHub */
const FALLBACK = `https://raw.githack.com/${OWNER}/${REPO}/${BRANCH}/${FILE}`

/** ذاكرةٌ قصيرة: لا نسأل GitHub في كلّ طلب، ولا نتأخّر عن نشرٍ جديد */
let cachedSha = ''
let cachedAt = 0
const TTL_MS = 60_000

async function latestSha(): Promise<string> {
  const now = Date.now()
  if (cachedSha && now - cachedAt < TTL_MS) return cachedSha
  try {
    const res = await fetch(
      `https://api.github.com/repos/${OWNER}/${REPO}/commits/${encodeURIComponent(BRANCH)}`,
      {
        headers: { Accept: 'application/vnd.github.sha', 'User-Agent': 'midad-app-redirect' },
        signal: AbortSignal.timeout(4000),
      },
    )
    if (!res.ok) return cachedSha
    const sha = (await res.text()).trim()
    if (/^[0-9a-f]{40}$/.test(sha)) { cachedSha = sha; cachedAt = now }
    return cachedSha
  } catch {
    return cachedSha        // شبكةٌ متعثّرة: نُبقي على آخر ما عرفناه
  }
}

Deno.serve(async (req) => {
  const url = new URL(req.url)
  const path = url.pathname.replace(/^\/functions\/v1/, '').replace(/^\/app(?=\/|$)\/?/, '')

  const sha = await latestSha()
  const site = sha
    ? `https://rawcdn.githack.com/${OWNER}/${REPO}/${sha}/${FILE}`
    : FALLBACK

  if (path === 'health') {
    return new Response(JSON.stringify({ ok: true, sha: sha || null, redirectsTo: site }), {
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    })
  }

  const target = site + (url.search || '') + (url.hash || '')
  return new Response(null, {
    status: 302,
    headers: { Location: target, 'Cache-Control': 'no-store' },
  })
})
