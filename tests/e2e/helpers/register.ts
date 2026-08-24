import { expect, Page } from '@playwright/test';
import { runTinker } from './db';

export function latestOtpForEmail(email: string): string {
  return (
    runTinker(
      `echo \\App\\Models\\PasswordResetOtp::where('email','${email}')->latest('id')->value('otp');`,
    )
      .trim()
      .split(/\r?\n/)
      .pop()
      ?.trim() ?? ''
  );
}

export async function completeRegistrationOtp(page: Page, email: string): Promise<void> {
  await expect(page).toHaveURL(/\/verify-email/);
  await expect(page.getByText(email)).toBeVisible();

  const otp = latestOtpForEmail(email);
  expect(otp).toMatch(/^\d{6}$/);

  const boxes = page.locator('.otp-input');
  for (let i = 0; i < 6; i++) {
    await boxes.nth(i).fill(otp[i] ?? '');
  }

  await expect(page).toHaveURL(/\/login/, { timeout: 15_000 });
  await expect(page.getByText(/pending approval|verified/i)).toBeVisible();
}
