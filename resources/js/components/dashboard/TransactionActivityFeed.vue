<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  ArrowUpRight,
  ArrowDownLeft,
  ChevronRight,
  Receipt,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as transactionIndex } from '@/routes/transactions'
import type { RecentTransactions } from '@/types'

const props = defineProps<{
  recentTransactions: RecentTransactions
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()
const { masked } = useMasking()

const transactions = computed(() => props.recentTransactions.slice(0, 5))
</script>

<template>
  <div
    class="rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 sm:p-6 shadow-xl"
  >
    <!-- Header -->
    <div
      class="flex flex-row items-center justify-between pb-4 border-b border-[#1f222e]/60 mb-4"
    >
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400"
          >
            <Receipt class="size-4" />
          </span>
          <h2 class="font-mono text-sm font-bold text-zinc-100 uppercase tracking-wide">
            {{ __('recent_transactions') }}
          </h2>
        </div>
        <p class="font-mono text-[11px] text-zinc-500">
          {{ __('recent_transactions_description') }}
        </p>
      </div>

      <Link :href="transactionIndex.url()">
        <span
          class="inline-flex items-center gap-1 font-mono text-xs text-zinc-400 hover:text-emerald-400 transition-colors"
        >
          {{ __('all_data', { data: __('transactions') }) }}
          <ChevronRight class="size-3.5" />
        </span>
      </Link>
    </div>

    <!-- Content -->
    <div>
      <div
        v-if="transactions.length === 0"
        class="flex flex-col items-center justify-center space-y-2 py-8 text-center"
      >
        <div
          class="flex size-10 items-center justify-center rounded-full border border-[#1f222e] bg-[#121217] text-zinc-500"
        >
          <ArrowLeftRight class="size-5" />
        </div>
        <p class="font-mono text-sm font-medium text-zinc-500">
          {{ __('no_transactions') }}
        </p>
      </div>

      <div v-else class="divide-y divide-[#1f222e]/50">
        <div
          v-for="t in transactions"
          :key="t.id"
          class="group flex items-center justify-between rounded-xl px-2.5 py-3 transition-colors hover:bg-[#121217]/70"
        >
          <!-- Left: Flow icon & Details -->
          <div class="flex min-w-0 items-center gap-3 pr-3">
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-xl border transition-colors"
              :class="
                t.type === 'income'
                  ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                  : 'border-[#1f222e] bg-[#121217] text-zinc-400 group-hover:border-rose-500/30 group-hover:bg-rose-500/10 group-hover:text-rose-400'
              "
            >
              <ArrowDownLeft v-if="t.type === 'income'" class="size-4" />
              <ArrowUpRight v-else class="size-4" />
            </div>

            <div class="min-w-0">
              <p class="truncate font-mono text-sm font-semibold text-zinc-100">
                {{ t.description || t.category?.name || __('transaction') }}
              </p>
              <div
                class="flex items-center gap-1.5 font-mono text-[11px] text-zinc-500"
              >
                <span
                  v-if="t.category?.name"
                  class="truncate text-zinc-300"
                >
                  {{ t.category.name }}
                </span>
                <span
                  v-if="t.category?.name && t.balance?.name"
                  class="text-zinc-600"
                  >•</span
                >
                <span v-if="t.balance?.name" class="truncate text-zinc-400">
                  via {{ t.balance.name }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Amount & Timestamp -->
          <div class="shrink-0 text-right">
            <p
              class="font-mono text-sm font-bold tabular-nums"
              :class="t.type === 'income' ? 'text-emerald-400' : 'text-zinc-100'"
            >
              {{
                masked
                  ? '••••'
                  : (t.type === 'income' ? '+' : '-') + formatAmount(t.amount)
              }}
            </p>
            <p class="font-mono text-[11px] text-zinc-500">
              {{ formatDate(t.date) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
