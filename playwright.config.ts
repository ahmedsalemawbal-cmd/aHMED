import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:4173',
    locale: 'ar-SA',
    timezoneId: 'Asia/Riyadh',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'mobile-390',
      use: { ...devices['Pixel 7'], viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 },
    },
  ],
  webServer: {
    command: 'npm run build && npx vite preview --port 4173 --strictPort',
    url: 'http://localhost:4173',
    reuseExistingServer: true,
    timeout: 180_000,
  },
});
