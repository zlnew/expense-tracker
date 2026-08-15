import type { Locator, Page } from '@playwright/test'
import { expect } from '@playwright/test'

export const LABEL_FIT_TOLERANCE_PX = 8
export const OVERLAP_EPSILON_PX = 1

export type Viewport = {
  name: string
  width: number
  height: number
}

export const VIEWPORTS = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'tablet-break', width: 768, height: 1024 },
  { name: 'laptop', width: 1024, height: 768 },
  { name: 'desktop', width: 1280, height: 800 },
] as const

export const THEMES = ['light', 'dark'] as const

export type ColorScheme = (typeof THEMES)[number]

/**
 * Set the viewport and color scheme BEFORE goto. The theme rides
 * prefers-color-scheme → initializeTheme() 'system' default, so no app
 * state (localStorage) is touched.
 */
export async function setLayout(
  page: Page,
  viewport: Viewport,
  colorScheme: ColorScheme,
) {
  await page.setViewportSize({ width: viewport.width, height: viewport.height })
  await page.emulateMedia({ colorScheme })
}

/**
 * A label must not overflow its own box (truncation/clipping) or its
 * container, within an 8px tolerance. Prints the measured widths on failure.
 */
export async function expectLabelFits(label: Locator, container?: Locator) {
  await expect(label).toBeVisible()
  const { scrollWidth, clientWidth, containerScrollWidth, containerClientWidth } =
    await label.evaluate((el, containerEl) => {
      const rect = el.getBoundingClientRect()
      const labelScroll = el.scrollWidth - el.clientWidth
      const cScroll = containerEl ? containerEl.scrollWidth - containerEl.clientWidth : 0
      return {
        scrollWidth: el.scrollWidth,
        clientWidth: el.clientWidth,
        containerScrollWidth: containerEl?.scrollWidth ?? null,
        containerClientWidth: containerEl?.clientWidth ?? null,
        labelScroll,
        cScroll,
        rect: {
          left: rect.left,
          right: rect.right,
          width: rect.width,
        },
      }
    }, container ? await container.elementHandle() : null)

  const labelOverflow = scrollWidth - clientWidth
  const containerOverflow = containerScrollWidth !== null
    ? containerScrollWidth - containerClientWidth
    : 0

  expect(
    labelOverflow <= LABEL_FIT_TOLERANCE_PX,
    `label overflow ${labelOverflow}px > ${LABEL_FIT_TOLERANCE_PX}px tolerance (scrollWidth=${scrollWidth}, clientWidth=${clientWidth})`,
  ).toBe(true)

  expect(
    containerOverflow <= LABEL_FIT_TOLERANCE_PX,
    `container overflow ${containerOverflow}px > ${LABEL_FIT_TOLERANCE_PX}px tolerance (scrollWidth=${containerScrollWidth}, clientWidth=${containerClientWidth})`,
  ).toBe(true)
}

/**
 * Two elements' bounding boxes must not intersect, within a 1px epsilon.
 * Prints both boxes on failure.
 */
export async function expectNoOverlap(a: Locator, b: Locator) {
  await expect(a).toBeVisible()
  await expect(b).toBeVisible()
  const [boxA, boxB] = await Promise.all([
    a.boundingBox(),
    b.boundingBox(),
  ])
  if (!boxA || !boxB) {
    throw new Error('expectNoOverlap: both elements must have bounding boxes')
  }

  const aRight = boxA.x + boxA.width
  const aBottom = boxA.y + boxA.height
  const bRight = boxB.x + boxB.width
  const bBottom = boxB.y + boxB.height

  const horizontalOverlap = boxA.x < bRight - OVERLAP_EPSILON_PX && aRight > boxB.x + OVERLAP_EPSILON_PX
  const verticalOverlap = boxA.y < bBottom - OVERLAP_EPSILON_PX && aBottom > boxB.y + OVERLAP_EPSILON_PX

  expect(
    !(horizontalOverlap && verticalOverlap),
    `elements overlap: A=${JSON.stringify(boxA)}, B=${JSON.stringify(boxB)}`,
  ).toBe(true)
}

/**
 * Assert the shell model at the lg (1024px) switch. 'mobile' (<1024):
 * bottom nav visible + desktop sidebar hidden (the closed drawer Sheet is
 * not in the DOM). 'desktop' (>=1024): sidebar visible + bottom nav hidden.
 */
export async function expectShell(page: Page, mode: 'mobile' | 'desktop') {
  if (mode === 'mobile') {
    await expect(page.getByTestId('bottom-nav')).toBeVisible()
    await expect(page.locator('[data-slot="sidebar"]')).toBeHidden()
  } else {
    await expect(page.locator('[data-slot="sidebar"]')).toBeVisible()
    await expect(page.getByTestId('bottom-nav')).toBeHidden()
  }
}

/** Element must be fully inside the viewport (no horizontal clipping). */
export async function expectInViewport(locator: Locator) {
  await expect(locator).toBeVisible()
  const viewport = await locator.page().evaluate(() => ({
    width: window.innerWidth,
    height: window.innerHeight,
  }))
  const box = await locator.boundingBox()
  if (!box) {
    throw new Error('expectInViewport: element must have a bounding box')
  }
  expect(
    box.x >= -1 && box.x + box.width <= viewport.width + 1,
    `element extends outside viewport width: box=${JSON.stringify(box)}, viewport=${JSON.stringify(viewport)}`,
  ).toBe(true)
}

/** Bottom nav structure: { nav, links (4 <a>), fab }. */
export function bottomNav(page: Page) {
  const nav = page.getByTestId('bottom-nav')
  return {
    nav,
    links: nav.locator('a'),
    fab: nav.getByTestId('transaction-fab'),
  }
}
