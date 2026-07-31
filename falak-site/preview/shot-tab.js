const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  async function shot(file,out,vp,pre){
    const c = await b.newContext({ viewport: vp, deviceScaleFactor: 1.4 });
    const p = await c.newPage();
    await p.goto('file://'+path.join(D,file), {waitUntil:'networkidle', timeout:60000});
    await p.waitForTimeout(1800);
    await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
    if(pre) await p.evaluate(pre);
    await p.waitForTimeout(500);
    await p.screenshot({path:path.join(D,out), fullPage:false, type:'jpeg', quality:74});
    await c.close(); console.log('shot',out);
  }
  await shot('enroll.html','v3-enroll.jpg',{width:1440,height:760},()=>{var r=document.querySelector('input[name=section][value="بنين"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}window.scrollTo(0,560);});
  await b.close();
})().catch(e=>{console.error(e);process.exit(1);});
