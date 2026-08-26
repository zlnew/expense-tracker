import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

/**
 * US-5 — Quick capture: the transaction-create dialog's quick-log strip
 * (opened by the mobile FAB from any page). Free-text line parses into
 * note + amount + category + balance, one-tap category override,
 * batch-ready reset after save.
 *
 * Grammar (resources/js/lib/parseQuickLogClient.ts): "bensin 33k cash" →
 * note "bensin", amount 33.000, category Transportation (alias), balance
 * hint "cash". The seeded primary balance is "Kas Utama"; the active budget
 * carries Food/Transportation/Home items so those categories are offered as
 * chips and the expense links to a real budget item.
 */

test.describe('US-5 quick capture', () => {
  test('FAB opens the dialog with the quick-log strip', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')

    await page.getByTestId('transaction-fab').click()

    await expect(
      page.getByRole('heading', { name: 'Tambah Transaksi' }),
    ).toBeVisible()
    const strip = page.getByTestId('quick-log-strip')
    await expect(strip).toBeVisible()
    await expect(page.getByTestId('quick-log-input')).toBeVisible()
  })

  test('free text parses into amount preview, category chip and balance hint', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')
    await page.getByTestId('transaction-fab').click()
    await page.getByTestId('quick-log-input').fill('bensin 33k cash')

    // Amount preview shows the parsed 33000…
    await expect(page.getByTestId('quick-log-amount')).toHaveValue('33000')

    // …the alias maps bensin → Transportation chip becomes active…
    const transportChip = page
      .getByTestId('quick-log-categories')
      .getByRole('button', { name: 'Transportation' })
    await expect(transportChip).toHaveAttribute('aria-pressed', 'true')

    // …and the unmatched tail word surfaces as a balance hint.
    await expect(page.getByTestId('quick-log-balance-hint')).toContainText(
      'cash',
    )
  })

  test('one-tap category override wins over the parsed category', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')
    await page.getByTestId('transaction-fab').click()
    await page.getByTestId('quick-log-input').fill('makan siang 25rb')

    const foodChip = page
      .getByTestId('quick-log-categories')
      .getByRole('button', { name: 'Food' })
    const homeChip = page
      .getByTestId('quick-log-categories')
      .getByRole('button', { name: 'Home' })

    // Parsed: makan → Food active…
    await expect(foodChip).toHaveAttribute('aria-pressed', 'true')

    // …then a single tap moves the selection to Home, and the override wins
    // on save — the created expense is Home, not the parsed Food.
    await homeChip.click()
    await expect(homeChip).toHaveAttribute('aria-pressed', 'true')
    await expect(foodChip).toHaveAttribute('aria-pressed', 'false')

    await page.getByTestId('quick-log-submit').click()
    await expect(page.getByText('Transaksi Dibuat')).toBeVisible()

    await page.setViewportSize({ width: 1280, height: 720 })
    await page.goto('/transactions')
    await page.getByPlaceholder('Cari...').fill('makan siang')
    await expect(
      page
        .getByRole('row', { name: /makan siang/ })
        .getByRole('cell', { name: 'Home' }),
    ).toBeVisible()
  })

  test('saving creates the expense and stays open with a clean line for batch entry', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')
    await page.getByTestId('transaction-fab').click()

    await page.getByTestId('quick-log-input').fill('bensin 33k')
    await expect(page.getByTestId('quick-log-amount')).toHaveValue('33000')

    await page.getByTestId('quick-log-submit').click()
    await expect(page.getByText('Transaksi Dibuat')).toBeVisible()

    // Batch-ready reset: input cleared, dialog still open for the next entry.
    await expect(page.getByTestId('quick-log-input')).toBeVisible()
    await expect(page.getByTestId('quick-log-input')).toHaveValue('')
    await expect(page.getByTestId('quick-log-amount')).toHaveValue('')

    // The created transaction shows up in the list. This spec runs at 375px
    // where the list renders cards, so verify on the desktop table instead —
    // the data assertion is what matters here, not the breakpoint.
    await page.setViewportSize({ width: 1280, height: 720 })
    await page.goto('/transactions')
    await page.getByPlaceholder('Cari...').fill('bensin')
    await expect(
      page
        .getByRole('row', { name: /bensin/ })
        .getByRole('cell', { name: 'Pengeluaran' }),
    ).toBeVisible()
  })

  test('last-used preferences persist in localStorage for the next session', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')
    await page.getByTestId('transaction-fab').click()
    await page.getByTestId('quick-log-input').fill('parkir 5k')
    await page.getByTestId('quick-log-submit').click()
    await expect(page.getByText('Transaksi Dibuat')).toBeVisible()

    // Prefs written on success (useQuickLogPrefs → et:last*).
    const prefs = await page.evaluate(() => ({
      balance: window.localStorage.getItem('et:lastBalanceId'),
      category: window.localStorage.getItem('et:lastCategoryId'),
    }))
    expect(prefs.balance).toBe('1')
    expect(Number(prefs.category)).toBeGreaterThan(0)

    // A fresh context replays them: reopening the form preselects Kas Utama
    // in the main balance select without any user input.
    await page.reload()
    await page.getByTestId('transaction-fab').click()
    await expect(page.locator('#balance')).toContainText('Kas Utama')
  })

  test('submit stays disabled while the parse is incomplete', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page)
    await page.goto('/dashboard')
    await page.getByTestId('transaction-fab').click()

    // Note only → no amount → quickCanSubmit false.
    await page.getByTestId('quick-log-input').fill('tanpa nominal')
    await expect(page.getByTestId('quick-log-submit')).toBeDisabled()

    // Amount appears → enabled again.
    await page.getByTestId('quick-log-input').fill('kopi 18k')
    await expect(page.getByTestId('quick-log-submit')).toBeEnabled()
  })
})
