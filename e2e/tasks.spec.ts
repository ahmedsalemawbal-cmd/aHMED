import { expect, test, type Locator, type Page } from '@playwright/test';
import { leadId, phoneOf, taskId } from './fixtures-leads';
import { lastFollowupFixtures, tasksFixtures, type RawTaskFixture } from './fixtures-tasks';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

type Reply = ReturnType<Handler>;

const H = 3_600_000;
const D = 24 * H;
const riyadhDayStart = (d: Date) => Math.floor((d.getTime() + 3 * H) / D) * D - 3 * H;

interface Db {
  tasks: RawTaskFixture[];
  last: RawTaskFixture[];
}

/**
 * «المهام» REST. The writes change the stored rows as the server does (a
 * tick sets done_at, «أجّل» moves due_at, postpone_lead cancels the
 * follow-ups and adds «أعد المحاولة», mark_lost closes the lead), so the
 * refetch after each write shows the new state.
 */
function handler(db: Db = { tasks: tasksFixtures(), last: lastFollowupFixtures() }, o: { list?: () => Reply; patch?: () => Reply; postpone?: () => Reply } = {}): Handler {
  return (table, req, p) => {
    const m = req.method();
    if (table === 'tasks' && m === 'GET') {
      if (p.get('kind') === 'eq.followup_7') return { body: db.last };
      return o.list?.() ?? { body: db.tasks };
    }
    if (table === 'tasks' && m === 'PATCH') {
      const r = o.patch?.();
      if (r) return r;
      const id = p.get('id')?.replace('eq.', '');
      const t = db.tasks.find((x) => x.id === id);
      if (t) Object.assign(t, req.postDataJSON() as Partial<RawTaskFixture>);
      return { body: [] };
    }
    if (table === 'rpc/postpone_lead') {
      const r = o.postpone?.();
      if (r) return r;
      const { lead_id, retry_at } = (req.postDataJSON() as { p: { lead_id: string; retry_at: string } }).p;
      const lead = db.tasks.find((t) => t.lead.id === lead_id)?.lead ?? db.last.find((t) => t.lead.id === lead_id)?.lead;
      db.tasks = db.tasks.filter((t) => !(t.lead.id === lead_id && t.done_at === null && t.kind.startsWith('followup')));
      if (lead) db.tasks.push({ id: taskId(90), title: 'أعد المحاولة', kind: 'retry', due_at: retry_at, done_at: null, lead });
      return { body: { lead_id, task_id: taskId(90), due_at: retry_at, cancelled_task_ids: [taskId(9)] } };
    }
    if (table === 'rpc/mark_lost') {
      const ids = (req.postDataJSON() as { p: { lead_ids: string[] } }).p.lead_ids;
      db.tasks = db.tasks.filter((t) => !ids.includes(t.lead.id));
      db.last = db.last.filter((t) => !ids.includes(t.lead.id));
      return { body: { updated: ids.length } };
    }
    if (table === 'leads' && m === 'HEAD') return { count: 11 };
    if (table === 'profiles') return { body: [{ full_name: 'أحمد السالم', brand_name: 'استوديو الحي' }] };
    return { body: [] };
  };
}

async function open(page: Page, h: Handler = handler()) {
  await fakeSignIn(page);
  const log = await mockRest(page, h);
  await page.goto('/tasks');
  return log;
}

const section = (page: Page, title: RegExp) => page.locator('section').filter({ has: page.getByRole('heading', { level: 2, name: title }) });
const row = (page: Page, n: number) => page.locator(`[data-task="${taskId(n)}"]`);
const rowTitles = (s: Locator) => s.locator('[data-task] a > span:first-child');

/** A real touch drag on a row (Chromium CDP), held still before lifting. */
async function swipe(page: Page, target: Locator, dx: number) {
  await target.scrollIntoViewIfNeeded();
  const box = await target.boundingBox();
  if (!box) throw new Error('target not visible');
  // start at the far edge so the finger stays on the screen
  const x = dx < 0 ? box.x + box.width - 24 : box.x + 24;
  const y = box.y + 32;
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
  for (let i = 1; i <= 12; i++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + (dx * i) / 12, y }] });
  await page.waitForTimeout(120);
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await cdp.detach();
}

