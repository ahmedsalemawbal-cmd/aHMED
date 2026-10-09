import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACTIVITIES } from './fixtures-catalog';
import { expectTouchTargets } from './helpers';
import { fakeSignIn, mockRest, type Handler } from './mock-supabase';

const LEAD_ID = 'feed0000-0000-4000-8000-000000000001';
const MSG_ID = 'feed0000-0000-4000-8000-0000000000aa';
const TASK_F3 = 'feed0000-0000-4000-8000-0000000000f3';

// Message.dc.html drafts
const DRAFTS = {
  friendly:
    'السلام عليكم أستاذ خالد، معك أحمد، زرتكم اليوم في مطعم ريدان.\nلاحظت أن أطباقكم شكلها ممتاز، لكن حسابكم في إنستقرام بدون أي فيديو لها.\nعندي فكرة ريل قصير كل أسبوع يوصل المطعم لسكان الحي.\nيناسبك أرسل لك عينة مجانية من تصويري اليوم؟',
  formal:
    'السلام عليكم أستاذ خالد، معك أحمد، مسوّق ومصوّر محتوى في الحمدانية، وتشرفت بزيارة مطعم ريدان اليوم.\nلفتني مستوى تقديم الأطباق، ولاحظت أن حساب المطعم في إنستقرام لا يعرضها بالفيديو.\nأقترح ريلز أسبوعية قصيرة تعرّف سكان الحي بالمطعم.\nهل تسمح لي بإرسال عينة مجانية من تصوير اليوم؟',
  short: 'السلام عليكم أستاذ خالد، معك أحمد من زيارة اليوم لمطعم ريدان.\nأطباقكم ممتازة وحسابكم بدون فيديو لها.\nأرسل لك عينة ريل مجانية من تصويري اليوم؟',
};

function leadRow(over: Record<string, unknown> = {}) {
  return {
    id: LEAD_ID,
    business_name: 'مطعم ريدان',
    contact_name: 'خالد العتيبي',
    contact_role: 'owner',
    phone_e164: '966551234567',
    stage: 'visited',
    wa_consent: true,
    do_not_contact: false,
    best_contact_time: 'evening',
    activity_type_id: 'a-rest',
    activity: { checklist: ACTIVITIES[0]?.checklist },
    ...over,
  };
}

interface Opts {
  lead?: Record<string, unknown>;
  leadStatus?: () => number;
  draft?: Record<string, unknown> | null;
  task?: Record<string, unknown> | null;
}

function handler(o: Opts = {}): Handler {
  return (table, req) => {
    const m = req.method();
    if (table === 'leads') {
      if (m === 'PATCH') return { status: 204, body: [] };
      const status = o.leadStatus?.() ?? 200;
      return status === 200 ? { body: [leadRow(o.lead)] } : { status };
    }
    if (table === 'tasks') return { body: o.task ? [o.task] : [] };
    if (table === 'profiles') return { body: [{ full_name: 'أحمد السالم', default_tone: 'friendly' }] };
    if (table === 'visits') return { body: [{ key_observation: 'أطباق ممتازة وحساب بدون فيديو' }] };
    if (table === 'assessments') return { body: [{ weaknesses: ['s3', 'g3'] }] };
    if (table === 'message_templates') return { body: [] };
    if (table === 'messages') {
      if (m === 'POST') return { status: 201, body: [{ id: MSG_ID }] };
      if (m === 'PATCH') return { status: 204, body: [] };
      return { body: o.draft ? [o.draft] : [] };
    }
    if (table === 'rpc/confirm_message_sent') {
      return {
        body: {
          message_id: MSG_ID,
          lead_id: LEAD_ID,
          already_sent: false,
          stage_before: 'visited',
          created_task_ids: o.task ? [] : ['t-3', 't-7'],
          closed_task_ids: o.task ? [TASK_F3] : ['t-first'],
          last_contact_before: null,
        },
      };
    }
    if (table === 'rpc/undo_message_sent') return { body: { message_id: MSG_ID, undone: true } };
    return { body: [] };
  };
}

type FnMode = 'ai' | 'down';

