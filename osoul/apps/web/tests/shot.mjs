/**
 * صورةُ الموقع كما يراه الزائر — من البناء لا من خادم التطوير.
 * `node tests/shot.mjs [مسار…]`
 */
import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { extname, join, resolve } from 'node:path'
import { launchBrowser } from './lib/browser.mjs'

const DIST = resolve('dist'); const PORT = 4198
const MIME = { '.html':'text/html; charset=utf-8', '.js':'text/javascript', '.css':'text/css',
  '.woff2':'font/woff2', '.svg':'image/svg+xml', '.png':'image/png', '.json':'application/json' }

const server = createServer(async (req, res) => {
  const url = decodeURIComponent((req.url || '/').split('?')[0])
  let f = join(DIST, url)
  if (!extname(url) || !existsSync(f)) f = join(DIST, 'index.html')
  try { res.writeHead(200, { 'Content-Type': MIME[extname(f)] || 'application/octet-stream' }); res.end(await readFile(f)) }
  catch { res.writeHead(404); res.end() }
})
await new Promise((r) => server.listen(PORT, '127.0.0.1', r))

const browser = await launchBrowser()
const routes = process.argv.slice(2).length ? process.argv.slice(2) : ['/']

for (const theme of ['light', 'dark']) {
  const ctx = await browser.newContext({
    viewport: { width: 1360, height: 1000 }, deviceScaleFactor: 2,
    colorScheme: theme,
  })
  const page = await ctx.newPage()
  for (const r of routes) {
    await page.goto(`http://127.0.0.1:${PORT}${r}`, { waitUntil: 'networkidle' })
    // الخطُّ يُنتظَر صراحةً: بدونه تُقاس أبعادٌ ليست هي، وتخرج الصورةُ بخطٍّ آخر
    await page.evaluate(() => document.fonts.ready)
    const name = 'shot' + (r === '/' ? '-home' : r.replace(/\//g, '-')) + '-' + theme + '.png'
    await page.screenshot({ path: name, fullPage: true })
    console.log('  ✓ ' + name)
  }
  await ctx.close()
}
await browser.close(); server.close()
