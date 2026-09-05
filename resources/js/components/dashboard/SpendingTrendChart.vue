<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  VisXYContainer,
  VisLine,
  VisArea,
  VisAxis,
  VisScatter,
} from '@unovis/vue'
import { TrendingDown, LineChart, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'
import ChartEmpty from '@/components/ChartEmpty.vue'
import {
  ChartContainer,
  ChartTooltip,
  ChartCrosshair,
} from '@/components/ui/chart'
import ChartTooltipContent from '@/components/ui/chart/ChartTooltipContent.vue'
import { componentToString } from '@/components/ui/chart/utils'
import { useLang } from '@/composables/useLang'
import { index as budgetIndex } from '@/routes/budgets'
import type { MonthlySpendingTrend } from '@/types'

const props = defineProps<{
  monthlySpendingTrend?: MonthlySpendingTrend[]
  hasActiveBudget?: boolean
}>()

const { __ } = useLang()

const INCOME_COLOR = '#10b981'
const EXPENSE_COLOR = '#f43f5e'

const lineChartConfig = {
  income: {
    label: __('income'),
    color: INCOME_COLOR,
  },
  expense: {
    label: __('expense'),
    color: EXPENSE_COLOR,
  },
}

const MONTH_NAMES = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
]

const FULL_MONTH_NAMES = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]

const lineChartTemplate = componentToString(
  lineChartConfig,
  ChartTooltipContent,
  {
    labelFormatter: (d) => {
      if (
        d &&
        typeof d === 'object' &&
        'month' in d &&
        typeof d.month === 'number'
      ) {
        return FULL_MONTH_NAMES[d.month - 1] ?? `Month ${d.month}`
      }

      return ''
    },
  },
)

const hasTrendData = computed(() => {
  return (
    (props.monthlySpendingTrend?.length ?? 0) > 0 &&
    props.monthlySpendingTrend!.some((d) => d.income > 0 || d.expense > 0)
  )
})

const trendData = computed(() => props.monthlySpendingTrend ?? [])

const x = (_d: MonthlySpendingTrend, i: number) => i
const incomeY = (d: MonthlySpendingTrend) => d.income
const expenseY = (d: MonthlySpendingTrend) => d.expense

const tickFormatX = (tick: number): string => {
  const item = trendData.value[tick]

  if (!item) {
    return ''
  }

  return MONTH_NAMES[item.month - 1] ?? `${item.month}`
}

const tickFormatY = (tick: number): string => {
  if (tick >= 1_000_000) {
    return `${(tick / 1_000_000).toFixed(1)}M`
  }

  if (tick >= 1_000) {
    return `${Math.round(tick / 1_000)}k`
  }

  return `${tick}`
}
</script>

<template>
  <div
    class="rounded-none border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
  >
    <!-- Header -->
    <div
      class="mb-4 flex flex-row items-center justify-between border-b border-border pb-4"
    >
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-none border border-blue-500/30 bg-blue-500/10 text-blue-500 dark:text-blue-400"
          >
            <LineChart class="size-4" />
          </span>
          <h2
            class="font-mono text-sm font-bold tracking-wide text-foreground uppercase"
          >
            {{ __('monthly_spending_trend') }}
          </h2>
        </div>
        <p class="font-mono text-[11px] text-muted-foreground">
          {{ __('monthly_spending_trend_description') }}
        </p>
      </div>

      <div class="flex items-center gap-3 font-mono text-xs font-semibold">
        <span
          class="flex items-center gap-1.5 text-emerald-500 dark:text-emerald-400"
        >
          <span
            class="size-2 rounded-none bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.8)] dark:bg-emerald-400"
          />
          {{ __('income') }}
        </span>
        <span
          class="flex items-center gap-1.5 text-rose-500 dark:text-rose-400"
        >
          <span
            class="size-2 rounded-none bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.8)] dark:bg-rose-400"
          />
          {{ __('expense') }}
        </span>
      </div>
    </div>

    <!-- Content -->
    <div>
      <div
        v-if="monthlySpendingTrend === undefined"
        class="h-[200px] w-full animate-pulse rounded-none border border-border bg-secondary/50 sm:h-[260px]"
      />

      <ChartEmpty
        v-else-if="!hasTrendData"
        :icon="TrendingDown"
        :title="hasActiveBudget ? __('no_trend_data') : __('no_active_budget')"
        :description="
          hasActiveBudget
            ? __('no_trend_data_description')
            : __('no_active_budget_description')
        "
      >
        <Link v-if="!hasActiveBudget" :href="budgetIndex.url()">
          <button
            type="button"
            class="mt-2 inline-flex items-center gap-1.5 rounded-none border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 font-mono text-xs font-semibold text-emerald-500 transition-all hover:bg-emerald-500/20 dark:text-emerald-400"
          >
            {{ __('create_budget') }}
            <ChevronRight class="size-3.5" />
          </button>
        </Link>
      </ChartEmpty>

      <template v-else>
        <div
          role="img"
          :aria-label="__('trend_chart_aria') || 'Monthly Spending Trend Chart'"
          class="h-[200px] w-full min-w-0 overflow-hidden font-mono sm:h-[260px]"
        >
          <ChartContainer
            :config="lineChartConfig"
            class="h-full w-full font-mono [&_.grid_line]:!stroke-border/40"
          >
            <VisXYContainer
              :data="trendData"
              :margin="{ left: 40, right: 12, top: 12, bottom: 24 }"
              class="h-full w-full"
            >
              <VisLine
                :x="x"
                :y="incomeY"
                :color="INCOME_COLOR"
                :lineWidth="2"
              />
              <VisLine
                :x="x"
                :y="expenseY"
                :color="EXPENSE_COLOR"
                :lineWidth="2"
              />
              <VisArea
                :x="x"
                :y="incomeY"
                :color="INCOME_COLOR"
                :opacity="0.08"
              />
              <VisArea
                :x="x"
                :y="expenseY"
                :color="EXPENSE_COLOR"
                :opacity="0.08"
              />
              <VisScatter
                :x="x"
                :y="incomeY"
                :color="INCOME_COLOR"
                :size="(d: MonthlySpendingTrend) => (d.income > 0 ? 5 : 0)"
                strokeColor="var(--card)"
                :strokeWidth="2"
              />
              <VisScatter
                :x="x"
                :y="expenseY"
                :color="EXPENSE_COLOR"
                :size="(d: MonthlySpendingTrend) => (d.expense > 0 ? 5 : 0)"
                strokeColor="var(--card)"
                :strokeWidth="2"
              />
              <VisAxis type="x" :tickFormat="tickFormatX" :gridLine="false" />
              <VisAxis
                type="y"
                :tickFormat="tickFormatY"
                :gridLine="true"
                :numTicks="4"
              />
              <ChartTooltip :template="lineChartTemplate" />
              <ChartCrosshair />
            </VisXYContainer>
          </ChartContainer>
        </div>
      </template>
    </div>
  </div>
</template>
