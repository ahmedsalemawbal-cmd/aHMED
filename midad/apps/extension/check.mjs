/**
 * فاحص الحزمة — يُشغَّل قبل كلّ تسليم.
 *
 * وُلد من عطبٍ حقيقيّ: كتبتُ `default_locale` في البيان ولم أُنشئ مجلّد
 * `_locales`، فرفض كروم تحميل الإضافة كلّها برسالةٍ لا يفهمها إلّا من
 * يعرف الاشتراط. وفحصي يومها كان يتحقّق من الأذون والمراجع ولا يتحقّق
 * من هذا — فكان فحصًا يطمئن ولا يحمي.
 *
 * والقاعدة التي خرجت منه: **كلّ مفتاحٍ في البيان يَعِد بملفٍّ يجب أن
 * يُفحص وجودُ ملفّه.** لا مفتاحًا بعينه، بل الصنف كلّه.
 *
 * يُشغَّل: node check.mjs
 */

import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'

const HERE = path.dirname(new URL(import.meta.url).pathname)
const R = (p) => path.join(HERE, p)
const exists = (p) => fs.existsSync(R(p))

let bad = 0
const ok = (cond, label, extra = '') => {
  if (!cond) bad++
  console.log(`${cond ? '✅' : '✗ '} ${label}${extra ? ' — ' + extra : ''}`)
}

const m = JSON.parse(fs.readFileSync(R('manifest.json'), 'utf8'))

console.log('═══ البيان ═══')
ok(m.manifest_version === 3, 'MV3', `v${m.version}`)
ok(!!m.name && !!m.description, 'اسمٌ ووصف')

/* ── ما يَعِد به البيان من ملفّات ── */
console.log('\n═══ الملفّات الموعودة ═══')
const promises = []
const add = (p, why) => { if (p) promises.push([p, why]) }

add(m.background?.service_worker, 'background.service_worker')
add(m.action?.default_popup, 'action.default_popup')
for (const [k, v] of Object.entries(m.action?.default_icon || {})) add(v, `action.default_icon.${k}`)
for (const [k, v] of Object.entries(m.icons || {})) add(v, `icons.${k}`)
for (const cs of m.content_scripts || []) {
  for (const j of cs.js || []) add(j, 'content_scripts.js')
  for (const c of cs.css || []) add(c, 'content_scripts.css')
}
for (const war of m.web_accessible_resources || []) {
  for (const r of war.resources || []) if (!r.includes('*')) add(r, 'web_accessible_resources')
}

for (const [p, why] of promises) ok(exists(p), p, why)

/* الفخّ الذي أوقعني: مفتاحٌ يَعِد بمجلّدٍ لا بملفّ */
if (m.default_locale) {
  ok(exists(`_locales/${m.default_locale}/messages.json`),
    `_locales/${m.default_locale}/messages.json`,
    'يشترطه default_locale — وبدونه يرفض كروم الإضافة كلّها')
} else {
  ok(!exists('_locales'), 'لا default_locale ولا _locales', 'متّسقان')
}

/* ── النافذة تُشير إلى ملفّاتٍ موجودة ── */
console.log('\n═══ النافذة ═══')
if (m.action?.default_popup) {
  const dir = path.dirname(m.action.default_popup)
  const html = fs.readFileSync(R(m.action.default_popup), 'utf8')
  const refs = [...html.matchAll(/(?:src|href)="([^"]+)"/g)].map((x) => x[1])
    .filter((u) => !/^https?:/.test(u))
  for (const r of refs) ok(exists(path.join(dir, r)), `${r}`, 'مُشارٌ إليه من النافذة')
  ok(!/type="module"/.test(html), 'بلا وحدات', 'سكربتات المحتوى لا تقبلها، والنواة مشتركة')
}

/* ── الشيفرة ── */
console.log('\n═══ الشيفرة ═══')
const jsFiles = []
const walk = (d) => {
  for (const e of fs.readdirSync(R(d), { withFileTypes: true })) {
    if (e.name === 'node_modules' || e.name.startsWith('.')) continue
    const p = path.join(d, e.name)
    if (e.isDirectory()) walk(p)
    else if (e.name.endsWith('.js')) jsFiles.push(p)
  }
}
walk('src')

for (const f of jsFiles) {
  const src = fs.readFileSync(R(f), 'utf8')
  try { new vm.Script(src, { filename: f }); ok(true, f, 'سكربتٌ عاديٌّ صالح') }
  catch (e) { ok(false, f, e.message) }
}

const all = jsFiles.map((f) => fs.readFileSync(R(f), 'utf8')).join('\n')
ok(!/\beval\s*\(|new Function\s*\(/.test(all), 'لا eval ولا new Function', 'المتجر يرفضهما')
const remote = [...all.matchAll(/https?:\/\/[^\s'"`)]+/g)].map((x) => x[0])
  .filter((u) => !/ehimyixcqnmnwgbqrdmr|ahmedawbal\.com|w3\.org|openxmlformats/.test(u))
ok(remote.length === 0, 'لا موارد بعيدة', remote.slice(0, 3).join(' · ') || 'نظيف')

/* ── الأذون ── */
console.log('\n═══ الأذون ═══')
ok(!(m.host_permissions || []).some((h) => h.includes('all_urls') || h === '*://*/*'),
  'لا <all_urls>', (m.host_permissions || []).join(' · '))
const allowed = new Set(['activeTab', 'storage', 'scripting'])
const extra = (m.permissions || []).filter((p) => !allowed.has(p))
ok(extra.length === 0, 'أذونٌ دنيا', extra.length ? `زائد: ${extra}` : (m.permissions || []).join(' · '))

console.log(`\n═══ ${bad === 0 ? 'سليمة ✅' : `${bad} عطبًا ✗`} ═══`)
process.exit(bad === 0 ? 0 : 1)
