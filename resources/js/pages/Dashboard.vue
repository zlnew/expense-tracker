<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3'
import { Donut } from '@unovis/ts'
import {
  VisArea,
  VisAxis,
  VisDonut,
  VisLine,
  VisSingleContainer,
  VisXYContainer,
} from '@unovis/vue'
import { useMediaQuery } from '@vueuse/core'
import {
  AlertTriangle,
  ChevronRight,
  Download,
  PieChart,
  Target,
  TrendingDown,
  TrendingDownIcon,
  TrendingUp,
  TrendingUpIcon,
  Wallet,
  X,
} from 'lucide-vue-next'
import { computed } from 'vue'
import ChartEmpty from '@/components/ChartEmpty.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListSkeleton from '@/components/ListSkeleton.vue'
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
  ChartCrosshair,
  ChartLegendContent,
  ChartTooltip,
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
  recent_transactions: RecentTransactions
  // Deferred via Inertia::defer() — undefined until the follow-up request lands.
  expense_breakdown?: ExpenseBreakdown[]
  monthly_spending_trend?: MonthlySpendingTrend[]
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

const isNarrow = useMediaQuery('(max-width: 399px)')
const isMobile = useMediaQuery('(max-width: 767px)')

// --- Monthly Spending Trend (Line Chart) Configuration ---
const lineChartConfig = {
  income: {
    label: __('income'),
    color: 'var(--color-income)',
  },
  expense: {
    label: __('expense'),
    color: 'var(--color-expense)',
  },
}

const shortMonths = [
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

const x = (d: MonthlySpendingTrend) => d.month
const incomeY = (d: MonthlySpendingTrend) => d.income
const expenseY = (d: MonthlySpendingTrend) => d.expense

const tickFormatX = (monthNum: number | Date) => {
  const month =
    typeof monthNum === 'number' ? monthNum : monthNum.getMonth() + 1
  const name = shortMonths[month - 1] ? __(shortMonths[month - 1]) : ''

  if (!name) {
    return ''
  }

  // Single letter below 400px — 12 three-letter ticks don't fit.
  return isNarrow.value ? name.charAt(0) : name.substring(0, 3)
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
    labelFormatter: (monthNum: number | Date) => {
      const month =
        typeof monthNum === 'number' ? monthNum : monthNum.getMonth() + 1
      const key = shortMonths[month - 1]

      return key ? __(key) : String(month)
    },
  },
)

const trendData = computed<MonthlySpendingTrend[]>(() => {
  const data = props.monthly_spending_trend ?? []

  // Below md, 12 unrotated ticks at 375px are unreadable — last 6 months only.
  if (!isMobile.value || data.length <= 6) {
    return data
  }

  return data.slice(-6)
})

const trendAriaLabel = computed(() => {
  const data = props.monthly_spending_trend ?? []
  const income = data.reduce((sum, d) => sum + d.income, 0)
  const expense = data.reduce((sum, d) => sum + d.expense, 0)

  return `${__('monthly_spending_trend')}: ${__('income')} ${formatAmount(income)}, ${__('expense')} ${formatAmount(expense)}`
})

const trendEmptyMessage = computed(() =>
  props.summary_cards.has_active_budget
    ? __('no_data_found', { data: __('expense') })
    : __('no_active_budget'),
)

const trendEmptyDescription = computed(() =>
  props.summary_cards.has_active_budget
    ? __('monthly_spending_trend_description')
    : __('budget_create_description'),
)

// --- Expense Breakdown (ranked bars <md, donut at md+) ---
const chartColor = (i: number) => `var(--chart-${(i % 8) + 1})`

// Top 7 categories + everything else rolled into "Lainnya" — kills colour
// repetition (8 theme tokens, no duplicates) and unreadable 14-slice donuts.
const breakdownTop7 = computed<ExpenseBreakdown[]>(() => {
  const items = props.expense_breakdown ?? []

  if (items.length <= 7) {
    return items
  }

  const top = items.slice(0, 7)
  const others = items.slice(7)
  const othersAmount = others.reduce((sum, item) => sum + item.amount, 0)
  const total = items.reduce((sum, item) => sum + item.amount, 0)

  return [
    ...top,
    {
      category: __('others'),
      amount: othersAmount,
      percentage:
        total > 0 ? Number(((othersAmount / total) * 100).toFixed(2)) : 0,
    },
  ]
})

