import { ref, watch } from 'vue'

export const MASKING_STORAGE_KEY = 'expense-tracker.masked'

export const MASKED_VALUE = 'Rp ••••••'

function loadInitialMasked(): boolean {
  if (typeof window === 'undefined') {
    return false
  }

  try {
    return window.localStorage.getItem(MASKING_STORAGE_KEY) === 'true'
  } catch {
    // localStorage unavailable (private mode, blocked storage) — default to visible.
    return false
  }
}

// Module-level singleton: every page/component shares the same masked state,
// and the watcher outlives individual components so the preference persists
// across SPA navigation.
const masked = ref<boolean>(loadInitialMasked())

// Persist the preference across navigation and reloads.
watch(masked, (value) => {
  if (typeof window === 'undefined') {
    return
  }

  try {
    window.localStorage.setItem(MASKING_STORAGE_KEY, String(value))
  } catch {
    // Ignore storage failures — the in-memory state still works for the session.
  }
})

function toggleMask(): void {
  masked.value = !masked.value
}

/**
 * Returns a fixed-width mask when masking is enabled, otherwise the
 * already-formatted value unchanged.
 */
function mask(value: string): string {
  return masked.value ? MASKED_VALUE : value
}

export function useMasking() {
  return {
    masked,
    toggleMask,
    mask,
  }
}
