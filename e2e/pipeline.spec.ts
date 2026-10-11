import { expect, test, type Locator, type Page } from '@playwright/test';
import { leadId, leadsFixtures, type RawLeadFixture } from './fixtures-leads';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

type Reply = ReturnType<Handler>;

const DAY = 86_400_000;

/** The leads of «العملاء», with «مطعم بيت المندي» 10 days in «تم الإرسال» (stale). */
function rows(): RawLeadFixture[] {
  return leadsFixtures().map((r) => (r.id === leadId(5) ? { ...r, stage_changed_at: new Date(Date.now() - 10 * DAY).toISOString() } : r));
}

/** The REST API for «Pipeline»: the RPCs change the stored stage, as the server does, so a refetch keeps the move. */
function handler(data: RawLeadFixture[] = rows(), o: { leads?: () => Reply; setStage?: () => Reply } = {}): Handler {
  const move = (ids: unknown, stage: string) => {
    for (const r of data) if (Array.isArray(ids) && ids.includes(r.id)) Object.assign(r, { stage, stage_changed_at: new Date().toISOString() });
  };
  return (table, req, p) => {
    if (table === 'leads') {
      if (req.method() === 'HEAD') return { count: data.length };
      return o.leads?.() ?? { body: data };
    }
    if (table === 'tasks' && p.has('lead_id')) return { body: [] };
    if (table === 'profiles') return { body: [{ full_name: 'أحمد السالم', brand_name: 'استوديو الحي' }] };
    if (table.startsWith('rpc/')) {
      const body = req.postDataJSON() as { p: { lead_ids?: string[]; lead_id?: string; stage?: string } };
      if (table === 'rpc/set_stage') {
        const r = o.setStage?.();
        if (r) return r;
        move(body.p.lead_ids, body.p.stage ?? '');
        return { body: { updated: 1 } };
      }
      if (table === 'rpc/mark_lost') {
        move(body.p.lead_ids, 'lost');
        return { body: { updated: 1 } };
      }
      if (table === 'rpc/mark_won') {
        move([body.p.lead_id], 'won');
        return { body: { ok: true } };
      }
    }
    return { body: [] };
  };
}

async function open(page: Page, h: Handler = handler(), path = '/pipeline') {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  await page.goto(path);
  return log;
}

const tab = (page: Page, name: RegExp) => page.getByRole('tablist', { name: 'المراحل' }).getByRole('tab', { name });
const cards = (page: Page) => page.locator('#pipeline-panel [data-lead] .md-lead-name');

/** A finger resting on `target` (Chromium CDP touch), then lifted. */
async function longPress(page: Page, target: Locator) {
  const box = await target.boundingBox();
  if (!box) throw new Error('target not visible');
  const cdp = await page.context().newCDPSession(page);
  const pt = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [pt] });
  await page.waitForTimeout(700);
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await cdp.detach();
}

