<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  CircleDollarSign,
  AlertTriangle,
  CheckCircle2,
  ChevronRight,
  TrendingUp,
  Clock,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
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
      class:
        'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
    }
  }

  if (pacingDelta.value > 10) {
    return {
      label: `+${pacingDelta.value}% vs cycle`,
      variant: 'warning',
      class:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
    }
  }

  return {
    label: __('on_track') || 'On track',
    variant: 'success',
    class: 'bg-income/10 text-income border-income/20',
  }
})
</script>

<template>
  <Card class="border-border/70 bg-card shadow-sm">
    <CardHeader class="flex flex-row items-center justify-between pb-3">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg bg-budget-pacing/10 text-budget-pacing"
          >
            <Gauge class="size-4" />
          </span>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('budget_status') || 'Budget Pacing' }}
          </CardTitle>
        </div>
        <CardDescription class="text-xs">
          <template v-if="hasBudget">
            {{ cycleDaysRemaining }}
            {{ __('days_remaining') || 'days left in cycle' }}
          </template>
          <template v-else>
            {{ __('no_active_budget') }}
          </template>
        </CardDescription>
      </div>

      <Link v-if="hasBudget" :href="budgetIndex.url()">
        <Button
          variant="ghost"
          size="sm"
          class="h-8 gap-1 text-xs text-muted-foreground hover:text-foreground"
        >
          {{ __('details') || 'Details' }}
          <ChevronRight class="size-3.5" />
        </Button>
      </Link>
    </CardHeader>

    <CardContent class="space-y-5 pt-1">
      <template v-if="hasBudget">
        <!-- Main Stats Strip (Responsive for Mobile) -->
        <div
          class="grid grid-cols-3 gap-1.5 rounded-xl border border-border/40 bg-muted/40 p-2.5 text-center sm:gap-2 sm:p-3"
        >
          <div class="min-w-0">
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('limit') || 'Cap' }}</span
            >
            <p
              class="truncate text-xs font-bold text-foreground tabular-nums sm:text-sm"
            >
              {{ masked ? '••••' : formatAmount(budgetLimit) }}
            </p>
          </div>
          <div class="min-w-0">
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('spent') }}</span
            >
            <p
              class="truncate text-xs font-bold text-expense tabular-nums sm:text-sm"
            >
              {{ masked ? '••••' : formatAmount(budgetSpent) }}
            </p>
          </div>
          <div class="min-w-0">
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('remaining') }}</span
            >
            <p
              class="truncate text-xs font-bold tabular-nums sm:text-sm"
              :class="
                isOverspent ? 'font-extrabold text-rose-500' : 'text-income'
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
            <span class="flex items-center gap-1.5 font-medium text-foreground">
              <span>{{ spentPercent }}% {{ __('spent') }}</span>
              <span
                class="inline-flex items-center gap-0.5 rounded-md border px-1.5 py-0.5 text-[10px] font-bold"
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
              class="flex items-center gap-1 text-[11px] text-muted-foreground"
            >
              <Clock class="size-3" />
              {{ cycleDaysRemaining }} {{ __('days_remaining') || 'days left' }}
            </span>
          </div>

          <!-- Progress Bar -->
          <div
            class="relative h-3 w-full overflow-hidden rounded-full bg-muted/60"
          >
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :class="
                isOverspent
                  ? 'bg-rose-500'
                  : pacingDelta > 10
                    ? 'bg-amber-500'
                    : 'bg-budget-pacing'
              "
              :style="{
                width: `${spentPercent}%`,
              }"
            />
          </div>
        </div>

        <!-- Sub Envelopes Preview (if available) -->
        <div
          v-if="budgetProgress?.length"
          class="space-y-3 border-t border-border/40 pt-2"
        >
          <div class="flex items-center justify-between">
            <span
              class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('top_envelopes') || 'Key Envelopes' }}
            </span>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in budgetProgress.slice(0, 3)"
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
                  class="text-[11px] font-medium text-muted-foreground tabular-nums"
                >
                  {{ formatAmount(item.actual_amount ?? 0) }} /
                  {{ formatAmount(item.planned_amount) }}
                </span>
              </div>
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full transition-all duration-300"
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
          class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          <CircleDollarSign class="size-6" />
        </div>
        <div class="space-y-1">
          <p class="text-sm font-semibold text-foreground">
            {{ __('no_active_budget') }}
          </p>
          <p class="max-w-[220px] text-xs text-muted-foreground">
            Set monthly category caps to prevent overspending and track your
            burn rate.
          </p>
        </div>
        <Link :href="budgetIndex.url()">
          <Button size="sm" variant="outline" class="h-8 text-xs font-semibold">
            {{ __('create_budget') || 'Set Up Budget' }}
          </Button>
        </Link>
      </div>
    </CardContent>
  </Card>
</template>
