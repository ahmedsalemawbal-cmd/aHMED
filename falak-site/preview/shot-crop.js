const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1360,height:900}, deviceScaleFactor:2 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2200);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  // hero crop
  await p.screenshot({path:path.join(D,'e-hero.jpg'), clip:{x:0,y:0,width:1360,height:520}, type:'jpeg', quality:82});
  // CTA band crop
  await p.evaluate(()=>{var el=document.querySelector('.fk-cta');if(el)el.scrollIntoView({block:'center'});});
  await p.waitForTimeout(400);
  await p.screenshot({path:path.join(D,'e-cta.jpg'), clip:{x:0,y:120,width:1360,height:520}, type:'jpeg', quality:82});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
