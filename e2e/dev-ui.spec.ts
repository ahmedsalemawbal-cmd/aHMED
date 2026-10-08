import { expect, test } from '@playwright/test';
import { expectTouchTargets, findSmallTargets } from './helpers';

test.describe('/dev/ui design-system components', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/dev/ui');
    await expect(page.getByRole('heading', { name: 'مكونات نظام التصميم' })).toBeVisible();
  });

  test('light and dark panels resolve colours from tokens.css', async ({ page }) => {
    const bg = await page.evaluate(() =>
      Array.from(document.querySelectorAll('section[data-theme]')).map((s) => [s.getAttribute('data-theme'), getComputedStyle(s).backgroundColor]),
    );
    expect(bg).toEqual([
      ['light', 'rgb(245, 246, 248)'], // --surface light
      ['dark', 'rgb(15, 18, 22)'], // --surface dark
    ]);
    const wa = page.locator('section[data-theme=dark] .md-btn-whatsapp').first();
    await expect(wa).toHaveCSS('background-color', 'rgb(37, 211, 102)');
    await expect(wa).toHaveCSS('color', 'rgb(6, 48, 26)'); // dark text on WhatsApp green
  });

  test('every control offers a 48px touch target at 390px', async ({ page }) => {
    await expectTouchTargets(page, 'section[data-theme=light]');
  });

  test('the touch-target check itself catches small controls (self-test)', async ({ page }) => {
    await page.evaluate(() => {
      const host = document.createElement('div');
      host.id = 'probe-host';
      host.style.padding = '48px 16px';
      host.innerHTML =
        '<button type="button" style="width:30px;height:30px">صغير</button>' +
        '<button type="button" class="md-btn md-btn-secondary" style="min-height:40px">بلا توسيع</button>' +
        '<button type="button" class="md-btn md-btn-secondary md-btn-sm">موسّع</button>';
      document.querySelector('section[data-theme=light]')?.append(host);
    });
    const small = await findSmallTargets(page, '#probe-host');
    expect(small.map((s) => s.split('"')[1])).toEqual(['صغير', 'بلا توسيع']);
    // the widened sm button really offers 48px (::before height)
    const h = await page.locator('#probe-host .md-btn-sm').evaluate((el) => getComputedStyle(el, '::before').height);
    expect(h).toBe('48px');
  });

  test('TriSelect is a real radio group: arrow keys move the choice', async ({ page }) => {
    const group = page.locator('section[data-theme=light] fieldset', { hasText: 'يرد على المراجعات' });
    const radios = group.getByRole('radio');
    await expect(radios).toHaveCount(3);
    for (const r of await radios.all()) await expect(r).not.toBeChecked();
    await group.locator('label', { hasText: 'نعم' }).click();
    await expect(radios.nth(0)).toBeChecked();
    await radios.nth(0).focus();
    await page.keyboard.press('ArrowDown');
    await expect(radios.nth(1)).toBeChecked();
    await expect(group.locator('.md-tri-opt.is-on')).toHaveText('جزئي');
  });

  test('BottomSheet: focus moves in, Escape closes, focus returns', async ({ page }) => {
    const trigger = page.locator('section[data-theme=light]').getByRole('button', { name: 'افتح «هل أرسلت الرسالة؟»' });
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'هل أرسلت الرسالة؟' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'نعم، أرسلتها' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
  });

  test('success toast disappears after 4s, error stays', async ({ page }) => {
    const panel = page.locator('section[data-theme=light]');
    await panel.getByRole('button', { name: 'إشعار نجاح' }).click();
    const live = page.locator('.app-toasts');
    await expect(live.getByText('حُفظ العميل')).toBeVisible();
    await expect(live.getByText('حُفظ العميل')).toHaveCount(0, { timeout: 6000 });
    await panel.getByRole('button', { name: 'إشعار خطأ' }).click();
    await page.waitForTimeout(5000);
    await expect(live.getByRole('alert')).toContainText('تعذّر الحفظ');
    await live.getByRole('button', { name: 'أعد المحاولة' }).click();
    await expect(live.getByRole('alert')).toHaveCount(0);
  });

  test('no horizontal scroll at 390px', async ({ page }) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test('screenshot for visual review', async ({ page }) => {
    await page.screenshot({ path: 'test-results/dev-ui-390.png', fullPage: true });
  });
});
