// Gate 3 · the main flow against the real Supabase project (no mocks):
// login → new lead → assessment → message → «هل أرسلت؟» → in the list at «تم الإرسال».
// Needs network access to *.supabase.co and the E2E_USER_A_* accounts.
import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { e2eEnv, login } from './helpers';

const LIMIT_MS = 2 * 60_000; // PLAN X.4: a lead and its message in under two minutes

async function accessToken(page: Page): Promise<string> {
  const ref = new URL(e2eEnv('VITE_SUPABASE_URL')).hostname.split('.')[0] ?? '';
  const raw = await page.evaluate((key) => localStorage.getItem(key), `sb-${ref}-auth-token`);
  const token = raw ? (JSON.parse(raw) as { access_token?: string }).access_token : undefined;
  if (!token) throw new Error('no session after login');
  return token;
}

async function rest<T>(request: APIRequestContext, token: string, path: string): Promise<T> {
  const res = await request.get(`${e2eEnv('VITE_SUPABASE_URL')}/rest/v1/${path}`, {
    headers: { apikey: e2eEnv('VITE_SUPABASE_ANON_KEY'), Authorization: `Bearer ${token}` },
  });
  expect(res.ok(), await res.text()).toBe(true);
  return (await res.json()) as T;
}

test('main flow at 390: new lead → message → confirm → contacted with two follow-ups', async ({ page, request, context }) => {
  await context.route('https://wa.me/**', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>wa</body></html>' }));
  const name = `محل اختبار ${Date.now().toString()}`;
  const phone = `05${Math.floor(10_000_000 + Math.random() * 89_999_999).toString()}`;

  await login(page, 'A');
  const started = Date.now();
  await page.goto('/leads/new');

  await page.getByLabel('اسم المحل').fill(name);
  await page.getByRole('button', { name: 'مطعم', exact: true }).click();
  await page.getByLabel('اسم المسؤول').fill('خالد');
  await page.getByLabel('الجوال').fill(phone);
  await page.getByRole('switch', { name: /وافق على رسالة واتساب/ }).click();
  await page.getByRole('button', { name: 'التالي: التقييم' }).click();

  const tri = (label: string) => page.locator('fieldset', { hasText: label });
  await tri('التقييم 4.3 أو أعلى').locator('label', { hasText: 'نعم' }).click();
  await tri('ينشر فيديو أو ريلز').locator('label', { hasText: 'لا' }).click();
  await page.getByRole('button', { name: 'التالي: الملاحظات' }).click();
  await page.getByLabel('الملاحظة الأبرز').fill('أطباقهم ممتازة وحسابهم بدون فيديو');
  await page.getByRole('button', { name: 'التالي: الخدمات' }).click();
  await page.getByRole('button', { name: 'احفظ وولّد الرسالة' }).click();

  await expect(page).toHaveURL(/\/leads\/[0-9a-f-]{36}\/message$/);
  const leadId = /\/leads\/([0-9a-f-]{36})\/message/.exec(page.url())?.[1] ?? '';
  const text = page.getByLabel('نص الرسالة');
  await expect(text).not.toHaveValue('', { timeout: 30_000 });
  await expect(text).toHaveValue(/خالد/);

  const popup = page.waitForEvent('popup');
  await page.getByRole('link', { name: 'إرسال عبر واتساب' }).click();
  await (await popup).close();
  await page.getByRole('dialog', { name: 'هل أرسلت الرسالة؟' }).getByRole('button', { name: 'نعم، أرسلتها' }).click();
  await expect(page.getByText('المرحلة الآن: تم الإرسال')).toBeVisible();
  expect(Date.now() - started).toBeLessThan(LIMIT_MS);

  // the lead is in the list at «تم الإرسال»
  await page.goto(`/leads?q=${encodeURIComponent(name)}`);
  const card = page.locator('article', { hasText: name });
  await expect(card).toHaveCount(1);
  await expect(card.locator('.md-stage')).toHaveText('تم الإرسال');

  const token = await accessToken(page);
  const [lead] = await rest<{ stage: string; last_contact_at: string | null }[]>(request, token, `leads?id=eq.${leadId}&select=stage,last_contact_at`);
  expect(lead?.stage).toBe('contacted');
  expect(lead?.last_contact_at).not.toBeNull();
  const tasks = await rest<{ kind: string; done_at: string | null }[]>(request, token, `tasks?lead_id=eq.${leadId}&select=kind,done_at&order=kind`);
  expect(tasks.filter((t) => !t.done_at).map((t) => t.kind)).toEqual(['followup_3', 'followup_7']);
  expect(tasks.find((t) => t.kind === 'first_message')?.done_at).not.toBeNull();
  const msgs = await rest<{ status: string; sent_at: string | null }[]>(request, token, `messages?lead_id=eq.${leadId}&select=status,sent_at`);
  expect(msgs).toHaveLength(1);
  expect(msgs[0]?.status).toBe('sent');

  // keep the test account clean (visits, messages and tasks cascade)
  await request.delete(`${e2eEnv('VITE_SUPABASE_URL')}/rest/v1/leads?id=eq.${leadId}`, {
    headers: { apikey: e2eEnv('VITE_SUPABASE_ANON_KEY'), Authorization: `Bearer ${token}` },
  });
});
