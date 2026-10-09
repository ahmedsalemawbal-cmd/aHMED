import { defineConfig, devices } from '@playwright/test';

// Parallel workers on one machine can each use their own port and build folder:
// E2E_PORT=4174 E2E_OUTDIR=dist-e2e-a npx playwright test e2e/leads.spec.ts
const port = Number(process.env.E2E_PORT ?? 4173);
const outDir = process.env.E2E_OUTDIR;
const server = outDir
  ? `VITE_DEV_UI=1 npx vite build --outDir ${outDir} && npx vite preview --outDir ${outDir} --port ${port.toString()} --strictPort`
  : `VITE_DEV_UI=1 npm run build && npx vite preview --port ${port.toString()} --strictPort`;

export default defineConfig({
  testDir: 'e2e',
  outputDir: outDir ? `test-results-${outDir}` : 'test-results',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: `http://localhost:${port.toString()}`,
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
    command: server,
    url: `http://localhost:${port.toString()}`,
    reuseExistingServer: true,
    timeout: 180_000,
  },
});
