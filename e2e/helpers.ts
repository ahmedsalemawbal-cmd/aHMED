import { readFileSync, existsSync } from 'node:fs';
import { expect, type Page } from '@playwright/test';

export function e2eEnv(key: string): string {
  const fromProcess = process.env[key];
  if (fromProcess) return fromProcess;
  if (existsSync('.env.local')) {
    for (const line of readFileSync('.env.local', 'utf8').split('\n')) {
      const m = /^([A-Z0-9_]+)=(.*)$/.exec(line.trim());
      if (m?.[1] === key && m[2]) return m[2];
    }
  }
  throw new Error(`Missing ${key} in environment or .env.local`);
}

/** Every visible interactive element must offer a 48px touch target (design-system.md). */
export async function expectTouchTargets(page: Page, min = 48) {
  const small = await page.evaluate((minSize) => {
    const sel = 'button, a[href], input:not([type=hidden]), select, textarea, [role=button], [role=radio], [role=checkbox], [role=switch], [role=tab]';
    return Array.from(document.querySelectorAll<HTMLElement>(sel))
      .filter((el) => {
        const r = el.getBoundingClientRect();
        const style = getComputedStyle(el);
        if (r.width === 0 || r.height === 0 || style.visibility === 'hidden') return false;
        // radio inputs inside a labelled option are covered by their label
        if (el instanceof HTMLInputElement && (el.type === 'radio' || el.type === 'checkbox') && el.closest('label')) return false;
        return r.height < minSize - 0.5 || r.width < minSize - 0.5;
      })
      .map((el) => `${el.tagName.toLowerCase()} "${(el.textContent || el.getAttribute('aria-label') || '').trim()}" ${Math.round(el.getBoundingClientRect().width).toString()}x${Math.round(el.getBoundingClientRect().height).toString()}`);
  }, min);
  expect(small, `touch targets under ${min.toString()}px`).toEqual([]);
}

export async function login(page: Page, who: 'A' | 'B' = 'A') {
  await page.goto('/login');
  await page.getByLabel('البريد الإلكتروني').fill(e2eEnv(`E2E_USER_${who}_EMAIL`));
  await page.getByLabel('كلمة المرور').fill(e2eEnv(`E2E_USER_${who}_PASSWORD`));
  await page.getByRole('button', { name: 'دخول' }).click();
  await expect(page).not.toHaveURL(/\/login/);
}
