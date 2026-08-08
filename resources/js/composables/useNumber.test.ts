import { beforeEach, describe, expect, it, vi } from 'vitest'

// Node environment (no jsdom) has no localStorage; useMasking reads and writes
// it through window. Provide a minimal in-memory shim.
class MemoryStorage {
  private store = new Map<string, string>()

  clear() {
    this.store.clear()
  }

  getItem(key: string): string | null {
    return this.store.get(key) ?? null
  }

  setItem(key: string, value: string) {
    this.store.set(key, String(value))
  }
}

;(globalThis as Record<string, unknown>).localStorage = new MemoryStorage()
;(globalThis as Record<string, unknown>).window = {
  localStorage: (globalThis as Record<string, unknown>).localStorage,
} as unknown as Window & typeof globalThis

// useNumber is pure (Intl.NumberFormat + mask), so environment: 'node' is fine.
// useMasking keeps a module-level singleton loaded at import time, so each test
// re-imports the modules fresh via vi.resetModules() to control the initial state.
async function loadNumber() {
  const { useMasking } = await import('@/composables/useMasking')
  const { useNumber } = await import('@/composables/useNumber')

  return { useMasking, useNumber }
}

describe('useNumber.formatAmount × useMasking', () => {
  beforeEach(() => {
    vi.resetModules()
    localStorage.clear()
  })

  it('formats id-ID with the Rp prefix', async () => {
    const { useNumber } = await loadNumber()
    const { formatAmount } = useNumber()

    expect(formatAmount(0)).toBe('Rp 0')
    expect(formatAmount(1_500_000)).toBe('Rp 1.500.000')
    expect(formatAmount(1_500_000.5, 0, 2)).toBe('Rp 1.500.000,5')
  })

  it('returns the fixed mask when masking is enabled', async () => {
    localStorage.setItem('expense-tracker.masked', 'true')
    const { useMasking, useNumber } = await loadNumber()

    const { masked, mask } = useMasking()
    expect(masked.value).toBe(true)
    expect(mask('Rp 1.500.000')).toBe('Rp ••••••')

    const { formatAmount } = useNumber()
    expect(formatAmount(1_500_000)).toBe('Rp ••••••')
  })

  it('shows the real value when masking is disabled', async () => {
    const { useMasking, useNumber } = await loadNumber()

    const { masked, mask } = useMasking()
    expect(masked.value).toBe(false)
    expect(mask('Rp 1.500.000')).toBe('Rp 1.500.000')

    const { formatAmount } = useNumber()
    expect(formatAmount(1_500_000)).toBe('Rp 1.500.000')
  })

  it('toggleMask flips the value and persists', async () => {
    const { useMasking } = await loadNumber()
    const { toggleMask, masked } = useMasking()

    expect(masked.value).toBe(false)
    toggleMask()
    expect(masked.value).toBe(true)

    // The persistence watcher flushes on the next microtask.
    await import('vue').then(({ nextTick }) => nextTick())
    expect(localStorage.getItem('expense-tracker.masked')).toBe('true')
  })
})
