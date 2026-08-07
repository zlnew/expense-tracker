import { describe, expect, it } from 'vitest'
import { useBudgetProgress } from '@/composables/useBudgetProgress'

const { getProgressPercent, getProgressColor, getProgressTextColor } =
  useBudgetProgress()

describe('useBudgetProgress.getProgressPercent', () => {
  it('clamps to 100', () => {
    expect(getProgressPercent(100, 100)).toBe(100)
    expect(getProgressPercent(100, 150)).toBe(100)
    expect(getProgressPercent(100, 250)).toBe(100)
  })

  it('rounds normally', () => {
    expect(getProgressPercent(100, 79)).toBe(79)
    expect(getProgressPercent(100, 80)).toBe(80)
    expect(getProgressPercent(100, 99)).toBe(99)
    expect(getProgressPercent(100, 0)).toBe(0)
  })

  it('returns 0 when planned is not positive', () => {
    expect(getProgressPercent(0, 50)).toBe(0)
    expect(getProgressPercent(-10, 50)).toBe(0)
  })
})

describe('useBudgetProgress.getProgressColor', () => {
  it('rose at 100+', () => {
    expect(getProgressColor(100, 100)).toBe('bg-rose-500 dark:bg-rose-600')
    expect(getProgressColor(100, 120)).toBe('bg-rose-500 dark:bg-rose-600')
  })

  it('amber at 80–99', () => {
    expect(getProgressColor(100, 80)).toBe('bg-amber-500 dark:bg-amber-600')
    expect(getProgressColor(100, 99)).toBe('bg-amber-500 dark:bg-amber-600')
  })

  it('emerald below 80 and at 0 planned', () => {
    expect(getProgressColor(100, 0)).toBe('bg-emerald-500 dark:bg-emerald-600')
    expect(getProgressColor(100, 79)).toBe('bg-emerald-500 dark:bg-emerald-600')
    expect(getProgressColor(0, 50)).toBe('bg-emerald-500 dark:bg-emerald-600')
  })
})

describe('useBudgetProgress.getProgressTextColor', () => {
  it('rose bold at 100+', () => {
    expect(getProgressTextColor(100, 100)).toBe(
      'text-rose-600 dark:text-rose-400 font-bold',
    )
  })

  it('amber medium at 80–99', () => {
    expect(getProgressTextColor(100, 85)).toBe(
      'text-amber-600 dark:text-amber-400 font-medium',
    )
  })

  it('emerald below 80 and at 0 planned', () => {
    expect(getProgressTextColor(100, 50)).toBe(
      'text-emerald-600 dark:text-emerald-400',
    )
    expect(getProgressTextColor(0, 50)).toBe(
      'text-emerald-600 dark:text-emerald-400',
    )
  })
})
