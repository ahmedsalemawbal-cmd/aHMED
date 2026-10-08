import { expect, test, type Page } from '@playwright/test';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';
import { todayFixtures } from './fixtures-today';

const PROFILE = [{ full_name: 'أحمد السالم', brand_name: 'استوديو الحي', weekly_visit_goal: 20 }];

function fullHandler(overrides: Partial<Record<string, ReturnType<Handler>>> = {}): Handler {
  const fx = todayFixtures();
  return (table, req, p) => {
    if (overrides[table]) return overrides[table];
    const head = req.method() === 'HEAD';
    switch (table) {
      case 'profiles':
        return { body: PROFILE };
      case 'tasks':
        if (head) return { count: p.get('due_at')?.startsWith('lt.') ? 1 : 4 };
        return { body: fx.tasks };
      case 'visits':
        if (head) return { count: 14 };
        return { body: [{ visited_at: new Date().toISOString(), lead: { business_name: 'مطعم ريدان' } }, { visited_at: new Date().toISOString(), lead: { business_name: 'مخبز الحي' } }] };
      case 'messages': {
        if (p.get('status') === 'eq.draft') return { count: 1 };
        if (p.get('status') === 'eq.replied') return { count: 4 };
        if (p.get('kind') === 'eq.first') return { count: 11 };
        return { count: 3 };
      }
      case 'leads':
        if (head) return { count: 47 };
        return { body: fx.hot };
      case 'stage_history':
        return { body: [{ to_stage: 'replied' }, { to_stage: 'replied' }, { to_stage: 'meeting' }, { to_stage: 'won' }] };
      default:
        return { body: [] };
    }
  };
}

async function open(page: Page, handler: Handler) {
  await fakeSignIn(page);
  const log = await mockRest(page, handler);
  await page.goto('/');
  return log;
}

