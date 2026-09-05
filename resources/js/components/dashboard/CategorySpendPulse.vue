<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Donut } from '@unovis/ts'
import { VisSingleContainer, VisDonut } from '@unovis/vue'
import { PieChart, TrendingDown, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'
import ChartEmpty from '@/components/ChartEmpty.vue'
import { ChartContainer, ChartTooltip } from '@/components/ui/chart'
import ChartTooltipContent from '@/components/ui/chart/ChartTooltipContent.vue'
import { componentToString } from '@/components/ui/chart/utils'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as budgetIndex } from '@/routes/budgets'
import type { ExpenseBreakdown } from '@/types'

const props = defineProps<{
  expenseBreakdown?: ExpenseBreakdown[]
  hasActiveBudget?: boolean
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { masked } = useMasking()

const BREAKDOWN_TOP = 6

const breakdownItems = computed<ExpenseBreakdown[]>(() => {
  const data = props.expenseBreakdown ?? []

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
      category: __('others') || 'Others',
      amount: restAmount,
      percentage: total > 0 ? Math.round((restAmount / total) * 100) : 0,
    },
  ]
})

const totalExpense = computed(() => {
  return breakdownItems.value.reduce((sum, item) => sum + item.amount, 0)
})

const CATEGORY_PALETTE = [
  '#10b981', // emerald
  '#8b5cf6', // violet
  '#f59e0b', // amber
  '#f43f5e', // rose
  '#06b6d4', // cyan
  '#3b82f6', // blue
  '#ec4899', // pink
  '#14b8a6', // teal
] as const

const getCategoryColor = (index: number) => {
  return CATEGORY_PALETTE[index % CATEGORY_PALETTE.length]
}

const donutColor = (_d: ExpenseBreakdown, i: number) => {
  return getCategoryColor(i)
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
</script>

<template>
  <div
    class="rounded-none border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
  >
    <!-- Card Header -->
    <div
      class="mb-4 flex items-center justify-between border-b border-border pb-4"
    >
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-none border border-purple-500/30 bg-purple-500/10 text-purple-500 dark:text-purple-400"
          >
            <PieChart class="size-4" />
          </span>
          <h2
            class="font-mono text-sm font-bold tracking-wide text-foreground uppercase"
          >
            {{ __('expense_breakdown') }}
          </h2>
        </div>
        <p class="font-mono text-[11px] text-muted-foreground">
          {{ __('expense_breakdown_description') }}
        </p>
      </div>
    </div>

    <!-- Content -->
    <div>
      <div
        v-if="expenseBreakdown === undefined"
        class="h-[220px] w-full animate-pulse rounded-none border border-border bg-secondary/50"
      />

      <ChartEmpty
        v-else-if="expenseBreakdown.length === 0"
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
          <button
            type="button"
            class="mt-2 inline-flex items-center gap-1.5 rounded-none border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 font-mono text-xs font-semibold text-emerald-500 transition-all hover:bg-emerald-500/20 dark:text-emerald-400"
          >
            {{ __('create_budget') }}
            <ChevronRight class="size-3.5" />
          </button>
        </Link>
      </ChartEmpty>

      <div v-else class="flex flex-col gap-6 md:flex-row md:items-center">
        <!-- Visual Donut Chart with Center Amount -->
        <div
          role="img"
          :aria-label="__('breakdown_chart_aria') || 'Category Breakdown Chart'"
          class="relative mx-auto size-[180px] shrink-0"
        >
          <ChartContainer :config="donutConfig" class="h-full w-full">
            <VisSingleContainer :data="breakdownItems" class="h-full w-full">
              <VisDonut
                :value="(d: ExpenseBreakdown) => d.amount"
                :name="(d: ExpenseBreakdown) => d.category"
                :arcWidth="18"
                :color="donutColor"
              />
              <ChartTooltip :triggers="donutTriggers" />
            </VisSingleContainer>
          </ChartContainer>

          <!-- Center Metric Overlay -->
          <div
            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
          >
            <span
              class="font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
              >Total</span
            >
            <span
              class="font-mono text-xs font-extrabold text-foreground tabular-nums"
            >
              {{ masked ? '••••' : formatAmount(totalExpense) }}
            </span>
          </div>
        </div>

        <!-- Ranked Category Chips / List -->
        <div class="flex-1 space-y-1.5">
          <div
            v-for="(item, i) in breakdownItems"
            :key="item.category"
            class="flex items-center justify-between rounded-none border border-border bg-secondary/40 px-3 py-2 text-xs transition-colors hover:bg-secondary"
          >
            <div class="flex items-center gap-2 truncate pr-2">
              <span
                class="size-2 shrink-0 rounded-none"
                :style="{ backgroundColor: getCategoryColor(i) }"
              />
              <span class="truncate font-medium text-foreground">
                {{ item.category }}
              </span>
            </div>

            <div class="flex shrink-0 items-center gap-2.5">
              <span
                class="font-mono text-[11px] font-bold text-muted-foreground tabular-nums"
              >
                {{ item.percentage }}%
              </span>
              <span
                class="font-mono text-xs font-bold text-foreground tabular-nums"
              >
                {{ masked ? '••••' : formatAmount(item.amount) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
