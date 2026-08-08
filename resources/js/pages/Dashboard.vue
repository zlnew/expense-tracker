<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3'
import { Donut } from '@unovis/ts'
import {
  VisXYContainer,
  VisLine,
  VisArea,
  VisAxis,
  VisSingleContainer,
  VisDonut,
} from '@unovis/vue'
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  Target,
  TrendingUpIcon,
  AlertTriangle,
  ChevronRight,
  Download,
  TrendingDownIcon,
  X,
} from 'lucide-vue-next'
import { computed } from 'vue'
import AppContent from '@/components/AppContent.vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  ChartContainer,
  ChartTooltip,
  ChartCrosshair,
} from '@/components/ui/chart'
import ChartTooltipContent from '@/components/ui/chart/ChartTooltipContent.vue'
import { componentToString } from '@/components/ui/chart/utils'
import { useBudgetProgress } from '@/composables/useBudgetProgress'
import { useDate } from '@/composables/useDate'
import { useInstallPrompt } from '@/composables/useInstallPrompt'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { dashboard } from '@/routes'
import { index as balanceIndex } from '@/routes/balances'
import { index as budgetIndex } from '@/routes/budgets'
import { index as transactionIndex } from '@/routes/transactions'
import type {
  BudgetProgress,
  ExpenseBreakdown,
  MonthlySpendingTrend,
  RecentTransactions,
  SummaryCards,
} from '@/types'

const props = defineProps<{
  summary_cards: SummaryCards
  budget_progress: BudgetProgress
  expense_breakdown: ExpenseBreakdown[]
  monthly_spending_trend: MonthlySpendingTrend[]
  recent_transactions: RecentTransactions
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()
const { canInstall, promptInstall, dismissInstall } = useInstallPrompt()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('dashboard'),
      href: dashboard(),
    },
  ],
})

// --- Monthly Spending Trend (Line Chart) Configuration ---
const lineChartConfig = {
  income: {
    label: __('income'),
    color: '#10b981', // emerald-500
  },
  expense: {
    label: __('expense'),
    color: '#f43f5e', // rose-500
  },
}

const x = (d: MonthlySpendingTrend) => d.month
const incomeY = (d: MonthlySpendingTrend) => d.income
const expenseY = (d: MonthlySpendingTrend) => d.expense

const tickFormatX = (monthNum: number) => {
  const shortMonths = [
    __('january').substring(0, 3),
    __('february').substring(0, 3),
    __('march').substring(0, 3),
    __('april').substring(0, 3),
    __('may').substring(0, 3),
    __('june').substring(0, 3),
    __('july').substring(0, 3),
    __('august').substring(0, 3),
    __('september').substring(0, 3),
    __('october').substring(0, 3),
    __('november').substring(0, 3),
    __('december').substring(0, 3),
  ]

  return shortMonths[monthNum - 1] || ''
}

const tickFormatY = (value: number) => {
  if (value >= 1_000_000) {
    return `Rp ${(value / 1_000_000).toFixed(1)}jt`
  }

  if (value >= 1_000) {
    return `Rp ${(value / 1_000).toFixed(0)}rb`
  }

  return `Rp ${value}`
}

const lineTooltipTemplate = componentToString(
  lineChartConfig,
  ChartTooltipContent,
  {
    labelFormatter: (x: any) => {
      const months = [
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
        'september',
        'october',
        'november',
        'december',
      ]
      const key = months[Number(x) - 1]

      return key ? __(key) : String(x)
    },
  },
)

// --- Expense Breakdown (Pie/Donut Chart) Configuration ---
const totalExpenseAmount = computed(() => {
  return props.expense_breakdown.reduce((sum, item) => sum + item.amount, 0)
})

const donutColors = [
  '#3b82f6', // blue-500
  '#10b981', // emerald-500
  '#f59e0b', // amber-500
  '#f43f5e', // rose-500
  '#8b5cf6', // violet-500
  '#ec4899', // pink-500
  '#14b8a6', // teal-500
  '#f97316', // orange-500
  '#06b6d4', // cyan-500
  '#a855f7', // purple-500
]

const donutColor = (d: ExpenseBreakdown, i: number) => {
  return donutColors[i % donutColors.length]
}

const donutConfig = {
  amount: {
    label: __('amount'),
  },
}

const donutTemplate = componentToString(donutConfig, ChartTooltipContent, {
  labelKey: 'category',
})

const donutTriggers = {
  [Donut.selectors.segment]: donutTemplate,
}

