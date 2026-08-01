// Shared budget-progress helpers, used by both Dashboard and BudgetDetail so
// the "Progress Anggaran" rendering stays identical everywhere.
export function useBudgetProgress() {
  const getProgressPercent = (planned: number, actual: number) => {
    if (planned <= 0) {
      return 0
    }

    return Math.min(Math.round((actual / planned) * 100), 100)
  }

  const getProgressColor = (planned: number, actual: number) => {
    if (planned <= 0) {
      return 'bg-emerald-500 dark:bg-emerald-600'
    }

    const percentage = (actual / planned) * 100

    if (percentage >= 100) {
      return 'bg-rose-500 dark:bg-rose-600'
    }

    if (percentage >= 80) {
      return 'bg-amber-500 dark:bg-amber-600'
    }

    return 'bg-emerald-500 dark:bg-emerald-600'
  }

  const getProgressBgColor = (planned: number, actual: number) => {
    if (planned <= 0) {
      return 'bg-emerald-100 dark:bg-emerald-950/30'
    }

    const percentage = (actual / planned) * 100

    if (percentage >= 100) {
      return 'bg-rose-100 dark:bg-rose-950/30'
    }

    if (percentage >= 80) {
      return 'bg-amber-100 dark:bg-amber-950/30'
    }

    return 'bg-emerald-100 dark:bg-emerald-950/30'
  }

  const getProgressTextColor = (planned: number, actual: number) => {
    if (planned <= 0) {
      return 'text-emerald-600 dark:text-emerald-400'
    }

    const percentage = (actual / planned) * 100

    if (percentage >= 100) {
      return 'text-rose-600 dark:text-rose-400 font-bold'
    }

    if (percentage >= 80) {
      return 'text-amber-600 dark:text-amber-400 font-medium'
    }

    return 'text-emerald-600 dark:text-emerald-400'
  }

  return {
    getProgressPercent,
    getProgressColor,
    getProgressBgColor,
    getProgressTextColor,
  }
}