test.describe('Pipeline · جوال 390', () => {
  test('stage tabs with counts, the stage summary and compact cards', async ({ page }) => {
    await open(page, handler(), '/pipeline?stage=contacted');
    await expect(page.getByRole('heading', { level: 1, name: 'Pipeline' })).toBeVisible();
    const tabs = page.getByRole('tablist', { name: 'المراحل' }).getByRole('tab');
    await expect(tabs).toHaveText(['لم يُزر1', 'تمت الزيارة2', 'تم الإرسال4', 'رد1', 'اجتماع1', 'عرض سعر1', 'تم الإغلاق1', 'خسارة0']);
    await expect(tab(page, /تم الإرسال/)).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByText('تم الإرسال · 4 عملاء')).toBeVisible();
    await expect(page.locator('#pipeline-panel')).toContainText('قيمة متوقعة 10,500 ر.س');
    await expect(cards(page)).toHaveCount(4);
    // the stale card: more than 7 days in the stage
    const stale = page.locator('[data-lead]').filter({ hasText: 'مطعم بيت المندي' });
    await expect(stale.locator('.md-lead')).toHaveClass(/is-stale/);
    await expect(stale).toContainText('10 يوم');
    const fresh = page.locator('[data-lead]').filter({ hasText: 'مطعم ريدان' });
    await expect(fresh.locator('.md-lead')).not.toHaveClass(/is-stale/);
    await expect(fresh).toContainText('2,500 ر.س');
    await expect(page.getByText('اضغط مطولاً على بطاقة لتغيير مرحلتها.', { exact: false })).toBeVisible();
    await expectTouchTargets(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);

    // arrows follow the reading direction (RTL: left is next)
    await tab(page, /تم الإرسال/).focus();
    await page.keyboard.press('ArrowLeft');
    await expect(tab(page, /^رد/)).toHaveAttribute('aria-selected', 'true');
    await expect(page).toHaveURL(/stage=replied/);
    await expect(cards(page)).toHaveText(['مجمع ابتسامة لطب الأسنان']);

    // a tap opens the lead
    await cards(page).first().click();
    await expect(page).toHaveURL(new RegExp(`/leads/${leadId(3)}$`));
  });

  test('long press opens «تغيير المرحلة»; a direct stage moves the card at once', async ({ page }) => {
    const log = await open(page, handler(), '/pipeline?stage=contacted');
    await longPress(page, page.locator('[data-lead]').filter({ hasText: 'مطعم ريدان' }));
    const sheet = page.getByRole('dialog', { name: 'تغيير المرحلة' });
    await expect(sheet).toBeVisible();
    await expectTouchTargets(page, '[role="dialog"]');
    await expect(sheet.getByRole('listitem').filter({ hasText: 'تم الإرسال' })).toBeDisabled();
    await sheet.getByRole('listitem').filter({ hasText: 'عرض سعر' }).click();
    await expect(page.getByText('المرحلة الآن: عرض سعر · مطعم ريدان')).toBeVisible();
    await expect(cards(page)).toHaveCount(3);
    await expect(tab(page, /عرض سعر/)).toContainText('2');
    expect(log.writes.find((w) => w.table === 'rpc/set_stage')?.body).toEqual({ p: { lead_ids: [leadId(2)], stage: 'proposal' } });
  });

  test('a failed move goes back with an error and a retry', async ({ page }) => {
    await open(page, handler(rows(), { setStage: () => ({ status: 500 }) }), '/pipeline?stage=contacted');
    await page.getByRole('button', { name: 'تغيير المرحلة: مطعم ريدان' }).click();
    await page.getByRole('dialog', { name: 'تغيير المرحلة' }).getByRole('listitem').filter({ hasText: 'تمت الزيارة' }).click();
    await expect(page.getByText('تعذّر نقل مطعم ريدان إلى «تمت الزيارة»')).toBeVisible();
    await expect(page.getByRole('button', { name: 'أعد المحاولة' })).toBeVisible();
    await expect(cards(page)).toHaveCount(4);
    await expect(tab(page, /تمت الزيارة/)).toContainText('2');
  });

  test('«خسارة» from the card menu opens the lost sheet', async ({ page }) => {
    const log = await open(page, handler(), '/pipeline?stage=visited');
    await page.getByRole('button', { name: 'تغيير المرحلة: مطعم الديرة' }).click();
    await page.getByRole('dialog', { name: 'تغيير المرحلة' }).getByRole('listitem').filter({ hasText: 'خسارة' }).click();
    const lost = page.getByRole('dialog', { name: 'نقل مطعم الديرة إلى خسارة' });
    await expect(lost).toBeVisible();
    await lost.locator('label', { hasText: 'السعر' }).click();
    await lost.getByRole('button', { name: /انقل|نقل/ }).last().click();
    await expect(page.getByText('نُقل مطعم الديرة إلى خسارة')).toBeVisible();
    expect(log.writes.find((w) => w.table === 'rpc/mark_lost')?.body).toMatchObject({ p: { lead_ids: [leadId(8)], reason: 'price' } });
  });

  test('loading, error with retry, and empty', async ({ page }) => {
    let fail = true;
    await open(page, handler(rows(), { leads: () => (fail ? { status: 500 } : { body: rows(), delayMs: 400 }) }));
    await expect(page.getByText('تعذّر تحميل الـ Pipeline')).toBeVisible();
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('tablist', { name: 'المراحل' })).toBeVisible();

    await page.unrouteAll({ behavior: 'ignoreErrors' });
    await mockRest(page, handler([]));
    await page.reload();
    await expect(page.getByText('لا عملاء بعد')).toBeVisible();
    await expect(page.getByRole('button', { name: 'عميل جديد' })).toBeVisible();
  });

  test('offline shows the banner and keeps the board', async ({ page, context }) => {
    await open(page);
    await expect(page.getByRole('tablist', { name: 'المراحل' })).toBeVisible();
    await context.setOffline(true);
    await expect(page.getByText(/بدون اتصال/).first()).toBeVisible();
    await expect(page.getByRole('tablist', { name: 'المراحل' })).toBeVisible();
    await context.setOffline(false);
  });
});

