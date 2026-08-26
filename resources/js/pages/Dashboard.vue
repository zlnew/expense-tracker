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
  VisScatter,
} from '@unovis/vue'
import { useMediaQuery } from '@vueuse/core'
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  Target,
  AlertTriangle,
  ChevronRight,
  Download,
  X,
} from 'lucide-vue-next'
import { computed } from 'vue'
import AppContent from '@/components/AppContent.vue'
import ChartEmpty from '@/components/ChartEmpty.vue'
import ImpendingDrainsCard from '@/components/dashboard/ImpendingDrainsCard.vue'
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
  ChartLegendContent,
  ChartTooltip,
  ChartCrosshair,
} from '@/components/ui/chart'
import ChartTooltipContent from '@/components/ui/chart/ChartTooltipContent.vue'
import { componentToString } from '@/components/ui/chart/utils'
import { Skeleton } from '@/components/ui/skeleton'
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
  expense_breakdown?: ExpenseBreakdown[]
  monthly_spending_trend?: MonthlySpendingTrend[]
  recent_transactions: RecentTransactions
  impending_drains: {
    window_days: number
    from: string
    until: string
    total_impending_outflow: number
    items: Array<{
      kind: 'fund_due' | 'recurring'
      id: number
      label: string
      amount: number
      balance_id: number
      balance_name: string
      due_date: string
      source: string
    }>
    per_balance: Array<{
      balance_id: number
      balance_name: string
      real: number
      impending: number
      projected_free_after: number
      would_go_negative: boolean
    }>
    has_negative_warning: boolean
  }
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

const isMobile = useMediaQuery('(max-width: 767px)')

// --- Monthly Spending Trend (Line Chart) ---
const lineChartConfig = {
  income: {
    label: __('income'),
    color: 'oklch(0.696 0.17 162.48)', // --income token
  },
  expense: {
    label: __('expense'),
    color: 'oklch(0.645 0.246 16.439)', // --expense token (light)
  },
}

const trendData = computed(() => {
  const data = props.monthly_spending_trend ?? []

  // Mobile shows the 6 most recent months; desktop shows the full year.
  if (!isMobile.value) {
    return data
  }

  const currentMonth = new Date().getMonth() + 1

  return data.filter((d) => d.month <= currentMonth).slice(-6)
})

const hasTrendData = computed(() => {
  return (props.monthly_spending_trend ?? []).some(
    (d) => d.income > 0 || d.expense > 0,
  )
})

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

// --- Expense Breakdown (ranked list <md, donut md+) ---
const BREAKDOWN_TOP = 7

const breakdownItems = computed<ExpenseBreakdown[]>(() => {
  const data = props.expense_breakdown ?? []

  if (data.length <= BREAKDOWN_TOP) {
    return data
  }

  const top = data.slice(0, BREAKDOWN_TOP)
  const rest = data.slice(BREAKDOWN_TOP)
  const restAmount = rest.reduce((sum, item) => sum + item.amount, 0)
  const total = data.reduce((sum, item) => sum + item.amount, 0)

  return [
    ...top,
    {
      category: __('others'),
      amount: restAmount,
      percentage: total > 0 ? Math.round((restAmount / total) * 100) : 0,
    },
  ]
})

const totalExpenseAmount = computed(() => {
  return breakdownItems.value.reduce((sum, item) => sum + item.amount, 0)
})

