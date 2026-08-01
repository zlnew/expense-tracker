<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import {
  ArrowRightLeft,
  ChevronDown,
  ListPlus,
  Plus,
  Search,
  SquarePen,
  Trash2,
  Filter,
  WalletIcon,
  ListTodoIcon,
  MinusIcon,
  TrendingDown,
  TrendingUp,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import TransactionBulkCreateDialog from '@/components/dialogs/TransactionBulkCreateDialog.vue'
import TransactionDeleteDialog from '@/components/dialogs/TransactionDeleteDialog.vue'
import TransactionTransferDialog from '@/components/dialogs/TransactionTransferDialog.vue'
import TransactionUpdateDialog from '@/components/dialogs/TransactionUpdateDialog.vue'
import Heading from '@/components/Heading.vue'
import ListSkeleton from '@/components/ListSkeleton.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import SheetDialogContent from '@/components/ui/dialog-sheet.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
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
const { formatAmount } = useNumber()
const param = useParam()

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
const addSheetOpen = ref(false)
const targetData = ref<Transaction | null>(null)

const search = ref(param.get('search') || '')
const balanceFilter = ref(param.get('balance') || 'all')
const categoryFilter = ref(param.get('category') || 'all')
const dateFromFilter = ref(param.get('dateFrom') || '')
const dateToFilter = ref(param.get('dateTo') || '')

const filterSheetOpen = ref(false)

// True while a debounced search/filter visit is in flight — shows the skeleton.
const loading = ref(false)

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
    onStart: () => {
      loading.value = true
    },
    onFinish: () => {
      loading.value = false
    },
  })
}, 300)

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
          <!-- Desktop: dropdown with all three actions -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button class="hidden sm:inline-flex">
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
                <ListPlus class="mr-2 size-4" />
                {{ __('multiple_transactions') }}
              </DropdownMenuItem>
              <DropdownMenuItem @click="openTransferDialog">
                <ArrowRightLeft class="mr-2 size-4" />
                {{ __('transfer') }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Mobile: compact add button opens a bottom-sheet action menu -->
          <Button
            variant="default"
            size="icon"
            class="h-11 w-11 sm:hidden"
            :aria-label="__('add_data', { data: __('transaction') })"
            @click="addSheetOpen = true"
          >
            <Plus class="size-5" />
          </Button>
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
          <Button
            variant="outline"
            size="icon"
            class="h-10 w-10 shrink-0 lg:hidden"
            :aria-label="__('filter_transactions')"
            @click="filterSheetOpen = true"
          >
            <Filter class="size-4" />
          </Button>
        </div>

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

      <!-- Mobile: filter bottom sheet (md-) / centered dialog (md+) -->
      <Dialog v-model:open="filterSheetOpen">
        <SheetDialogContent class="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
              <Filter class="size-4" />
              {{ __('filter_transactions') }}
            </DialogTitle>
          </DialogHeader>

          <div class="grid gap-4 py-2">
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

            <div class="grid gap-2">
              <Input
                type="date"
                v-model="dateFromFilter"
                :aria-label="__('date_from')"
              />
              <Input
                type="date"
                v-model="dateToFilter"
                :aria-label="__('date_to')"
              />
            </div>
          </div>

          <DialogFooter>
            <Button class="w-full" @click="filterSheetOpen = false">
              {{ __('save') }}
            </Button>
          </DialogFooter>
        </SheetDialogContent>
      </Dialog>

      <!-- Mobile: add-transaction action sheet (sm:hidden equivalent via content) -->
      <Dialog v-model:open="addSheetOpen">
        <SheetDialogContent class="sm:hidden">
          <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
              <Plus class="size-4" />
              {{ __('add_data', { data: __('transaction') }) }}
            </DialogTitle>
          </DialogHeader>

          <div class="space-y-2">
            <Button
              variant="outline"
              class="h-12 w-full justify-start gap-3 text-base"
              @click="openCreateDialog"
            >
              <Plus class="size-5" />
              {{ __('single_transaction') }}
            </Button>
            <Button
              variant="outline"
              class="h-12 w-full justify-start gap-3 text-base"
              @click="openBulkCreateDialog"
            >
              <ListPlus class="size-5" />
              {{ __('multiple_transactions') }}
            </Button>
            <Button
              variant="outline"
              class="h-12 w-full justify-start gap-3 text-base"
              @click="openTransferDialog"
            >
              <ArrowRightLeft class="size-5" />
              {{ __('transfer') }}
            </Button>
          </div>
        </SheetDialogContent>
      </Dialog>

      <!-- Loading skeleton while a search/filter visit is in flight -->
      <ListSkeleton v-if="loading" :rows="5" />

      <!-- Empty state -->
      <div
        v-if="!loading && transactions.data.length === 0"
        class="flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed bg-background/50 p-8 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <ArrowRightLeft class="size-8 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-semibold">
          {{ __('no_data_found', { data: __('transactions') }) }}
        </h3>
        <p class="mb-6 text-muted-foreground">
          {{ __('transaction_create_description') }}
        </p>
        <Button @click="openCreateDialog">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('transaction') }) }}
        </Button>
      </div>

      <template v-else>
        <!-- Mobile View: Cards -->
        <div class="grid grid-cols-1 gap-4 md:hidden">
          <div
            v-for="t in transactions.data"
            :key="t.id"
            class="rounded-lg border bg-background p-4 shadow-sm"
          >
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
                    :is="
                      t.type === 'income' ? TrendingUp : TrendingDown
                    "
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
                  {{ t.type === 'income' ? '+' : '-' }}{{ formatAmount(t.amount) }}
                </p>
                <div class="mt-2 flex items-center justify-end gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10"
                    @click="openUpdateDialog(t)"
                  >
                    <SquarePen class="size-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10 text-destructive"
                    @click="openDeleteDialog(t)"
                  >
                    <Trash2 class="size-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Desktop View: Table -->
      <div
        v-if="!loading"
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
                {{ t.type === 'income' ? '+' : '-' }}{{ formatAmount(t.amount) }}
              </TableCell>
              <TableCell class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <Button
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10"
                    @click="openUpdateDialog(t)"
                  >
                    <SquarePen class="size-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10 text-destructive"
                    @click="openDeleteDialog(t)"
                  >
                    <Trash2 class="size-4" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

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
