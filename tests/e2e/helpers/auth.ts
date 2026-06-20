import { Page } from '@playwright/test';

export const credentials = {
  faculty_ccs: { email: 'faculty.ccs1@auf.edu.ph', password: 'password' },
  faculty_cba: { email: 'faculty.cba1@auf.edu.ph', password: 'password' },
  dean_ccs: { email: 'dean.ccs@auf.edu.ph', password: 'password' },
  dean_cba: { email: 'dean.cba@auf.edu.ph', password: 'password' },
  ovpri: { email: 'ovpri@auf.edu.ph', password: 'password' },
  cdaic: { email: 'cdaic@auf.edu.ph', password: 'password' },
  admin: { email: 'admin@auf.edu.ph', password: 'password' },
};

export async function login(page: Page, email: string, password: string) {
  await page.goto('/login');

  const loginInput = page.locator('input[name="login"]');
  const onLoginForm = await loginInput.isVisible({ timeout: 3_000 }).catch(() => false);

  if (!onLoginForm) {
    const signOut = page.getByRole('button', { name: /sign out/i });
    if (await signOut.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await signOut.click();
      await page.waitForURL(
        (url) => url.pathname.endsWith('/login') || url.pathname === '/',
        { timeout: 15_000 },
      );
    }
    await page.goto('/login');
  }

  await page.fill('input[name="login"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 });
}

export async function logout(page: Page) {
  await page.click('button[type="submit"]:has-text("Sign Out")');
  await page.waitForURL((url) => url.pathname.endsWith('/login') || url.pathname === '/', {
    timeout: 15_000,
  });
  if (!page.url().includes('/login')) {
    await page.goto('/login');
  }
}
