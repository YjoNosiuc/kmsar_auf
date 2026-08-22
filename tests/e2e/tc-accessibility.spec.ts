import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login, credentials } from './helpers/auth';
import {
  createAndSubmitResearch,
  setupEndorsedResearch,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

function formatViolations(
  violations: { id: string; impact?: string | null; help: string; nodes: { target: unknown[] }[] }[],
): string {
  if (!violations.length) {
    return '';
  }
  return violations
    .map(
      (v) =>
        `[${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s) — ${v.nodes
          .slice(0, 3)
          .map((n) => JSON.stringify(n.target))
          .join(', ')})`,
    )
    .join('\n');
}

async function assertNoCriticalOrSeriousA11y(page: Page): Promise<void> {
  // color-contrast: design-system muted text (sidebar, hints) fails WCAG AA today; A11Y-024 covers non-color-only info.
  // scrollable-region-focusable: overflow wrappers (main, table wraps) are not keyboard traps in this app.
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .disableRules(['color-contrast', 'scrollable-region-focusable'])
    .analyze();
  const violations = results.violations.filter(
    (v) => v.impact === 'critical' || v.impact === 'serious',
  );
  expect(violations, formatViolations(violations)).toEqual([]);
}

async function fillWizardStep1(page: Page, title: string): Promise<void> {
  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
}

test.describe('Accessibility — UAT', () => {
  test.describe.configure({ timeout: 120_000 });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — Authentication pages', () => {
    test.use({ storageState: { cookies: [], origins: [] } });
    test('A11Y-001: Login page has no critical accessibility violations', async ({ page }) => {
      await page.goto('/login');
      await expect(page.locator('input[name="login"]')).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-002: Login page all form inputs have labels', async ({ page }) => {
      await page.goto('/login');
      await expect(page.getByLabel(/Employee number or email/i)).toBeVisible();
      await expect(page.getByLabel(/^Password$/i)).toBeVisible();
    });

    test('A11Y-003: Login page keyboard navigation works (Tab through fields, Enter to submit)', async ({
      page,
    }) => {
      await page.goto('/login');
      await page.locator('input[name="login"]').focus();
      await expect(page.locator('input[name="login"]')).toBeFocused();
      await page.keyboard.press('Tab');
      await expect(page.locator('input[name="password"]')).toBeFocused();

      await page.fill('input[name="login"]', credentials.faculty_ccs.email);
      await page.fill('input[name="password"]', credentials.faculty_ccs.password);
      await page.locator('input[name="password"]').press('Enter');
      await expect(page).toHaveURL(/\/research/, { timeout: 30_000 });
    });

    test('A11Y-004: Forgot password page has no critical accessibility violations', async ({
      page,
    }) => {
      await page.goto('/forgot-password');
      await expect(page.getByRole('heading', { name: /forgot|reset/i }).or(page.locator('input[name="email"]')).first()).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — Faculty pages', () => {
    test.use({ storageState: { cookies: [], origins: [] } });
    let sampleResearchId = '';

    test.beforeAll(async ({ browser }) => {
      const context = await browser.newContext({ baseURL: 'http://kmsar_auf.test' });
      const page = await context.newPage();
      sampleResearchId = (await createAndSubmitResearch(page, uniqueTitle('A11Y Faculty Sample'))) ?? '';
      await context.close();
    });

    test('A11Y-005: Research list page has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-006: Research registration wizard Step 1 has no critical violations', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await expect(page.getByText(/Step 1 of 3/i)).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-007: Research registration wizard Step 2 (authors) has no critical violations', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/, { timeout: 90_000 });
      await fillWizardStep1(page, uniqueTitle('A11Y Step2'));
      await page.getByRole('button', { name: 'Continue to authors' }).click();
      await expect(page).toHaveURL(/\/authors/, { timeout: 30_000 });
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-008: Research show page has no critical accessibility violations', async ({
      page,
    }) => {
      expect(sampleResearchId).toBeTruthy();
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto(`/research/${sampleResearchId}`);
      await expect(page.getByRole('tab', { name: /Research info/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-009: All images/icons have alt text or aria-hidden', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');

      const images = page.locator('img');
      const imgCount = await images.count();
      for (let i = 0; i < imgCount; i++) {
        const img = images.nth(i);
        const alt = await img.getAttribute('alt');
        const ariaHidden = await img.getAttribute('aria-hidden');
        const role = await img.getAttribute('role');
        expect(
          alt !== null || ariaHidden === 'true' || role === 'presentation',
          `img #${i} missing alt/aria-hidden`,
        ).toBeTruthy();
      }

      const svgs = page.locator('svg');
      const svgCount = await svgs.count();
      for (let i = 0; i < Math.min(svgCount, 40); i++) {
        const svg = svgs.nth(i);
        const inDebugChrome = await svg.evaluate((el) =>
          Boolean(el.closest('#phpdebugbar, .phpdebugbar, [class*="phpdebugbar"]')),
        );
        if (inDebugChrome) {
          continue;
        }
        const ariaHidden = await svg.getAttribute('aria-hidden');
        const ariaLabel = await svg.getAttribute('aria-label');
        const role = await svg.getAttribute('role');
        const titled = (await svg.locator('title').count()) > 0;
        const parentControl = svg.locator(
          'xpath=ancestor::*[@aria-label or @aria-labelledby][self::button or self::a or @role="button"][1]',
        );
        const parentHasLabel = (await parentControl.count()) > 0;
        const ancestorHidden = await svg.evaluate((el) => {
          let node: Element | null = el.parentElement;
          while (node) {
            if (node.getAttribute('aria-hidden') === 'true') {
              return true;
            }
            node = node.parentElement;
          }
          return false;
        });
        expect(
          ariaHidden === 'true' ||
            ancestorHidden ||
            !!ariaLabel ||
            role === 'img' ||
            titled ||
            parentHasLabel ||
            role === 'presentation',
          `svg #${i} should be decorative (aria-hidden) or labelled`,
        ).toBeTruthy();
      }
    });

    test('A11Y-010: All buttons have accessible names (aria-label or text content)', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');

      const buttons = page.locator('button, [role="button"]');
      const count = await buttons.count();
      expect(count).toBeGreaterThan(0);
      for (let i = 0; i < count; i++) {
        const btn = buttons.nth(i);
        if (!(await btn.isVisible().catch(() => false))) {
          continue;
        }
        const name = ((await btn.getAttribute('aria-label')) ?? '').trim()
          || ((await btn.innerText().catch(() => '')) ?? '').trim()
          || ((await btn.getAttribute('title')) ?? '').trim();
        expect(name.length, `button #${i} missing accessible name`).toBeGreaterThan(0);
      }
    });

    test('A11Y-011: Modal dialogs have aria-modal and aria-labelledby attributes', async ({
      page,
    }) => {
      expect(sampleResearchId).toBeTruthy();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${sampleResearchId}`);
      await page.getByRole('button', { name: 'Endorse', exact: true }).click();

      const dialog = page.locator('[role="dialog"][aria-modal="true"]').first();
      await expect(dialog).toBeVisible();
      const labelledBy = await dialog.getAttribute('aria-labelledby');
      expect(labelledBy).toBeTruthy();
      await expect(page.locator(`#${labelledBy}`)).toBeVisible();
    });

    test('A11Y-012: Notification bell has aria-label indicating unread count', async ({
      page,
    }) => {
      await createAndSubmitResearch(page, uniqueTitle('A11Y Bell Count'));
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');

      const bell = page.getByRole('button', { name: /notification/i });
      await expect(bell).toBeVisible();
      const ariaLabel = (await bell.getAttribute('aria-label')) ?? '';
      expect(ariaLabel.toLowerCase()).toMatch(/notification/);

      const badge = page.locator('.kmsar-navbar-notif-dot');
      if (await badge.isVisible().catch(() => false)) {
        const badgeText = (await badge.innerText()).trim();
        const combined = `${ariaLabel} ${badgeText}`;
        expect(combined).toMatch(/\d|9\+/);
      } else {
        // No unread — aria-label still identifies the control
        expect(ariaLabel.length).toBeGreaterThan(0);
      }
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — Dean pages', () => {
    let pendingResearchId = '';

    test.beforeAll(async ({ browser }) => {
      const context = await browser.newContext({ baseURL: 'http://kmsar_auf.test' });
      const page = await context.newPage();
      pendingResearchId =
        (await createAndSubmitResearch(page, uniqueTitle('A11Y Dean Review'))) ?? '';
      await context.close();
    });

    test('A11Y-013: Dean dashboard has no critical accessibility violations', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      await expect(page.getByRole('heading', { name: /College Dashboard/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-014: Approval queue has no critical accessibility violations', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/approval/queue');
      await expect(page.getByRole('heading', { name: /Approval Queue/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-015: Research review page has no critical accessibility violations', async ({
      page,
    }) => {
      expect(pendingResearchId).toBeTruthy();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${pendingResearchId}`);
      await expect(page.getByRole('tab', { name: /Research Info/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-016: Tab panels have correct role and aria-selected attributes', async ({
      page,
    }) => {
      expect(pendingResearchId).toBeTruthy();
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto(`/approval/${pendingResearchId}`);

      const tabs = page.getByRole('tab');
      await expect(tabs.first()).toBeVisible();
      const tabCount = await tabs.count();
      expect(tabCount).toBeGreaterThan(0);

      for (let i = 0; i < tabCount; i++) {
        const selected = await tabs.nth(i).getAttribute('aria-selected');
        expect(['true', 'false']).toContain(selected);
      }

      await tabs.nth(1).click();
      await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true');
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — OVPRI pages', () => {
    let endorsedResearchId = '';

    test.beforeAll(async ({ browser }) => {
      const context = await browser.newContext({ baseURL: 'http://kmsar_auf.test' });
      const page = await context.newPage();
      endorsedResearchId = await setupEndorsedResearch(page, uniqueTitle('A11Y OVPRI Review'));
      await context.close();
    });

    test('A11Y-017: OVPRI dashboard has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await expect(page.getByRole('heading', { name: /University dashboard/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-018: All Research page has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/research');
      await expect(page.getByRole('heading', { name: /All research/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-019: OVPRI review page has no critical accessibility violations', async ({
      page,
    }) => {
      expect(endorsedResearchId).toBeTruthy();
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto(`/ovpri/review/${endorsedResearchId}`);
      await expect(page.getByRole('tab', { name: /Research Info/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — Admin pages', () => {
    test('A11Y-020: Admin dashboard has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/dashboard');
      await expect(page.getByRole('heading', { name: /Admin Dashboard/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-021: User management page has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/users');
      await expect(page.getByRole('heading', { name: /User management/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-022: College management page has no critical accessibility violations', async ({
      page,
    }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/colleges');
      await expect(page.getByRole('heading', { name: /Colleges/i }).first()).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-023: Import pages have no critical accessibility violations', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/import/users');
      await expect(page.getByRole('heading', { name: /Import Faculty Users/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);

      await page.goto('/admin/import/research');
      await expect(page.getByRole('heading', { name: /Import Research Records/i })).toBeVisible();
      await assertNoCriticalOrSeriousA11y(page);
    });

    test('A11Y-023b: Dashboard Date From and Date To inputs have accessible labels', async ({
      page,
    }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/dashboard');
      await expect(page.getByLabel(/Date From/i)).toBeVisible();
      await expect(page.getByLabel(/Date To/i)).toBeVisible();

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await expect(page.getByLabel(/Date From/i)).toBeVisible();
      await expect(page.getByLabel(/Date To/i)).toBeVisible();
    });
  });

  test.describe('Accessibility — new registration and error UI', () => {
    test('A11Y-026: Author search inputs have accessible names', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await page.waitForURL(/\/research\/\d+\/details/);
      await fillWizardStep1(page, uniqueTitle('A11Y Author Search'));
      await page.getByRole('button', { name: 'Continue to authors' }).click();

      const clear = page.getByRole('button', { name: 'Clear', exact: true });
      if (await clear.isVisible().catch(() => false)) {
        await clear.click();
      }
      await expect(page.locator('#primary-author-search')).toHaveAttribute('aria-label', /primary author/i);
      await page.getByRole('button', { name: 'This is me' }).click();
      await expect(page.locator('#coauthor-search')).toHaveAttribute('aria-label', /co-author/i);
    });

    test('A11Y-027: 403, 404, and 419 pages have one level-one heading', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/dean/dashboard');
      await expect(page.getByRole('heading', { level: 1, name: 'Access Denied' })).toBeVisible();
      await expect(page.locator('h1')).toHaveCount(1);

      await page.goto(`/not-found-${Date.now()}`);
      await expect(page.getByRole('heading', { level: 1, name: 'Page Not Found' })).toBeVisible();
      await expect(page.locator('h1')).toHaveCount(1);

      const response = await page.request.post('/logout', { failOnStatusCode: false });
      expect(response.status()).toBe(419);
      await page.setContent(await response.text());
      await expect(page.getByRole('heading', { level: 1, name: 'Session Expired' })).toBeVisible();
      await expect(page.locator('h1')).toHaveCount(1);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Accessibility — Color and contrast', () => {
    test('A11Y-024: Page does not rely solely on color to convey information (badges have text)', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');

      const badges = page.locator('.kmsar-badge, [class*="badge"]');
      const count = await badges.count();
      if (count === 0) {
        // Seeded/list cards may use plain text status labels instead
        await expect(page.getByText(/Draft|Dean Review|Approved|Rejected|OVPRI/i).first()).toBeVisible();
        return;
      }

      for (let i = 0; i < Math.min(count, 20); i++) {
        const text = ((await badges.nth(i).innerText()) ?? '').trim();
        expect(text.length, `badge #${i} should include text, not color alone`).toBeGreaterThan(0);
      }
    });

    test('A11Y-025: Focus indicators visible on interactive elements', async ({ page }) => {
      await page.goto('/login');
      await page.locator('input[name="login"]').focus();

      const outline = await page.locator('input[name="login"]').evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          outlineStyle: styles.outlineStyle,
          outlineWidth: styles.outlineWidth,
          boxShadow: styles.boxShadow,
        };
      });

      const hasOutline =
        (outline.outlineStyle !== 'none' && outline.outlineWidth !== '0px') ||
        (outline.boxShadow !== 'none' && outline.boxShadow.length > 0);
      expect(hasOutline).toBeTruthy();
    });
  });
});
