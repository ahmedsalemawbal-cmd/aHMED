import { esc, icon, dirIcons } from './lib.mjs';
import { SITE_URL, FORMSPREE_ID, contact, whatsappUrl, social } from './data/site.mjs';

// Page links inside one language folder. Arabic lives at the site root, English under /en/.
export const PAGES = { home: 'index.html', about: 'about.html', cvs: 'cvs.html', request: 'request.html' };

export function pageUrl(L, page) {
  return `${SITE_URL}/${L === 'en' ? 'en/' : ''}${PAGES[page] === 'index.html' ? '' : PAGES[page]}`;
}

function head(ctx, extraHead) {
  const { t, L, page, root, version } = ctx;
  const meta = t.meta[page];
  const other = L === 'ar' ? 'en' : 'ar';
  return `<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(meta.title)}</title>
<meta name="description" content="${esc(meta.description)}">
<link rel="canonical" href="${pageUrl(L, page)}">
<link rel="alternate" hreflang="ar" href="${pageUrl('ar', page)}">
<link rel="alternate" hreflang="en" href="${pageUrl('en', page)}">
<link rel="alternate" hreflang="x-default" href="${pageUrl('ar', page)}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="${esc(t.siteName)}">
<meta property="og:title" content="${esc(meta.title)}">
<meta property="og:description" content="${esc(meta.description)}">
<meta property="og:url" content="${pageUrl(L, page)}">
<meta property="og:image" content="${SITE_URL}/assets/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="${t.locale}">
<meta property="og:locale:alternate" content="${other === 'en' ? 'en_US' : 'ar_SA'}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0B4F6C">
<link rel="icon" type="image/png" sizes="32x32" href="${root}assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="${root}assets/icons/apple-touch-icon.png">
<link rel="manifest" href="${root}site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="${root}assets/css/site.css?v=${version.css}">
${extraHead || ''}</head>`;
}

function header(ctx, active) {
  const { t, L, page, root } = ctx;
  const h = t.header;
  const d = dirIcons(L);
  const other = L === 'ar' ? `en/${PAGES[page]}` : `../${PAGES[page]}`;
  const nav = [
    ['home', h.nav.home, 'index.html'],
    ['about', h.nav.about, 'about.html'],
    ['services', h.nav.services, 'index.html#services', true],
    ['countries', h.nav.countries, 'index.html#countries'],
    ['faq', h.nav.faq, 'index.html#faq'],
    ['contact', h.nav.contact, 'index.html#contact']
  ];
  const services = [
    [h.services[0], 'house-line'],
    [h.services[1], 'buildings'],
    [h.services[2], 'arrows-left-right'],
    [h.services[3], 'calendar-check']
  ];
  const ddLinks = services
    .map(([label, ic]) => `<a class="dd-link" href="index.html#services">${icon(ic)}${esc(label)}</a>`)
    .join('');
  const navItems = nav
    .map(([key, label, href, hasMenu]) => {
      const on = key === active;
      return `<div class="nav-item${hasMenu ? ' has-dd' : ''}">
          <a class="nav-link" href="${href}"${on ? ' aria-current="page"' : ''}>${esc(label)}${hasMenu ? icon('caret-down') : ''}</a>${on ? '<span class="nav-active" aria-hidden="true"></span>' : ''}${
            hasMenu ? `<div class="dd"><div class="dd-box">${ddLinks}</div></div>` : ''
          }
        </div>`;
    })
    .join('\n        ');
  const mobileLinks = nav
    .map(([, label, href]) => `<a class="mnav-link" href="${href}">${esc(label)}${icon(d.caret)}</a>`)
    .join('');
  const mobileServices = services
    .map(([label, ic]) => `<a href="index.html#services">${icon(ic)}${esc(label)}</a>`)
    .join('');
  const socialLinks = social
    .map(s => `<a href="${s.href}" aria-label="${s.label}">${icon(s.icon)}</a>`)
    .join('');
  const subLang = L === 'ar' ? ' lang="en"' : ' lang="ar"';

  return `<header class="hdr">
  <div class="topbar">
    <div class="topbar-in">
      <div class="topbar-info">
        <span class="tb-item">${icon('map-pin')}${esc(h.address)}</span>
        <a class="tb-item" href="mailto:${contact.email}">${icon('envelope-simple')}${contact.email}</a>
        <a class="tb-item" href="tel:${contact.phone}">${icon('phone')}${esc(h.customerService)} <span dir="ltr">${contact.phone}</span></a>
      </div>
      <div class="topbar-social">${socialLinks}</div>
    </div>
  </div>
  <div class="hbar">
    <div class="hbar-in">
      <a class="brand" href="index.html">
        <img src="${root}assets/img/logo-gold-192.webp" alt="${esc(h.logoAlt)}" width="119" height="192">
        <span class="brand-txt"><span class="brand-name">${esc(h.brandName)}</span><span class="brand-sub"${subLang}>${esc(h.brandSub)}</span></span>
      </a>
      <nav class="nav" aria-label="${esc(h.menu)}">
        ${navItems}
      </nav>
      <div class="hact">
        <div class="lang" role="group" aria-label="${esc(h.langLabel)}">
          <a href="${L === 'ar' ? PAGES[page] : other}" lang="ar" hreflang="ar"${L === 'ar' ? ' class="on" aria-current="true"' : ''}>${h.langAr}</a>
          <a href="${L === 'en' ? PAGES[page] : other}" lang="en" hreflang="en"${L === 'en' ? ' class="on" aria-current="true"' : ''}>${h.langEn}</a>
        </div>
        <a class="hcta" href="request.html">${icon('file-plus')}<span class="hcta-txt">${esc(h.cta)}</span></a>
        <button class="menu-btn" type="button" aria-label="${esc(h.menu)}" aria-expanded="false" aria-controls="mnav">${icon('list', 'ic-open')}${icon('x', 'ic-close')}</button>
      </div>
    </div>
  </div>
  <div class="mnav" id="mnav" hidden>
    ${mobileLinks}
    <div class="mnav-svc">${mobileServices}</div>
  </div>
</header>`;
}

