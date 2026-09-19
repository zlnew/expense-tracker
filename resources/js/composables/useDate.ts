import { useDateFormat } from '@vueuse/core'

export const getLocalDateString = (d: Date = new Date()): string => {
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function useDate() {
  const formatDate = (
    date: Date | number | string | null = null,
    format = 'DD-MM-YYYY',
  ) => {
    if (!date) {
      return ''
    }

    let dateObj: Date | number | string = date

    if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
      const [y, m, d] = date.split('-').map(Number)
      dateObj = new Date(y, m - 1, d)
    }

    const formatted = useDateFormat(dateObj, format, {
      locales: 'id-ID',
    })

    return formatted.value
  }

  const formatTime = (time: string, format = 'HH:mm') => {
    const today = getLocalDateString()
    const date = new Date(`${today}T${time}`)

    return formatDate(date, format)
  }

  return {
    formatDate,
    formatTime,
    getLocalDateString,
  }
}
