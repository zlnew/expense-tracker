<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  CircleDollarSign,
  AlertTriangle,
  CheckCircle2,
  ChevronRight,
  TrendingUp,
  Clock,
  Gauge,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { useBudgetProgress } from '@/composables/useBudgetProgress'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as budgetIndex } from '@/routes/budgets'
import type { BudgetProgress, SummaryCards } from '@/types'

const props = defineProps<{
  budgetProgress: BudgetProgress
  summaryCards: SummaryCards
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { masked } = useMasking()
const { getProgressPercent, getProgressColor } = useBudgetProgress()

const hasBudget = computed(() => props.summaryCards.has_active_budget)

const budgetLimit = computed(() => {
  return props.budgetProgress.reduce(
    (sum, item) => sum + item.planned_amount,
    0,
  )
})

const budgetSpent = computed(() => {
  return props.budgetProgress.reduce(
    (sum, item) => sum + (item.actual_amount ?? 0),
    0,
  )
})

// Keep remaining strictly consistent with envelope totals
const budgetRemaining = computed(() => budgetLimit.value - budgetSpent.value)

const spentPercent = computed(() => {
  if (budgetLimit.value <= 0) {
    return 0
  }

  return Math.min(
    100,
    Math.round((budgetSpent.value / budgetLimit.value) * 100),
  )
})

const isOverspent = computed(() => budgetRemaining.value < 0)

// Sort envelopes by highest consumption/activity:
// 1) actual_amount descending (where money was actually spent)
// 2) secondary: planned_amount descending (biggest envelopes)
const topEnvelopes = computed(() => {
  if (!props.budgetProgress?.length) {
    return []
  }

  return [...props.budgetProgress]
    .sort((a, b) => {
      const diffActual = (b.actual_amount ?? 0) - (a.actual_amount ?? 0)

      if (diffActual !== 0) {
        return diffActual
      }

      return (b.planned_amount ?? 0) - (a.planned_amount ?? 0)
    })
    .slice(0, 3)
})

// Calculate pacing based on the active budget's actual cycle range (or calendar fallback)
const cycleElapsedPercent = computed(() => {
  if (props.summaryCards.cycle_start && props.summaryCards.cycle_end) {
    const start = new Date(props.summaryCards.cycle_start).getTime()
    const end = new Date(props.summaryCards.cycle_end).getTime()
    const today = new Date().getTime()
    const totalCycleMs = Math.max(1, end - start)
    const elapsedMs = Math.max(0, Math.min(totalCycleMs, today - start))

    return Math.round((elapsedMs / totalCycleMs) * 100)
  }

  const now = new Date()
  const totalDaysInMonth = new Date(
    now.getFullYear(),
    now.getMonth() + 1,
    0,
  ).getDate()
  const dayOfMonth = now.getDate()

  return Math.round((dayOfMonth / totalDaysInMonth) * 100)
})

const cycleDaysRemaining = computed(() => {
  if (props.summaryCards.cycle_end) {
    const end = new Date(props.summaryCards.cycle_end).setHours(23, 59, 59, 999)
    const today = new Date().setHours(0, 0, 0, 0)

    return Math.max(0, Math.ceil((end - today) / (1000 * 60 * 60 * 24)))
  }

  const now = new Date()
  const totalDays = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()

  return Math.max(0, totalDays - now.getDate())
})

// Pacing delta: spent % minus cycle elapsed %
const pacingDelta = computed(
  () => spentPercent.value - cycleElapsedPercent.value,
)

const pacingStatus = computed(() => {
  if (isOverspent.value) {
    return {
      label: __('overspent') || 'Overspent',
      variant: 'destructive',
      class: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
    }
  }

  if (pacingDelta.value > 10) {
    return {
      label: `+${pacingDelta.value}% vs cycle`,
      variant: 'warning',
      class: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    }
  }

  return {
    label: __('on_track') || 'On track',
    variant: 'success',
    class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
  }
})
</script>

<template>
  <div
    class="rounded-none border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
  >
    <!-- Card Header -->
    <div
      class="mb-4 flex items-center justify-between border-b border-border pb-4"
    >
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-none border border-amber-500/30 bg-amber-500/10 text-amber-500 dark:text-amber-400"
          >
            <Gauge class="size-4" />
          </span>
          <h2
            class="font-mono text-sm font-bold tracking-wide text-foreground uppercase"
          >
            {{ __('budget_status') || 'Budget Pacing' }}
          </h2>
        </div>
        <p class="font-mono text-[11px] text-muted-foreground">
          <template v-if="hasBudget">
            {{ cycleDaysRemaining }}
            {{ __('days_remaining') || 'days left in cycle' }}
          </template>
          <template v-else>
            {{ __('no_active_budget') }}
          </template>
        </p>
      </div>

      <Link v-if="hasBudget" :href="budgetIndex.url()">
        <span
          class="inline-flex items-center gap-1 font-mono text-xs text-muted-foreground transition-colors hover:text-emerald-500 dark:hover:text-emerald-400"
        >
          {{ __('details') || 'Details' }}
          <ChevronRight class="size-3.5" />
        </span>
      </Link>
    </div>

    <!-- Content -->
    <div class="space-y-5">
      <template v-if="hasBudget">
        <!-- Main Stats Strip (3-Column Monospace Terminal Cards) -->
        <div
          class="grid grid-cols-3 gap-2 rounded-none border border-border bg-secondary/50 p-2.5 text-center sm:gap-3 sm:p-3"
        >
          <div class="min-w-0">
            <span
              class="font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >{{ __('limit') || 'Cap' }}</span
            >
            <p
              class="truncate font-mono text-xs font-bold text-foreground tabular-nums sm:text-sm"
            >
              {{ masked ? '••••' : formatAmount(budgetLimit) }}
            </p>
          </div>
          <div class="min-w-0">
            <span
              class="font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >{{ __('spent') }}</span
            >
            <p
              class="truncate font-mono text-xs font-bold text-rose-500 tabular-nums sm:text-sm dark:text-rose-400"
            >
              {{ masked ? '••••' : formatAmount(budgetSpent) }}
            </p>
          </div>
          <div class="min-w-0">
            <span
              class="font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >{{ __('remaining') }}</span
            >
            <p
              class="truncate font-mono text-xs font-extrabold tabular-nums sm:text-sm"
              :class="
                isOverspent
                  ? 'text-rose-500 dark:text-rose-400'
                  : 'text-emerald-500 dark:text-emerald-400'
              "
            >
              {{
                masked
                  ? '••••'
                  : (isOverspent ? '-' : '') +
                    formatAmount(Math.abs(budgetRemaining))
              }}
            </p>
          </div>
        </div>

        <!-- Pacing Comparison Bar -->
        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs">
            <span
              class="flex items-center gap-2 font-mono font-medium text-foreground"
            >
              <span>{{ spentPercent }}% {{ __('spent') }}</span>
              <span
                class="inline-flex items-center gap-1 rounded-none border px-1.5 py-0.5 font-mono text-[10px] font-bold"
                :class="pacingStatus.class"
              >
                <component
                  :is="
                    isOverspent
                      ? AlertTriangle
                      : pacingDelta > 10
                        ? TrendingUp
                        : CheckCircle2
                  "
                  class="size-3"
                />
                {{ pacingStatus.label }}
              </span>
            </span>

            <span
              class="flex items-center gap-1 font-mono text-[11px] text-muted-foreground"
            >
              <Clock class="size-3 text-muted-foreground" />
              {{ cycleDaysRemaining }} {{ __('days_remaining') || 'days left' }}
            </span>
          </div>

          <!-- Progress Bar -->
          <div
            class="relative h-2.5 w-full overflow-hidden rounded-none bg-secondary"
          >
            <div
              class="h-full rounded-none transition-all duration-500 ease-out"
              :class="
                isOverspent
                  ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)]'
                  : pacingDelta > 10
                    ? 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]'
                    : 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]'
              "
              :style="{
                width: `${spentPercent}%`,
              }"
            />
          </div>
        </div>

        <!-- Sub Envelopes Preview -->
        <div
          v-if="topEnvelopes.length"
          class="space-y-2.5 border-t border-border pt-3"
        >
          <div class="flex items-center justify-between">
            <span
              class="font-mono text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('top_envelopes') || 'Key Envelopes' }}
            </span>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in topEnvelopes"
              :key="item.category_id"
              class="space-y-1 text-xs"
            >
              <div class="flex items-center justify-between">
                <span
                  class="max-w-[180px] truncate font-medium text-foreground"
                >
                  {{ item.category?.name || 'Category' }}
                </span>
                <span
                  class="font-mono text-[11px] font-medium text-muted-foreground tabular-nums"
                >
                  {{ formatAmount(item.actual_amount ?? 0) }} /
                  {{ formatAmount(item.planned_amount) }}
                </span>
              </div>
              <div
                class="h-1.5 w-full overflow-hidden rounded-none bg-secondary"
              >
                <div
                  class="h-full rounded-none transition-all duration-300"
                  :class="
                    getProgressColor(
                      item.planned_amount,
                      item.actual_amount ?? 0,
                    )
                  "
                  :style="{
                    width: `${getProgressPercent(item.planned_amount, item.actual_amount ?? 0)}%`,
                  }"
                />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Empty State when no active budget is configured -->
      <div
        v-else
        class="flex flex-col items-center justify-center space-y-3 py-6 text-center"
      >
        <div
          class="flex size-12 items-center justify-center rounded-none border border-border bg-secondary/50 text-muted-foreground"
        >
          <CircleDollarSign class="size-6 text-amber-500 dark:text-amber-400" />
        </div>
        <div class="space-y-1">
          <p class="font-mono text-sm font-semibold text-foreground">
            {{ __('no_active_budget') }}
          </p>
          <p class="max-w-[240px] font-mono text-xs text-muted-foreground">
            {{ __('no_active_budget_description') }}
          </p>
        </div>
        <Link :href="budgetIndex.url()">
          <button
            type="button"
            class="rounded-none border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 font-mono text-xs font-semibold text-emerald-500 transition-all hover:bg-emerald-500/20 dark:text-emerald-400"
          >
            {{ __('create_budget') || 'Set Up Budget' }}
          </button>
        </Link>
      </div>
    </div>
  </div>
</template>
