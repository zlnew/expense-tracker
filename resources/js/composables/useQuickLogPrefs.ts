import { ref } from 'vue'

const LS_BALANCE = 'et:lastBalanceId'
const LS_CATEGORY = 'et:lastCategoryId'

function safeGet(key: string): string | null {
  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

function safeSet(key: string, value: string): void {
  try {
    window.localStorage.setItem(key, value)
  } catch {
    // ignore
  }
}

export function useQuickLogPrefs() {
  const lastBalanceId = ref<number | null>(null)
  const lastCategoryId = ref<number | null>(null)

  function hydrate() {
    const b = safeGet(LS_BALANCE)
    const c = safeGet(LS_CATEGORY)
    lastBalanceId.value = b != null && b !== '' ? Number(b) : null
    lastCategoryId.value = c != null && c !== '' ? Number(c) : null
  }

  function rememberBalance(id: number | null | undefined) {
    if (id != null) {
      lastBalanceId.value = id
      safeSet(LS_BALANCE, String(id))
    }
  }

  function rememberCategory(id: number | null | undefined) {
    if (id != null) {
      lastCategoryId.value = id
      safeSet(LS_CATEGORY, String(id))
    }
  }

  function rememberFromIds(
    balanceId: number | null | undefined,
    categoryId: number | null | undefined,
  ) {
    rememberBalance(balanceId ?? null)
    rememberCategory(categoryId ?? null)
  }

  // Call hydrate once on import consumer's mount; also expose for tests.
  hydrate()

  return {
    lastBalanceId,
    lastCategoryId,
    hydrate,
    rememberBalance,
    rememberCategory,
    rememberFromIds,
  }
}
