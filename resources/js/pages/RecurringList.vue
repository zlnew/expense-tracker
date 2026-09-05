<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { Plus, Repeat, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import RecurringTransactionDeleteDialog from '@/components/dialogs/RecurringTransactionDeleteDialog.vue'
import RecurringTransactionFormDialog from '@/components/dialogs/RecurringTransactionFormDialog.vue'
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
    <div class="page-container space-y-5">
      <!-- Command Bar -->
      <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-center gap-2.5">
          <h1
            class="font-mono text-base font-bold tracking-wide text-zinc-100 uppercase"
          >
            Tagihan & Langganan
          </h1>
          <span class="stat-chip font-semibold text-zinc-400">
            {{ recurrings.meta?.total ?? recurrings.data.length }} jadwal
          </span>
        </div>

        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-3.5 py-2 font-mono text-xs font-bold text-[#0a0a0c] shadow-[0_0_15px_rgba(16,185,129,0.35)] transition-all hover:bg-emerald-400 active:scale-95"
          @click="createDialogOpen = true"
        >
          <Plus class="size-3.5 stroke-[2.5]" />
          <span>{{
            __('add_data', { data: __('recurring_transaction') })
          }}</span>
        </button>
      </div>

      <!-- Filters: Search & Tabs -->
      <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="w-full sm:max-w-xs">
          <SearchInput
            v-model="search"
            :placeholder="__('search_recurring_placeholder')"
          />
        </div>

        <div class="w-full sm:w-auto sm:min-w-[260px]">
          <TransactionTabs viewMode="recurring" />
        </div>
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
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-[#0a0a0c] shadow-[0_0_15px_rgba(16,185,129,0.35)] transition-all hover:bg-emerald-400 active:scale-95"
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
              cellClass: 'font-mono text-zinc-100 font-semibold',
            },
            {
              header: __('type'),
              cell: (r) => __(r.type),
              cellClass: 'w-[110px]',
            },
            {
              header: __('frequency'),
              cell: (r) => __(r.frequency),
              cellClass: 'capitalize font-mono text-xs text-zinc-300',
            },
            {
              header: __('amount'),
              cell: (r) => formatAmount(r.amount),
              cellClass: 'text-right font-mono font-bold',
            },
            {
              header: __('category'),
              cell: (r) => r.category?.name || '—',
              cellClass: 'font-mono text-xs text-zinc-400',
            },
            {
              header: __('next_run_date'),
              cell: (r) => formatDate(r.next_run_date, 'DD MMM YYYY'),
              cellClass: 'font-mono text-xs text-zinc-400',
            },
            {
              header: __('status'),
              cell: (r) => (r.is_active ? __('active') : __('inactive')),
              cellClass: 'w-[120px]',
            },
            {
              header: __('actions'),
              cell: () => '',
              cellClass: 'w-[100px] text-right',
            },
          ]"
          :rows="recurrings.data"
        >
          <!-- Mobile card -->
          <template #card="{ row: r }">
            <div class="space-y-3">
              <div class="flex items-start justify-between gap-2">
                <div class="flex min-w-0 items-center gap-3">
                  <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl border"
                    :class="
                      r.type === 'income'
                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400'
                        : 'border-rose-500/20 bg-rose-500/10 text-rose-400'
                    "
                  >
                    <Repeat class="size-4" />
                  </div>
                  <div class="min-w-0">
                    <h3
                      class="truncate font-mono text-sm font-bold text-zinc-100"
                    >
                      {{ r.description || '—' }}
                    </h3>
                    <p class="truncate font-mono text-xs text-zinc-400">
                      {{ r.category?.name || 'Tanpa Kategori' }}
                    </p>
                  </div>
                </div>
                <RowActions :actions="rowActions(r)" collapse-below="md" />
              </div>

              <div
                class="flex items-baseline justify-between border-t border-[#1f222e] pt-1"
              >
                <span
                  class="font-mono text-base font-bold"
                  :class="
                    r.type === 'income' ? 'text-emerald-400' : 'text-rose-400'
                  "
                >
                  {{ r.type === 'income' ? '+' : '-'
                  }}{{ formatAmount(r.amount) }}
                </span>
                <span
                  class="inline-flex rounded border border-[#1f222e] bg-[#121217] px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider text-zinc-300 uppercase"
                >
                  {{ __(r.frequency) }}
                </span>
              </div>

              <div
                class="flex items-center justify-between pt-1 font-mono text-xs text-zinc-400"
              >
                <span class="text-[11px] text-zinc-500">
                  Jadwal: {{ formatDate(r.next_run_date, 'DD MMM YYYY') }}
                </span>
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 font-mono text-xs font-semibold transition-all active:scale-95"
                  :class="
                    r.is_active
                      ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20'
                      : 'border-[#1f222e] bg-[#0d0d12] text-zinc-500 hover:text-zinc-300'
                  "
                  :aria-pressed="r.is_active"
                  @click="toggleActive(r)"
                >
                  <span
                    class="size-1.5 rounded-full"
                    :class="
                      r.is_active
                        ? 'animate-pulse bg-emerald-400'
                        : 'bg-zinc-600'
                    "
                  ></span>
                  <span>{{ r.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
              </div>
            </div>
          </template>

          <!-- Desktop cells -->
          <template #cell-1="{ row: r }">
            <span
              class="inline-flex rounded border px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase"
              :class="
                r.type === 'income'
                  ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400'
                  : 'border-rose-500/30 bg-rose-500/15 text-rose-400'
              "
            >
              {{ __(r.type) }}
            </span>
          </template>
          <template #cell-3="{ row: r }">
            <span
              class="font-mono text-sm font-bold"
              :class="
                r.type === 'income' ? 'text-emerald-400' : 'text-rose-400'
              "
            >
              {{ r.type === 'income' ? '+' : '-' }}{{ formatAmount(r.amount) }}
            </span>
          </template>
          <template #cell-6="{ row: r }">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 font-mono text-xs font-semibold transition-all active:scale-95"
              :class="
                r.is_active
                  ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20'
                  : 'border-[#1f222e] bg-[#0d0d12] text-zinc-500 hover:text-zinc-300'
              "
              :aria-pressed="r.is_active"
              @click="toggleActive(r)"
            >
              <span
                class="size-1.5 rounded-full"
                :class="
                  r.is_active ? 'animate-pulse bg-emerald-400' : 'bg-zinc-600'
                "
              ></span>
              <span>{{ r.is_active ? 'Aktif' : 'Nonaktif' }}</span>
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
