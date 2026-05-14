<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { ChevronDown, Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import TransactionBulkCreateDialog from '@/components/dialogs/TransactionBulkCreateDialog.vue'
import TransactionCreateDialog from '@/components/dialogs/TransactionCreateDialog.vue'
import TransactionDeleteDialog from '@/components/dialogs/TransactionDeleteDialog.vue'
import TransactionUpdateDialog from '@/components/dialogs/TransactionUpdateDialog.vue'
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { useParam } from '@/composables/useParam'
import { index as transactionIndex } from '@/routes/transactions'
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
const { formatNumber } = useNumber()
const param = useParam()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('transactions'),
    },
  ],
})

const createDialogOpen = ref(false)
const updateDialogOpen = ref(false)
const bulkCreateDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const targetData = ref<Transaction | null>(null)

const search = ref(param.get('search') || '')
const balanceFilter = ref(param.get('balance') || 'all')
const categoryFilter = ref(param.get('category') || 'all')
const dateFromFilter = ref(param.get('dateFrom') || '')
const dateToFilter = ref(param.get('dateTo') || '')

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

watch(
  [search, balanceFilter, categoryFilter, dateFromFilter, dateToFilter],
  () => {
    fetchData()
  },
)

const fetchData = useDebounceFn(() => {
  const params: Record<string, any> = {}

  params.search = search.value

  if (balanceFilter.value !== 'all') {
    params.balance = balanceFilter.value
  }

  if (categoryFilter.value !== 'all') {
    params.category = categoryFilter.value
  }

  if (dateFromFilter.value) {
    params.dateFrom = dateFromFilter.value
  }

  if (dateToFilter.value) {
    params.dateTo = dateToFilter.value
  }

  router.get(transactionIndex.url(), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const openCreateDialog = () => {
  createDialogOpen.value = true
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
        <div class="flex items-center gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button>
                <Plus class="mr-2 size-4" />
                {{ __('add_data', { data: __('transaction') }) }}
                <ChevronDown class="ml-2 size-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem @click="openCreateDialog">
                <Plus class="mr-2 size-4" />
                {{ __('single_transaction') }}
              </DropdownMenuItem>
              <DropdownMenuItem @click="openBulkCreateDialog">
                <Plus class="mr-2 size-4" />
                {{ __('multiple_transactions') }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div class="flex flex-col items-center gap-4 lg:flex-row lg:items-end">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <div class="relative w-full">
            <Search
              class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
              v-model="search"
              :placeholder="__('search_transactions_placeholder')"
              class="w-full bg-background pl-8"
            />
          </div>
        </div>

        <div class="grid w-full grid-cols-2 gap-2 lg:flex lg:w-auto">
          <div class="space-y-2">
            <Label>{{ __('balance') }}</Label>
            <Select v-model="balanceFilter">
              <SelectTrigger>
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
            <Select v-model="categoryFilter">
              <SelectTrigger>
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

          <div class="space-y-2">
            <Label>{{ __('date_from') }}</Label>
            <Input type="date" v-model="dateFromFilter" />
          </div>

          <div class="space-y-2">
            <Label>{{ __('date_to') }}</Label>
            <Input type="date" v-model="dateToFilter" />
          </div>
        </div>
      </div>

      <!-- Mobile View: Cards -->
      <div class="grid grid-cols-1 gap-4 md:hidden">
        <div
          v-if="transactions.data.length === 0"
          class="h-24 content-center text-center text-muted-foreground"
        >
          {{ __('no_data_found', { data: __('transactions') }) }}
        </div>
        <div
          v-for="t in transactions.data"
          :key="t.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-medium text-muted-foreground">{{
              formatDate(t.date)
            }}</span>
            <div
              class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
              :class="
                t.type === 'income'
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                  : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
              "
            >
              {{ __(t.type) }}
            </div>
          </div>
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold">{{ t.category?.name }}</h3>
              <p class="text-sm text-muted-foreground">{{ t.balance?.name }}</p>
              <p
                v-if="t.description"
                class="mt-1 max-w-[200px] truncate text-sm text-muted-foreground"
              >
                {{ t.description }}
              </p>
            </div>
            <div class="text-right">
              <p
                class="font-bold"
                :class="
                  t.type === 'income'
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-red-600 dark:text-red-400'
                "
              >
                Rp {{ t.type === 'income' ? '+' : '-' }}
                {{ formatNumber(t.amount) }}
              </p>
              <div class="mt-2 flex items-center justify-end gap-1">
                <Button
                  variant="ghost"
                  size="icon"
                  @click="openUpdateDialog(t)"
                >
                  <SquarePen class="size-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  class="text-destructive"
                  @click="openDeleteDialog(t)"
                >
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop View: Table -->
      <div
        class="hidden overflow-hidden rounded-md border bg-background md:block"
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-[60px]">#</TableHead>
              <TableHead class="w-[120px]">{{ __('date') }}</TableHead>
              <TableHead>{{ __('category') }}</TableHead>
              <TableHead>{{ __('balance') }}</TableHead>
              <TableHead>{{ __('description') }}</TableHead>
              <TableHead class="text-right">{{ __('amount') }}</TableHead>
              <TableHead class="w-[100px] text-right">
                {{ __('actions') }}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="transactions.data.length === 0">
              <TableCell colspan="7" class="h-24 text-center">
                {{ __('no_data_found', { data: __('transactions') }) }}
              </TableCell>
            </TableRow>
            <template v-else>
              <TableRow v-for="(t, index) in transactions.data" :key="t.id">
                <TableCell>
                  {{
                    (transactions.meta.current_page - 1) *
                      transactions.meta.per_page +
                    index +
                    1
                  }}.
                </TableCell>
                <TableCell class="font-medium">
                  {{ formatDate(t.date) }}
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">
                      {{ __(t.type) }}
                    </span>
                    <span class="font-medium">
                      {{ t.category?.name }}
                    </span>
                  </div>
                </TableCell>
                <TableCell>{{ t.balance?.name }}</TableCell>
                <TableCell class="text-sm text-muted-foreground">
                  <div class="max-w-md truncate wrap-anywhere">
                    {{ t.description || '-' }}
                  </div>
                </TableCell>
                <TableCell
                  class="text-right font-medium"
                  :class="
                    t.type === 'income'
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-red-600 dark:text-red-400'
                  "
                >
                  Rp
                  {{ t.type === 'income' ? '+' : '-' }}
                  {{ formatNumber(t.amount) }}
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="openUpdateDialog(t)"
                    >
                      <SquarePen class="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="text-destructive"
                      @click="openDeleteDialog(t)"
                    >
                      <Trash2 class="size-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </template>
          </TableBody>
        </Table>
      </div>

      <AppPagination
        v-if="transactions.meta"
        :meta="transactions.meta"
        :links="transactions.links"
      />
    </div>
  </AppContent>

  <TransactionCreateDialog
    v-model:open="createDialogOpen"
    :balances="balances"
    :budgets="budgets"
    :categories="categories"
    :primaryBalanceId="primaryBalanceId"
    :activeBudgetId="activeBudgetId"
  />
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
  <TransactionDeleteDialog
    v-model:open="deleteDialogOpen"
    :transaction="targetData"
  />
</template>
