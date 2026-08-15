import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'
import {
  VIEWPORTS,
  THEMES,
  bottomNav,
  expectInViewport,
  expectLabelFits,
  expectNoOverlap,
  expectShell,
  setLayout,
} from './helpers/layout'

test.describe('layout matrix (viewport × theme)', () => {
  for (const viewport of VIEWPORTS) {
    for (const theme of THEMES) {
      test(`${viewport.name} × ${theme}`, async ({ page }) => {
        await setLayout(page, viewport, theme)
        await login(page)
        // Fortify home — the first page a logged-in user sees. (The /dashboard
        // route 500s on the sqlite e2e DB: GetMonthlySpendingTrend uses
        // Postgres-only EXTRACT/DATE_TRUNC — pre-existing, prod runs PG.)
        await page.goto('/transactions')

        const isMobile = viewport.width < 1024
        await expectShell(page, isMobile ? 'mobile' : 'desktop')

        if (isMobile) {
          const { nav, links, fab } = bottomNav(page)

          // Raised FAB must not overlap its left/right neighbors.
          await expectNoOverlap(fab, links.nth(0))
          await expectNoOverlap(fab, links.nth(2))

          // Every bottom-nav label fits its own box (id locale = longest labels).
          const labels = nav.locator('span')
          for (let i = 0; i < (await labels.count()); i++) {
            await expectLabelFits(labels.nth(i))
          }

          await expectInViewport(nav)

          // Drawer: trigger opens the Sheet, close hides it again.
          const trigger = page
            .locator('[data-slot="sidebar-trigger"]')
            .filter({ visible: true })
          await trigger.click()
          const drawer = page.locator('[data-sidebar="sidebar"]')
          await expect(drawer).toBeVisible()
          await page.keyboard.press('Escape')
          await expect(drawer).toBeHidden()
        } else {
          // Desktop band: sidebar nav labels fit, main heading in viewport.
          // Menu buttons with tooltips carry data-sidebar="menu-button"
          // (their data-slot becomes tooltip-trigger) — use the stable attr.
          const labels = page.locator(
            '[data-slot="sidebar"] [data-sidebar="menu-button"] span',
          )
          for (let i = 0; i < (await labels.count()); i++) {
            await expectLabelFits(labels.nth(i))
          }

          await expectInViewport(
            page.getByRole('heading', { name: 'Transaksi' }),
          )
        }
      })
    }
  }
})
