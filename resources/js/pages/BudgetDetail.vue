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
import AppContent from '@/components/AppContent.vue'
import DataListState from '@/components/DataListState.vue'
import Heading from '@/components/Heading.vue'
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
  transactions as budgetTransactions,
} from '@/routes/budgets'
import type { Budget, BudgetTransactionsResponse, Transaction } from '@/types'

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

// Month/year live in the URL query (useFilters) so reload restores them and
// back/forward round-trips them — previously they were lost on both.
const { month, year } = useFilters({
  url: budgetShow.url({ budget: props.budget }),
  defaults: {
    month: currentCycle.month.toString(),
    year: currentCycle.year.toString(),
  },
})

const transactionApi = useHttp({
  budget: props.budget.id,
  month: month.value,
  year: year.value,
})

const transactions = ref<Transaction[]>([])

// Envelope extras from the web transactions response (2026-08-16 spec):
// payout transactions are budget-exempt (excluded from expense actuals) and
// fund set-asides count as reserved (added to expense actuals).
const payoutIds = ref<Set<number>>(new Set())
const reserved = ref<Record<string, number>>({})

// True while the transactions API fetch is in flight — shows the skeleton.
const transactionsLoading = ref(false)

// API failure — rendered in DataListState's error slot with Retry.
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
    value: (index + 1).toString(),
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
    // Envelope actuals: sum the month's expense rows for this item EXCLUDING
    // fund payouts (already reserved when set aside), then add this item's
    // fund set-asides for the browsed cycle month.
    const actualAmount =
      expenses.value
        .filter((e) => e.budget_item_id === d.id && !payoutIds.value.has(e.id))
        .reduce((acc, curr) => acc + curr.amount, 0) +
      (reserved.value[d.id] ?? 0)

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

watch([month, year], () => {
  fetchTransactions()
})

onMounted(() => {
  fetchTransactions()
})

