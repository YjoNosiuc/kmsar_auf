import { test, expect, Page } from '@playwright/test';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { login, logout, credentials } from './helpers/auth';
import { runTinker } from './helpers/db';
import { createAndSubmitResearch } from './helpers/research';

const FIXTURES = path.resolve('tests/e2e/fixtures');
const SAMPLE_PDF = path.join(FIXTURES, 'sample.pdf');
const SAMPLE_JPG = path.join(FIXTURES, 'sample.jpg');
const FAKE_HTML_PDF = path.join(FIXTURES, 'fake-html.pdf');
const MALWARE_EXE = path.join(FIXTURES, 'malware.exe');
const MALWARE_SH = path.join(FIXTURES, 'malware.sh');

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

function ensureOversizePdfFixture(): string {
  const filePath = path.join(os.tmpdir(), 'kmsar-e2e-oversize-101mb.pdf');
  const targetBytes = 101 * 1024 * 1024;
  if (!fs.existsSync(filePath) || fs.statSync(filePath).size < targetBytes) {
    const fd = fs.openSync(filePath, 'w');
    fs.writeSync(fd, Buffer.from('%PDF-1.4\n%\xe2\xe3\xcf\xd3\n'));
    fs.ftruncateSync(fd, targetBytes);
    fs.closeSync(fd);
  }
  return filePath;
}

async function fillStep1Basics(page: Page, title: string): Promise<void> {
  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
}

async function startWizardAtDocuments(page: Page, title: string): Promise<string> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto('/research/create');
  await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
  await fillStep1Basics(page, title);
  await Promise.all([
    page.waitForURL(/\/authors/, { timeout: 90_000 }),
    page.getByRole('button', { name: 'Continue to authors' }).click(),
  ]);
  const primaryCheckbox = page.locator('.authors-primary-author-toggle input[type="checkbox"]');
  if (await primaryCheckbox.count()) {
    await primaryCheckbox.check();
  }
  await Promise.all([
    page.waitForURL(/\/documents/, { timeout: 90_000 }),
    page.getByRole('button', { name: 'Continue to documents' }).click(),
  ]);
  return page.url().match(/\/research\/(\d+)\//)?.[1] ?? '';
}

async function openDeanReturnModal(page: Page, researchId: string): Promise<void> {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
  await page.goto(`/approval/${researchId}`);
  await page.getByRole('button', { name: 'Return', exact: true }).click();
  await page.locator('#return-remarks').waitFor({ state: 'visible', timeout: 15_000 });
}

async function openDeanRejectModal(page: Page, researchId: string): Promise<void> {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
  await page.goto(`/approval/${researchId}`);
  await page.getByRole('button', { name: 'Reject', exact: true }).click();
  await page.locator('#reject-remarks').waitFor({ state: 'visible', timeout: 15_000 });
}

