import { describe, expect, it } from 'vitest'
import { parseQuickLogClient } from '@/lib/parseQuickLogClient'

const cats = [
  { id: 1, name: 'Transportation' },
  { id: 2, name: 'Food' },
  { id: 3, name: 'Utilities' },
]
const bals = [
  { id: 10, name: 'Cash' },
  { id: 11, name: 'BCA' },
]

describe('parseQuickLogClient', () => {
  it('parses bensin 33k cash → Transportation / 33000 / Cash', () => {
    const r = parseQuickLogClient('bensin 33k cash', cats, bals, null, null, 10)
    expect(r.amount).toBe(33_000)
    expect(r.categoryId).toBe(1)
    expect(r.balanceId).toBe(10)
    expect(r.note).toBe('bensin')
  })

  it('resolves k / rb / ribu / plain', () => {
    expect(parseQuickLogClient('makan 2.5k', cats, bals, null, null, 10).amount).toBe(2_500)
    expect(parseQuickLogClient('makan 33rb', cats, bals, null, null, 10).amount).toBe(33_000)
    expect(parseQuickLogClient('makan 10ribu', cats, bals, null, null, 10).amount).toBe(10_000)
    expect(parseQuickLogClient('makan 15000', cats, bals, null, null, 10).amount).toBe(15_000)
  })

  it('falls back to last-used on ambiguous category', () => {
    const ambCats = [
      { id: 20, name: 'Food Stall' },
      { id: 21, name: 'Food Court' },
    ]
    const r = parseQuickLogClient('food 10k', ambCats, bals, 20, null, null)
    expect(r.categoryId).toBe(20)
    expect(parseQuickLogClient('food 10k', ambCats, bals, null, null, null).categoryId).toBeNull()
  })

  it('resolves balance from tail else lastUsed else primary', () => {
    expect(parseQuickLogClient('makan 10k cash', cats, bals, null, 11, null).balanceId).toBe(10)
    expect(parseQuickLogClient('makan 10k', cats, bals, null, 11, null).balanceId).toBe(11)
    expect(parseQuickLogClient('makan 10k', cats, bals, null, null, 10).balanceId).toBe(10)
  })

  it('does not invent categories', () => {
    const r = parseQuickLogClient('unknownxyz 10k', cats, bals, null, null, null)
    expect(r.categoryId).toBeNull()
  })
})
