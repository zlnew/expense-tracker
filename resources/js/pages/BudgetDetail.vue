<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useHttp } from '@inertiajs/vue3'
import {
  AlertTriangle,
  CheckCircle2,
  CalendarDays,
  SquarePen,
} from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import DataListState from '@/components/DataListState.vue'
import Heading from '@/components/Heading.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { useBudgetProgress } from '@/composables/useBudgetProgress'
import { useDate } from '@/composables/useDate'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import {
  index as budgetIndex,
  edit as budgetEdit,
  setActive as budgetSetActive,
  show as budgetShow,
} from '@/routes/budgets'
import type { Budget, Transaction } from '@/types'

const props = defineProps<{
  budget: Budget
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { formatDate } = useDate()
const {
  getProgressPercent,
  getProgressColor,
  getProgressBgColor,
  getProgressTextColor,
} = useBudgetProgress()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('budgets'),
      href: budgetIndex.url(),
    },
    {
      title: __('detail'),
    },
  ],
})

function resolveEffectiveCutoff(
  year: number,
  month: number,
  cutoffDay: number,
): number {
  const lastDayOfMonth = new Date(year, month, 0).getDate()

  return Math.min(cutoffDay, lastDayOfMonth)
}

function resolveCurrentCycleDate(cutoffDay: number): {
  month: number
  year: number
} {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth() + 1
  const today = now.getDate()

  const effectiveCutoff = resolveEffectiveCutoff(year, month, cutoffDay)

  if (today > effectiveCutoff) {
    const nextMonth = new Date(year, month, 1)

    return {
      month: nextMonth.getMonth() + 1,
      year: nextMonth.getFullYear(),
    }
  }

  return { month, year }
}

const currentCycle = resolveCurrentCycleDate(props.budget.cutoff_day)

// Month/year live in the URL (?month=&year=) instead of local state, so a
// reload or browser back/forward restores the exact cycle being viewed.
const { month: monthFilter, year: yearFilter } = useFilters({
  url: budgetShow.url({ budget: props.budget }),
  defaults: {
    month: String(currentCycle.month),
    year: String(currentCycle.year),
  },
})

const selectedMonth = computed(
  () => Number(monthFilter.value) || currentCycle.month,
)
const selectedYear = computed(
  () => Number(yearFilter.value) || currentCycle.year,
)

const transactionApi = useHttp({
  budget: props.budget.id,
  month: selectedMonth.value,
  year: String(selectedYear.value),
})

const transactions = ref<Transaction[]>([])

// True while the transactions API fetch is in flight — shows the skeleton.
const transactionsLoading = ref(false)

// Non-null when the fetch failed — DataListState swaps in an error + retry.
const transactionsError = ref<string | null>(null)

const months = computed(() => {
  const monthNames = [
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

  return monthNames.map((month, index) => ({
    label: __(month),
    value: String(index + 1),
  }))
})

const years = computed(() => {
  const startYear = new Date(props.budget.period_start).getFullYear()
  const endYear = new Date(props.budget.period_end).getFullYear()

  return Array.from({ length: endYear - startYear + 1 }, (_, index) => {
    const year = (startYear + index).toString()

    return {
      label: year,
      value: year,
    }
  })
})

const expenses = computed(() =>
  transactions.value.filter((t) => t.type === 'expense'),
)

const incomes = computed(() =>
  transactions.value.filter((t) => t.type === 'income'),
)

const expenseBudgetItems = computed(() => {
  const data = props.budget.expenses ?? []

  return data.map((d) => {
    const actualAmount = expenses.value
      .filter((e) => e.budget_item_id === d.id)
      .reduce((acc, curr) => acc + curr.amount, 0)

    const diffAmount = d.planned_amount - actualAmount

    return {
      ...d,
      actual_amount: actualAmount,
      diff_amount: diffAmount,
    }
  })
})

const incomeBudgetItems = computed(() => {
  const data = props.budget.incomes ?? []

  return data.map((d) => {
    const actualAmount = incomes.value
      .filter((e) => e.budget_item_id === d.id)
      .reduce((acc, curr) => acc + curr.amount, 0)

    const diffAmount = d.planned_amount - actualAmount

    return {
      ...d,
      actual_amount: actualAmount,
      diff_amount: diffAmount,
    }
  })
})

const plannedExpenseTotal = computed(() =>
  expenseBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

const actualExpenseTotal = computed(() =>
  expenseBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.actual_amount ?? 0),
    0,
  ),
)

