/**
 * التقاط تصميمٍ **كما يُرسَم** لا كما يُكتَب.
 *
 * ملفّ أداة التصميم ليس صفحةً جاهزة. منه ما متنُه ثابتٌ يُقرأ كما هو،
 * ومنه ما هو **قالبٌ يرسم نفسه**: متنُه المكتوب فيه `{{ p.title }}`
 * و`{{ f.bv }}`، ومعه جافاسكربت يقرأ بياناتٍ ويولّد المستند عند الفتح.
 *
 * وكان المستورد يقرأ المتن المكتوب ويُسقط السكربتات. فخرج القالب الأخير
 * إلى المالك وفيه `{{ p.title }}` حرفيًّا في مكان العناوين. ولا علاج
 * لهذا بترقيع: لا سبيل إلى معرفة ما سيولّده سكربتٌ إلّا بتشغيله.
 *
 *     ما يُرسَم بالتنفيذ لا يُقرأ من المصدر.
 *
 * فصار الملفّ يُفتح في إطارٍ معزول، ويُترك ليرسم نفسه، ثمّ يُلتقط ناتجه.
 * وهذا يعمّ: الملفّ الثابت يُلتقط كما هو (تشغيلُه لا يغيّره)، والقالب
 * يُلتقط مرسومًا. طريقٌ واحدٌ لكلّ الملفّات.
 *
 * ═══ والعزل شرط ═══
 *
 * السكربت من ملفٍّ يرفعه المالك، فيُشغَّل في `iframe` بـ`sandbox` بلا
 * `allow-same-origin`: أصلٌ مُعتِمٌ لا يبلغ تخزيننا ولا كعكاتنا ولا
 * مستندنا. ويبقى `postMessage` وحده طريقًا للخروج — وهو ما نريده منه.
 *
 * ═══ ولمَ يُسطَّح الشكل ═══
 *
 * ثلثُ عناصر هذا الملفّ يستمدّ شكله من `class` وورقةِ أنماطٍ في رأسه، لا
 * من سمةٍ سطريّة (١٦٣٥ من ٤٦٠٣). وورقة الأنماط لا تسافر مع المتن: هي
 * ليست عقدةً في مخطّط المحرّر، وأصنافها تصطدم بأصنافنا.
 *
 * فيُقرأ الشكل **المحسوب** لكلّ عنصرٍ ويُكتب سطريًّا. فيصير المستند
 * مستغنيًا عن كلّ ورقة أنماط — وهذا هو معنى «إعداداتٌ واحدةٌ لكلّ
 * الملفّات»: لا يبقى في الشكل شيءٌ يعتمد على ما لم نأخذه.
 *
 * ولا يُكتب كلُّ ما يُحسب: ثلاثُ مئة خاصّيّةٍ لكلّ عنصرٍ متنٌ لا يُحتمل.
 * فيُكتب ما يخالف الافتراض وحده — والافتراض يُقاس بتعطيل أوراق التصميم
 * نفسها ثمّ قياس عنصرٍ عارٍ من نوعه. والموروثُ يُقاس على الأب: ما ساوى
 * أباه يرثه في مِداد كما ورثه هناك.
 */

/** الخصائص التي يتوقّف عليها شكل مستند. وما سواها لا يُنقل. */
const PROPS = [
  'color', 'background-color', 'background-image', 'background-size',
  'background-position', 'background-repeat', 'background-clip',
  'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
  'line-height', 'letter-spacing', 'word-spacing', 'text-align',
  'text-decoration-line', 'text-transform',
  'text-indent', 'white-space', 'direction', 'unicode-bidi', 'writing-mode',
  'display', 'position', 'top', 'inset-inline-start', 'inset-inline-end', 'bottom',
  'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
  'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
  'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
  'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
  'border-top-left-radius', 'border-top-right-radius',
  'border-bottom-left-radius', 'border-bottom-right-radius',
  'box-shadow', 'opacity', 'overflow-x', 'overflow-y', 'box-sizing',
  'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-self',
  'flex-grow', 'flex-shrink', 'flex-basis', 'gap', 'row-gap', 'column-gap',
  'grid-template-columns', 'grid-template-rows', 'grid-column', 'grid-row',
  'vertical-align', 'table-layout', 'border-collapse', 'border-spacing',
  'list-style-type', 'list-style-position', 'transform', 'transform-origin',
  'object-fit', 'aspect-ratio', 'z-index', 'float', 'clear',
] as const