// --- Budget Progress Helpers (shared with BudgetDetail) ---
const {
  getProgressPercent,
  getProgressColor,
  getProgressBgColor,
  getProgressTextColor,
} = useBudgetProgress()
</script>

<template>
  <Head :title="__('dashboard')" />

  <AppContent>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <!-- Header -->
      <div class="flex flex-col gap-1">
        <h1
          class="text-3xl font-extrabold tracking-tight text-foreground md:text-4xl"
        >
          {{ __('dashboard') }}
        </h1>
        <p class="text-sm text-muted-foreground">
          {{ __('dashboard_description') }}
        </p>
      </div>

      <!-- Summary Cards Grid -->
      <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <!-- Total Balance -->
        <Card
          class="relative overflow-hidden border border-border/50 bg-card shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md py-3 gap-3 sm:gap-6 sm:py-6"
        >
          <div
            class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-blue-500/5 blur-xl dark:bg-blue-400/5"
          ></div>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 px-3 pb-2 sm:px-6"
          >
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('total_balance') }}
            </CardTitle>
            <div
              class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400"
            >
              <Wallet class="size-4" />
            </div>
          </CardHeader>
          <CardContent class="px-3 sm:px-6">
            <div
              class="min-w-0 truncate text-xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
            >
              {{ formatAmount(summary_cards.total_balance) }}
            </div>
            <p
              class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
            >
              <Link
                :href="balanceIndex.url()"
                class="inline-flex items-center font-medium text-blue-600 transition-colors hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
              >
                {{ __('balance_list') }}
                <ChevronRight class="ml-0.5 size-3" />
              </Link>
            </p>
          </CardContent>
        </Card>

        <!-- Monthly Incomes -->
        <Card
          class="relative overflow-hidden border border-border/50 bg-card shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md py-3 gap-3 sm:gap-6 sm:py-6"
        >
          <div
            class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-emerald-500/5 blur-xl dark:bg-emerald-400/5"
          ></div>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 px-3 pb-2 sm:px-6"
          >
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('current_month_incomes') }}
            </CardTitle>
            <div
              class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400"
            >
              <TrendingUp class="size-4" />
            </div>
          </CardHeader>
          <CardContent class="px-3 sm:px-6">
            <div
              class="min-w-0 truncate text-xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
            >
              {{ formatAmount(summary_cards.current_month_incomes) }}
            </div>
            <p
              class="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
            >
              {{ __('active_income') }}
            </p>
          </CardContent>
        </Card>

        <!-- Monthly Expenses -->
        <Card
          class="relative overflow-hidden border border-border/50 bg-card shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md py-3 gap-3 sm:gap-6 sm:py-6"
        >
          <div
            class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-rose-500/5 blur-xl dark:bg-rose-400/5"
          ></div>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 px-3 pb-2 sm:px-6"
          >
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('current_month_expenses') }}
            </CardTitle>
            <div
              class="rounded-lg bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400"
            >
              <TrendingDown class="size-4" />
            </div>
          </CardHeader>
          <CardContent class="px-3 sm:px-6">
            <div
              class="min-w-0 truncate text-xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
            >
              {{ formatAmount(summary_cards.current_month_expenses) }}
            </div>
            <p
              class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400"
            >
              {{ __('active_expense') }}
            </p>
          </CardContent>
        </Card>

        <!-- Budget Remaining -->
        <Card
          class="relative overflow-hidden border border-border/50 bg-card shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md py-3 gap-3 sm:gap-6 sm:py-6"
        >
          <div
            class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-violet-500/5 blur-xl dark:bg-violet-400/5"
          ></div>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 px-3 pb-2 sm:px-6"
          >
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('remaining_budget') }}
            </CardTitle>
            <div
              class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/30 dark:text-violet-400"
            >
              <Target class="size-4" />
            </div>
          </CardHeader>
          <CardContent class="px-3 sm:px-6">
            <div
              class="min-w-0 truncate text-xl font-bold tracking-tight tabular-nums sm:text-3xl"
              :class="
                summary_cards.budget_remaining < 0
                  ? 'text-rose-600 dark:text-rose-400'
                  : 'text-foreground'
              "
            >
              {{ formatAmount(Math.abs(summary_cards.budget_remaining)) }}
              <span
                v-if="summary_cards.budget_remaining < 0"
                class="block text-xs font-normal text-rose-500 sm:ml-1 sm:inline-block"
              >
                (Overspent)
              </span>
            </div>
            <p
              class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
            >
              <Link
                :href="budgetIndex.url()"
                class="inline-flex items-center font-medium text-violet-600 transition-colors hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300"
              >
                {{ __('budget_list') }}
                <ChevronRight class="ml-0.5 size-3" />
              </Link>
            </p>
          </CardContent>
        </Card>
      </div>

      <!-- Charts Section -->
      <div class="grid min-w-0 gap-6 lg:grid-cols-3">
        <!-- Monthly Spending Trend (Line Chart) -->
        <Card
          class="min-w-0 border border-border/50 bg-card shadow-xs lg:col-span-2"
        >
          <CardHeader>
            <CardTitle class="text-lg font-bold text-foreground">
              {{ __('monthly_spending_trend') }}
            </CardTitle>
            <CardDescription class="text-xs">
              {{ __('monthly_spending_trend_description') }}
            </CardDescription>
          </CardHeader>
          <CardContent class="pt-2 pb-6">
            <div
              class="mb-4 flex items-center justify-end gap-4 text-xs font-medium"
            >
              <div class="flex items-center gap-1.5">
                <span
                  class="inline-block h-0.5 w-3 rounded-full bg-emerald-500"
                ></span>
                <span>{{ __('income') }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span
                  class="inline-block h-0.5 w-3 rounded-full bg-rose-500"
                ></span>
                <span>{{ __('expense') }}</span>
              </div>
            </div>

            <div class="h-[220px] min-w-0 w-full overflow-hidden sm:h-[300px]">
              <ChartContainer :config="lineChartConfig" class="h-full w-full">
                <VisXYContainer
                  :data="monthly_spending_trend"
                  :margin="{ left: 10, right: 8, top: 10, bottom: 20 }"
                  class="h-full w-full"
                >
                  <VisLine
                    :x="x"
                    :y="incomeY"
                    color="var(--color-income)"
                    :lineWidth="2.5"
                  />
                  <VisLine
                    :x="x"
                    :y="expenseY"
                    color="var(--color-expense)"
                    :lineWidth="2.5"
                  />
                  <VisArea
                    :x="x"
                    :y="incomeY"
                    color="var(--color-income)"
                    :opacity="0.04"
                  />
                  <VisArea
                    :x="x"
                    :y="expenseY"
                    color="var(--color-expense)"
                    :opacity="0.04"
                  />
                  <VisAxis
                    type="x"
                    :tickFormat="tickFormatX"
                    :gridLine="false"
                  />
                  <VisAxis
                    type="y"
                    :tickFormat="tickFormatY"
                    :gridLine="true"
                    :numTicks="5"
                  />
                  <ChartCrosshair :template="lineTooltipTemplate" />
                </VisXYContainer>
              </ChartContainer>
            </div>
          </CardContent>
        </Card>

        <!-- Expense Breakdown (Pie/Donut Chart) -->
        <Card class="min-w-0 border border-border/50 bg-card shadow-xs">
          <CardHeader>
            <CardTitle class="text-lg font-bold text-foreground">
              {{ __('expense_breakdown') }}
            </CardTitle>
            <CardDescription class="text-xs">
              {{ __('expense_breakdown_description') }}
            </CardDescription>
          </CardHeader>
          <CardContent class="pt-2 pb-6">
            <div
              v-if="expense_breakdown.length === 0"
              class="flex h-[260px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-border/60 p-6 text-center text-muted-foreground"
            >
              <TrendingDown
                class="mb-2 size-8 stroke-1 text-muted-foreground/40"
              />
              <span class="text-sm font-medium">{{
                __('no_data_found', { data: __('expense') })
              }}</span>
              <span class="mt-1 text-xs text-muted-foreground">{{
                __('transaction_list_description')
              }}</span>
            </div>

            <div v-else class="flex flex-col gap-6">
              <!-- Donut Chart Wrapper -->
              <div class="relative mx-auto h-[160px] w-[160px] max-w-[180px] sm:h-[200px] sm:w-[200px]">
                <ChartContainer :config="donutConfig" class="h-full w-full">
                  <VisSingleContainer
                    :data="expense_breakdown"
                    class="h-full w-full"
                  >
                    <VisDonut
                      :value="(d: ExpenseBreakdown) => d.amount"
                      :name="(d: ExpenseBreakdown) => d.category"
                      :arcWidth="20"
                      :color="donutColor"
                    />
                    <ChartTooltip :triggers="donutTriggers" />
                  </VisSingleContainer>
                </ChartContainer>
                <!-- Centered Text -->
                <div
                  class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                >
                  <span
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >Total</span
                  >
                  <span
                    class="mt-0.5 text-sm font-extrabold text-foreground tabular-nums"
                  >
                    {{ formatAmount(totalExpenseAmount) }}
                  </span>
                </div>
              </div>

              <!-- Custom Detailed Legend -->
              <div class="max-h-[140px] space-y-1.5 overflow-y-auto pr-1">
                <div
                  v-for="(item, i) in expense_breakdown"
                  :key="item.category"
                  class="flex items-center justify-between border-b border-border/20 py-1.5 text-xs last:border-b-0"
                >
                  <div class="flex min-w-0 items-center gap-2">
                    <span
                      class="h-2.5 w-2.5 shrink-0 rounded-full"
                      :style="{ backgroundColor: donutColor(item, i) }"
                    />
                    <span class="truncate font-medium text-foreground">{{
                      item.category
                    }}</span>
                  </div>
                  <div class="shrink-0 pl-2 text-right">
                    <span class="font-mono font-semibold text-foreground"
                      >{{ formatAmount(item.amount) }}</span
                    >
                    <span class="ml-1 font-mono text-muted-foreground"
                      >({{ item.percentage }}%)</span
                    >
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Data Feed Section -->
      <div class="grid items-start gap-6 md:grid-cols-2">
        <!-- Recent Transactions -->
        <Card class="border border-border/50 bg-card shadow-xs">
          <CardHeader
            class="flex flex-col justify-between space-y-0 sm:flex-row sm:items-center"
          >
            <div>
              <CardTitle class="text-lg font-bold text-foreground">
                {{ __('recent_transactions') }}
              </CardTitle>
              <CardDescription class="text-xs">
                {{ __('recent_transactions_description') }}
              </CardDescription>
            </div>
            <Link :href="transactionIndex.url()">
              <Button
                variant="link"
                size="sm"
                class="flex h-8 items-center gap-1 px-0! text-xs font-semibold"
              >
                {{ __('all_data', { data: __('transactions') }) }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent class="pt-2 pb-6">
            <div class="divide-y divide-border/40">
              <div
                v-if="recent_transactions.length === 0"
                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
              >
                <Wallet class="mb-2 size-8 stroke-1 text-muted-foreground/30" />
                <span class="text-sm font-medium">{{
                  __('no_data_found', { data: __('transactions') })
                }}</span>
              </div>
              <div
                v-for="t in recent_transactions"
                :key="t.id"
                class="-mx-2 flex items-center justify-between rounded-lg px-2 py-3 transition-colors duration-200 hover:bg-muted/10"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <div
                    class="rounded-lg p-2 text-xs font-semibold"
                    :class="
                      t.type === 'income'
                        ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                        : 'bg-rose-50 text-rose-600 dark:bg-rose-950/20 dark:text-rose-400'
                    "
                  >
                    <component
                      :is="
                        t.type === 'income' ? TrendingUpIcon : TrendingDownIcon
                      "
                      class="size-4"
                    />
                  </div>
                  <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="truncate text-sm font-semibold text-foreground"
                      >
                        {{ t.category?.name || __('unknown') }}
                      </span>
                      <span class="text-[10px] text-muted-foreground">
                        via {{ t.balance?.name || __('unknown') }}
                      </span>
                    </div>
                    <p
                      class="max-w-[220px] truncate text-xs text-muted-foreground"
                    >
                      {{ t.description || '-' }}
                    </p>
                  </div>
                </div>
                <div class="shrink-0 pl-3 text-right">
                  <span
                    class="font-mono text-sm font-bold"
                    :class="
                      t.type === 'income'
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-rose-600 dark:text-rose-400'
                    "
                  >
                    {{ t.type === 'income' ? '+' : '-' }}{{ formatAmount(t.amount) }}
                  </span>
                  <p class="text-[10px] text-muted-foreground">
                    {{ formatDate(t.date) }}
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Budget Progress -->
        <Card class="border border-border/50 bg-card shadow-xs">
          <CardHeader
            class="flex flex-col justify-between space-y-0 sm:flex-row sm:items-center"
          >
            <div>
              <CardTitle class="text-lg font-bold text-foreground">
                {{ __('budget_progress') }}
              </CardTitle>
              <CardDescription class="text-xs">
                {{ __('budget_progress_description') }}
              </CardDescription>
            </div>
            <Link :href="budgetIndex.url()">
              <Button
                variant="ghost"
                size="sm"
                class="flex h-8 items-center gap-1 px-0! text-xs font-semibold"
              >
                {{ __('all_data', { data: __('budgets') }) }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent class="pt-2 pb-6">
            <div class="space-y-4">
              <div
                v-if="budget_progress.length === 0"
                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
              >
                <Target class="mb-2 size-8 stroke-1 text-muted-foreground/30" />
                <span class="text-sm font-medium">{{
                  __('no_data_found', { data: __('budget') })
                }}</span>
                <span class="mt-1 text-xs">{{
                  __('budget_create_description')
                }}</span>
              </div>
              <div
                v-for="bi in budget_progress"
                :key="bi.id"
                class="-mx-2 space-y-1.5 rounded-lg px-2 py-2 transition-colors duration-200 hover:bg-muted/10"
              >
                <div class="flex items-center justify-between text-sm">
                  <div class="flex min-w-0 items-center gap-1.5">
                    <span class="truncate font-bold text-foreground">
                      {{ bi.category?.name || __('unknown') }}
                    </span>
                    <span
                      v-if="
                        bi.actual_amount !== undefined &&
                        bi.planned_amount > 0 &&
                        bi.actual_amount > bi.planned_amount
                      "
                      class="inline-flex items-center gap-0.5 rounded-full bg-rose-50 px-1.5 py-0.5 text-[9px] font-semibold text-rose-600 dark:bg-rose-950/20 dark:text-rose-400"
                    >
                      <AlertTriangle class="size-2.5" />
                      {{ __('overspent') }}
                    </span>
                  </div>
                  <span
                    class="text-xs font-semibold"
                    :class="
                      getProgressTextColor(
                        bi.planned_amount,
                        bi.actual_amount ?? 0,
                      )
                    "
                  >
                    {{
                      getProgressPercent(
                        bi.planned_amount,
                        bi.actual_amount ?? 0,
                      )
                    }}% {{ __('spent') }}
                  </span>
                </div>

                <!-- Progress Bar -->
                <div
                  class="h-2 w-full overflow-hidden rounded-full"
                  :class="
                    getProgressBgColor(bi.planned_amount, bi.actual_amount ?? 0)
                  "
                >
                  <div
                    class="h-full rounded-full transition-all duration-500 ease-out"
                    :class="
                      getProgressColor(bi.planned_amount, bi.actual_amount ?? 0)
                    "
                    :style="{
                      width: `${getProgressPercent(bi.planned_amount, bi.actual_amount ?? 0)}%`,
                    }"
                  ></div>
                </div>

                <!-- Amounts detail -->
                <div
                  class="flex justify-between font-mono text-[11px] text-muted-foreground"
                >
                  <span>
                    {{ formatAmount(bi.actual_amount ?? 0) }} / {{ formatAmount(bi.planned_amount) }}
                  </span>
                  <span
                    v-if="bi.actual_amount !== undefined"
                    :class="
                      bi.planned_amount - bi.actual_amount < 0
                        ? 'text-rose-500'
                        : 'text-emerald-500'
                    "
                  >
                    {{
                      bi.planned_amount - bi.actual_amount < 0
                        ? `-${formatAmount(Math.abs(bi.planned_amount - bi.actual_amount))}`
                        : `${__('remaining')} ${formatAmount(bi.planned_amount - bi.actual_amount)}`
                    }}
                  </span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- PWA Install Banner (dismissible, once per session) -->
    <div
      v-if="canInstall"
      role="region"
      aria-label="Install app"
      class="fixed inset-x-4 bottom-20 z-50 md:inset-x-auto md:bottom-6 md:left-1/2 md:w-full md:max-w-md md:-translate-x-1/2"
    >
      <div
        class="flex items-center gap-3 rounded-xl border border-border/60 bg-card p-3 shadow-lg"
      >
        <div
          class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
        >
          <Download class="size-4" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-foreground">
            {{ __('install_banner_title') }}
          </p>
          <p class="truncate text-xs text-muted-foreground">
            {{ __('install_banner_description') }}
          </p>
        </div>
        <Button size="sm" class="shrink-0" @click="promptInstall">
          {{ __('install_banner_action') }}
        </Button>
        <Button
          variant="ghost"
          size="icon-sm"
          class="shrink-0"
          :aria-label="__('install_banner_dismiss')"
          @click="dismissInstall"
        >
          <X class="size-4" />
        </Button>
      </div>
    </div>
  </AppContent>
</template>
