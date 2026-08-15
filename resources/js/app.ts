import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h } from 'vue'
import { initializeTheme } from '@/composables/useAppearance'
import { useInstallPrompt } from '@/composables/useInstallPrompt'
import { i18n } from '@/lang'
import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { initializeFlashToast } from '@/lib/flashToast'

// NOTE: no registerSW({ immediate: true }) here — the service worker is
// registered with registerType: 'prompt' via PwaUpdatePrompt (mounted in
// AppSidebarLayout), which surfaces a "new version available" toast instead
// of force-reloading the app when a deploy lands mid-use.

// Capture the browser's install prompt as early as possible so the
// dismissible install banner (Dashboard) can react to it...
useInstallPrompt()

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name === 'Welcome':
        return null
      case name.startsWith('auth/'):
        return AuthLayout
      case name.startsWith('settings/'):
        return [AppLayout, SettingsLayout]
      default:
        return AppLayout
    }
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })

    const locale = props.initialPage.props.locale as 'id' | 'en' | undefined

    if (locale) {
      i18n.global.locale.value = locale
    }

    app.use(plugin)
    app.use(i18n)

    if (typeof window !== 'undefined') {
      app.mount(el as string | Element)
    }

    return app
  },
  progress: {
    color: '#4B5563',
  },
})

// This will set light / dark mode on page load...
initializeTheme()

// This will listen for flash toast data from the server...
initializeFlashToast()
