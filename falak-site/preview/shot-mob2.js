const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:390,height:1500}, deviceScaleFactor:2, isMobile:true });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'enroll.html'), {waitUntil:'networkidle', timeout:60000});
  await p.waitForTimeout(1600);
  await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));var r=document.querySelector('input[name=section][value="بنين"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}var f=document.querySelector('.fk-enroll-grid');if(f)f.scrollIntoView({block:'start'});});
  await p.waitForTimeout(500);
  await p.screenshot({path:path.join(D,'v5-enroll-mobile.jpg'), fullPage:false, type:'jpeg', quality:78});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
