// Phone, iPad and desktop: the flows keep the desktop sidebar and use the
// screen; phones and portrait tablets get the focused full-screen form.
import { expect, test, type Page } from '@playwright/test';
import { ACTIVITIES, SERVICES } from './fixtures-catalog';
import { LEAD_ID, leadDb, leadHandler } from './fixtures-lead';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

const handler: Handler = (table, req, params) => {
  if (table === 'activity_types') return { body: ACTIVITIES };
  if (table === 'services') return { body: SERVICES };
  return leadHandler(() => leadDb())(table, req, params);
};

async function at(page: Page, width: number, height: number, path: string) {
  await page.setViewportSize({ width, height });
  await fakeSignIn(page);
  await mockRest(page, handler);
  await page.goto(path);
}

const sidebar = (page: Page) => page.getByRole('navigation', { name: 'الأقسام' });
const bottomNav = (page: Page) => page.getByRole('navigation', { name: 'التنقل الرئيسي' });

async function noHorizontalScroll(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
}

for (const path of ['/leads/new', `/leads/${LEAD_ID}/edit`, `/leads/${LEAD_ID}/visit`, `/leads/${LEAD_ID}/message`]) {
  test(`desktop 1440 keeps the sidebar and a wide card: ${path}`, async ({ page }) => {
    await at(page, 1440, 1000, path);
    await expect(sidebar(page)).toBeVisible();
    const card = page.locator('[data-flow-card]');
    await expect(card).toBeVisible();
    const box = await card.boundingBox();
    expect(box?.width ?? 0).toBeGreaterThan(700);
    await noHorizontalScroll(page);
  });
}

test('iPad landscape 1024: desktop layout with the sidebar', async ({ page }) => {
  await at(page, 1024, 768, '/leads/new');
  await expect(sidebar(page)).toBeVisible();
  await expect(page.locator('[data-flow-card]')).toBeVisible();
  await noHorizontalScroll(page);
});

test('iPad portrait 820: focused form centred at a readable width, no sidebar', async ({ page }) => {
  await at(page, 820, 1180, '/leads/new');
  await expect(sidebar(page)).toHaveCount(0);
  await expect(page.getByRole('heading', { level: 1, name: 'عميل جديد' })).toBeVisible();
  const name = await page.getByLabel('اسم المحل').boundingBox();
  expect(name?.width ?? 0).toBeGreaterThan(560);
  expect(name?.width ?? 0).toBeLessThanOrEqual(680);
  await noHorizontalScroll(page);
});

test('iPad portrait 820: list screens keep the bottom nav and a centred column', async ({ page }) => {
  await at(page, 820, 1180, '/leads');
  await expect(bottomNav(page)).toBeVisible();
  await expect(sidebar(page)).toHaveCount(0);
  const search = await page.getByRole('searchbox').first().boundingBox();
  expect(search?.width ?? 0).toBeLessThanOrEqual(760);
  await noHorizontalScroll(page);
});

test('phone 390: full-width focused form, no navigation bars', async ({ page }) => {
  await at(page, 390, 844, '/leads/new');
  await expect(sidebar(page)).toHaveCount(0);
  await expect(bottomNav(page)).toHaveCount(0);
  const name = await page.getByLabel('اسم المحل').boundingBox();
  expect(name?.width ?? 0).toBeGreaterThan(340);
  await noHorizontalScroll(page);
});