/** ما يورثه العنصر عن أبيه — يُقاس عليه لا على عنصرٍ عارٍ. */
const INHERITED = new Set([
  'color', 'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
  'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-transform',
  'text-indent', 'white-space', 'direction', 'writing-mode', 'visibility',
  'list-style-type', 'list-style-position', 'border-spacing', 'border-collapse',
])

/**
 * السكربت الذي يُحقن في الإطار.
 *
 * ويُكتب نصًّا لا دالّةً تُمرَّر: الإطار أصلٌ آخر، ولا سبيل إلى تمرير
 * إغلاقٍ إليه. فيُبنى المصدر هنا وتُغرَس فيه الثوابت.
 */
function snapshotScript(): string {
  return `
(function () {
  var PROPS = ${JSON.stringify(PROPS)};
  var INHERITED = ${JSON.stringify([...INHERITED])};
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
      el.setAttribute('style', add + (own && !/;\s*$/.test(own) ? own + ';' : own))
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
})();`
}

/** يُلحق سكربت الالتقاط بآخر المستند، بعد سكربتات التصميم كلّها. */
function inject(raw: string): string {
  const tag = `<script>${snapshotScript()}</` + 'script>'
  const i = raw.toLowerCase().lastIndexOf('</body>')
  return i > 0 ? raw.slice(0, i) + tag + raw.slice(i) : raw + tag
}

export interface RenderResult {
  /** متن المستند بعد أن رسم نفسه — أو فراغٌ إن تعذّر */
  html: string
  /** سببُ التعذّر، إن كان */
  note: string
}

/**
 * يفتح التصميم في إطارٍ معزول، ويتركه يرسم نفسه، ثمّ يلتقط ناتجه.
 *
 * ويُعطى عرضًا ثابتًا (A4 عند ٩٦ نقطةً في البوصة): التصميم قد يقيس عرض
 * النافذة، فلو تُرك لعرضٍ عشوائيٍّ لاختلف ما يرسمه باختلاف شاشة الرافع.
 */
export function renderDesign(raw: string, timeoutMs = 20000): Promise<RenderResult> {
  if (typeof document === 'undefined') return Promise.resolve({ html: '', note: 'لا مستند' })

  return new Promise((resolve) => {
    const frame = document.createElement('iframe')
    /* بلا `allow-same-origin`: أصلٌ مُعتِمٌ لا يبلغ تخزيننا ولا مستندنا،
       ويبقى `postMessage` طريقه الوحيد إلينا. */
    frame.setAttribute('sandbox', 'allow-scripts')
    frame.setAttribute('aria-hidden', 'true')
    frame.setAttribute('title', 'معاينة التصميم')
    frame.style.cssText =
      'position:fixed;top:0;left:-20000px;width:794px;height:1123px;border:0;visibility:hidden'

    let settled = false
    const finish = (html: string, note: string) => {
      if (settled) return
      settled = true
      window.removeEventListener('message', onMessage)
      clearTimeout(timer)
      frame.remove()
      resolve({ html, note })
    }

    const onMessage = (e: MessageEvent) => {
      if (e.source !== frame.contentWindow) return
      const d = e.data as { __midad?: string; html?: string; note?: string } | null
      if (!d || d.__midad !== 'design') return
      finish(typeof d.html === 'string' ? d.html : '', d.note || '')
    }

    const timer = window.setTimeout(
      () => finish('', 'انقضت المهلة قبل أن يكتمل رسم التصميم'), timeoutMs)

    window.addEventListener('message', onMessage)
    frame.srcdoc = inject(raw)
    document.body.appendChild(frame)
  })
}
