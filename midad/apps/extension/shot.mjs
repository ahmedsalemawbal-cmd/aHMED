/**
 * صورةٌ للّوحة من شيفرتها لا من محاكاتها.
 *
 * الرسمُ اليدويّ يُري ما قصدتُه؛ والتشغيلُ يُري ما كُتب. وبينهما يقع
 * كلُّ عطبٍ في التنفيذ.
 */
import fs from 'node:fs'
import path from 'node:path'
import { launch } from '../web/tests/lib/harness.mjs'

const EXT = new URL('./src/', import.meta.url).pathname
const OUT = process.argv[2]
const core = fs.readFileSync(path.join(EXT, 'core.js'), 'utf8')
const panel = fs.readFileSync(path.join(EXT, 'panel.js'), 'utf8')

/* صفحةُ نور مُقلَّدةً: جدولُ درجاتٍ بخلايا مدموجةٍ ورأسين — كما هي. */
const NOOR = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>
 body{margin:0;font-family:"Segoe UI",Tahoma,sans-serif;background:#fff;color:#333}
 .top{background:#1F4E79;color:#fff;padding:8px 14px;font-size:13px}
 .sub{background:#E8EDF3;padding:6px 14px;font-size:12px;color:#41608C;border-bottom:1px solid #C9D6E4}
 .c{padding:14px}
 h2{font-size:15px;color:#1F4E79;margin:0 0 10px}
 table{width:100%;border-collapse:collapse;font-size:12px}
 th,td{border:1px solid #B9C7D6;padding:5px 7px;text-align:right}
 thead th{background:#DCE6F1;color:#1F4E79}
</style></head><body>
<div class="top">نظام نور — الإدارة العامة للتعليم</div>
<div class="sub">الرئيسية ‹ الدرجات ‹ رصد درجات الطلاب</div>
<div class="c">
<h2>كشف درجات — الصف الأوّل «أ» — الرياضيات</h2>
<table>
 <thead>
  <tr><th rowspan="2">م</th><th rowspan="2">اسم الطالب</th>
      <th colspan="2">الفترة الأولى</th><th colspan="2">الفترة الثانية</th>
      <th rowspan="2">النهائي</th></tr>
  <tr><th>أعمال</th><th>اختبار</th><th>أعمال</th><th>اختبار</th></tr>
 </thead>
 <tbody>
  <tr><td>١</td><td>أحمد سالم الغامدي</td><td>18</td><td>19</td><td>19</td><td>20</td><td>95</td></tr>
  <tr><td>٢</td><td>محمّد عبدالله القحطاني</td><td>17</td><td>18</td><td>18</td><td>17</td><td>88</td></tr>
  <tr><td>٣</td><td>ياسر فوّاز الجابري</td><td>20</td><td>20</td><td>20</td><td>20</td><td>100</td></tr>
  <tr><td>٤</td><td>إبراهيم صابر المقاطي</td><td>16</td><td>17</td><td>17</td><td>16</td><td>82</td></tr>
  <tr><td>٥</td><td>سعد ناصر الدوسري</td><td>19</td><td>18</td><td>18</td><td>19</td><td>91</td></tr>
  <tr><td>٦</td><td>خالد فهد العتيبي</td><td>15</td><td>16</td><td>16</td><td>15</td><td>79</td></tr>
  <tr><td>٧</td><td>عبدالرحمن ماجد الزهراني</td><td>18</td><td>18</td><td>17</td><td>19</td><td>90</td></tr>
  <tr><td>٨</td><td>فيصل تركي الشهري</td><td>17</td><td>17</td><td>18</td><td>18</td><td>86</td></tr>
  <tr><td>٩</td><td>نايف بندر الحربي</td><td>19</td><td>20</td><td>19</td><td>19</td><td>96</td></tr>
  <tr><td>١٠</td><td>ماجد سلطان العنزي</td><td>14</td><td>15</td><td>16</td><td>15</td><td>75</td></tr>
 </tbody>
</table></div></body></html>`

const openPanel = process.env.OPEN !== '0'
const browser = await launch()
const page = await browser.newPage({ viewport: { width: 1180, height: 700 }, deviceScaleFactor: 2 })

/* البدائل: `chrome` غير موجودةٍ خارج الإضافة، والخادمُ لا يُنادى.
   وما عداهما شيفرةٌ حقيقيّة تعمل كما تعمل عند المعلّم. */
const STUB = ({ open }) => {
  const store = { midad_panel_open: open, midad_key: 'MDD-AB12CD34-EF56GH78-IJ90KL12' }
  window.chrome = {
    storage: { local: {
      get: async (k) => {
        const keys = Array.isArray(k) ? k : [k]
        const o = {}; for (const x of keys) if (x in store) o[x] = store[x]
        return o
      },
      set: async (o) => { Object.assign(store, o) },
      remove: async () => {},
    } },
    runtime: { getURL: (p) => p },
  }
  window.__PANEL__ = {
    ok: true, who: 'أحمد سالم', school: 'ابتدائية الأمير سلطان',
    state: 'active', days_left: 87, key_days: 87,
    whats_new: 'إشعار الأولياء',
    tools: [
      { key: 'tables', name: 'جداولي', icon: 'files', kind: 'open', open: '/#/app/noor', count: 12 },
      { key: 'down', name: 'تنزيل', icon: 'down', kind: 'open', open: '/#/app/noor', hint: '٣ صيغ' },
      { key: 'tpl', name: 'القوالب', icon: 'doc', kind: 'open', open: '/#/app/library', count: 16 },
      { key: 'marks', name: 'الرصد', icon: 'chart', kind: 'open', open: '/#/app/classroom', hint: '٣ / ٥' },
      { key: 'pf', name: 'الإنجاز', icon: 'files', kind: 'open', open: '/#/app/portfolio', count: 24 },
      { key: 'par', name: 'الأولياء', icon: 'chat', kind: 'open', open: '/#/app/parents', badge: 'جديد' },
    ],
  }
}

page.on('console', (m) => console.log('صفحة:', m.type(), m.text()))
page.on('pageerror', (e) => console.log('خطأ:', e.message))
await page.setContent(NOOR, { waitUntil: 'load' })
/* البدائلُ تُوضع بعد المتن مباشرةً: `addInitScript` تسري مع التنقّل،
   و`setContent` ليس تنقّلًا — فلا تصل. */
await page.evaluate(STUB, { open: openPanel })
await page.addScriptTag({ content: core })
/* ويُبدَّل نداءُ الشبكة وحده — والقراءةُ والرسمُ يعملان بشيفرتهما. */
await page.evaluate(() => { self.Midad.panelInfo = async () => window.__PANEL__ })
await page.addScriptTag({ content: panel })

await page.waitForFunction(() => {
  const h = document.getElementById('midad-host')
  return h && h.shadowRoot && h.shadowRoot.querySelector(openPanelFlag() ? '.card' : '.tab')
  function openPanelFlag() { return window.__OPEN__ }
}, null, { timeout: 6000 }).catch(() => {})
await page.waitForTimeout(700)

await page.screenshot({ path: OUT })
const dbg = await page.evaluate(() => {
  const h = document.getElementById('midad-host')
  return { host: !!h, shadow: !!h?.shadowRoot,
    html: (h?.shadowRoot?.innerHTML || '').slice(0, 220) }
})
console.log('تشخيص:', JSON.stringify(dbg))
const box = await page.evaluate(() => {
  const h = document.getElementById('midad-host')
  const r = h.shadowRoot
  const g = (sel) => { const e = r.querySelector(sel); if (!e) return null
    const b = e.getBoundingClientRect(); return { x: Math.round(b.x), y: Math.round(b.y),
      w: Math.round(b.width), h: Math.round(b.height), bottom: Math.round(b.bottom) } }
  return { vh: innerHeight, host: h.getBoundingClientRect().top,
    wrap: g('.wrap'), card: g('.card'), tab: g('.tab'), hero: g('.hero') }
})
console.log('المواضع:', JSON.stringify(box))
const shot = await page.evaluate(() => {
  const r = document.getElementById('midad-host')?.shadowRoot
  return {
    card: !!r?.querySelector('.card'),
    days: r?.querySelector('.days')?.textContent || '',
    hero: r?.querySelector('.hero-t')?.textContent || '',
    meta: r?.querySelector('.hero-m')?.textContent || '',
    tools: [...(r?.querySelectorAll('.tl-n') || [])].map((n) => n.textContent),
    newLine: r?.querySelector('.new')?.textContent || '',
  }
})
console.log(JSON.stringify(shot, null, 1))
await browser.close()
