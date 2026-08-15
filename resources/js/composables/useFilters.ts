import { router, usePage } from '@inertiajs/vue3'
import { tryOnScopeDispose, useDebounceFn } from '@vueuse/core'
import { computed, nextTick, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { toQuery } from '@/lib/utils'

export const FILTER_KEYS = [
  'search',
  'balance',
  'category',
  'dateFrom',
  'dateTo',
] as const

export type FilterKey = (typeof FILTER_KEYS)[number]

export type UseFiltersOptions = {
  /** Base URL of the page's list route (e.g. transactions.index().url). */
  url: string
  /** Values that mean "no filter" per key (e.g. balance: 'all'). */
  defaults?: Partial<Record<FilterKey, string>>
  /** Debounce delay in ms for filter-driven visits (default 300). */
  debounceMs?: number
  /** Called when a debounced visit starts (e.g. show the skeleton). */
  onStart?: () => void
  /** Called when a debounced visit finishes (e.g. hide the skeleton). */
  onFinish?: () => void
}

const fallbackDefaults: Record<FilterKey, string> = {
  search: '',
  balance: 'all',
  category: 'all',
  dateFrom: '',
  dateTo: '',
}

/**
 * Reactive filter state synced to the URL query string.
 *
 * - Reload restores: refs are seeded from `usePage().url`.
 * - One history entry per burst: changes are debounced into a single visit,
 *   so browser back/forward round-trips the whole filter set.
 * - No sync loop: our own visit's `navigate` echo is ignored (`syncing`
 *   guard), and back/forward adoption is applied without re-visiting
 *   (`adopting` guard).
 * - Split replace policy: a visit that leaves real filters in the query
 *   pushes a history entry (back/forward can return to it); a visit that
 *   collapses back to all-defaults replaces the current entry instead, so
 *   clearing filters never stacks a dead history entry.
 */
export function useFilters(options: UseFiltersOptions) {
  const defaults: Record<FilterKey, string> = {
    ...fallbackDefaults,
    ...options.defaults,
  }

  const page = usePage()
  const initialParams = new URLSearchParams(
    typeof window !== 'undefined'
      ? new URL(page.url, window.location.origin).search
      : '',
  )

  const search = ref(initialParams.get('search') ?? defaults.search)
  const balance = ref(initialParams.get('balance') ?? defaults.balance)
  const category = ref(initialParams.get('category') ?? defaults.category)
  const dateFrom = ref(initialParams.get('dateFrom') ?? defaults.dateFrom)
  const dateTo = ref(initialParams.get('dateTo') ?? defaults.dateTo)

  const filters = {
    search,
    balance,
    category,
    dateFrom,
    dateTo,
  } satisfies Record<FilterKey, Ref<string>>

  const activeCount = computed(
    () =>
      FILTER_KEYS.filter((key) => filters[key].value !== defaults[key]).length,
  )

  const isDirty = computed(() => activeCount.value > 0)

  /** Non-default params only — ready for `toQuery` or a server query. */
  function buildParams(): Record<string, string> {
    const params: Record<string, string> = {}

    FILTER_KEYS.forEach((key) => {
      const value = filters[key].value

      if (value !== defaults[key]) {
        params[key] = value
      }
    })

    return params
  }

  // True while a visit WE initiated is in flight — the `navigate` event our
  // own visit fires must not be mistaken for back/forward navigation.
  let syncing = false
  // True while we copy URL params into the refs after back/forward — the
  // watcher must not schedule a redundant visit.
  let adopting = false

  const syncToUrl = useDebounceFn(() => {
    const params = buildParams()
    const url = options.url + toQuery(params)
    const hasFilters = Object.keys(params).length > 0

    syncing = true
    router.get(
      url,
      {},
      {
        preserveState: true,
        preserveScroll: true,
        // Push when the query carries real filters (back/forward target);
        // replace when it collapses to defaults (no dead history entry).
        replace: !hasFilters,
        onStart: options.onStart,
        onError: () => {
          syncing = false
        },
        onFinish: () => {
          syncing = false
          options.onFinish?.()
        },
      },
    )
  }, options.debounceMs ?? 300)

  watch([search, balance, category, dateFrom, dateTo], () => {
    if (adopting) {
      return
    }

    syncToUrl()
  })

  const offNavigate = router.on('navigate', (event) => {
    // Echo of our own visit — the URL already reflects what we pushed.
    if (syncing) {
      return
    }

    const params = new URL(
      event.detail.page.url,
      typeof window !== 'undefined'
        ? window.location.origin
        : 'http://localhost',
    ).searchParams

    adopting = true
    search.value = params.get('search') ?? defaults.search
    balance.value = params.get('balance') ?? defaults.balance
    category.value = params.get('category') ?? defaults.category
    dateFrom.value = params.get('dateFrom') ?? defaults.dateFrom
    dateTo.value = params.get('dateTo') ?? defaults.dateTo
    nextTick(() => {
      adopting = false
    })
  })

  tryOnScopeDispose(offNavigate)

  /** Replace the filter set (e.g. from a sheet's draft) and sync. */
  function apply(next: Partial<Record<FilterKey, string>>) {
    FILTER_KEYS.forEach((key) => {
      if (next[key] !== undefined) {
        filters[key].value = next[key]!
      }
    })
  }

  /** Clear every filter back to its default and sync. */
  function reset() {
    FILTER_KEYS.forEach((key) => {
      filters[key].value = defaults[key]
    })
  }

  return {
    search,
    balance,
    category,
    dateFrom,
    dateTo,
    activeCount,
    isDirty,
    buildParams,
    apply,
    reset,
    offNavigate,
  }
}