const totalExpenseAmount = computed(() =>
  breakdownTop7.value.reduce((sum, item) => sum + item.amount, 0),
)

const breakdownAriaLabel = computed(() => {
  if (breakdownTop7.value.length === 0) {
    return __('expense_breakdown')
  }

  return `${__('expense_breakdown')}: ${breakdownTop7.value
    .map((item) => `${item.category} ${formatAmount(item.amount)}`)
    .join(', ')}`
})

const breakdownEmptyMessage = computed(() =>
  props.summary_cards.has_active_budget
    ? __('no_data_found', { data: __('expense') })
    : __('no_active_budget'),
)

const breakdownEmptyDescription = computed(() =>
  props.summary_cards.has_active_budget
    ? __('expense_breakdown_description')
    : __('budget_create_description'),
)

const donutColor = (_d: ExpenseBreakdown, i: number) => chartColor(i)

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

const totalPlanned = computed(() =>
  props.budget_progress.reduce((sum, b) => sum + b.planned_amount, 0),
)

const totalActual = computed(() =>
  props.budget_progress.reduce((sum, b) => sum + (b.actual_amount ?? 0), 0),
)

const topBudgetProgress = computed(() =>
  [...props.budget_progress]
    .sort(
      (a, b) =>
        getProgressPercent(b.planned_amount, b.actual_amount ?? 0) -
        getProgressPercent(a.planned_amount, a.actual_amount ?? 0),
    )
    .slice(0, 5),
)
</script>

