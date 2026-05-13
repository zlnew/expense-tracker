import { createI18n } from 'vue-i18n'

import en from '@/lang/en/app.json'
import id from '@/lang/id/app.json'

export const i18n = createI18n({
  legacy: false,
  locale: 'id',
  fallbackLocale: 'id',
  messages: {
    en,
    id,
  },
})