async function mockFunction(page: Page, mode: () => FnMode) {
  const calls: { kind: string; tone: string | null; task_id: string | null }[] = [];
  await page.route('**/functions/v1/generate-message', async (route) => {
    const body = route.request().postDataJSON() as { kind: string; tone: string | null; task_id: string | null };
    calls.push(body);
    if (mode() === 'down') {
      await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: 'boom' }) });
      return;
    }
    const tone = body.tone ?? 'friendly';
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        message_id: MSG_ID,
        tone,
        drafts: DRAFTS,
        generated_by: 'ai',
        observation: 'أطباق ممتازة وحساب بدون فيديو',
        weaknesses: ['الفيديو', 'الرد على المراجعات'],
      }),
    });
  });
  return calls;
}

async function stubWhatsApp(context: BrowserContext) {
  await context.route('https://wa.me/**', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>wa</body></html>' }));
}

async function open(page: Page, path: string, o: Opts = {}, fn: () => FnMode = () => 'ai') {
  await fakeSignIn(page);
  const log = await mockRest(page, handler(o));
  const calls = await mockFunction(page, fn);
  await stubWhatsApp(page.context());
  const gets: string[] = [];
  page.on('request', (r) => {
    if (r.method() === 'GET' && r.url().includes('/rest/v1/')) gets.push(decodeURIComponent(r.url()));
  });
  await page.goto(path);
  return { log, calls, gets };
}

const textarea = (page: Page) => page.getByLabel('نص الرسالة');
const sheet = (page: Page) => page.getByRole('dialog', { name: 'هل أرسلت الرسالة؟' });

async function sendViaWhatsApp(page: Page) {
  const popup = page.waitForEvent('popup');
  await page.getByRole('link', { name: 'إرسال عبر واتساب' }).click();
  await (await popup).close();
}

test.describe('الرسالة · 390', () => {
  test.use({ permissions: ['clipboard-read', 'clipboard-write'] });

  test('first message: matches Message.dc.html, tones, regenerate, WhatsApp, confirm, undo', async ({ page }) => {
    const { log, calls } = await open(page, `/leads/${LEAD_ID}/message`);

    // header: shop, contact, LTR phone, stage
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await expect(page.getByText('أ. خالد العتيبي · مالك · 055 123 4567')).toBeVisible();
    await expect(page.locator('header span[dir=ltr]')).toHaveText('055 123 4567');
    await expect(page.locator('header .md-stage')).toHaveText('تمت الزيارة');

    // draft from the function, with what it was built on
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
    await expect(page.getByText('مسودة مبنية على ملاحظتك: أطباق ممتازة وحساب بدون فيديو، ونقطتي الضعف: الفيديو، والرد على المراجعات.')).toBeVisible();
    expect(calls[0]).toMatchObject({ lead_id: LEAD_ID, kind: 'first', tone: 'friendly', task_id: null });
    await expect(page.getByText('4 أسطر · الحد 5')).toBeVisible();

    // order on screen: info, tone, text, footer
    const order = await page.locator('main > *').evaluateAll((els) => els.map((e) => e.textContent.trim().slice(0, 12)));
    expect(order[0]).toContain('مسودة مبنية');
    expect(order[1]).toContain('النبرة');
    expect(order[2]).toContain('نص الرسالة');

    // tone switch replaces the text without calling the function again
    await page.getByRole('radio', { name: 'مختصر' }).click();
    await expect(textarea(page)).toHaveValue(DRAFTS.short);
    await expect(page.getByText('3 أسطر · الحد 5')).toBeVisible();
    expect(calls).toHaveLength(1);
    await page.getByRole('radio', { name: 'مختصر' }).press('ArrowRight'); // RTL: right = previous
    await expect(page.getByRole('radio', { name: 'رسمي' })).toHaveAttribute('aria-checked', 'true');
    await expect(textarea(page)).toHaveValue(DRAFTS.formal);
    await page.getByRole('radio', { name: 'ودّي' }).click();

    // «أعد الصياغة» asks again in the current tone
    await page.getByRole('button', { name: 'أعد الصياغة' }).click();
    await expect.poll(() => calls.length).toBe(2);
    expect(calls[1]).toMatchObject({ tone: 'friendly' });

    // editing: counter, over-limit, and the link carries the edited text
    await textarea(page).fill(`${DRAFTS.friendly}\nسطر زائد\nوآخر`);
    await expect(page.getByText('6 أسطر · الحد 5. اختصرها')).toBeVisible();
    await textarea(page).fill(DRAFTS.friendly);
    const link = page.getByRole('link', { name: 'إرسال عبر واتساب' });
    await expect(link).toHaveAttribute('href', `https://wa.me/966551234567?text=${encodeURIComponent(DRAFTS.friendly)}`);
    await expectTouchTargets(page);

    // copy
    await page.getByRole('button', { name: 'نسخ الرسالة' }).click();
    await expect(page.getByText('نُسخت الرسالة')).toBeVisible();
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(DRAFTS.friendly);

    // WhatsApp → back → «هل أرسلت؟» with both follow-ups
    await sendViaWhatsApp(page);
    await expect(sheet(page)).toBeVisible();
    await expect(sheet(page).getByText('عند التأكيد تصبح مرحلة مطعم ريدان «تم الإرسال»، وتُنشأ متابعتان:')).toBeVisible();
    await expect(sheet(page).getByText(/^متابعة أولى · /)).toBeVisible();
    await expect(sheet(page).getByText('عينة ريل أو فكرة محتوى')).toBeVisible();
    await expect(sheet(page).getByText(/^متابعة ثانية · /)).toBeVisible();
    await expect(sheet(page).getByText('تذكير قصير وأخير بدون ضغط')).toBeVisible();
    await expectTouchTargets(page, '[role=dialog]');

    // «ليس بعد» keeps the draft; nothing is recorded
    await sheet(page).getByRole('button', { name: 'ليس بعد' }).click();
    await expect(sheet(page)).toHaveCount(0);
    expect(log.writes.some((w) => w.table === 'rpc/confirm_message_sent')).toBe(false);

    await sendViaWhatsApp(page);
    await sheet(page).getByRole('button', { name: 'نعم، أرسلتها' }).click();
    await expect(page.getByText('المرحلة الآن: تم الإرسال')).toBeVisible();
    await expect(page.getByText('أُنشئت متابعتان بعد 3 و7 أيام.')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/leads/${LEAD_ID}$`));

    const rpc = log.writes.find((w) => w.table === 'rpc/confirm_message_sent');
    const p = (rpc?.body as { p: { message_id: string; body: string; tone: string; followups: { kind: string; title: string; due_at: string }[] } }).p;
    expect(p).toMatchObject({ message_id: MSG_ID, body: DRAFTS.friendly, tone: 'friendly' });
    expect(p.followups.map((f) => [f.kind, f.title])).toEqual([
      ['followup_3', 'متابعة أولى'],
      ['followup_7', 'متابعة ثانية وأخيرة'],
    ]);
    // evening contact time: 19:00 Riyadh = 16:00 UTC, 3 and 7 days after today
    const [d3, d7] = p.followups.map((f) => new Date(f.due_at));
    expect(d3?.toISOString()).toMatch(/T16:00:00\.000Z$/);
    expect(((d7?.getTime() ?? 0) - (d3?.getTime() ?? 0)) / 86_400_000).toBe(4);
    const [y, mo, da] = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date()).split('-').map(Number);
    const riyadhMidnightAsUtc = Date.UTC(y ?? 0, (mo ?? 1) - 1, da ?? 1);
    expect(((d3?.getTime() ?? 0) - 16 * 3_600_000 - riyadhMidnightAsUtc) / 86_400_000).toBe(3);

    // «تراجع» undoes with exactly what the confirm created
    await page.getByRole('button', { name: 'تراجع' }).click();
    await expect(page.getByText('تراجعنا عن الإرسال')).toBeVisible();
    const undo = log.writes.find((w) => w.table === 'rpc/undo_message_sent');
    expect(undo?.body).toEqual({ p: { message_id: MSG_ID, stage_before: 'visited', created_task_ids: ['t-3', 't-7'], closed_task_ids: ['t-first'], last_contact_before: null } });
  });

  test('function down: the ready template with a notice, saved as a draft', async ({ page }) => {
    const { log } = await open(page, `/leads/${LEAD_ID}/message`, {}, () => 'down');
    await expect(page.getByText('تعذّر توليد الرسالة. استخدمنا القالب الجاهز بدلاً منها.')).toBeVisible();
    await expect(textarea(page)).toHaveValue(
      'السلام عليكم خالد العتيبي، معك أحمد، زرتكم اليوم في مطعم ريدان.\nلاحظت أطباق ممتازة وحساب بدون فيديو.\nعندي فكرة بسيطة تخدمكم في هذي النقطة بالذات.\nيناسبك أرسل لك عينة مجانية؟',
    );
    const insert = log.writes.find((w) => w.table === 'messages' && w.method === 'POST');
    expect(insert?.body).toMatchObject({ lead_id: LEAD_ID, kind: 'first', status: 'draft', tone: 'friendly', generated_by: 'fallback', task_id: null });
    await expect(page.getByRole('link', { name: 'إرسال عبر واتساب' })).toBeVisible();
  });

  test('no consent: warning, no WhatsApp until «سجّلت موافقته»', async ({ page }) => {
    const { log } = await open(page, `/leads/${LEAD_ID}/message`, { lead: { wa_consent: false } });
    await expect(page.getByText('لم تُسجَّل موافقته على رسائل واتساب. اسأله أولاً، ثم سجّل موافقته ليظهر زر الإرسال.')).toBeVisible();
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
    await expect(page.getByRole('link', { name: 'إرسال عبر واتساب' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'نسخ الرسالة' })).toBeEnabled();
    await expectTouchTargets(page);
    await page.getByRole('button', { name: 'سجّلت موافقته' }).click();
    await expect(page.getByRole('link', { name: 'إرسال عبر واتساب' })).toBeVisible();
    expect(log.writes.find((w) => w.table === 'leads' && w.method === 'PATCH')?.body).toEqual({ wa_consent: true });
  });

  test('do_not_contact: no editor, no WhatsApp, no generation', async ({ page }) => {
    const { calls } = await open(page, `/leads/${LEAD_ID}/message`, { lead: { do_not_contact: true } });
    await expect(page.getByText('طلب عدم التواصل')).toBeVisible();
    await expect(page.getByText('لا نرسل لهذا المحل رسائل ولا ننشئ له متابعات.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'إرسال عبر واتساب' })).toHaveCount(0);
    await expect(page.getByLabel('نص الرسالة')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'افتح صفحة العميل' })).toHaveAttribute('href', `/leads/${LEAD_ID}`);
    expect(calls).toHaveLength(0);
  });

  test('no phone: copy only, with the reason', async ({ page }) => {
    await open(page, `/leads/${LEAD_ID}/message`, { lead: { phone_e164: null } });
    await expect(page.getByText('لا يوجد رقم جوال لهذا المحل. انسخ الرسالة، أو أضف الرقم من صفحة العميل.')).toBeVisible();
    await expect(page.getByText('أ. خالد العتيبي · مالك', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'نسخ الرسالة' })).toBeEnabled();
    await expect(page.getByRole('link', { name: 'إرسال عبر واتساب' })).toHaveCount(0);
  });

  test('follow-up task: its own limit, closes the task, no new follow-ups', async ({ page }) => {
    const draft = { id: MSG_ID, body: 'هلا خالد، معك أحمد من زيارة مطعم ريدان.\nجهزت لك فكرة محتوى قصيرة.\nيناسبك أرسلها لك هنا؟', tone: 'friendly', generated_by: 'ai' };
    const { log, calls } = await open(page, `/leads/${LEAD_ID}/message?task=${TASK_F3}`, {
      lead: { stage: 'contacted' },
      task: { id: TASK_F3, kind: 'followup_3', title: 'متابعة أولى', lead_id: LEAD_ID },
      draft,
    });
    // the saved draft is resumed, not regenerated
    await expect(textarea(page)).toHaveValue(draft.body);
    expect(calls).toHaveLength(0);
    await expect(page.getByText('3 أسطر · الحد 4')).toBeVisible();
    await sendViaWhatsApp(page);
    await expect(sheet(page).getByText('عند التأكيد نسجّل الرسالة في سجل مطعم ريدان، وتُغلق مهمة «متابعة أولى».')).toBeVisible();
    await sheet(page).getByRole('button', { name: 'نعم، أرسلتها' }).click();
    await expect(page.getByText('أُغلقت مهمة «متابعة أولى».')).toBeVisible();
    const rpc = log.writes.find((w) => w.table === 'rpc/confirm_message_sent');
    expect(rpc?.body).toMatchObject({ p: { message_id: MSG_ID, followups: [] } });
  });

  test('reopening after WhatsApp (app was closed) still asks', async ({ page }) => {
    await open(page, `/leads/${LEAD_ID}/message`);
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
    const popup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'إرسال عبر واتساب' }).click();
    await (await popup).close();
    await page.reload();
    await expect(sheet(page)).toBeVisible();
    await sheet(page).getByRole('button', { name: 'ليس بعد' }).click();
    await page.reload();
    await expect(textarea(page)).toBeVisible();
    await expect(sheet(page)).toHaveCount(0);
  });

  test('load error with retry, then success', async ({ page }) => {
    let fail = true;
    await open(page, `/leads/${LEAD_ID}/message`, { leadStatus: () => (fail ? 500 : 200) });
    await expect(page.getByText('تعذّر تحميل بيانات العميل')).toBeVisible();
    fail = false;
    await page.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('مطعم ريدان');
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
  });

  test('unknown lead: empty state with a way back', async ({ page }) => {
    await fakeSignIn(page);
    await mockRest(page, (table) => (table === 'leads' ? { body: [] } : { body: [] }));
    await page.goto(`/leads/${LEAD_ID}/message`);
    await expect(page.getByText('لم نجد هذا العميل')).toBeVisible();
    await expect(page.getByRole('link', { name: 'ارجع لليوم' })).toHaveAttribute('href', '/');
  });

  test('offline: top bar, regenerate falls back to the template', async ({ page, context }) => {
    await open(page, `/leads/${LEAD_ID}/message`);
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await page.getByRole('button', { name: 'أعد الصياغة' }).click();
    await expect(page.getByText('تعذّر توليد الرسالة. استخدمنا القالب الجاهز بدلاً منها.')).toBeVisible();
    await expect(textarea(page)).toHaveValue(/^السلام عليكم خالد العتيبي، معك أحمد/);
  });

  test('loading shows skeletons, not a spinner', async ({ page }) => {
    await fakeSignIn(page);
    await mockRest(page, (table) => (table === 'leads' ? { body: [leadRow()], delayMs: 1500 } : { body: [] }));
    await mockFunction(page, () => 'ai');
    await page.goto(`/leads/${LEAD_ID}/message`);
    await expect(page.locator('[aria-busy=true][aria-label="جارٍ التحميل"]')).toBeVisible();
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
  });
});

test.describe('الرسالة الأولى من مهمة «اليوم» · 390', () => {
  test('opens the same draft as after the visit: no second draft, task id not attached', async ({ page }) => {
    const draft = { id: MSG_ID, body: DRAFTS.friendly, tone: 'friendly', generated_by: 'ai' };
    const { log, calls, gets } = await open(page, `/leads/${LEAD_ID}/message?task=t-first`, {
      task: { id: 't-first', kind: 'first_message', title: 'أرسل الرسالة الأولى', lead_id: LEAD_ID },
      draft,
    });
    await expect(textarea(page)).toHaveValue(DRAFTS.friendly);
    expect(calls).toHaveLength(0);
    const draftQuery = gets.find((u) => u.includes('/rest/v1/messages')) ?? '';
    expect(draftQuery).toContain('kind=eq.first');
    expect(draftQuery).toContain('task_id=is.null');
    expect(log.writes.filter((w) => w.table === 'messages' && w.method === 'POST')).toHaveLength(0);
  });
});
