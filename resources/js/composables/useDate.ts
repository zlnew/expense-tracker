import { useDateFormat } from '@vueuse/core'

export function useDate() {
  const formatDate = (
    date: Date | number | string | null = null,
    format = 'DD-MM-YYYY',
  ) => {
    if (!date) {
      return ''
    }

    const formatted = useDateFormat(date, format, {
      locales: 'id-ID',
    })

    return formatted.value
  }

  const formatTime = (time: string, format = 'HH:mm') => {
    const today = new Date().toISOString().split('T')[0]
    const date = new Date(`${today}T${time}`)

    return formatDate(date, format)
  }

  return {
    formatDate,
    formatTime,
  }
}
