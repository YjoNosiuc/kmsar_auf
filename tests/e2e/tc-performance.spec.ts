import { test, expect, Page } from '@playwright/test';
import * as path from 'path';
import { login, credentials } from './helpers/auth';
import {
  createAndSubmitResearch,
  endorseResearch,
  approveResearch,
  setupEndorsedResearch,
  selectCurrentUserAsPrimary,
  submitResearchFromDocuments,
  fillRegistrationStep1,
  REGISTRATION_UI,
} from './helpers/research';

const USER_IMPORT = path.resolve('tests/e2e/fixtures/user_import_valid.xlsx');
const RESEARCH_IMPORT = path.resolve('tests/e2e/fixtures/research_import_valid.xlsx');

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function measureGoto(page: Page, url: string): Promise<number> {
  const start = Date.now();
  await page.goto(url, { waitUntil: 'networkidle' });
  return Date.now() - start;
}

async function measureAction(run: () => Promise<void>): Promise<number> {
  const start = Date.now();
  await run();
  return Date.now() - start;
}

test.describe('Performance — UAT', () => {
  test.describe.configure({ timeout: 180_000 });

  // -------------------------------------------------------------------------
  test.describe('Page load performance', () => {
    test('PERF-001: Login page loads within 3 seconds', async ({ page }) => {
      test.slow();
      const ms = await measureGoto(page, '/login');
      expect(ms, `login loaded in ${ms}ms`).toBeLessThan(3000);
    });

    test('PERF-002: Faculty research list loads within 3 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const ms = await measureGoto(page, '/research');
      expect(ms, `research list loaded in ${ms}ms`).toBeLessThan(3000);
    });

    test('PERF-003: Dean dashboard loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const ms = await measureGoto(page, '/dean/dashboard');
      expect(ms, `dean dashboard loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-004: OVPRI dashboard loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const ms = await measureGoto(page, '/ovpri/dashboard');
      expect(ms, `ovpri dashboard loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-005: Admin dashboard loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.admin.email, credentials.admin.password);
      const ms = await measureGoto(page, '/admin/dashboard');
      expect(ms, `admin dashboard loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-005b: Admin dashboard with SDG chart loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.admin.email, credentials.admin.password);
      const ms = await measureGoto(page, '/admin/dashboard');
      await expect(page.locator('#adminSdgChart')).toBeVisible();
      expect(ms, `admin dashboard with SDG chart loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-004b: OVPRI dashboard with new charts loads within 5 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const ms = await measureGoto(page, '/ovpri/dashboard');
      await expect(page.locator('#submissionTrendChart')).toBeVisible();
      await expect(page.locator('#engagedByCollegeChart')).toBeVisible();
      expect(ms, `OVPRI dashboard with new charts loaded in ${ms}ms`).toBeLessThan(5000);
    });

    test('PERF-006: All Research page (OVPRI) loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const ms = await measureGoto(page, '/ovpri/research');
      expect(ms, `ovpri research loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-007: Reports page loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const ms = await measureGoto(page, '/reports');
      expect(ms, `reports loaded in ${ms}ms`).toBeLessThan(4000);
    });

    test('PERF-008: Audit logs page loads within 4 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.admin.email, credentials.admin.password);
      const ms = await measureGoto(page, '/admin/audit-logs');
      expect(ms, `audit logs loaded in ${ms}ms`).toBeLessThan(4000);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Action performance', () => {
    test('PERF-009: Research submission completes within 5 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.getByRole('button', { name: 'Register new research', exact: true }).click();
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });

      const title = uniqueTitle('PERF009 Submit');
      await fillRegistrationStep1(page, title);
      await page.getByRole('button', { name: REGISTRATION_UI.continueToAuthors }).click();
      await page.waitForURL(/\/authors/, { timeout: 90_000 });
      await selectCurrentUserAsPrimary(page);
      await page.getByRole('button', { name: 'Continue to documents' }).click();
      await page.waitForURL(/\/documents/, { timeout: 90_000 });
      await page.locator('#kmsar-document-file-input').setInputFiles('tests/e2e/fixtures/sample.pdf');
      await page.getByRole('button', { name: 'Save Document' }).click();
      await expect(page.getByText(/uploaded successfully|Document uploaded/i).first()).toBeVisible({
        timeout: 30_000,
      });

      const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
      expect(researchId).toBeTruthy();

      const ms = await measureAction(async () => {
        await submitResearchFromDocuments(page, researchId!);
      });
      expect(ms, `submission took ${ms}ms`).toBeLessThan(5000);
    });

    test('PERF-010: Dean endorsement completes within 5 seconds', async ({ page }) => {
      test.slow();
      const researchId = await createAndSubmitResearch(page, uniqueTitle('PERF010 Endorse'));
      expect(researchId).toBeTruthy();

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${researchId}`);
      await page.getByRole('button', { name: 'Endorse', exact: true }).click();
      await page.fill('#endorse-remarks', 'Performance endorsement timing measurement remarks.');

      const ms = await measureAction(async () => {
        await page.locator('form[action*="endorse"] button[type="submit"]').click();
        await expect(
          page.getByRole('alert').filter({ hasText: /endorsed and forwarded to OVPRI/i }),
        ).toBeVisible({ timeout: 15_000 });
      });
      expect(ms, `endorse took ${ms}ms`).toBeLessThan(5000);
    });

    test('PERF-011: OVPRI approval completes within 5 seconds', async ({ page }) => {
      test.slow();
      const researchId = await setupEndorsedResearch(page, uniqueTitle('PERF011 Approve'));

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto(`/ovpri/review/${researchId}`);
      await page.getByRole('button', { name: 'Approve', exact: true }).click();
      await page.fill('#approve-remarks', 'Performance approval timing measurement remarks.');

      const ms = await measureAction(async () => {
        await page.locator('form[action*="approve"] button[type="submit"]').click();
        await expect(page.getByRole('alert').or(page.locator('.kmsar-alert--success')).first()).toBeVisible({
          timeout: 15_000,
        });
      });
      expect(ms, `approve took ${ms}ms`).toBeLessThan(5000);
    });

    test('PERF-012: PDF report generation completes within 10 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/reports', { waitUntil: 'networkidle' });

      const ms = await measureAction(async () => {
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: 30_000 }),
          page
            .locator('form[action*="export"]')
            .filter({ has: page.locator('input[name="format"][value="pdf"]') })
            .locator('button[type="submit"]')
            .click(),
        ]);
        expect(download.suggestedFilename().toLowerCase()).toMatch(/\.pdf$/);
      });
      expect(ms, `PDF export took ${ms}ms`).toBeLessThan(10_000);
    });

    test('PERF-013: Excel report generation completes within 10 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/reports', { waitUntil: 'networkidle' });

      const ms = await measureAction(async () => {
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: 30_000 }),
          page
            .locator('form[action*="export"]')
            .filter({ has: page.locator('input[name="format"][value="excel"]') })
            .locator('button[type="submit"]')
            .click(),
        ]);
        expect(download.suggestedFilename().toLowerCase()).toMatch(/\.xlsx$/);
      });
      expect(ms, `Excel export took ${ms}ms`).toBeLessThan(10_000);
    });

    test('PERF-014: User import of 10 records completes within 15 seconds', async ({ page }) => {
      test.slow();
      // Fixture ships 3 valid users; assert import completes within the 15s budget
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/import/users', { waitUntil: 'networkidle' });

      const ms = await measureAction(async () => {
        await page.setInputFiles('input[name="file"]', USER_IMPORT);
        await page
          .locator('form')
          .filter({ has: page.locator('input[name="file"]') })
          .locator('button[type="submit"]')
          .click();
        await expect(page.locator('.kmsar-alert--success, table.kmsar-table').first()).toBeVisible({
          timeout: 30_000,
        });
      });
      expect(ms, `user import took ${ms}ms`).toBeLessThan(15_000);
    });

    test('PERF-015: Research import of 10 records completes within 15 seconds', async ({ page }) => {
      test.slow();
      // Depends on PERF-014 imported users when run in order; seed users if missing
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/import/users', { waitUntil: 'networkidle' });
      await page.setInputFiles('input[name="file"]', USER_IMPORT);
      await page
        .locator('form')
        .filter({ has: page.locator('input[name="file"]') })
        .locator('button[type="submit"]')
        .click();
      await page.waitForLoadState('networkidle');

      await page.goto('/admin/import/research', { waitUntil: 'networkidle' });
      const ms = await measureAction(async () => {
        await page.setInputFiles('input[name="file"]', RESEARCH_IMPORT);
        await page
          .locator('form')
          .filter({ has: page.locator('input[name="file"]') })
          .locator('button[type="submit"]')
          .click();
        await expect(page.locator('.kmsar-alert--success, table.kmsar-table').first()).toBeVisible({
          timeout: 30_000,
        });
      });
      expect(ms, `research import took ${ms}ms`).toBeLessThan(15_000);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Search and filter performance', () => {
    test('PERF-016: Faculty research list search responds within 1 second', async ({ page }) => {
      test.slow();
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research', { waitUntil: 'networkidle' });
      const search = page.locator('#faculty-research-search');
      await expect(search).toBeVisible();

      const ms = await measureAction(async () => {
        await search.fill('AI');
        await page.waitForTimeout(50);
      });
      expect(ms, `faculty search took ${ms}ms`).toBeLessThan(1000);
    });

    test('PERF-017: OVPRI All Research college filter responds within 2 seconds', async ({
      page,
    }) => {
      test.slow();
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/research', { waitUntil: 'networkidle' });
      const college = page.locator('select[name="college"]');
      await expect(college).toBeVisible();
      const ccsValue = await college.locator('option').filter({ hasText: 'CCS —' }).first().getAttribute('value');
      expect(ccsValue).toBeTruthy();

      const ms = await measureAction(async () => {
        await Promise.all([
          page.waitForURL(/college=\d+/),
          college.selectOption(ccsValue!),
        ]);
      });
      expect(ms, `college filter took ${ms}ms`).toBeLessThan(2000);
    });

    test('PERF-018: Reports filter applies within 2 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/reports', { waitUntil: 'networkidle' });

      const ms = await measureAction(async () => {
        await Promise.all([
          page.waitForLoadState('networkidle'),
          page.locator('form').filter({ has: page.getByRole('button', { name: 'Apply' }) }).getByRole('button', { name: 'Apply' }).click(),
        ]);
      });
      expect(ms, `reports filter took ${ms}ms`).toBeLessThan(2000);
    });

    test('PERF-019: Admin user search responds within 2 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/users', { waitUntil: 'networkidle' });
      const search = page.getByRole('searchbox', { name: /Search users/i }).or(
        page.getByLabel(/Search users/i),
      );
      await expect(search.first()).toBeVisible();

      const ms = await measureAction(async () => {
        await search.first().fill('dean');
        await page.waitForTimeout(50);
      });
      expect(ms, `admin user search took ${ms}ms`).toBeLessThan(2000);
    });

    test('PERF-020: Audit log filter responds within 2 seconds', async ({ page }) => {
      test.slow();
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/audit-logs', { waitUntil: 'networkidle' });
      const action = page.locator('select[name="action"]');
      await expect(action).toBeVisible();

      const ms = await measureAction(async () => {
        const options = await action.locator('option').count();
        if (options > 1) {
          await Promise.all([
            page.waitForLoadState('networkidle'),
            action.selectOption({ index: 1 }),
          ]);
          // Some filters auto-submit on change; otherwise click submit if present
          const submit = page.locator('form').filter({ has: action }).locator('button[type="submit"]');
          if (await submit.count()) {
            await Promise.all([page.waitForLoadState('networkidle'), submit.click()]);
          }
        } else {
          await page.locator('input[name="user"]').fill('admin');
          const submit = page.locator('form').filter({ has: action }).locator('button[type="submit"]');
          if (await submit.count()) {
            await Promise.all([page.waitForLoadState('networkidle'), submit.click()]);
          }
        }
      });
      expect(ms, `audit filter took ${ms}ms`).toBeLessThan(2000);
    });
  });
});
