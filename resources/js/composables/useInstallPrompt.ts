import { ref } from 'vue'

export interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>
}

const SESSION_DISMISS_KEY = 'expense-tracker.install-banner-dismissed'

// Module-level singleton state so app.ts and any component share the same
// captured prompt and visibility flag...
const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null)
const canInstall = ref(false)

let listenersAttached = false

function handleBeforeInstallPrompt(event: Event): void {
  // Prevent the browser's automatic mini-infobar so we control the prompt...
  event.preventDefault()

  deferredPrompt.value = event as BeforeInstallPromptEvent

  // Don't re-show the banner once the user dismissed/installed this session...
  if (sessionStorage.getItem(SESSION_DISMISS_KEY)) {
    return
  }

  canInstall.value = true
}

function handleAppInstalled(): void {
  canInstall.value = false
  deferredPrompt.value = null
  sessionStorage.setItem(SESSION_DISMISS_KEY, '1')
}

function attachListeners(): void {
  if (listenersAttached || typeof window === 'undefined') {
    return
  }

  window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.addEventListener('appinstalled', handleAppInstalled)
  listenersAttached = true
}

export function useInstallPrompt() {
  attachListeners()

  async function promptInstall(): Promise<void> {
    const promptEvent = deferredPrompt.value

    if (!promptEvent) {
      return
    }

    await promptEvent.prompt()
    await promptEvent.userChoice

    // Hide the banner for the rest of the session whether the user accepted
    // or dismissed the native prompt...
    canInstall.value = false
    deferredPrompt.value = null
    sessionStorage.setItem(SESSION_DISMISS_KEY, '1')
  }

  function dismissInstall(): void {
    canInstall.value = false
    sessionStorage.setItem(SESSION_DISMISS_KEY, '1')
  }

  return {
    canInstall,
    promptInstall,
    dismissInstall,
  }
}
