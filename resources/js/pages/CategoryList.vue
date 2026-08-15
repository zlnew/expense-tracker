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
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('categories')"
          :description="__('category_list_description')"
          class="mb-0"
        />
        <Button @click="createDialogOpen = true">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('category') }) }}
        </Button>
      </div>

      <div class="flex flex-col items-center gap-4 lg:flex-row">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <SearchInput
            v-model="search"
            :placeholder="__('search_categories_placeholder')"
          />
        </div>

        <div class="flex w-full gap-2 sm:w-auto">
          <div class="w-full">
            <Select v-model="typeFilter">
              <SelectTrigger class="w-full bg-background">
                <SelectValue
                  :placeholder="__('all_data', { data: __('types') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="all">
                    {{ __('all_data', { data: __('types') }) }}
                  </SelectItem>
                  <SelectItem
                    v-for="value in types"
                    :key="value"
                    :value="value"
                  >
                    {{ __(value) }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>
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
          <Button @click="createDialogOpen = true">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('category') }) }}
          </Button>
        </template>

        <ResponsiveTable
          :columns="[
            { header: '#', cell: (row, i) => i + 1, cellClass: 'w-[60px]' },
            {
              header: __('type'),
              cell: (c) => __(c.type),
              cellClass: 'w-[100px] text-muted-foreground',
            },
            {
              header: __('name'),
              cell: (c) => c.name,
              cellClass: 'font-medium',
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
            <div class="flex items-center justify-between">
              <div>
                <p
                  class="mb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                  {{ __(c.type) }}
                </p>
                <h3 class="text-lg font-bold">{{ c.name }}</h3>
              </div>
              <RowActions :actions="rowActions(c)" collapse-below="md" />
            </div>
          </template>

          <!-- Desktop cells -->
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
