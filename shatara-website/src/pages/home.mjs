import { esc, icon, dirIcons, ornament } from '../lib.mjs';
import { layout } from '../layout.mjs';
import { contact, whatsappUrl, countryCodes, SITE_URL } from '../data/site.mjs';

const HERO_IMAGES = [
  { src: 'hero-1', w: 848, h: 1264 },
  { src: 'hero-2', w: 848, h: 1264 },
  { src: 'hero-3', w: 617, h: 920 }
];
const JOB_SIZES = [[464, 253], [464, 253], [464, 253], [464, 253], [464, 253], [464, 253], [259, 464], [464, 253], [464, 253]];

export function renderHome(ctx) {
  const { t, L, root } = ctx;
  const c = t.home;
  const d = dirIcons(L);

  const slideButtons = [
    `<a class="btn-gold" href="cvs.html">${icon('identification-card')}${esc(c.slides[0].primary)}</a>
            <a class="btn-ghost" href="request.html">${esc(c.slides[0].secondary)}${icon(d.fwd)}</a>`,
    `<a class="btn-gold" href="request.html">${icon('buildings')}${esc(c.slides[1].primary)}</a>`,
    `<a class="btn-gold" href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(c.slides[2].primary)}</a>`
  ];
  const slides = c.slides
    .map((s, i) => {
      const tag = i === 0 ? 'h1' : 'h2';
      return `<div class="hero-slide" data-slide${i ? ' hidden' : ''}>
          <span class="hero-label"><span class="dia"></span>${esc(s.label)}</span>
          <${tag} class="hero-title">${esc(s.title)}</${tag}>
          <p class="hero-p">${esc(s.text)}</p>
          <div class="hero-btns">
            ${slideButtons[i]}
          </div>
        </div>`;
    })
    .join('\n        ');
  const heroImgs = HERO_IMAGES.map(
    (im, i) => `<div class="hero-img${i ? '' : ' on'}" data-slide-img>
                <img src="${root}assets/img/${im.src}.webp" srcset="${root}assets/img/${im.src}-480.webp 480w, ${root}assets/img/${im.src}.webp ${im.w}w" sizes="(max-width: 520px) 92vw, 460px" width="${im.w}" height="${im.h}" alt="${esc(c.slides[i].alt)}"${i ? ' loading="lazy"' : ' fetchpriority="high"'}>
              </div>`
  ).join('\n              ');
  const dots = c.slides
    .map((_, i) => `<button class="hero-dot${i ? '' : ' on'}" type="button" data-dot aria-label="${esc(c.slide)} ${i + 1}"${i ? '' : ' aria-current="true"'}></button>`)
    .join('');

  const sectorIcons = ['buildings', 'users-three', 'handshake'];
  const sectors = c.sectors
    .map(
      (s, i) => `<a class="sector" href="#services">
        <span class="sector-ic"><span class="sector-ic-frame"></span>${icon('duo:' + sectorIcons[i])}</span>
        <span class="sector-txt"><strong>${esc(s.title)}</strong><span>${esc(s.sub)}</span></span>
        ${icon(d.fwdUp, 'sector-arr')}
      </a>`
    )
    .join('\n      ');

  const trustIcons = ['shield-check', 'seal-check', 'file-text', 'hand-heart'];
  const trust = c.trust
    .map(
      (x, i) => `<div class="trust-item">
        ${icon('duo:' + trustIcons[i])}
        <div><strong>${esc(x.title)}</strong><span>${esc(x.text)}</span></div>
      </div>`
    )
    .join('\n      ');

  const serviceIcons = ['house-line', 'buildings', 'arrows-left-right', 'calendar-check'];
  const services = c.services.items
    .map(
      (s, i) => `<div class="svc">
          <span class="svc-ic"><span class="svc-ic-frame"></span>${icon('duo:' + serviceIcons[i])}</span>
          <h3>${esc(s.title)}</h3>
          <p>${esc(s.text)}</p>
          <a href="#">${esc(c.services.more)}${icon(d.fwd)}</a>
        </div>`
    )
    .join('\n        ');

  const offers = c.offer.items
    .map(
      (o, i) => `<div class="offer-item">
          <span class="offer-n" aria-hidden="true">0${i + 1}</span>
          <div><h3>${esc(o.title)}</h3><p>${esc(o.text)}</p></div>
        </div>`
    )
    .join('\n        ');

  const jobs = c.jobs.items
    .map(
      (name, i) => `<div class="job">
          <div class="job-img"><img src="${root}assets/img/job-${i + 1}.webp" width="${JOB_SIZES[i][0]}" height="${JOB_SIZES[i][1]}" alt="${esc(name)}" loading="lazy"></div>
          <div class="job-cap">
            <h3>${esc(name)}</h3>
            <a href="cvs.html">${esc(c.jobs.link)}${icon(d.fwd)}</a>
          </div>
        </div>`
    )
    .join('\n        ');

  const countries = countryCodes
    .map(
      cc => `<div class="ctry">
          <span class="ctry-ring"><span><img src="${root}assets/flags/${cc}.svg" alt="${esc(t.countries[cc])}" width="156" height="156" loading="lazy"></span></span>
          <span class="ctry-name"><strong>${esc(t.countries[cc])}</strong><span class="dia6"></span></span>
        </div>`
    )
    .join('\n        ');

  const stepIcons = ['identification-card', 'chats-circle', 'signature', 'airplane-tilt', 'house-line'];
  const stepsH = c.steps.items
    .map(
      (s, i) => `<div class="step">
            <span class="step-c"><span class="step-in">${icon('duo:' + stepIcons[i])}</span><span class="step-n">${i + 1}</span></span>
            <h3>${esc(s.title)}</h3>
            <p>${esc(s.text)}</p>
          </div>`
    )
    .join('\n          ');
  const stepsV = c.steps.items
    .map(
      (s, i, a) => `<div class="stepv">
            <div class="stepv-l">
              <span class="stepv-c">${icon('duo:' + stepIcons[i])}<span class="stepv-n">${i + 1}</span></span>${i < a.length - 1 ? '<span class="stepv-line"></span>' : ''}
            </div>
            <div class="stepv-r"><h3>${esc(s.title)}</h3><p>${esc(s.text)}</p></div>
          </div>`
    )
    .join('\n          ');

  const why = c.why.items
    .map(
      w => `<div class="why-item">
          <span class="why-chk"><span class="why-chk-dia"></span>${icon('check')}</span>
          <p>${esc(w)}</p>
        </div>`
    )
    .join('\n        ');

  const faqs = c.faq.items
    .map(
      (f, i) => `<div class="faq-item">
          <button class="faq-q" type="button" id="faq-q${i}" aria-expanded="${i === 0}" aria-controls="faq-a${i}">${esc(f.q)}<span class="faq-ic">${icon('minus', 'ic-minus')}${icon('plus', 'ic-plus')}</span></button>
          <p class="faq-a" id="faq-a${i}" role="region" aria-labelledby="faq-q${i}"${i === 0 ? '' : ' hidden'}>${esc(f.a)}</p>
        </div>`
    )
    .join('\n        ');

  const k = c.contact;
  const list = k.list;
  const contacts = [
    ['headset', list.customerService, contact.phone, `tel:${contact.phone}`],
    ['whatsapp-logo', list.whatsapp, contact.phone, whatsappUrl],
    ['phone', list.management, `${contact.management[0]} • ${contact.management[1]}`, `tel:${contact.management[0]}`],
    ['envelope-simple', list.email, contact.email, `mailto:${contact.email}`],
    ['map-pin', list.address, list.addressValue, '#'],
    ['clock', list.hours, list.hoursValue, '#']
  ]
    .map(
      ([ic, label, value, href]) => `<a class="cl" href="${href}">
            ${icon('duo:' + ic)}
            <span class="cl-txt"><span class="cl-label">${esc(label)}</span><span class="cl-value">${esc(value)}</span></span>
          </a>`
    )
    .join('\n          ');

  const body = `
<section class="hero" data-hero>
  <div class="hero-box">
    <div class="hero-frame"></div>
    <div class="hero-grid">
      <div class="hero-txt">
        ${slides}
        <div class="hero-ctrl">
          <button class="hero-arr" type="button" data-prev aria-label="${esc(c.prev)}">${icon(d.back)}</button>
          <div class="hero-dots">${dots}</div>
          <button class="hero-arr" type="button" data-next aria-label="${esc(c.next)}">${icon(d.fwd)}</button>
        </div>
      </div>
      <div class="hero-media">
        <div class="hero-arch">
          <div class="hero-arch-line"></div>
          <div class="hero-arch-img">
              ${heroImgs}
          </div>
          <div class="seal">
            <div class="seal-in">
              <span class="seal-a">${esc(c.sealTop)}</span>
              <span class="seal-n">${esc(c.sealNumber)}</span>
              <span class="seal-b">${esc(c.sealBottom)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sectors">
  <div class="sectors-grid">
      ${sectors}
  </div>
</section>

<section class="trust">
  <div class="trust-grid">
      ${trust}
  </div>
</section>

<section id="about" class="home-about">
  <div class="about-grid">
    <div class="about-arch">
      <div class="about-arch-line"></div>
      <div class="about-arch-img"><img src="${root}assets/img/about-team.webp" width="960" height="960" alt="${esc(c.about.imageAlt)}" loading="lazy"></div>
    </div>
    <div class="about-txt">
      <span class="eyebrow"><span class="dia"></span>${esc(c.about.eyebrow)}</span>
      <h2 class="h2 h2-lh">${esc(c.about.title)}</h2>
      <p class="about-p">${esc(c.about.text)}</p>
      <div class="btn-row">
        <a class="btn-petrol" href="about.html">${esc(c.about.more)}${icon(d.fwd)}</a>
        <a class="btn-outline" href="#">${icon('duo:file-pdf')}${esc(t.common.profile)}</a>
      </div>
    </div>
  </div>
</section>

<section id="services" class="services">
  <div class="wrap-col services-in">
    <div class="shead">
      ${ornament()}
      <h2 class="h2">${esc(c.services.title)}</h2>
      <p>${esc(c.services.text)}</p>
    </div>
    <div class="svc-grid">
        ${services}
    </div>
  </div>
</section>

<section class="offer">
  <img class="offer-wm" src="${root}assets/img/logo-gold-840.webp" alt="" width="519" height="840" loading="lazy">
  <div class="offer-in">
    <div class="shead shead-dark">
      ${ornament()}
      <h2 class="h2">${esc(c.offer.title)}</h2>
      <p>${esc(c.offer.text)}</p>
    </div>
    <div class="offer-grid">
        ${offers}
    </div>
  </div>
</section>

<section id="jobs" class="jobs">
  <div class="wrap-col jobs-in">
    <div class="jobs-head">
      <div>
        ${ornament('start')}
        <h2 class="h2">${esc(c.jobs.title)}</h2>
        <p class="sub">${esc(c.jobs.text)}</p>
      </div>
    </div>
    <div class="jobs-grid">
        ${jobs}
    </div>
  </div>
</section>

<section id="countries" class="countries">
  <div class="wrap-col countries-in">
    <div class="shead">
      ${ornament()}
      <h2 class="h2">${esc(c.countries.title)}</h2>
      <p>${esc(c.countries.text)}</p>
    </div>
    <div class="ctry-wrap">
        ${countries}
    </div>
  </div>
</section>

<section class="steps">
  <div class="wrap-col steps-in">
    <div class="shead">
      ${ornament()}
      <h2 class="h2">${esc(c.steps.title)}</h2>
      <p>${esc(c.steps.text)}</p>
    </div>
    <div class="steps-h">
      <div class="steps-line"></div>
          ${stepsH}
    </div>
    <div class="steps-v">
          ${stepsV}
    </div>
  </div>
</section>

<section class="why">
  <div class="why-grid">
    <div class="why-head">
      ${ornament('start')}
      <h2 class="h2 h2-lh">${esc(c.why.title)}</h2>
      <img class="why-logo" src="${root}assets/img/logo-gold-840.webp" alt="${esc(t.header.logoAlt)}" width="519" height="840" loading="lazy">
    </div>
    <div class="why-list">
        ${why}
    </div>
  </div>
</section>

<section id="faq" class="faq">
  <div class="faq-in">
    <div class="shead">
      ${ornament()}
      <h2 class="h2">${esc(c.faq.title)}</h2>
    </div>
    <div class="faq-list" data-faq>
        ${faqs}
    </div>
    <a class="btn-all" href="#">${esc(c.faq.all)}${icon(d.fwd)}</a>
  </div>
</section>

<section class="cta">
  <div class="cta-box">
    <div class="cta-frame"></div>
    <div class="cta-txt">
      <h2 class="h2">${esc(t.common.ctaTitle)}</h2>
      <p>${esc(t.common.ctaText)}</p>
    </div>
    <div class="cta-btns">
      <a class="btn-wa" href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(t.common.whatsappBtn)}</a>
      <a class="btn-gold btn-gold-cta" href="tel:${contact.phone}">${icon('phone')}${esc(t.common.callUs)} <span dir="ltr">${contact.phone}</span></a>
    </div>
  </div>
</section>

<section id="contact" class="contact">
  <div class="wrap-col contact-in">
    <div class="contact-head">
      ${ornament('start')}
      <h2 class="h2">${esc(k.title)}</h2>
      <p class="sub">${esc(k.text)}</p>
    </div>
    <div class="contact-grid">
      <div class="form-card" data-form-wrap>
        <form class="cform" data-form="contact" data-wa-title="${esc(k.whatsappTitle)}">
          <label class="fl">${esc(k.name)}<input class="fi" required type="text" name="name" autocomplete="name" data-label="${esc(k.name)}"></label>
          <label class="fl">${esc(k.phone)}<input class="fi fi-tel" required type="tel" name="phone" placeholder="05xxxxxxxx" autocomplete="tel" inputmode="tel" pattern="[0-9+ \\-]{9,16}" title="${esc(t.phoneTitle)}" data-label="${esc(k.phone)}"></label>
          <label class="fl">${esc(k.email)}<input class="fi" type="email" name="email" autocomplete="email" data-label="${esc(k.email)}"></label>
          <label class="fl">${esc(k.service)}
            <select class="fs" required name="service" data-label="${esc(k.service)}">
              <option value="">${esc(k.servicePlaceholder)}</option>
              ${k.serviceOptions.map(o => `<option>${esc(o)}</option>`).join('')}
            </select>
          </label>
          <label class="fl fl-full">${esc(k.message)}<textarea class="ft" rows="5" name="message" data-label="${esc(k.message)}"></textarea></label>
          <button class="cform-submit" type="submit">${icon('paper-plane-tilt')}${esc(k.submit)}</button>
        </form>
        <div class="sent" data-sent hidden>
          <span class="sent-ic">${icon('duo:check-circle')}</span>
          <p role="status">${esc(k.success)}</p>
          <button class="sent-again" type="button" data-again>${esc(k.again)}</button>
        </div>
      </div>
      <div class="cl-list">
          ${contacts}
      </div>
    </div>
  </div>
</section>
`;

  const ld = {
    '@context': 'https://schema.org',
    '@type': 'EmploymentAgency',
    name: t.siteName,
    url: `${SITE_URL}/${L === 'en' ? 'en/' : ''}`,
    logo: `${SITE_URL}/assets/icons/icon-512.png`,
    image: `${SITE_URL}/assets/img/og-image.jpg`,
    description: t.meta.home.description,
    telephone: '+966533420302',
    email: contact.email,
    areaServed: 'SA'
  };
  const extraHead = `<link rel="preload" as="image" href="${root}assets/img/hero-1.webp" imagesrcset="${root}assets/img/hero-1-480.webp 480w, ${root}assets/img/hero-1.webp 848w" imagesizes="(max-width: 520px) 92vw, 460px" fetchpriority="high">
<script type="application/ld+json">${JSON.stringify(ld)}</script>
`;

  return layout(ctx, { body, active: 'home', extraHead });
}
