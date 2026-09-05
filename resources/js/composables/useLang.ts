import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export function useLang() {
  const { t, locale } = useI18n()

  const currentLocale = computed(() => locale.value as 'id' | 'en')

  function setLocale(newLocale: 'id' | 'en') {
    locale.value = newLocale

    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('locale', newLocale)
      } catch {
        // ignore
      }

      document.documentElement.lang = newLocale
    }
  }

  return {
    __: t,
    t,
    currentLocale,
    setLocale,
  }
}
