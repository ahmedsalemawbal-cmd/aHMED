import { expect, test, type Page } from '@playwright/test';
import { ACTIVITIES, SERVICES } from './fixtures-catalog';
import { LEAD_ID, leadDb, leadHandler, type LeadDb } from './fixtures-lead';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

const PNG = Buffer.from('89504e470d0a1a0a0000000d4948445200000001000000010806000000', 'hex');

function handler(db: () => LeadDb, o: { rpcStatus?: () => number } = {}): Handler {
  const base = leadHandler(db);
  return (table, req, params) => {
    if (table === 'activity_types') return { body: ACTIVITIES };
    if (table === 'services') return { body: SERVICES };
    if (table === 'assessments') return { body: [{ answers: { g1: 'yes', s3: 'no', g3: 'no', web: 'no' }, score: 48, created_at: new Date().toISOString(), visit_id: 'v1', weaknesses: ['s3', 'g3', 'web'] }] };
    if (table === 'rpc/add_visit') {
      const status = o.rpcStatus?.() ?? 200;
      if (status !== 200) return { status };
      const p = (req.postDataJSON() as { p: { id: string; score: number } }).p;
      return { body: { lead_id: LEAD_ID, visit_id: p.id, score: p.score, priority: 'hot', stage: 'contacted', stage_before: 'contacted', task_id: null, existing: false } };
    }
    if (table === 'visit_media') return { status: 201, body: [] };
    return base(table, req, params);
  };
}

async function open(page: Page, h: Handler) {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  const uploads: string[] = [];
  await page.route('**/storage/v1/object/**', async (route) => {
    uploads.push(new URL(route.request().url()).pathname);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ Key: 'ok', Id: 'x' }) });
  });
  await page.goto(`/leads/${LEAD_ID}/visit`);
  return { log, uploads };
}

const tri = (page: Page, label: string) => page.locator('fieldset', { hasText: label });

test.describe('زيارة جديدة · 390', () => {
  test('three steps from the last answers to add_visit, then back to the lead', async ({ page }) => {
    const { log, uploads } = await open(page, handler(() => leadDb()));
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('زيارة مطعم ريدان');
    await expect(page.getByText('1 من 3')).toBeVisible();
    // the last assessment is the starting point
    await expect(page.getByRole('meter')).toHaveAttribute('aria-valuenow', '10');
    await tri(page, 'ينشر فيديو أو ريلز').locator('label', { hasText: 'نعم' }).click();
    await expect(page.getByRole('meter')).toHaveAttribute('aria-valuenow', '20');
    await expectTouchTargets(page);
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('ملاحظات الزيارة');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await expect(page.getByText('اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.')).toBeVisible();
    await page.getByLabel('الملاحظة الأبرز').fill('بدأوا ينشرون ريلز لكن بدون ردود على المراجعات');
    await page.locator('input[type=file]').setInputFiles({ name: 'menu.png', mimeType: 'image/png', buffer: PNG });
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('الخدمات المقترحة');
    await expect(page.getByText(/وبقيت الخدمات المقترحة من قبل لـمطعم ريدان/)).toBeVisible();
    await expectTouchTargets(page);
    await page.getByRole('button', { name: 'احفظ الزيارة' }).click();

    await expect(page.getByText('سُجّلت الزيارة')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}$`));
    const rpc = log.writes.find((w) => w.table === 'rpc/add_visit');
    const p = (rpc?.body as { p: Record<string, unknown> }).p;
    expect(p).toMatchObject({
      lead_id: LEAD_ID,
      key_observation: 'بدأوا ينشرون ريلز لكن بدون ردود على المراجعات',
      answers: { g1: 'yes', s3: 'yes', g3: 'no', web: 'no' },
      score: 20,
      priority: 'hot',
      expected_value: 2500,
      lat: null,
      lng: null,
    });
    expect(typeof p.id).toBe('string');
    expect(p.weaknesses).toEqual(expect.arrayContaining(['g3', 'web']));
    expect((p.services as { service_id: string }[]).map((s) => s.service_id)).toEqual(expect.arrayContaining(['s-maps', 's-video']));
    expect(uploads[0]).toMatch(new RegExp(`/visit-media/11111111-1111-4111-8111-111111111111/${String(p.id)}/[0-9a-f-]+\\.png$`));
  });

  test('the draft survives a reload and can be discarded', async ({ page }) => {
    await open(page, handler(() => leadDb()));
    await tri(page, 'ينشر فيديو أو ريلز').locator('label', { hasText: 'نعم' }).click();
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
    await page.getByLabel('الملاحظة الأبرز').fill('ملاحظة من الزيارة');
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('ملاحظات الزيارة');
    await expect(page.getByLabel('الملاحظة الأبرز')).toHaveValue('ملاحظة من الزيارة');
    await expect(page.getByText('أكملنا من المسودة')).toBeVisible();
    await page.getByRole('button', { name: 'ابدأ من جديد' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('زيارة مطعم ريدان');
  });

  test('a server error keeps the draft and retries with the same visit id', async ({ page }) => {
    let status = 500;
    const { log } = await open(page, handler(() => leadDb(), { rpcStatus: () => status }));
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
    await page.getByLabel('الملاحظة الأبرز').fill('ملاحظة');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await page.getByRole('button', { name: 'احفظ الزيارة' }).click();
    await expect(page.getByText('تعذّر حفظ الزيارة')).toBeVisible();
    status = 200;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByText('سُجّلت الزيارة')).toBeVisible();
    const ids = log.writes.filter((w) => w.table === 'rpc/add_visit').map((w) => (w.body as { p: { id: string } }).p.id);
    expect(ids).toHaveLength(2);
    expect(ids[0]).toBe(ids[1]);
  });

  test('offline save waits and completes when the network returns', async ({ page, context }) => {
    const { log } = await open(page, handler(() => leadDb()));
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
    await page.getByLabel('الملاحظة الأبرز').fill('ملاحظة');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await context.setOffline(true);
    await page.getByRole('button', { name: 'احفظ الزيارة' }).click();
    await expect(page.getByText('المسودة محفوظة. سنحفظ الزيارة تلقائياً عند عودة الشبكة.')).toBeVisible();
    expect(log.writes.filter((w) => w.table === 'rpc/add_visit')).toHaveLength(0);
    await context.setOffline(false);
    await expect(page.getByText('سُجّلت الزيارة')).toBeVisible();
  });
});
