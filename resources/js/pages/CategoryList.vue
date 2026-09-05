<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3'
import { Plus, SquarePen, Tags, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import CategoryCreateDialog from '@/components/dialogs/CategoryCreateDialog.vue'
import CategoryDeleteDialog from '@/components/dialogs/CategoryDeleteDialog.vue'
import CategoryUpdateDialog from '@/components/dialogs/CategoryUpdateDialog.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { index as categoriesIndex } from '@/routes/categories'
import type { Category, Paginate } from '@/types'

type Data = Category

defineProps<{
  categories: Paginate<Data>
  types: string[]
}>()

const { __ } = useLang()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('budgets'),
    },
    {
      title: __('categories'),
    },
  ],
})

const createDialogOpen = ref(false)
const updateDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const targetData = ref<Data | null>(null)

const loading = ref(false)

const { search, type: typeFilter } = useFilters({
  url: categoriesIndex.url(),
  defaults: { type: 'all' },
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
})

const openEditDialog = (data: Data) => {
  targetData.value = data
  updateDialogOpen.value = true
}

const openDeleteDialog = (data: Data) => {
  targetData.value = data
  deleteDialogOpen.value = true
}

const rowActions = (c: Data) => [
  {
    label: __('edit_data', { data: __('category') }),
    icon: SquarePen,
    onClick: () => openEditDialog(c),
  },
  {
    label: __('delete_data', { data: __('category') }),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(c),
  },
]
</script>

<template>
  <Head :title="__('categories')" />

  <AppContent>
    <div class="page-container space-y-5">
      <!-- Command Bar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2.5">
          <h1 class="font-mono text-base font-bold text-zinc-100 uppercase tracking-wide">
            {{ __('categories') }}
          </h1>
          <span class="stat-chip text-zinc-400 font-semibold">
            {{ categories.meta?.total ?? categories.data.length }} pos
          </span>
        </div>

        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-3.5 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
          @click="createDialogOpen = true"
        >
          <Plus class="size-3.5 stroke-[2.5]" />
          <span>{{ __('add_data', { data: __('category') }) }}</span>
        </button>
      </div>

      <!-- Filters: Search + Segmented Pills -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="w-full sm:max-w-xs">
          <SearchInput
            v-model="search"
            :placeholder="__('search_categories_placeholder')"
          />
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none font-mono text-xs">
          <button
            type="button"
            class="px-3 py-1.5 rounded-xl font-medium transition-all"
            :class="typeFilter === 'all' ? 'bg-[#181820] text-emerald-400 border border-emerald-500/30' : 'bg-[#121217] text-zinc-400 border border-[#1f222e] hover:text-zinc-200'"
            @click="typeFilter = 'all'"
          >
            {{ __('all_data', { data: __('types') }) }}
          </button>
          <button
            v-for="value in types"
            :key="value"
            type="button"
            class="px-3 py-1.5 rounded-xl font-medium capitalize transition-all"
            :class="typeFilter === value ? 'bg-[#181820] text-emerald-400 border border-emerald-500/30' : 'bg-[#121217] text-zinc-400 border border-[#1f222e] hover:text-zinc-200'"
            @click="typeFilter = value"
          >
            {{ __(value) }}
          </button>
        </div>
      </div>

      <DataListState
        :loading="loading"
        :is-empty="!loading && categories.data.length === 0"
        :rows="5"
        :empty-icon="Tags"
        :empty-title="__('no_data_found', { data: __('categories') })"
        :empty-description="__('category_list_description')"
      >
        <template #empty>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
            @click="createDialogOpen = true"
          >
            <Plus class="size-4 stroke-[2.5]" />
            {{ __('add_data', { data: __('category') }) }}
          </button>
        </template>

        <ResponsiveTable
          :columns="[
            { header: '#', cell: (row, i) => i + 1, cellClass: 'w-[60px] font-mono text-zinc-500' },
            {
              header: __('type'),
              cell: (c) => __(c.type),
              cellClass: 'w-[120px]',
            },
            {
              header: __('name'),
              cell: (c) => c.name,
              cellClass: 'font-medium font-mono text-zinc-100',
            },
            {
              header: __('actions'),
              cell: () => '',
              cellClass: 'w-[100px] text-right',
            },
          ]"
          :rows="categories.data"
        >
          <!-- Mobile card -->
          <template #card="{ row: c }">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <div 
                  class="size-10 rounded-xl flex items-center justify-center shrink-0 border"
                  :class="c.type === 'income' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'"
                >
                  <Tags class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-mono text-sm font-bold text-zinc-100">{{ c.name }}</h3>
                    <span
                      class="inline-flex rounded px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border shrink-0"
                      :class="
                        c.type === 'income'
                          ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400'
                          : 'border-rose-500/30 bg-rose-500/15 text-rose-400'
                      "
                    >
                      {{ __(c.type) }}
                    </span>
                  </div>
                  <span class="text-[11px] font-mono text-zinc-500">ID Pos #{{ c.id }}</span>
                </div>
              </div>
              <RowActions :actions="rowActions(c)" collapse-below="md" />
            </div>
          </template>

          <!-- Desktop cells -->
          <template #cell-1="{ row: c }">
            <span
              class="inline-flex rounded px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border"
              :class="
                c.type === 'income'
                  ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400'
                  : 'border-rose-500/30 bg-rose-500/15 text-rose-400'
              "
            >
              {{ __(c.type) }}
            </span>
          </template>

          <template #cell-3="{ row: c }">
            <div class="flex items-center justify-end gap-2">
              <RowActions :actions="rowActions(c)" collapse-below="md" />
            </div>
          </template>
        </ResponsiveTable>
      </DataListState>

      <AppPagination
        v-if="!loading && categories.meta"
        :meta="categories.meta"
        :links="categories.links"
      />
    </div>
  </AppContent>

  <CategoryCreateDialog v-model:open="createDialogOpen" :types="types" />
  <CategoryUpdateDialog
    v-model:open="updateDialogOpen"
    :category="targetData"
    :types="types"
  />
  <CategoryDeleteDialog
    v-model:open="deleteDialogOpen"
    :category="targetData"
  />
</template>
