export function useNumber() {
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

  return {
    formatNumber,
  }
}
