import { expect, test, type Page } from '@playwright/test';
import { ACTIVITIES, SERVICES } from './fixtures-catalog';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

const NEW_ID = 'feed0000-0000-4000-8000-000000000001';
const PNG = Buffer.from('89504e470d0a1a0a0000000d4948445200000001000000010806000000', 'hex');

function handler(opts: { rpcStatus?: () => number } = {}): Handler {
  return (table, req) => {
    if (table === 'activity_types') return { body: ACTIVITIES };
    if (table === 'services') return { body: SERVICES };
    if (table === 'leads' && req.method() === 'GET') {
      return { body: [{ id: 'old-1', business_name: 'كافيه سحابة', phone_e164: '966500000001', lat: null, lng: null }] };
    }
    if (table === 'rpc/create_lead_from_visit') {
      const status = opts.rpcStatus?.() ?? 200;
      return status === 200 ? { body: { lead_id: NEW_ID, visit_id: 'visit-1', task_id: 'task-1', existing: false } } : { status };
    }
    if (table === 'visit_media') return { status: 201, body: [] };
    if (table === 'profiles') return { body: [{ full_name: 'أحمد', brand_name: null, weekly_visit_goal: 20 }] };
    return { body: [] };
  };
}

async function start(page: Page, h = handler()) {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  const uploads: string[] = [];
  await page.route('**/storage/v1/object/**', async (route) => {
    uploads.push(new URL(route.request().url()).pathname);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ Key: 'ok', Id: 'x' }) });
  });
  await page.goto('/leads/new');
  await expect(page.getByRole('heading', { level: 1, name: 'عميل جديد' })).toBeVisible();
  return { log, uploads };
}

async function fillStep1(page: Page) {
  await page.getByLabel('اسم المحل').fill('مطعم ريدان');
  await page.getByRole('button', { name: 'مطعم', exact: true }).click();
  await page.getByLabel('اسم المسؤول').fill('خالد العتيبي');
  await page.getByRole('radio', { name: 'مالك' }).click();
  await page.getByLabel('الجوال').fill('055 123 4567');
  await page.getByRole('switch', { name: /وافق على رسالة واتساب/ }).click();
  await page.getByRole('button', { name: 'المساء' }).click();
}

