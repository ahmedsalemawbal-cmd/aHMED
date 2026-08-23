// خدمة خلفية خفيفة — لا تراقب التصفّح ولا تقرأ شيئًا من تلقاء نفسها.
chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.get(['midadKey'], () => {})
})
