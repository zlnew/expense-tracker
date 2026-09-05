<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3'
import {
  ArrowRightLeft,
  Download,
  Filter,
  ListPlus,
  ListTodoIcon,
  MinusIcon,
  Plus,
  SquarePen,
  Trash2,
  TrendingDown,
  TrendingUp,
  WalletIcon,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import TransactionBulkCreateDialog from '@/components/dialogs/TransactionBulkCreateDialog.vue'
import TransactionDeleteDialog from '@/components/dialogs/TransactionDeleteDialog.vue'
import TransactionTransferDialog from '@/components/dialogs/TransactionTransferDialog.vue'
import TransactionUpdateDialog from '@/components/dialogs/TransactionUpdateDialog.vue'
import FilterSheet from '@/components/FilterSheet.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import TransactionTabs from '@/components/TransactionTabs.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useDate } from '@/composables/useDate'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { toQuery } from '@/lib/utils'
import {
  exportMethod as transactionExport,
  index as transactionIndex,
} from '@/routes/transactions'
import type { Balance, Budget, Category, Paginate, Transaction } from '@/types'

const props = defineProps<{
  transactions: Paginate<Transaction>
  balances: Balance[]
  budgets: Budget[]
  categories: Category[]
  primaryBalanceId?: number
  activeBudgetId?: number
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('transactions'),
    },
  ],
})

const updateDialogOpen = ref(false)
const bulkCreateDialogOpen = ref(false)
const transferDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const targetData = ref<Transaction | null>(null)

const loading = ref(false)

// Single source of filter truth: reactive refs synced to the URL query
// string (reload restores, back/forward round-trips, debounced visits).
const {
  search,
  balance: balanceFilter,
  category: categoryFilter,
  dateFrom: dateFromFilter,
  dateTo: dateToFilter,
  activeCount,
  buildParams,
  apply,
} = useFilters({
  url: transactionIndex.url(),
  defaults: { balance: 'all', category: 'all' },
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
})

const filterSheetOpen = ref(false)

const groupedCategories = computed(() => {
  const items = props.categories

  const groups: Record<string, Category[]> = {
    income: [],
    expense: [],
  }

  items.forEach((c) => {
    if (c.type === 'income') {
      groups.income.push(c)
    } else if (c.type === 'expense') {
      groups.expense.push(c)
    }
  })

  return groups
})

const currentCategory = computed(() =>
  props.categories.find((c) => c.id.toString() === categoryFilter.value),
)

const groupedTransactions = computed(() => {
  const list = props.transactions.data ?? []
  const todayStr = new Date().toISOString().slice(0, 10)
  const yesterday = new Date()
  yesterday.setDate(yesterday.getDate() - 1)
  const yesterdayStr = yesterday.toISOString().slice(0, 10)

  const groupMap = new Map<string, Transaction[]>()

  list.forEach((t) => {
    const rawDate = t.date ? t.date.slice(0, 10) : 'unknown'
    if (!groupMap.has(rawDate)) {
      groupMap.set(rawDate, [])
    }
    groupMap.get(rawDate)!.push(t)
  })

  const result: Array<{
    dateKey: string
    dateLabel: string
    items: Transaction[]
    totalIncome: number
    totalExpense: number
  }> = []

  groupMap.forEach((items, rawDate) => {
    let label = formatDate(rawDate, 'DD MMMM YYYY')
    if (rawDate === todayStr) {
      label = 'Hari Ini'
    } else if (rawDate === yesterdayStr) {
      label = 'Kemarin'
    }

    let inc = 0
    let exp = 0
    items.forEach((item) => {
      if (item.type === 'income') inc += item.amount
      else exp += item.amount
    })

    result.push({
      dateKey: rawDate,
      dateLabel: label,
      items,
      totalIncome: inc,
      totalExpense: exp,
    })
  })

  return result
})

// Download the current filter view as CSV (full download, not Inertia visit).
const exportCsv = () => {
  const url = new URL(transactionExport.url(), window.location.origin)
  url.search = toQuery(buildParams())

  window.location.href = url.toString()
}

// The global quick-add FAB (mobile bottom nav) and this page's "Single
// Transaction" action both open the shared create dialog mounted in the
// layout via this custom event — works from ANY page.
const openCreateDialog = () => {
  window.dispatchEvent(new CustomEvent('open:transaction-create'))
}

const openUpdateDialog = (data: Transaction) => {
  targetData.value = data
  updateDialogOpen.value = true
}

const openDeleteDialog = (data: Transaction) => {
  targetData.value = data
  deleteDialogOpen.value = true
}