test.describe('عميل جديد · 390', () => {
  test.use({ permissions: ['geolocation'], geolocation: { latitude: 21.6012, longitude: 39.2034, accuracy: 12 } });

  test('full wizard: validation, live score, suggestions, save → message screen', async ({ page }) => {
    const { log, uploads } = await start(page);

    // step 1: validation says what to do
    await expect(page.getByText('البيانات', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await expect(page.getByText('اكتب اسم المحل.')).toBeVisible();
    await expect(page.getByText('اختر نوع النشاط.')).toBeVisible();

    await fillStep1(page);
    await expect(page.getByRole('button', { name: 'مطعم', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('radio', { name: 'مالك' })).toHaveAttribute('aria-checked', 'true');
    // keyboard moves the role (RTL: ArrowLeft = next)
    await page.getByRole('radio', { name: 'مالك' }).press('ArrowLeft');
    await expect(page.getByRole('radio', { name: 'مدير' })).toHaveAttribute('aria-checked', 'true');
    await page.getByRole('radio', { name: 'مدير' }).press('ArrowRight');
    await expect(page.getByText('أخذت موافقته شفهياً في الزيارة')).toBeVisible();

    await page.getByRole('button', { name: 'التقط موقعي' }).click();
    await expect(page.getByText('تم التقاط الموقع')).toBeVisible();
    await expect(page.getByText('الدقة ± 12 م')).toBeVisible();
    await expect(page.getByText('حُفظت كمسودة قبل لحظات')).toBeVisible();
    await expectTouchTargets(page);

    await page.getByRole('button', { name: 'التالي: التقييم' }).click();

    // step 2: live score in the header
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('تقييم مطعم ريدان');
    await expect(page.getByText('2 من 4')).toBeVisible();
    const meter = page.getByRole('meter');
    await expect(meter).toHaveAttribute('aria-valuenow', '0');
    await expect(page.getByText('الدرجة · 0 من 13 بنود')).toBeVisible();
    const tri = (label: string) => page.locator('fieldset', { hasText: label });
    await tri('التقييم 4.3 أو أعلى').locator('label', { hasText: 'نعم' }).click();
    await expect(meter).toHaveAttribute('aria-valuenow', '10');
    await tri('عدد مراجعات كافٍ').locator('label', { hasText: 'جزئي' }).click();
    await expect(meter).toHaveAttribute('aria-valuenow', '13'); // 12.5 → 13
    await tri('ينشر فيديو أو ريلز').locator('label', { hasText: 'لا' }).click();
    await tri('موقع أو صفحة هبوط').locator('label', { hasText: 'لا' }).click();
    await tri('يرد على المراجعات').locator('label', { hasText: 'لا' }).click();
    await expect(page.getByText('الدرجة · 5 من 13 بنود')).toBeVisible();
    await expect(page.getByText('بنود خاصة بنشاط «مطعم» · لا تدخل في الدرجة')).toBeVisible();
    await tri('منيو QR').locator('label', { hasText: 'نعم' }).click();
    await expect(meter).toHaveAttribute('aria-valuenow', '13'); // specific items never count
    await expect(page.getByText('8 من البنود بلا إجابة وتُحسب صفراً')).toBeVisible();
    await expectTouchTargets(page);
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();

    // step 3
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('ملاحظات الزيارة');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await expect(page.getByText('اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.')).toBeVisible();
    await page.getByLabel('الملاحظة الأبرز').fill('أطباقهم شكلها ممتاز لكن حسابهم في إنستقرام بدون أي فيديو');
    await expect(page.getByText('56/90')).toBeVisible();
    await expect(page.getByRole('button', { name: 'سجّل ملاحظة صوتية' })).toBeDisabled();
    await page.locator('input[type=file]').setInputFiles({ name: 'front.png', mimeType: 'image/png', buffer: PNG });
    await expect(page.getByText('ملف واحد · تُرفع عند الحفظ')).toBeVisible();
    await expectTouchTargets(page);
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();

    // step 4: suggested from weaknesses, first three pre-selected
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('الخدمات المقترحة');
    const cards = page.getByRole('group', { name: 'الخدمات' }).getByRole('checkbox');
    await expect(cards).toHaveCount(3);
    await expect(cards.nth(0)).toContainText('تصوير وإنتاج فيديو وريلز');
    await expect(cards.nth(0)).toContainText('لأنه: لا ينشر فيديو أو ريلز');
    await expect(cards.nth(0)).toHaveAttribute('aria-checked', 'true');
    await expect(cards.nth(1)).toContainText('صفحة هبوط');
    await expect(cards.nth(2)).toContainText('لأنه: لا يرد على مراجعات قوقل');
    await expect(page.getByLabel('القيمة المتوقعة')).toHaveValue('2300');
    await cards.nth(1).click();
    await expect(cards.nth(1)).toHaveAttribute('aria-checked', 'false');
    await expect(page.getByText('خدمتان محددتان · تدخل أول خدمتين في الرسالة')).toBeVisible();
    await expectTouchTargets(page);

    await page.getByRole('button', { name: 'احفظ وولّد الرسالة' }).click();
    await expect(page).toHaveURL(/\/leads\/feed0000-0000-4000-8000-000000000001\/message$/);

    const rpc = log.writes.find((w) => w.table === 'rpc/create_lead_from_visit');
    expect(rpc?.body).toMatchObject({
      p: {
        business_name: 'مطعم ريدان',
        activity_type_id: 'a-rest',
        contact_role: 'owner',
        phone_e164: '966551234567',
        is_decision_maker: true,
        best_contact_time: 'evening',
        wa_consent: true,
        lat: 21.6012,
        lng: 39.2034,
        score: 13,
        priority: 'hot',
        expected_value: 2300,
        key_observation: 'أطباقهم شكلها ممتاز لكن حسابهم في إنستقرام بدون أي فيديو',
        weaknesses: ['s3', 'web', 'g3', 'g2'], // «جزئي» is a weakness too, most points lost first
        services: [
          { service_id: 's-video', status: 'suggested' },
          { service_id: 's-landing', status: 'dropped' },
          { service_id: 's-maps', status: 'suggested' },
        ],
      },
    });
    expect(uploads).toHaveLength(1);
    expect(uploads[0]).toMatch(/\/visit-media\/11111111-1111-4111-8111-111111111111\/visit-1\/[0-9a-f-]+\.png$/);
    expect(log.writes.some((w) => w.table === 'visit_media')).toBe(true);
  });

  test('invalid and duplicate phone numbers are explained', async ({ page }) => {
    await start(page);
    const phone = page.getByLabel('الجوال');
    await phone.fill('055123');
    await phone.blur();
    await expect(page.getByText('الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05')).toBeVisible();
    await phone.fill('0500000001');
    await expect(page.getByText('هذا الرقم مسجّل لمحل آخر: كافيه سحابة. افتحه بدل إنشاء عميل جديد.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'افتح كافيه سحابة' })).toHaveAttribute('href', '/leads/old-1');
    await page.getByLabel('اسم المحل').fill('محل جديد');
    await page.getByRole('button', { name: 'أخرى', exact: true }).click();
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'عميل جديد' })).toBeVisible(); // blocked
    await page.getByRole('button', { name: 'محل آخر لنفس المالك، تابع' }).click();
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('تقييم محل جديد');
  });

  test('the draft survives a reload and can be discarded', async ({ page }) => {
    await start(page);
    await fillStep1(page);
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await page.locator('fieldset', { hasText: 'التقييم 4.3 أو أعلى' }).locator('label', { hasText: 'نعم' }).click();
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('تقييم مطعم ريدان');
    await expect(page.getByRole('meter')).toHaveAttribute('aria-valuenow', '10');
    await expect(page.getByText('أكملنا من المسودة')).toBeVisible();
    await page.getByRole('button', { name: 'ابدأ من جديد' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'عميل جديد' })).toBeVisible();
    await expect(page.getByLabel('اسم المحل')).toHaveValue('');
  });

  test('offline save waits and completes when the network returns', async ({ page, context }) => {
    const { log } = await start(page);
    await fillStep1(page);
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
    await page.getByLabel('الملاحظة الأبرز').fill('الطاولات ممتلئة بعد المغرب');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await context.setOffline(true);
    await page.getByRole('button', { name: 'احفظ وولّد الرسالة' }).click();
    await expect(page.getByText('المسودة محفوظة. سنحفظ العميل تلقائياً عند عودة الشبكة.')).toBeVisible();
    await expect(page.getByText('بدون اتصال. سنحفظ العميل عند عودة الشبكة.')).toBeVisible();
    expect(log.writes.filter((w) => w.table === 'rpc/create_lead_from_visit')).toHaveLength(0);
    await context.setOffline(false);
    await expect(page).toHaveURL(/\/message$/);
    expect(log.writes.filter((w) => w.table === 'rpc/create_lead_from_visit')).toHaveLength(1);
  });

  test('server error: says what happened, keeps the draft, retries with the same id', async ({ page }) => {
    let fail = true;
    const { log } = await start(page, handler({ rpcStatus: () => (fail ? 500 : 200) }));
    await fillStep1(page);
    await page.getByRole('button', { name: 'التالي: التقييم' }).click();
    await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
    await page.getByLabel('الملاحظة الأبرز').fill('ملاحظة');
    await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
    await page.getByRole('button', { name: 'احفظ وولّد الرسالة' }).click();
    await expect(page.getByRole('alert')).toContainText('تعذّر حفظ العميل');
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page).toHaveURL(/\/message$/);
    const calls = log.writes.filter((w) => w.table === 'rpc/create_lead_from_visit');
    expect(calls.length).toBeGreaterThanOrEqual(2);
    const ids = new Set(calls.map((c) => (c.body as { p: { id: string } }).p.id));
    expect(ids.size).toBe(1); // idempotent retry
  });

  test('catalogue loading and error states', async ({ page }) => {
    let fail = true;
    await fakeSignIn(page);
    await mockRest(page, (table, req) => (table === 'activity_types' && fail ? { status: 500 } : handler()(table, req, new URLSearchParams())));
    await page.goto('/leads/new');
    await expect(page.getByRole('alert')).toContainText('تعذّر تحميل الأنشطة والخدمات');
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('button', { name: 'مطعم', exact: true })).toBeVisible();
  });
});
