import { defineConfig } from '@playwright/test';

/**
 * KMSAR Playwright — optimized local runs (~45min target).
 *
 * Prefer: npm run test:e2e  (always runs sequential even if parallel fails)
 * Or:     npx playwright test (uses project dependencies; sequential waits on parallel)
 *
 * Parallel: specs that only need a stable seeded DB (no migrate:fresh).
 * Sequential: mutating suites, workers:1.
 *
 * PERF-* skipped unless INCLUDE_PERF=1
 */
const skipPerf = process.env.INCLUDE_PERF !== '1';
const isCi = !!process.env.CI;

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: isCi ? 1 : 3,
  retries: 1,
  reporter: 'list',
  grepInvert: skipPerf ? /PERF-\d+/ : undefined,
  use: {
    baseURL: 'http://kmsar_auf.test',
    headless: true,
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'dual-cycle',
      testMatch: [
        '**/tc-dual-cycle.spec.ts',
        '**/tc-dual-cycle-final-reject.spec.ts',
        '**/tc-dual-cycle-permanent-loop.spec.ts',
      ],
      fullyParallel: false,
      workers: 1,
    },
    {
      name: 'parallel',
      // Truly shareable on one DB: no migrate:fresh; light / read-mostly.
      // Security, API, and A11Y mutate research concurrently and cause 500/flakes —
      // they run in sequential instead.
      testMatch: [
        '**/tc-role-access.spec.ts',
        '**/tc-uat-current-roles.spec.ts',
        '**/tc-performance.spec.ts',
      ],
      fullyParallel: false,
      workers: isCi ? 1 : 3,
    },
    {
      name: 'sequential',
      dependencies: ['parallel'],
      testMatch: [
        '**/tc-security.spec.ts',
        '**/tc-api.spec.ts',
        '**/tc-accessibility.spec.ts',
        '**/tc-faculty.spec.ts',
        '**/tc-dean.spec.ts',
        '**/tc-ovpri.spec.ts',
        '**/tc-admin.spec.ts',
        '**/tc-import.spec.ts',
        '**/tc-notifications.spec.ts',
        '**/tc-cache.spec.ts',
        '**/tc-registration.spec.ts',
        '**/tc-password-reset.spec.ts',
        '**/tc-error-pages.spec.ts',
      ],
      fullyParallel: false,
      workers: 1,
    },
  ],
});
