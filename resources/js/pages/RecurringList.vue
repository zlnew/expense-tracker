<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { Plus, Repeat, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import RecurringTransactionDeleteDialog from '@/components/dialogs/RecurringTransactionDeleteDialog.vue'
import RecurringTransactionFormDialog from '@/components/dialogs/RecurringTransactionFormDialog.vue'
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
import { useLang } from '@/composables/useLang'
import { update as updateRecurring } from '@/routes/recurring-transactions'
import type { Balance, Category, RecurringTransaction } from '@/types'

defineProps<{
  recurrings: RecurringTransaction[]
  balances: Balance[]
  categories: Category[]
}>()

const { __ } = useLang()

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

const amountFmt = (amount: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount)

const fmtDate = (d: string | null) =>
  d ? new Date(d).toLocaleDateString('id-ID') : '—'
</script>

<template>
  <Head :title="__('recurring_transactions')" />

  <div>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('recurring_transactions')"
          :description="__('recurring_transactions_description')"
          class="mb-0"
        />
        <Button @click="createDialogOpen = true">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('recurring_transaction') }) }}
        </Button>
      </div>

      <!-- Mobile View: Cards -->
      <div class="grid grid-cols-1 gap-4 md:hidden">
        <div
          v-if="recurrings.length === 0"
          class="flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed bg-background/50 p-8 text-center"
        >
          <div class="mb-4 rounded-full bg-muted p-4">
            <Repeat class="size-8 text-muted-foreground" />
          </div>
          <h3 class="text-lg font-semibold">
            {{ __('no_data_found', { data: __('recurring_transactions') }) }}
          </h3>
          <p class="mb-6 text-muted-foreground">
            {{ __('recurring_transactions_description') }}
          </p>
          <Button @click="createDialogOpen = true">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('recurring_transaction') }) }}
          </Button>
        </div>
        <div
          v-for="r in recurrings"
          :key="r.id"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
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
                {{ amountFmt(r.amount) }}
                <span class="mx-1">·</span>
                {{ r.category?.name || '—' }}
              </p>
            </div>
            <Badge :variant="r.is_active ? 'default' : 'secondary'">
              {{ r.is_active ? __('active') : __('inactive') }}
            </Badge>
          </div>
          <div
            class="mt-3 flex items-center justify-between text-sm text-muted-foreground"
          >
            <span>
              {{ __('next_run_date') }}:
              {{ fmtDate(r.next_run_date) }}
            </span>
            <div class="flex items-center gap-1">
              <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                @click="openEditDialog(r)"
                :title="__('edit_data', { data: __('recurring_transaction') })"
              >
                <SquarePen class="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="openDeleteDialog(r)"
                :title="
                  __('delete_data', { data: __('recurring_transaction') })
                "
              >
                <Trash2 class="size-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop View: Table -->
      <div
        class="hidden overflow-hidden rounded-md border bg-background md:block"
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{{ __('description') }}</TableHead>
              <TableHead>{{ __('type') }}</TableHead>
              <TableHead>{{ __('frequency') }}</TableHead>
              <TableHead class="text-right">{{ __('amount') }}</TableHead>
              <TableHead>{{ __('category') }}</TableHead>
              <TableHead>{{ __('next_run_date') }}</TableHead>
              <TableHead>{{ __('status') }}</TableHead>
              <TableHead class="w-[110px] text-right">{{
                __('actions')
              }}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="recurrings.length === 0">
              <TableCell colspan="8" class="h-24 text-center">
                {{
                  __('no_data_found', { data: __('recurring_transactions') })
                }}
              </TableCell>
            </TableRow>
            <template v-else>
              <TableRow v-for="r in recurrings" :key="r.id">
                <TableCell class="font-medium">
                  {{ r.description || '—' }}
                </TableCell>
                <TableCell
                  class="capitalize"
                  :class="
                    r.type === 'income' ? 'text-emerald-600' : 'text-rose-600'
                  "
                >
                  {{ __(r.type) }}
                </TableCell>
                <TableCell class="capitalize">{{ __(r.frequency) }}</TableCell>
                <TableCell class="text-right">
                  {{ amountFmt(r.amount) }}
                </TableCell>
                <TableCell class="text-muted-foreground">
                  {{ r.category?.name || '—' }}
                </TableCell>
                <TableCell>{{ fmtDate(r.next_run_date) }}</TableCell>
                <TableCell>
                  <button
                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="
                      r.is_active
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                        : 'border-muted bg-muted/40 text-muted-foreground hover:bg-muted'
                    "
                    @click="toggleActive(r)"
                  >
                    {{ r.is_active ? __('active') : __('inactive') }}
                  </button>
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="openEditDialog(r)"
                      :title="
                        __('edit_data', { data: __('recurring_transaction') })
                      "
                    >
                      <SquarePen class="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      @click="openDeleteDialog(r)"
                      :title="
                        __('delete_data', { data: __('recurring_transaction') })
                      "
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
    </div>
  </div>

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
