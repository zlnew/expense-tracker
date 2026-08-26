import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

/**
 * US-4 — Balance reconcile: record the counted amount on a balance, see the
 * drift vs Active and get flagged when |drift| > tolerance.
 *
 * Drift semantics (app/Models/Balance.php): drift = reconciled_amount −
 * final_amount; flagged when abs(drift) > DRIFT_TOLERANCE = 500. The card
 * shows a drift row only after a reconcile, with id-ID money formatting.
 *
 * NOTE ON ISOLATION: other spec files in this branch create transactions /
 * payouts against the same seeded balance, and every one of them moves
 * final_amount via SyncBalance. This file therefore never hardcodes the
 * active amount — it reads the card's own "Aktif" row before reconciling
 * and derives the expected drift from what it measured ("self-calibrating"
 * assertions), so cross-file mutation order can't flip these tests.
 */

async function readActiveAmount(page: import('@playwright/test').Page) {
  const row = page
    .getByTestId('balance-card-1')
    .locator('div.justify-between')
    .filter({ has: page.getByText('Aktif', { exact: true }) })
  const text = await row.first().innerText()
  return Number(text.replace(/[^\d]/g, ''))
}

test.describe('US-4 balance reconcile', () => {
  // Desktop width: RowActions renders inline ghost icon buttons whose
  // aria-label carries the action name (the "Aksi" dropdown only exists
  // below the md breakpoint).
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 })
  })

  const openReconcileDialog = async (page: import('@playwright/test').Page) => {
    await page
      .getByTestId('balance-card-1')
      .getByRole('button', { name: 'Rekonsiliasi saldo' })
      .click()
  }

  test('reconciling at the exact active amount shows zero drift within tolerance', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    const active = await readActiveAmount(page)

    // Open the reconcile dialog through the card's row action.
    await openReconcileDialog(page)

    await expect(page.getByText('Rekonsiliasi saldo')).toBeVisible()
    await expect(
      page.getByText(/Masukkan jumlah aktual dari bank/i),
    ).toBeVisible()

    // Prefill = current final amount; keep it → drift must be exactly 0.
    await expect(page.locator('#reconciled_amount')).not.toHaveValue('')
    const prefilled = Number(
      (await page.locator('#reconciled_amount').inputValue()).replace(/[^\d-]/g, ''),
    )
    expect(prefilled).toBe(active)
    await page.getByRole('button', { name: 'Simpan' }).click()
    await expect(page.getByText('Saldo Diperbarui')).toBeVisible()

    // Zero drift is inside tolerance → "Dalam toleransi" row, not flagged.
    const driftRow = page.getByTestId('balance-drift-1')
    await expect(driftRow).toBeVisible()
    await expect(driftRow.getByText('Dalam toleransi')).toBeVisible()
    await expect(driftRow.locator('.text-destructive')).toHaveCount(0)
    await expect(driftRow).toContainText('Rp 0')

    await expect(page.getByTestId('balance-reconciled-at-1')).toContainText(
      /^\d{4}-\d{2}-\d{2}$/,
    )
  })

  test('drift beyond tolerance gets flagged as selisih', async ({ page }) => {
    await login(page)
    await page.goto('/balances')

    const active = await readActiveAmount(page)

    await openReconcileDialog(page)

    // Counted 100_000 below the recorded amount → |drift| > 500 tolerance.
    await page.locator('#reconciled_amount').fill(String(active - 100_000))
    await page.getByRole('button', { name: 'Simpan' }).click()
    await expect(page.getByText('Saldo Diperbarui')).toBeVisible()

    const driftRow = page.getByTestId('balance-drift-1')
    await expect(driftRow).toBeVisible()
    await expect(driftRow.getByText('Selisih terdeteksi')).toBeVisible()
    // formatAmount renders the sign after the currency prefix: Rp -100.000.
    await expect(driftRow).toContainText('Rp -100.000')

    // Flag styling lives on the row itself (border/ring/text).
    await expect(driftRow).toHaveClass(/text-destructive/)
  })

  test('reconcile dialog rejects an empty amount', async ({ page }) => {
    await login(page)
    await page.goto('/balances')

    await openReconcileDialog(page)
    await expect(page.locator('#reconciled_amount')).toBeVisible()

    await page.locator('#reconciled_amount').fill('')
    await page.getByRole('button', { name: 'Simpan' }).click()

    // HTML5 constraint blocks submission; the dialog stays open.
    await expect(page.locator('#reconciled_amount')).toBeVisible()
    await expect(page.getByText('Saldo Diperbarui')).toHaveCount(0)
    await expect(page.getByTestId('balance-drift-1')).toHaveCount(0)
  })
})
