import { describe, expect, it } from 'vitest'
import { useBudgetProgress } from '@/composables/useBudgetProgress'

describe('useBudgetProgress', () => {
  const { getProgressPercent, getProgressColor } = useBudgetProgress()

  describe('getProgressPercent', () => {
    it('returns 0 when planned is zero or negative', () => {
      expect(getProgressPercent(0, 100)).toBe(0)
      expect(getProgressPercent(-50, 100)).toBe(0)
    })

    it('computes the raw percentage', () => {
      expect(getProgressPercent(100, 50)).toBe(50)
    })

    it('caps at 100 when actual exceeds planned', () => {
      expect(getProgressPercent(100, 150)).toBe(100)
    })

    it('rounds to the nearest integer', () => {
      expect(getProgressPercent(100, 33)).toBe(33)
      expect(getProgressPercent(100, 66)).toBe(66)
      expect(getProgressPercent(3, 1)).toBe(33)
    })
  })

  describe('getProgressColor', () => {
    it('returns emerald below 80%', () => {
      expect(getProgressColor(100, 50)).toContain('emerald-500')
    })

    it('returns amber at or above 80%', () => {
      expect(getProgressColor(100, 80)).toContain('amber-500')
      expect(getProgressColor(100, 99)).toContain('amber-500')
    })

    it('returns rose at or above 100%', () => {
      expect(getProgressColor(100, 100)).toContain('rose-500')
      expect(getProgressColor(100, 120)).toContain('rose-500')
    })

    it('returns emerald when planned is zero or negative', () => {
      expect(getProgressColor(0, 100)).toContain('emerald-500')
      expect(getProgressColor(-10, 100)).toContain('emerald-500')
    })
  })
})
