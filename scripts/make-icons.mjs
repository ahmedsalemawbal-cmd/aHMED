// Renders the PWA icons from the logo bars (Login.dc.html, tallest bar on the
// right as in RTL) using the
// pre-installed Chromium. Colours are the light-theme tokens from tokens.css.
import { writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

const t = { surface: '#f5f6f8', visited: '#0f766e', contacted: '#0369a1', replied: '#4338ca', proposal: '#b4430b', won: '#15703a' };
const bars = [[40, t.visited], [32, t.contacted], [25, t.replied], [18, t.proposal], [12, t.won]];

function svg(size, pad) {
  // 5 bars of 12 + 4 gaps of 4 = 76 wide, 40 high, in a 100-unit box
  const scale = (100 - pad * 2) / 76;
  const x0 = (100 - 76 * scale) / 2;
  const base = 50 + (40 * scale) / 2;
  const rects = bars
    .map(([h, c], i) => `<rect x="${(x0 + (bars.length - 1 - i) * 16 * scale).toFixed(2)}" y="${(base - h * scale).toFixed(2)}" width="${(12 * scale).toFixed(2)}" height="${(h * scale).toFixed(2)}" rx="${(4 * scale).toFixed(2)}" fill="${c}"/>`)
    .join('');
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${size}" height="${size}"><rect width="100" height="100" fill="${t.surface}"/>${rects}</svg>`;
}

writeFileSync('public/icons/icon.svg', svg(512, 18));
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH });
const page = await browser.newPage();
for (const [name, size, pad] of [['icon-192.png', 192, 18], ['icon-512.png', 512, 18], ['icon-maskable-512.png', 512, 28], ['apple-touch-icon.png', 180, 20]]) {
  await page.setViewportSize({ width: size, height: size });
  await page.setContent(`<html><body style="margin:0">${svg(size, pad)}</body></html>`);
  await page.screenshot({ path: `public/icons/${name}`, clip: { x: 0, y: 0, width: size, height: size } });
}
await browser.close();
console.log('icons written');
