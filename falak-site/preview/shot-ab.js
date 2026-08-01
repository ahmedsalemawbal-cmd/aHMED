const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1300,height:800}, deviceScaleFactor:1.5 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2000);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  await p.waitForTimeout(700);
  // scroll to about and capture viewport
  await p.evaluate(()=>{var a=document.querySelector('.fk-about');if(a)a.scrollIntoView({block:'center'});});
  await p.waitForTimeout(500);
  await p.screenshot({path:path.join(D,'v8-about2.jpg'), fullPage:false, type:'jpeg', quality:78});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
