<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { useLang } from '@/composables/useLang'
import recurringTransactions from '@/routes/recurring-transactions'
import transactions from '@/routes/transactions'

defineProps<{
  viewMode: 'transactions' | 'recurring'
}>()

const { __ } = useLang()

// The current page is never re-visited; tapping it is a no-op.
function visit(viewMode: 'transactions' | 'recurring') {
  if (viewMode === 'transactions') {
    router.visit(transactions.index.url())
  } else {
    router.visit(recurringTransactions.index().url)
  }
}
</script>

<template>
  <div
    class="grid w-full grid-cols-2 gap-1 rounded-none border border-border bg-card p-1 font-mono text-xs shadow-xs"
  >
    <button
      type="button"
      class="cursor-pointer rounded-none px-3 py-1.5 text-center text-xs font-semibold transition-all"
      :class="
        viewMode === 'transactions'
          ? 'border border-emerald-500/40 bg-secondary font-bold text-emerald-500 shadow-xs dark:text-emerald-400'
          : 'border border-transparent text-muted-foreground hover:bg-secondary/50 hover:text-foreground'
      "
      :aria-pressed="viewMode === 'transactions'"
      @click="visit('transactions')"
    >
      {{ __('transactions') }}
    </button>
    <button
      type="button"
      class="cursor-pointer rounded-none px-3 py-1.5 text-center text-xs font-semibold transition-all"
      :class="
        viewMode === 'recurring'
          ? 'border border-emerald-500/40 bg-secondary font-bold text-emerald-500 shadow-xs dark:text-emerald-400'
          : 'border border-transparent text-muted-foreground hover:bg-secondary/50 hover:text-foreground'
      "
      :aria-pressed="viewMode === 'recurring'"
      @click="visit('recurring')"
    >
      {{ __('recurring_transactions') }}
    </button>
  </div>
</template>