const fetchTransactions = async () => {
  transactionsLoading.value = true
  transactionsError.value = null

  // useHttp merges its data object into the GET query string, so carry the
  // URL-backed month/year through the form data.
  transactionApi.month = month.value
  transactionApi.year = year.value

  try {
    // Web (session-auth) route mirroring the API read — /api/* is Sanctum
    // token-only per ADR 2026-08-12, so browser sessions cannot auth there.
    // useHttp keeps merging {budget, month, year} into the query string.
    const res = await transactionApi.get(
      budgetTransactions.url({ budget: props.budget.id }),
    )

    const data = res as unknown as BudgetTransactionsResponse
    transactions.value = data.transactions
    payoutIds.value = new Set(data.fund.payout_transaction_ids)
    reserved.value = data.fund.reserved
  } catch (error) {
    const apiError = error as Error
    transactionsError.value = apiError.message
  } finally {
    transactionsLoading.value = false
  }
}

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

  <AppContent>
    <div class="space-y-6 px-4 pt-6 pb-[var(--bottom-nav-height)] md:px-8">
      <div class="flex items-start justify-between">
        <Heading
          :title="__('detail_data', { data: __('budget') })"
          :description="__('budget_detail_description')"
        />
        <span
          v-if="budget.is_active"
          class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-3 py-1 font-mono text-xs font-bold text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.25)]"
        >
          <CheckCircle2 class="size-3.5" />
          {{ __('active') }}
        </span>
      </div>

      <!-- Budget Meta Grid -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-4 font-mono text-xs">
        <div class="space-y-1">
          <div class="text-[10px] text-zinc-500 uppercase tracking-wider">{{ __('periods') }}</div>
          <div class="text-xs font-bold text-zinc-200">
            {{ formatDate(budget.period_start, 'DD MMM') }} -
            {{ formatDate(budget.period_end, 'DD MMM YYYY') }}
          </div>
        </div>

        <div class="space-y-1">
          <div class="text-[10px] text-zinc-500 uppercase tracking-wider">{{ __('cutoff_day') }}</div>
          <div class="text-xs font-bold text-zinc-200">Tgl {{ budget.cutoff_day }}</div>
        </div>

        <div class="space-y-1">
          <div class="text-[10px] text-zinc-500 uppercase tracking-wider">{{ __('carry_over') }}</div>
          <div class="text-xs font-bold" :class="budget.carry_over ? 'text-emerald-400' : 'text-zinc-400'">
            {{ budget.carry_over ? 'Aktif' : 'Non-aktif' }}
          </div>
        </div>

        <div class="space-y-1">
          <div class="text-[10px] text-zinc-500 uppercase tracking-wider">{{ __('notes') }}</div>
          <div class="truncate text-xs text-zinc-300">
            {{ budget.notes ?? '-' }}
          </div>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="flex items-center justify-center gap-2 lg:col-span-2">
          <CalendarDays class="mr-1 text-zinc-500 size-4" />
          <select
            v-model="month"
            class="rounded-xl border border-[#1f222e] bg-[#0a0a0c] px-3 py-1.5 font-mono text-xs text-zinc-200 focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
          >
            <option v-for="m in months" :key="m.value" :value="m.value" class="bg-[#12141a] text-zinc-200">
              {{ m.label }}
            </option>
          </select>
          <select
            v-model="year"
            class="rounded-xl border border-[#1f222e] bg-[#0a0a0c] px-3 py-1.5 font-mono text-xs text-zinc-200 focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
          >
            <option v-for="y in years" :key="y.value" :value="y.value" class="bg-[#12141a] text-zinc-200">
              {{ y.label }}
            </option>
          </select>
        </div>

        <!-- Monthly Expenses Envelopes -->
        <div class="rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 sm:p-6 shadow-xl">
          <div class="flex items-center justify-between pb-4 border-b border-[#1f222e]/60 mb-4">
            <h3 class="font-mono text-sm font-bold text-zinc-100 uppercase tracking-wide">
              {{ __('monthly_expenses') }}
            </h3>
          </div>
          <div>
            <DataListState
              :loading="transactionsLoading"
              :error="transactionsError"
              :rows="4"
              :is-empty="
                !transactionsLoading &&
                !transactionsError &&
                expenseBudgetItems.length === 0
              "
              :empty-title="__('no_data_found', { data: __('category') })"
            >
              <div class="space-y-4">
                <div
                  v-for="(exp, index) in expenseBudgetItems"
                  :key="index"
                  class="space-y-2 rounded-xl border border-transparent bg-[#121217]/50 p-3 transition-colors hover:border-[#1f222e]"
                >
                  <div class="flex items-center justify-between text-sm">
                    <div class="flex min-w-0 items-center gap-1.5">
                      <span class="truncate font-mono font-bold text-zinc-100">
                        {{ exp.category?.name || __('unknown') }}
                      </span>
                      <span
                        v-if="exp.actual_amount > exp.planned_amount"
                        class="inline-flex items-center gap-1 rounded-md border border-rose-500/30 bg-rose-500/15 px-1.5 py-0.5 font-mono text-[9px] font-bold text-rose-400"
                      >
                        <AlertTriangle class="size-2.5" />
                        {{ __('overspent') }}
                      </span>
                    </div>
                    <span
                      class="font-mono text-xs font-bold tabular-nums"
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
                  <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
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
                    />
                  </div>

                  <!-- Amounts detail -->
                  <div
                    class="flex justify-between font-mono text-[11px] text-zinc-400"
                  >
                    <span>
                      {{ formatAmount(exp.actual_amount ?? 0) }} /
                      {{ formatAmount(exp.planned_amount) }}
                    </span>
                    <span
                      :class="
                        exp.diff_amount < 0
                          ? 'font-bold text-rose-400'
                          : 'font-bold text-emerald-400'
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
                  class="flex flex-col gap-1 border-t border-[#1f222e]/60 pt-4 font-mono text-xs"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-zinc-400 font-semibold">{{ __('total') }}</span>
                    <div class="flex flex-col items-end space-y-0.5">
                      <span class="text-zinc-300">
                        {{ __('planned') }}:
                        {{ formatAmount(plannedExpenseTotal) }}
                      </span>
                      <span class="text-rose-400 font-bold">
                        {{ __('actual') }}:
                        {{ formatAmount(actualExpenseTotal) }}
                      </span>
                      <span
                        :class="
                          diffExpenseTotal < 0
                            ? 'text-rose-400 font-bold'
                            : 'text-zinc-400'
                        "
                      >
                        {{ __('diff') }}: {{ formatAmount(diffExpenseTotal) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </DataListState>
          </div>
        </div>

        <!-- Monthly Incomes Envelopes -->
        <div class="rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 sm:p-6 shadow-xl">
          <div class="flex items-center justify-between pb-4 border-b border-[#1f222e]/60 mb-4">
            <h3 class="font-mono text-sm font-bold text-zinc-100 uppercase tracking-wide">
              {{ __('monthly_incomes') }}
            </h3>
          </div>
          <div>
            <DataListState
              :loading="transactionsLoading"
              :error="transactionsError"
              :rows="4"
              :is-empty="
                !transactionsLoading &&
                !transactionsError &&
                incomeBudgetItems.length === 0
              "
              :empty-title="__('no_data_found', { data: __('category') })"
            >
              <div class="space-y-4">
                <div
                  v-for="(inc, index) in incomeBudgetItems"
                  :key="index"
                  class="space-y-2 rounded-xl border border-transparent bg-[#121217]/50 p-3 transition-colors hover:border-[#1f222e]"
                >
                  <div class="flex items-center justify-between text-sm">
                    <div class="flex min-w-0 items-center gap-1.5">
                      <span class="truncate font-mono font-bold text-zinc-100">
                        {{ inc.category?.name || __('unknown') }}
                      </span>
                    </div>
                    <span
                      class="font-mono text-xs font-bold text-emerald-400 tabular-nums"
                    >
                      {{
                        getProgressPercent(
                          inc.planned_amount,
                          inc.actual_amount ?? 0,
                        )
                      }}% {{ __('received') }}
                    </span>
                  </div>

                  <!-- Progress Bar -->
                  <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
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
                    />
                  </div>

                  <!-- Amounts detail -->
                  <div
                    class="flex justify-between font-mono text-[11px] text-zinc-400"
                  >
                    <span>
                      {{ formatAmount(inc.actual_amount ?? 0) }} /
                      {{ formatAmount(inc.planned_amount) }}
                    </span>
                    <span
                      :class="
                        inc.diff_amount < 0
                          ? 'text-rose-400 font-bold'
                          : 'text-emerald-400 font-bold'
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
                  class="flex flex-col gap-1 border-t border-[#1f222e]/60 pt-4 font-mono text-xs"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-zinc-400 font-semibold">{{ __('total') }}</span>
                    <div class="flex flex-col items-end space-y-0.5">
                      <span class="text-zinc-300">
                        {{ __('planned') }}:
                        {{ formatAmount(plannedIncomeTotal) }}
                      </span>
                      <span class="text-emerald-400 font-bold">
                        {{ __('actual') }}:
                        {{ formatAmount(actualIncomeTotal) }}
                      </span>
                      <span
                        :class="
                          diffIncomeTotal < 0
                            ? 'text-rose-400 font-bold'
                            : 'text-zinc-400'
                        "
                      >
                        {{ __('diff') }}: {{ formatAmount(diffIncomeTotal) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </DataListState>
          </div>
        </div>
      </div>
    </div>
  </AppContent>

  <div
    class="fixed right-4 bottom-[calc(var(--bottom-nav-height)+0.75rem)] z-fab flex gap-2 md:right-8 md:bottom-8"
  >
    <button
      v-if="!budget.is_active"
      type="button"
      class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/30 bg-[#121217] px-4 py-3 font-mono text-xs font-bold text-emerald-400 shadow-xl hover:bg-emerald-500/10 transition-all active:scale-95"
      @click="setActive"
    >
      <CheckCircle2 class="size-4" />
      <span>{{ __('set_as_active') }}</span>
    </button>
    <Link
      :href="budgetEdit.url({ budget: budget })"
      class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500 px-5 py-3 font-mono text-xs font-bold text-[#0a0a0c] shadow-[0_0_15px_rgba(16,185,129,0.35)] hover:bg-emerald-400 transition-all active:scale-95"
    >
      <SquarePen class="size-4" />
      <span>{{ __('edit') }}</span>
    </Link>
  </div>
</template>