test.describe('Pipeline · آيباد 820', () => {
  test.use({ viewport: { width: 820, height: 1180 } });

  test('phone layout centred, cards in two columns, bottom nav', async ({ page }) => {
    await open(page, handler(), '/pipeline?stage=contacted');
    await expect(page.getByRole('navigation', { name: 'التنقل الرئيسي' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'الأقسام' })).toHaveCount(0);
    const a = await cards(page).nth(0).boundingBox();
    const b = await cards(page).nth(1).boundingBox();
    expect(Math.abs((a?.y ?? 0) - (b?.y ?? 1))).toBeLessThan(2);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
  });
});

// a desktop browser: Chromium with a phone user agent starts no HTML5 drag
const DESKTOP_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

test.describe('Pipeline · ديسكتوب 1440', () => {
  test.use({ viewport: { width: 1440, height: 1000 }, isMobile: false, hasTouch: false, userAgent: DESKTOP_UA });

  const column = (page: Page, label: string) => page.getByRole('main', { name: 'لوحة المراحل' }).getByRole('region', { name: label, exact: true });

  test('kanban columns with counts and sums; the lost column folded', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('navigation', { name: 'الأقسام' })).toBeVisible();
    await expect(page.getByText('9 صفقات مفتوحة')).toBeVisible();
    const sent = column(page, 'تم الإرسال');
    await expect(sent).toContainText('10,500 ر.س متوقعة');
    await expect(sent.locator('[data-lead]')).toHaveCount(4);
    await expect(column(page, 'لم يُزر')).toContainText('بدون قيمة بعد');
    const lost = page.getByRole('button', { name: 'خسارة · 0 · افتح العمود' });
    await expect(lost).toHaveAttribute('aria-expanded', 'false');
    await lost.click();
    await expect(column(page, 'خسارة')).toBeVisible();
    // the activity filter
    await page.getByLabel('النشاط').selectOption({ label: 'مطعم' });
    await expect(page).toHaveURL(/activity=a-rest/);
    await expect(sent.locator('[data-lead]')).toHaveCount(2);
  });

  test('drag a card to a direct stage saves it; to «رد» opens its sheet', async ({ page }) => {
    const log = await open(page);
    const card = column(page, 'تم الإرسال').locator('[data-lead]').filter({ hasText: 'مطعم ريدان' });
    await card.dragTo(column(page, 'تمت الزيارة'));
    await expect(page.getByText('المرحلة الآن: تمت الزيارة · مطعم ريدان')).toBeVisible();
    await expect(column(page, 'تمت الزيارة').locator('[data-lead]')).toHaveCount(3);
    await expect(column(page, 'تم الإرسال').locator('[data-lead]')).toHaveCount(3);
    expect(log.writes.find((w) => w.table === 'rpc/set_stage')?.body).toEqual({ p: { lead_ids: [leadId(2)], stage: 'visited' } });

    await column(page, 'تمت الزيارة').locator('[data-lead]').filter({ hasText: 'كافيه نسمة الحمدانية' }).dragTo(column(page, 'رد'));
    await expect(page.getByRole('dialog', { name: 'هل رد العميل؟' })).toBeVisible();
    expect(log.writes.filter((w) => w.table === 'rpc/mark_replied')).toHaveLength(0);
  });

  test('keyboard: the card menu opens «تغيير المرحلة»', async ({ page }) => {
    await open(page);
    const card = column(page, 'رد').locator('[data-lead]').first();
    await card.getByRole('button', { name: 'تغيير المرحلة: مجمع ابتسامة لطب الأسنان' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'تغيير المرحلة' })).toBeVisible();
  });

  test('loading skeleton, then error with retry', async ({ page }) => {
    let fail = true;
    await open(page, handler(rows(), { leads: () => (fail ? { status: 500, delayMs: 300 } : { body: rows() }) }));
    await expect(page.locator('[aria-busy="true"]').first()).toBeVisible();
    await expect(page.getByText('تعذّر تحميل الـ Pipeline')).toBeVisible();
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(column(page, 'تم الإرسال')).toBeVisible();
  });
});
