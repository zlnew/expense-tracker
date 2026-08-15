import { describe, expect, it } from 'vitest'
import { cn, toUrl } from '@/lib/utils'

describe('lib/utils', () => {
  describe('cn', () => {
    it('merges conflicting Tailwind classes with tailwind-merge', () => {
      expect(cn('p-2 p-4')).toBe('p-4')
    })

    it('keeps non-conflicting classes', () => {
      expect(cn('flex px-2')).toBe('flex px-2')
    })
  })

  describe('toUrl', () => {
    it('passes through a string href', () => {
      expect(toUrl('/x')).toBe('/x')
    })

    it('extracts url from an object href', () => {
      expect(toUrl({ url: '/y', method: 'get' })).toBe('/y')
    })
  })
})
