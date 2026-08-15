import type { Page } from '@playwright/test'

export async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel('Email address', { exact: true }).fill('e2e@et.local')
  await page.getByLabel('Password', { exact: true }).fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  // Fortify home is /transactions (config/fortify.php) — wait for any
  // authenticated URL, not a hardcoded path.
  await page.waitForURL((u) => u.pathname !== '/login')
}
