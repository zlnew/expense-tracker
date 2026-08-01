<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useHttp } from '@inertiajs/vue3'
import { CheckCircle2, CalendarDays, SquarePen } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AppContent from '@/components/AppContent.vue'
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
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import {
  index as budgetIndex,
  edit as budgetEdit,
  setActive as budgetSetActive,
} from '@/routes/budgets'
import type { Budget, Transaction } from '@/types'

const props = defineProps<{
  budget: Budget
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { formatDate } = useDate()

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

const transactionApi = useHttp({
  budget: props.budget.id,
  month: currentCycle.month,
  year: currentCycle.year.toString(),
})

const transactions = ref<Transaction[]>([])

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
    value: index + 1,
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

watch(
  () => [transactionApi.month, transactionApi.year],
  () => {
    fetchTransactions()
  },
)

onMounted(() => {
  fetchTransactions()
})

const fetchTransactions = async () => {
  try {
    const res = await transactionApi.get('/api/transactions')
    transactions.value = res as Transaction[]
  } catch (error) {
    const apiError = error as Error
    toast.error(apiError.message)
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
    <div class="space-y-6 px-4 pt-6 pb-22 md:px-8">
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

      <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="flex items-center justify-center gap-2 lg:col-span-2">
          <CalendarDays class="mr-1 text-muted-foreground" />
          <Select v-model="transactionApi.month">
            <SelectTrigger class="h-10 md:h-9">
              <SelectValue :placeholder="__('month')" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                {{ m.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <Select v-model="transactionApi.year">
            <SelectTrigger class="h-10 md:h-9">
              <SelectValue :placeholder="__('year')" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="y in years" :key="y.value" :value="y.value">
                {{ y.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{{ __('monthly_expenses') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <!-- Mobile view for expenses -->
            <div class="space-y-4 md:hidden">
              <div
                v-if="expenseBudgetItems.length === 0"
                class="py-8 text-center text-muted-foreground"
              >
                {{ __('no_data_found', { data: __('category') }) }}
              </div>
              <div
                v-for="(exp, index) in expenseBudgetItems"
                :key="index"
                class="border-b pb-4 last:border-0"
              >
                <div class="flex items-center justify-between font-bold">
                  <span>{{ exp.category?.name }}</span>
                  <span
                    :class="
                      exp.diff_amount < 0
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                    "
                  >
                    {{ formatAmount(exp.diff_amount) }}
                  </span>
                </div>
                <div
                  class="mt-1 flex items-center justify-between text-sm text-muted-foreground"
                >
                  <span
                    >{{ __('planned') }}: {{ formatAmount(exp.planned_amount) }}</span
                  >
                  <span
                    >{{ __('actual') }}: {{ formatAmount(exp.actual_amount) }}</span
                  >
                </div>
              </div>
              <div
                v-if="expenseBudgetItems.length > 0"
                class="flex flex-col gap-1 border-t pt-4 text-sm font-bold"
              >
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">{{ __('total') }}</span>
                  <div class="flex flex-col items-end">
                    <span>
                      {{ __('planned') }}: {{ formatAmount(plannedExpenseTotal) }}
                    </span>
                    <span>
                      {{ __('actual') }}: {{ formatAmount(actualExpenseTotal) }}
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

            <!-- Desktop View: Table -->
            <div class="hidden md:block">
              <Table>
                <TableHeader class="bg-accent">
                  <TableRow>
                    <TableHead>
                      {{ __('category') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('planned') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('actual') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('diff') }}
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow
                    v-for="(exp, index) in expenseBudgetItems"
                    :key="index"
                  >
                    <TableCell>{{ exp.category?.name }}</TableCell>
                    <TableCell class="text-end">
                      {{ formatAmount(exp.planned_amount) }}
                    </TableCell>
                    <TableCell class="text-end">
                      {{ formatAmount(exp.actual_amount) }}
                    </TableCell>
                    <TableCell class="text-end">
                      <span
                        :class="
                          exp.diff_amount < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ formatAmount(exp.diff_amount) }}
                      </span>
                    </TableCell>
                  </TableRow>
                </TableBody>
                <TableFooter>
                  <TableRow>
                    <TableCell>{{ __('total') }}</TableCell>
                    <TableCell class="text-right">
                      {{ formatAmount(plannedExpenseTotal) }}
                    </TableCell>
                    <TableCell class="text-right">
                      {{ formatAmount(actualExpenseTotal) }}
                    </TableCell>
                    <TableCell class="text-right">
                      <span
                        :class="
                          diffExpenseTotal < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ formatAmount(diffExpenseTotal) }}
                      </span>
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{{ __('monthly_incomes') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <!-- Mobile view for incomes -->
            <div class="space-y-4 md:hidden">
              <div
                v-if="incomeBudgetItems.length === 0"
                class="py-8 text-center text-muted-foreground"
              >
                {{ __('no_data_found', { data: __('category') }) }}
              </div>
              <div
                v-for="(inc, index) in incomeBudgetItems"
                :key="index"
                class="border-b pb-4 last:border-0"
              >
                <div class="flex items-center justify-between font-bold">
                  <span>{{ inc.category?.name }}</span>
                  <span
                    :class="
                      inc.diff_amount < 0
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                    "
                  >
                    {{ formatAmount(inc.diff_amount) }}
                  </span>
                </div>
                <div
                  class="mt-1 flex items-center justify-between text-sm text-muted-foreground"
                >
                  <span
                    >{{ __('planned') }}: {{ formatAmount(inc.planned_amount) }}</span
                  >
                  <span
                    >{{ __('actual') }}: {{ formatAmount(inc.actual_amount) }}</span
                  >
                </div>
              </div>
              <div
                v-if="incomeBudgetItems.length > 0"
                class="flex flex-col gap-1 border-t pt-4 text-sm font-bold"
              >
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">{{ __('total') }}</span>
                  <div class="flex flex-col items-end">
                    <span>
                      {{ __('planned') }}: {{ formatAmount(plannedIncomeTotal) }}
                    </span>
                    <span>
                      {{ __('actual') }}: {{ formatAmount(actualIncomeTotal) }}
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

            <!-- Desktop View: Table -->
            <div class="hidden md:block">
              <Table>
                <TableHeader class="bg-accent">
                  <TableRow>
                    <TableHead>
                      {{ __('category') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('planned') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('actual') }}
                    </TableHead>
                    <TableHead class="w-[300px] text-end">
                      {{ __('diff') }}
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow
                    v-for="(inc, index) in incomeBudgetItems"
                    :key="index"
                  >
                    <TableCell>{{ inc.category?.name }}</TableCell>
                    <TableCell class="text-end">
                      {{ formatAmount(inc.planned_amount) }}
                    </TableCell>
                    <TableCell class="text-end">
                      {{ formatAmount(inc.actual_amount) }}
                    </TableCell>
                    <TableCell class="text-end">
                      <span
                        :class="
                          inc.diff_amount < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ formatAmount(inc.diff_amount) }}
                      </span>
                    </TableCell>
                  </TableRow>
                </TableBody>
                <TableFooter>
                  <TableRow>
                    <TableCell>{{ __('total') }}</TableCell>
                    <TableCell class="text-right">
                      {{ formatAmount(plannedIncomeTotal) }}
                    </TableCell>
                    <TableCell class="text-right">
                      {{ formatAmount(actualIncomeTotal) }}
                    </TableCell>
                    <TableCell class="text-right">
                      <span
                        :class="
                          diffIncomeTotal < 0
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                        "
                      >
                        {{ formatAmount(diffIncomeTotal) }}
                      </span>
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppContent>

  <div
    class="fixed right-4 bottom-[calc(4rem+env(safe-area-inset-bottom)+0.75rem)] z-50 flex gap-2 md:right-8 md:bottom-8"
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
