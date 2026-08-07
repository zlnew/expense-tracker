<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { Plus, Search, SquarePen, Tags, Trash2 } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import AppPagination from '@/components/AppPagination.vue'
import CategoryCreateDialog from '@/components/dialogs/CategoryCreateDialog.vue'
import CategoryDeleteDialog from '@/components/dialogs/CategoryDeleteDialog.vue'
import CategoryUpdateDialog from '@/components/dialogs/CategoryUpdateDialog.vue'
import Heading from '@/components/Heading.vue'
import ListSkeleton from '@/components/ListSkeleton.vue'
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

// True while a debounced search/filter visit is in flight — shows the skeleton.
const loading = ref(false)

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
    onStart: () => {
      loading.value = true
    },
    onFinish: () => {
      loading.value = false
    },
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

  <div>
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
              class="h-10 w-full bg-background pl-8 md:h-9"
            />
          </div>
        </div>

        <div class="flex w-full gap-2 sm:w-auto">
          <div class="w-full">
            <Select v-model="typeFilter">
              <SelectTrigger class="h-10 w-full bg-background md:h-9">
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

      <!-- Loading skeleton while a search/filter visit is in flight -->
      <ListSkeleton v-if="loading" :rows="5" />

      <!-- Mobile View: Cards -->
      <div v-if="!loading" class="grid grid-cols-1 gap-4 md:hidden">
        <div
          v-if="categories.data.length === 0"
          class="flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed bg-background/50 p-8 text-center"
        >
          <div class="mb-4 rounded-full bg-muted p-4">
            <Tags class="size-8 text-muted-foreground" />
          </div>
          <h3 class="text-lg font-semibold">
            {{ __('no_data_found', { data: __('categories') }) }}
          </h3>
          <p class="mb-6 text-muted-foreground">
            {{ __('category_list_description') }}
          </p>
          <Button @click="createDialogOpen = true">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('category') }) }}
          </Button>
        </div>
        <div
          v-for="c in categories.data"
          :key="c.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="flex items-center justify-between">
            <div>
              <p
                class="mb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
              >
                {{ __(c.type) }}
              </p>
              <h3 class="text-lg font-bold">{{ c.name }}</h3>
            </div>
            <div class="flex items-center gap-1">
              <Button
                variant="ghost"
                size="icon"
                class="h-10 w-10"
                @click="openEditDialog(c)"
                :title="__('edit_data', { data: __('category') })"
              >
                <SquarePen class="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-10 w-10 text-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="openDeleteDialog(c)"
                :title="__('delete_data', { data: __('category') })"
              >
                <Trash2 class="size-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop View: Table -->
      <div
        v-if="!loading"
        class="hidden overflow-hidden rounded-md border bg-background md:block"
      >
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
        v-if="!loading && categories.meta"
        :meta="categories.meta"
        :links="categories.links"
      />
    </div>
  </div>

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
