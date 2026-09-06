/**
 * عامل الخدمة.
 *
 * لا يفعل شيئًا في الخلفيّة: لا يرصد تنقّلًا، ولا يقرأ صفحةً، ولا يتّصل
 * بالشبكة من تلقائه. كلّ العمل يجري في النافذة حين يفتحها المعلّم.
 *
 * ووجوده هنا لأمرٍ واحد: تلوين الأيقونة في الصفحات التي تعمل فيها
 * الإضافة، فيعرف المعلّم أين تنفع قبل أن يضغطها. وهذا يقتضي معرفة
 * عنوان التبويب وحده — وهو ما تُتيحه أذونات المضيف الصريحة، لا رصدًا
 * عامًّا لكلّ ما يفتح.
 */

const OK = /(^|\.)moe\.gov\.sa$|(^|\.)madrasati\.sa$/i

function hostOf(url) {
  try { return new URL(url).hostname } catch { return '' }
}

async function paint(tabId, url) {
  const on = OK.test(hostOf(url))
  try {
    await chrome.action.setBadgeText({ tabId, text: on ? '●' : '' })
    if (on) {
      await chrome.action.setBadgeBackgroundColor({ tabId, color: '#1f7a4d' })
      await chrome.action.setTitle({ tabId, title: 'مِداد — اسحب الجدول من هذه الصفحة' })
    } else {
      await chrome.action.setTitle({ tabId, title: 'مِداد — افتح نور أو مدرستي أوّلًا' })
    }
  } catch { /* التبويب أُغلق قبل أن نصل إليه */ }
}

chrome.tabs.onUpdated.addListener((tabId, info, tab) => {
  if (info.status === 'complete' || info.url) paint(tabId, tab.url || info.url || '')
})

chrome.tabs.onActivated.addListener(async ({ tabId }) => {
  try {
    const tab = await chrome.tabs.get(tabId)
    paint(tabId, tab.url || '')
  } catch { /* لا شيء */ }
})
