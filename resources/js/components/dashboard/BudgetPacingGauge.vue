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

const budgetRemaining = computed(() => {
  return (
    props.summaryCards.budget_remaining ?? budgetLimit.value - budgetSpent.value
  )
})

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

// Calculate calendar month pacing
const now = new Date()
const totalDaysInMonth = new Date(
  now.getFullYear(),
  now.getMonth() + 1,
  0,
).getDate()
const dayOfMonth = now.getDate()
const calendarElapsedPercent = Math.round((dayOfMonth / totalDaysInMonth) * 100)

const pacingDelta = computed(() => spentPercent.value - calendarElapsedPercent)
const isPacingFast = computed(
  () => pacingDelta.value > 10 && !isOverspent.value,
)
</script>

<template>
  <Card class="border-border/70 bg-card shadow-sm">
    <CardHeader class="flex flex-row items-center justify-between pb-3">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg bg-budget-pacing/10 text-budget-pacing"
          >
            <CircleDollarSign class="size-4" />
          </span>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('budget_status') || 'Budget Pacing' }}
          </CardTitle>
        </div>
        <CardDescription class="text-xs">
          {{
            hasBudget
              ? `${dayOfMonth} of ${totalDaysInMonth} days elapsed (${calendarElapsedPercent}%)`
              : __('no_active_budget')
          }}
        </CardDescription>
      </div>

      <Link v-if="hasBudget" :href="budgetIndex.url()">
        <Button
          variant="ghost"
          size="sm"
          class="h-8 gap-1 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
        >
          {{ __('details') || 'Envelopes' }}
          <ChevronRight class="size-3.5" />
        </Button>
      </Link>
    </CardHeader>

    <CardContent class="space-y-5 pt-1">
      <template v-if="hasBudget">
        <!-- Main Stats Strip -->
        <div
          class="grid grid-cols-3 gap-2 rounded-xl border border-border/40 bg-muted/40 p-3 text-center"
        >
          <div>
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('limit') || 'Cap' }}</span
            >
            <p class="text-sm font-bold text-foreground tabular-nums">
              {{ masked ? '••••' : formatAmount(budgetLimit) }}
            </p>
          </div>
          <div>
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('spent') }}</span
            >
            <p class="text-sm font-bold text-expense tabular-nums">
              {{ masked ? '••••' : formatAmount(budgetSpent) }}
            </p>
          </div>
          <div>
            <span
              class="text-[10px] font-medium text-muted-foreground uppercase"
              >{{ __('remaining') }}</span
            >
            <p
              class="text-sm font-bold tabular-nums"
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
                v-if="isOverspent"
                class="inline-flex items-center gap-0.5 rounded-md bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold text-rose-600 dark:text-rose-400"
              >
                <AlertTriangle class="size-3" />
                {{ __('overspent') }}
              </span>
              <span
                v-else-if="isPacingFast"
                class="inline-flex items-center gap-0.5 rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400"
              >
                <TrendingUp class="size-3" />
                +{{ pacingDelta }}% vs calendar
              </span>
              <span
                v-else
                class="inline-flex items-center gap-0.5 rounded-md bg-income/10 px-1.5 py-0.5 text-[10px] font-bold text-income"
              >
                <CheckCircle2 class="size-3" />
                {{ __('on_track') || 'On Track' }}
              </span>
            </span>

            <span
              class="flex items-center gap-1 text-[11px] text-muted-foreground"
            >
              <Clock class="size-3" />
              {{ totalDaysInMonth - dayOfMonth }} days left
            </span>
          </div>

          <!-- Progress Bar with Calendar Marker -->
          <div
            class="relative h-3 w-full overflow-hidden rounded-full bg-muted/60"
          >
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :class="
                isOverspent
                  ? 'bg-rose-500'
                  : isPacingFast
                    ? 'bg-amber-500'
                    : 'bg-budget-pacing'
              "
              :style="{
                width: `${budgetLimit > 0 ? Math.min(100, (budgetSpent / budgetLimit) * 100) : 0}%`,
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