const diffExpenseTotal = computed(() =>
  expenseBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.diff_amount ?? 0),
    0,
  ),
)

const plannedIncomeTotal = computed(() =>
  incomeBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

const actualIncomeTotal = computed(() =>
  incomeBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.actual_amount ?? 0),
    0,
  ),
)

const diffIncomeTotal = computed(() =>
  incomeBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.diff_amount ?? 0),
    0,
  ),
)

// Monotonic sequence so a stale in-flight response (user flipped months
// twice) can never overwrite the freshest one.
let fetchSeq = 0

const fetchTransactions = async () => {
  const seq = ++fetchSeq

  transactionsLoading.value = true
  transactionsError.value = null

  try {
    transactionApi.month = selectedMonth.value
    transactionApi.year = String(selectedYear.value)
    const res = await transactionApi.get('/api/transactions')

    if (seq !== fetchSeq) {
      return
    }

    transactions.value = res as Transaction[]
  } catch (error) {
    if (seq !== fetchSeq) {
      return
    }

    const apiError = error as Error
    transactionsError.value = apiError.message
  } finally {
    if (seq === fetchSeq) {
      transactionsLoading.value = false
    }
  }
}

watch([monthFilter, yearFilter], fetchTransactions)

onMounted(fetchTransactions)

const setActive = () => {
  router.post(
    budgetSetActive.url({ budget: props.budget }),
    {},
    {
      preserveScroll: true,
      onSuccess: (res) => {
        toast.success(
          (res.props.flash as any)?.success ??
            __('updated_data', { data: __('budget') }),
        )
      },
    },
  )
}
</script>

