<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  ShieldCheck,
  PiggyBank,
  ArrowUpRight,
  ArrowDownRight,
  Plus,
  ArrowRightLeft,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as balanceIndex } from '@/routes/balances'
import { index as fundIndex } from '@/routes/funds'
import type { SummaryCards } from '@/types'

const props = defineProps<{
  summaryCards: SummaryCards
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { masked } = useMasking()

// Backend contract:
// - props.summaryCards.total_balance = Real Spendable Balance (Total Active - Total Reserved)
// - props.summaryCards.total_active = Total Gross Ledger Balance across all accounts
// - props.summaryCards.total_reserved = Reserved in Sinking Funds
const realBalance = computed(() => props.summaryCards.total_balance ?? 0)
const reservedBalance = computed(() => props.summaryCards.total_reserved ?? 0)
const ledgerBalance = computed(
  () =>
    props.summaryCards.total_active ??
    realBalance.value + reservedBalance.value,
)
const monthlyIncome = computed(
  () => props.summaryCards.current_month_incomes ?? 0,
)
const monthlyExpense = computed(
  () => props.summaryCards.current_month_expenses ?? 0,
)
const netCashFlow = computed(() => monthlyIncome.value - monthlyExpense.value)

// Percentages for the liquidity dual-leg bar (relative to gross ledger balance)
const realPercent = computed(() => {
  if (ledgerBalance.value <= 0) {
    return 100
  }

  return Math.max(
    0,
    Math.min(100, Math.round((realBalance.value / ledgerBalance.value) * 100)),
  )
})

const reservedPercent = computed(() => {
  if (ledgerBalance.value <= 0) {
    return 0
  }

  return Math.max(0, Math.min(100, 100 - realPercent.value))
})

function triggerTransactionCreate() {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('open:transaction-create'))
  }
}
</script>

