<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import CategoryCreateDialog from '@/components/dialogs/CategoryCreateDialog.vue'
import CategoryDeleteDialog from '@/components/dialogs/CategoryDeleteDialog.vue'
import CategoryUpdateDialog from '@/components/dialogs/CategoryUpdateDialog.vue'
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
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
import { useLang } from '@/composables/useLang'
import { useParam } from '@/composables/useParam'
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

const param = useParam()

const createDialogOpen = ref(false)
const updateDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const targetData = ref<Data | null>(null)

const search = ref(param.get('search') || '')
const typeFilter = ref(param.get('type') || 'all')

watch([search, typeFilter], () => {
  fetchData()
})

const fetchData = useDebounceFn(() => {
  const params: Record<string, any> = {}

  if (search.value) {
    params.search = search.value
  }

  if (typeFilter.value) {
    params.type = typeFilter.value
  }

  router.get(categoriesIndex.url(), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const openEditDialog = (data: Data) => {
  targetData.value = data
  updateDialogOpen.value = true
}

const openDeleteDialog = (data: Data) => {
  targetData.value = data
  deleteDialogOpen.value = true
}
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
          <div class="relative w-full">
            <Search
              class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
              v-model="search"
              :placeholder="__('search_categories_placeholder')"
              class="w-full bg-background pl-8"
            />
          </div>
        </div>

        <div class="flex w-full gap-2 sm:w-auto">
          <div class="w-full">
            <Select v-model="typeFilter">
              <SelectTrigger class="bg-background">
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

      <div class="overflow-hidden rounded-md border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-[60px]">#</TableHead>
              <TableHead class="w-[100px]">{{ __('type') }}</TableHead>
              <TableHead>{{ __('name') }}</TableHead>
              <TableHead class="w-[100px] text-right">
                {{ __('actions') }}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="categories.data.length === 0">
              <TableCell colspan="4" class="h-24 text-center">
                {{ __('no_data_found', { data: __('categories') }) }}
              </TableCell>
            </TableRow>
            <template v-else>
              <TableRow v-for="(c, index) in categories.data" :key="c.id">
                <TableCell>
                  {{
                    (categories.meta.current_page - 1) *
                      categories.meta.per_page +
                    index +
                    1
                  }}.
                </TableCell>
                <TableCell class="text-muted-foreground">
                  {{ __(c.type) }}
                </TableCell>
                <TableCell class="font-medium">
                  {{ c.name }}
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="openEditDialog(c)"
                      :title="__('edit_data', { data: __('category') })"
                    >
                      <SquarePen class="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      @click="openDeleteDialog(c)"
                      :title="__('delete_data', { data: __('category') })"
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
        v-if="categories.meta"
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
