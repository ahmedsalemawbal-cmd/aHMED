/**
 * يُشغّل مستورد كلود ديزاين كما يُشغَّل عند المالك — في متصفّح.
 *
 * ولا يُقرأ ناتجٌ محفوظٌ من قبل: الملفّ المحفوظ يتخلّف عن الشيفرة متى
 * غُيّر المستورد ولم يُعَد توليده، فيمرّ الفحص على متنٍ قديمٍ ويطمئنّ.
 * فيُبنى المستورد ويُنفَّذ في كلّ مرّة.
 *
 * ويلزم أن يكون في متصفّحٍ يحمل أنماطنا: المستورد يقيس أعمدة الجداول
 * ليُثبّتها، والقياس خارج تلك الأنماط يُعطي أعمدةً لا تُشبه ما سيُرسم.
 */

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { APP, TESTS, CAIRO, waitForFonts } from './harness.mjs'

let cachedBundle = null

/** يبني حزمةً من المستورد صالحةً للتنفيذ في صفحة. */
function bundle() {
  if (cachedBundle) return cachedBundle
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'midad-import-'))
  const entry = path.join(dir, 'entry.ts')
  const out = path.join(dir, 'importer.js')
  fs.writeFileSync(entry,
    `import { importDesignHtml } from '${path.join(APP, 'src/lib/importDesign')}'\n` +
    `;(globalThis as any).__midadImport = importDesignHtml\n`)
  execFileSync(path.join(APP, 'node_modules/.bin/esbuild'), [
    entry, '--bundle', '--format=iife', `--outfile=${out}`,
    '--platform=browser', '--log-level=error',
  ], { stdio: ['ignore', 'ignore', 'inherit'] })
  cachedBundle = fs.readFileSync(out, 'utf8')
  fs.rmSync(dir, { recursive: true, force: true })
  return cachedBundle
}

/**
 * يستورد ملفّ تصميمٍ ويردّ ما يردّه المستورد للمالك.
 * @param {import('playwright').BrowserContext} ctx
 * @param {string} fixture اسم الملفّ في tests/fixtures
 */
export async function importDesign(ctx, fixture = 'nafs.design.html') {
  const raw = fs.readFileSync(path.join(TESTS, 'fixtures', fixture), 'utf8')
  const css = fs.readFileSync(path.join(APP, 'src/ui/midad.css'), 'utf8')

  const p = await ctx.newPage()
  await p.setContent('<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"></head><body style="margin:0"></body></html>')
  await p.addStyleTag({ content: CAIRO })
  await p.addStyleTag({ content: css })
  await p.addScriptTag({ content: bundle() })
  await waitForFonts(p)

  const result = await p.evaluate(
    ([src, name]) => globalThis.__midadImport(src, name),
    [raw, fixture])

  await p.close()
  return result
}

/** يفتح ملفّ التصميم الأصليّ نفسه — مرجعُ المقارنة. */
export async function openOriginal(ctx, fixture = 'nafs.design.html') {
  const p = await ctx.newPage()
  await p.goto('file://' + path.join(TESTS, 'fixtures', fixture), { waitUntil: 'domcontentloaded' })
  await p.addStyleTag({ content: CAIRO })
  await waitForFonts(p)
  return p
}
