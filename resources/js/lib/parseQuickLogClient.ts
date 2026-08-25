/**
 * Lightweight client-side mirror of the PHP ParseQuickLog grammar so the
 * quick-capture UX can preview without a round-trip. Keep in sync with
 * `app/Actions/ParseQuickLog.php` (amount + category alias rules).
 */

export type QuickParseResult = {
  note: string
  amount: number | null
  balanceNameHint: string | null
  categoryId: number | null
  balanceId: number | null
}

const CATEGORY_ALIASES: Record<string, string> = {
  bensin: 'Transportation',
  parkir: 'Transportation',
  tol: 'Transportation',
  ojek: 'Transportation',
  grab: 'Transportation',
  gojek: 'Transportation',
  makan: 'Food',
  kopi: 'Food',
  jajan: 'Food',
  ngopi: 'Food',
  listrik: 'Utilities',
  air: 'Utilities',
  wifi: 'Utilities',
  pulsa: 'Utilities',
  sewa: 'Home',
  kos: 'Home',
  obat: 'Health',
  dokter: 'Health',
  kado: 'Gifts',
  hadiah: 'Gifts',
  servis: 'Maintenance',
  service: 'Maintenance',
  pajak: 'Taxes',
  gaji: 'Paycheck',
  bonus: 'Bonus',
  bunga: 'Interest',
  tabungan: 'Savings',
}

function normalizeCategoryName(s: string): string {
  return s.trim().toLowerCase()
}

function parseAmountToken(tok: string): number | null {
  let n = tok.trim().toLowerCase()
  let mult = 1
  if (n.endsWith('ribu')) {
    mult = 1000
    n = n.slice(0, -4)
  } else if (n.endsWith('rb')) {
    mult = 1000
    n = n.slice(0, -2)
  } else if (n.endsWith('k')) {
    mult = 1000
    n = n.slice(0, -1)
  }
  n = n.trim().replace(/,/g, '')
  if (n.includes('.')) {
    const parts = n.split('.')
    if (parts.length === 2 && parts[1].length <= 2 && mult > 1) {
      // decimal
    } else {
      n = n.replace(/\./g, '')
    }
  }
  if (n === '' || Number.isNaN(Number(n))) return null
  return Math.round(Number.parseFloat(n) * mult)
}

function isAmountToken(tok: string): boolean {
  return /^\d[\d.,]*\s*(k|rb|ribu)?$/i.test(tok.trim().toLowerCase())
}

export function parseQuickLogClient(
  raw: string,
  categories: Array<{ id: number; name: string }>,
  balances: Array<{ id: number; name: string }>,
  lastUsedCategoryId: number | null,
  lastUsedBalanceId: number | null,
  primaryBalanceId: number | null | undefined,
): QuickParseResult {
  const trimmed = raw.trim()
  if (!trimmed) {
    return { note: '', amount: null, balanceNameHint: null, categoryId: null, balanceId: null }
  }

  const tokens = trimmed.split(/\s+/).filter(Boolean)
  let amountIdx: number | null = null
  for (let i = 0; i < tokens.length; i++) {
    if (isAmountToken(tokens[i])) {
      amountIdx = i
      break
    }
  }

  const resolveBalanceFromTail = (): number | null => {
    if (amountIdx != null && amountIdx + 1 < tokens.length) {
      const tail = tokens.slice(amountIdx + 1).join(' ').trim().toLowerCase()
      const exact = balances.find((b) => b.name.toLowerCase() === tail)
      if (exact) return exact.id
      const cands = balances.filter(
        (b) =>
          b.name.toLowerCase().includes(tail) || tail.includes(b.name.toLowerCase()),
      )
      if (cands.length === 1) return cands[0].id
    }
    if (lastUsedBalanceId != null && balances.some((b) => b.id === lastUsedBalanceId)) {
      return lastUsedBalanceId
    }
    if (primaryBalanceId != null) return primaryBalanceId
    return null
  }

  const fallbackCategory = (): number | null => {
    if (lastUsedCategoryId != null && categories.some((c) => c.id === lastUsedCategoryId)) {
      return lastUsedCategoryId
    }
    return null
  }

  const resolveCategory = (note: string): number | null => {
    if (!categories.length) return fallbackCategory()
    const needle = normalizeCategoryName(note)
    if (!needle) return fallbackCategory()

    const exact = categories.find((c) => c.name.toLowerCase() === needle)
    if (exact) return exact.id

    for (const [alias, canonical] of Object.entries(CATEGORY_ALIASES)) {
      if (needle.includes(alias) || alias.includes(needle)) {
        const hit = categories.find((c) => c.name === canonical)
        if (hit) return hit.id
      }
    }

    const cands = categories.filter(
      (c) => c.name.toLowerCase().includes(needle) || needle.includes(c.name.toLowerCase()),
    )
    if (cands.length === 1) return cands[0].id
    if (cands.length > 1) return fallbackCategory()
    return fallbackCategory()
  }

  if (amountIdx === null) {
    const note = trimmed
    return {
      note,
      amount: null,
      balanceNameHint: null,
      categoryId: resolveCategory(note),
      balanceId: resolveBalanceFromTail(),
    }
  }

  const amount = parseAmountToken(tokens[amountIdx])
  const note = tokens.slice(0, amountIdx).join(' ').trim()
  const balanceNameHint =
    amountIdx + 1 < tokens.length ? tokens.slice(amountIdx + 1).join(' ') : null

  return {
    note,
    amount,
    balanceNameHint,
    categoryId: resolveCategory(note),
    balanceId: resolveBalanceFromTail(),
  }
}
