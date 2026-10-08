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

/**
 * Every visible interactive element must offer a 48px touch target (design-system.md).
 * A control may look smaller if its hit area is widened (app.css). We probe the
 * points (min/2 − 0.5)px from its centre: each must hit the control itself or a
 * descendant (a ::before hit reports the control). TriSelect options are judged
 * by their label. Elements inside [data-desktop-only] are skipped.
 */
export async function findSmallTargets(page: Page, scope = 'body', min = 48): Promise<string[]> {
  return page.evaluate(
    ({ scopeSel, minSize }) => {
      const sel =
        'button, a[href], input:not([type=hidden]), select, textarea, [role=button], [role=radio], [role=checkbox], [role=switch], [role=tab], label:has(> input[type=radio]), label:has(> input[type=checkbox])';
      const root = document.querySelector(scopeSel);
      if (!root) return ['scope not found'];
      const half = minSize / 2 - 0.5;
      return Array.from(root.querySelectorAll<HTMLElement>(sel))
        .filter((el) => {
          if (el.closest('[data-desktop-only]')) return false;
          // not reachable by the user (e.g. the hidden file input behind «إضافة»)
          if (el.closest('[aria-hidden="true"]')) return false;
          // a radio/checkbox inside a label is judged through its label
          if (el instanceof HTMLInputElement && (el.type === 'radio' || el.type === 'checkbox') && el.closest('label')) return false;
          const r0 = el.getBoundingClientRect();
          const style = getComputedStyle(el);
          if (r0.width === 0 || r0.height === 0 || style.visibility === 'hidden') return false;
          if (r0.height >= minSize - 0.5 && r0.width >= minSize - 0.5) return false;
          el.scrollIntoView({ block: 'center', inline: 'center' });
          const b = el.getBoundingClientRect();
          const cx = b.left + b.width / 2;
          const cy = b.top + b.height / 2;
          const hits = (x: number, y: number) => {
            const at = document.elementFromPoint(x, y);
            return !!at && (at === el || el.contains(at));
          };
          const tallEnough = b.height >= minSize - 0.5 || (hits(cx, cy - half) && hits(cx, cy + half));
          const wideEnough = b.width >= minSize - 0.5 || (hits(cx - half, cy) && hits(cx + half, cy));
          return !(tallEnough && wideEnough);
        })
        .map((el) => {
          const r = el.getBoundingClientRect();
          return `${el.tagName.toLowerCase()} "${(el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 30)}" ${Math.round(r.width).toString()}x${Math.round(r.height).toString()}`;
        });
    },
    { scopeSel: scope, minSize: min },
  );
}

export async function expectTouchTargets(page: Page, scope = 'body', min = 48) {
  expect(await findSmallTargets(page, scope, min), `touch targets under ${min.toString()}px`).toEqual([]);
}

export async function login(page: Page, who: 'A' | 'B' = 'A') {
  await page.goto('/login');
  await page.getByLabel('البريد الإلكتروني').fill(e2eEnv(`E2E_USER_${who}_EMAIL`));
  await page.getByLabel('كلمة المرور').fill(e2eEnv(`E2E_USER_${who}_PASSWORD`));
  await page.getByRole('button', { name: 'دخول' }).click();
  await expect(page).not.toHaveURL(/\/login/);
}
