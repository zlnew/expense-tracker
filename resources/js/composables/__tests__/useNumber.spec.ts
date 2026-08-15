import { beforeEach, describe, expect, it } from 'vitest'
import { useMasking, MASKED_VALUE } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'

describe('useNumber', () => {
  const { formatNumber, formatAmount } = useNumber()
  const { toggleMask } = useMasking()

  beforeEach(() => {
    // Start every test unmasked; toggleMask flips the module singleton.
    if (useMasking().masked.value) {
      toggleMask()
    }
  })

  describe('formatNumber', () => {
    it('formats with id-ID grouping', () => {
      expect(formatNumber(1234567.8, 0, 2)).toBe('1.234.567,8')
    })

    it('defaults to 0', () => {
      expect(formatNumber()).toBe('0')
    })
  })

  describe('formatAmount', () => {
    it('prefixes with Rp and applies unmasked formatting', () => {
      expect(formatAmount(50000)).toBe('Rp 50.000')
    })

    it('returns the masked value when masking is enabled', () => {
      toggleMask()
      expect(formatAmount(50000)).toBe(MASKED_VALUE)
    })

    it('returns the unmasked value after toggling back', () => {
      toggleMask()
      expect(formatAmount(50000)).toBe(MASKED_VALUE)
      toggleMask()
      expect(formatAmount(50000)).toBe('Rp 50.000')
    })
  })
})
