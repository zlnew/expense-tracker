import { describe, expect, it } from 'vitest'
import { getInitials } from '@/composables/useInitials'

describe('getInitials', () => {
  it('returns the first and last name initials', () => {
    expect(getInitials('Maulana Aprizqy')).toBe('MA')
  })

  it('returns a single character for a single-word name', () => {
    expect(getInitials('Maulana')).toBe('M')
  })

  it('returns an empty string for undefined or empty input', () => {
    expect(getInitials(undefined)).toBe('')
    expect(getInitials('')).toBe('')
  })

  it('trims extra whitespace', () => {
    expect(getInitials('  Maulana   Aprizqy  ')).toBe('MA')
  })
})
