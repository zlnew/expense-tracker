import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import type { UseFiltersOptions } from '@/composables/useFilters'

// Node environment (no jsdom): provide a minimal window shim and a fake
// Inertia router so useFilters can read URL params and record visits.
type NavigateHandler = (event: { detail: { page: { url: string } } }) => void

const navigateHandlers: NavigateHandler[] = []
const getMock = vi.fn()

vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: getMock,
    on: (event: string, handler: NavigateHandler) => {
      if (event === 'navigate') {
        navigateHandlers.push(handler)
      }

      return () => {}
    },
  },
}))

// Mutable location so each test can control the "current URL".
const location = { search: '', origin: 'http://localhost' }

;(globalThis as Record<string, unknown>).window = { location }

// Vitest fake timers so the debounced visit can be advanced deterministically.
beforeEach(() => {
  vi.useFakeTimers()
  getMock.mockClear()
  navigateHandlers.length = 0
  location.search = ''
})

afterEach(() => {
  vi.useRealTimers()
})

async function loadFilters(options: Record<string, unknown> = {}) {
  vi.resetModules()
  const { useFilters } = await import('@/composables/useFilters')

  return useFilters({
    url: '/transactions',
    ...options,
  } as UseFiltersOptions)
}

describe('useFilters', () => {
  it('seeds filter refs from the URL (reload restores)', async () => {
    location.search = '?search=coffee&balance=2&category=5&dateFrom=2024-01-01'

    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    expect(filters.search.value).toBe('coffee')
    expect(filters.balance.value).toBe('2')
    expect(filters.category.value).toBe('5')
    expect(filters.dateFrom.value).toBe('2024-01-01')
    expect(filters.dateTo.value).toBe('')
  })

  it('falls back to defaults for missing params', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    expect(filters.search.value).toBe('')
    expect(filters.balance.value).toBe('all')
    expect(filters.category.value).toBe('all')
  })

  it('buildParams omits default values and empty strings', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    filters.search.value = 'makan'
    filters.balance.value = 'all'
    filters.category.value = '3'
    filters.dateFrom.value = '2024-01-01'

    expect(filters.buildParams()).toEqual({
      search: 'makan',
      category: '3',
      dateFrom: '2024-01-01',
    })
  })

  it('activeCount counts only non-default filters', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    expect(filters.activeCount.value).toBe(0)
    expect(filters.isDirty.value).toBe(false)

    filters.search.value = 'x'
    filters.dateTo.value = '2024-12-31'

    expect(filters.activeCount.value).toBe(2)
    expect(filters.isDirty.value).toBe(true)
  })

  it('debounces a typing burst into a single visit (one history entry per burst)', async () => {
    const filters = await loadFilters()

    filters.search.value = 'a'
    filters.search.value = 'ab'
    filters.search.value = 'abc'

    // Watcher is async; nothing has been pushed yet.
    await nextTick()
    expect(getMock).not.toHaveBeenCalled()

    vi.advanceTimersByTime(350)
    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock).toHaveBeenCalledWith(
      '/transactions?search=abc',
      {},
      expect.objectContaining({ preserveState: true, preserveScroll: true }),
    )
  })

  it('URL updates as filters change (toQuery round-trip)', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    filters.category.value = '7'
    filters.dateTo.value = '2024-06-30'
    await nextTick()
    vi.advanceTimersByTime(350)

    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock.mock.calls[0][0]).toBe(
      '/transactions?category=7&dateTo=2024-06-30',
    )
  })

  it('adopts URL params on browser back/forward without re-visiting', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    // Simulate Inertia firing navigate with a restored (older) URL.
    navigateHandlers.forEach((handler) =>
      handler({
        detail: {
          page: { url: 'http://localhost/transactions?search=tea&category=9' },
        },
      }),
    )

    // Adoption updates refs but must NOT trigger a new visit.
    expect(filters.search.value).toBe('tea')
    expect(filters.category.value).toBe('9')
    await nextTick()
    vi.advanceTimersByTime(350)
    expect(getMock).not.toHaveBeenCalled()
  })

  it('ignores the navigate echo of its own visit (syncing guard)', async () => {
    const filters = await loadFilters()

    filters.search.value = 'abc'
    await nextTick()
    vi.advanceTimersByTime(350)
    expect(getMock).toHaveBeenCalledTimes(1)

    // The visit we just made lands; Inertia fires navigate for OUR url. The
    // syncing guard must swallow it — no re-read, no second visit.
    const visitedUrl = getMock.mock.calls[0][0]
    navigateHandlers.forEach((handler) =>
      handler({ detail: { page: { url: `http://localhost${visitedUrl}` } } }),
    )

    await nextTick()
    vi.advanceTimersByTime(350)
    expect(getMock).toHaveBeenCalledTimes(1)
    expect(filters.search.value).toBe('abc')
  })

  it('apply() replaces the filter set and syncs', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    filters.apply({ search: 'kopi', balance: '4', dateFrom: '2024-03-01' })
    await nextTick()
    vi.advanceTimersByTime(350)

    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock.mock.calls[0][0]).toBe(
      '/transactions?search=kopi&balance=4&dateFrom=2024-03-01',
    )
  })

  it('reset() clears every filter back to its default and syncs', async () => {
    const filters = await loadFilters({
      defaults: { balance: 'all', category: 'all' },
    })

    filters.apply({ search: 'kopi', balance: '4', category: '3' })
    await nextTick()
    vi.advanceTimersByTime(350)
    expect(getMock).toHaveBeenCalledTimes(1)

    filters.reset()
    await nextTick()
    vi.advanceTimersByTime(350)

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(getMock.mock.calls[1][0]).toBe('/transactions')
    expect(filters.search.value).toBe('')
    expect(filters.balance.value).toBe('all')
    expect(filters.category.value).toBe('all')
    expect(filters.activeCount.value).toBe(0)
  })
})
