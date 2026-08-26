import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

/**
 * US-1 — Balance overview: Active / Reserved / Real legs on /balances.
 *
 * Seeded fixture (E2ESeeder): one primary balance "Kas Utama"
 * (initial 5,000,000 → final 4,200,000) and a "Dana Darurat" sinking fund
 * sourced from it with a single 500,000 contribution. Therefore:
 *   reserved = 500_000
 *   real     = final_amount − reserved = 3_700_000
 *
 * Money renders through Intl.NumberFormat('id-ID') → "Rp 4.200.000"
 * (dot thousands separator, no decimals).
 */

const ACTIVE_TEXT = 'Rp 4.200.000'
const RESERVED_TEXT = 'Rp 500.000'
const REAL_TEXT = 'Rp 3.700.000'
const INITIAL_TEXT = 'Rp 5.000.000'

test.describe('US-1 balance overview', () => {
  test('balance card shows active, reserved and real amounts', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    const card = page.getByTestId('balance-card-1')
    await expect(card).toBeVisible()
    await expect(card.getByRole('link', { name: 'Kas Utama' })).toBeVisible()

    // The card lists four labeled money rows; assert by label pairing.
    // Markup: <div class="… justify-between"><span>Label</span><span>Rp …</span></div>
    const rowFor = (label: string) =>
      card.locator('div.justify-between').filter({
        has: page.getByText(label, { exact: true }),
      })

    for (const [label, value] of [
      ['Aktif', ACTIVE_TEXT],
      ['Reserved', RESERVED_TEXT],
      ['Real Balance', REAL_TEXT],
      ['Saldo Awal', INITIAL_TEXT],
    ] as const) {
      await expect(rowFor(label)).toContainText(value)
    }
  })

  test('reserved + real stay consistent with active (real = active − reserved)', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    const card = page.getByTestId('balance-card-1')

    const parse = async (label: string) => {
      const row = card.locator('div.justify-between').filter({
        has: page.getByText(label, { exact: true }),
      })
      const text = await row.first().innerText()
      return Number(text.replace(/[^\d]/g, ''))
    }

    const [active, reserved, real] = [
      await parse('Aktif'),
      await parse('Reserved'),
      await parse('Real Balance'),
    ]

    expect(reserved).toBe(500_000)
    expect(real).toBe(active - reserved)
  })

  test('unreconciled balances show no drift row', async ({ page }) => {
    await login(page)
    await page.goto('/balances')

    await expect(page.getByTestId('balance-card-1')).toBeVisible()

    // No reconcile happened in the fixture yet.
    await expect(page.getByTestId(/balance-drift-/)).toHaveCount(0)
    await expect(page.getByTestId(/balance-reconciled-at-/)).toHaveCount(0)
  })

  test('creating a balance via the dialog adds a card with derived legs', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    await page.getByRole('button', { name: 'Tambah Saldo' }).click()
    await page.locator('#name').fill('Dompet Kedua')
    await page.locator('#initial_amount').fill('750000')
    await page.getByRole('button', { name: 'Simpan' }).click()

    const newCard = page
      .getByTestId('balance-card-2')
      .getByRole('link', { name: 'Dompet Kedua' })
    await expect(newCard).toBeVisible()

    // No funds source this balance → reserved 0 and real == active.
    await expect(
      page.getByTestId('balance-card-2').locator('div.justify-between').filter({
        has: page.getByText('Reserved', { exact: true }),
      }),
    ).toContainText('Rp 0')
    await expect(
      page
        .getByTestId('balance-card-2')
        .locator('div.justify-between')
        .filter({ has: page.getByText('Real Balance', { exact: true }) }),
    ).toContainText('Rp 750.000')
  })

  test('balance detail keeps initial/final/status cards consistent', async ({
    page,
  }) => {
    await login(page)
    await page.goto('/balances')

    await page.getByRole('link', { name: 'Kas Utama' }).click()
    await expect(page).toHaveURL('/balances/1')

    await expect(page.getByRole('heading', { name: 'Kas Utama' })).toBeVisible()
    await expect(page.getByText(ACTIVE_TEXT)).toBeVisible() // Saldo Akhir leg
    await expect(page.getByText(INITIAL_TEXT)).toBeVisible() // Saldo Awal leg
    await expect(page.getByText('Utama', { exact: true })).toBeVisible() // status badge

    // The seeded transactions of this balance are listed.
    await expect(
      page.getByRole('cell', { name: 'Transaksi e2e #1' }).first(),
    ).toBeVisible()
  })
})