test.describe('Security & sad-path — UAT', () => {
  test.describe.configure({ timeout: 120_000 });

  test.beforeAll(() => {
    ensureOversizePdfFixture();
    if (!fs.existsSync(SAMPLE_JPG)) {
      fs.writeFileSync(
        SAMPLE_JPG,
        Buffer.from(
          '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGcP//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z',
          'base64',
        ),
      );
    }
    if (!fs.existsSync(FAKE_HTML_PDF)) {
      fs.writeFileSync(FAKE_HTML_PDF, '<html><body><script>alert(1)</script>not a real pdf</body></html>');
    }
    if (!fs.existsSync(MALWARE_EXE)) {
      fs.writeFileSync(MALWARE_EXE, 'MZ fake executable content');
    }
    if (!fs.existsSync(MALWARE_SH)) {
      fs.writeFileSync(MALWARE_SH, '#!/bin/sh\necho pwned\n');
    }
  });

  // -------------------------------------------------------------------------
  test.describe('Form validation — sad paths', () => {
    test('SEC-001: Login with empty email → validation error shown', async ({ page }) => {
      await page.goto('/login');
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/login/);
      const message = await page.locator('input[name="login"]').evaluate(
        (el: HTMLInputElement) => el.validationMessage,
      );
      expect(message.length).toBeGreaterThan(0);
    });

    test('SEC-002: Login with empty password → validation error shown', async ({ page }) => {
      await page.goto('/login');
      await page.fill('input[name="login"]', credentials.faculty_ccs.email);
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/login/);
      const message = await page.locator('input[name="password"]').evaluate(
        (el: HTMLInputElement) => el.validationMessage,
      );
      expect(message.length).toBeGreaterThan(0);
    });

    test('SEC-003: Login with valid email but wrong password → error message shown', async ({
      page,
    }) => {
      await page.goto('/login');
      await page.fill('input[name="login"]', credentials.faculty_ccs.email);
      await page.fill('input[name="password"]', 'wrong-password-xyz');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/login/);
      await expect(page.getByText(/credentials do not match/i)).toBeVisible();
    });

    test('SEC-004: Login with deactivated account → specific error shown', async ({ page }) => {
      runTinker(
        "App\\Models\\User::where('email', 'faculty.ccs3@yopmail.com')->update(['is_active' => false]);",
      );
      await page.goto('/login');
      await page.fill('input[name="login"]', 'faculty.ccs3@yopmail.com');
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/login/);
      await expect(page.getByText(/This account is inactive/i)).toBeVisible();
    });

    test('SEC-005: Register with duplicate email → validation error shown', async ({ page }) => {
      await page.goto('/register');
      await page.fill('#first_name', 'SEC');
      await page.fill('#last_name', 'DUPLICATE');
      await page.fill('#employee_number', `SEC${Date.now()}`.slice(0, 20));
      await page.locator('#college_id').selectOption({ index: 1 });
      await page.locator('#user_type').selectOption('faculty');
      await page.fill('#email', credentials.faculty_ccs.email);
      await page.fill('#password', 'password123');
      await page.fill('#password_confirmation', 'password123');
      await page.click('button[type="submit"]');
      await expect(page.getByText(/already been taken|has already been taken|unique/i)).toBeVisible({
        timeout: 15_000,
      });
    });

    test('SEC-006: Research title with special characters (< > " \' &) → stored safely without XSS', async ({
      page,
    }) => {
      const special = `SEC006 <script> & "quotes" 'apos' ${Date.now()}`;
      page.on('dialog', () => {
        throw new Error('XSS executed!');
      });

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await fillStep1Basics(page, special);
      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page).toHaveURL(/\/authors/, { timeout: 30_000 });

      const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
      expect(researchId).toBeTruthy();
      await page.goto(`/research/${researchId}`);
      await expect(page.getByText(/SEC006/i).first()).toBeVisible();
      const html = await page.content();
      expect(html).not.toMatch(/<script>\s*alert/i);
      expect(html).toMatch(/&lt;script&gt;|&amp;|&quot;|&#039;|&#39;|SEC006/i);
    });

    test('SEC-007: Research title with extremely long text (1000+ chars) → validation error or truncated safely', async ({
      page,
    }) => {
      const longTitle = `SEC007 LONG ${'A'.repeat(1200)}`;
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await fillStep1Basics(page, longTitle);
      await page.getByRole('button', { name: 'Continue to authors' }).click();

      const errorVisible = await page
        .locator('.kmsar-form-error, .kmsar-alert--danger')
        .first()
        .isVisible()
        .catch(() => false);

      if (errorVisible) {
        await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible();
        return;
      }

      await expect(page).toHaveURL(/\/authors/, { timeout: 30_000 });
      const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
      expect(researchId).toBeTruthy();
      await page.goto(`/research/${researchId}`);
      await expect(page.getByText(/SEC007 LONG/i).first()).toBeVisible();
      await expect(page.locator('.kmsar-alert--danger')).toHaveCount(0);
    });

    test('SEC-008: Submit research wizard Step 1 with all required fields missing → all validation errors shown', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });

      const detailsForm = page.locator('form').filter({ has: page.locator('textarea[name="title"]') });
      // Bypass HTML5 so server-side validation runs for empty/missing required fields
      await detailsForm.evaluate((form: HTMLFormElement) => {
        form.setAttribute('novalidate', 'novalidate');
      });
      await page.fill('textarea[name="title"]', '');
      await page.locator('select[name="research_classification"]').selectOption({ index: 0 });
      await page.locator('input[name="expected_output[]"][value="publication"]').uncheck();
      await page.locator('input[name="start_date"]').fill('');
      await page.locator('input[name="estimated_completion_date"]').fill('');
      await page.locator('input[name="sdg_tags"]').evaluate((el: HTMLInputElement) => {
        el.value = '[]';
      });

      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible({
        timeout: 15_000,
      });
      const errorText = (
        await page.locator('.kmsar-form-error, .kmsar-alert--danger, .kmsar-alert li').allTextContents()
      )
        .join(' ')
        .toLowerCase();
      expect(errorText.length).toBeGreaterThan(0);
      expect(errorText).toMatch(/title|required|sdg|classification|date|output|status|field/i);
    });

    test('SEC-009: Submit research with past estimated completion date (before start date) → validation error', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await page.fill('textarea[name="title"]', uniqueTitle('SEC009 Dates'));
      await page.selectOption('select[name="research_classification"]', 'internally_funded');
      await page.check('input[name="expected_output[]"][value="publication"]');
      await page.fill('input[name="start_date"]', '2026-06-01');
      await page.fill('input[name="estimated_completion_date"]', '2026-01-01');
      await page.selectOption('select[name="status"]', 'proposal');
      await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(
        page.getByText(/after or equal|estimated completion|must be a date after/i).first(),
      ).toBeVisible();
    });

    test('SEC-010: Upload file exceeding 100MB limit → rejected with error message', async ({
      page,
    }) => {
      const oversize = ensureOversizePdfFixture();
      await startWizardAtDocuments(page, uniqueTitle('SEC010 Oversize'));
      await page.locator('#kmsar-document-file-input').setInputFiles(oversize);
      await page.getByRole('button', { name: 'Save Document' }).click();
      await expect(page.locator('.kmsar-alert--danger, .kmsar-form-error').first()).toBeVisible({
        timeout: 60_000,
      });
      await expect(
        page.getByText(/may not be greater|too large|100|max|kilobytes|size/i).first(),
      ).toBeVisible({ timeout: 5_000 });
    });

    test('SEC-011: Upload non-PDF/non-document file type (e.g. .exe, .sh) → rejected with error message', async ({
      page,
    }) => {
      await startWizardAtDocuments(page, uniqueTitle('SEC011 Bad Ext'));
      await page.locator('#kmsar-document-file-input').setInputFiles(MALWARE_EXE);
      await page.getByRole('button', { name: 'Save Document' }).click();
      await expect(page.locator('.kmsar-alert--danger, .kmsar-form-error').first()).toBeVisible({
        timeout: 15_000,
      });

      await page.locator('#kmsar-document-file-input').setInputFiles(MALWARE_SH);
      await page.getByRole('button', { name: 'Save Document' }).click();
      await expect(page.locator('.kmsar-alert--danger, .kmsar-form-error').first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('SEC-012: Endorse with empty remarks → now succeeds (remarks optional)', async ({
      page,
    }) => {
      const title = uniqueTitle('SEC012 Empty Endorse');
      const researchId = await createAndSubmitResearch(page, title);
      expect(researchId).toBeTruthy();

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${researchId}`);
      await page.getByRole('button', { name: 'Endorse', exact: true }).click();
      await page.locator('#endorse-remarks').fill('');
      await page.locator('form[action*="endorse"] button[type="submit"]').click();
      await expect(
        page.getByRole('alert').filter({ hasText: /endorsed and forwarded to OVPRI/i }),
      ).toBeVisible({ timeout: 15_000 });
    });

    test('SEC-013: Return research with empty remarks → validation error (remarks required for return)', async ({
      page,
    }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('SEC013 Empty Return'));
      expect(researchId).toBeTruthy();
      await openDeanReturnModal(page, researchId!);

      await page.locator('#return-remarks').evaluate((el: HTMLTextAreaElement) => {
        el.removeAttribute('required');
        el.removeAttribute('minlength');
        el.value = '';
      });
      await page.locator('form[action*="return"] button[type="submit"]').click();
      await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.getByText(/required|at least 4|remarks/i).first()).toBeVisible();
    });

    test('SEC-014: Return research with only 3 chars remarks → validation error (min 4 chars)', async ({
      page,
    }) => {
      test.slow();
      const researchId = await createAndSubmitResearch(page, uniqueTitle('SEC014 Short Return'));
      expect(researchId).toBeTruthy();
      await openDeanReturnModal(page, researchId!);

      // Keep HTML5 minlength — short remarks must not submit; avoid fragile page-wide /min/ text matches
      await page.locator('#return-remarks').fill('abc');
      await page.locator('form[action*="return"] button[type="submit"]').click();

      await expect(
        page.locator('.kmsar-modal').filter({ hasText: /Return for revision/i }),
      ).toBeVisible({ timeout: 10_000 });

      const remarks = page.locator('#return-remarks');
      await expect(remarks).toHaveAttribute('minlength', '4');
      const tooShort = await remarks.evaluate(
        (el: HTMLTextAreaElement) => el.validity.tooShort || !el.checkValidity(),
      );
      expect(tooShort).toBe(true);
    });

    test('SEC-015: Reject research with empty remarks → validation error (remarks required for reject)', async ({
      page,
    }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('SEC015 Empty Reject'));
      expect(researchId).toBeTruthy();
      await openDeanRejectModal(page, researchId!);

      await page.locator('#reject-remarks').evaluate((el: HTMLTextAreaElement) => {
        el.removeAttribute('required');
        el.value = '';
      });
      await page.locator('form[action*="reject"] button[type="submit"]').click();
      await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.getByText(/required|remarks/i).first()).toBeVisible();
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Access control — direct URL manipulation', () => {
    test('SEC-016: Faculty directly accessing /approval/1 → 403 or redirect', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const response = await page.goto('/approval/1');
      const status = response?.status() ?? 0;
      expect([403, 302, 404].includes(status) || page.url().includes('/login')).toBeTruthy();
      if (status === 403) {
        await expect(page.getByText(/403|Forbidden|not authorized|access denied/i).first()).toBeVisible();
      }
    });

    test('SEC-017: Faculty directly accessing /ovpri/queue → 403', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const response = await page.goto('/ovpri/queue');
      expect(response?.status()).toBe(403);
    });

    test('SEC-018: Faculty directly accessing /admin/users → 403', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const response = await page.goto('/admin/users');
      expect(response?.status()).toBe(403);
    });

    test('SEC-019: Dean directly accessing /research/create → 403', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const response = await page.goto('/research/create');
      expect(response?.status()).toBe(403);
    });

    test('SEC-020: Dean directly accessing /admin/dashboard → 403', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const response = await page.goto('/admin/dashboard');
      expect(response?.status()).toBe(403);
    });

    test('SEC-021: OVPRI directly accessing /admin/users → 403', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const response = await page.goto('/admin/users');
      expect(response?.status()).toBe(403);
    });

    test('SEC-022: Unauthenticated user accessing /research → redirected to login', async ({
      page,
    }) => {
      const response = await page.goto('/research');
      expect(page.url()).toMatch(/\/login/);
      expect(response?.status()).toBeLessThan(500);
    });

    test('SEC-023: Unauthenticated user accessing /api or any protected route → redirected to login', async ({
      page,
    }) => {
      await page.goto('/dean/dashboard');
      await expect(page).toHaveURL(/\/login/);

      await page.goto('/admin/dashboard');
      await expect(page).toHaveURL(/\/login/);

      await page.goto('/ovpri/dashboard');
      await expect(page).toHaveURL(/\/login/);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('File upload security', () => {
    test('SEC-024: Upload a file with .pdf extension but wrong MIME type (text/html content) → rejected or handled safely', async ({
      page,
    }) => {
      await startWizardAtDocuments(page, uniqueTitle('SEC024 Fake MIME'));
      await page.locator('#kmsar-document-file-input').setInputFiles(FAKE_HTML_PDF);
      await page.getByRole('button', { name: 'Save Document' }).click();
      await expect(page.locator('.kmsar-alert--danger, .kmsar-form-error').first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(
        page.getByText(/does not match|not allowed|file type|contents|mime/i).first(),
      ).toBeVisible();
    });

    test('SEC-025: Upload a file with .jpg extension → accepted (image files allowed) or rejected based on whitelist', async ({
      page,
    }) => {
      await startWizardAtDocuments(page, uniqueTitle('SEC025 JPG'));
      await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_JPG);
      await page.getByRole('button', { name: 'Save Document' }).click();

      const success = page.getByText(/uploaded successfully|Document uploaded/i).first();
      const failure = page.locator('.kmsar-alert--danger, .kmsar-form-error').first();
      await expect(success.or(failure)).toBeVisible({ timeout: 15_000 });

      if (await success.isVisible().catch(() => false)) {
        await expect(page.getByText(/sample\.jpg|\.jpg/i).first()).toBeVisible();
      }
    });

    test('SEC-026: Document preview loads in modal not new tab (regression check)', async ({
      page,
      context,
    }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('SEC026 Preview'));
      expect(researchId).toBeTruthy();

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${researchId}`);
      await page.getByRole('tab', { name: /Documents/i }).click();

      const pagesBefore = context.pages().length;
      await page.getByRole('button', { name: 'Preview' }).first().click();
      await expect(page.locator('#kmsar-preview-modal')).toBeVisible();
      expect(context.pages().length).toBe(pagesBefore);
    });

    test('SEC-027: Download link serves correct file with correct headers', async ({ page }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('SEC027 Download'));
      expect(researchId).toBeTruthy();

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${researchId}`);
      await page.getByRole('tab', { name: /Documents/i }).click();

      const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Download' }).first().click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.pdf$/i);

      const failure = await download.failure();
      expect(failure).toBeNull();
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Session security', () => {
    test('SEC-028: After logout, direct URL access redirects to login (not cached page)', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await expect(page).toHaveURL(/\/research/);
      await logout(page);
      await page.goto('/research');
      await expect(page).toHaveURL(/\/login/);
      await page.goBack();
      await page.reload();
      await expect(page.locator('input[name="login"]')).toBeVisible();
    });

    test('SEC-029: Session cookie has correct attributes (httponly)', async ({ page, context }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const cookies = await context.cookies();
      const sessionCookie = cookies.find(
        (c) => /session/i.test(c.name) || c.name === 'laravel_session',
      );
      expect(sessionCookie).toBeTruthy();
      expect(sessionCookie!.httpOnly).toBe(true);
    });

    test('SEC-030: CSRF token present on all forms with POST method', async ({ page }) => {
      await page.goto('/login');
      await expect(page.locator('form[method="POST"] input[name="_token"]').first()).toBeAttached();

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      const postForms = page.locator('form[method="post"], form[method="POST"]');
      const count = await postForms.count();
      expect(count).toBeGreaterThan(0);
      for (let i = 0; i < count; i++) {
        await expect(postForms.nth(i).locator('input[name="_token"]')).toHaveCount(1);
      }
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Input sanitization', () => {
    test('SEC-031: Search field with SQL-like input (SELECT * FROM) → no error, treated as plain text search', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      const search = page.locator('#faculty-research-search');
      await expect(search).toBeVisible();
      await search.fill("SELECT * FROM users; DROP TABLE research;--");
      await expect(page.locator('.kmsar-alert--danger')).toHaveCount(0);
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
    });

    test('SEC-032: Research title with HTML tags (<script>alert(1)</script>) → stored and displayed as plain text not executed', async ({
      page,
    }) => {
      const xssTitle = `SEC032 <script>alert(1)</script> ${Date.now()}`;
      page.on('dialog', () => {
        throw new Error('XSS executed!');
      });

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await fillStep1Basics(page, xssTitle);
      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page).toHaveURL(/\/authors/, { timeout: 30_000 });

      const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
      await page.goto(`/research/${researchId}`);
      await expect(page.getByText(/SEC032/i).first()).toBeVisible();
      const bodyHtml = await page.locator('main, .kmsar-main-content, body').first().innerHTML();
      expect(bodyHtml).not.toContain('<script>alert(1)</script>');
      expect(bodyHtml).toMatch(/&lt;script&gt;|SEC032/);
    });

    test('SEC-033: Co-author email field with invalid format → validation error', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await fillStep1Basics(page, uniqueTitle('SEC033 Bad Email'));
      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page).toHaveURL(/\/authors/, { timeout: 30_000 });

      await page.getByRole('button', { name: /Add co-author/i }).click();
      const row = page.locator('.authors-coauthor-card').last();
      await row.locator('input[name*="[first_name]"]:not([disabled])').fill('BAD');
      await row.locator('input[name*="[last_name]"]:not([disabled])').fill('EMAIL');
      const emailInput = row.locator('input[type="email"]:not([disabled])').first();
      await emailInput.fill('not-an-email');

      // Prefer HTML5 type=email failure; also exercise server validation if novalidate is set
      const clientInvalid = await emailInput.evaluate((el: HTMLInputElement) => !el.checkValidity());
      if (clientInvalid) {
        const message = await emailInput.evaluate((el: HTMLInputElement) => el.validationMessage);
        expect(message.length).toBeGreaterThan(0);
        return;
      }

      await page.locator('form').first().evaluate((form: HTMLFormElement) => {
        form.setAttribute('novalidate', 'novalidate');
      });
      await page.getByRole('button', { name: 'Continue to documents' }).click();
      await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible({
        timeout: 15_000,
      });
      await expect(page.getByText(/email|valid|format/i).first()).toBeVisible();
    });

    test('SEC-034: SDG picker with value outside 1-17 range → validation error or ignored', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await page.fill('textarea[name="title"]', uniqueTitle('SEC034 Bad SDG'));
      await page.selectOption('select[name="research_classification"]', 'internally_funded');
      await page.check('input[name="expected_output[]"][value="publication"]');
      await page.fill('input[name="start_date"]', '2026-01-01');
      await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
      await page.selectOption('select[name="status"]', 'proposal');
      await page.getByRole('button', { name: 'SDG 4', exact: true }).click();

      await page.locator('input[name="sdg_tags"]').evaluate((el: HTMLInputElement) => {
        el.value = JSON.stringify([99]);
      });

      await page.getByRole('button', { name: 'Continue to authors' }).click();

      const errorVisible = await page
        .locator('.kmsar-form-error, .kmsar-alert--danger')
        .first()
        .isVisible()
        .catch(() => false);

      if (errorVisible) {
        await expect(page.getByText(/sdg|between|1.*17|invalid/i).first()).toBeVisible();
        return;
      }

      // If server ignored/clamped invalid SDG, wizard should not proceed with invalid payload
      // or should have rejected — staying on details is also acceptable.
      const onAuthors = /\/authors/.test(page.url());
      if (onAuthors) {
        const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
        const tags = runTinker(
          `echo json_encode(\\App\\Models\\Research::find(${researchId})?->sdg_tags);`,
        );
        expect(tags).not.toMatch(/\b99\b/);
      } else {
        await expect(page).toHaveURL(/\/details/);
      }
    });
  });
});
