const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1400,height:820}, deviceScaleFactor:1.5 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'dash-login.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(1800);
  // قِس أعلى مساحة الـ body padding
  const pt = await p.evaluate(()=>getComputedStyle(document.body).paddingTop);
  console.log('body padding-top =', pt);
  await p.screenshot({path:path.join(D,'v6-dash-login.jpg'), fullPage:true, type:'jpeg', quality:78});
  await b.close();
})().catch(e=>{console.error(e);process.exit(1);});
