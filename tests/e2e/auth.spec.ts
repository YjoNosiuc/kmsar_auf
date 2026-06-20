import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, logout, setUserInactive, shortEmployeeNumber } from './helpers/auth';

test.describe('Authentication', () => {
  test('login with valid credentials for faculty redirects to research index', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    await expect(page).toHaveURL(/\/research(\/)?$/);
    await logout(page);
  });

  test('login with valid credentials for dean redirects to dean dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await expect(page).toHaveURL(/\/dean\/dashboard/);
    await logout(page);
  });

  test('login with valid credentials for OVPRI redirects to ovpri dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.ovpri.email);
    await expect(page).toHaveURL(/\/ovpri\/dashboard/);
    await logout(page);
  });

  test('login with valid credentials for CDAIC redirects to ovpri dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.cdaic.email);
    await expect(page).toHaveURL(/\/ovpri\/dashboard/);
    await logout(page);
  });

  test('login with valid credentials for admin redirects to admin dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.admin.email);
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await logout(page);
  });

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);
    await page.locator('#login').fill(CREDENTIALS.faculty.email);
    await page.locator('#password').fill('wrong-password');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(`${BASE_URL}/login`);
    await expect(page.getByText(/credentials do not match/i)).toBeVisible();
  });

  test('login with inactive account shows error', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.inactive.${stamp}@auf.edu.ph`;

    await login(page, CREDENTIALS.admin.email);
    await page.goto(`${BASE_URL}/admin/users`);
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('AE2E', stamp));
    await page.locator('#add-first_name').fill('E2E');
    await page.locator('#add-last_name').fill('Inactive');
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

  test('logout redirects to login', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    await logout(page);
    await expect(page.locator('h2.kmsar-login-heading', { hasText: 'Sign in' })).toBeVisible();
  });

  test('H-02: after logout, browser back button redirects to login', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    await expect(page).toHaveURL(/\/research/);

    await logout(page);
    await page.goBack();
    await page.reload();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('h2.kmsar-login-heading', { hasText: 'Sign in' })).toBeVisible();
  });
});