const openBulkCreateDialog = () => {
  bulkCreateDialogOpen.value = true
}

const openTransferDialog = () => {
  transferDialogOpen.value = true
}

const rowActions = (t: Transaction) => [
  {
    label: __('edit'),
    icon: SquarePen,
    onClick: () => openUpdateDialog(t),
  },
  {
    label: __('delete'),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(t),
  },
]
</script>

<template>
  <Head :title="__('transactions')" />

  <AppContent>
    <div class="page-container space-y-5">
      <!-- Command Bar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2.5">
          <h1 class="font-mono text-base font-bold text-zinc-100 uppercase tracking-wide">
            {{ __('transactions') }}
          </h1>
          <span class="stat-chip text-zinc-400 font-semibold">
            {{ transactions.meta?.total ?? transactions.data.length }} total
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-3 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_12px_rgba(16,185,129,0.3)] active:scale-95"
            @click="openCreateDialog"
          >
            <Plus class="size-3.5 stroke-[2.5]" />
            <span>Catat Cepat</span>
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border border-[#1f222e] bg-[#12141a] px-3 py-2 font-mono text-xs font-semibold text-zinc-300 hover:text-zinc-100 hover:border-zinc-700 transition-all active:scale-95"
            @click="openTransferDialog"
          >
            <ArrowRightLeft class="size-3.5 text-zinc-400" />
            <span class="hidden sm:inline">Pindah Saldo</span>
          </button>
          <button
            type="button"
            class="size-9 inline-flex items-center justify-center rounded-xl border border-[#1f222e] bg-[#12141a] text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 transition-all active:scale-95"
            title="Catat Banyak"
            @click="openBulkCreateDialog"
          >
            <ListPlus class="size-4" />
          </button>
          <button
            type="button"
            class="size-9 inline-flex items-center justify-center rounded-xl border border-[#1f222e] bg-[#12141a] text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 transition-all active:scale-95"
            title="Export CSV"
            @click="exportCsv"
          >
            <Download class="size-4" />
          </button>
        </div>
      </div>

      <div class="flex flex-col items-center gap-4 lg:flex-row lg:items-end">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <div class="w-full">
            <SearchInput
              v-model="search"
              :placeholder="__('search_transactions_placeholder')"
            />
          </div>
          <Button
            variant="outline"
            size="icon"
            class="h-10 w-10 shrink-0 border-[#1f222e] bg-[#121217] text-zinc-300 hover:bg-[#181820] hover:text-zinc-100 relative lg:hidden"
            :aria-label="__('filter_transactions')"
            @click="filterSheetOpen = true"
          >
            <Filter class="size-4" />
            <Badge
              v-if="activeCount > 0"
              variant="secondary"
              class="absolute -top-1 -right-1 size-4 p-0 text-[9px] bg-emerald-500 text-[#0a0a0c] font-mono font-bold"
            >
              {{ activeCount }}
            </Badge>
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="h-10 w-10 shrink-0 border-[#1f222e] bg-[#121217] text-zinc-300 hover:bg-[#181820] hover:text-zinc-100"
            :aria-label="__('export_transactions')"
            :title="__('export_transactions')"
            @click="exportCsv"
          >
            <Download class="size-4" />
          </Button>
        </div>

        <!-- Segmented [ Transactions | Recurring ] — visible at every
             breakpoint; the old control was mobile-only. -->
        <TransactionTabs class="lg:hidden" viewMode="transactions" />

        <!-- Desktop: inline filter controls (lg+) -->
        <div class="hidden w-full gap-4 lg:flex lg:w-auto">
          <div class="space-y-2">
            <Select v-model="balanceFilter">
              <SelectTrigger>
                <WalletIcon />
                <SelectValue :placeholder="__('balance')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">
                  {{ __('all_data', { data: __('balances') }) }}
                </SelectItem>
                <SelectItem
                  v-for="b in balances"
                  :key="b.id"
                  :value="b.id.toString()"
                >
                  {{ b.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Select v-model="categoryFilter">
              <SelectTrigger>
                <ListTodoIcon />
                <span
                  v-if="currentCategory?.type"
                  class="text-muted-foreground"
                >
                  {{ __(currentCategory.type) }}
                </span>
                <SelectValue :placeholder="__('category')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">
                  {{ __('all_data', { data: __('categories') }) }}
                </SelectItem>
                <SelectGroup v-if="groupedCategories.expense.length > 0">
                  <SelectLabel>{{ __('expense') }}</SelectLabel>
                  <SelectItem
                    v-for="c in groupedCategories.expense"
                    :key="c.id"
                    :value="c.id.toString()"
                  >
                    {{ c.name }}
                  </SelectItem>
                </SelectGroup>
                <SelectGroup v-if="groupedCategories.income.length > 0">
                  <SelectLabel>{{ __('income') }}</SelectLabel>
                  <SelectItem
                    v-for="c in groupedCategories.income"
                    :key="c.id"
                    :value="c.id.toString()"
                  >
                    {{ c.name }}
                  </SelectItem>
                </SelectGroup>
                <div
                  v-if="
                    groupedCategories.income.length === 0 &&
                    groupedCategories.expense.length === 0
                  "
                  class="p-4 text-center text-sm text-muted-foreground"
                >
                  {{ __('no_data_found', { data: __('category') }) }}
                </div>
              </SelectContent>
            </Select>
          </div>

          <div class="flex items-center gap-2">
            <Input type="date" v-model="dateFromFilter" />
            <MinusIcon />
            <Input type="date" v-model="dateToFilter" />
          </div>
        </div>
      </div>

      <!-- Mobile: filter bottom sheet with real Reset/Apply -->
      <FilterSheet
        v-model:open="filterSheetOpen"
        :model="{
          search: search,
          balance: balanceFilter,
          category: categoryFilter,
          dateFrom: dateFromFilter,
          dateTo: dateToFilter,
        }"
        :defaults="{
          search: '',
          balance: 'all',
          category: 'all',
          dateFrom: '',
          dateTo: '',
        }"
        :active-count="activeCount"
        :trigger-label="__('filter_transactions')"
        @apply="apply"
      >
        <template #default="{ draft }">
          <div class="grid gap-4 py-2">
            <div class="space-y-2">
              <Label>{{ __('balance') }}</Label>
              <Select v-model="draft.balance">
                <SelectTrigger>
                  <WalletIcon />
                  <SelectValue :placeholder="__('balance')" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">
                    {{ __('all_data', { data: __('balances') }) }}
                  </SelectItem>
                  <SelectItem
                    v-for="b in balances"
                    :key="b.id"
                    :value="b.id.toString()"
                  >
                    {{ b.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label>{{ __('category') }}</Label>
              <Select v-model="draft.category">
                <SelectTrigger>
                  <ListTodoIcon />
                  <SelectValue :placeholder="__('category')" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">
                    {{ __('all_data', { data: __('categories') }) }}
                  </SelectItem>
                  <SelectGroup v-if="groupedCategories.expense.length > 0">
                    <SelectLabel>{{ __('expense') }}</SelectLabel>
                    <SelectItem
                      v-for="c in groupedCategories.expense"
                      :key="c.id"
                      :value="c.id.toString()"
                    >
                      {{ c.name }}
                    </SelectItem>
                  </SelectGroup>
                  <SelectGroup v-if="groupedCategories.income.length > 0">
                    <SelectLabel>{{ __('income') }}</SelectLabel>
                    <SelectItem
                      v-for="c in groupedCategories.income"
                      :key="c.id"
                      :value="c.id.toString()"
                    >
                      {{ c.name }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-2">
              <Label>{{ __('date_from') }}</Label>
              <Input type="date" v-model="draft.dateFrom" />
              <Label>{{ __('date_to') }}</Label>
              <Input type="date" v-model="draft.dateTo" />
            </div>
          </div>
        </template>
      </FilterSheet>

      <!-- Skeleton / empty / table chain — one owner, no double render -->
      <DataListState
        :loading="loading"
        :is-empty="!loading && transactions.data.length === 0"
        :rows="5"
        :empty-icon="ArrowRightLeft"
        :empty-title="__('no_data_found', { data: __('transactions') })"
        :empty-description="__('transaction_create_description')"
      >
        <template #empty>
          <Button @click="openCreateDialog">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('transaction') }) }}
          </Button>
        </template>

        <!-- Mobile Date Grouped Ledger Stream (block md:hidden) -->
        <div class="space-y-4 md:hidden">
          <div
            v-for="group in groupedTransactions"
            :key="group.dateKey"
            class="space-y-1.5"
          >
            <!-- Date Group Header -->
            <div class="flex items-center justify-between px-1 font-mono text-xs">
              <span class="font-bold text-zinc-400 uppercase tracking-wider text-[11px]">
                {{ group.dateLabel }}
              </span>
              <div class="flex items-center gap-2 text-[11px] tabular-nums">
                <span v-if="group.totalIncome > 0" class="text-emerald-400 font-bold">
                  +{{ formatAmount(group.totalIncome) }}
                </span>
                <span v-if="group.totalExpense > 0" class="text-rose-400 font-bold">
                  -{{ formatAmount(group.totalExpense) }}
                </span>
              </div>
            </div>

            <!-- Day Cards Stack -->
            <div class="overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] divide-y divide-[#1f222e]/60 shadow-lg">
              <div
                v-for="t in group.items"
                :key="t.id"
                class="flex items-center justify-between p-3.5 hover:bg-[#12141a]/60 active:bg-[#181b24] transition-colors cursor-pointer"
                @click="openUpdateDialog(t)"
              >
                <div class="flex items-center gap-3 min-w-0 pr-3">
                  <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl border"
                    :class="
                      t.type === 'income'
                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                        : 'border-rose-500/30 bg-rose-500/10 text-rose-400'
                    "
                  >
                    <component
                      :is="t.type === 'income' ? TrendingUp : TrendingDown"
                      class="size-4"
                    />
                  </div>
                  <div class="min-w-0">
                    <h3 class="truncate font-mono text-xs font-bold text-zinc-100">
                      {{ t.description || t.category?.name || __('transaction') }}
                    </h3>
                    <p class="flex items-center gap-1.5 font-mono text-[11px] text-zinc-500 mt-0.5">
                      <span class="truncate text-zinc-400">{{ t.category?.name || __('unknown') }}</span>
                      <span v-if="t.balance?.name" class="text-zinc-600">•</span>
                      <span v-if="t.balance?.name" class="truncate text-zinc-400">{{ t.balance?.name }}</span>
                    </p>
                  </div>
                </div>

                <div class="shrink-0 text-right">
                  <p
                    class="font-mono text-xs font-bold tabular-nums"
                    :class="t.type === 'income' ? 'text-emerald-400' : 'text-zinc-200'"
                  >
                    {{ t.type === 'income' ? '+' : '-' }}{{ formatAmount(t.amount) }}
                  </p>
                  <p class="font-mono text-[10px] text-zinc-500 mt-0.5">
                    {{ formatDate(t.date, 'HH:mm') }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop Table (hidden md:block) -->
        <div class="hidden md:block">
          <ResponsiveTable
            :columns="[
              { header: '#', cell: (row, i) => i + 1, cellClass: 'w-[60px]' },
              {
                header: __('date'),
                cell: (t) => formatDate(t.date),
                cellClass: 'w-[120px] font-medium font-mono text-xs text-zinc-400',
              },
              { header: __('category'), cell: (t) => t.category?.name ?? '-', cellClass: 'font-mono text-xs text-zinc-200' },
              { header: __('balance'), cell: (t) => t.balance?.name ?? '-', cellClass: 'font-mono text-xs text-zinc-400' },
              { header: __('description'), cell: (t) => t.description || '-', cellClass: 'font-mono text-xs text-zinc-500' },
              {
                header: __('amount'),
                cell: (t) =>
                  (t.type === 'income' ? '+' : '-') + formatAmount(t.amount),
                cellClass: 'text-right font-mono text-xs font-bold tabular-nums',
              },
              {
                header: __('actions'),
                cell: () => '',
                cellClass: 'w-[100px] text-right',
              },
            ]"
            :rows="transactions.data"
          >
            <template #cell-5="{ row: t }">
              <span
                :class="
                  t.type === 'income'
                    ? 'text-emerald-400 font-mono font-bold'
                    : 'text-zinc-200 font-mono font-bold'
                "
              >
                {{ t.type === 'income' ? '+' : '-' }}{{ formatAmount(t.amount) }}
              </span>
            </template>
            <template #cell-6="{ row: t }">
              <div class="flex items-center justify-end gap-2">
                <RowActions :actions="rowActions(t)" collapse-below="md" />
              </div>
            </template>
          </ResponsiveTable>
        </div>
      </DataListState>

      <AppPagination
        v-if="!loading && transactions.meta"
        :meta="transactions.meta"
        :links="transactions.links"
      />
    </div>
  </AppContent>

  <TransactionUpdateDialog
    v-model:open="updateDialogOpen"
    :transaction="targetData"
    :balances="balances"
    :budgets="budgets"
    :categories="categories"
  />
  <TransactionBulkCreateDialog
    v-model:open="bulkCreateDialogOpen"
    :balances="balances"
    :budgets="budgets"
    :categories="categories"
    :primaryBalanceId="primaryBalanceId"
    :activeBudgetId="activeBudgetId"
  />
  <TransactionTransferDialog
    v-model:open="transferDialogOpen"
    :balances="balances"
  />
  <TransactionDeleteDialog
    v-model:open="deleteDialogOpen"
    :transaction="targetData"
  />
</template>
