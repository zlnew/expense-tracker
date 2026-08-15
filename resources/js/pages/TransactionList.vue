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
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('transactions')"
          :description="__('transaction_list_description')"
          class="mb-0"
        />
        <!-- Visible 3-action grid: single / bulk / transfer (mobile + desktop) -->
        <div class="grid w-full grid-cols-3 gap-2 sm:w-auto">
          <Button class="min-w-0 px-2" @click="openCreateDialog">
            <Plus class="size-4 shrink-0" />
            <span class="min-w-0 truncate">{{ __('single_transaction') }}</span>
          </Button>
          <Button
            variant="outline"
            class="min-w-0 px-2"
            @click="openBulkCreateDialog"
          >
            <ListPlus class="size-4 shrink-0" />
            <span class="min-w-0 truncate">{{
              __('multiple_transactions')
            }}</span>
          </Button>
          <Button
            variant="outline"
            class="min-w-0 px-2"
            @click="openTransferDialog"
          >
            <ArrowRightLeft class="size-4 shrink-0" />
            <span class="min-w-0 truncate">{{ __('transfer') }}</span>
          </Button>
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
            class="h-10 w-10 shrink-0 lg:hidden"
            :aria-label="__('filter_transactions')"
            @click="filterSheetOpen = true"
          >
            <Filter class="size-4" />
            <Badge
              v-if="activeCount > 0"
              variant="secondary"
              class="absolute -top-1 -right-1 size-4 p-0 text-[9px]"
            >
              {{ activeCount }}
            </Badge>
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="h-10 w-10 shrink-0"
            :aria-label="__('export_transactions')"
            :title="__('export_transactions')"
            @click="exportCsv"
          >
            <Download class="size-4" />
          </Button>
        </div>

        <!-- Segmented [ Transactions | Recurring ] — visible at every
             breakpoint; the old control was mobile-only. -->
        <TransactionTabs class="md:hidden" viewMode="transactions" />

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

        <!-- ResponsiveTable: mobile cards + desktop table -->
        <ResponsiveTable
          :columns="[
            { header: '#', cell: (row, i) => i + 1, cellClass: 'w-[60px]' },
            {
              header: __('date'),
              cell: (t) => formatDate(t.date),
              cellClass: 'w-[120px] font-medium',
            },
            { header: __('category'), cell: (t) => t.category?.name ?? '-' },
            { header: __('balance'), cell: (t) => t.balance?.name ?? '-' },
            { header: __('description'), cell: (t) => t.description || '-' },
            {
              header: __('amount'),
              cell: (t) =>
                t.type === 'income'
                  ? '+' + formatAmount(t.amount)
                  : '-' + formatAmount(t.amount),
              cellClass: 'text-right font-medium',
            },
            {
              header: __('actions'),
              cell: () => '',
              cellClass: 'w-[100px] text-right',
            },
          ]"
          :rows="transactions.data"
        >
          <!-- Mobile card -->
          <template #card="{ row: t }">
            <div class="mb-3 flex items-center justify-between gap-2">
              <span
                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
              >
                {{ formatDate(t.date) }}
              </span>
              <span
                class="rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                :class="
                  t.type === 'income'
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                "
              >
                {{ __(t.type) }}
              </span>
            </div>
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-start gap-3">
                <div
                  class="flex size-10 shrink-0 items-center justify-center rounded-full"
                  :class="
                    t.type === 'income'
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                  "
                >
                  <component
                    :is="t.type === 'income' ? TrendingUp : TrendingDown"
                    class="size-5"
                  />
                </div>
                <div class="min-w-0">
                  <h3 class="truncate text-base font-bold">
                    {{ t.category?.name }}
                  </h3>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ t.balance?.name }}
                  </p>
                  <p
                    v-if="t.description"
                    class="mt-1 truncate text-sm text-muted-foreground"
                  >
                    {{ t.description }}
                  </p>
                </div>
              </div>
              <div class="shrink-0 text-right">
                <p
                  class="text-lg font-bold"
                  :class="
                    t.type === 'income'
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-red-600 dark:text-red-400'
                  "
                >
                  {{ t.type === 'income' ? '+' : '-'
                  }}{{ formatAmount(t.amount) }}
                </p>
                <div class="mt-2 flex items-center justify-end gap-1">
                  <RowActions :actions="rowActions(t)" collapse-below="md" />
                </div>
              </div>
            </div>
          </template>

          <!-- Desktop cells -->
          <template #cell-2="{ row: t }">
            <div class="flex items-center gap-2">
              <span class="text-muted-foreground">{{ __(t.type) }}</span>
              <span class="font-medium">{{ t.category?.name }}</span>
            </div>
          </template>
          <template #cell-4="{ row: t }">
            <div class="max-w-md truncate wrap-anywhere">
              {{ t.description || '-' }}
            </div>
          </template>
          <template #cell-5="{ row: t }">
            <span
              :class="
                t.type === 'income'
                  ? 'text-green-600 dark:text-green-400'
                  : 'text-red-600 dark:text-red-400'
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