test.describe('المهام · جوال 390', () => {
  test('sections, counts and rows as in Tasks.dc.html', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('heading', { level: 1, name: 'المهام' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'رجوع إلى اليوم' })).toHaveAttribute('href', '/');
    await expect(page.getByText('1 منجزة اليوم')).toBeVisible();
    await expect(page.getByRole('heading', { level: 2 })).toHaveText(['تحتاج قراراً · 1', 'متأخرة · 2', 'اليوم · 4', 'قادمة · 3']);

    const late = section(page, /^متأخرة/);
    await expect(rowTitles(late)).toHaveText(['أرسل الرسالة الأولى', 'متابعة ثانية وأخيرة']);
    await expect(row(page, 1)).toContainText('كافيه نسمة الحمدانية · منذ يومين');
    await expect(row(page, 9)).toContainText('حلويات السنبلة · أمس');
    await expect(row(page, 1).locator('.text-warning')).toHaveText('منذ يومين');

    const today = section(page, /^اليوم/);
    await expect(rowTitles(today)).toHaveText(['أرسل ريل العينة خلال 24 ساعة', 'متابعة ثانية وأخيرة', 'حدد اجتماعاً', 'متابعة أولى: رابط التقرير']);
    await expect(row(page, 2)).toContainText('مطعم بيت المندي · 10:00 ص');
    await expect(row(page, 3)).toContainText('12:30 م');
    // done today: ticked, struck through, no action
    await expect(row(page, 5).getByRole('checkbox')).toHaveAttribute('aria-checked', 'true');
    await expect(row(page, 5).getByRole('checkbox')).toHaveAccessibleName('إلغاء إنجاز أرسل ريل العينة خلال 24 ساعة');
    await expect(rowTitles(today).first()).toHaveClass(/line-through/);
    await expect(row(page, 5).getByRole('link', { name: /واتساب/ })).toHaveCount(0);

    const next = section(page, /^قادمة/);
    await expect(rowTitles(next)).toHaveText(['اجتماع في المحل', 'متابعة أولى: عينة الريل', 'تابع رد العرض']);
    await expect(row(page, 6)).toContainText('4:30 م');

    // actions: WhatsApp opens the message of this task, a meeting is a call
    await expect(row(page, 1).getByRole('link', { name: 'واتساب كافيه نسمة الحمدانية' })).toHaveAttribute('href', `/leads/${leadId(1)}/message?task=${taskId(1)}`);
    await expect(row(page, 3).getByRole('link', { name: 'اتصال بمجمع ابتسامة لطب الأسنان' })).toHaveAttribute('href', `tel:+${phoneOf(3)}`);
    // the shop opens the lead
    await expect(row(page, 4).getByRole('link', { name: 'متابعة أولى: رابط التقرير مركز رواء لطب الأسنان · 4:00 م' })).toHaveAttribute('href', `/leads/${leadId(4)}`);

    // flow 2.4: under the overdue last follow-up, and for the earlier one
    await expect(row(page, 9).getByRole('group', { name: 'قرار حلويات السنبلة' })).toContainText('لم يرد بعد المتابعة الأخيرة');
    await expect(row(page, 2).getByRole('group')).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'قرار مطعم شاورما الحي' })).toContainText('آخر متابعة قبل 3 أيام');

    await expect(page.getByText('اسحب المهمة يساراً للإنجاز، ويميناً للتأجيل: غداً، بعد 3 أيام، أو تاريخ محدد.')).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'التنقل الرئيسي' }).getByRole('link', { name: 'اليوم' })).toHaveAttribute('aria-current', 'page');
    await expectTouchTargets(page);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
  });

  test('tick a task, then «تراجع»', async ({ page }) => {
    const log = await open(page);
    await row(page, 3).getByRole('checkbox', { name: 'إنجاز حدد اجتماعاً' }).click();
    await expect(row(page, 3).getByRole('checkbox')).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByText('أُنجزت المهمة')).toBeVisible();
    await expect(page.getByText('2 منجزة اليوم')).toBeVisible();
    const done = log.writes.find((w) => w.table === 'tasks' && w.method === 'PATCH');
    expect(done?.params).toContain(`id=eq.${taskId(3)}`);
    expect(typeof (done?.body as { done_at: unknown }).done_at).toBe('string');

    await page.getByRole('button', { name: 'تراجع' }).click();
    await expect(row(page, 3).getByRole('checkbox')).toHaveAttribute('aria-checked', 'false');
    await expect(page.getByText('1 منجزة اليوم')).toBeVisible();
    expect(log.writes.filter((w) => w.table === 'tasks' && w.method === 'PATCH').at(-1)?.body).toEqual({ done_at: null });
  });

  test('swipe left completes; swipe right opens «أجّل»', async ({ page }) => {
    const log = await open(page);
    await swipe(page, row(page, 4), -200);
    await expect(row(page, 4).getByRole('checkbox')).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByText('أُنجزت المهمة')).toBeVisible();

    await swipe(page, row(page, 7), 200);
    const sheet = page.getByRole('dialog', { name: 'أجّل المهمة' });
    await expect(sheet).toBeVisible();
    await expectTouchTargets(page, '[role="dialog"]');
    await sheet.getByRole('button', { name: /^غداً/ }).click();
    await expect(page.getByText(/^أُجّلت المهمة إلى/)).toBeVisible();
    const moved = log.writes.filter((w) => w.table === 'tasks' && w.method === 'PATCH').at(-1);
    expect(moved?.params).toContain(`id=eq.${taskId(7)}`);
    expect(Object.keys(moved?.body as object)).toEqual(['due_at']);

    // a short move does nothing
    const before = log.writes.length;
    await swipe(page, row(page, 6), -40);
    await expect(row(page, 6).getByRole('checkbox')).toHaveAttribute('aria-checked', 'false');
    expect(log.writes.length).toBe(before);
  });

  test('the visible «أجّل» button does the same as the swipe', async ({ page }) => {
    const log = await open(page);
    await row(page, 1).getByRole('button', { name: 'أجّل أرسل الرسالة الأولى · كافيه نسمة الحمدانية' }).click();
    await page.getByRole('dialog', { name: 'أجّل المهمة' }).getByRole('button', { name: /^بعد 3 أيام/ }).click();
    await expect(page.getByText(/^أُجّلت المهمة إلى/)).toBeVisible();
    // it left «متأخرة»
    await expect(page.getByRole('heading', { level: 2, name: 'متأخرة · 1' })).toBeVisible();
    const due = new Date((log.writes.find((w) => w.method === 'PATCH')?.body as { due_at: string }).due_at);
    expect(due.getTime()).toBeGreaterThan(Date.now());
  });

  test('day 7: «أجّل 30 يوماً» calls postpone_lead at 10:00 Riyadh in 30 days', async ({ page }) => {
    const log = await open(page);
    await page.getByRole('button', { name: 'أجّل حلويات السنبلة 30 يوماً' }).click();
    await expect(page.getByText('أُجّل حلويات السنبلة 30 يوماً')).toBeVisible();
    const body = log.writes.find((w) => w.table === 'rpc/postpone_lead')?.body as { p: { lead_id: string; retry_at: string } };
    expect(body.p.lead_id).toBe(leadId(9));
    expect(body.p.retry_at).toBe(new Date(riyadhDayStart(new Date()) + 30 * D + 10 * H).toISOString());
    // the follow-up is cancelled, «أعد المحاولة» is planned
    await expect(row(page, 9)).toHaveCount(0);
    await expect(page.locator(`[data-task="${taskId(90)}"]`)).toContainText('أعد المحاولة');
  });

  test('day 7: «نقل إلى خسارة» opens the lost sheet with «لم يرد» chosen', async ({ page }) => {
    const log = await open(page);
    await page.getByRole('button', { name: 'نقل مطعم شاورما الحي إلى خسارة' }).click();
    const sheet = page.getByRole('dialog', { name: 'نقل مطعم شاورما الحي إلى خسارة' });
    await expect(sheet).toBeVisible();
    await expect(sheet.getByRole('radio', { name: 'لم يرد' })).toBeChecked();
    await sheet.getByRole('button', { name: 'انقل إلى خسارة' }).click();
    await expect(page.getByText('نُقل مطعم شاورما الحي إلى خسارة')).toBeVisible();
    expect(log.writes.find((w) => w.table === 'rpc/mark_lost')?.body).toMatchObject({ p: { lead_ids: [leadId(11)], reason: 'no_reply', retry_at: null } });
    await expect(page.getByRole('heading', { level: 2, name: /تحتاج قراراً/ })).toHaveCount(0);
  });

  test('a failed tick goes back with an error and a retry', async ({ page }) => {
    await open(page, handler(undefined, { patch: () => ({ status: 500 }) }));
    await row(page, 3).getByRole('checkbox').click();
    await expect(page.getByText('تعذّر حفظ الإنجاز')).toBeVisible();
    await expect(row(page, 3).getByRole('checkbox')).toHaveAttribute('aria-checked', 'false');
    await expect(page.getByRole('button', { name: 'أعد المحاولة' })).toBeVisible();
  });

  test('loading, error with retry, empty, and offline', async ({ page, context }) => {
    let fail = true;
    await open(page, handler(undefined, { list: () => (fail ? { status: 500, delayMs: 300 } : { body: [], delayMs: 300 }) }));
    await expect(page.locator('[aria-busy="true"]').first()).toBeVisible();
    await expect(page.getByText('تعذّر تحميل المهام')).toBeVisible();
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    // only the decision is left
    await expect(page.getByRole('heading', { level: 2 })).toHaveText(['تحتاج قراراً · 1']);

    await page.unrouteAll({ behavior: 'ignoreErrors' });
    await mockRest(page, handler({ tasks: [], last: [] }));
    await page.reload();
    await expect(page.getByText('لا مهام', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'عميل جديد' })).toBeVisible();

    await context.setOffline(true);
    await expect(page.getByText(/بدون اتصال/).first()).toBeVisible();
    await context.setOffline(false);
  });
});