test.describe('اليوم · جوال 390', () => {
  test('success: matches Main.dc.html order, texts and actions', async ({ page }) => {
    await open(page, fullHandler());
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/^(صباح|مساء) الخير، أحمد$/);
    const counters = page.getByRole('region', { name: 'أرقام اليوم' });
    await expect(counters).toContainText('2زيارات اليوم');
    await expect(counters).toContainText('3رسائل أُرسلت');
    await expect(counters).toContainText('4متابعات مستحقة');
    await expect(page.getByRole('region', { name: 'هدف الأسبوع' })).toContainText('14 من 20 زيارة');
    await expect(page.getByRole('region', { name: 'هدف الأسبوع' })).toContainText(/باقي 6 زيارات|من هدف هذا الأسبوع/);

    const late = page.getByRole('region', { name: /متأخرة/ });
    await expect(late.getByRole('heading')).toHaveText('متأخرة · 1');
    await expect(late).toContainText('كافيه نسمة الحمدانية');
    await expect(late).toContainText('متأخرة منذ يومين');

    const today = page.getByRole('region', { name: /متابعات اليوم/ });
    await expect(today.getByRole('heading')).toHaveText('متابعات اليوم · 3');
    await expect(today).toContainText('هلا أستاذ سلمان، جهزت لك عينة ريل');
    await expect(today.getByRole('button', { name: 'واتساب مطعم الديرة' })).toBeVisible();
    await expect(today.getByRole('link', { name: 'اتصال بمجمع ابتسامة لطب الأسنان' })).toHaveAttribute('href', 'tel:+966551234504');

    await expect(page.getByRole('region', { name: 'عملاء حارّون' })).toContainText('مجمع ابتسامة لطب الأسنان');

    // section order as in the design
    const order = await page.locator('main > section, main > div > section').evaluateAll((els) => els.map((e) => e.getAttribute('aria-label') ?? e.getAttribute('aria-labelledby')));
    expect(order).toEqual(['أرقام اليوم', 'هدف الأسبوع', 'late', 'today', 'hot']);

    // bottom nav: five items, today current
    const nav = page.getByRole('navigation', { name: 'التنقل الرئيسي' });
    await expect(nav.getByRole('link')).toHaveText(['اليوم', 'العملاء', 'عميل جديد', 'Pipeline', 'المزيد']);
    await expect(nav.getByRole('link', { name: 'اليوم' })).toHaveAttribute('aria-current', 'page');

    await expectTouchTargets(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test('WhatsApp on a follow-up opens its message screen', async ({ page }) => {
    await open(page, fullHandler());
    await page.getByRole('button', { name: 'واتساب مطعم الديرة' }).click();
    await expect(page).toHaveURL(/\/leads\/l02\/message\?task=t2$/);
  });

  test('empty: invites to the field with «عميل جديد»', async ({ page }) => {
    await open(page, fullHandler({ tasks: { body: [], count: 0 }, leads: { body: [], count: 0 } }));
    await expect(page.getByRole('heading', { name: 'انزل للميدان' })).toBeVisible();
    await page.getByRole('main').getByRole('button', { name: 'عميل جديد' }).click();
    await expect(page).toHaveURL(/\/leads\/new$/);
  });

  test('loading: skeletons in the shape of the content', async ({ page }) => {
    await open(page, fullHandler({ tasks: { body: todayFixtures().tasks, delayMs: 2500 } }));
    await expect(page.getByLabel('جارٍ التحميل')).toBeVisible();
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toBeVisible({ timeout: 8000 });
  });

  test('error: says what happened and retries', async ({ page }) => {
    let fail = true;
    const base = fullHandler();
    await open(page, (table, req, p) => (fail && table === 'tasks' ? { status: 500 } : base(table, req, p)));
    await expect(page.getByRole('alert')).toContainText('تعذّر تحميل اليوم');
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toBeVisible();
  });

  test('offline: top bar appears and data stays', async ({ page, context }) => {
    await open(page, fullHandler());
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toBeVisible();
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toBeVisible();
    await context.setOffline(false);
  });
});

test.describe('اليوم · ديسكتوب 1440', () => {
  test.use({ viewport: { width: 1440, height: 1000 }, isMobile: false, hasTouch: false });

  test('matches DeskToday.dc.html and postpones an overdue task', async ({ page }) => {
    const log = await open(page, fullHandler());
    const side = page.getByRole('navigation', { name: 'الأقسام' });
    await expect(side).toContainText('اليوم');
    await expect(side.getByRole('link', { name: /اليوم/ })).toHaveAttribute('aria-current', 'page');
    await expect(side).toContainText('لوحة الأرقام');
    await expect(side).toContainText('قريباً');
    await expect(page.locator('aside')).toContainText('أحمد السالم');
    await expect(page.locator('aside')).toContainText('استوديو الحي');
    // sidebar is on the right in RTL
    const box = await page.locator('aside').boundingBox();
    expect(box && box.x > 1000).toBe(true);

    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/الخير، أحمد$/);
    await expect(page.getByText(/4 متابعات تنتظرك/)).toBeVisible();
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toContainText('منها 1 متأخرة');
    await expect(page.getByRole('region', { name: 'أرقام اليوم' })).toContainText('رسالة واحدة لم تُرسل بعد');
    const table = page.getByRole('table');
    await expect(table.getByRole('columnheader')).toHaveText(['المحل', 'المرحلة', 'المتابعة', 'الموعد', 'إجراء']);
    await expect(table.getByRole('row')).toHaveCount(4);
    // the table fits its column at 1440: no clipped action buttons
    const fits = await table.evaluate((t) => t.scrollWidth <= (t.parentElement?.clientWidth ?? 0));
    expect(fits).toBe(true);
    // search field: 40px desktop control, icon does not cover the text
    const search = page.getByRole('searchbox', { name: 'بحث' });
    await expect(search).toHaveCSS('min-height', '40px');
    await expect(search).toHaveCSS('padding-inline-start', '38px');
    await expect(page.getByRole('region', { name: 'هذا الأسبوع' })).toContainText('نسبة الرد على الرسالة الأولى 36%');

    await page.getByRole('region', { name: /متأخرة: أرسل الرسالة الأولى/ }).getByRole('button', { name: 'أجّل' }).click();
    const dialog = page.getByRole('dialog', { name: 'أجّل المهمة' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: /^غداً/ }).click();
    await expect(page.getByRole('status').filter({ hasText: 'أُجّلت المهمة إلى' })).toBeVisible();
    const patch = log.writes.find((w) => w.method === 'PATCH' && w.table === 'tasks');
    expect(patch?.params).toContain('id=eq.t1');
    expect(patch?.body).toHaveProperty('due_at');
  });
});
