const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1440,height:280}, deviceScaleFactor:2 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'networkidle', timeout:60000});
  await p.waitForTimeout(2500);
  await p.screenshot({path:path.join(D,'v2-logo.jpg'), fullPage:false, type:'jpeg', quality:82});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