test.describe('المهام · آيباد 820', () => {
  test.use({ viewport: { width: 820, height: 1180 } });

  test('phone layout centred with the bottom nav', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('navigation', { name: 'التنقل الرئيسي' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'الأقسام' })).toHaveCount(0);
    const box = await section(page, /^اليوم/).boundingBox();
    expect(box?.width ?? 0).toBeLessThanOrEqual(760);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
  });
});

test.describe('المهام · ديسكتوب 1440', () => {
  test.use({ viewport: { width: 1440, height: 1000 }, isMobile: false, hasTouch: false });

  test('sidebar, two columns, «أجّل» buttons', async ({ page }) => {
    await open(page);
    await expect(page.getByRole('navigation', { name: 'الأقسام' })).toBeVisible();
    await expect(page.getByText('8 مهام مفتوحة · 1 منجزة اليوم')).toBeVisible();
    const late = await section(page, /^متأخرة/).boundingBox();
    const next = await section(page, /^قادمة/).boundingBox();
    // side by side: overdue and today in one column, upcoming in the other
    expect(Math.abs((late?.y ?? 0) - (next?.y ?? 1000))).toBeLessThan(2);
    expect(Math.abs((late?.x ?? 0) - (next?.x ?? 0))).toBeGreaterThan(300);
    await expect(row(page, 3).getByRole('button', { name: 'أجّل حدد اجتماعاً · مجمع ابتسامة لطب الأسنان' })).toHaveText('أجّل');
    await row(page, 3).getByRole('button', { name: /^أجّل/ }).click();
    await expect(page.getByRole('dialog', { name: 'أجّل المهمة' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
  });
});
