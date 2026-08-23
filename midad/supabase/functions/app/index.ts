// خادم واجهة مِداد — يقدّم بناء الإنتاج من المستودع ويخزّنه في الذاكرة.
const BASE = 'https://raw.githubusercontent.com/ahmedsalemawbal-cmd/aHMED/claude/educational-platform-setup-ct0iwr/midad/deploy'

const TYPES: Record<string, string> = {
  html: 'text/html; charset=utf-8',
  js: 'application/javascript; charset=utf-8',
  css: 'text/css; charset=utf-8',
  svg: 'image/svg+xml',
  webmanifest: 'application/manifest+json; charset=utf-8',
  json: 'application/json; charset=utf-8',
  png: 'image/png',
  ico: 'image/x-icon',
}

const cache = new Map<string, { body: Uint8Array; type: string }>()

async function load(path: string): Promise<{ body: Uint8Array; type: string } | null> {
  const hit = cache.get(path)
  if (hit) return hit
  const res = await fetch(`${BASE}/${path}`, { headers: { 'User-Agent': 'midad-app' } })
  if (!res.ok) return null
  const body = new Uint8Array(await res.arrayBuffer())
  const ext = path.split('.').pop() || 'html'
  const entry = { body, type: TYPES[ext] || 'application/octet-stream' }
  cache.set(path, entry)
  return entry
}

Deno.serve(async (req) => {
  const url = new URL(req.url)
  let path = url.pathname.replace(/^\/functions\/v1/, '').replace(/^\/app\/?/, '')
  if (path === '' || path === '/') path = 'index.html'
  path = path.replace(/^\/+/, '')

  if (path === 'health') {
    return new Response(JSON.stringify({ ok: true, cached: [...cache.keys()] }), {
      headers: { 'Content-Type': 'application/json' },
    })
  }
  if (path === 'refresh') {
    cache.clear()
    return new Response(JSON.stringify({ ok: true, cleared: true }), {
      headers: { 'Content-Type': 'application/json' },
    })
  }
  if (path.includes('..')) return new Response('Bad request', { status: 400 })

  let asset = await load(path)
  // أيّ مسار غير ملفّ يرجع إلى الواجهة (تطبيق صفحة واحدة)
  if (!asset && !path.includes('.')) asset = await load('index.html')
  if (!asset) return new Response('Not found', { status: 404 })

  const immutable = path.startsWith('assets/')
  return new Response(asset.body, {
    headers: {
      'Content-Type': asset.type,
      'Cache-Control': immutable ? 'public, max-age=600' : 'public, max-age=60',
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'strict-origin-when-cross-origin',
    },
  })
})