<template>
  <div
    class="relative overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-xl sm:p-6"
  >
    <!-- Terminal Ambient Glow -->
    <div
      class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-emerald-500/5 blur-3xl"
    />
    <div
      class="pointer-events-none absolute -bottom-24 -left-24 size-64 rounded-full bg-cyan-500/5 blur-3xl"
    />

    <div class="relative space-y-6">
      <div
        class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
      >
        <!-- Left: Hero Real Spendable Balance -->
        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <span
              class="inline-flex size-7 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400"
            >
              <Wallet class="size-4" />
            </span>
            <span
              class="font-mono text-xs font-semibold tracking-wider text-zinc-400 uppercase"
            >
              {{ __('real_balance') || 'Real Spendable Balance' }}
            </span>
            <span
              v-if="reservedBalance > 0"
              class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 font-mono text-[10px] font-semibold text-amber-400"
            >
              <ShieldCheck class="size-3" />
              {{ formatAmount(reservedBalance) }}
              {{ __('reserved') || 'Reserved' }}
            </span>
          </div>

          <!-- Hero Monospace Number -->
          <div class="flex items-baseline gap-1">
            <span
              class="font-mono text-3xl font-extrabold tracking-tight text-emerald-400 tabular-nums drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] sm:text-4xl"
            >
              {{ masked ? '••••••••' : formatAmount(realBalance) }}
            </span>
          </div>

          <p class="font-mono text-xs text-zinc-500">
            {{ __('active') || 'Total Active' }}:
            <span class="font-semibold text-zinc-200">
              {{ masked ? '••••••' : formatAmount(ledgerBalance) }}
            </span>
            <span v-if="reservedBalance > 0" class="mx-1.5 text-zinc-600"
              >•</span
            >
            <Link
              v-if="reservedBalance > 0"
              :href="fundIndex()"
              class="inline-flex items-center gap-1 font-mono text-xs text-amber-400/90 hover:text-amber-300 hover:underline"
            >
              <PiggyBank class="size-3.5" />
              {{ __('sinking_funds') || 'Sinking Funds' }}
            </Link>
          </p>
        </div>

        <!-- Right: Flow Metrics Pills -->
        <div
          class="grid grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:items-center sm:gap-3 lg:justify-end"
        >
          <!-- Income Inflow Card -->
          <div
            class="flex flex-col justify-between space-y-1.5 rounded-xl border border-[#1f222e] bg-[#121217] p-2.5 sm:min-w-[140px] sm:p-3"
          >
            <div class="flex items-center justify-between gap-1.5">
              <span
                class="font-mono text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
              >
                MASUK (BLN)
              </span>
              <div
                class="flex size-5 shrink-0 items-center justify-center rounded border border-emerald-500/20 bg-emerald-500/10 text-emerald-400"
              >
                <TrendingUp class="size-3" />
              </div>
            </div>
            <p
              class="truncate font-mono text-xs font-bold text-emerald-400 tabular-nums sm:text-sm"
            >
              {{
                masked
                  ? '••••'
                  : (monthlyIncome > 0 ? '+' : '') + formatAmount(monthlyIncome)
              }}
            </p>
          </div>

          <!-- Expense Outflow Card -->
          <div
            class="flex flex-col justify-between space-y-1.5 rounded-xl border border-[#1f222e] bg-[#121217] p-2.5 sm:min-w-[140px] sm:p-3"
          >
            <div class="flex items-center justify-between gap-1.5">
              <span
                class="font-mono text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
              >
                KELUAR (BLN)
              </span>
              <div
                class="flex size-5 shrink-0 items-center justify-center rounded border border-rose-500/20 bg-rose-500/10 text-rose-400"
              >
                <TrendingDown class="size-3" />
              </div>
            </div>
            <p
              class="truncate font-mono text-xs font-bold text-rose-400 tabular-nums sm:text-sm"
            >
              {{
                masked
                  ? '••••'
                  : (monthlyExpense > 0 ? '-' : '') +
                    formatAmount(monthlyExpense)
              }}
            </p>
          </div>

          <!-- Net Cash Flow Badge -->
          <div
            class="col-span-2 flex items-center justify-center gap-2 rounded-xl border px-3 py-2 font-mono text-xs font-semibold sm:col-span-1"
            :class="
              netCashFlow >= 0
                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                : 'border-rose-500/30 bg-rose-500/10 text-rose-400'
            "
          >
            <component
              :is="netCashFlow >= 0 ? ArrowUpRight : ArrowDownRight"
              class="size-4 shrink-0"
            />
            <span class="tabular-nums">
              {{ netCashFlow >= 0 ? '+' : ''
              }}{{ masked ? '••••' : formatAmount(netCashFlow) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Liquidity Proportion Bar (Real vs Sinking Fund Reserves) -->
      <div
        v-if="reservedBalance > 0 && ledgerBalance > 0"
        class="border-t border-[#1f222e] pt-4"
      >
        <div
          class="mb-1.5 flex items-center justify-between font-mono text-[11px] text-zinc-400"
        >
          <span class="flex items-center gap-1.5 font-medium">
            <span
              class="size-2 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(16,185,129,0.8)]"
            />
            {{ __('spendable') || 'Free Cash' }}: {{ realPercent }}%
          </span>
          <span class="flex items-center gap-1.5 font-medium">
            <span
              class="size-2 rounded-full bg-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.8)]"
            />
            {{ __('reserved_in_funds') || 'Reserved' }}: {{ reservedPercent }}%
          </span>
        </div>
        <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-800">
          <div
            class="h-full bg-emerald-500 transition-all duration-500 ease-out"
            :style="{ width: `${realPercent}%` }"
          />
          <div
            class="h-full bg-amber-500 transition-all duration-500 ease-out"
            :style="{ width: `${reservedPercent}%` }"
          />
        </div>
      </div>

      <!-- Quick Action Dock -->
      <div class="border-t border-[#1f222e]/60 pt-4">
        <div class="grid grid-cols-3 gap-2">
          <button
            type="button"
            class="flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/15 px-1 py-2 font-mono text-xs font-bold text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.15)] transition-all hover:bg-emerald-500/25 active:scale-95"
            @click="triggerTransactionCreate"
          >
            <Plus class="size-3.5" />
            <span>Catat</span>
          </button>
          <Link
            :href="balanceIndex.url()"
            class="flex items-center justify-center gap-1.5 rounded-xl border border-[#1f222e] bg-[#12141a] px-1 py-2 font-mono text-xs font-semibold text-zinc-300 transition-all hover:border-zinc-700 hover:text-zinc-100 active:scale-95"
          >
            <ArrowRightLeft class="size-3.5 text-zinc-400" />
            <span>Pindah</span>
          </Link>
          <Link
            :href="balanceIndex.url()"
            class="flex items-center justify-center gap-1.5 rounded-xl border border-[#1f222e] bg-[#12141a] px-1 py-2 font-mono text-xs font-semibold text-zinc-300 transition-all hover:border-zinc-700 hover:text-zinc-100 active:scale-95"
          >
            <ShieldCheck class="size-3.5 text-zinc-400" />
            <span>Rekon</span>
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