function footer(ctx) {
  const { t, root } = ctx;
  const f = t.footer;
  const h = t.header;
  const socialLinks = social
    .map(s => `<a href="${s.href}" aria-label="${s.label}">${icon(s.icon)}</a>`)
    .join('');
  const quick = [
    [f.links.home, 'index.html'],
    [f.links.about, 'about.html'],
    [f.links.services, 'index.html#services'],
    [f.links.countries, 'index.html#countries'],
    [f.links.request, 'request.html'],
    [f.links.faq, 'index.html#faq'],
    [f.links.contact, 'index.html#contact']
  ]
    .map(([label, href]) => `<a class="ftr-a" href="${href}">${esc(label)}</a>`)
    .join('');
  const services = h.services.map(s => `<a class="ftr-a" href="index.html#services">${esc(s)}</a>`).join('');

  return `<footer class="ftr">
  <div class="ftr-grid">
    <div class="ftr-brand">
      <div class="ftr-logo">
        <img src="${root}assets/img/logo-gold-192.webp" alt="${esc(h.logoAlt)}" width="119" height="192" loading="lazy">
        <span>${esc(f.brand)}</span>
      </div>
      <p>${esc(f.about)}</p>
      <div class="ftr-soc">${socialLinks}</div>
    </div>
    <div class="ftr-col">
      <h2 class="ftr-h">${esc(f.quickLinks)}</h2>
      <div class="ftr-links2">${quick}</div>
    </div>
    <div class="ftr-col">
      <h2 class="ftr-h">${esc(f.servicesTitle)}</h2>
      <div class="ftr-links">${services}</div>
    </div>
    <div class="ftr-col">
      <h2 class="ftr-h">${esc(f.contactTitle)}</h2>
      <div class="ftr-contact">
        <a href="tel:${contact.phone}">${icon('headset')}${esc(f.customerService)} <span dir="ltr">${contact.phone}</span></a>
        <a href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(f.whatsapp)} <span dir="ltr">${contact.phone}</span></a>
        <span>${icon('phone')}${esc(f.management)} <span dir="ltr">${contact.management[0]}</span> • <span dir="ltr">${contact.management[1]}</span></span>
        <a class="ftr-mail" href="mailto:${contact.email}">${icon('envelope-simple')}${contact.email}</a>
        <span>${icon('map-pin')}${esc(f.address)}</span>
      </div>
    </div>
  </div>
  <div class="ftr-bottom">
    <div class="ftr-bottom-in">
      <span>${esc(f.copyright)}</span>
      <div class="ftr-legal">
        <a href="#">${esc(f.privacy)}</a>
        <a href="#">${esc(f.terms)}</a>
        <span>${esc(f.cr)}</span>
        <span>${esc(f.license)}</span>
      </div>
    </div>
  </div>
  <a class="wa-float" href="${whatsappUrl}" aria-label="${esc(f.whatsappAria)}">${icon('whatsapp-logo')}</a>
  <div class="mbar">
    <a class="mbar-call" href="tel:${contact.phone}">${icon('phone')}${esc(f.mobileCall)}</a>
    <a class="mbar-wa" href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(f.mobileWhatsapp)}</a>
    <a class="mbar-book" href="request.html">${icon('calendar-plus')}${esc(f.mobileBook)}</a>
  </div>
</footer>`;
}

// Breadcrumb used at the top of the inner pages.
export function breadcrumb(ctx, current) {
  const d = dirIcons(ctx.L);
  return `<nav class="crumbs" aria-label="breadcrumb"><a href="index.html">${esc(ctx.t.common.home)}</a>${icon(d.caret)}<span aria-current="page">${esc(current)}</span></nav>`;
}

export function layout(ctx, { body, active, extraHead }) {
  const { t, root, version } = ctx;
  return `<!doctype html>
<html lang="${t.lang}" dir="${t.dir}">
${head(ctx, extraHead)}
<body data-formspree="${esc(FORMSPREE_ID)}" data-whatsapp="${contact.whatsapp}">
<div class="page">
${header(ctx, active)}
<main id="main">
${body}
</main>
${footer(ctx)}
</div>
<script src="${root}assets/js/site.js?v=${version.js}" defer></script>
</body>
</html>
`;
}
