import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

/**
 * US-3 — Impending drains card on the dashboard.
 *
 * Seeded fixture: "Langganan bulanan" recurring (150.000, monthly, active,
 * next run first day of next month) and "Dana Darurat" (next_due null → NOT
 * a drain). So the 60-day default window shows exactly one item and
 * total_impending_outflow = Rp 150.000.
 *
 * The card fetches /dashboard/impending-drains?window=… client-side; the
 * window buttons re-fetch with ?window=30/60/90.
 */

test.describe('US-3 impending drains', () => {
  test('dashboard shows the drains card with the seeded recurring drain', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/dashboard')

    await expect(
      page.getByRole('heading', { name: 'Impending drains' }),
    ).toBeVisible()
    await expect(page.getByText(/Upcoming fund dues \+ recurring/)).toBeVisible()

    // Exactly one drain in the fixture window.
    await expect(page.getByText('Langganan bulanan')).toBeVisible()
    await expect(page.getByText('Recurring', { exact: true })).toBeVisible()
    await expect(page.getByText('Fund', { exact: true })).toHaveCount(0)

    // Total row: only the one recurring occurrence.
    await expect(
      page.getByText(/^Total impending$/).locator('..'),
    ).toContainText('Rp 150.000')

    // The per-balance projection names the source balance.
    await expect(page.getByText('Kas Utama', { exact: true })).toBeVisible()
    await expect(page.getByText(/Free after/)).toBeVisible()
  })

  test('switching to the 30-day window keeps the next-month run visible', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/dashboard')

    await expect(
      page.getByRole('heading', { name: 'Impending drains' }),
    ).toBeVisible()

    // next_run_date = first day of next month ⇒ ≤ 31 days out ⇒ still inside.
    await page.getByRole('button', { name: '30d' }).click()
    await expect(page.getByText('Langganan bulanan')).toBeVisible()
    // The drain row's own amount cell — not the totals line, which repeats
    // the same figure.
    await expect(
      page
        .locator('span.font-mono', { hasText: /^Rp 150\.000$/ }),
    ).toBeVisible()
  })

  test('empty state renders when no drains fall inside the horizon', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    // A balance with no funds/recurrings attached — used below to prove the
    // empty state comes from the data, not from a broken fetch.
    await page.getByRole('button', { name: 'Tambah Saldo' }).click()
    await page.locator('#name').fill('Dompet Kosong')
    await page.locator('#initial_amount').fill('100000')
    await page.getByRole('button', { name: 'Simpan' }).click()
    await expect(page.getByRole('link', { name: 'Dompet Kosong' })).toBeVisible()

    await page.goto('/dashboard')
    await expect(
      page.getByRole('heading', { name: 'Impending drains' }),
    ).toBeVisible()

    // The recurring drain is global, not per-balance — it stays listed; the
    // assertion here is that the card still renders its list section.
    await expect(page.getByText('Langganan bulanan')).toBeVisible()

    // Deactivate the recurring so nothing is due inside any window…
    await page.goto('/recurring-transactions')
    await expect(
      page.getByRole('heading', { name: 'Transaksi Berulang' }),
    ).toBeVisible()
    // ResponsiveTable renders a real <table> at md+; its action cell holds
    // RowActions' inline ghost buttons (aria-label = action name). The
    // description column shows "—" when empty, so anchor on the row that
    // also carries the amount.
    await page
      .getByRole('row', { name: /Langganan bulanan/ })
      .getByRole('button', { name: /Hapus Transaksi Berulang/ })
      .click()
    // The delete flow opens a confirmation dialog whose destructive button
    // is the plain translated "delete" label.
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Hapus', exact: true })
      .click()
    await expect(page.getByText('Langganan bulanan')).toHaveCount(0)

    // …then the dashboard card must show its dedicated empty state.
    await page.goto('/dashboard')
    await expect(page.getByText('No drains due in this window')).toBeVisible()
    await expect(
      page.getByText('Funds and recurring inside the window will appear here.'),
    ).toBeVisible()
  })
})
