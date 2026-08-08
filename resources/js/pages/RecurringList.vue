<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { Plus, Repeat, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import RecurringTransactionDeleteDialog from '@/components/dialogs/RecurringTransactionDeleteDialog.vue'
import RecurringTransactionFormDialog from '@/components/dialogs/RecurringTransactionFormDialog.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { useDate } from '@/composables/useDate'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { index as recurringIndex } from '@/routes/recurring-transactions'
import { update as updateRecurring } from '@/routes/recurring-transactions'
import type { Balance, Category, Paginate, RecurringTransaction } from '@/types'

defineProps<{
  recurrings: Paginate<RecurringTransaction>
  balances: Balance[]
  categories: Category[]
}>()

const { __ } = useLang()
const { formatDate } = useDate()
// The local Intl formatters bypassed and defeated the privacy mask; the
// shared composables honour it, so amounts/date now mask like every page.
const { formatAmount } = useNumber()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('transactions'),
    },
    {
      title: __('recurring_transactions'),
    },
  ],
})

const createDialogOpen = ref(false)
const updateDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const targetData = ref<RecurringTransaction | null>(null)

const loading = ref(false)

// URL-backed search — server-side via RecurringTransactionQuery.
const { search } = useFilters({
  url: recurringIndex.url(),
  defaults: {},
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
})

const openEditDialog = (data: RecurringTransaction) => {
  targetData.value = data
  updateDialogOpen.value = true
}

const openDeleteDialog = (data: RecurringTransaction) => {
  targetData.value = data
  deleteDialogOpen.value = true
}

const toggleActive = (data: RecurringTransaction) => {
  router.put(
    updateRecurring.url(data),
    {
      type: data.type,
      balance_id: data.balance_id,
      category_id: data.category_id,
      amount: data.amount,
      description: data.description,
      frequency: data.frequency,
      start_date: data.start_date,
      end_date: data.end_date,
      next_run_date: data.next_run_date,
      is_active: !data.is_active,
    },
    { preserveScroll: true },
  )
}

const fmtDate = (d: string | null) => (d ? formatDate(d, 'DD MMM YYYY') : '—')

const rowActions = (r: RecurringTransaction) => [
  {
    label: __('edit_data', { data: __('recurring_transaction') }),
    icon: SquarePen,
    onClick: () => openEditDialog(r),
  },
  {
    label: __('delete_data', { data: __('recurring_transaction') }),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(r),
  },
]

const columns = [
  {
    header: __('description'),
    cell: (r: RecurringTransaction) => r.description || '—',
    cellClass: 'font-medium',
  },
  {
    header: __('type'),
    cell: (r: RecurringTransaction) => r.type,
    cellClass: 'capitalize',
  },
  {
    header: __('frequency'),
    cell: (r: RecurringTransaction) => __(r.frequency),
    cellClass: 'capitalize',
  },
  {
    header: __('amount'),
    cell: (r: RecurringTransaction) => formatAmount(r.amount),
    headerClass: 'text-right',
    cellClass: 'text-right',
  },
  {
    header: __('category'),
    cell: (r: RecurringTransaction) => r.category?.name || '—',
    cellClass: 'text-muted-foreground',
  },
  {
    header: __('next_run_date'),
    cell: (r: RecurringTransaction) => fmtDate(r.next_run_date),
  },
  {
    header: __('status'),
    cell: () => '',
  },
  {
    header: __('actions'),
    cell: () => '',
    headerClass: 'text-right',
    cellClass: 'w-[110px] text-right',
  },
]
</script>

<template>
  <Head :title="__('recurring_transactions')" />

  <div>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('recurring_transactions')"
          :description="__('recurring_transactions_description')"
          class="mb-0"
        />
        <Button @click="createDialogOpen = true">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('recurring_transaction') }) }}
        </Button>
      </div>

      <div class="flex w-full items-center gap-2 lg:max-w-md">
        <SearchInput
          v-model="search"
          :placeholder="__('search_recurring_placeholder')"
        />
      </div>

      <!-- One owner: skeleton / empty / table, any viewport -->
      <DataListState
        :loading="loading"
        :is-empty="!loading && recurrings.data.length === 0"
        :rows="5"
        :empty-icon="Repeat"
        :empty-title="
          __('no_data_found', { data: __('recurring_transactions') })
        "
        :empty-description="__('recurring_transactions_description')"
      >
        <template #empty>
          <Button @click="createDialogOpen = true">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('recurring_transaction') }) }}
          </Button>
        </template>

        <ResponsiveTable :columns="columns" :rows="recurrings.data">
          <template #card="{ row: r }">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p
                  class="mb-1 text-[10px] font-bold tracking-wider uppercase"
                  :class="
                    r.type === 'income' ? 'text-emerald-600' : 'text-rose-600'
                  "
                >
                  {{ __(r.type) }} · {{ __(r.frequency) }}
                </p>
                <h3 class="text-lg font-bold">{{ r.description || '—' }}</h3>
                <p class="text-sm text-muted-foreground">
                  {{ formatAmount(r.amount) }}
                  <span class="mx-1">·</span>
                  {{ r.category?.name || '—' }}
                </p>
              </div>
              <!-- Switch at BOTH breakpoints — the old static Badge made
                   activating/deactivating impossible on a phone. -->
              <div class="flex shrink-0 flex-col items-end gap-2">
                <Switch
                  :checked="r.is_active"
                  :aria-label="__('toggle_active')"
                  @update:checked="toggleActive(r)"
                />
                <span class="text-xs text-muted-foreground">
                  {{ r.is_active ? __('active') : __('inactive') }}
                </span>
              </div>
            </div>
            <div
              class="mt-3 flex items-center justify-between text-sm text-muted-foreground"
            >
              <span>
                {{ __('next_run_date') }}: {{ fmtDate(r.next_run_date) }}
              </span>
              <RowActions :actions="rowActions(r)" />
            </div>
          </template>

          <template #cell-1="{ row: r }">
            <span
              class="capitalize"
              :class="
                r.type === 'income' ? 'text-emerald-600' : 'text-rose-600'
              "
            >
              {{ __(r.type) }}
            </span>
          </template>
          <template #cell-6="{ row: r }">
            <Switch
              :checked="r.is_active"
              :aria-label="__('toggle_active')"
              @update:checked="toggleActive(r)"
            />
          </template>
          <template #cell-7="{ row: r }">
            <div class="flex items-center justify-end gap-2">
              <RowActions :actions="rowActions(r)" />
            </div>
          </template>
        </ResponsiveTable>
      </DataListState>

      <AppPagination
        v-if="!loading && recurrings.meta"
        :meta="recurrings.meta"
        :links="recurrings.links"
      />
    </div>
  </div>

  <RecurringTransactionFormDialog
    v-model:open="createDialogOpen"
    :balances="balances"
    :categories="categories"
  />
  <RecurringTransactionFormDialog
    v-model:open="updateDialogOpen"
    :recurring="targetData"
    :balances="balances"
    :categories="categories"
  />
  <RecurringTransactionDeleteDialog
    v-model:open="deleteDialogOpen"
    :recurring="targetData"
  />
</template>
