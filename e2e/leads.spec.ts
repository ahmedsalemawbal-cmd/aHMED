import { readFileSync } from 'node:fs';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { leadId, leadsFixtures, manyLeads, NAMES, taskId, type RawLeadFixture } from './fixtures-leads';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

type Reply = ReturnType<Handler>;

/**
 * «العملاء» REST: the list, the nav counts, one lead's open tasks (the
 * «هل رد العميل؟» sheet reads them), and the RPCs. `leads` overrides the list GET.
 */
function handler(rows: RawLeadFixture[] = leadsFixtures(), o: { leads?: () => Reply } = {}): Handler {
  return (table, req, p) => {
    const m = req.method();
    const byId = (param: string | null) => rows.find((r) => param === `eq.${r.id}`);
    if (table === 'leads') {
      if (m === 'HEAD') return { count: rows.length };
      if (p.has('id')) return { body: rows.filter((r) => r === byId(p.get('id'))) };
      return o.leads?.() ?? { body: rows };
    }
    if (table === 'tasks' && p.has('lead_id')) {
      const lead = byId(p.get('lead_id'));
      return { body: (lead?.tasks ?? []).map((t) => ({ ...t, created_at: lead?.created_at, done_at: null, cancelled_at: null })) };
    }
    if (table === 'profiles') return { body: [{ full_name: 'أحمد السالم', brand_name: 'استوديو الحي' }] };
    if (table === 'rpc/set_stage') return { body: { updated: 3 } };
    if (table === 'rpc/mark_lost') return { body: { updated: 2 } };
    if (table === 'rpc/mark_replied') return { body: { stage_before: 'contacted', cancelled_task_ids: [taskId(2)], task_id: taskId(99) } };
    if (table === 'rpc/set_meeting' || table === 'rpc/mark_won') return { body: { ok: true } };
    return { body: [] };
  };
}

async function open(page: Page, h: Handler = handler(), path = '/leads') {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  await page.goto(path);
  return log;
}

const cardNames = (page: Page) => page.locator('main article.md-lead .md-lead-name');
const card = (page: Page, name: string) => page.locator('main [data-swipe]').filter({ has: page.locator('.md-lead-name', { hasText: name }) });

/** A real touch drag (Chromium CDP), so touch-action and the browser's own scrolling apply. */
async function touchDrag(page: Page, target: Locator, dx: number, dy = 0) {
  const box = await target.boundingBox();
  if (!box) throw new Error('target not visible');
  // start on the card itself: its left part to open (RTL), its right part to close
  const x = dx > 0 ? box.x + 40 : dx < 0 ? box.x + box.width - 40 : box.x + box.width / 2;
  const y = box.y + Math.min(48, box.height / 2);
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
  const steps = 12;
  for (let i = 1; i <= steps; i++) {
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + (dx * i) / steps, y: y + (dy * i) / steps }] });
  }
  // hold still before lifting, as a finger does: no fling, so the next tap is a click
  await page.waitForTimeout(150);
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + dx, y: y + dy }] });
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await cdp.detach();
}

// next-action order: the two overdue first, then by due date, the lead without a task last
const BY_NEXT = [
  'كافيه نسمة الحمدانية',
  'حلويات السنبلة',
  'مطعم ريدان',
  'مجمع ابتسامة لطب الأسنان',
  'مخبز الحي',
  'مطعم بيت المندي',
  'كوفي بلاك روز',
  'مركز رواء لطب الأسنان',
  'مطعم الديرة',
  'كافيه سحابة',
  'مطعم شاورما الحي',
];