// Chart tokens are theme-aware; index 0..7 maps to --chart-1..8, so the
// top-7 + rollup never repeats a color.
const donutColor = (_d: ExpenseBreakdown, i: number) => {
  return `var(--chart-${(i % 8) + 1})`
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

// --- Budget Progress Helpers ---
const {
  getProgressPercent,
  getProgressColor,
  getProgressBgColor,
  getProgressTextColor,
} = useBudgetProgress()

const topBudgetProgress = computed(() =>
  props.budget_progress.filter(
    (bp) => bp.planned_amount > 0 || (bp.actual_amount ?? 0) > 0,
  ),
)
const topRecentTransactions = computed(() =>
  props.recent_transactions.slice(0, 5),
)

const hasActiveBudget = computed(() => props.summary_cards.has_active_budget)

// --- Two-up income/expense row (config-driven) ---
const statCards = computed(() => [
  {
    label: __('current_month_incomes'),
    value: formatAmount(props.summary_cards.current_month_incomes),
    icon: TrendingUp,
    iconClass:
      'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
  },
  {
    label: __('current_month_expenses'),
    value: formatAmount(props.summary_cards.current_month_expenses),
    icon: TrendingDown,
    iconClass:
      'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400',
  },
])
</script>

<template>
  <Head :title="__('dashboard')" />

  <AppContent>
    <div class="space-y-4 px-4 pb-4 sm:space-y-6 sm:px-6 md:pb-6 lg:px-8">
      <!-- Header -->
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-extrabold tracking-tight text-foreground">
          {{ __('dashboard') }}
        </h1>
        <p class="text-sm text-muted-foreground">
          {{ __('dashboard_description') }}
        </p>
      </div>

      <!-- Hero balance — US-1: headline Real, secondary Active/Reserved -->
      <Card class="border-border/50 bg-card shadow-xs">
        <CardContent class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('real_balance') }}
            </CardTitle>
            <div
              class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400"
            >
              <Wallet class="size-4" />
            </div>
          </div>
          <div
            class="min-w-0 truncate text-4xl font-extrabold tracking-tight text-foreground tabular-nums"
          >
            {{ formatAmount(summary_cards.total_balance) }}
          </div>
          <p
            v-if="summary_cards.total_active !== undefined"
            class="text-xs text-muted-foreground tabular-nums"
          >
            {{ __('active') }} {{ formatAmount(summary_cards.total_active) }} ·
            {{ __('reserved') }}
            {{ formatAmount(summary_cards.total_reserved ?? 0) }}
          </p>
          <Link
            :href="balanceIndex.url()"
            class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 transition-colors hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
          >
            {{ __('balance_list') }}
            <ChevronRight class="size-3" />
          </Link>
        </CardContent>
      </Card>

      <!-- Two-up income/expense -->
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <Card
          v-for="stat in statCards"
          :key="stat.label"
          class="border-border/50 bg-card shadow-xs"
        >
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0"
          >
            <CardTitle
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ stat.label }}
            </CardTitle>
            <div class="rounded-lg p-2" :class="stat.iconClass">
              <component :is="stat.icon" class="size-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div
              class="min-w-0 truncate text-xl font-bold tracking-tight text-foreground tabular-nums sm:text-2xl"
            >
              {{ stat.value }}
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Budget status band -->
      <div
        v-if="hasActiveBudget"
        role="region"
        :aria-label="__('remaining_budget')"
        class="flex items-center justify-between gap-3 rounded-xl border border-border/50 bg-card p-4 shadow-xs sm:p-5"
      >
        <div class="flex min-w-0 items-center gap-3">
          <div
            class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/30 dark:text-violet-400"
          >
            <Target class="size-4" />
          </div>
          <div class="min-w-0">
            <p
              class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
              {{ __('remaining_budget') }}
            </p>
            <p
              class="truncate text-lg font-bold tracking-tight tabular-nums sm:text-xl"
              :class="
                summary_cards.budget_remaining < 0
                  ? 'text-rose-600 dark:text-rose-400'
                  : 'text-foreground'
              "
            >
              {{ formatAmount(Math.abs(summary_cards.budget_remaining)) }}
              <span
                v-if="summary_cards.budget_remaining < 0"
                class="text-xs font-normal text-rose-500"
              >
                ({{ __('overspent') }})
              </span>
            </p>
          </div>
        </div>
        <Link :href="budgetIndex.url()" class="shrink-0">
          <Button variant="ghost" size="sm" class="gap-1 text-xs font-semibold">
            {{ __('see_all') }}
            <ChevronRight class="size-4" />
          </Button>
        </Link>
      </div>

      <!-- No active budget: the highest-value CTA -->
      <div
        v-else
        role="region"
        :aria-label="__('no_active_budget')"
        class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-border/60 bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
      >
        <div class="flex items-start gap-3 sm:items-center">
          <div
            class="rounded-lg bg-primary/10 p-2 text-primary dark:bg-primary/15"
          >
            <Target class="size-4" />
          </div>
          <div>
            <p class="text-sm font-semibold text-foreground">
              {{ __('no_active_budget') }}
            </p>
            <p class="mt-0.5 text-xs text-muted-foreground">
              {{ __('no_active_budget_description') }}
            </p>
          </div>
        </div>
        <Link :href="budgetIndex.url()" class="shrink-0">
          <Button size="sm" class="gap-1.5">
            {{ __('create_budget') }}
            <ChevronRight class="size-4" />
          </Button>
        </Link>
      </div>

      <ImpendingDrainsCard
        :window-days="60"
        :impending-drains-initial="props.impending_drains"
      />

      <!-- Budget progress (top-5) + Recent transactions -->
      <div class="grid items-start gap-4 sm:gap-6 md:grid-cols-2">
        <Card class="border-border/50 bg-card shadow-xs">
          <CardHeader
            class="flex flex-col justify-between space-y-0 sm:flex-row sm:items-center"
          >
            <div>
              <CardTitle class="text-base font-bold text-foreground">
                {{ __('budget_progress') }}
              </CardTitle>
              <CardDescription class="text-xs">
                {{ __('budget_progress_description') }}
              </CardDescription>
            </div>
            <Link :href="budgetIndex.url()">
              <Button
                variant="link"
                size="sm"
                class="flex h-8 items-center gap-1 px-0! text-xs font-semibold"
              >
                {{ __('see_all') }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent class="pt-2 pb-5">
            <div class="space-y-4">
              <div
                v-if="budget_progress.length === 0"
                class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
              >
                <Target class="mb-2 size-8 stroke-1 text-muted-foreground/30" />
                <span class="text-sm font-medium">{{
                  __('no_data_found', { data: __('budget') })
                }}</span>
              </div>
              <div
                v-for="bi in topBudgetProgress"
                :key="bi.id"
                class="-mx-2 space-y-1.5 rounded-lg px-2 py-2"
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

                <div
                  class="flex justify-between font-mono text-[11px] text-muted-foreground"
                >
                  <span>
                    {{ formatAmount(bi.actual_amount ?? 0) }} /
                    {{ formatAmount(bi.planned_amount) }}
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

        <Card class="border-border/50 bg-card shadow-xs">
          <CardHeader
            class="flex flex-col justify-between space-y-0 sm:flex-row sm:items-center"
          >
            <div>
              <CardTitle class="text-base font-bold text-foreground">
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
          <CardContent class="pt-2 pb-5">
            <div class="divide-y divide-border/40">
              <div
                v-if="recent_transactions.length === 0"
                class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
              >
                <Wallet class="mb-2 size-8 stroke-1 text-muted-foreground/30" />
                <span class="text-sm font-medium">{{
                  __('no_data_found', { data: __('transactions') })
                }}</span>
              </div>
              <div
                v-for="t in topRecentTransactions"
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
                      :is="t.type === 'income' ? TrendingUp : TrendingDown"
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
                    {{ t.type === 'income' ? '+' : '-'
                    }}{{ formatAmount(t.amount) }}
                  </span>
                  <p class="text-[10px] text-muted-foreground">
                    {{ formatDate(t.date) }}
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Expense breakdown: ranked list <md, donut md+ -->
      <Card class="border-border/50 bg-card shadow-xs">
        <CardHeader>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('expense_breakdown') }}
          </CardTitle>
          <CardDescription class="text-xs">
            {{ __('expense_breakdown_description') }}
          </CardDescription>
        </CardHeader>
        <CardContent class="pt-2 pb-5">
          <Skeleton
            v-if="expense_breakdown === undefined"
            class="h-[240px] w-full"
          />
          <ChartEmpty
            v-else-if="expense_breakdown.length === 0"
            :icon="TrendingDown"
            :title="
              hasActiveBudget ? __('no_breakdown_data') : __('no_active_budget')
            "
            :description="
              hasActiveBudget
                ? __('no_breakdown_data_description')
                : __('no_active_budget_description')
            "
          >
            <Link v-if="!hasActiveBudget" :href="budgetIndex.url()">
              <Button size="sm" class="mt-2 gap-1.5">
                {{ __('create_budget') }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </ChartEmpty>

          <template v-else>
            <div class="flex flex-col gap-6 md:flex-row md:items-center">
              <!-- Donut (md+) -->
              <div
                role="img"
                :aria-label="__('breakdown_chart_aria')"
                class="relative mx-auto hidden h-[180px] w-[180px] shrink-0 md:block lg:h-[200px] lg:w-[200px]"
              >
                <ChartContainer :config="donutConfig" class="h-full w-full">
                  <VisSingleContainer
                    :data="breakdownItems"
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
                <div
                  class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                >
                  <span
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >{{ __('total') }}</span
                  >
                  <span
                    class="mt-0.5 text-sm font-extrabold text-foreground tabular-nums"
                  >
                    {{ formatAmount(totalExpenseAmount) }}
                  </span>
                </div>
              </div>

              <!-- Ranked list: bars on mobile, legend on md+ -->
              <div
                role="img"
                :aria-label="__('breakdown_chart_aria')"
                class="min-w-0 flex-1 space-y-3"
              >
                <div
                  v-for="(item, i) in breakdownItems"
                  :key="item.category"
                  class="space-y-1"
                >
                  <div class="flex items-center justify-between gap-2 text-xs">
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
                      <span class="font-mono font-semibold text-foreground">{{
                        formatAmount(item.amount)
                      }}</span>
                      <span class="ml-1 font-mono text-muted-foreground"
                        >({{ item.percentage }}%)</span
                      >
                    </div>
                  </div>
                  <div
                    class="h-1.5 w-full overflow-hidden rounded-full bg-muted md:hidden"
                  >
                    <div
                      class="h-full rounded-full"
                      :style="{
                        width: `${Math.min(item.percentage, 100)}%`,
                        backgroundColor: donutColor(item, i),
                      }"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Screen-reader table fallback -->
            <table class="sr-only">
              <caption>
                {{
                  __('expense_breakdown')
                }}
              </caption>
              <thead>
                <tr>
                  <th scope="col">{{ __('category') }}</th>
                  <th scope="col">{{ __('amount') }}</th>
                  <th scope="col">%</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in breakdownItems" :key="item.category">
                  <td>{{ item.category }}</td>
                  <td>{{ formatAmount(item.amount) }}</td>
                  <td>{{ item.percentage }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </CardContent>
      </Card>

      <!-- Trend chart (last) -->
      <Card class="border-border/50 bg-card shadow-xs">
        <CardHeader>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('monthly_spending_trend') }}
          </CardTitle>
          <CardDescription class="text-xs">
            {{ __('monthly_spending_trend_description') }}
          </CardDescription>
        </CardHeader>
        <CardContent class="pt-2 pb-5">
          <Skeleton
            v-if="monthly_spending_trend === undefined"
            class="h-[180px] w-full sm:h-[260px] md:h-[300px]"
          />
          <ChartEmpty
            v-else-if="!hasTrendData"
            :icon="TrendingDown"
            :title="
              hasActiveBudget ? __('no_trend_data') : __('no_active_budget')
            "
            :description="
              hasActiveBudget
                ? __('no_trend_data_description')
                : __('no_active_budget_description')
            "
          >
            <Link v-if="!hasActiveBudget" :href="budgetIndex.url()">
              <Button size="sm" class="mt-2 gap-1.5">
                {{ __('create_budget') }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </ChartEmpty>

          <template v-else>
            <div
              role="img"
              :aria-label="__('trend_chart_aria')"
              class="h-[180px] w-full min-w-0 overflow-hidden sm:h-[260px] md:h-[300px]"
            >
              <ChartContainer :config="lineChartConfig" class="h-full w-full">
                <VisXYContainer
                  :data="trendData"
                  :margin="{ left: 44, right: 8, top: 10, bottom: 20 }"
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
                  <VisScatter
                    :x="x"
                    :y="incomeY"
                    color="var(--color-income)"
                    :size="6"
                  />
                  <VisScatter
                    :x="x"
                    :y="expenseY"
                    color="var(--color-expense)"
                    :size="6"
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
                    :numTicks="4"
                  />
                  <ChartTooltip />
                  <ChartCrosshair :template="lineTooltipTemplate" />
                </VisXYContainer>
                <ChartLegendContent />
              </ChartContainer>
            </div>

            <!-- Screen-reader table fallback -->
            <table class="sr-only">
              <caption>
                {{
                  __('monthly_spending_trend')
                }}
              </caption>
              <thead>
                <tr>
                  <th scope="col">{{ __('month') }}</th>
                  <th scope="col">{{ __('income') }}</th>
                  <th scope="col">{{ __('expense') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in trendData" :key="d.month">
                  <td>{{ tickFormatX(d.month) }}</td>
                  <td>{{ formatAmount(d.income) }}</td>
                  <td>{{ formatAmount(d.expense) }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </CardContent>
      </Card>
    </div>

    <!-- PWA Install Banner (dismissible, once per session) -->
    <div
      v-if="canInstall"
      role="region"
      :aria-label="__('install_banner_title')"
      class="fixed inset-x-4 bottom-20 z-fab md:inset-x-auto md:bottom-6 md:left-1/2 md:w-full md:max-w-md md:-translate-x-1/2"
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
