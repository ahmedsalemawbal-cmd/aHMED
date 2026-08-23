import { SUPABASE_URL, SUPABASE_KEY, APP_URL } from './config.js'

const $ = (id) => document.getElementById(id)
const show = (el, on) => el.classList.toggle('hide', !on)

let currentKey = null
let scraped = null

function msg(text, kind = 'err') {
  $('msg').innerHTML = text ? `<div class="msg ${kind}">${text}</div>` : ''
}

async function api(body, key) {
  const res = await fetch(`${SUPABASE_URL}/functions/v1/noor`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      apikey: SUPABASE_KEY,
      'x-midad-key': key,
    },
    body: JSON.stringify(body),
  })
  const json = await res.json().catch(() => ({ error: 'تعذّر قراءة الرد' }))
  if (!res.ok) throw new Error(json?.error || 'تعذّر الاتّصال بمِداد')
  return json
}

async function scrapeActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true })
  if (!tab?.id) return null
  try {
    const [res] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['scrape.js'],
    })
    const [out] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: () => midadScrapeTables(),
    })
    return out?.result || null
  } catch {
    return null
  }
}

function renderTables(data) {
  const wrap = $('found')
  wrap.innerHTML = ''
  const tables = data?.tables || []
  show($('none'), tables.length === 0)
  if (!tables.length) return

  const head = document.createElement('p')
  head.className = 't'
  head.style.margin = '4px 0 8px'
  head.textContent = `وجدنا ${tables.length} ${tables.length === 1 ? 'جدولًا' : 'جداول'} في هذه الصفحة`
  wrap.appendChild(head)

  tables.forEach((t, i) => {
    const div = document.createElement('div')
    div.className = 'tbl'
    div.innerHTML = `
      <div class="t" style="margin-block-end:4px">${escapeHtml(t.title)}</div>
      <p class="muted" style="margin:0 0 9px">${t.row_count} صفًّا · ${t.columns.length} أعمدة</p>
      <button class="small" data-i="${i}" style="width:100%">أرسل إلى مِداد</button>`
    wrap.appendChild(div)
  })

  wrap.querySelectorAll('button[data-i]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const i = Number(btn.dataset.i)
      const t = tables[i]
      btn.disabled = true
      btn.innerHTML = '<span class="spin"></span> جارٍ الإرسال…'
      msg('')
      try {
        await api({
          action: 'ingest',
          title: t.title,
          columns: t.columns,
          rows: t.rows,
          source_url: data.url,
        }, currentKey)
        btn.textContent = 'تمّ ✓ — افتحه في مِداد'
        btn.classList.add('ghost')
        btn.disabled = false
        btn.onclick = () => chrome.tabs.create({ url: `${APP_URL}/#/app/noor` })
        msg('وصل الجدول إلى حسابك في مِداد.', 'ok')
      } catch (e) {
        msg(e.message || 'تعذّر الإرسال', 'err')
        btn.disabled = false
        btn.textContent = 'أرسل إلى مِداد'
      }
    })
  })
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

async function boot() {
  $('openApp').href = APP_URL
  $('openApp').addEventListener('click', (e) => {
    e.preventDefault()
    chrome.tabs.create({ url: APP_URL })
  })

  const stored = await chrome.storage.local.get(['midadKey'])
  currentKey = stored.midadKey || null

  if (!currentKey) {
    show($('link'), true)
    $('dot').className = 'dot off'
    return
  }

  try {
    const info = await api({ action: 'verify_key' }, currentKey)
    $('dot').className = 'dot on'
    $('subName').textContent = info.subscriber || 'حسابك'
    $('userName').textContent = info.user ? `مربوطة بحساب ${info.user}` : ''
    if (info.state === 'expired') msg('انتهى اشتراكك — جدّده لتنزيل الجداول.', 'warn')
    show($('linked'), true)
    scraped = await scrapeActiveTab()
    renderTables(scraped)
  } catch (e) {
    $('dot').className = 'dot off'
    show($('link'), true)
    msg(e.message || 'المفتاح غير صالح — الصق مفتاحًا جديدًا.', 'err')
    await chrome.storage.local.remove('midadKey')
    currentKey = null
  }
}

$('btnLink').addEventListener('click', async () => {
  const key = $('key').value.trim()
  if (!key) { msg('الصق مفتاح الربط أوّلًا'); return }
  const btn = $('btnLink')
  btn.disabled = true
  btn.innerHTML = '<span class="spin"></span> جارٍ الربط…'
  msg('')
  try {
    await api({ action: 'verify_key' }, key)
    await chrome.storage.local.set({ midadKey: key })
    currentKey = key
    show($('link'), false)
    msg('')
    await boot()
  } catch (e) {
    msg(e.message || 'مفتاح الربط غير صحيح', 'err')
  } finally {
    btn.disabled = false
    btn.textContent = 'اربط الإضافة'
  }
})

$('btnUnlink').addEventListener('click', async () => {
  await chrome.storage.local.remove('midadKey')
  currentKey = null
  show($('linked'), false)
  show($('link'), true)
  $('dot').className = 'dot off'
  msg('فُكّ الربط من هذا المتصفّح. المفتاح نفسه ما زال صالحًا في مِداد.', 'ok')
})

boot()
