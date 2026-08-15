import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'
import { bottomNav, expectShell, setLayout } from './helpers/layout'

test.describe('SPA navigation', () => {
  test('mobile: bottom-nav link navigates via Inertia', async ({ page }) => {
    await setLayout(page, { name: 'mobile', width: 375, height: 812 }, 'light')
    await login(page)

    // Fortify home is /transactions; the bottom nav links to /budgets etc.
    const { links } = bottomNav(page)
    await links.nth(2).click() // Anggaran

    await expect(page).toHaveURL('/budgets')
    await expect(
      page.getByRole('heading', { name: 'Anggaran' }),
    ).toBeVisible()
    await expectShell(page, 'mobile')

    // Back to transactions through the bottom nav.
    await links.nth(1).click()
    await expect(page).toHaveURL('/transactions')
    await expect(
      page.getByRole('heading', { name: 'Transaksi' }),
    ).toBeVisible()
    await expectShell(page, 'mobile')
  })

  test('desktop: sidebar link navigates via Inertia', async ({ page }) => {
    await setLayout(page, { name: 'desktop', width: 1280, height: 800 }, 'light')
    await login(page)

    await page
      .locator('[data-slot="sidebar"] [data-sidebar="menu-button"]')
      .filter({ hasText: 'Dana Cadangan' })
      .click()

    await expect(page).toHaveURL('/funds')
    await expect(
      page.getByRole('heading', { name: 'Dana Cadangan' }),
    ).toBeVisible()
    await expectShell(page, 'desktop')
  })
})
