import{ak as be,u as oe,j as e,ag as ye,ah as xe,a2 as le,A as V,l as G,G as J,H as X,ai as we,B as W,L as O,J as U,a3 as ve,a as je,E as ke,X as Se,al as Ae,f as Q,C as ce,x as _e,ao as Ce,t as Ee,a5 as Ne,ar as $e,e as Z,aq as Pe,b as ze,ae as De,af as qe}from"./app.Bm7MOtdC.js";import{r as C,b as Te}from"./router.BlsZkRLJ.js";import{s as de}from"./styleSafe.B3uT_xC2.js";import"./supabase.DGyy41YE.js";const Me=/[ﭐ-﷿ﹰ-﻿]/,Ie=/[؀-ۿݐ-ݿ]/,Le=new Set("0123456789٠١٢٣٤٥٦٧٨٩"),Y=new Set(["المدرسة","الطالب","الطالبة","المعلم","المعلّم","الصف","الصفّ","المادة","المادّة","التاريخ","اليوم","الاسم","ملاحظات","التوقيع","إدارة","تعليم","الفصل","الدراسي","الدراسيّ","العام","رقم","وزارة","مدير","وكيل","المشرف","الحضور","الغياب","الدرجة","المجموع","النتيجة","خطة","خطّة","تقرير","محضر","اجتماع","نموذج","استمارة","سجل","سجلّ","متابعة","الطلبة","الطلاب","المستوى","نسبة","عدد"]);function Oe(n){const t=n.replace(/ـ+/g,"");return Me.test(t)?t.normalize("NFKC"):t}const ue=n=>n.match(/[؀-ۿ]+/g)||[],Re=n=>n.replace(/[ً-ٰٟ]/g,"");function ee(n){let t=0;for(const s of ue(n))(Y.has(s)||Y.has(Re(s)))&&t++;return t}function te(n){const t=ue(n).filter(s=>s.length>3);return t.length?t.filter(s=>s.startsWith("ال")).length/t.length:0}function He(n){if(!Ie.test(n))return!1;const t=[...n].reverse().join(""),s=ee(n),r=ee(t);return s!==r?r>s:te(t)>te(n)+.15}function Fe(n){let t=Oe(n||"");return He(t)&&(t=[...t].reverse().join("")),t.replace(/[‎‏‪-‮]/g,"").replace(/[ \t ]+/g," ").trim()}const We=/[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿ -~ ·«»×÷ -⁯←-⇿─-╿■-◿✓✔✗]/;function Be(n){const t=[...n].filter(a=>!/\s/.test(a));if(!t.length)return{chars:0,bad:0,ratio:0,healthy:!0,sample:""};const s=t.filter(a=>!We.test(a)),r=new Set;for(const a of s)r.size<10&&r.add(a);const i=s.length/t.length;return{chars:t.length,bad:s.length,ratio:i,healthy:i<.03||s.length<=2,sample:[...r].join("")}}const ne=n=>n.replace(/[&<>]/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;"})[t]);function Ve(n){let t="",s=[];const r=()=>{s.length&&(t+=s.reverse().join(""),s=[])};for(let i=0;i<n.length;i++){const a=n[i],m=!a.atomic&&a.ch.length===1&&Le.has(a.ch);let d="";if(i>0&&n[i-1].x-a.x1>Math.max(.6,a.size*.11)&&(d=" "),m){d&&r(),d&&(t+=d),s.push(a.ch);continue}r(),t+=d+a.ch}return r(),t}async function Ue(n){const t=await be(()=>import("./pdf.C9f1iiyp.js"),[],import.meta.url);t.GlobalWorkerOptions.workerSrc=new URL(""+new URL("pdf.worker.min.yatZIOMy.mjs",import.meta.url).href,import.meta.url).toString();const s=await n.arrayBuffer(),r=await t.getDocument({data:s,isEvalSupported:!1}).promise,i=[],a=[];let m="",d=!1,g="",l=0;for(let v=1;v<=r.numPages;v++){const S=await r.getPage(v),x=S.getViewport({scale:1});v===1&&(d=x.width>x.height);const E=await S.getTextContent(),h=[];for(const y of E.items){const b=y.str??"";if(!b||!b.trim())continue;const N=y.transform,c=N[4],f=x.height-N[5],P=Math.abs(N[3])||Math.abs(N[0])||10,H=y.width||b.length*P*.5;h.push({ch:b,x:c,x1:c+H,y:f,size:P,atomic:b.length>1})}if(!h.length){i.push(`صفحة ${v}: بلا نصٍّ مُستخرَج — قد تكون صورةً ممسوحة`);continue}h.sort((y,b)=>y.y-b.y);const u=[];for(const y of h){const b=u[u.length-1];b&&Math.abs(b[0].y-y.y)<=Math.max(2.5,y.size*.55)?b.push(y):u.push([y])}const q=new Map;for(const y of h){const b=Math.round(y.size*2)/2;q.set(b,(q.get(b)||0)+1)}let A=10,L=0;q.forEach((y,b)=>{y>L&&(L=y,A=b)}),v>1&&a.push("<hr>");let $=[];const T=()=>{$.length&&(a.push(`<p>${ne($.join(" "))}</p>`),$=[])};for(const y of u){y.sort((P,H)=>H.x-P.x);const b=Fe(Ve(y)).replace(/\s+/g," ").trim();if(!b)continue;m+=b+" ";const N=Math.max(...y.map(P=>P.size)),c=b.length<=90,f=N>=A*1.5&&c?"h1":N>=A*1.24&&c?"h2":N>=A*1.12&&c&&b.length<=60?"h3":"p";if(f==="p"){$.push(b);continue}T(),a.push(`<${f}>${ne(b)}</${f}>`),v===1&&N>l&&b.length>5&&(g=b,l=N)}T()}const w=Be(m);return w.healthy||i.unshift(`طبقة النصّ في هذا الملفّ فاسدة (${Math.round(w.ratio*100)}٪ محارف غريبة${w.sample?": "+w.sample:""}) — الخطّ يخزّن رسوم الحروف برموزٍ غير عربيّة. لا استخراجَ يُصلحها؛ تحتاج قراءةً ضوئيّة.`),/[؀-ۿ]\d|\d[؀-ۿ]/.test(m)&&i.push("أرقامٌ ملاصقةٌ لحروفٍ عربيّة — راجع ترتيبها في المحرّر قبل النشر."),i.push("الجداول لا تُستخرج هنا: أعِد جدولة ما كان مؤطَّرًا بزرّ الجدول في المحرّر."),{title:g||n.name.replace(/\.pdf$/i,""),html:a.join(`
`)||"<p></p>",pages:r.numPages,landscape:d,health:w,warnings:i}}const Je=["color","background-color","background-image","background-size","background-position","background-repeat","background-clip","font-family","font-size","font-weight","font-style","font-variant","line-height","letter-spacing","word-spacing","text-align","text-decoration-line","text-transform","text-indent","white-space","direction","unicode-bidi","writing-mode","display","position","top","inset-inline-start","inset-inline-end","bottom","width","height","min-width","min-height","max-width","max-height","margin-top","margin-right","margin-bottom","margin-left","padding-top","padding-right","padding-bottom","padding-left","border-top-width","border-right-width","border-bottom-width","border-left-width","border-top-style","border-right-style","border-bottom-style","border-left-style","border-top-color","border-right-color","border-bottom-color","border-left-color","border-top-left-radius","border-top-right-radius","border-bottom-left-radius","border-bottom-right-radius","box-shadow","opacity","overflow-x","overflow-y","box-sizing","flex-direction","flex-wrap","justify-content","align-items","align-self","flex-grow","flex-shrink","flex-basis","gap","row-gap","column-gap","grid-template-columns","grid-template-rows","grid-column","grid-row","vertical-align","table-layout","border-collapse","border-spacing","list-style-type","list-style-position","transform","transform-origin","object-fit","aspect-ratio","z-index","float","clear"],Ke=new Set(["color","font-family","font-size","font-weight","font-style","font-variant","line-height","letter-spacing","word-spacing","text-align","text-transform","text-indent","white-space","direction","writing-mode","visibility","list-style-type","list-style-position","border-spacing","border-collapse"]);function Ge(){return`
(function () {
  var PROPS = ${JSON.stringify(Je)};
  var INHERITED = ${JSON.stringify([...Ke])};
  var INH = new Set(INHERITED);

  function send(html, note) {
    try { parent.postMessage({ __midad: 'design', html: html, note: note || '' }, '*') } catch (e) {}
  }

  /* ننتظر أن يسكن المستند: القالب يرسم نفسه على دفعات، والالتقاط في
     منتصف الرسم يعطي نصف مستند. فنقيس حتّى يثبت الطول ثلاث مرّات. */
  function settle() {
    return new Promise(function (done) {
      var last = -1, same = 0, n = 0
      var tick = function () {
        var now = document.body ? document.body.innerHTML.length : 0
        if (now === last && now > 200) { if (++same >= 3) return done() }
        else same = 0
        last = now
        if (++n > 90) return done()          /* تسعُ ثوانٍ سقفًا */
        setTimeout(tick, 100)
      }
      tick()
    })
  }

  /**
   * الصور مؤقّتةٌ بعمر الإطار (blob:) — تُثبَّت قبل أن يُغلق.
   *
   * وتُثبَّت **بمقاس عرضها لا بمقاس أصلها**: شعارُ الوزارة في هذا الملفّ
   * أربعون كيلوبايتًا يُعرض في ستّين بكسلًا، ويتكرّر في ثمانٍ وخمسين
   * صفحة — ثلاثةُ ميغابايتٍ ونصف من دقّةٍ لا تُرى. فيُعاد ترميزُه
   * بضعف مقاس عرضه: حدُّ ما تحتاجه شاشةٌ كثيفة، ولا زيادة.
   *
   * ويُحتفظ بالأصل إن كان أصغر ممّا سيخرج: إعادةُ الترميز ليست دائمًا
   * ربحًا، وصورةٌ صغيرةٌ قد تكبر بها.
   */
  function freezeImages() {
    var cache = {}
    var imgs = Array.prototype.slice.call(document.images)
    return Promise.all(imgs.map(function (img) {
      var s = img.getAttribute('src') || ''
      if (s.indexOf('blob:') !== 0 && s.indexOf('http') !== 0) return null
      var w = Math.max(1, Math.round(img.getBoundingClientRect().width || img.width || 0))
      var key = img.src + '|' + w
      if (cache[key]) { img.setAttribute('src', cache[key]); return null }

      return fetch(img.src).then(function (r) { return r.blob() }).then(function (b) {
        return new Promise(function (res) {
          var fr = new FileReader()
          fr.onerror = function () { res(null) }
          fr.onload = function () {
            var raw = String(fr.result)
            var probe = new Image()
            probe.onerror = function () { img.setAttribute('src', raw); cache[key] = raw; res(null) }
            probe.onload = function () {
              var out = raw
              try {
                var target = Math.min(probe.naturalWidth, Math.max(w * 2, 48))
                if (target > 0 && target < probe.naturalWidth) {
                  var c = document.createElement('canvas')
                  c.width = target
                  c.height = Math.max(1, Math.round(probe.naturalHeight * target / probe.naturalWidth))
                  c.getContext('2d').drawImage(probe, 0, 0, c.width, c.height)
                  var small = c.toDataURL('image/png')
                  if (small.length < raw.length) out = small
                }
              } catch (e) { /* الأصل يكفي */ }
              img.setAttribute('src', out)
              cache[key] = out
              res(null)
            }
            probe.src = raw
          }
          fr.readAsDataURL(b)
        })
      }).catch(function () { return null })
    }))
  }

  /**
   * يُثبّت ما تُضيفه أوراقُ الأنماط، ولا يمسّ ما سواه.
   *
   * وكانت أوّل صياغةٍ تكتب الشكل المحسوب **كلَّه** على كلّ عنصر. فسقط
   * الوفاء: من ١٨٩ مطابقًا من ١٩٥ إلى ١٤١، ومن ٨٢ من ٨٤ إلى اثنين.
   * والسبب أنّ الشكل المحسوب يردّ القياس المستعمَل لا المُعلَن — فيُجمَّد
   * تخطيطٌ كان مرنًا، وتُكتب على كلّ عنصرٍ قيمٌ لم يقصدها التصميم.
   *
   *     ما لم تُضفه ورقةُ الأنماط لا يحتاج إلى نقل.
   *
   * فيُقاس الفرق: يُقرأ الشكل مرّةً وأوراق التصميم **معطَّلة** — فيبقى
   * ما كتبه التصميم سطريًّا وحده — ثمّ مرّةً وهي عاملة. وما اختلف بينهما
   * هو ما جاءت به الأصناف، وهو وحده ما يُكتب.
   *
   * فالعنصر الذي شكلُه سطريٌّ كلُّه لا يُمسّ، والذي شكلُه من صنفٍ يُنقل
   * شكلُه معه. وملفٌّ بلا أوراق أنماطٍ يخرج كما كان يخرج قبل هذا كلّه.
   */
  function flatten() {
    var sheets = Array.prototype.slice.call(document.styleSheets)
    if (!sheets.length) return
    var nodes = Array.prototype.slice.call(document.body.querySelectorAll('*'))
      .filter(function (el) {
        var t = el.tagName.toLowerCase()
        return t !== 'script' && t !== 'style' && t !== 'link'
      })

    /* ① الشكل بلا أوراق التصميم — ما كتبه التصميم سطريًّا وحده */
    sheets.forEach(function (s) { try { s.disabled = true } catch (e) {} })
    var baseRows = nodes.map(function (el) {
      var cs = getComputedStyle(el), row = new Array(PROPS.length)
      for (var i = 0; i < PROPS.length; i++) row[i] = cs.getPropertyValue(PROPS[i])
      return row
    })
    sheets.forEach(function (s) { try { s.disabled = false } catch (e) {} })

    /* ② وما زادته الأصناف */
    for (var k = 0; k < nodes.length; k++) {
      var el = nodes[k]
      var tag = el.tagName.toLowerCase()
      var cs2 = getComputedStyle(el)
      var was = baseRows[k]

      /* ما لا يُرى لا يُكتب: لونُ حدٍّ عرضُه صفرٌ لونُ لا شيء. */
      var skip = {}
      var sides = ['top', 'right', 'bottom', 'left']
      for (var q = 0; q < 4; q++) {
        var w = cs2.getPropertyValue('border-' + sides[q] + '-width')
        var st = cs2.getPropertyValue('border-' + sides[q] + '-style')
        if (parseFloat(w) === 0 || !st || st === 'none') {
          skip['border-' + sides[q] + '-color'] = 1
          skip['border-' + sides[q] + '-style'] = 1
          skip['border-' + sides[q] + '-width'] = 1
        }
      }
      if (cs2.getPropertyValue('transform') === 'none') skip['transform-origin'] = 1

      /* والقياس يُترك للمتصفّح إلّا حيث يقرّره التصميم: الجدول وأعمدته
         وخلاياه والصورة. وقراءةُ الشكل تردّ القياس المستعمَل لا المُعلَن،
         فعرضٌ يتغيّر في أبٍ واحدٍ يُكتب على كلّ ذرّيّته — ويُجمَّد ما
         كان ينساب. */
      /* والصندوق الفارغ استثناء: لا محتوى فيه يحدّد قياسه، فقياسُه
         المُعلَن هو كلُّ ما لديه. ومساحةُ إرفاق الشواهد في هذا الملفّ
         صندوقٌ فارغٌ ارتفاعه سبعةٌ وأربعون بكسلًا من صنف — تخطّيناه
         فحشا المحرّر فيه فقرةً، فعلا اثنين وثلاثين، وتراكم في كلّ بندٍ
         حتّى بلغ آخرَ الصفحة مئةً وأربعين. */
      var sized = tag === 'table' || tag === 'col' || tag === 'colgroup' ||
                  tag === 'td' || tag === 'th' || tag === 'img'
      if (!sized) { skip['width'] = 1; skip['min-width'] = 1; skip['max-width'] = 1 }
      if (cs2.getPropertyValue('position') === 'static') {
        skip['top'] = 1; skip['bottom'] = 1
        skip['inset-inline-start'] = 1; skip['inset-inline-end'] = 1
        skip['z-index'] = 1
      }

      var add = ''
      for (var m = 0; m < PROPS.length; m++) {
        var p = PROPS[m]
        if (skip[p]) continue
        var v = cs2.getPropertyValue(p)
        if (!v || v === was[m]) continue
        add += p + ':' + v + ';'
      }
      if (!add) continue
      /* السطريُّ يبقى أوّلًا فيغلب: ما أعلنه التصميم صراحةً أولى ممّا
         استنبطناه من صنف. */
      var own = el.getAttribute('style') || ''
      el.setAttribute('style', add + (own && !/;s*$/.test(own) ? own + ';' : own))
    }
  }

  function go() {
    settle()
      .then(function () { return (document.fonts && document.fonts.ready) || null })
      .then(function () { return new Promise(function (r) { setTimeout(r, 150) }) })
      .then(function () { flatten() })
      .then(freezeImages)
      .then(function () {
        var junk = document.querySelectorAll('script, style, link[rel="stylesheet"], noscript')
        for (var i = 0; i < junk.length; i++) junk[i].remove()
        send(document.documentElement.outerHTML)
      })
      .catch(function (e) { send('', String((e && e.message) || e)) })
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(go, 0)
  else document.addEventListener('DOMContentLoaded', function () { setTimeout(go, 0) })
})();`}function Xe(n){const t=`<script>${Ge()}<\/script>`,s=n.toLowerCase().lastIndexOf("</body>");return s>0?n.slice(0,s)+t+n.slice(s):n+t}function Qe(n,t=2e4){return typeof document>"u"?Promise.resolve({html:"",note:"لا مستند"}):new Promise(s=>{const r=document.createElement("iframe");r.setAttribute("sandbox","allow-scripts"),r.setAttribute("aria-hidden","true"),r.setAttribute("title","معاينة التصميم"),r.style.cssText="position:fixed;top:0;left:-20000px;width:794px;height:1123px;border:0;visibility:hidden";let i=!1;const a=(g,l)=>{i||(i=!0,window.removeEventListener("message",m),clearTimeout(d),r.remove(),s({html:g,note:l}))},m=g=>{if(g.source!==r.contentWindow)return;const l=g.data;!l||l.__midad!=="design"||a(typeof l.html=="string"?l.html:"",l.note||"")},d=window.setTimeout(()=>a("","انقضت المهلة قبل أن يكتمل رسم التصميم"),t);window.addEventListener("message",m),r.srcdoc=Xe(n),document.body.appendChild(r)})}function he(n,t){const s=n.querySelector(`script[type="__bundler/${t}"]`);return s?(s.textContent||"").trim():null}function me(n){const t=new Map,s=he(n,"manifest");if(!s)return t;try{const r=JSON.parse(s);for(const[i,a]of Object.entries(r))!a?.data||!a.mime||a.compressed||/^image\/(png|jpe?g|gif|webp)$/i.test(a.mime)&&t.set(i,`data:${a.mime};base64,${a.data}`)}catch{}return t}function Ze(n){const t=new DOMParser().parseFromString(n,"text/html"),s=me(t),r=he(t,"template");if(r)try{const i=JSON.parse(r);if(typeof i=="string"&&i.length>40)return{html:i,bundled:!0,assets:s}}catch{}return{html:n,bundled:!1,assets:s}}const se={"sc-raw-table":"table","sc-raw-thead":"thead","sc-raw-tbody":"tbody","sc-raw-tfoot":"tbody","sc-raw-tr":"tr","sc-raw-td":"td","sc-raw-th":"th","sc-raw-caption":"caption","sc-raw-col":"col","sc-raw-colgroup":"colgroup"};function pe(n,t){const s=n.ownerDocument.createElement(t);for(const r of Array.from(n.attributes))s.setAttribute(r.name,r.value);for(;n.firstChild;)s.appendChild(n.firstChild);return n.replaceWith(s),s}const Ye="script,style,link,meta,noscript,iframe,object,embed,form,input,button,select,textarea,helmet,title,base",et=new Set(["style","colspan","rowspan","dir","align","width","height","data-page-break","data-page","src","alt"]);function tt(n){const t=(n||"").trim();return t&&(/^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(t)||/^https:\/\//i.test(t)||/^\/(?!\/)/.test(t))?t:null}function nt(n,t,s){n.querySelectorAll(Ye).forEach(i=>{t.add(i.tagName.toLowerCase()),i.remove()}),Object.keys(se).forEach(i=>{n.querySelectorAll(i).forEach(a=>pe(a,se[i]))});const r=i=>{for(const a of Array.from(i.children))r(a);for(const a of Array.from(i.attributes)){const m=a.name.toLowerCase();if(m.startsWith("on")){i.removeAttribute(a.name);continue}if(m==="style"){const d=de(a.value);d?i.setAttribute("style",d):i.removeAttribute("style");continue}if(m==="href"){/^(https?:|mailto:|tel:|#)/i.test(a.value)||i.removeAttribute("href");continue}if(!et.has(m)){i.removeAttribute(a.name);continue}if(m==="src"){const d=s.get(a.value.trim())??a.value,g=tt(d);g?i.setAttribute("src",g):(i.removeAttribute("src"),t.add("صورةٌ لم يُعرَف مصدرها"))}}};r(n)}const fe=25.4/96;function st(n){const t=n.trim().split(/\s+/).map(m=>{const d=/^([\d.]+)(px|pt|mm|in)?$/.exec(m);if(!d)return null;const g=Number(d[1]),l=d[2]||"px";return l==="px"?g:l==="pt"?g*96/72:l==="mm"?g/fe:g*96});if(t.some(m=>m===null))return null;const[s,r,i,a]=t;return t.length===1?[s,s,s,s]:t.length===2?[s,r,s,r]:t.length===3?[s,r,i,r]:t.length===4?[s,r,i,a]:null}function rt(n){const t=new Map;for(const l of n){const w=l.getAttribute("style")||"",v=/(?:^|;)\s*padding\s*:\s*([^;]+)/.exec(w);if(!v)continue;const S=st(v[1]);if(!S||S.every(h=>h===0))continue;const x=S.join(","),E=t.get(x);E?E.n++:t.set(x,{n:1,v:S})}let s,r=0;if(t.forEach(l=>{l.n>r&&(r=l.n,s=l.v)}),!s)return{top:14,right:14,bottom:14,left:14};const[i,a,m,d]=s,g=l=>Math.round(l*fe*10)/10;return{top:g(i),right:g(a),bottom:g(m),left:g(d)}}const it=["cairo","ibm plex mono"];function at(n){const t=[];n.querySelectorAll("style").forEach(s=>{const r=s.textContent||"";for(const i of r.matchAll(/font-family\s*:\s*([^;}]+)/gi))t.push(i[1])});for(const s of t){const r=s.split(",")[0].trim().replace(/^["']|["']$/g,"");if(it.includes(r.toLowerCase()))return r}return null}function ot(n){const t="div,table,ul,ol,section,h1,h2,h3,h4,h5,h6,blockquote,hr,p";let s=0;for(;s++<60;){const r=Array.from(n.querySelectorAll("div:not([data-page-break]):not([data-page]):not([style])")).filter(a=>!a.querySelector(t));if(!r.length)break;let i=!1;for(const a of r)a.parentNode&&(a.querySelector(t)?(a.replaceWith(...Array.from(a.childNodes)),i=!0):(pe(a,"p"),i=!0));if(!i)break}}function lt(n){let t=0;return n.querySelectorAll("table").forEach(s=>{const r=s.querySelectorAll("td,th");if(!r.length){s.remove(),t++;return}const i=Array.from(r).some(m=>(m.textContent||"").trim()),a=Array.from(r).some(m=>/background|border|height/.test(m.getAttribute("style")||""));!i&&!a&&(s.remove(),t++)}),t}const ct="td,th,p,div,span,li,section,article,header,footer,figcaption";function dt(n){let t=0;return n.querySelectorAll(ct).forEach(s=>{if((s.textContent||"").trim()||s.querySelector("img, svg, table, hr, input, canvas"))return;const r=s.getAttribute("style")||"";/line-height/i.test(r)||(s.setAttribute("style",`${r}${r&&!r.trim().endsWith(";")?"; ":""}line-height: 0`),t++)}),t}function ut(n){let t=0;return n.querySelectorAll("table").forEach(s=>{const r=s.getAttribute("style")||"",i=/(?:^|;)\s*(?:width|inline-size)\s*:\s*([^;]+)/i.exec(r);if(!i)return;const a=i[1].trim();!a||/auto/i.test(a)||(s.setAttribute("style",`${r}${r.trim().endsWith(";")?"":";"} --mdd-tw: ${a}`),t++)}),t}async function ht(n,t=""){const s=await Qe(n);return s.html?re(s.html,n,t,[]):re(n,n,t,[`تعذّر تشغيل التصميم لالتقاطه${s.note?` (${s.note})`:""} — قُرئ متنُه المكتوب. فإن كان قالبًا يرسم نفسه فقد تظهر فيه علامات مثل {{ … }}.`])}function re(n,t,s,r){const i=[...r],a=new Set,m=n!==t,{html:d,bundled:g,assets:l}=m?{html:n,bundled:!1,assets:me(new DOMParser().parseFromString(t,"text/html"))}:Ze(t);g&&i.push("الملفّ كان محزومًا — فُكَّ واستُخرج منه المتن.");const w=new DOMParser().parseFromString(d,"text/html"),v=w.body,S=(w.querySelector("title")?.textContent||"").trim(),x=Array.from(v.querySelectorAll("section.page, section[data-screen-label]")),E=x.length||1,h=x.length?{top:0,right:0,bottom:0,left:0}:rt(x),u=w.createElement("div");if(x.length)x.forEach((_,k)=>{if(k){const R=w.createElement("div");R.setAttribute("data-page-break","true"),u.appendChild(R)}const M=w.createElement("div");M.setAttribute("data-page","true");const F=de(_.getAttribute("style"));for(F&&M.setAttribute("style",F);_.firstChild;)M.appendChild(_.firstChild);u.appendChild(M)});else for(;v.firstChild;)u.appendChild(v.firstChild);const q=at(w);nt(u,a,l),q&&(u.querySelectorAll(":scope > [data-page]").forEach(_=>{const k=_.getAttribute("style")||"";/font-family/i.test(k)||_.setAttribute("style",`${k}${k&&!k.trim().endsWith(";")?";":""}font-family:'${q}',sans-serif`)}),u.querySelector(":scope > [data-page]")||u.querySelectorAll(":scope > *").forEach(_=>{const k=_.getAttribute("style")||"";/font-family/i.test(k)||_.setAttribute("style",`${k}${k&&!k.trim().endsWith(";")?";":""}font-family:'${q}',sans-serif`)})),ot(u);const A=lt(u),L=u.querySelectorAll("table").length,$=u.querySelectorAll("td,th").length,T=_=>{const k=_.cloneNode(!0);return k.querySelectorAll("br").forEach(M=>M.replaceWith(w.createTextNode(" "))),(k.textContent||"").replace(/\s+/g," ").trim()};let y="",b=0;u.querySelectorAll("p,h1,h2,h3").forEach(_=>{const k=T(_);if(!k||k.length<4||k.length>90)return;const M=_.getAttribute("style")||"",F=/font-size:\s*([\d.]+)px/.exec(M),R=F?Number(F[1]):_.tagName==="H1"?26:_.tagName==="H2"?20:12;R>b&&(b=R,y=k)}),y||(y=S&&S!=="Bundled Page"?S:s.replace(/\.[^.]+$/,""));const N=Array.from(a);N.includes("script")&&i.push("أُسقطت الشيفرة البرمجيّة من الملفّ — القالب يفتحه كلّ المعلّمين، فلا يُشغَّل فيه سكربت."),A&&i.push(`أُسقط ${A} جدولًا فارغًا لا نصّ فيه ولا لون.`),!L&&!u.textContent?.trim()&&i.push("لم نجد محتوًى في هذا الملفّ — تأكّد أنّه تصديرُ HTML لا صورة.");const c=x[0]?.getAttribute("style")||"",f=/width:\s*([\d.]+)(px|mm|in)/.exec(c),P=/height:\s*([\d.]+)(px|mm|in)/.exec(c),H=!!(f&&P&&Number(f[1])>Number(P[1]));return dt(u),ut(u),{title:y,html:u.innerHTML||"<p></p>",pages:E,tables:L,cells:$,landscape:H,margins:h,warnings:i,dropped:N}}function mt(n){const t={ا:"a",أ:"a",إ:"i",آ:"a",ب:"b",ت:"t",ث:"th",ج:"j",ح:"h",خ:"kh",د:"d",ذ:"dh",ر:"r",ز:"z",س:"s",ش:"sh",ص:"s",ض:"d",ط:"t",ظ:"z",ع:"a",غ:"gh",ف:"f",ق:"q",ك:"k",ل:"l",م:"m",ن:"n",ه:"h",و:"w",ي:"y",ى:"a",ة:"h",ء:"",ؤ:"w",ئ:"y"},s=[];for(const r of(n||"").normalize("NFKC"))t[r]!==void 0?s.push(t[r]):/[a-z0-9]/i.test(r)?s.push(r.toLowerCase()):/[\s\-_/]/.test(r)&&s.push("-");return s.join("").replace(/-+/g,"-").replace(/^-|-$/g,"").slice(0,56)||"template"}function ie({value:n,onChange:t,folders:s}){const r=i=>{const a=m=>s.find(d=>d.is_general&&d.audience===m)?.name;return i==="all"?`يظهر في «${a("school")||"ملفّات المدرسة"}» عند المدارس، وفي «${a("teacher")||"ملفّاتي"}» عند المعلّمين.`:`يظهر في «${a(i)||"—"}» — ولا يراه غيرهم.`};return e.jsx(J,{label:"لمن هذا القالب؟",help:r(n),children:e.jsxs(U,{value:n,onChange:i=>t(i.target.value),children:[e.jsx("option",{value:"all",children:"الكلّ — المدارس والمعلّمون معًا"}),e.jsx("option",{value:"school",children:"المدرسة — يراه المدير ومعلّموه"}),e.jsx("option",{value:"teacher",children:"المعلّم — لمشترك المعلّم المستقلّ"})]})})}function pt({folders:n,onClose:t,onDone:s}){const{toast:r}=oe(),i=C.useRef(null),[a,m]=C.useState(null),[d,g]=C.useState(null),[l,w]=C.useState(null),[v,S]=C.useState(""),[x,E]=C.useState("school"),h=c=>c==="all"?null:n.find(f=>f.is_general&&f.audience===c)?.id??null,[u,q]=C.useState(!1),[A,L]=C.useState(!1),[$,T]=C.useState(null),y=c=>/\.html?$/i.test(c.name)||/html/i.test(c.type),b=async c=>{if(T(null),g(null),w(null),m(c),c.size>25*1024*1024){T("الملفّ أكبر من ٢٥ ميغابايت");return}q(!0);try{if(y(c)){const f=await ht(await c.text(),c.name);w(f),S(f.title)}else{const f=await Ue(c);g(f),S(f.title)}}catch(f){T(f?.message||"تعذّرت قراءة الملفّ — تأكّد أنّه PDF أو HTML سليم")}finally{q(!1)}},N=async()=>{const c=l||d;if(!c||!a)return;const f=v.trim()||c.title;L(!0),T(null);try{const P=`${mt(f)}-${Date.now().toString(36).slice(-4)}`,H=l?"html":"pdf",_=l?"text/html":"application/pdf",k=`${P}.${H}`,M=await O.storage.from("template-sources").upload(k,a,{contentType:_,upsert:!0});if(M.error)throw new Error("تعذّر حفظ الأصل: "+M.error.message);const{data:F,error:R}=await O.from("templates").insert({slug:P,title:f,category_key:"general",description:null,kind:"doc",folder_id:h(x),audience:x,content_html:c.html,page:{size:"A4",orientation:c.landscape?"landscape":"portrait",margins:l?l.margins:{top:16,right:14,bottom:16,left:14}},source_pdf_path:k,source_pages:c.pages,status:"draft",outputs:["pdf","docx"]}).select("id").single();if(R)throw new Error(R.message);r("استُورد القالب مسوّدةً — راجعه ثمّ انشره"),s(F.id)}catch(P){T(P?.message||"تعذّر الحفظ")}finally{L(!1)}};return e.jsx(ye,{open:!0,onClose:t,title:"استيراد قالبٍ من ملفّ",wide:!0,footer:e.jsxs(e.Fragment,{children:[e.jsx(W,{variant:"secondary",onClick:t,block:!0,children:"إلغاء"}),e.jsx(W,{variant:"primary",onClick:N,block:!0,loading:A,disabled:!d&&!l||u,children:"احفظ مسوّدةً"})]}),children:e.jsxs("div",{className:"mdd-col",style:{gap:14},children:[!d&&!l&&e.jsxs("button",{type:"button",className:"mdd-drop",onClick:()=>i.current?.click(),onDragOver:c=>c.preventDefault(),onDrop:c=>{c.preventDefault();const f=c.dataTransfer.files?.[0];f&&b(f)},children:[u?e.jsx(xe,{size:30,className:"mdd-spin"}):e.jsx(le,{size:30}),e.jsx("b",{children:u?"جارٍ القراءة…":"اختر ملفّ HTML أو PDF، أو أسقطه هنا"}),e.jsx("span",{children:"ملفّ التصميم (HTML) يحفظ التصميم كاملًا — جداولَه وألوانه وصفحاته. وملفّ PDF يُستخرج نصُّه فقط."}),e.jsx("span",{children:"حتّى ٢٥ ميغابايت · تُقرأ في متصفّحك ولا تُرفع لتُقرأ"})]}),e.jsx("input",{ref:i,type:"file",accept:".html,.htm,text/html,application/pdf,.pdf",hidden:!0,onChange:c=>{const f=c.target.files?.[0];f&&b(f)}}),$&&e.jsx(V,{tone:"danger",children:$}),l&&e.jsxs(e.Fragment,{children:[e.jsxs("div",{className:"mdd-imp-stats",children:[e.jsxs("span",{children:[e.jsx("b",{children:l.pages})," صفحة"]}),e.jsxs("span",{children:[e.jsx("b",{children:l.tables})," جدولًا · ",e.jsx("b",{children:l.cells})," خليّة"]}),e.jsxs("span",{children:[e.jsx("b",{children:l.landscape?"أفقيّ":"رأسيّ"})," الاتّجاه"]}),e.jsxs("span",{className:"ok",children:[e.jsx(G,{size:13}),"التصميم محفوظ"]})]}),l.warnings.map((c,f)=>e.jsx(V,{tone:"info",children:c},f)),e.jsx(J,{label:"اسم القالب",children:e.jsx(X,{value:v,onChange:c=>S(c.target.value),placeholder:"مثال: تحليل نتيجة اختبار نافس"})}),e.jsx(ie,{value:x,onChange:E,folders:n}),e.jsxs("div",{className:"mdd-imp-prev",children:[e.jsx("span",{className:"mdd-imp-lab",children:"معاينة التصميم كما سيراه المعلّم"}),e.jsx("div",{className:"mdd-imp-prev-body mdd-imp-prev-body--design",dangerouslySetInnerHTML:{__html:l.html}})]}),e.jsx(V,{tone:"warn",children:"يُحفظ مسوّدةً لا يراها المعلّمون. افتحه في المحرّر وتحقّق، ثمّ انشره."})]}),d&&e.jsxs(e.Fragment,{children:[e.jsxs("div",{className:"mdd-imp-stats",children:[e.jsxs("span",{children:[e.jsx("b",{children:d.pages})," صفحة"]}),e.jsxs("span",{children:[e.jsx("b",{children:d.landscape?"أفقيّ":"رأسيّ"})," الاتّجاه"]}),e.jsxs("span",{className:d.health.healthy?"ok":"bad",children:[d.health.healthy?e.jsx(G,{size:13}):e.jsx(we,{size:13}),d.health.healthy?"طبقة النصّ سليمة":"طبقة النصّ فاسدة"]})]}),d.warnings.map((c,f)=>e.jsx(V,{tone:f===0&&!d.health.healthy?"danger":"info",children:c},f)),e.jsx(J,{label:"اسم القالب",children:e.jsx(X,{value:v,onChange:c=>S(c.target.value),placeholder:"مثال: تحليل نتيجة اختبار نافس"})}),e.jsx(ie,{value:x,onChange:E,folders:n}),e.jsxs("div",{className:"mdd-imp-prev",children:[e.jsx("span",{className:"mdd-imp-lab",children:"معاينة المتن المستخرَج"}),e.jsx("div",{className:"mdd-imp-prev-body",dangerouslySetInnerHTML:{__html:d.html}})]}),e.jsx(V,{tone:"warn",children:"يُحفظ مسوّدةً لا يراها المعلّمون. افتحه في المحرّر، أعِد جدولة ما كان مؤطَّرًا، راجع الأرقام، ثمّ انشره."})]})]})})}const ae=[{key:"school",name:"قوالب المدرسة",dot:"oklch(0.55 0.16 245)",col:"sort_school"},{key:"teacher",name:"قوالب المعلّم",dot:"var(--mdd-accent)",col:"sort_teacher"}],ft={all:"الكلّ",school:"المدرسة",teacher:"المعلّم"};function vt(){const{toast:n}=oe(),t=Te(),[s,r]=C.useState(""),[i,a]=C.useState(""),[m,d]=C.useState(""),[g,l]=C.useState(null),[w,v]=C.useState(!1),[S,x]=C.useState(!1),E=ve(s),{data:h,loading:u,error:q,reload:A}=je(async()=>{const[o,p]=await Promise.all([O.from("templates").select("id,slug,title,category_key,description,outputs,estimated_minutes,version,status,usage_count,is_new,sort,sort_school,sort_teacher,created_at,updated_at,kind,folder_id,audience,page,source_pdf_path,source_pages,body_len").order("title"),O.from("template_folders").select("*").order("sort").order("name")]);if(o.error)throw new Error(o.error.message);if(p.error)throw new Error(p.error.message);return{list:o.data||[],folders:p.data||[]}},[]),L=h?.folders||[],[$,T]=C.useState([]);C.useEffect(()=>{T(h?.list||[])},[h]);const y=$.filter(o=>o.status==="published").length,b=$.filter(o=>o.status==="draft").length,N=C.useMemo(()=>{const o=E.trim();return p=>(!o||p.title.includes(o)||p.slug.includes(o.toLowerCase()))&&(!i||p.audience===i)&&(!m||p.status===m)},[E,i,m]),c=(o,p)=>$.filter(j=>j.audience===o||j.audience==="all").sort((j,z)=>(j[p]??0)-(z[p]??0)||j.title.localeCompare(z.title,"ar")),f=async(o,p)=>{const j=o.filter((D,B)=>(D[p]??0)!==B);if(!j.length)return;T(D=>D.map(B=>{const K=o.findIndex(ge=>ge.id===B.id);return K<0?B:{...B,[p]:K}}));const I=(await Promise.all(j.map(D=>O.from("templates").update({[p]:o.findIndex(B=>B.id===D.id)}).eq("id",D.id)))).find(D=>D.error);I?.error&&(n("تعذّر حفظ الترتيب: "+I.error.message,"danger"),A())},P=(o,p,j,z)=>{const I=c(o,p);if(j===z||z<0||z>=I.length)return;const D=I.slice();D.splice(z,0,D.splice(j,1)[0]),f(D,p)},H=async(o,p)=>{if(p===o.audience)return;const j=p==="all"?null:L.find(I=>I.is_general&&I.audience===p)?.id??null;T(I=>I.map(D=>D.id===o.id?{...D,audience:p,folder_id:j}:D));const{error:z}=await O.from("templates").update({audience:p,folder_id:j}).eq("id",o.id);if(z){n(z.message,"danger"),A();return}n(`صار «${o.title}» لـ${ft[p]}`)},_=async()=>{v(!0);const o=`template-${Date.now().toString(36)}`,{data:p,error:j}=await O.from("templates").insert({slug:o,title:"قالب جديد",category_key:"general",description:"",kind:"doc",audience:"school",folder_id:L.find(z=>z.is_general&&z.audience==="school")?.id??null,content_html:`<h1>عنوان المستند</h1>
<p>اكتب هنا، أو أدرج جدولًا من شريط الأدوات.</p>`,status:"draft",outputs:["pdf","docx"]}).select("id").single();if(v(!1),j){n(j.message,"danger");return}t(`/admin/template/${p.id}`)},k=async o=>{const{data:p,error:j}=await O.from("templates").select("content_html").eq("id",o.id).maybeSingle();if(j){n(j.message,"danger");return}const{data:z,error:I}=await O.from("templates").insert({slug:`${o.slug}-copy-${Date.now().toString(36)}`,title:`نسخة من ${o.title}`,category_key:o.category_key,description:o.description,kind:o.kind??"doc",audience:o.audience,folder_id:o.folder_id,content_html:p?.content_html??"",page:o.page,outputs:o.outputs,estimated_minutes:o.estimated_minutes,status:"draft"}).select("id").single();if(I){n(I.message,"danger");return}n("أُنشئت نسخة مسوّدة"),t(`/admin/template/${z.id}`)},M=async o=>{const p=o.status==="published"?"draft":"published",{error:j}=await O.from("templates").update({status:p}).eq("id",o.id);if(j){n(j.message,"danger");return}n(p==="published"?"نُشر القالب — صار يظهر للمشتركين":"سُحب القالب من المشتركين"),A()},F=async()=>{if(!g)return;v(!0);const{error:o}=await O.from("templates").delete().eq("id",g.id);if(v(!1),o){n("تعذّر الحذف — قد تكون هناك ملفّات مبنيّة عليه","danger");return}n("حُذف القالب"),l(null),A()};if(q)return e.jsx(ke,{onRetry:A,message:q});const R=ae.some(o=>c(o.key,o.col).filter(N).length>0);return e.jsxs(e.Fragment,{children:[e.jsx(Se,{title:"مكتبة القوالب",sub:u?"جارٍ التحميل…":`${Q(y)} منشورًا يراه المشتركون · ${Q(b)} مسوّدة عندك`,actions:e.jsxs("div",{className:"mdd-row",style:{gap:8},children:[e.jsx(W,{auto:!0,variant:"secondary",icon:e.jsx(le,{size:15}),onClick:()=>x(!0),children:"استورد ملفًّا"}),e.jsx(W,{auto:!0,variant:"primary",icon:e.jsx(Ae,{size:15}),loading:w,onClick:_,children:"قالب جديد"})]})}),e.jsxs(ce,{className:"mdd-col",style:{gap:12,marginBlockEnd:"var(--mdd-s-4)"},children:[e.jsx(_e,{value:s,onChange:r,placeholder:"ابحث بالعنوان أو المفتاح"}),e.jsxs("div",{className:"mdd-grid mdd-grid--2",style:{gap:10},children:[e.jsxs(U,{value:i,onChange:o=>a(o.target.value),"aria-label":"الصلاحيّة",children:[e.jsx("option",{value:"",children:"كلّ الصلاحيّات"}),e.jsx("option",{value:"all",children:"الكلّ"}),e.jsx("option",{value:"school",children:"المدرسة"}),e.jsx("option",{value:"teacher",children:"المعلّم"})]}),e.jsxs(U,{value:m,onChange:o=>d(o.target.value),"aria-label":"الحالة",children:[e.jsx("option",{value:"",children:"كلّ الحالات"}),e.jsx("option",{value:"published",children:"منشور"}),e.jsx("option",{value:"draft",children:"مسوّدة"})]})]})]}),u?e.jsx(Ce,{n:8}):R?ae.map(o=>e.jsx(gt,{audience:o,rows:c(o.key,o.col),visible:N,onMove:(p,j)=>P(o.key,o.col,p,j),onAudience:H,onOpen:p=>t(`/admin/template/${p.id}`),onDuplicate:k,onPublish:M,onDelete:l},o.key)):e.jsx(Ee,{art:e.jsx(Ne,{size:58}),title:$.length?"لا نتيجة":"المكتبة فارغة",line:$.length?"جرّب كلمةً أقصر أو امسح الفلاتر.":"صمّم قالبًا في المحرّر، أو استورد ملفًّا جاهزًا — ثمّ انشره فيظهر للمشتركين.",action:e.jsx(W,{variant:"primary",onClick:$.length?()=>{r(""),a(""),d("")}:_,children:$.length?"امسح الفلاتر":"أنشئ قالبًا"})}),g&&e.jsx($e,{open:!0,onClose:()=>l(null),onConfirm:F,loading:w,title:"حذف القالب",danger:!0,confirmLabel:"احذف",body:`سيُحذف «${g.title}» ولن يظهر للمشتركين. الملفّات التي أنشأوها منه تبقى عندهم.`}),S&&e.jsx(pt,{folders:L,onClose:()=>x(!1),onDone:o=>{x(!1),A(),o&&t(`/admin/template/${o}`)}})]})}function gt({audience:n,rows:t,visible:s,onMove:r,onAudience:i,onOpen:a,onDuplicate:m,onPublish:d,onDelete:g}){const[l,w]=C.useState(null),[v,S]=C.useState(null),x=t.filter(s),E=x.length!==t.length;return x.length?e.jsxs(ce,{className:"mdd-col",style:{gap:0,padding:0,marginBlockEnd:"var(--mdd-s-4)"},children:[e.jsxs("div",{className:"mdd-tsec",children:[e.jsx("span",{className:"mdd-tsec__dot",style:{background:n.dot}}),e.jsx("b",{children:n.name}),e.jsxs("span",{className:"mdd-tsec__n",children:[t.length," · ",E?"امسح البحث لترتّبها":"رتّبها بالسحب أو بالسهمين"]})]}),e.jsx("div",{className:"mdd-table-wrap mdd-table-wrap--cards",children:e.jsxs("table",{className:"mdd-table",children:[e.jsx("thead",{children:e.jsxs("tr",{children:[e.jsx("th",{"aria-label":"ترتيب",style:{inlineSize:78}}),e.jsx("th",{children:"القالب"}),e.jsx("th",{children:"الصلاحيّة"}),e.jsx("th",{children:"الحالة"}),e.jsx("th",{children:"آخر تحديث"}),e.jsx("th",{"aria-label":"إجراءات"})]})}),e.jsx("tbody",{children:x.map(h=>{const u=t.indexOf(h),q=(h.body_len??0)===0;return e.jsxs("tr",{draggable:!E,onDragStart:()=>w(u),onDragEnd:()=>{w(null),S(null)},onDragOver:A=>{l!==null&&(A.preventDefault(),S(u))},onDrop:A=>{A.preventDefault(),l!==null&&r(l,u),w(null),S(null)},className:[l===u?"mdd-trow--drag":"",v===u&&l!==null&&l!==u?"mdd-trow--over":""].filter(Boolean).join(" "),children:[e.jsx("td",{"data-label":"ترتيب",children:e.jsxs("div",{className:"mdd-ord",children:[e.jsx("span",{className:"mdd-ord__grip","aria-hidden":"true",title:E?"امسح البحث لترتّب":"اسحب لتُرتّب",children:"⠿"}),e.jsxs("div",{className:"mdd-ord__arrows",children:[e.jsx("button",{type:"button",className:"mdd-ord__btn",disabled:E||u===0,"aria-label":`ارفع ${h.title}`,onClick:()=>r(u,u-1),children:"▲"}),e.jsx("button",{type:"button",className:"mdd-ord__btn",disabled:E||u===t.length-1,"aria-label":`أنزل ${h.title}`,onClick:()=>r(u,u+1),children:"▼"})]})]})}),e.jsxs("td",{"data-label":"القالب",children:[e.jsx("button",{className:"mdd-linkish",onClick:()=>a(h),children:h.title}),e.jsxs("span",{className:"mdd-dim",style:{display:"block",fontSize:11.5},children:[h.slug,h.source_pages?` · ${h.source_pages} صفحة من الأصل`:""]}),q&&e.jsx(Z,{tone:"warn",children:"فارغ"})]}),e.jsx("td",{"data-label":"الصلاحيّة",children:e.jsxs(U,{value:h.audience,"aria-label":`صلاحيّة ${h.title}`,onChange:A=>i(h,A.target.value),children:[e.jsx("option",{value:"all",children:"الكلّ"}),e.jsx("option",{value:"school",children:"المدرسة"}),e.jsx("option",{value:"teacher",children:"المعلّم"})]})}),e.jsx("td",{"data-label":"الحالة",children:e.jsx(Z,{tone:h.status==="published"?"success":"neutral",children:h.status==="published"?"منشور":"مسوّدة"})}),e.jsx("td",{"data-label":"آخر تحديث",className:"mdd-dim",children:Pe(h.updated_at)}),e.jsx("td",{children:e.jsxs("div",{className:"mdd-row",style:{gap:6,justifyContent:"flex-end"},children:[e.jsx(W,{auto:!0,size:"sm",variant:"ghost",title:"تحرير",onClick:()=>a(h),children:e.jsx(ze,{size:15})}),e.jsx(W,{auto:!0,size:"sm",variant:"ghost",title:"مضاعفة",onClick:()=>m(h),children:e.jsx(De,{size:15})}),e.jsx(W,{auto:!0,size:"sm",variant:h.status==="published"?"secondary":"primary",onClick:()=>d(h),children:h.status==="published"?"اسحب":"انشر"}),e.jsx(W,{auto:!0,size:"sm",variant:"ghost",title:"حذف",onClick:()=>g(h),children:e.jsx(qe,{size:15})})]})})]},h.id)})})]})})]}):null}export{vt as default};
