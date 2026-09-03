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
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
import {
  ChartContainer,
  ChartTooltip,
  ChartCrosshair,
} from '@/components/ui/chart'
import ChartTooltipContent from '@/components/ui/chart/ChartTooltipContent.vue'
import { componentToString } from '@/components/ui/chart/utils'
import { Skeleton } from '@/components/ui/skeleton'
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
  <Card class="border-border/70 bg-card shadow-sm">
    <CardHeader class="flex flex-row items-center justify-between pb-3">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
          >
            <LineChart class="size-4" />
          </span>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('monthly_spending_trend') }}
          </CardTitle>
        </div>
        <CardDescription class="text-xs">
          {{ __('monthly_spending_trend_description') }}
        </CardDescription>
      </div>

      <div class="flex items-center gap-3 text-xs font-semibold">
        <span class="flex items-center gap-1.5 text-income">
          <span class="size-2 rounded-full bg-income" />
          {{ __('income') }}
        </span>
        <span class="flex items-center gap-1.5 text-expense">
          <span class="size-2 rounded-full bg-expense" />
          {{ __('expense') }}
        </span>
      </div>
    </CardHeader>

    <CardContent class="pt-1">
      <Skeleton
        v-if="monthlySpendingTrend === undefined"
        class="h-[200px] w-full rounded-xl sm:h-[260px]"
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
          <Button size="sm" class="mt-2 gap-1.5 text-xs font-semibold">
            {{ __('create_budget') }}
            <ChevronRight class="size-3.5" />
          </Button>
        </Link>
      </ChartEmpty>

      <template v-else>
        <div
          role="img"
          :aria-label="__('trend_chart_aria') || 'Monthly Spending Trend Chart'"
          class="h-[200px] w-full min-w-0 overflow-hidden sm:h-[260px]"
        >
          <ChartContainer :config="lineChartConfig" class="h-full w-full">
            <VisXYContainer
              :data="trendData"
              :margin="{ left: 40, right: 12, top: 12, bottom: 24 }"
              class="h-full w-full"
            >
              <VisLine
                :x="x"
                :y="incomeY"
                :color="INCOME_COLOR"
                :lineWidth="2.5"
              />
              <VisLine
                :x="x"
                :y="expenseY"
                :color="EXPENSE_COLOR"
                :lineWidth="2.5"
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
    </CardContent>
  </Card>
</template>