<template>
  <Head :title="__('dashboard')" />

  <div class="px-4 py-6 md:px-8">
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-1">
      <h1
        class="text-3xl font-extrabold tracking-tight text-foreground md:text-4xl"
      >
        {{ __('dashboard') }}
      </h1>
      <p class="text-sm text-muted-foreground">
        {{ __('dashboard_description') }}
      </p>
    </div>

    <!--
      Single markup tree: mobile order via order-*, desktop via md:/lg:col-span.
      No hidden-md forks — the same nodes reflow.
    -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-6">
      <!-- 1. Hero: balance + two-up income/expense row -->
      <Card
        class="order-1 border border-border/50 bg-card shadow-xs md:col-span-2 lg:col-span-6"
      >
        <CardHeader
          class="flex flex-row items-center justify-between space-y-0"
        >
          <CardTitle
            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
          >
            {{ __('total_balance') }}
          </CardTitle>
          <div
            class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400"
          >
            <Wallet class="size-4" />
          </div>
        </CardHeader>
        <CardContent>
          <div
            class="min-w-0 truncate text-4xl font-extrabold tracking-tight text-foreground tabular-nums"
          >
            {{ formatAmount(summary_cards.total_balance) }}
          </div>

          <!-- Compact two-up income/expense stats (stat rows, not cards) -->
          <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-border/40 bg-muted/30 p-3">
              <div
                class="flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >
                <TrendingUp class="size-3.5 text-emerald-500" />
                {{ __('current_month_incomes') }}
              </div>
              <div
                class="mt-1 truncate font-mono text-sm font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
              >
                +{{ formatAmount(summary_cards.current_month_incomes) }}
              </div>
            </div>
            <div class="rounded-xl border border-border/40 bg-muted/30 p-3">
              <div
                class="flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >
                <TrendingDown class="size-3.5 text-rose-500" />
                {{ __('current_month_expenses') }}
              </div>
              <div
                class="mt-1 truncate font-mono text-sm font-bold text-rose-600 tabular-nums dark:text-rose-400"
              >
                -{{ formatAmount(summary_cards.current_month_expenses) }}
              </div>
            </div>
          </div>

          <p class="mt-4 text-xs text-muted-foreground">
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

      <!-- 2. Budget status band -->
      <Card
        class="order-2 border border-border/50 bg-card shadow-xs md:col-span-2 lg:col-span-6"
      >
        <CardHeader
          class="flex flex-row items-center justify-between space-y-0"
        >
          <div>
            <CardTitle class="text-lg font-bold text-foreground">
              {{ __('remaining_budget') }}
            </CardTitle>
            <CardDescription class="text-xs">
              {{ __('budget_progress_description') }}
            </CardDescription>
          </div>
          <div
            class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/30 dark:text-violet-400"
          >
            <Target class="size-4" />
          </div>
        </CardHeader>
        <CardContent class="pt-2">
          <!-- No active budget: the highest-value empty state on the page -->
          <EmptyState
            v-if="!summary_cards.has_active_budget"
            :icon="Target"
            :title="__('no_active_budget')"
            :description="__('budget_create_description')"
            class="min-h-0! py-6"
          >
            <Link :href="budgetIndex.url()">
              <Button size="sm">
                {{ __('create_data', { data: __('budget') }) }}
                <ChevronRight class="size-4" />
              </Button>
            </Link>
          </EmptyState>

          <div v-else class="space-y-3">
            <div
              class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
            >
              <div>
                <span
                  class="text-3xl font-extrabold tracking-tight tabular-nums"
                  :class="
                    summary_cards.budget_remaining < 0
                      ? 'text-rose-600 dark:text-rose-400'
                      : 'text-foreground'
                  "
                >
                  {{ formatAmount(Math.abs(summary_cards.budget_remaining)) }}
                </span>
                <span
                  v-if="summary_cards.budget_remaining < 0"
                  class="ml-2 text-sm font-medium text-rose-500"
                >
                  {{ __('overspent') }}
                </span>
              </div>
              <span
                v-if="summary_cards.period_start && summary_cards.period_end"
                class="text-xs text-muted-foreground"
              >
                {{ __('period') }}:
                {{ formatDate(summary_cards.period_start) }} –
                {{ formatDate(summary_cards.period_end) }}
              </span>
            </div>

            <!-- One aggregate progress bar -->
            <div
              class="h-2 w-full overflow-hidden rounded-full"
              :class="getProgressBgColor(totalPlanned, totalActual)"
            >
              <div
                class="h-full rounded-full transition-all duration-500 ease-out"
                :class="getProgressColor(totalPlanned, totalActual)"
                :style="{
                  width: `${getProgressPercent(totalPlanned, totalActual)}%`,
                }"
              ></div>
            </div>
            <div
              class="flex justify-between font-mono text-[11px] text-muted-foreground"
            >
              <span>
                {{ formatAmount(totalActual) }} /
                {{ formatAmount(totalPlanned) }}
              </span>
              <span
                >{{ getProgressPercent(totalPlanned, totalActual) }}%
                {{ __('spent') }}</span
              >
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- 3. Budget progress list (top 5 by % spent) -->
      <Card
        v-if="summary_cards.has_active_budget"
        class="order-3 min-w-0 border border-border/50 bg-card shadow-xs lg:col-span-3"
      >
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
              variant="link"
              size="inline"
              class="flex items-center gap-1 text-xs font-semibold"
            >
              {{ __('all_data', { data: __('budgets') }) }}
              <ChevronRight class="size-4" />
            </Button>
          </Link>
        </CardHeader>
        <CardContent class="pt-2 pb-6">
          <div class="space-y-4">
            <div
              v-for="bi in topBudgetProgress"
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

      <!-- 4. Recent transactions -->
      <Card
        class="order-4 min-w-0 border border-border/50 bg-card shadow-xs lg:col-span-3"
      >
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
              size="inline"
              class="flex items-center gap-1 text-xs font-semibold"
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
              v-for="t in recent_transactions.slice(0, 5)"
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

      <!-- 5. Expense breakdown (deferred) -->
      <Deferred data="expense_breakdown">
        <template #fallback>
          <ListSkeleton
            :rows="4"
            :icon="false"
            class="order-5 border-border/50 shadow-xs md:order-6 lg:col-span-2"
          />
        </template>

        <template #default>
          <Card
            class="order-5 min-w-0 border border-border/50 bg-card shadow-xs md:order-6 lg:col-span-2"
          >
            <CardHeader>
              <CardTitle class="text-lg font-bold text-foreground">
                {{ __('expense_breakdown') }}
              </CardTitle>
              <CardDescription class="text-xs">
                {{ __('expense_breakdown_description') }}
              </CardDescription>
            </CardHeader>
            <CardContent class="pt-2 pb-6">
              <ChartEmpty
                v-if="breakdownTop7.length === 0"
                :icon="PieChart"
                :message="breakdownEmptyMessage"
                :description="breakdownEmptyDescription"
              >
                <Link
                  v-if="!summary_cards.has_active_budget"
                  :href="budgetIndex.url()"
                >
                  <Button size="sm" variant="outline">
                    {{ __('create_data', { data: __('budget') }) }}
                  </Button>
                </Link>
              </ChartEmpty>

              <template v-else>
                <!-- Donut: md+ only; the ranked bar list below is its legend -->
                <div
                  class="relative mx-auto hidden h-[160px] w-[160px] max-w-[180px] sm:h-[200px] sm:w-[200px] md:block"
                >
                  <ChartContainer
                    :config="donutConfig"
                    role="img"
                    :aria-label="breakdownAriaLabel"
                    class="h-full w-full"
                  >
                    <VisSingleContainer
                      :data="breakdownTop7"
                      aria-hidden="true"
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

                <!-- Ranked horizontal bar list: the real <ul> fallback -->
                <ul class="space-y-2.5">
                  <li v-for="(item, i) in breakdownTop7" :key="item.category">
                    <div
                      class="flex items-center justify-between gap-2 text-xs"
                    >
                      <div class="flex min-w-0 items-center gap-2">
                        <span
                          class="h-2.5 w-2.5 shrink-0 rounded-full"
                          :style="{ backgroundColor: chartColor(i) }"
                        />
                        <span class="truncate font-medium text-foreground">{{
                          item.category
                        }}</span>
                      </div>
                      <div class="shrink-0 text-right font-mono">
                        <span class="font-semibold text-foreground">{{
                          formatAmount(item.amount)
                        }}</span>
                        <span class="ml-1 text-muted-foreground"
                          >{{ item.percentage }}%</span
                        >
                      </div>
                    </div>
                    <div
                      class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                    >
                      <div
                        class="h-full rounded-full transition-all duration-500 ease-out"
                        :style="{
                          width: `${item.percentage}%`,
                          backgroundColor: chartColor(i),
                        }"
                      ></div>
                    </div>
                  </li>
                </ul>

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
                    <tr v-for="item in breakdownTop7" :key="item.category">
                      <td>{{ item.category }}</td>
                      <td>{{ formatAmount(item.amount) }}</td>
                      <td>{{ item.percentage }}%</td>
                    </tr>
                  </tbody>
                </table>
              </template>
            </CardContent>
          </Card>
        </template>
      </Deferred>

      <!-- 6. Monthly spending trend (deferred, last) -->
      <Deferred data="monthly_spending_trend">
        <template #fallback>
          <ListSkeleton
            :rows="4"
            :icon="false"
            class="order-6 border-border/50 shadow-xs md:order-5 lg:col-span-4"
          />
        </template>

        <template #default>
          <Card
            class="order-6 min-w-0 border border-border/50 bg-card shadow-xs md:order-5 lg:col-span-4"
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
              <ChartEmpty
                v-if="trendData.length === 0"
                :icon="TrendingDown"
                :message="trendEmptyMessage"
                :description="trendEmptyDescription"
              >
                <Link
                  v-if="!summary_cards.has_active_budget"
                  :href="budgetIndex.url()"
                >
                  <Button size="sm" variant="outline">
                    {{ __('create_data', { data: __('budget') }) }}
                  </Button>
                </Link>
              </ChartEmpty>

              <template v-else>
                <div class="h-[180px] sm:h-[260px] md:h-[300px]">
                  <ChartContainer
                    :config="lineChartConfig"
                    role="img"
                    :aria-label="trendAriaLabel"
                    class="h-full w-full justify-start"
                  >
                    <VisXYContainer
                      :data="trendData"
                      aria-hidden="true"
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
                      <ChartCrosshair :template="lineTooltipTemplate" />
                    </VisXYContainer>
                    <ChartLegendContent verticalAlign="bottom" />
                  </ChartContainer>
                </div>

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
        </template>
      </Deferred>
    </div>

    <!-- PWA Install Banner (dismissible, once per session) -->
    <div
      v-if="canInstall"
      role="region"
      :aria-label="__('install_banner_title')"
      class="fixed inset-x-4 bottom-[calc(var(--bottom-nav-height)+0.75rem)] z-fab md:inset-x-auto md:bottom-6 md:left-1/2 md:w-full md:max-w-md md:-translate-x-1/2"
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
  </div>
</template>
