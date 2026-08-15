import { describe, expect, it } from 'vitest'
import { useDate } from '@/composables/useDate'

describe('useDate', () => {
  const { formatDate, formatTime } = useDate()

  describe('formatDate', () => {
    it('returns an empty string for null', () => {
      expect(formatDate(null)).toBe('')
    })

    it('formats an ISO date as DD-MM-YYYY', () => {
      expect(formatDate('2026-08-15')).toBe('15-08-2026')
    })
  })

  describe('formatTime', () => {
    it('formats HH:mm as-is', () => {
      expect(formatTime('09:05')).toBe('09:05')
    })
  })
})
