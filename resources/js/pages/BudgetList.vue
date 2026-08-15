<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import {
  CheckCircle2,
  Info,
  Plus,
  SquarePen,
  Trash2,
  Wallet,
} from 'lucide-vue-next'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import AppContent from '@/components/AppContent.vue'
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
  index as budgetIndex,
  show as budgetShow,
  setActive as budgetSetActive,
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

const { search } = useFilters({
  url: budgetIndex.url(),
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

const rowActions = (b: Budget) => {
  const actions: {
    label: string
    icon: typeof Info
    variant?: 'default' | 'destructive'
    onClick: () => void
  }[] = [
    {
      label: __('view_detail'),
      icon: Info,
      onClick: () => router.visit(budgetShow.url({ budget: b })),
    },
  ]

  if (!b.is_active) {
    actions.push({
      label: __('set_as_active'),
      icon: CheckCircle2,
      onClick: () => setActive(b),
    })
  }

  actions.push(
    {
      label: __('edit_data', { data: __('budget') }),
      icon: SquarePen,
      onClick: () => router.visit(budgetEdit.url({ budget: b })),
    },
    {
      label: __('delete_data', { data: __('budget') }),
      icon: Trash2,
      variant: 'destructive' as const,
      onClick: () => openDeleteDialog(b),
    },
  )

  return actions
}
</script>

<template>
  <Head :title="__('budgets')" />

  <AppContent>
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

      <div class="flex flex-col items-center gap-4 lg:flex-row">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <SearchInput
            v-model="search"
            :placeholder="__('search_budgets_placeholder')"
          />
        </div>
      </div>

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

        <ResponsiveTable
          :columns="[
            { header: '#', cell: (row, i) => i + 1, cellClass: 'w-[60px]' },
            {
              header: __('period_start'),
              cell: (b) => formatDate(b.period_start, 'DD MMM YYYY'),
              cellClass: 'w-[150px] font-medium',
            },
            {
              header: __('period_end'),
              cell: (b) => formatDate(b.period_end, 'DD MMM YYYY'),
              cellClass: 'w-[150px] font-medium',
            },
            {
              header: __('notes'),
              cell: (b) => b.notes ?? '-',
            },
            {
              header: __('status'),
              cell: (b) => (b.is_active ? __('active') : ''),
              cellClass: 'w-[100px]',
            },
            {
              header: __('actions'),
              cell: () => '',
              cellClass: 'w-[100px] text-right',
            },
          ]"
          :rows="budgets.data"
        >
          <!-- Mobile card -->
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
                >
                  {{ __('active') }}
                </Badge>
              </div>
              <RowActions :actions="rowActions(b)" collapse-below="md" />
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

          <!-- Desktop cells -->
          <template #cell-3="{ row: b }">
            <div class="max-w-md truncate wrap-anywhere">
              {{ b.notes ?? '-' }}
            </div>
          </template>
          <template #cell-5="{ row: b }">
            <div class="flex items-center justify-end gap-2">
              <RowActions :actions="rowActions(b)" collapse-below="md" />
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
  </AppContent>

  <!-- Mobile FAB removed: header CTA is the single budget-add; the bottom nav
       center FAB covers transaction create. No redundant floating buttons. -->

  <BudgetDeleteDialog v-model:open="deleteDialogOpen" :budget="targetData" />
</template>
