import { expect, test, type Page } from '@playwright/test';
import { ACTIVITIES, SERVICES } from './fixtures-catalog';
import { LEAD_ID, leadDb, leadHandler, type LeadDb } from './fixtures-lead';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

const OTHER = { id: 'other-1', business_name: 'كافيه سحابة', phone_e164: '966500000001', lat: null, lng: null };

function handler(db: () => LeadDb, o: { leadStatus?: () => number; patchStatus?: number } = {}): Handler {
  const base = leadHandler(db, { leadStatus: o.leadStatus });
  return (table, req, params) => {
    if (table === 'activity_types') return { body: ACTIVITIES };
    if (table === 'services') return { body: SERVICES };
    if (table === 'leads' && req.method() === 'PATCH') return o.patchStatus ? { status: o.patchStatus } : { status: 200, body: [{ id: LEAD_ID }] };
    // the duplicate index has no id filter: the other shop is in it
    if (table === 'leads' && req.method() === 'GET' && !params.get('id')) return { body: [...db().leads, OTHER] };
    return base(table, req, params);
  };
}

async function open(page: Page, h: Handler) {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  await page.goto(`/leads/${LEAD_ID}/edit`);
  return log;
}

test.describe('تعديل البيانات · 390', () => {
  test('prefilled; only the changed columns are saved; back to the lead', async ({ page }) => {
    const log = await open(page, handler(() => leadDb()));
    await expect(page.getByRole('heading', { level: 1, name: 'تعديل البيانات' })).toBeVisible();
    await expect(page.getByLabel('اسم المحل')).toHaveValue('مطعم ريدان');
    await expect(page.getByLabel('الجوال')).toHaveValue('0551234567');
    await expect(page.getByRole('button', { name: 'مطعم', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('radio', { name: 'مالك' })).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByRole('button', { name: 'احفظ التعديلات' })).toBeDisabled();
    await expectTouchTargets(page);

    await page.getByLabel('اسم المسؤول').fill('خالد');
    await page.getByLabel('الجوال').fill('055 999 8877');
    await page.getByRole('switch', { name: /طلب عدم التواصل/ }).click();
    await page.getByLabel('حساب إنستقرام').fill('@raydan');
    await expect(page.getByText('تعديلات غير محفوظة')).toBeVisible();
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();

    await expect(page.getByText('حُفظت البيانات')).toBeVisible();
    await expect(page.getByText('أُلغيت المتابعات المفتوحة ولن يظهر زر الواتساب.')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}$`));
    const patch = log.writes.find((w) => w.table === 'leads' && w.method === 'PATCH');
    expect(patch?.body).toEqual({ contact_name: 'خالد', phone_e164: '966559998877', do_not_contact: true, instagram_url: 'https://instagram.com/raydan' });
    expect(patch?.params).toContain(`id=eq.${LEAD_ID}`);
  });

  test('validation says what to fix; a number used by another shop needs a decision', async ({ page }) => {
    const log = await open(page, handler(() => leadDb()));
    await page.getByLabel('اسم المحل').fill(' ');
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();
    await expect(page.getByText('اكتب اسم المحل.')).toBeVisible();
    await page.getByLabel('اسم المحل').fill('مطعم ريدان');
    await page.getByLabel('حساب إنستقرام').fill('not a link');
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();
    await expect(page.getByText('الرابط غير صحيح. الصق رابط الحساب أو اكتب اسم المستخدم مثل @riyadan.')).toBeVisible();
    // back to the saved link: not a change
    await page.getByLabel('حساب إنستقرام').fill('https://www.instagram.com/raydan.rest/');
    await page.getByLabel('الجوال').fill('0500000001');
    await expect(page.getByText('هذا الرقم مسجّل لمحل آخر: كافيه سحابة. افتحه بدل إنشاء عميل جديد.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'افتح كافيه سحابة' })).toHaveAttribute('href', '/leads/other-1');
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();
    expect(log.writes.filter((w) => w.method === 'PATCH')).toHaveLength(0);
    await page.getByRole('button', { name: 'محل آخر لنفس المالك، تابع' }).click();
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();
    await expect(page.getByText('حُفظت البيانات')).toBeVisible();
    expect(log.writes.find((w) => w.method === 'PATCH')?.body).toEqual({ phone_e164: '966500000001' });
  });

  test('leaving with unsaved changes asks first', async ({ page }) => {
    await open(page, handler(() => leadDb()));
    await page.getByLabel('العنوان').fill('شارع الأمير');
    await page.getByRole('button', { name: 'إغلاق' }).click();
    const sheet = page.getByRole('dialog', { name: 'تجاهل التعديلات؟' });
    await expect(sheet).toBeVisible();
    await expectTouchTargets(page, '[role=dialog]');
    await sheet.getByRole('button', { name: 'أكمل التعديل' }).click();
    await expect(page.getByLabel('العنوان')).toHaveValue('شارع الأمير');
    await page.getByRole('button', { name: 'إلغاء', exact: true }).click();
    await page.getByRole('dialog', { name: 'تجاهل التعديلات؟' }).getByRole('button', { name: 'تجاهل التعديلات' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}$`));
  });

  test('a failed save keeps the form and offers a retry', async ({ page }) => {
    await open(page, handler(() => leadDb(), { patchStatus: 500 }));
    await page.getByLabel('العنوان').fill('شارع الأمير');
    await page.getByRole('button', { name: 'احفظ التعديلات' }).click();
    await expect(page.getByText('تعذّر حفظ التعديلات')).toBeVisible();
    await expect(page.getByRole('button', { name: 'أعد المحاولة' })).toBeVisible();
    await expect(page.getByLabel('العنوان')).toHaveValue('شارع الأمير');
  });

  test('offline: the save waits for the network', async ({ page, context }) => {
    await open(page, handler(() => leadDb()));
    await page.getByLabel('العنوان').fill('شارع الأمير');
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await expect(page.getByText('بدون اتصال. احفظ عند عودة الشبكة.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'احفظ التعديلات' })).toBeDisabled();
    await context.setOffline(false);
    await expect(page.getByRole('button', { name: 'احفظ التعديلات' })).toBeEnabled();
  });

  test('load error with retry, and an unknown lead', async ({ page }) => {
    let fail = true;
    await open(page, handler(() => leadDb(), { leadStatus: () => (fail ? 500 : 200) }));
    await expect(page.getByText('تعذّر تحميل بيانات العميل')).toBeVisible();
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByLabel('اسم المحل')).toHaveValue('مطعم ريدان');

    await page.goto('/leads/feed0000-0000-4000-8000-0000000fffff/edit');
    await expect(page.getByText('لم نجد هذا العميل')).toBeVisible();
    await expect(page.getByRole('link', { name: 'ارجع للعملاء' })).toHaveAttribute('href', '/leads');
  });
});
