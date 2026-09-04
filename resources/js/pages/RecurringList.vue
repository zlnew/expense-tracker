<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { Plus, Repeat, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import RecurringTransactionDeleteDialog from '@/components/dialogs/RecurringTransactionDeleteDialog.vue'
import RecurringTransactionFormDialog from '@/components/dialogs/RecurringTransactionFormDialog.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import TransactionTabs from '@/components/TransactionTabs.vue'
import { useDate } from '@/composables/useDate'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import {
  index as recurringIndex,
  update as updateRecurring,
} from '@/routes/recurring-transactions'
import type { Balance, Category, Paginate, RecurringTransaction } from '@/types'

defineProps<{
  recurrings: Paginate<RecurringTransaction>
  balances: Balance[]
  categories: Category[]
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { formatDate } = useDate()

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

const { search } = useFilters({
  url: recurringIndex.url(),
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
</script>

<template>
  <Head :title="__('recurring_transactions')" />

  <AppContent>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('recurring_transactions')"
          :description="__('recurring_transactions_description')"
          class="mb-0"
        />
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-3.5 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
          @click="createDialogOpen = true"
        >
          <Plus class="size-4 stroke-[2.5]" />
          {{ __('add_data', { data: __('recurring_transaction') }) }}
        </button>
      </div>

      <div class="flex flex-col items-center gap-4 lg:flex-row">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <SearchInput
            v-model="search"
            :placeholder="__('search_recurring_placeholder')"
          />
        </div>

        <!-- Segmented [ Transactions | Recurring ] — visible at every
             breakpoint, mirroring the transactions page. -->
        <TransactionTabs viewMode="recurring" />
      </div>

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
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
            @click="createDialogOpen = true"
          >
            <Plus class="size-4 stroke-[2.5]" />
            {{ __('add_data', { data: __('recurring_transaction') }) }}
          </button>
        </template>

        <ResponsiveTable
          :columns="[
            {
              header: __('description'),
              cell: (r) => r.description || '—',
              cellClass: 'font-medium',
            },
            {
              header: __('type'),
              cell: (r) => __(r.type),
              cellClass: 'capitalize',
            },
            {
              header: __('frequency'),
              cell: (r) => __(r.frequency),
              cellClass: 'capitalize',
            },
            {
              header: __('amount'),
              cell: (r) => formatAmount(r.amount),
              cellClass: 'text-right',
            },
            {
              header: __('category'),
              cell: (r) => r.category?.name || '—',
            },
            {
              header: __('next_run_date'),
              cell: (r) => formatDate(r.next_run_date, 'DD MMM YYYY'),
            },
            {
              header: __('status'),
              cell: (r) => (r.is_active ? __('active') : __('inactive')),
              cellClass: 'w-[110px]',
            },
            {
              header: __('actions'),
              cell: () => '',
              cellClass: 'w-[110px] text-right',
            },
          ]"
          :rows="recurrings.data"
        >
          <!-- Mobile card -->
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
              <div class="flex items-center gap-1">
                <RowActions :actions="rowActions(r)" collapse-below="md" />
              </div>
            </div>
            <div
              class="mt-3 flex items-center justify-between text-sm text-muted-foreground"
            >
              <span>
                {{ __('next_run_date') }}:
                {{ formatDate(r.next_run_date, 'DD MMM YYYY') }}
              </span>
              <!-- Real toggle on mobile too — not a static badge -->
              <button
                type="button"
                class="touch-target rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                :class="
                  r.is_active
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                    : 'border-muted bg-muted/40 text-muted-foreground hover:bg-muted'
                "
                :aria-pressed="r.is_active"
                @click="toggleActive(r)"
              >
                {{ r.is_active ? __('active') : __('inactive') }}
              </button>
            </div>
          </template>

          <!-- Desktop cells -->
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
            <button
              type="button"
              class="touch-target rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
              :class="
                r.is_active
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                  : 'border-muted bg-muted/40 text-muted-foreground hover:bg-muted'
              "
              :aria-pressed="r.is_active"
              @click="toggleActive(r)"
            >
              {{ r.is_active ? __('active') : __('inactive') }}
            </button>
          </template>
          <template #cell-7="{ row: r }">
            <div class="flex items-center justify-end gap-2">
              <RowActions :actions="rowActions(r)" collapse-below="md" />
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
  </AppContent>

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
