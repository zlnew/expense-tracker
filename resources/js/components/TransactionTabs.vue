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
  <div class="grid w-full grid-cols-2 gap-1 rounded-xl border border-[#1f222e] bg-[#0d0d12] p-1 font-mono text-xs">
    <button
      type="button"
      class="touch-target rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
      :class="
        viewMode === 'transactions'
          ? 'bg-[#181820] text-emerald-400 border border-emerald-500/30 shadow-sm'
          : 'text-zinc-400 hover:text-zinc-200'
      "
      :aria-pressed="viewMode === 'transactions'"
      @click="visit('transactions')"
    >
      {{ __('transactions') }}
    </button>
    <button
      type="button"
      class="touch-target rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
      :class="
        viewMode === 'recurring'
          ? 'bg-[#181820] text-emerald-400 border border-emerald-500/30 shadow-sm'
          : 'text-zinc-400 hover:text-zinc-200'
      "
      :aria-pressed="viewMode === 'recurring'"
      @click="visit('recurring')"
    >
      {{ __('recurring_transactions') }}
    </button>
  </div>
</template>
