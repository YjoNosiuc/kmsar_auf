import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, logout, setUserInactive, shortEmployeeNumber } from './helpers/auth';

test.describe('Admin management', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.admin.email);
  });

  test('admin dashboard shows research breakdown by status', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/dashboard`);
    await expect(page.getByRole('heading', { name: /Admin Dashboard/i })).toBeVisible();
    await expect(page.getByText('Research by approval stage')).toBeVisible();
    await expect(page.getByLabel('Research approval stage breakdown').getByText('Draft')).toBeVisible();
    await expect(page.getByText('Dean review').first()).toBeVisible();
    await expect(page.getByText('OVPRI review').first()).toBeVisible();
    await expect(page.getByText('Approved').first()).toBeVisible();
    await expect(page.getByText('Rejected').first()).toBeVisible();
  });

  test('L-04: active college count updates when college toggled inactive', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/dashboard`);
    const collegesCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Total colleges' });
    const beforeCount = parseInt(
      (await collegesCard.locator('.kmsar-stat-card-value').innerText()).replace(/,/g, ''),
      10,
    );

    await page.goto(`${BASE_URL}/admin/colleges`);
    await page.getByRole('button', { name: 'Edit' }).first().click();
    await expect(page.locator('#form-edit-college')).toBeVisible();
    const activeCheckbox = page.locator('#form-edit-college input[name="is_active"][type="checkbox"]');
    const wasActive = await activeCheckbox.isChecked();
    await page.locator('#form-edit-college .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 15_000 });

    await page.goto(`${BASE_URL}/admin/dashboard`);
    const afterCount = parseInt(
      (await page.locator('.kmsar-stat-card').filter({ hasText: 'Total colleges' }).locator('.kmsar-stat-card-value').innerText()).replace(/,/g, ''),
      10,
    );

    if (wasActive) {
      expect(afterCount).toBe(beforeCount - 1);
    } else {
      expect(afterCount).toBe(beforeCount + 1);
    }

    await page.goto(`${BASE_URL}/admin/colleges`);
    await page.getByRole('button', { name: 'Edit' }).first().click();
    await expect(page.locator('#form-edit-college')).toBeVisible();
    await page.locator('#form-edit-college .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 15_000 });
  });

  test('create user with office field for non-college unit', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.unithead.${stamp}@auf.edu.ph`;

    await page.goto(`${BASE_URL}/admin/users`);
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('AUH', stamp));
    await page.locator('#add-first_name').fill('E2E');
    await page.locator('#add-last_name').fill('UnitHead');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('unit_head');
    await page.locator('#add-office').fill('OVPRI');
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText('User created successfully')).toBeVisible({ timeout: 15_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('OVPRI');
  });

  test('L-03: edit user modal is scrollable', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`);
    await page.getByRole('button', { name: 'Edit' }).first().click();
    const scrollableForm = page.locator('#form-edit-user');
    await expect(scrollableForm).toBeVisible();

    const overflowY = await scrollableForm.evaluate((el) => getComputedStyle(el).overflowY);
    expect(['auto', 'scroll']).toContain(overflowY);
  });

  test('deactivate user — cannot login', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.deactivate.${stamp}@auf.edu.ph`;

    await page.goto(`${BASE_URL}/admin/users`);
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('AD', stamp));
    await page.locator('#add-first_name').fill('Deact');
    await page.locator('#add-last_name').fill('User');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await page.locator('#add-college_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText('User created successfully')).toBeVisible({ timeout: 15_000 });

    await setUserInactive(page, email);
    await logout(page);

    await page.goto(`${BASE_URL}/login`);
    await page.locator('#login').fill(email);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(`${BASE_URL}/login`);
    await expect(page.getByText(/This account is inactive/i)).toBeVisible();
  });
});
