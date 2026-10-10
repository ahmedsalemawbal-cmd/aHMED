import { expect, test } from '@playwright/test';
import { expectTouchTargets, login } from './helpers';

test.describe('الدخول', () => {
  test('protected pages redirect to /login', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('matches Login.dc.html: texts, order, RTL, tokens, touch', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('ميداني');
    await expect(page.getByText('سجّل المحل، أرسل الرسالة، وتابع حتى الإغلاق.')).toBeVisible();
    await expect(page.getByText('حساب واحد فقط. لا يوجد تسجيل جديد من التطبيق.')).toBeVisible();

    // order: email, password, submit
    const labels = await page.locator('label').allTextContents();
    expect(labels.map((l) => l.trim())).toEqual(['البريد الإلكتروني', 'كلمة المرور']);
    const email = page.getByLabel('البريد الإلكتروني');
    await expect(email).toHaveAttribute('dir', 'ltr');
    await expect(email).toHaveAttribute('type', 'email');

    // no sign-up anywhere
    await expect(page.getByText(/تسجيل جديد|إنشاء حساب/).filter({ hasNot: page.getByText('لا يوجد') })).toHaveCount(0);

    // tokens: font, 16px inputs, primary button colours resolve from tokens.css
    const styles = await page.evaluate(() => {
      const input = document.querySelector('input');
      const btn = document.querySelector('button[type=submit]');
      if (!input || !btn) throw new Error('login form not rendered');
      const root = getComputedStyle(document.documentElement);
      return {
        font: getComputedStyle(document.body).fontFamily,
        inputSize: getComputedStyle(input).fontSize,
        btnBg: getComputedStyle(btn).backgroundColor,
        radius: root.getPropertyValue('--radius-md').trim(),
        btnRadius: getComputedStyle(btn).borderTopLeftRadius,
      };
    });
    expect(styles.font).toContain('IBM Plex Sans Arabic');
    expect(styles.inputSize).toBe('16px');
    expect(styles.btnBg).toBe('rgb(20, 23, 31)'); // --action (light)
    expect(styles.radius).toBe('10px');
    expect(styles.btnRadius).toBe('10px');

    await expectTouchTargets(page);
  });

  test('validation: says what happened and how to fix it', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button', { name: 'دخول' }).click();
    await expect(page.getByText('البريد غير مكتمل. اكتبه بصيغة name@example.com')).toBeVisible();
  });

  test('offline shows the top bar', async ({ page, context }) => {
    await page.goto('/login');
    await context.setOffline(true);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toBeVisible();
    await context.setOffline(false);
    await expect(page.getByText('بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.')).toHaveCount(0);
  });

  test('wrong password shows an error, right password signs in', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('البريد الإلكتروني').fill('e2e-a@maidani.test');
    await page.getByLabel('كلمة المرور').fill('wrong-password');
    await page.getByRole('button', { name: 'دخول' }).click();
    await expect(page.getByText('البريد أو كلمة المرور غير صحيحة. تأكد منهما وحاول مرة أخرى.')).toBeVisible();

    await login(page, 'A');
    await expect(page).toHaveURL(/\/$/);
  });

  test('PWA manifest is installable', async ({ request }) => {
    const res = await request.get('/manifest.webmanifest');
    expect(res.ok()).toBe(true);
    const m = (await res.json()) as { name: string; display: string; dir: string; lang: string; start_url: string; scope: string; icons: { src: string; sizes: string; purpose?: string }[] };
    expect(m.name).toBe('ميداني');
    expect(m.display).toBe('standalone');
    expect(m.dir).toBe('rtl');
    expect(m.lang).toBe('ar');
    expect(m.icons.map((i) => i.sizes)).toEqual(expect.arrayContaining(['192x192', '512x512']));
    expect(m.icons.some((i) => i.purpose === 'maskable')).toBe(true);
    // relative to the manifest, so the same build also installs under a sub-path (GitHub Pages)
    expect(m.start_url).toBe('./');
    expect(m.scope).toBe('./');
    for (const icon of m.icons) {
      expect(icon.src.startsWith('/')).toBe(false);
      expect((await request.get(new URL(icon.src, res.url()).toString())).ok()).toBe(true);
    }
    const sw = await request.get('/sw.js');
    expect(sw.ok()).toBe(true);
  });
});

test.describe('تثبيت التطبيق', () => {
  test('Chrome offers installation: one tap opens the native dialog', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('button', { name: 'ثبّت التطبيق على جوالك' })).toHaveCount(0);
    await page.evaluate(() => {
      const e = new Event('beforeinstallprompt', { cancelable: true });
      Object.assign(e, {
        prompt: () => {
          document.body.dataset.prompted = 'yes';
          return Promise.resolve();
        },
        userChoice: Promise.resolve({ outcome: 'accepted', platform: 'web' }),
      });
      window.dispatchEvent(e);
    });
    const install = page.getByRole('button', { name: 'ثبّت التطبيق على جوالك' });
    await expect(install).toBeVisible();
    await expectTouchTargets(page);
    await install.click();
    await expect(page.locator('body')).toHaveAttribute('data-prompted', 'yes');
    await expect(page.getByText('جارٍ تثبيت ميداني على جوالك.')).toBeVisible();
    await expect(install).toHaveCount(0);
  });

  test('no install offer and not an iPhone: nothing is shown', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('button', { name: 'دخول' })).toBeVisible();
    await expect(page.getByText('للتثبيت على الآيفون', { exact: false })).toHaveCount(0);
  });
});

test.describe('تثبيت التطبيق · آيفون', () => {
  test.use({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' });
  test('iPhone shows the Share-menu steps', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByText('للتثبيت على الآيفون: افتح الرابط في Safari، ثم اضغط زر المشاركة واختر «إضافة إلى الشاشة الرئيسية».')).toBeVisible();
  });
});
