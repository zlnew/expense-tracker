<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3'
import { Info, Plus, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import BudgetDeleteDialog from '@/components/dialogs/BudgetDeleteDialog.vue'
import Heading from '@/components/Heading.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import {
  create as budgetCreate,
  edit as budgetEdit,
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
                  {{ formatDate(b.period_start, 'DD MMM YYYY') }}
                </TableCell>
                <TableCell class="font-medium">
                  {{ formatDate(b.period_end, 'DD MMM YYYY') }}
                </TableCell>
                <TableCell class="text-sm text-muted-foreground">
                  <div class="max-w-md truncate wrap-anywhere">
                    {{ b.notes ?? '-' }}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge v-if="b.is_active">{{ __('active') }}</Badge>
                </TableCell>

                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      :title="__('view_detail')"
                      asChild
                    >
                      <Link :href="budgetShow.url({ budget: b })">
                        <Info class="size-4" />
                      </Link>
                    </Button>
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
