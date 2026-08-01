import { useMasking } from '@/composables/useMasking'

export function useNumber() {
  const { mask } = useMasking()

  const formatNumber = (
    number = 0,
    minimumFractionDigits = 0,
    maximumFractionDigits = 2,
  ) => {
    const options: Intl.NumberFormatOptions = {
      maximumFractionDigits,
      minimumFractionDigits,
      style: 'decimal',
    }

    return new Intl.NumberFormat('id-ID', options).format(number)
  }

  /**
   * Single call site for money values: applies id-ID formatting with the
   * "Rp" prefix, then the mask when sensitive-number masking is enabled.
   */
  const formatAmount = (
    number = 0,
    minimumFractionDigits = 0,
    maximumFractionDigits = 2,
  ) => {
    const formatted = `Rp ${formatNumber(
      number,
      minimumFractionDigits,
      maximumFractionDigits,
    )}`

    return mask(formatted)
  }

  return {
    formatNumber,
    formatAmount,
  }
}
