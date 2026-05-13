<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import BudgetDeleteDialog from '@/components/dialogs/BudgetDeleteDialog.vue'
import Heading from '@/components/Heading.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
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
import {
  index as budgetsIndex,
  create as budgetCreate,
  edit as budgetEdit,
} from '@/routes/budgets'
import type { Budget, Paginate } from '@/types'

type Data = Budget

defineProps<{
  budgets: Paginate<Data>
}>()

const { __ } = useLang()

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

const param = useParam()

const deleteDialogOpen = ref(false)
const targetData = ref<Data | null>(null)

const search = ref(param.get('search') || '')

watch([search], () => {
  fetchData()
})

const fetchData = useDebounceFn(() => {
  const params: Record<string, any> = {}

  if (search.value) {
    params.search = search.value
  }

  router.get(budgetsIndex.url(), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const openDeleteDialog = (data: Data) => {
  targetData.value = data
  deleteDialogOpen.value = true
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
          :description="__('budgets_description')"
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
          <div class="relative w-full">
            <Search
              class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
              v-model="search"
              :placeholder="__('search_budgets_placeholder')"
              class="w-full bg-background pl-8"
            />
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-md border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-[60px]">#</TableHead>
              <TableHead class="w-[150px]">{{ __('period_start') }}</TableHead>
              <TableHead class="w-[150px]">{{ __('period_end') }}</TableHead>
              <TableHead>{{ __('notes') }}</TableHead>
              <TableHead class="w-[100px]"></TableHead>
              <TableHead class="w-[100px] text-right">
                {{ __('actions') }}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="budgets.data.length === 0">
              <TableCell colspan="7" class="h-24 text-center">
                {{ __('no_data_found', { data: __('budgets') }) }}
              </TableCell>
            </TableRow>
            <template v-else>
              <TableRow v-for="(b, index) in budgets.data" :key="b.id">
                <TableCell>
                  {{
                    (budgets.meta.current_page - 1) * budgets.meta.per_page +
                    index +
                    1
                  }}.
                </TableCell>
                <TableCell class="font-medium">
                  {{ b.period_start }}
                </TableCell>
                <TableCell class="font-medium">
                  {{ b.period_end }}
                </TableCell>
                <TableCell class="text-sm text-muted-foreground">
                  {{ b.notes ?? '-' }}
                </TableCell>
                <TableCell>
                  <Badge v-if="b.is_active">{{ __('active') }}</Badge>
                </TableCell>

                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      :title="__('edit_data', { data: __('budget') })"
                      asChild
                    >
                      <Link :href="budgetEdit.url({ budget: b })">
                        <SquarePen class="size-4" />
                      </Link>
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      @click="openDeleteDialog(b)"
                      :title="__('delete_data', { data: __('budget') })"
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
        v-if="budgets.meta"
        :meta="budgets.meta"
        :links="budgets.links"
      />
    </div>
  </AppContent>

  <BudgetDeleteDialog v-model:open="deleteDialogOpen" :budget="targetData" />
</template>
