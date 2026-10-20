// Builds the static site into dist/: Arabic pages at the root, English pages under en/.
// Usage: node build.mjs   (optional env: SITE_URL, FORMSPREE_ID)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import ar from './src/data/content.ar.mjs';
import en from './src/data/content.en.mjs';
import { SITE_URL } from './src/data/site.mjs';
import { hash } from './src/lib.mjs';
import { PAGES, pageUrl } from './src/layout.mjs';
import { renderHome } from './src/pages/home.mjs';
import { renderAbout } from './src/pages/about.mjs';
import { renderCvs } from './src/pages/cvs.mjs';
import { renderRequest } from './src/pages/request.mjs';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const DIST = path.join(ROOT, 'dist');
const renderers = { home: renderHome, about: renderAbout, cvs: renderCvs, request: renderRequest };

const write = (rel, content) => {
  const file = path.join(DIST, rel);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, content);
};

fs.rmSync(DIST, { recursive: true, force: true });
fs.cpSync(path.join(ROOT, 'public'), DIST, { recursive: true });

const css = fs.readFileSync(path.join(ROOT, 'src/styles/site.css'), 'utf8');
const js = fs.readFileSync(path.join(ROOT, 'src/scripts/site.js'), 'utf8');
write('assets/css/site.css', css);
write('assets/js/site.js', js);
const version = { css: hash(css), js: hash(js) };

for (const t of [ar, en]) {
  const L = t.lang;
  for (const page of Object.keys(PAGES)) {
    const ctx = { t, L, page, root: L === 'en' ? '../' : '', version };
    write((L === 'en' ? 'en/' : '') + PAGES[page], renderers[page](ctx));
  }
}

const today = new Date().toISOString().slice(0, 10);
const urls = Object.keys(PAGES).flatMap(page => ['ar', 'en'].map(L => `  <url>
    <loc>${pageUrl(L, page)}</loc>
    <lastmod>${today}</lastmod>
    <xhtml:link rel="alternate" hreflang="ar" href="${pageUrl('ar', page)}"/>
    <xhtml:link rel="alternate" hreflang="en" href="${pageUrl('en', page)}"/>
  </url>`));
write('sitemap.xml', `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
${urls.join('\n')}
</urlset>
`);
write('robots.txt', `User-agent: *\nAllow: /\n\nSitemap: ${SITE_URL}/sitemap.xml\n`);
write('site.webmanifest', JSON.stringify({
  name: ar.siteName,
  short_name: ar.header.brandName,
  lang: 'ar',
  dir: 'rtl',
  start_url: './',
  display: 'standalone',
  background_color: '#F6F9FA',
  theme_color: '#0B4F6C',
  icons: [
    { src: 'assets/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
    { src: 'assets/icons/icon-512.png', sizes: '512x512', type: 'image/png' }
  ]
}, null, 2) + '\n');
// GitHub Pages: serve files as-is (no Jekyll processing).
write('.nojekyll', '');

console.log(`Built ${Object.keys(PAGES).length * 2} pages into dist/ (site URL: ${SITE_URL})`);
