/**
 * لوحة القوالب: صلاحيّةٌ على الملفّ، وقائمتان، وترتيبٌ يُحفظ.
 *
 * أربعة أمورٍ تُفحص هنا لأنّ عطبها **صامت** — يُحفظ ويُنشر ولا يشتكي أحد
 * إلّا بعد أن يبحث المشترك عن قالبٍ وُعد به:
 *
 *   ① الرفع يسأل «لمن» سؤالًا واحدًا بثلاثة خيارات، ولا يسأل عن المجلّد:
 *      المجلّد يُشتقّ من الصلاحيّة، وسؤالٌ جوابُه معلومٌ ليس سؤالًا.
 *   ② اللوحة قائمتان: قوالب المدرسة وقوالب المعلّم.
 *   ③ وقالبُ «الكلّ» يظهر في القائمتين — وهو ملفٌّ واحدٌ لا نسختان.
 *   ④ والترتيب يُحفظ في عمود جمهوره: `sort_school` أو `sort_teacher`،
 *      فسحبُ قالب «الكلّ» في إحداهما لا يحرّكه في الأخرى.
 *
 * والرابع يُفحص بمراقبة ما يُرسَل إلى الخادم لا بما يُرسَم: الصفّ قد
 * يقفز في الشاشة ولا يُحفظ شيء، فيعود إلى مكانه عند أوّل تحديث.
 */

import path from 'node:path'
import { launch, ORIGIN, tally, TESTS } from './lib/harness.mjs'

const FOLDERS = [
  { id: 'f1', slug: 'school-files', name: 'ملفّات المدرسة', blurb: null, accent: '#4285F4', icon: 'folder', sort: 10, is_active: true, audience: 'school', owner_only: false, coming_soon: false, is_general: true, created_at: '2026-08-01', updated_at: '2026-08-01' },
  { id: 'f2', slug: 'certificates', name: 'قوالب الشهادات', blurb: null, accent: '#a855f7', icon: 'folder', sort: 20, is_active: true, audience: 'school', owner_only: true, coming_soon: true, is_general: false, created_at: '2026-08-01', updated_at: '2026-08-01' },
  { id: 'f3', slug: 'my-files', name: 'ملفّاتي', blurb: null, accent: '#0F9D58', icon: 'folder', sort: 30, is_active: true, audience: 'teacher', owner_only: false, coming_soon: false, is_general: true, created_at: '2026-08-01', updated_at: '2026-08-01' },
]

const T_ = (o) => ({
  category_key: 'general', description: null, body: '', fields: [],
  outputs: ['pdf', 'docx'], estimated_minutes: 5, version: 1, status: 'published',
  usage_count: 0, is_new: false, sort: 0, kind: 'doc',
  content_html: '<p>متن</p>', page: { size: 'A4', orientation: 'portrait', margins: { top: 14, right: 14, bottom: 14, left: 14 } },
  source_pdf_path: null, source_pages: null,
  created_at: '2026-08-01', updated_at: '2026-08-20', ...o,
})

/** ثلاثةٌ: واحدٌ لكلّ جمهور، وثالثٌ للكلّ يظهر في القائمتين. */
const TEMPLATES = [
  T_({ id: 't1', slug: 'school-one', title: 'تقرير المدرسة', audience: 'school', folder_id: 'f1', sort_school: 0, sort_teacher: 0 }),
  T_({ id: 't2', slug: 'both-one', title: 'قالبُ الكلّ', audience: 'all', folder_id: null, sort_school: 1, sort_teacher: 0 }),
  T_({ id: 't3', slug: 'teacher-one', title: 'سجلّ المعلّم', audience: 'teacher', folder_id: 'f3', sort_school: 0, sort_teacher: 1 }),
]

const T = tally('لوحة القوالب')
const browser = await launch()
const errors = []
/** كلّ ما كُتب في الخادم — الترتيب يُفحص هنا لا في الشاشة. */
const writes = []

