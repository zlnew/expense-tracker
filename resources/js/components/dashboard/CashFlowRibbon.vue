<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  ShieldCheck,
  ChevronRight,
  PiggyBank,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
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

const totalBalance = computed(() => props.summaryCards.total_balance ?? 0)
const reservedBalance = computed(() => props.summaryCards.total_reserved ?? 0)
const realBalance = computed(
  () =>
    props.summaryCards.total_active ??
    totalBalance.value - reservedBalance.value,
)
const monthlyIncome = computed(
  () => props.summaryCards.current_month_incomes ?? 0,
)
const monthlyExpense = computed(
  () => props.summaryCards.current_month_expenses ?? 0,
)
const netCashFlow = computed(() => monthlyIncome.value - monthlyExpense.value)

// Percentages for the liquidity dual-leg bar
const realPercent = computed(() => {
  if (totalBalance.value <= 0) {
    return 100
  }

  return Math.max(
    0,
    Math.min(100, Math.round((realBalance.value / totalBalance.value) * 100)),
  )
})

const reservedPercent = computed(() => {
  if (totalBalance.value <= 0) {
    return 0
  }

  return Math.max(0, Math.min(100, 100 - realPercent.value))
})
</script>

<template>
  <Card class="relative overflow-hidden border-border/70 bg-card shadow-sm">
    <!-- Ambient subtle background glow for financial depth -->
    <div
      class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-income/5 blur-3xl"
    />
    <div
      class="pointer-events-none absolute -bottom-24 -left-24 size-64 rounded-full bg-primary/5 blur-3xl"
    />

    <CardContent class="relative p-6">
      <div
        class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
      >
        <!-- Left: Hero Real Balance -->
        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <span
              class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
              <Wallet class="size-4" />
            </span>
            <span
              class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('real_balance') || 'Real Spendable Balance' }}
            </span>
            <span
              v-if="reservedBalance > 0"
              class="inline-flex items-center gap-1 rounded-full bg-reserved/15 px-2 py-0.5 text-[10px] font-semibold text-reserved"
            >
              <ShieldCheck class="size-3" />
              {{ formatAmount(reservedBalance) }}
              {{ __('reserved') || 'Reserved' }}
            </span>
          </div>

          <!-- Hero Number Display -->
          <div class="flex items-baseline gap-1">
            <span
              class="text-3xl font-extrabold tracking-tight text-foreground tabular-nums sm:text-4xl"
            >
              {{ masked ? '••••••••' : formatAmount(realBalance) }}
            </span>
          </div>

          <p class="text-xs text-muted-foreground">
            {{ __('total_balance') }}:
            <span class="font-medium text-foreground">
              {{ masked ? '••••••' : formatAmount(totalBalance) }}
            </span>
            <span
              v-if="reservedBalance > 0"
              class="mx-1 text-muted-foreground/60"
              >•</span
            >
            <Link
              v-if="reservedBalance > 0"
              :href="fundIndex()"
              class="inline-flex items-center gap-0.5 text-xs font-medium text-reserved hover:underline"
            >
              <PiggyBank class="size-3" />
              {{ __('sinking_funds') || 'Sinking Funds' }}
            </Link>
          </p>
        </div>

        <!-- Right: Inflow / Outflow Flow Metrics -->
        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
          <!-- Income Inflow Card -->
          <div
            class="flex items-center gap-3 rounded-xl border border-border/50 bg-background/60 p-3 shadow-2xs sm:min-w-[140px]"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-income/10 text-income"
            >
              <TrendingUp class="size-4.5" />
            </div>
            <div>
              <p class="text-[11px] font-medium text-muted-foreground">
                {{ __('current_month_incomes') || 'Income' }}
              </p>
              <p class="text-sm font-bold text-foreground tabular-nums">
                {{ masked ? '••••' : formatAmount(monthlyIncome) }}
              </p>
            </div>
          </div>

          <!-- Expense Outflow Card -->
          <div
            class="flex items-center gap-3 rounded-xl border border-border/50 bg-background/60 p-3 shadow-2xs sm:min-w-[140px]"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-expense/10 text-expense"
            >
              <TrendingDown class="size-4.5" />
            </div>
            <div>
              <p class="text-[11px] font-medium text-muted-foreground">
                {{ __('current_month_expenses') || 'Expense' }}
              </p>
              <p class="text-sm font-bold text-foreground tabular-nums">
                {{ masked ? '••••' : formatAmount(monthlyExpense) }}
              </p>
            </div>
          </div>

          <!-- Net Cash Flow Badge -->
          <div
            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold"
            :class="
              netCashFlow >= 0
                ? 'border-income/30 bg-income/10 text-income'
                : 'border-expense/30 bg-expense/10 text-expense'
            "
          >
            <span>Net:</span>
            <span class="font-bold tabular-nums">
              {{
                masked
                  ? '••••'
                  : (netCashFlow >= 0 ? '+' : '') + formatAmount(netCashFlow)
              }}
            </span>
          </div>

          <Link :href="balanceIndex.url()">
            <span
              class="inline-flex size-9 items-center justify-center rounded-lg border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              title="Manage Balances"
            >
              <ChevronRight class="size-4" />
            </span>
          </Link>
        </div>
      </div>

      <!-- Liquidity Proportion Bar (Real vs Sinking Fund Reserves) -->
      <div
        v-if="reservedBalance > 0 && totalBalance > 0"
        class="mt-6 border-t border-border/40 pt-4"
      >
        <div
          class="mb-1.5 flex items-center justify-between text-[11px] text-muted-foreground"
        >
          <span class="flex items-center gap-1.5 font-medium">
            <span class="size-2 rounded-full bg-income" />
            {{ __('spendable') || 'Free Cash' }}: {{ realPercent }}%
          </span>
          <span class="flex items-center gap-1.5 font-medium">
            <span class="size-2 rounded-full bg-reserved" />
            {{ __('reserved_in_funds') || 'Reserved' }}: {{ reservedPercent }}%
          </span>
        </div>
        <div class="flex h-2 w-full overflow-hidden rounded-full bg-muted/60">
          <div
            class="h-full bg-income transition-all duration-500 ease-out"
            :style="{ width: `${realPercent}%` }"
          />
          <div
            class="h-full bg-reserved transition-all duration-500 ease-out"
            :style="{ width: `${reservedPercent}%` }"
          />
        </div>
      </div>
    </CardContent>
  </Card>
</template>
