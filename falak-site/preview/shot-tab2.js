const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1200,height:520}, deviceScaleFactor:2 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'enroll.html'), {waitUntil:'networkidle', timeout:60000});
  await p.waitForTimeout(1800);
  await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));var r=document.querySelector('input[name=section][value="بنين"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}var f=document.querySelector('.fk-seg');if(f)f.scrollIntoView({block:'center'});});
  await p.waitForTimeout(500);
  await p.screenshot({path:path.join(D,'v3-toggle.jpg'), fullPage:false, type:'jpeg', quality:82});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