<template>
  <Head :title="__('detail_data', { data: __('budget') })" />

  <div>
    <div
      class="space-y-6 px-4 pt-6 pb-[var(--bottom-nav-height)] md:px-8 md:pb-22"
    >
      <div class="flex items-start justify-between">
        <Heading
          :title="__('detail_data', { data: __('budget') })"
          :description="__('budget_detail_description')"
        />
        <Badge v-if="budget.is_active">{{ __('active') }}</Badge>
      </div>

      <div class="space-y-4">
        <div class="flex flex-col gap-1">
          <div class="text-sm font-semibold">{{ __('periods') }}</div>
          <div class="text-lg">
            {{ formatDate(budget.period_start, 'DD MMM YYYY') }} -
            {{ formatDate(budget.period_end, 'DD MMM YYYY') }}
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <div class="text-sm font-semibold">{{ __('cutoff_day') }}</div>
          <div class="text-lg">{{ budget.cutoff_day }}</div>
        </div>

        <div v-if="budget.carry_over" class="flex flex-col gap-1">
          <div class="text-sm font-semibold">{{ __('carry_over') }}</div>
          <div class="max-w-md text-muted-foreground">
            {{ __('carry_over_description') }}
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <div class="text-sm font-semibold">{{ __('notes') }}</div>
          <div class="max-w-md text-muted-foreground">
            {{ budget.notes ?? '-' }}
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <div class="text-sm font-semibold">{{ __('last_updated_at') }}</div>
          <div>
            {{
              budget.updated_at
                ? formatDate(budget.updated_at, 'DD MMM YYYY HH:mm')
                : '-'
            }}
          </div>
        </div>
      </div>

      <Separator />

      <div class="flex items-center justify-center gap-2">
        <CalendarDays class="mr-1 text-muted-foreground" />
        <Select v-model="monthFilter">
          <SelectTrigger class="h-10 md:h-9" :aria-label="__('month')">
            <SelectValue :placeholder="__('month')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="m in months" :key="m.value" :value="m.value">
              {{ m.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Select v-model="yearFilter">
          <SelectTrigger class="h-10 md:h-9" :aria-label="__('year')">
            <SelectValue :placeholder="__('year')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="y in years" :key="y.value" :value="y.value">
              {{ y.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Skeleton / error+retry / empty / content — one owner for both cards,
           since they share a single transactions fetch. -->
      <DataListState
        :loading="transactionsLoading"
        :error="transactionsError"
        :is-empty="
          !transactionsLoading &&
          !transactionsError &&
          expenseBudgetItems.length === 0 &&
          incomeBudgetItems.length === 0
        "
        :rows="4"
        :empty-icon="CalendarDays"
        :empty-title="__('no_data_found', { data: __('transactions') })"
      >
        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
          <Card>
            <CardHeader>
              <CardTitle>{{ __('monthly_expenses') }}</CardTitle>
            </CardHeader>
            <CardContent>
              <div
                v-if="expenseBudgetItems.length === 0"
                class="py-8 text-center text-muted-foreground"
              >
                {{ __('no_data_found', { data: __('category') }) }}
              </div>

              <!-- Budget progress style (mobile + desktop), matching Dashboard -->
              <div v-else class="space-y-4">
                <div
                  v-for="(exp, index) in expenseBudgetItems"
                  :key="index"
                  class="-mx-2 space-y-1.5 rounded-lg px-2 py-2 transition-colors duration-200 hover:bg-muted/10"
                >
                  <div class="flex items-center justify-between text-sm">
                    <div class="flex min-w-0 items-center gap-1.5">
                      <span class="truncate font-bold text-foreground">
                        {{ exp.category?.name || __('unknown') }}
                      </span>
                      <span
                        v-if="exp.actual_amount > exp.planned_amount"
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
                          exp.planned_amount,
                          exp.actual_amount ?? 0,
                        )
                      "
                    >
                      {{
                        getProgressPercent(
                          exp.planned_amount,
                          exp.actual_amount ?? 0,
                        )
                      }}% {{ __('spent') }}
                    </span>
                  </div>

                  <!-- Progress Bar -->
                  <div
                    class="h-2 w-full overflow-hidden rounded-full"
                    :class="
                      getProgressBgColor(
                        exp.planned_amount,
                        exp.actual_amount ?? 0,
                      )
                    "
                  >
                    <div
                      class="h-full rounded-full transition-all duration-500 ease-out"
                      :class="
                        getProgressColor(
                          exp.planned_amount,
                          exp.actual_amount ?? 0,
                        )
                      "
                      :style="{
                        width: `${getProgressPercent(exp.planned_amount, exp.actual_amount ?? 0)}%`,
                      }"
                    ></div>
                  </div>

                  <!-- Amounts detail -->
                  <div
                    class="flex justify-between font-mono text-[11px] text-muted-foreground"
                  >
                    <span>
                      {{ formatAmount(exp.actual_amount ?? 0) }} /
                      {{ formatAmount(exp.planned_amount) }}
                    </span>
                    <span
                      :class="
                        exp.diff_amount < 0
                          ? 'text-rose-500'
                          : 'text-emerald-500'
                      "
                    >
                      {{
                        exp.diff_amount < 0
                          ? `-${formatAmount(Math.abs(exp.diff_amount))}`
                          : `${__('remaining')} ${formatAmount(exp.diff_amount)}`
                      }}
                    </span>
                  </div>
                </div>

                <!-- Totals -->
                <div
                  v-if="expenseBudgetItems.length > 0"
                  class="flex flex-col gap-1 border-t pt-4 text-sm font-bold"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">{{ __('total') }}</span>
                    <div class="flex flex-col items-end">
                      <span>
                        {{ __('planned') }}:
                        {{ formatAmount(plannedExpenseTotal) }}
                      </span>
                      <span>
                        {{ __('actual') }}:
                        {{ formatAmount(actualExpenseTotal) }}
                      </span>
                      <span
                        :class="
                          diffExpenseTotal < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ __('diff') }}: {{ formatAmount(diffExpenseTotal) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{{ __('monthly_incomes') }}</CardTitle>
            </CardHeader>
            <CardContent>
              <div
                v-if="incomeBudgetItems.length === 0"
                class="py-8 text-center text-muted-foreground"
              >
                {{ __('no_data_found', { data: __('category') }) }}
              </div>

              <!-- Same progress-bar presentation as the expenses card -->
              <div v-else class="space-y-4">
                <div
                  v-for="(inc, index) in incomeBudgetItems"
                  :key="index"
                  class="-mx-2 space-y-1.5 rounded-lg px-2 py-2 transition-colors duration-200 hover:bg-muted/10"
                >
                  <div class="flex items-center justify-between text-sm">
                    <div class="flex min-w-0 items-center gap-1.5">
                      <span class="truncate font-bold text-foreground">
                        {{ inc.category?.name || __('unknown') }}
                      </span>
                    </div>
                    <span
                      class="text-xs font-semibold"
                      :class="
                        getProgressTextColor(
                          inc.planned_amount,
                          inc.actual_amount ?? 0,
                        )
                      "
                    >
                      {{
                        getProgressPercent(
                          inc.planned_amount,
                          inc.actual_amount ?? 0,
                        )
                      }}% {{ __('collected') }}
                    </span>
                  </div>

                  <!-- Progress Bar -->
                  <div
                    class="h-2 w-full overflow-hidden rounded-full"
                    :class="
                      getProgressBgColor(
                        inc.planned_amount,
                        inc.actual_amount ?? 0,
                      )
                    "
                  >
                    <div
                      class="h-full rounded-full transition-all duration-500 ease-out"
                      :class="
                        getProgressColor(
                          inc.planned_amount,
                          inc.actual_amount ?? 0,
                        )
                      "
                      :style="{
                        width: `${getProgressPercent(inc.planned_amount, inc.actual_amount ?? 0)}%`,
                      }"
                    ></div>
                  </div>

                  <!-- Amounts detail -->
                  <div
                    class="flex justify-between font-mono text-[11px] text-muted-foreground"
                  >
                    <span>
                      {{ formatAmount(inc.actual_amount ?? 0) }} /
                      {{ formatAmount(inc.planned_amount) }}
                    </span>
                    <span
                      :class="
                        inc.diff_amount < 0
                          ? 'text-rose-500'
                          : 'text-emerald-500'
                      "
                    >
                      {{
                        inc.diff_amount < 0
                          ? `-${formatAmount(Math.abs(inc.diff_amount))}`
                          : `${__('remaining')} ${formatAmount(inc.diff_amount)}`
                      }}
                    </span>
                  </div>
                </div>

                <!-- Totals -->
                <div
                  v-if="incomeBudgetItems.length > 0"
                  class="flex flex-col gap-1 border-t pt-4 text-sm font-bold"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">{{ __('total') }}</span>
                    <div class="flex flex-col items-end">
                      <span>
                        {{ __('planned') }}:
                        {{ formatAmount(plannedIncomeTotal) }}
                      </span>
                      <span>
                        {{ __('actual') }}:
                        {{ formatAmount(actualIncomeTotal) }}
                      </span>
                      <span
                        :class="
                          diffIncomeTotal < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ __('diff') }}: {{ formatAmount(diffIncomeTotal) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </DataListState>
    </div>
  </div>

  <div
    class="fixed right-4 bottom-[calc(var(--bottom-nav-height)+0.75rem)] z-fab flex gap-2 md:right-8 md:bottom-8"
  >
    <Button
      v-if="!budget.is_active"
      type="button"
      size="lg"
      variant="secondary"
      class="rounded-full shadow-xl"
      @click="setActive"
    >
      <CheckCircle2 class="mr-2 size-4" />
      <span>{{ __('set_as_active') }}</span>
    </Button>
    <Button type="submit" size="lg" class="rounded-full shadow-xl" asChild>
      <Link :href="budgetEdit.url({ budget: budget })">
        <SquarePen />
        <span>{{ __('edit') }}</span>
      </Link>
    </Button>
  </div>
</template>
