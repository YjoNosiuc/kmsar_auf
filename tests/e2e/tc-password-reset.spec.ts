import { test, expect, Page } from '@playwright/test';
import { runTinker } from './helpers/db';

const SEEDED_EMAIL = 'faculty.ccs1@yopmail.com';

function seedOtp(email = SEEDED_EMAIL, otp = '123456', expired = false): void {
  runTinker(
    `\\App\\Models\\PasswordResetOtp::where('email','${email}')->delete(); \\App\\Models\\PasswordResetOtp::create(['email'=>'${email}','otp'=>'${otp}','expires_at'=>now()->${expired ? 'subMinute()' : 'addMinute()'}]);`,
  );
}

function createResetUser(): string {
  const id = Date.now();
  const email = `otp.${id}@auf.edu.ph`;
  runTinker(
    `$c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $u=\\App\\Models\\User::create(['name'=>'OTP USER','first_name'=>'OTP','last_name'=>'USER','employee_number'=>'OTP-${id}','college_id'=>$c->id,'user_type'=>'faculty','email'=>'${email}','password'=>'old-password','is_active'=>true]); $u->assignRole('faculty');`,
  );
  return email;
}

async function openVerify(page: Page, email = SEEDED_EMAIL, otp = '123456'): Promise<void> {
  seedOtp(email, otp);
  await page.goto(`/verify-otp?email=${encodeURIComponent(email)}`);
}

async function fillOtp(page: Page, otp: string): Promise<void> {
  const boxes = page.locator('.otp-input');
  for (let i = 0; i < 5; i++) {
    await boxes.nth(i).fill(otp[i]);
  }
  await boxes.nth(5).evaluate((input: HTMLInputElement, digit) => {
    input.value = String(digit);
  }, otp[5]);
}

test.describe('Password reset OTP — UAT', () => {
  test.describe.configure({ mode: 'serial', timeout: 90_000 });

  test('OTP-001: Forgot password link is visible on login page', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('link', { name: 'Forgot password?' })).toBeVisible();
  });

  test('OTP-002: Forgot password page loads correctly', async ({ page }) => {
    await page.goto('/forgot-password');
    await expect(page.getByRole('heading', { name: 'Forgot password' })).toBeVisible();
    await expect(page.getByLabel('Email address')).toBeVisible();
  });

  test('OTP-003: Valid email shows check-email verification message', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.getByLabel('Email address').fill(SEEDED_EMAIL);
    await page.getByRole('button', { name: 'Send verification code' }).click();
    await expect(page).toHaveURL(/\/verify-otp\?email=/, { timeout: 30_000 });
    await expect(page.getByText(/6-digit verification code has been sent/i)).toBeVisible();
  });

  test('OTP-004: Unknown email shows an error', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.getByLabel('Email address').fill(`missing.${Date.now()}@auf.edu.ph`);
    await page.getByRole('button', { name: 'Send verification code' }).click();
    await expect(page.getByText(/could not find an account/i)).toBeVisible();
  });

  test('OTP-005: Verify page has six individual input boxes', async ({ page }) => {
    await openVerify(page);
    await expect(page.locator('.otp-input')).toHaveCount(6);
  });

  test('OTP-006: Typing a digit advances focus to the next box', async ({ page }) => {
    await openVerify(page);
    await page.locator('.otp-input').first().fill('1');
    await expect(page.locator('.otp-input').nth(1)).toBeFocused();
  });

  test('OTP-007: Backspace on an empty box returns to the previous box', async ({ page }) => {
    await openVerify(page);
    const boxes = page.locator('.otp-input');
    await boxes.first().fill('1');
    await boxes.nth(1).press('Backspace');
    await expect(boxes.first()).toBeFocused();
    await expect(boxes.first()).toHaveValue('');
  });

  test('OTP-008: Invalid OTP shows an error message', async ({ page }) => {
    await openVerify(page, SEEDED_EMAIL, '123456');
    await fillOtp(page, '654321');
    await page.getByRole('button', { name: 'Verify code' }).click();
    await expect(page.getByText(/Invalid or expired code/i)).toBeVisible();
  });

  test('OTP-009: Expired OTP shows expired message', async ({ page }) => {
    seedOtp(SEEDED_EMAIL, '123456', true);
    await page.goto(`/verify-otp?email=${encodeURIComponent(SEEDED_EMAIL)}`);
    await fillOtp(page, '123456');
    await page.getByRole('button', { name: 'Verify code' }).click();
    await expect(page.getByText(/Invalid or expired code/i)).toBeVisible();
  });

  test('OTP-010: Valid OTP redirects to reset password page', async ({ page }) => {
    await openVerify(page);
    await fillOtp(page, '123456');
    await page.getByRole('button', { name: 'Verify code' }).click();
    await expect(page).toHaveURL(/\/reset-password\?email=.*&otp=123456/);
  });

  test('OTP-011: Reset page has password and confirmation fields', async ({ page }) => {
    seedOtp();
    await page.goto(`/reset-password?email=${encodeURIComponent(SEEDED_EMAIL)}&otp=123456`);
    await expect(page.getByLabel('New password')).toBeVisible();
    await expect(page.getByLabel('Confirm password')).toBeVisible();
  });

  test('OTP-012: Password mismatch shows validation error', async ({ page }) => {
    seedOtp();
    await page.goto(`/reset-password?email=${encodeURIComponent(SEEDED_EMAIL)}&otp=123456`);
    await page.getByLabel('New password').fill('new-password-123');
    await page.getByLabel('Confirm password').fill('different-password');
    await page.getByRole('button', { name: 'Reset password' }).click();
    await expect(page.getByText(/confirmation does not match/i)).toBeVisible();
  });

  test('OTP-013: Successful reset redirects to login with success message', async ({ page }) => {
    const email = createResetUser();
    seedOtp(email);
    await page.goto(`/reset-password?email=${encodeURIComponent(email)}&otp=123456`);
    await page.getByLabel('New password').fill('new-password-123');
    await page.getByLabel('Confirm password').fill('new-password-123');
    await page.getByRole('button', { name: 'Reset password' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByText(/Password reset successfully/i)).toBeVisible();
  });

  test('OTP-014: User can log in with the new password', async ({ page }) => {
    const email = createResetUser();
    seedOtp(email);
    await page.goto(`/reset-password?email=${encodeURIComponent(email)}&otp=123456`);
    await page.getByLabel('New password').fill('new-password-123');
    await page.getByLabel('Confirm password').fill('new-password-123');
    await page.getByRole('button', { name: 'Reset password' }).click();
    await page.fill('input[name="login"]', email);
    await page.fill('input[name="password"]', 'new-password-123');
    await page.getByRole('button', { name: /Sign in/i }).click();
    await expect(page).toHaveURL(/\/research/);
  });

  test('OTP-015: Old password no longer works after reset', async ({ page }) => {
    const email = createResetUser();
    seedOtp(email);
    await page.goto(`/reset-password?email=${encodeURIComponent(email)}&otp=123456`);
    await page.getByLabel('New password').fill('new-password-123');
    await page.getByLabel('Confirm password').fill('new-password-123');
    await page.getByRole('button', { name: 'Reset password' }).click();
    await page.fill('input[name="login"]', email);
    await page.fill('input[name="password"]', 'old-password');
    await page.getByRole('button', { name: /Sign in/i }).click();
    await expect(page.getByText(/credentials do not match/i)).toBeVisible();
  });
});
