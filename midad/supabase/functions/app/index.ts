// رابط مِداد القصير — يحوّل إلى الاستضافة الفعلية.
//
// لماذا لا يُقدَّم الموقع من هنا مباشرة؟
// لأنّ Supabase تستبدل text/html بـ text/plain في دوالّ الحافّة والتخزين معًا
// (سياسة مكافحة تصيّد)، فيظهر الموقع نصًّا خامًا لا صفحةً مرسومة.
// فحصٌ مباشر: text/html → text/plain ، و text/css و application/json تمرّان سليمين.
const SITE = 'https://raw.githack.com/ahmedsalemawbal-cmd/aHMED/claude/educational-platform-setup-ct0iwr/midad/deploy/index.html'

Deno.serve((req) => {
  const url = new URL(req.url)
  const path = url.pathname.replace(/^\/functions\/v1/, '').replace(/^\/app(?=\/|$)\/?/, '')

  if (path === 'health') {
    return new Response(JSON.stringify({ ok: true, redirectsTo: SITE }), {
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    })
  }

  const target = SITE + (url.search || '') + (url.hash || '')
  return new Response(null, {
    status: 302,
    headers: { Location: target, 'Cache-Control': 'no-store' },
  })
})
