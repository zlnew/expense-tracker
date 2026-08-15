import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

test.describe('auth smoke', () => {
  test('logs in through the Fortify UI and lands on the dashboard', async ({ page }) => {
    const pageErrors: string[] = []
    page.on('pageerror', (err) => pageErrors.push(err.message))

    await login(page)

    // Fortify home is /transactions (config/fortify.php).
    await expect(page).toHaveURL('/transactions')
    await expect(
      page.getByRole('heading', { name: 'Transaksi' }),
    ).toBeVisible()

    expect(pageErrors, 'no pageerror during login flow').toEqual([])
  })
})