try {
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1000 } })
  await ctx.addInitScript(() => localStorage.setItem('midad.auth', JSON.stringify({
    access_token: 'x', token_type: 'bearer', expires_in: 7200,
    expires_at: Math.floor(Date.now() / 1000) + 7200, refresh_token: 'y',
    user: { id: 'u1', aud: 'authenticated', role: 'authenticated', email: 'a@b.c', app_metadata: {}, user_metadata: {}, created_at: '2026-08-01T00:00:00Z' },
  })))
  await ctx.route('**/auth/v1/**', (r) =>
    r.fulfill({ status: 200, contentType: 'application/json', body: '{}' }))

  await ctx.route('**/rest/v1/**', async (route) => {
    const req = route.request()
    const url = new URL(req.url())
    const t = url.pathname.split('/rest/v1/')[1] || ''
    const J = (d) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(d) })

    if (req.method() === 'PATCH') {
      let body = {}
      try { body = JSON.parse(req.postData() || '{}') } catch { /* لا شيء */ }
      writes.push({ table: t, where: url.searchParams.get('id') || '', body })
      return J([])
    }
    if (t === 'templates') return J(TEMPLATES)
    if (t === 'template_folders') return J(FOLDERS)
    if (t === 'template_folder_counts') return J([])
    if (t === 'platform_admins') return J([{ user_id: 'u1' }])
    if (t === 'profiles') return J([{ id: 'u1', subscriber_id: 's1', full_name: 'أحمد', phone: '05', email: null, role_key: 'owner', is_owner: true, status: 'active' }])
    if (t === 'subscribers') return J([{ id: 's1', name: 'مِداد', account_type: 'school', status: 'active', plan_id: 'p1', trial_ends_at: null }])
    if (t === 'plans') return J([{ id: 'p1', key: 'school', name_ar: 'ب', account_type: 'school', price_sar: 749, seats: 10, template_categories: [], ai_quota_monthly: 1000, features_ar: [] }])
    return J([])
  })

  const p = await ctx.newPage()
  p.on('pageerror', (e) => errors.push(e.message))
  await p.goto(`${ORIGIN}/#/admin/templates`, { waitUntil: 'load' })
  await p.waitForSelector('.mdd-tsec', { timeout: 25000 })
  await p.waitForTimeout(1200)

  /* ═════ ② و③ القائمتان، وقالبُ «الكلّ» فيهما ═════ */
  const read = () => p.evaluate(() =>
    [...document.querySelectorAll('.mdd-tsec')].map((h) => {
      const card = h.parentElement
      return {
        name: h.querySelector('b')?.textContent.trim() || '',
        rows: [...card.querySelectorAll('tbody tr')].map((r) => ({
          title: r.querySelector('.mdd-linkish')?.textContent.trim() || '',
          audience: r.querySelector('select')?.value || '',
        })),
      }
    }))

  const secs = await read()
  T('قائمتان لا واحدة', secs.length === 2, secs.map((s) => s.name).join(' · ') || 'لا شيء')
  T('  الأولى قوالب المدرسة', secs[0]?.name === 'قوالب المدرسة', secs[0]?.name)
  T('  والثانية قوالب المعلّم', secs[1]?.name === 'قوالب المعلّم', secs[1]?.name)

  const school = (secs[0]?.rows || []).map((r) => r.title)
  const teacher = (secs[1]?.rows || []).map((r) => r.title)
  T('قوالب المدرسة: تقريرها ثمّ قالبُ الكلّ',
    JSON.stringify(school) === JSON.stringify(['تقرير المدرسة', 'قالبُ الكلّ']), school.join(' · '))
  T('قوالب المعلّم: قالبُ الكلّ ثمّ سجلّه',
    JSON.stringify(teacher) === JSON.stringify(['قالبُ الكلّ', 'سجلّ المعلّم']), teacher.join(' · '))
  T('  و«الكلّ» في القائمتين — ملفٌّ واحدٌ لا نسختان',
    school.includes('قالبُ الكلّ') && teacher.includes('قالبُ الكلّ'))

  /* والترتيبان مختلفان: هو الثاني عند المدرسة والأوّل عند المعلّم.
     ولو كان الترتيب عمودًا واحدًا لاستحال هذا. */
  T('  وترتيبه في القائمتين مختلف',
    school.indexOf('قالبُ الكلّ') === 1 && teacher.indexOf('قالبُ الكلّ') === 0,
    `المدرسة ${school.indexOf('قالبُ الكلّ')} · المعلّم ${teacher.indexOf('قالبُ الكلّ')}`)

  /* ═════ ④ الترتيب يُحفظ في عمود جمهوره ═════ */
  writes.length = 0
  await p.evaluate(() => {
    const card = document.querySelectorAll('.mdd-tsec')[0].parentElement
    const rows = card.querySelectorAll('tbody tr')
    // «قالبُ الكلّ» هو الثاني — نرفعه بالسهم
    rows[1].querySelector('.mdd-ord__btn').click()
  })
  await p.waitForTimeout(900)

  const cols = writes.filter((w) => w.table === 'templates').flatMap((w) => Object.keys(w.body))
  T('السهم يحفظ الترتيب', writes.length > 0, `${writes.length} كتابة`)
  T('  في عمود المدرسة وحده',
    cols.length > 0 && cols.every((c) => c === 'sort_school'),
    cols.join(', ') || 'لا شيء')

  const after = (await read())[0].rows.map((r) => r.title)
  T('  والصفّ ارتفع في الشاشة',
    JSON.stringify(after) === JSON.stringify(['قالبُ الكلّ', 'تقرير المدرسة']), after.join(' · '))

  /* وسحبُه عند المدرسة لا يحرّكه عند المعلّم */
  const teacherAfter = (await read())[1].rows.map((r) => r.title)
  T('  ولم يتحرّك عند المعلّم',
    JSON.stringify(teacherAfter) === JSON.stringify(teacher), teacherAfter.join(' · '))

  /* ═════ تغيير الصلاحيّة ينقل القالب بين القائمتين ═════ */
  writes.length = 0
  await p.evaluate(() => {
    const card = document.querySelectorAll('.mdd-tsec')[1].parentElement   // المعلّم
    const row = [...card.querySelectorAll('tbody tr')]
      .find((r) => r.querySelector('.mdd-linkish')?.textContent.includes('سجلّ المعلّم'))
    const sel = row.querySelector('select')
    sel.value = 'school'
    sel.dispatchEvent(new Event('change', { bubbles: true }))
  })
  await p.waitForTimeout(900)

  const w = writes.find((x) => x.table === 'templates')
  T('تغيير الصلاحيّة يُحفظ', !!w, w ? JSON.stringify(w.body) : 'لا كتابة')
  T('  والمجلّد يتبعها', w?.body?.folder_id === 'f1', String(w?.body?.folder_id))

  const moved = await read()
  T('  والقالب انتقل إلى قائمة المدرسة',
    moved[0].rows.some((r) => r.title === 'سجلّ المعلّم') &&
    !moved[1].rows.some((r) => r.title === 'سجلّ المعلّم'),
    `المدرسة: ${moved[0].rows.map((r) => r.title).join('·')} | المعلّم: ${moved[1].rows.map((r) => r.title).join('·')}`)

  /* ═════ ① الرفع: سؤالٌ واحد بثلاثة خيارات ═════ */
  const open = await p.$('button:has-text("استورد")')
  T('زرّ الاستيراد في اللوحة', !!open)
  if (open) {
    await open.click()
    await p.waitForTimeout(600)
    /* السؤال لا يُرسم قبل قراءة ملفّ: لا معنى لسؤالٍ عن قالبٍ لم يوجد بعد. */
    const input = await p.$('input[type=file]')
    await input.setInputFiles(path.join(TESTS, 'fixtures/nafs.design.html'))
    await p.waitForSelector('.mdd-modal select', { timeout: 40000 })
    await p.waitForTimeout(1400)
    const dlg = await p.evaluate(() => {
      const m = document.querySelector('.mdd-modal')
      if (!m) return null
      const labels = [...m.querySelectorAll('.mdd-field__label')].map((l) => l.textContent.trim())
      const sel = m.querySelector('select')
      return {
        labels,
        options: sel ? [...sel.options].map((o) => o.value) : [],
        asksFolder: labels.some((l) => l.includes('مجلّد')),
      }
    })
    T('يسأل «لمن هذا القالب؟»', !!dlg && dlg.labels.some((l) => l.includes('لمن')), (dlg?.labels || []).join(' · '))
    T('  ثلاثة خيارات: الكلّ والمدرسة والمعلّم',
      JSON.stringify(dlg?.options) === JSON.stringify(['all', 'school', 'teacher']),
      (dlg?.options || []).join(' · '))
    T('  ولا يسأل عن المجلّد — يُشتقّ من الصلاحيّة', dlg && !dlg.asksFolder)
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
  await ctx.close()
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
