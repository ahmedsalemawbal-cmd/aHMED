const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  let c = await b.newContext({ viewport:{width:1300,height:1050}, deviceScaleFactor:1.4 });
  let p = await c.newPage();
  await p.goto('file://'+path.join(D,'careers.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(1800);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  await p.waitForTimeout(400);
  await p.screenshot({path:path.join(D,'v9-careers.jpg'), fullPage:false, type:'jpeg', quality:76});
  await c.close();
  c = await b.newContext({ viewport:{width:1300,height:760}, deviceScaleFactor:1.4 });
  p = await c.newPage();
  await p.goto('file://'+path.join(D,'dash-careers.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(1500);
  await p.screenshot({path:path.join(D,'v9-dash-careers.jpg'), fullPage:false, type:'jpeg', quality:78});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
