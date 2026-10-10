import { expect, test, type Page } from '@playwright/test';
import {
  DRAFT_BODY,
  LEAD_ID,
  TASK_F3,
  dayLong,
  leadDb,
  leadHandler,
  repliedDb,
  riyadhAt,
  riyadhDate,
  withDraft,
  type HandlerOpts,
  type LeadDb,
} from './fixtures-lead';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest } from './mock-supabase';

async function open(page: Page, db: LeadDb | (() => LeadDb) = leadDb(), o: HandlerOpts = {}, path = `/leads/${LEAD_ID}`) {
  await fakeSignIn(page);
  const get = typeof db === 'function' ? db : () => db;
  const log = await mockRest(page, leadHandler(get, o));
  await page.goto(path);
  return log;
}

function rpc(log: Awaited<ReturnType<typeof open>>, name: string) {
  return log.writes.find((w) => w.table === `rpc/${name}`)?.body;
}

const header = (page: Page) => page.locator('header').first();
const nextStep = (page: Page) => page.getByRole('region', { name: 'الخطوة القادمة' });
const tab = (page: Page, name: string) => page.getByRole('tab', { name });
const menu = (page: Page) => page.getByRole('button', { name: 'المزيد: تعديل البيانات، تغيير المرحلة' });

test.describe('صفحة العميل · 390', () => {
  test('overview matches Lead.dc.html: header, actions, next step, score, services, contact', async ({ page }) => {
    const now = new Date();
    await open(page);

    // header
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await expect(header(page)).toContainText('مطعم · حي الحمدانية · سُجّل اليوم');
    await expect(header(page).locator('.md-stage')).toHaveText('تم الإرسال');
    await expect(header(page).locator('.md-prio')).toHaveText('حار');
    await expect(page.getByRole('link', { name: 'رجوع إلى العملاء' })).toHaveAttribute('href', '/leads');
    await expect(menu(page)).toBeVisible();

    // action row: واتساب 2fr, اتصال, عرض سعر
    const wa = header(page).getByRole('button', { name: 'واتساب' });
    const call = header(page).getByRole('link', { name: 'اتصال' });
    const quote = header(page).getByRole('link', { name: 'عرض سعر' });
    await expect(call).toHaveAttribute('href', 'tel:+966551234567');
    await expect(quote).toHaveAttribute('href', `/leads/${LEAD_ID}/quotes/new`);
    const [bw, bc, bq] = await Promise.all([wa.boundingBox(), call.boundingBox(), quote.boundingBox()]);
    expect((bw?.width ?? 0) / (bc?.width ?? 1)).toBeGreaterThan(1.8);
    expect(Math.round(bc?.width ?? 0)).toBe(Math.round(bq?.width ?? 0));
    // RTL: WhatsApp first, on the right
    expect((bw?.x ?? 0) > (bq?.x ?? 0)).toBe(true);

    // next step: the first follow-up in 3 days
    await expect(nextStep(page)).toContainText('متابعة أولى: أرسل عينة الريل');
    await expect(nextStep(page)).toContainText(`${dayLong(riyadhAt(now, 3, 19))} · بعد 3 أيام`);
    await expect(nextStep(page).getByRole('button', { name: 'جهّز رسالة المتابعة' })).toBeVisible();

    // tabs
    await expect(page.getByRole('tab')).toHaveText(['نظرة عامة', 'الخط الزمني', 'الرسائل', 'المهام', 'العروض']);
    await expect(tab(page, 'نظرة عامة')).toHaveAttribute('aria-selected', 'true');

    // overview
    const meter = page.getByRole('meter', { name: 'درجة الحضور الرقمي' });
    await expect(meter).toHaveAttribute('aria-valuenow', '48');
    await expect(page.getByRole('tabpanel')).toContainText('48 / 100 · فرصة عالية');
    await expect(page.getByRole('list', { name: 'نقاط الضعف' }).getByRole('listitem')).toHaveText(['لا ينشر فيديو أو ريلز', 'لا يرد على مراجعات قوقل', 'بدون رابط طلب أو حجز']);
    const services = page.getByRole('region', { name: 'الخدمات المقترحة' });
    await expect(services).toContainText('القيمة المتوقعة 2,500 ر.س شهرياً');
    await expect(services.getByRole('listitem')).toHaveText(['إدارة ملف خرائط قوقل والمراجعات', 'تصوير وإنتاج فيديو وريلز']);
    const contact = page.getByRole('region', { name: 'المسؤول' });
    await expect(contact).toContainText('خالد العتيبي · مالك');
    await expect(contact.locator('[dir=ltr]').first()).toHaveText('055 123 4567');
    await expect(contact).toContainText('المساء');
    await expect(contact.getByText('وافق شفهياً في الزيارة')).toBeVisible();
    await expect(contact.getByRole('link', { name: 'instagram.com/raydan.rest' })).toHaveAttribute('href', 'https://www.instagram.com/raydan.rest/');
    await expect(page.getByRole('button', { name: 'العميل رد' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'زيارة جديدة' })).toHaveAttribute('href', `/leads/${LEAD_ID}/visit`);

    // full-screen page: no bottom nav, no sideways scroll, 48px targets
    await expect(page.getByRole('navigation', { name: 'التنقل الرئيسي' })).toHaveCount(0);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
    await expectTouchTargets(page);
  });

  test('tabs: click and RTL arrow keys, kept in the URL; timeline newest first', async ({ page }) => {
    await open(page);
    await tab(page, 'الخط الزمني').click();
    await expect(tab(page, 'الخط الزمني')).toHaveAttribute('aria-selected', 'true');
    await expect(page).toHaveURL(/\?tab=timeline$/);

    const items = page.getByRole('list', { name: 'الخط الزمني' }).getByRole('listitem');
    await expect(items).toHaveCount(4);
    await expect(items.nth(0)).toContainText('اليوم 11:21 ص');
    await expect(items.nth(0)).toContainText('أُنشئت متابعتان');
    await expect(items.nth(1)).toContainText('اليوم 11:20 ص');
    await expect(items.nth(1)).toContainText('تم الإرسال · الرسالة الأولى');
    await expect(items.nth(1)).toContainText('عبر واتساب · نبرة ودّية');
    await expect(items.nth(2)).toContainText('اليوم 10:45 ص');
    await expect(items.nth(2)).toContainText('تمت الزيارة');
    await expect(items.nth(2)).toContainText('الدرجة 48 · 3 ملفات');
    await expect(items.nth(3)).toContainText('اليوم 10:30 ص');
    await expect(items.nth(3)).toContainText('سُجّل العميل');
    await expect(items.nth(3)).toContainText('من زيارة ميدانية · خالد العتيبي، مالك');
    // dots in the stage colours, neutral for bookkeeping
    const tones = await page.locator('[data-tone]').evaluateAll((els) => els.map((e) => e.getAttribute('data-tone')));
    expect(tones).toEqual(['neutral', 'contacted', 'visited', 'not_visited']);
    await expect(page.locator('[data-tone=contacted]')).toHaveClass(/bg-stage-contacted/);
    await expect(page.locator('[data-tone=neutral]')).toHaveClass(/bg-line-strong/);
    // the phone follows its design: no quoted text
    await expect(page.getByText('الملاحظة الأبرز')).toHaveCount(0);

    // keyboard: in RTL the left arrow goes to the next tab
    await tab(page, 'الخط الزمني').focus();
    await page.keyboard.press('ArrowLeft');
    await expect(tab(page, 'الرسائل')).toHaveAttribute('aria-selected', 'true');
    await expect(tab(page, 'الرسائل')).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect(tab(page, 'الخط الزمني')).toBeFocused();
    await page.keyboard.press('End');
    await expect(tab(page, 'العروض')).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tabpanel')).toContainText('لا عروض بعد');
    await expect(page.getByRole('tabpanel')).toContainText('أنشئ العرض من زر «عرض سعر» أعلى الصفحة، ويُبنى من الخدمات المقترحة.');
    await page.keyboard.press('Home');
    await expect(tab(page, 'نظرة عامة')).toHaveAttribute('aria-selected', 'true');
    // only one tab stop
    await expect(page.locator('[role=tab][tabindex="0"]')).toHaveCount(1);

    // the tab survives a reload
    await page.goto(`/leads/${LEAD_ID}?tab=tasks`);
    await expect(tab(page, 'المهام')).toHaveAttribute('aria-selected', 'true');
  });

  test('messages and tasks tabs: draft, done, postpone', async ({ page }) => {
    const now = new Date();
    const log = await open(page, withDraft(leadDb(now), now));
    await tab(page, 'الرسائل').click();
    const msgs = page.getByRole('list', { name: 'الرسائل' }).getByRole('listitem');
    await expect(msgs).toHaveCount(2);
    await expect(msgs.nth(0)).toContainText('المتابعة الأولى');
    await expect(msgs.nth(0)).toContainText('مسودة');
    await expect(msgs.nth(0)).toContainText(DRAFT_BODY.split('\n')[0] ?? '');
    await expect(msgs.nth(0).getByRole('link', { name: 'أكمل المسودة' })).toHaveAttribute('href', `/leads/${LEAD_ID}/message?task=${TASK_F3}`);
    await expect(msgs.nth(1)).toContainText('الرسالة الأولى');
    await expect(msgs.nth(1)).toContainText('أُرسلت اليوم 11:20 ص');
    await expect(msgs.nth(1).locator('p')).toHaveCSS('white-space', 'pre-line');
    await expectTouchTargets(page);

    await tab(page, 'المهام').click();
    const openTasks = page.getByRole('region', { name: 'مفتوحة · 2' });
    await expect(openTasks.getByRole('listitem')).toHaveCount(2);
    await expect(openTasks.getByRole('listitem').nth(0)).toContainText('متابعة أولى');
    await expect(openTasks.getByRole('listitem').nth(0)).toContainText(`${dayLong(riyadhAt(now, 3, 19))} · بعد 3 أيام`);
    await expect(openTasks.getByRole('listitem').nth(1)).toContainText('متابعة ثانية وأخيرة');
    const closed = page.getByRole('region', { name: 'منتهية · 1' });
    await expect(closed).toContainText('أرسل الرسالة الأولى');
    await expect(closed).toContainText('أُنجزت اليوم 11:20 ص');
    await expectTouchTargets(page);

    await page.getByRole('button', { name: 'تم: متابعة أولى' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'أُنجزت المهمة' })).toBeVisible();
    const done = log.writes.find((w) => w.method === 'PATCH' && w.table === 'tasks');
    expect(done?.params).toContain(`id=eq.${TASK_F3}`);
    expect((done?.body as { done_at: string | null }).done_at).not.toBeNull();

    await page.getByRole('button', { name: 'أجّل: متابعة ثانية وأخيرة' }).click();
    const sheet = page.getByRole('dialog', { name: 'أجّل المهمة' });
    await expect(sheet).toBeVisible();
    await expectTouchTargets(page, '[role=dialog]');
    await sheet.getByRole('button', { name: /^غداً/ }).click();
    await expect(page.getByRole('status').filter({ hasText: 'أُجّلت المهمة إلى' })).toBeVisible();
    const moved = log.writes.filter((w) => w.method === 'PATCH' && w.table === 'tasks').at(-1);
    expect(moved?.body).toHaveProperty('due_at');
  });

  test('next step opens the message screen of its task', async ({ page }) => {
    await open(page);
    await nextStep(page).getByRole('button', { name: 'جهّز رسالة المتابعة' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}/message\\?task=${TASK_F3}$`));
  });

  test('back: one step back in history, or to «العملاء» when opened directly', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    // as if the lead was opened from another screen of the app
    await page.evaluate(() => {
      window.history.pushState({ usr: null, key: 'from-list', idx: 1 }, '', window.location.href);
    });
    await page.getByRole('link', { name: 'رجوع إلى العملاء' }).click();
    await expect.poll(() => page.evaluate(() => (window.history.state as { idx?: number } | null)?.idx)).toBe(0);
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}$`));
    // opened directly: the link goes to the list
    await page.getByRole('link', { name: 'رجوع إلى العملاء' }).click();
    await expect(page).toHaveURL(/\/leads$/);
  });

  test('header WhatsApp opens the message of the next task', async ({ page }) => {
    await open(page);
    await header(page).getByRole('button', { name: 'واتساب' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}/message\\?task=${TASK_F3}$`));
  });

  test('«العميل رد»: confirm, RPC, toast', async ({ page }) => {
    const log = await open(page);
    await page.getByRole('button', { name: 'العميل رد' }).click();
    const sheet = page.getByRole('dialog', { name: 'هل رد العميل؟' });
    await expect(sheet).toContainText('عند التأكيد تصبح المرحلة «رد»، وتُلغى المتابعتان الباقيتان، وتُنشأ مهمة «حدد اجتماعاً».');
    await expectTouchTargets(page, '[role=dialog]');
    await sheet.getByRole('button', { name: 'نعم، رد العميل' }).click();
    await expect(page.getByText('المرحلة الآن: رد')).toBeVisible();
    await expect(page.getByText('أُلغيت المتابعات الباقية. حدد اجتماعاً.')).toBeVisible();
    await expect(sheet).toHaveCount(0);
    expect(rpc(log, 'mark_replied')).toEqual({ p: { lead_id: LEAD_ID } });
  });

  test('a failed action says so and keeps the sheet open', async ({ page }) => {
    await open(page, leadDb(), { rpcStatus: 500 });
    await page.getByRole('button', { name: 'العميل رد' }).click();
    const sheet = page.getByRole('dialog', { name: 'هل رد العميل؟' });
    await sheet.getByRole('button', { name: 'نعم، رد العميل' }).click();
    await expect(page.getByRole('alert')).toContainText('تعذّر تسجيل الرد');
    await expect(page.getByRole('alert')).toContainText('تأكد من الشبكة ثم أعد المحاولة.');
    await expect(sheet).toBeVisible();
  });

  test('meeting: Riyadh date and time from the next step', async ({ page }) => {
    const now = new Date();
    const log = await open(page, repliedDb(now));
    await expect(nextStep(page)).toContainText('حدد اجتماعاً');
    await nextStep(page).getByRole('button', { name: 'حدد اجتماعاً' }).click();
    const sheet = page.getByRole('dialog', { name: 'حدد اجتماعاً' });
    await expect(sheet.getByLabel('التاريخ')).toHaveValue(riyadhDate(riyadhAt(now, 1, 12)));
    await expect(sheet.getByLabel('الوقت')).toHaveValue('16:30');
    await expect(sheet.getByLabel('عنوان الاجتماع')).toHaveValue('اجتماع في المحل');
    await expectTouchTargets(page, '[role=dialog]');

    // a past time is refused
    await sheet.getByLabel('التاريخ').fill(riyadhDate(now));
    await sheet.getByLabel('الوقت').fill('00:00');
    await sheet.getByRole('button', { name: 'احفظ الموعد' }).click();
    await expect(sheet).toContainText('الموعد مضى. اختر وقتاً قادماً.');
    expect(rpc(log, 'set_meeting')).toBeUndefined();

    const day = riyadhAt(now, 2, 17, 15);
    await sheet.getByLabel('التاريخ').fill(riyadhDate(day));
    await sheet.getByLabel('الوقت').fill('17:15');
    await sheet.getByRole('button', { name: 'احفظ الموعد' }).click();
    await expect(page.getByText('حُدد الاجتماع')).toBeVisible();
    await expect(page.getByText(`${dayLong(day)} · 5:15 م`)).toBeVisible();
    expect(rpc(log, 'set_meeting')).toEqual({ p: { lead_id: LEAD_ID, at: day.toISOString(), title: 'اجتماع في المحل' } });
    expect(day.toISOString()).toMatch(/T14:15:00\.000Z$/);
  });

  test('won: value and billing through «تغيير المرحلة»', async ({ page }) => {
    const log = await open(page);
    await menu(page).click();
    await page.getByRole('dialog', { name: 'المزيد' }).getByRole('button', { name: 'تغيير المرحلة' }).click();
    await page.getByRole('dialog', { name: 'تغيير المرحلة' }).getByRole('listitem').filter({ hasText: 'تم الإغلاق' }).click();
    const sheet = page.getByRole('dialog', { name: 'تم الإغلاق' });
    const value = sheet.getByLabel('قيمة الصفقة');
    await expect(value).toHaveValue('2500');
    await expectTouchTargets(page, '[role=dialog]');
    await value.fill('ألفين');
    await sheet.getByRole('button', { name: 'احفظ الإغلاق' }).click();
    await expect(sheet).toContainText('اكتب قيمة الصفقة بالأرقام، مثل 2500.');
    await value.fill('3,000');
    await sheet.getByRole('radio', { name: 'مرة واحدة' }).click();
    await sheet.getByRole('button', { name: 'احفظ الإغلاق' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'تم الإغلاق' })).toContainText('3,000 ر.س مرة واحدة');
    expect(rpc(log, 'mark_won')).toEqual({ p: { lead_id: LEAD_ID, value: 3000, billing: 'one_time' } });
  });

  test('lost with a retry reminder', async ({ page }) => {
    const now = new Date();
    const log = await open(page);
    await menu(page).click();
    await page.getByRole('dialog', { name: 'المزيد' }).getByRole('button', { name: 'نقل إلى خسارة' }).click();
    const sheet = page.getByRole('dialog', { name: 'نقل مطعم ريدان إلى خسارة' });
    await expect(sheet).toBeVisible();
    await expectTouchTargets(page, '[role=dialog]');
    await sheet.getByText('لا يحتاج الآن').click();
    await sheet.getByLabel('ملاحظة').fill('يرجع بعد رمضان');
    await sheet.getByText('بعد شهر').click();
    await sheet.getByRole('button', { name: 'انقل إلى خسارة' }).click();
    const retry = riyadhAt(now, 30, 10);
    await expect(page.getByText('نُقل إلى خسارة')).toBeVisible();
    await expect(page.getByText(`تذكير «أعد المحاولة» يوم ${dayLong(retry)}.`)).toBeVisible();
    expect(rpc(log, 'mark_lost')).toEqual({ p: { lead_ids: [LEAD_ID], reason: 'not_now', note: 'يرجع بعد رمضان', retry_at: retry.toISOString() } });
  });

  test('«⋮» menu: direct stage change and the other actions', async ({ page }) => {
    const log = await open(page);
    await menu(page).click();
    const m = page.getByRole('dialog', { name: 'المزيد' });
    await expect(m.getByRole('button')).toHaveText(['تعديل البيانات', 'تغيير المرحلة', 'نقل إلى خسارة', 'زيارة جديدة', 'إلغاء']);
    await expectTouchTargets(page, '[role=dialog]');
    await m.getByRole('button', { name: 'تغيير المرحلة' }).click();
    const stages = page.getByRole('dialog', { name: 'تغيير المرحلة' });
    await expect(stages.getByRole('listitem').filter({ hasText: 'تم الإرسال' })).toBeDisabled();
    await expectTouchTargets(page, '[role=dialog]');
    await stages.getByRole('listitem').filter({ hasText: 'عرض سعر' }).click();
    await expect(page.getByText('المرحلة الآن: عرض سعر')).toBeVisible();
    await expect(stages).toHaveCount(0);
    expect(rpc(log, 'set_stage')).toEqual({ p: { lead_ids: [LEAD_ID], stage: 'proposal' } });

    await menu(page).click();
    await page.getByRole('dialog', { name: 'المزيد' }).getByRole('button', { name: 'تعديل البيانات' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}/edit$`));
  });

  test('do_not_contact: no WhatsApp, a short note instead', async ({ page }) => {
    await open(page, leadDb(new Date(), { do_not_contact: true }));
    await expect(page.getByRole('note')).toHaveText('طلب عدم التواصل. لا رسائل ولا متابعات.');
    await expect(page.getByRole('button', { name: 'واتساب' })).toHaveCount(0);
    await expect(header(page).getByRole('link', { name: 'اتصال' })).toBeVisible();
    await expect(page.getByRole('region', { name: 'المسؤول' })).toContainText('طلب عدم التواصل');
    // no message is prepared for this lead: the task can only be closed
    await expect(nextStep(page).getByRole('button', { name: 'جهّز رسالة المتابعة' })).toHaveCount(0);
    await expect(nextStep(page).getByRole('button', { name: 'تم' })).toBeVisible();
    await expectTouchTargets(page);
  });

  test('no phone: no call and no WhatsApp', async ({ page }) => {
    await open(page, leadDb(new Date(), { phone_e164: null }));
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await expect(page.getByRole('button', { name: 'واتساب' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'اتصال' })).toHaveCount(0);
    await expect(header(page).getByRole('link', { name: 'عرض سعر' })).toBeVisible();
    await expect(page.getByRole('note')).toContainText('لا يوجد رقم جوال');
    await expect(page.getByRole('region', { name: 'المسؤول' })).toContainText('لا يوجد');
  });

  test('won and lost leads: where they stand instead of a task', async ({ page }) => {
    const won = leadDb(new Date(), { stage: 'won', won_value: 2500, won_billing: 'monthly' });
    won.tasks = won.tasks.map((t) => (t.done_at ? t : { ...t, cancelled_at: new Date().toISOString() }));
    await open(page, won);
    await expect(nextStep(page)).toContainText('تم الإغلاق · 2,500 ر.س شهرياً');
    await expect(page.getByRole('button', { name: 'العميل رد' })).toHaveCount(0);

    const lost = leadDb(new Date(), { stage: 'lost', lost_reason: 'price', lost_note: 'السعر أعلى من ميزانيته' });
    lost.tasks = won.tasks;
    await mockRest(page, leadHandler(() => lost));
    await page.reload();
    await expect(nextStep(page)).toContainText('خسارة · السعر');
    await expect(nextStep(page)).toContainText('السعر أعلى من ميزانيته');
    await expect(nextStep(page).getByRole('button', { name: 'غيّر المرحلة' })).toBeVisible();
  });

  test('more than three weaknesses open on request; empty messages offer one', async ({ page }) => {
    const db = leadDb(new Date(), { instagram_url: '@raydan.rest' });
    db.assessments = [{ visit_id: 'v1', score: 48, weaknesses: ['s3', 'g3', 'web', 'ad', 'wa'], created_at: new Date().toISOString() }];
    db.messages = [];
    await open(page, db);
    const items = page.getByRole('list', { name: 'نقاط الضعف' }).getByRole('listitem');
    await expect(items).toHaveCount(3);
    const more = page.getByRole('button', { name: 'اعرض نقطتي ضعف أخريين' });
    await expect(more).toHaveAttribute('aria-expanded', 'false');
    await expectTouchTargets(page);
    await more.click();
    await expect(items).toHaveText(['لا ينشر فيديو أو ريلز', 'لا يرد على مراجعات قوقل', 'بدون رابط طلب أو حجز', 'لا يعلن حالياً', 'لا يستخدم واتساب أعمال']);
    await expect(more).toHaveCount(0);

    // a handle typed instead of a link still opens the account
    const contact = page.getByRole('region', { name: 'المسؤول' });
    await expect(contact.getByRole('link', { name: '@raydan.rest' })).toHaveAttribute('href', 'https://www.instagram.com/raydan.rest/');

    await tab(page, 'الرسائل').click();
    await expect(page.getByRole('tabpanel')).toContainText('لا رسائل بعد');
    await expect(page.getByRole('tabpanel').getByRole('link', { name: 'اكتب رسالة' })).toHaveAttribute('href', `/leads/${LEAD_ID}/message?task=${TASK_F3}`);
    await expectTouchTargets(page);
  });

  test('not found: says so with a way back', async ({ page }) => {
    await open(page, { ...leadDb(), leads: [] });
    await expect(page.getByRole('heading', { name: 'لم نجد هذا العميل' })).toBeVisible();
    await expectTouchTargets(page);
    await page.getByRole('link', { name: 'ارجع للعملاء' }).click();
    await expect(page).toHaveURL(/\/leads$/);
  });

  test('a cut link is «not found», not a load error to retry', async ({ page }) => {
    // PostgREST refuses a malformed uuid with 400
    await open(page, leadDb(), { leadStatus: () => 400 }, `/leads/${LEAD_ID.slice(0, 20)}`);
    await expect(page.getByRole('heading', { name: 'لم نجد هذا العميل' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'ارجع للعملاء' })).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
  });

  test('loading: skeletons in the shape of the page', async ({ page }) => {
    await open(page, leadDb(), { leadDelayMs: 2500 });
    await expect(page.getByLabel('جارٍ التحميل')).toBeVisible();
    await expect(page.getByRole('link', { name: 'رجوع إلى العملاء' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان', { timeout: 8000 });
  });

  test('error: says what happened and retries', async ({ page }) => {
    let fail = true;
    await open(page, leadDb(), { leadStatus: () => (fail ? 500 : 200) });
    await expect(page.getByRole('alert')).toContainText('تعذّر تحميل العميل');
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
  });

  test('offline: top bar appears and the page stays', async ({ page, context }) => {
    await open(page);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await expect(nextStep(page)).toBeVisible();
    await context.setOffline(false);
  });
});

test.describe('صفحة العميل · ديسكتوب 1440', () => {
  test.use({ viewport: { width: 1440, height: 1000 }, isMobile: false, hasTouch: false });

  test('matches DeskLead.dc.html: two columns, breadcrumb, timeline with quotes, postpone', async ({ page }) => {
    const log = await open(page);
    await expect(page.getByRole('navigation', { name: 'الأقسام' }).getByRole('link', { name: /العملاء/ })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('navigation', { name: 'مسار التنقل' })).toHaveText('العملاء / مطعم ريدان');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await expect(page.getByText('مطعم · حي الحمدانية · سُجّل من زيارة ميدانية اليوم 10:30 ص')).toBeVisible();
    const head = page.locator('header').filter({ has: page.getByRole('heading', { level: 1 }) });
    await expect(head.getByRole('button').or(head.getByRole('link').filter({ hasNotText: 'العملاء' }))).toHaveText(['واتساب', 'اتصال', 'عرض سعر', 'تغيير المرحلة', 'المزيد']);

    // two columns: the overview on the right (start), the tabs on the left
    const next = await nextStep(page).boundingBox();
    const tabs = await page.getByRole('tablist').boundingBox();
    expect(next && tabs && next.x > tabs.x + tabs.width - 1).toBe(true);
    expect(Math.abs((next?.y ?? 0) - (tabs?.y ?? 0))).toBeLessThan(40);
    await expect(page.getByRole('region', { name: 'التقييم' })).toContainText('48 / 100');
    await expect(page.getByRole('region', { name: 'الخدمات المقترحة' })).toContainText('تصوير وإنتاج فيديو وريلز');
    await expect(page.getByRole('region', { name: 'المسؤول' })).toContainText('وافق شفهياً في الزيارة');

    // timeline first, with the quoted observation and message
    await expect(page.getByRole('tab')).toHaveText(['الخط الزمني', 'الرسائل', 'المهام', 'العروض']);
    await expect(tab(page, 'الخط الزمني')).toHaveAttribute('aria-selected', 'true');
    const tl = page.getByRole('list', { name: 'الخط الزمني' });
    await expect(tl.getByRole('listitem').nth(0)).toContainText('أُنشئت متابعتان');
    await expect(tl.getByText('الملاحظة الأبرز: أطباق ممتازة وحساب بدون فيديو')).toBeVisible();
    await expect(tl.getByText(/^السلام عليكم أستاذ خالد/)).toHaveCSS('white-space', 'pre-line');

    // «المزيد» has the actions that are not in the header
    await page.getByRole('button', { name: 'المزيد' }).click();
    await expect(page.getByRole('dialog', { name: 'المزيد' }).getByRole('button')).toHaveText(['العميل رد', 'زيارة جديدة', 'تعديل البيانات', 'نقل إلى خسارة', 'إلغاء']);
    await page.keyboard.press('Escape');

    // «أجّل» on the next step
    await nextStep(page).getByRole('button', { name: 'أجّل' }).click();
    const sheet = page.getByRole('dialog', { name: 'أجّل المهمة' });
    await expect(sheet).toBeVisible();
    await sheet.getByRole('button', { name: /^غداً/ }).click();
    await expect(page.getByRole('status').filter({ hasText: 'أُجّلت المهمة إلى' })).toBeVisible();
    const patch = log.writes.find((w) => w.method === 'PATCH' && w.table === 'tasks');
    expect(patch?.params).toContain(`id=eq.${TASK_F3}`);
    const due = new Date((patch?.body as { due_at: string }).due_at);
    // keeps its 19:00 and lands tomorrow in Riyadh
    expect(due.toISOString()).toBe(riyadhAt(new Date(), 1, 19).toISOString());
  });

  test('meeting task on the desktop: «حدد اجتماعاً» in the side column', async ({ page }) => {
    await open(page, repliedDb());
    await expect(nextStep(page)).toContainText('حدد اجتماعاً');
    await expect(nextStep(page).getByRole('button', { name: 'أجّل' })).toBeVisible();
    await nextStep(page).getByRole('button', { name: 'حدد اجتماعاً' }).click();
    await expect(page.getByRole('dialog', { name: 'حدد اجتماعاً' })).toBeVisible();
  });
});