test.describe('العملاء · جوال 390', () => {
  test('success: matches Leads.dc.html (title, search, chips, sort, cards)', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('العملاء 11');
    const search = page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' });
    await expect(search).toHaveAttribute('placeholder', 'ابحث باسم المحل أو الجوال');
    await expect(search).toHaveCSS('padding-inline-start', '44px');
    await expect(search).toHaveCSS('font-size', '16px');

    const chips = page.getByRole('group', { name: 'المراحل' }).getByRole('button');
    await expect(chips).toHaveText(['الكل 11', 'لم يُزر 1', 'تمت الزيارة 2', 'تم الإرسال 4', 'رد 1', 'اجتماع 1', 'عرض سعر 1', 'تم الإغلاق 1']);
    await expect(chips.first()).toHaveAttribute('aria-pressed', 'true');
    await expect(chips.nth(3)).toHaveAttribute('aria-pressed', 'false');

    await expect(page.getByText('11 عميلاً', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'ترتيب: الإجراء القادم' })).toBeVisible();

    expect([...BY_NEXT].sort()).toEqual([...NAMES].sort());
    await expect(cardNames(page)).toHaveText(BY_NEXT);
    const first = card(page, 'كافيه نسمة الحمدانية');
    await expect(first).toContainText('كافيه · أ. سارة');
    await expect(first).toContainText('آخر تواصل: قبل 4 أيام');
    await expect(first).toContainText('أرسل الرسالة الأولى');
    await expect(first.locator('.md-lead-next')).toHaveClass(/is-overdue/);
    await expect(first).toContainText('متأخرة منذ يومين');
    await expect(first.locator('.md-stage')).toHaveText('تمت الزيارة');
    await expect(first.locator('.md-prio')).toHaveText('دافئ');
    await expect(first.locator('.md-lead-score b')).toHaveText('58');
    // WhatsApp only with a number and without «لا تتواصل»
    await expect(page.getByRole('button', { name: 'واتساب مطعم ريدان' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'واتساب كافيه سحابة' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'واتساب مطعم شاورما الحي' })).toHaveCount(0);
    // the swipe actions stay out of reach while closed
    await expect(page.getByRole('button', { name: 'زيارة جديدة' })).toHaveCount(0);

    const nav = page.getByRole('navigation', { name: 'التنقل الرئيسي' });
    await expect(nav.getByRole('link', { name: 'العملاء' })).toHaveAttribute('aria-current', 'page');

    await expectTouchTargets(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test('search by name, by number as typed (05…) and in Arabic digits', async ({ page }) => {
    await open(page);
    const search = page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' });
    await search.fill('ريدان');
    await expect(cardNames(page)).toHaveText(['مطعم ريدان']);
    await expect(page.getByText('عميل واحد', { exact: true })).toBeVisible();
    await expect(page).toHaveURL(/[?&]q=/);
    // counts follow the search
    await expect(page.getByRole('group', { name: 'المراحل' }).getByRole('button')).toHaveText(['الكل 1', 'تم الإرسال 1']);

    await search.fill('0551234505');
    await expect(cardNames(page)).toHaveText(['مطعم بيت المندي']);
    await search.fill('٠٥٥١٢٣٤٥٠٢');
    await expect(cardNames(page)).toHaveText(['مطعم ريدان']);
    await search.fill('خالد');
    await expect(cardNames(page)).toHaveText(['مطعم ريدان']);
  });

  test('no results: says why and clears the search and filters', async ({ page }) => {
    await open(page, handler(), '/leads?stage=contacted&priority=hot');
    await expect(cardNames(page)).toHaveText(['مطعم ريدان']);
    await page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' }).fill('مغسلة');
    await expect(page.getByRole('heading', { name: 'لا نتائج' })).toBeVisible();
    await expect(page.getByText('لا عميل يطابق «مغسلة». امسح البحث والفلاتر أو جرّب كلمة أخرى.')).toBeVisible();
    await expect(page.getByText('لا عملاء', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'امسح البحث والفلاتر' }).click();
    await expect(cardNames(page)).toHaveCount(11);
    await expect(page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' })).toHaveValue('');
    await expect(page).toHaveURL(/\/leads$/);
  });

  test('stage chip filters the list', async ({ page }) => {
    await open(page);
    const chip = page.getByRole('group', { name: 'المراحل' }).getByRole('button', { name: 'تم الإرسال 4' });
    await chip.click();
    await expect(chip).toHaveAttribute('aria-pressed', 'true');
    await expect(cardNames(page)).toHaveText(['حلويات السنبلة', 'مطعم ريدان', 'مطعم بيت المندي', 'مركز رواء لطب الأسنان']);
    await expect(page.getByText('4 عملاء', { exact: true })).toBeVisible();
    await expect(page).toHaveURL(/stage=contacted/);
    await page.getByRole('group', { name: 'المراحل' }).getByRole('button', { name: 'الكل 11' }).click();
    await expect(cardNames(page)).toHaveCount(11);
  });

  test('the header stays while the list scrolls; a new sort starts the list from the top', async ({ page }) => {
    await open(page);
    await expect(cardNames(page)).toHaveCount(11);
    await page.evaluate(() => {
      window.scrollTo({ top: 1000 });
    });
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(1000);
    await expect(page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' })).toBeInViewport();
    await expect(page.getByRole('group', { name: 'المراحل' })).toBeInViewport();
    await expect(cardNames(page).first()).not.toBeInViewport();

    await page.getByRole('button', { name: 'ترتيب: الإجراء القادم' }).click();
    const sheet = page.getByRole('dialog', { name: 'ترتيب وفلترة' });
    await sheet.getByRole('group', { name: 'الترتيب' }).locator('label', { hasText: 'الدرجة' }).click();
    await sheet.getByRole('button', { name: 'اعرض النتائج' }).click();
    // the same 11 leads in a new order: their first one is in view, not the old scroll spot
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    await expect(cardNames(page).first()).toHaveText('كوفي بلاك روز');
    await expect(cardNames(page).first()).toBeInViewport();
  });

  test('sort sheet: «الدرجة» and the extra filters, shown as removable chips', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'ترتيب: الإجراء القادم' }).click();
    const sheet = page.getByRole('dialog', { name: 'ترتيب وفلترة' });
    await expect(sheet).toBeVisible();
    await expect(sheet.getByRole('group', { name: 'الترتيب' }).locator('label')).toHaveText(['الإجراء القادم', 'الأحدث', 'الدرجة']);
    await expect(sheet.getByRole('group', { name: 'النشاط' }).locator('label')).toHaveText(['الكل', 'حلويات', 'كافيه', 'مخبز', 'مركز أسنان', 'مطعم']);
    await expect(sheet.getByRole('group', { name: 'المصدر' }).locator('label')).toHaveText(['الكل', 'زيارة', 'استيراد']);
    await expectTouchTargets(page, '[role="dialog"]');

    await sheet.getByRole('group', { name: 'الترتيب' }).locator('label', { hasText: 'الدرجة' }).click();
    await sheet.getByRole('group', { name: 'الأولوية' }).locator('label', { hasText: 'حار' }).click();
    await sheet.getByRole('button', { name: 'اعرض النتائج' }).click();
    await expect(sheet).toBeHidden();

    await expect(page.getByRole('button', { name: 'ترتيب: الدرجة' })).toBeVisible();
    await expect(cardNames(page)).toHaveText(['كوفي بلاك روز', 'مجمع ابتسامة لطب الأسنان', 'كافيه سحابة', 'مطعم ريدان']);
    await expect(page.getByText('4 عملاء', { exact: true })).toBeVisible();
    await expect(page).toHaveURL(/priority=hot/);
    await expect(page).toHaveURL(/sort=score/);
    const remove = page.getByRole('button', { name: 'إزالة فلتر الأولوية: حار' });
    await expect(remove).toBeVisible();
    await expectTouchTargets(page, 'header');
    await remove.click();
    await expect(cardNames(page)).toHaveCount(11);
    await expect(cardNames(page).first()).toHaveText('كوفي بلاك روز');

    // source: imported leads only
    await page.getByRole('button', { name: 'ترتيب: الدرجة' }).click();
    await sheet.getByRole('group', { name: 'المصدر' }).locator('label', { hasText: 'استيراد' }).click();
    await sheet.getByRole('button', { name: 'اعرض النتائج' }).click();
    await expect(cardNames(page)).toHaveText(['مطعم شاورما الحي']);
    await expect(page.getByRole('button', { name: 'إزالة فلتر المصدر: استيراد' })).toBeVisible();
  });

  test('WhatsApp opens the message screen for the next task', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'واتساب مطعم ريدان' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(2)}/message\\?task=${taskId(2)}$`));
    await page.goBack();
    // the next task is a call: the message screen opens without it
    await page.getByRole('button', { name: 'واتساب مجمع ابتسامة لطب الأسنان' }).click();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(3)}/message$`));
  });

  test('a card opens the lead', async ({ page }) => {
    await open(page);
    await card(page, 'مخبز الحي').locator('.md-lead-main').click();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(4)}$`));
  });

  test('swipe reveals «واتساب» and «زيارة جديدة»; tap elsewhere closes; «زيارة جديدة» navigates', async ({ page }) => {
    await open(page);
    const c = card(page, 'مطعم ريدان');
    await expect(c).toHaveAttribute('data-swipe', 'closed');

    // a vertical drag scrolls the list and never opens the actions
    await touchDrag(page, c, 0, -260);
    await expect(c).toHaveAttribute('data-swipe', 'closed');
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);

    await touchDrag(page, c, 200);
    await expect(c).toHaveAttribute('data-swipe', 'open');
    const actions = page.getByRole('group', { name: 'إجراءات سريعة: مطعم ريدان' });
    await expect(actions.getByRole('button')).toHaveText(['واتساب', 'زيارة جديدة']);
    await expectTouchTargets(page, 'main [data-swipe="open"]');
    // the actions sit at the trailing edge: left in RTL
    const cardBox = await c.boundingBox();
    const visitBox = await actions.getByRole('button', { name: 'زيارة جديدة' }).boundingBox();
    expect(cardBox && visitBox && visitBox.x < cardBox.x + 100).toBe(true);
    await expect(page).toHaveURL(/\/leads$/);

    await page.getByRole('heading', { level: 1 }).tap();
    await expect(c).toHaveAttribute('data-swipe', 'closed');
    await expect(actions).toHaveCount(0);

    // swiping back closes it too
    await touchDrag(page, c, 200);
    await expect(c).toHaveAttribute('data-swipe', 'open');
    await touchDrag(page, c, -200);
    await expect(c).toHaveAttribute('data-swipe', 'closed');

    // «لا تتواصل»: only «زيارة جديدة»
    const dnc = card(page, 'كافيه سحابة');
    await dnc.evaluate((el) => {
      el.scrollIntoView({ block: 'center' });
    });
    await touchDrag(page, dnc, 200);
    await expect(page.getByRole('group', { name: 'إجراءات سريعة: كافيه سحابة' }).getByRole('button')).toHaveText(['زيارة جديدة']);
    await page.getByRole('group', { name: 'إجراءات سريعة: كافيه سحابة' }).getByRole('button', { name: 'زيارة جديدة' }).tap();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(10)}/visit$`));
  });

  test('swipe «واتساب» opens the message screen', async ({ page }) => {
    await open(page);
    const c = card(page, 'كافيه نسمة الحمدانية');
    await touchDrag(page, c, 200);
    await page.getByRole('group', { name: 'إجراءات سريعة: كافيه نسمة الحمدانية' }).getByRole('button', { name: 'واتساب' }).tap();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(1)}/message\\?task=${taskId(1)}$`));
  });

  test('empty: «لا عملاء بعد» with «عميل جديد»', async ({ page }) => {
    await open(page, handler([]));
    await expect(page.getByRole('heading', { name: 'لا عملاء بعد' })).toBeVisible();
    await expect(page.getByRole('searchbox')).toHaveCount(0);
    await page.getByRole('main').getByRole('button', { name: 'عميل جديد' }).click();
    await expect(page).toHaveURL(/\/leads\/new$/);
  });

  test('loading: skeleton cards', async ({ page }) => {
    const rows = leadsFixtures();
    await open(page, handler(rows, { leads: () => ({ body: rows, delayMs: 2500 }) }));
    await expect(page.getByLabel('جارٍ التحميل')).toBeVisible();
    await expect(cardNames(page)).toHaveCount(11, { timeout: 8000 });
    await expect(page.getByLabel('جارٍ التحميل')).toHaveCount(0);
  });

  test('error: says what happened and retries', async ({ page }) => {
    let fail = true;
    await open(page, handler(leadsFixtures(), { leads: () => (fail ? { status: 500 } : undefined) }));
    await expect(page.getByRole('alert')).toContainText('تعذّر تحميل العملاء');
    await expect(page.getByRole('alert')).toContainText('تأكد من الشبكة ثم أعد المحاولة.');
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(cardNames(page)).toHaveCount(11);
  });

  test('offline: top bar appears and the list stays', async ({ page, context }) => {
    await open(page);
    await expect(cardNames(page)).toHaveCount(11);
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await expect(cardNames(page)).toHaveCount(11);
    await page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' }).fill('ريدان');
    await expect(cardNames(page)).toHaveText(['مطعم ريدان']);
    await context.setOffline(false);
  });
});

test.describe('العملاء · ديسكتوب 1440', () => {
  test.use({ viewport: { width: 1440, height: 1000 }, isMobile: false, hasTouch: false });

  const bodyRows = (page: Page) => page.getByRole('table').locator('tbody tr');
  const firstCells = (page: Page) => page.getByRole('table').locator('tbody tr td:nth-child(2) a');

  test('matches DeskLeads.dc.html: header, toolbar, columns and rows', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('navigation', { name: 'الأقسام' }).getByRole('link', { name: /العملاء/ })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('العملاء 11');
    await expect(page.getByRole('button', { name: 'عميل جديد' }).first()).toBeVisible();
    // the page has its own search, not the header one
    await expect(page.getByRole('searchbox')).toHaveCount(1);
    const search = page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' });
    await expect(search).toHaveCSS('min-height', '40px');
    await expect(search).toHaveCSS('font-size', '15px');
    await expect(search).toHaveCSS('padding-inline-start', '38px');
    const toolbar = page.getByRole('group', { name: 'البحث والفلاتر' });
    await expect(toolbar.locator('label:has(select) > span')).toHaveText(['المرحلة:', 'النشاط:', 'الأولوية:', 'المصدر:']);
    await expect(toolbar.locator('label:has(select) > b')).toHaveText(['الكل', 'الكل', 'الكل', 'الكل']);
    await expect(toolbar.getByRole('combobox')).toHaveCount(4);

    const table = page.getByRole('table');
    await expect(table.getByRole('columnheader')).toHaveText(['', 'المحل', 'المرحلة', 'الدرجة', 'الأولوية', 'آخر تواصل', 'الإجراء القادم', 'القيمة المتوقعة']);
    await expect(table.getByRole('checkbox', { name: 'تحديد كل الصفوف' })).toBeVisible();
    await expect(table.getByRole('columnheader', { name: 'الإجراء القادم' })).toHaveAttribute('aria-sort', 'ascending');
    await expect(table.getByRole('columnheader', { name: 'الدرجة' })).toHaveAttribute('aria-sort', 'none');
    await expect(bodyRows(page)).toHaveCount(11);
    await expect(firstCells(page)).toHaveText(BY_NEXT);

    const r1 = bodyRows(page).first();
    await expect(r1.getByRole('link', { name: 'كافيه نسمة الحمدانية' })).toHaveAttribute('href', `/leads/${leadId(1)}`);
    await expect(r1).toContainText('كافيه · أ. سارة');
    await expect(r1.locator('.md-stage')).toHaveText('تمت الزيارة');
    await expect(r1.locator('.md-prio')).toHaveText('دافئ');
    await expect(r1).toContainText('قبل 4 أيام');
    await expect(r1.getByText('متأخرة منذ يومين')).toHaveClass(/text-warning/);
    await expect(r1.locator('td').last()).toHaveText('1,800 ر.س');
    await expect(r1.locator('td').last()).toHaveCSS('text-align', 'end');
    // the score bar takes the score's colour
    await expect(r1.locator('td:nth-child(4) span span')).toHaveClass(/bg-priority-warm/);
    await expect(bodyRows(page).nth(1).locator('td:nth-child(4) span span')).toHaveClass(/bg-priority-cold/);
    await expect(bodyRows(page).last()).toContainText('—');

    await expect(page.getByRole('navigation', { name: 'الصفحات' })).toContainText('عرض 1–11 من 11');
    await expect(page.getByRole('button', { name: 'السابق' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'التالي' })).toBeDisabled();
    const fits = await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);
    expect(fits).toBe(true);
  });

  test('score header sorts and toggles aria-sort', async ({ page }) => {
    await open(page);
    const th = page.getByRole('columnheader', { name: 'الدرجة' });
    await th.getByRole('button').click();
    await expect(th).toHaveAttribute('aria-sort', 'ascending');
    await expect(page.getByRole('columnheader', { name: 'الإجراء القادم' })).toHaveAttribute('aria-sort', 'none');
    await expect(firstCells(page).first()).toHaveText('كوفي بلاك روز');
    await th.getByRole('button').click();
    await expect(th).toHaveAttribute('aria-sort', 'descending');
    await expect(firstCells(page).first()).toHaveText('حلويات السنبلة');
    await expect(firstCells(page).last()).toHaveText('مطعم شاورما الحي');
    await expect(page).toHaveURL(/sort=score&dir=desc/);

    await page.getByRole('columnheader', { name: 'المحل' }).getByRole('button').click();
    await expect(page.getByRole('columnheader', { name: 'المحل' })).toHaveAttribute('aria-sort', 'ascending');
    await page.getByRole('columnheader', { name: 'القيمة المتوقعة' }).getByRole('button').click();
    await expect(page.getByRole('columnheader', { name: 'القيمة المتوقعة' })).toHaveAttribute('aria-sort', 'descending');
    await expect(firstCells(page).first()).toHaveText('مركز رواء لطب الأسنان');
    await page.getByRole('columnheader', { name: 'آخر تواصل' }).getByRole('button').click();
    await expect(firstCells(page).first()).toHaveText('مطعم ريدان');
  });

  test('selection bar: agreement, select all, clear', async ({ page }) => {
    await open(page);
    const bar = page.getByRole('region', { name: 'إجراءات على المحدد' });
    await expect(bar).toHaveCount(0);
    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await expect(bar).toContainText('عميل واحد محدد');
    await expect(page.getByRole('row').filter({ hasText: 'مطعم ريدان' })).toHaveClass(/bg-surface-sunken/);
    await page.getByRole('checkbox', { name: 'تحديد مطعم بيت المندي' }).check();
    await expect(bar).toContainText('عميلان محددان');
    await page.getByRole('checkbox', { name: 'تحديد حلويات السنبلة' }).check();
    await expect(bar).toContainText('3 عملاء محددون');
    // a trip through the phone layout (rotating a tablet) keeps the selection
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('main article.md-lead').first()).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await expect(bar).toContainText('3 عملاء محددون');
    await expect(bar.getByRole('button')).toHaveText(['تغيير المرحلة', 'تصدير CSV', 'إلغاء التحديد']);
    await page.getByRole('checkbox', { name: 'تحديد كل الصفوف' }).check();
    await expect(bar).toContainText('11 عميلاً محدداً');
    await page.getByRole('checkbox', { name: 'تحديد كل الصفوف' }).uncheck();
    await expect(bar).toHaveCount(0);
    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await bar.getByRole('button', { name: 'إلغاء التحديد' }).click();
    await expect(bar).toHaveCount(0);
    await expect(page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' })).not.toBeChecked();
  });

  test('bulk «تغيير المرحلة» to a direct stage', async ({ page }) => {
    const log = await open(page);
    for (const n of ['مطعم ريدان', 'مطعم بيت المندي', 'حلويات السنبلة']) await page.getByRole('checkbox', { name: `تحديد ${n}` }).check();
    await page.getByRole('region', { name: 'إجراءات على المحدد' }).getByRole('button', { name: 'تغيير المرحلة' }).click();
    const sheet = page.getByRole('dialog', { name: 'تغيير المرحلة · 3 عملاء' });
    await expect(sheet).toBeVisible();
    // reply, meeting and won need one lead's details
    await expect(sheet.getByRole('listitem').filter({ hasText: 'تم الإغلاق' })).toBeDisabled();
    await expect(sheet.getByRole('listitem').filter({ hasText: 'اجتماع' })).toBeDisabled();
    await sheet.getByRole('listitem').filter({ hasText: 'عرض سعر' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'المرحلة الآن: عرض سعر · 3 عملاء' })).toBeVisible();
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('region', { name: 'إجراءات على المحدد' })).toHaveCount(0);
    const rpc = log.writes.find((w) => w.table === 'rpc/set_stage');
    expect(rpc?.body).toEqual({ p: { lead_ids: [leadId(9), leadId(2), leadId(5)], stage: 'proposal' } });
  });

  test('bulk «خسارة» with a reason', async ({ page }) => {
    const log = await open(page);
    for (const n of ['مطعم ريدان', 'مطعم بيت المندي']) await page.getByRole('checkbox', { name: `تحديد ${n}` }).check();
    await page.getByRole('button', { name: 'تغيير المرحلة' }).click();
    await page.getByRole('dialog', { name: 'تغيير المرحلة · 2 عملاء' }).getByRole('listitem').filter({ hasText: 'خسارة' }).click();
    const lost = page.getByRole('dialog', { name: 'نقل عميلين إلى خسارة' });
    await expect(lost).toBeVisible();
    await lost.locator('label', { hasText: 'السعر' }).click();
    await lost.getByRole('button', { name: 'انقل إلى خسارة' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'نُقل عميلان إلى خسارة' })).toBeVisible();
    const rpc = log.writes.find((w) => w.table === 'rpc/mark_lost');
    expect(rpc?.body).toEqual({ p: { lead_ids: [leadId(2), leadId(5)], reason: 'price', note: '', retry_at: null } });
  });

  test('bulk action failure: says what happened and offers a retry', async ({ page }) => {
    let fail = true;
    const base = handler();
    const log = await open(page, (table, req, p) => (table === 'rpc/set_stage' && fail ? { status: 500 } : base(table, req, p)));
    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await page.getByRole('button', { name: 'تغيير المرحلة' }).click();
    await page.getByRole('dialog').getByRole('listitem').filter({ hasText: 'تمت الزيارة' }).click();
    const alert = page.getByRole('alert').filter({ hasText: 'تعذّر تغيير المرحلة' });
    await expect(alert).toContainText('تأكد من الشبكة ثم أعد المحاولة.');
    // nothing was lost: the selection stays
    await expect(page.getByRole('region', { name: 'إجراءات على المحدد' })).toContainText('عميل واحد محدد');
    fail = false;
    await alert.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'المرحلة الآن: تمت الزيارة · مطعم ريدان' })).toBeVisible();
    expect(log.writes.filter((w) => w.table === 'rpc/set_stage')).toHaveLength(2);
  });

  test('offline: the top bar shows and a bulk action says to wait for the network', async ({ page, context }) => {
    const log = await open(page);
    await expect(bodyRows(page)).toHaveCount(11);
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await page.getByRole('button', { name: 'تغيير المرحلة' }).click();
    await page.getByRole('dialog').getByRole('listitem').filter({ hasText: 'تمت الزيارة' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'تعذّر تغيير المرحلة' })).toContainText('بدون اتصال. أعد المحاولة عند عودة الشبكة.');
    await expect(page.getByRole('region', { name: 'إجراءات على المحدد' })).toContainText('عميل واحد محدد');
    expect(log.writes.filter((w) => w.table === 'rpc/set_stage')).toHaveLength(0);
    await context.setOffline(false);
  });

  test('one selected row: «اجتماع» and «تم الإغلاق» open their sheets here', async ({ page }) => {
    const log = await open(page);
    const bar = page.getByRole('region', { name: 'إجراءات على المحدد' });
    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await bar.getByRole('button', { name: 'تغيير المرحلة' }).click();
    const stages = page.getByRole('dialog', { name: 'تغيير المرحلة' });
    // one lead: every stage but its own
    await expect(stages.getByRole('listitem').filter({ hasText: 'تم الإرسال' })).toBeDisabled();
    await stages.getByRole('listitem').filter({ hasText: 'اجتماع' }).click();
    const meeting = page.getByRole('dialog', { name: 'حدد اجتماعاً' });
    await expect(meeting).toBeVisible();
    await meeting.getByRole('button', { name: 'احفظ الموعد' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'حُدد الاجتماع · مطعم ريدان' })).toBeVisible();
    await expect(meeting).toBeHidden();
    await expect(bar).toHaveCount(0);
    const set = log.writes.find((w) => w.table === 'rpc/set_meeting');
    // the default slot: tomorrow 4:30 pm in Riyadh
    expect(set?.body).toEqual({ p: { lead_id: leadId(2), at: expect.stringMatching(/T13:30:00\.000Z$/) as unknown, title: 'اجتماع في المحل' } });

    await page.getByRole('checkbox', { name: 'تحديد كوفي بلاك روز' }).check();
    await bar.getByRole('button', { name: 'تغيير المرحلة' }).click();
    await page.getByRole('dialog', { name: 'تغيير المرحلة' }).getByRole('listitem').filter({ hasText: 'تم الإغلاق' }).click();
    const won = page.getByRole('dialog', { name: 'تم الإغلاق' });
    // the expected value is the starting deal value
    await expect(won.getByRole('textbox', { name: 'قيمة الصفقة' })).toHaveValue('2700');
    await won.getByRole('button', { name: 'احفظ الإغلاق' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'تم الإغلاق · كوفي بلاك روز' })).toBeVisible();
    expect(log.writes.find((w) => w.table === 'rpc/mark_won')?.body).toEqual({ p: { lead_id: leadId(6), value: 2700, billing: 'monthly' } });
  });

  test('one selected row: «رد» confirms what it cancels; a closed deal says what to do first', async ({ page }) => {
    const log = await open(page);
    const bar = page.getByRole('region', { name: 'إجراءات على المحدد' });
    await page.getByRole('checkbox', { name: 'تحديد كافيه سحابة' }).check();
    await bar.getByRole('button', { name: 'تغيير المرحلة' }).click();
    const stages = page.getByRole('dialog', { name: 'تغيير المرحلة' });
    await stages.getByRole('listitem').filter({ hasText: 'رد' }).first().click();
    await expect(page.getByRole('status').filter({ hasText: 'العميل في مرحلة مغلقة' })).toContainText('انقله إلى «تم الإرسال» أولاً، ثم سجّل الرد.');
    await stages.getByRole('button', { name: 'إلغاء' }).click();
    await expect(stages).toBeHidden();
    await page.getByRole('checkbox', { name: 'تحديد كافيه سحابة' }).uncheck();

    await page.getByRole('checkbox', { name: 'تحديد مطعم ريدان' }).check();
    await bar.getByRole('button', { name: 'تغيير المرحلة' }).click();
    await stages.getByRole('listitem').filter({ hasText: 'رد' }).first().click();
    const replied = page.getByRole('dialog', { name: 'هل رد العميل؟' });
    // its open follow-up is cancelled and «حدد اجتماعاً» is created
    await expect(replied).toContainText('عند التأكيد تصبح المرحلة «رد»، وتُلغى المتابعة الباقية، وتُنشأ مهمة «حدد اجتماعاً».');
    await replied.getByRole('button', { name: 'نعم، رد العميل' }).click();
    // the lead page's toast, named: its one follow-up was cancelled and a meeting is next
    await expect(page.getByRole('status').filter({ hasText: 'المرحلة الآن: رد · مطعم ريدان' })).toContainText('أُلغيت المتابعة الباقية. حدد اجتماعاً.');
    await expect(bar).toHaveCount(0);
    expect(log.writes.filter((w) => w.table === 'rpc/mark_replied').map((w) => w.body)).toEqual([{ p: { lead_id: leadId(2) } }]);
  });

  test('«تصدير CSV» downloads the selected rows', async ({ page }) => {
    await open(page);
    for (const n of ['مطعم ريدان', 'كوفي بلاك روز']) await page.getByRole('checkbox', { name: `تحديد ${n}` }).check();
    // the file name the page asks for (headless Chromium reports a non-ASCII name as «download»)
    await page.evaluate(() => {
      const names: string[] = [];
      Object.assign(window, { __downloadNames: names });
      document.addEventListener(
        'click',
        (e) => {
          if (e.target instanceof HTMLAnchorElement && e.target.download) names.push(e.target.download);
        },
        true,
      );
    });
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'تصدير CSV' }).click()]);
    const names = await page.evaluate(() => (window as unknown as { __downloadNames: string[] }).__downloadNames);
    expect(names).toHaveLength(1);
    expect(names[0]).toMatch(/^عملاء-\d{4}-\d{2}-\d{2}\.csv$/);
    expect(download.url()).toMatch(/^blob:/);
    const text = readFileSync(await download.path(), 'utf8');
    expect(text.startsWith('﻿')).toBe(true);
    const lines = text.slice(1).trim().split('\r\n');
    expect(lines[0]).toBe('المحل,النشاط,المسؤول,الجوال,المرحلة,الدرجة,الأولوية,آخر تواصل,الإجراء القادم,موعد الإجراء,القيمة المتوقعة,تاريخ التسجيل');
    expect(lines).toHaveLength(3);
    expect(lines[1]).toContain('مطعم ريدان,مطعم,أ. خالد,0551234502,تم الإرسال,48,حار');
    expect(lines[2]).toContain('كوفي بلاك روز,كافيه,أ. نواف,0551234506,عرض سعر,39,حار');
    await expect(page.getByRole('status').filter({ hasText: 'صُدّر عميلان إلى ملف CSV' })).toBeVisible();
  });

  test('pagination: 25 per page', async ({ page }) => {
    await open(page, handler(manyLeads(30)));
    const pager = page.getByRole('navigation', { name: 'الصفحات' });
    await expect(pager).toContainText('عرض 1–25 من 30');
    await expect(bodyRows(page)).toHaveCount(25);
    await expect(firstCells(page).first()).toHaveText('محل 01');
    await page.getByRole('checkbox', { name: 'تحديد كل الصفوف' }).check();
    await expect(page.getByRole('region', { name: 'إجراءات على المحدد' })).toContainText('25 عميلاً محدداً');
    await page.getByRole('button', { name: 'التالي' }).click();
    await expect(pager).toContainText('عرض 26–30 من 30');
    await expect(bodyRows(page)).toHaveCount(5);
    await expect(firstCells(page).first()).toHaveText('محل 26');
    await expect(page).toHaveURL(/page=2/);
    await expect(page.getByRole('button', { name: 'التالي' })).toBeDisabled();
    await expect(page.getByRole('checkbox', { name: 'تحديد كل الصفوف' })).not.toBeChecked();
    await page.getByRole('button', { name: 'السابق' }).click();
    await expect(pager).toContainText('عرض 1–25 من 30');
  });

  test('/leads?q=ريدان prefills the search; the header search elsewhere lands here', async ({ page }) => {
    await open(page, handler(), '/leads?q=ريدان');
    await expect(page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' })).toHaveValue('ريدان');
    await expect(bodyRows(page)).toHaveCount(1);
    await expect(page.getByRole('navigation', { name: 'الصفحات' })).toContainText('عرض 1–1 من 1');

    await page.goto('/');
    const header = page.getByRole('searchbox', { name: 'بحث', exact: true });
    await header.fill('بلاك');
    await header.press('Enter');
    await expect(page).toHaveURL(/\/leads\?q=/);
    await expect(page.getByRole('searchbox', { name: 'بحث بالاسم أو الرقم' })).toHaveValue('بلاك');
    await expect(firstCells(page)).toHaveText(['كوفي بلاك روز']);
  });

  test('filters: native selects, no results and clear', async ({ page }) => {
    await open(page);
    await page.getByRole('combobox', { name: 'المرحلة' }).selectOption('contacted');
    await expect(bodyRows(page)).toHaveCount(4);
    await expect(page.getByRole('group', { name: 'البحث والفلاتر' }).locator('label:has(select) > b').first()).toHaveText('تم الإرسال');
    await page.getByRole('combobox', { name: 'المصدر' }).selectOption('import');
    await expect(page.getByRole('heading', { name: 'لا نتائج' })).toBeVisible();
    await expect(page.getByText('لا عميل يطابق هذه الفلاتر. امسحها لعرض كل العملاء.')).toBeVisible();
    await page.getByRole('button', { name: 'امسح البحث والفلاتر' }).click();
    await expect(bodyRows(page)).toHaveCount(11);
    await page.getByRole('combobox', { name: 'النشاط' }).selectOption({ label: 'مخبز' });
    await expect(firstCells(page)).toHaveText(['مخبز الحي']);
    await page.getByRole('combobox', { name: 'الأولوية' }).selectOption('cold');
    await expect(page.getByRole('heading', { name: 'لا نتائج' })).toBeVisible();
  });

  test('loading, empty and error states', async ({ page }) => {
    let mode: 'slow' | 'fail' | 'empty' = 'slow';
    const rows = leadsFixtures();
    await open(
      page,
      handler(rows, {
        leads: () => (mode === 'slow' ? { body: rows, delayMs: 2000 } : mode === 'fail' ? { status: 500 } : { body: [] }),
      }),
    );
    await expect(page.getByLabel('جارٍ التحميل')).toBeVisible();
    await expect(bodyRows(page)).toHaveCount(11, { timeout: 8000 });

    mode = 'fail';
    await page.reload();
    await expect(page.getByRole('alert')).toContainText('تعذّر تحميل العملاء');
    mode = 'empty';
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('heading', { name: 'لا عملاء بعد' })).toBeVisible();
    await page.getByRole('main').getByRole('button', { name: 'عميل جديد' }).click();
    await expect(page).toHaveURL(/\/leads\/new$/);
  });
});
