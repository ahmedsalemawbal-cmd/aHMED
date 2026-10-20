import { esc, icon, dirIcons } from '../lib.mjs';
import { layout, breadcrumb } from '../layout.mjs';
import { whatsappUrl } from '../data/site.mjs';

export function renderAbout(ctx) {
  const { t, L, root } = ctx;
  const a = t.about;
  const d = dirIcons(L);
  const valueIcons = ['handshake', 'eye', 'medal', 'lightning', 'scales'];
  const values = a.values
    .map(
      (v, i) => `<div class="val">
          <span class="val-ic">${icon('duo:' + valueIcons[i])}</span>
          <strong>${esc(v)}</strong>
        </div>`
    )
    .join('\n        ');

  const body = `
<section class="ab-intro">
  <div class="ab-intro-in">
    ${breadcrumb(ctx, a.title)}
    <div class="ab-grid">
      <div class="ab-txt">
        <h1>${esc(a.title)}</h1>
        <p>${esc(a.text)}</p>
        <div class="ab-btns">
          <a class="ab-btn" href="#">${icon('file-pdf')}${esc(t.common.profile)}</a>
          <a class="ab-btn ab-btn-o" href="index.html#contact">${esc(t.common.contactUs)}${icon(d.fwd)}</a>
        </div>
      </div>
      <div class="ab-mosaic">
        <div class="ph ab-ph-tall"><span class="ph-label">${esc(a.photoOffice)}</span></div>
        <div class="ab-logo"><img src="${root}assets/img/logo-gold-840.webp" alt="${esc(t.header.logoAlt)}" width="519" height="840"></div>
        <div class="ph"><span class="ph-label">${esc(a.photoTeam)}</span></div>
      </div>
    </div>
  </div>
</section>

<section class="vm">
  <div class="vm-grid">
    <div class="vm-card vm-vision">
      <img class="vm-wm" src="${root}assets/img/logo-white-480.webp" alt="" width="296" height="480" loading="lazy">
      <span class="vm-ic">${icon('eye')}</span>
      <h2>${esc(a.vision)}</h2>
      <p>${esc(a.visionText)}</p>
    </div>
    <div class="vm-card vm-mission">
      <span class="vm-ic">${icon('compass')}</span>
      <h2>${esc(a.mission)}</h2>
      <p>${esc(a.missionText)}</p>
    </div>
  </div>
</section>

<section class="values">
  <div class="values-in">
    <div class="values-head">
      <span class="values-eyebrow"><span class="vline"></span>${esc(a.valuesEyebrow)}<span class="vline"></span></span>
      <h2>${esc(a.valuesTitle)}</h2>
    </div>
    <div class="values-grid">
        ${values}
    </div>
  </div>
</section>

<section class="ab-cta">
  <div class="ab-cta-box">
    <div class="ab-cta-txt">
      <h2>${esc(t.common.ctaTitle)}</h2>
      <p>${esc(t.common.ctaText)}</p>
    </div>
    <div class="ab-cta-btns">
      <a class="ab-cta-wa" href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(t.common.whatsappBtn)}</a>
      <a class="ab-cta-req" href="request.html">${icon('file-plus')}${esc(t.common.requestBtn)}</a>
    </div>
  </div>
</section>
`;
  return layout(ctx, { body, active: 'about' });
}
