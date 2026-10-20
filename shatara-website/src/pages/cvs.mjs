import { esc, icon } from '../lib.mjs';
import { layout, breadcrumb } from '../layout.mjs';
import { cvs } from '../data/cvs.mjs';

export function renderCvs(ctx) {
  const { t, root } = ctx;
  const c = t.cvs;
  const f = c.filters;
  const uniq = list => [...new Set(list)];

  // Localised view of every CV.
  const items = cvs.map(cv => {
    const job = t.cvJobs[cv.job];
    const country = t.countries[cv.cc];
    const ageText = c.ageText(cv.age);
    const expText = c.expText(cv.years);
    const langText = cv.langs.map(l => t.languages[l]).join(c.listSep);
    const rel = t.religions[cv.religion][cv.gender];
    return { ...cv, jobText: job, country, ageText, expText, langText, rel, name: cv.name[t.lang], book: `request.html?cv=${cv.code}` };
  });

  const options = (all, list) =>
    [`<option value="">${esc(all)}</option>`, ...list.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`)].join('');
  const filters = [
    ['job', f.job, options(f.jobAll, uniq(cvs.map(x => x.job)).map(k => [k, t.cvJobs[k]]))],
    ['country', f.country, options(f.countryAll, uniq(cvs.map(x => x.cc)).map(k => [k, t.countries[k]]))],
    ['age', f.age, options(f.ageAll, [['a', f.ageA], ['b', f.ageB], ['c', f.ageC]])],
    ['exp', f.exp, options(f.expAll, [['yes', f.expYes], ['no', f.expNo]])],
    ['rel', f.rel, options(f.relAll, [['muslim', f.relMuslim], ['other', f.relOther]])],
    ['lang', f.lang, options(f.langAll, [['ar', t.languages.ar], ['en', t.languages.en]])]
  ]
    .map(([k, label, opts]) => `<label class="cvf-l">${esc(label)}
            <select class="cvf-s" data-filter="${k}">${opts}</select>
          </label>`)
    .join('\n          ');

  const fact = (label, value) =>
    `<div class="cv-fact"><span>${esc(label)}</span><span>${esc(value)}</span></div>`;

  const cards = items
    .map(
      x => `<article class="cv" data-cv="${x.code}" data-job="${x.job}" data-cc="${x.cc}" data-age="${x.age}" data-years="${x.years}" data-rel="${x.religion}" data-langs="${x.langs.join(' ')}">
            <div class="cv-photo">
              <span class="ph-label">${esc(c.photo)}</span>
              <span class="cv-badge">${esc(x.jobText)}</span>
              <span class="cv-code"><bdi dir="ltr">${x.code}</bdi></span>
            </div>
            <div class="cv-body">
              <div class="cv-head">
                <h3>${esc(x.name)}</h3>
                <span class="cv-country"><img src="${root}assets/flags/${x.cc}.svg" alt="" width="22" height="15" loading="lazy">${esc(x.country)}</span>
              </div>
              <div class="cv-facts">
                ${fact(c.facts.job, x.jobText)}
                ${fact(c.facts.age, x.ageText)}
                ${fact(c.facts.exp, x.expText)}
                ${fact(c.facts.langs, x.langText)}
              </div>
              <div class="cv-salary"><span>${esc(c.facts.salary)}</span><span>${x.salary} <span>${esc(c.currency)}</span></span></div>
              <div class="cv-btns">
                <button class="cv-view" type="button" data-view="${x.code}">${esc(c.view)}</button>
                <a class="cv-book" href="${x.book}">${esc(c.book)}</a>
              </div>
            </div>
          </article>`
    )
    .join('\n          ');

  // One pre-rendered dialog body per CV; the script copies the chosen one into the dialog.
  const modals = items
    .map(
      x => `<template id="cvm-${x.code}">
        <div class="cvm-head">
          <span class="cvm-title" id="cvm-title">${esc(c.modalTitle)} <span dir="ltr">${x.code}</span></span>
          <button class="cvm-close" type="button" data-close aria-label="${esc(c.close)}">${icon('x')}</button>
        </div>
        <div class="cvm-body">
          <div class="cvm-media">
            <div class="cvm-photo"><span class="ph-label">${esc(c.photo)}</span></div>
            <div class="cvm-video">${icon('play-circle')}${esc(c.video)}</div>
          </div>
          <div class="cvm-info">
            <div class="cvm-name">
              <h2>${esc(x.name)}</h2>
              <span><img src="${root}assets/flags/${x.cc}.svg" alt="" width="24" height="16">${esc(x.country)} • ${esc(x.jobText)}</span>
            </div>
            <div class="cvm-facts">
              ${[
                [c.facts.job, x.jobText],
                [c.facts.age, x.ageText],
                [c.facts.exp, x.expText],
                [c.facts.rel, x.rel],
                [c.facts.langs, x.langText],
                [c.facts.salary, `${x.salary} ${c.currency}`]
              ]
                .map(([l, v]) => `<div><span>${esc(l)}</span><span>${esc(v)}</span></div>`)
                .join('')}
            </div>
            <div class="cvm-skills">
              <span>${esc(c.skills)}</span>
              <div>${x.skills.map(s => `<span>${esc(t.skills[s])}</span>`).join('')}</div>
            </div>
            <div class="cvm-btns">
              <a class="cvm-pdf" href="#">${icon('file-pdf')}${esc(c.pdf)}</a>
              <a class="cvm-book" href="${x.book}">${esc(c.book)}</a>
            </div>
          </div>
        </div>
      </template>`
    )
    .join('\n      ');

  const body = `
<section class="pg-head">
  <div class="pg-head-in">
    ${breadcrumb(ctx, c.title)}
    <h1>${esc(c.title)}</h1>
    <p>${esc(c.text)}</p>
  </div>
</section>

<section class="cvf-wrap" data-cvs>
  <div class="cvf">
    <div class="cvf-top">
      <span class="cvf-title">${icon('sliders-horizontal')}${esc(c.filterTitle)}</span>
      <div class="cvf-actions">
        <button class="cvf-clear" type="button" data-clear hidden>${icon('arrow-counter-clockwise')}${esc(c.clear)}</button>
        <button class="cvf-toggle" type="button" data-toggle aria-expanded="false" data-show="${esc(c.showFilters)}" data-hide="${esc(c.hideFilters)}"><span>${esc(c.showFilters)}</span>${icon('caret-down', 'ic-down')}${icon('caret-up', 'ic-up')}</button>
      </div>
    </div>
    <div class="cvf-grid">
          ${filters}
    </div>
  </div>
</section>

<section class="cv-sec">
  <div class="cv-in">
    <span class="cv-count" data-count data-label="${esc(c.results)}" aria-live="polite">${esc(c.results)}${items.length}</span>
    <div class="cv-grid" data-grid>
          ${cards}
    </div>
    <div class="cv-empty" data-empty hidden>
      <span class="cv-empty-ic">${icon('magnifying-glass')}</span>
      <p>${esc(c.empty)}</p>
      <a href="request.html">${icon('file-plus')}${esc(t.common.requestBtn)}</a>
    </div>
  </div>
</section>

<div class="cvm" data-modal hidden>
  <div class="cvm-dialog" role="dialog" aria-modal="true" aria-labelledby="cvm-title" tabindex="-1"></div>
</div>
${modals}
`;
  return layout(ctx, { body, active: 'cvs' });
}
