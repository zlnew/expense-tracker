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
  <div class="grid w-full grid-cols-2 gap-1 rounded-lg border bg-muted/40 p-1">
    <button
      type="button"
      class="touch-target rounded-md px-3 py-2 text-sm font-medium transition-colors"
      :class="
        viewMode === 'transactions'
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground'
      "
      :aria-pressed="viewMode === 'transactions'"
      @click="visit('transactions')"
    >
      {{ __('transactions') }}
    </button>
    <button
      type="button"
      class="touch-target rounded-md px-3 py-2 text-sm font-medium transition-colors"
      :class="
        viewMode === 'recurring'
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground'
      "
      :aria-pressed="viewMode === 'recurring'"
      @click="visit('recurring')"
    >
      {{ __('recurring_transactions') }}
    </button>
  </div>
</template>
