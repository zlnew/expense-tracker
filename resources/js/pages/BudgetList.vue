<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import { CheckCircle2, Plus, Wallet } from 'lucide-vue-next'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import BudgetDeleteDialog from '@/components/dialogs/BudgetDeleteDialog.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useDate } from '@/composables/useDate'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import {
  create as budgetCreate,
  edit as budgetEdit,
  index as budgetListUrl,
  setActive as budgetSetActive,
  show as budgetShow,
} from '@/routes/budgets'
import type { Budget, Paginate } from '@/types'

type Data = Budget

defineProps<{
  budgets: Paginate<Data>
}>()

const { __ } = useLang()
const { formatDate } = useDate()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('budgets'),
    },
    {
      title: __('list'),
    },
  ],
})

const deleteDialogOpen = ref(false)
const targetData = ref<Data | null>(null)

const loading = ref(false)

// URL-backed search — BudgetQuery gained $searchable = ['notes'] so the
// server filters; the list was paginated with no search and no loading
// state, so finding "March 2024" meant paging with prev/next.
const { search } = useFilters({
  url: budgetListUrl.url(),
  defaults: {},
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
})

const openDeleteDialog = (data: Data) => {
  targetData.value = data
  deleteDialogOpen.value = true
}

const setActive = (budget: Budget) => {
  router.post(
    budgetSetActive.url({ budget }),
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

const rowActions = (b: Data) => [
  {
    label: __('view_detail'),
    icon: undefined,
    onClick: () => router.visit(budgetShow.url({ budget: b })),
  },
  ...(b.is_active
    ? []
    : [
        {
          label: __('set_as_active'),
          icon: CheckCircle2,
          onClick: () => setActive(b),
        },
      ]),
  {
    label: __('edit_data', { data: __('budget') }),
    icon: undefined,
    onClick: () => router.visit(budgetEdit.url({ budget: b })),
  },
  {
    label: __('delete_data', { data: __('budget') }),
    icon: undefined,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(b),
  },
]

const columns = [
  {
    header: '#',
    cell: (_row: Data, index: number) => index + 1,
    cellClass: 'w-[60px]',
  },
  {
    header: __('period_start'),
    cell: (b: Data) => formatDate(b.period_start, 'DD MMM YYYY'),
    cellClass: 'w-[150px] font-medium',
  },
  {
    header: __('period_end'),
    cell: (b: Data) => formatDate(b.period_end, 'DD MMM YYYY'),
    cellClass: 'w-[150px] font-medium',
  },
  {
    header: __('notes'),
    cell: (b: Data) => b.notes ?? '-',
    cellClass: 'text-sm text-muted-foreground',
  },
  {
    header: '',
    cell: (b: Data) => (b.is_active ? __('active') : ''),
    cellClass: 'w-[100px]',
  },
  {
    header: __('actions'),
    cell: () => '',
    headerClass: 'text-right',
    cellClass: 'w-[100px] text-right',
  },
]
</script>

<template>
  <Head :title="__('budgets')" />

  <div>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('budgets')"
          :description="__('budget_list_description')"
          class="mb-0"
        />
        <Button asChild>
          <Link :href="budgetCreate.url()">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('budget') }) }}
          </Link>
        </Button>
      </div>

      <div class="flex w-full items-center gap-2 lg:max-w-md">
        <SearchInput
          v-model="search"
          :placeholder="__('search_budgets_placeholder')"
        />
      </div>

      <!-- One owner: skeleton / empty / table, any viewport -->
      <DataListState
        :loading="loading"
        :is-empty="!loading && budgets.data.length === 0"
        :rows="5"
        :empty-icon="Wallet"
        :empty-title="__('no_data_found', { data: __('budgets') })"
        :empty-description="__('budget_list_description')"
      >
        <template #empty>
          <Button asChild>
            <Link :href="budgetCreate.url()">
              <Plus class="mr-2 size-4" />
              {{ __('add_data', { data: __('budget') }) }}
            </Link>
          </Button>
        </template>

        <ResponsiveTable :columns="columns" :rows="budgets.data">
          <template #card="{ row: b }">
            <div class="mb-3 flex items-center justify-between border-b pb-3">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold">{{
                  formatDate(b.period_start, 'MMM YYYY')
                }}</span>
                <Badge
                  v-if="b.is_active"
                  variant="default"
                  class="px-2.5 py-1 text-xs"
                  >{{ __('active') }}</Badge
                >
              </div>
              <RowActions :actions="rowActions(b)" />
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-xs text-muted-foreground">
                  {{ __('period_start') }}
                </p>
                <p class="font-medium">
                  {{ formatDate(b.period_start, 'DD MMM YYYY') }}
                </p>
              </div>
              <div>
                <p class="text-xs text-muted-foreground">
                  {{ __('period_end') }}
                </p>
                <p class="font-medium">
                  {{ formatDate(b.period_end, 'DD MMM YYYY') }}
                </p>
              </div>
            </div>
            <p
              v-if="b.notes"
              class="mt-3 line-clamp-2 text-sm text-muted-foreground italic"
            >
              "{{ b.notes }}"
            </p>
          </template>
          <template #cell-4="{ row: b }">
            <div class="max-w-md truncate wrap-anywhere">
              {{ b.notes ?? '-' }}
            </div>
          </template>
          <template #cell-5="{ row: b }">
            <Badge v-if="b.is_active">{{ __('active') }}</Badge>
          </template>
          <template #cell-6="{ row: b }">
            <div class="flex items-center justify-end gap-2">
              <RowActions :actions="rowActions(b)" />
            </div>
          </template>
        </ResponsiveTable>
      </DataListState>

      <AppPagination
        v-if="!loading && budgets.meta"
        :meta="budgets.meta"
        :links="budgets.links"
      />
    </div>
  </div>

  <BudgetDeleteDialog v-model:open="deleteDialogOpen" :budget="targetData" />
</template>
