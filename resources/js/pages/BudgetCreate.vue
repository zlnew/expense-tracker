<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3'
import { Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import AppContent from '@/components/AppContent.vue'
import Heading from '@/components/Heading.vue'
import InputError from '@/components/InputError.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import RowActions from '@/components/RowActions.vue'
import StickyFormActions from '@/components/StickyFormActions.vue'
import {
  Card,
  CardAction,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from '@/components/ui/number-field'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { index as budgetIndex, store as budgetStore } from '@/routes/budgets'
import type { BudgetItem, Category } from '@/types'

const props = defineProps<{
  categories: Category[]
  carryOverPreview: Record<string, number>
}>()

const { __ } = useLang()
const { formatNumber } = useNumber()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('budgets'),
      href: budgetIndex.url(),
    },
    {
      title: __('create'),
    },
  ],
})

const form = useForm({
  period_start: '',
  period_end: '',
  cutoff_day: 25,
  notes: null as string | null,
  carry_over: false,
  items: [] as any[],
})

const expenseBudgetItems = ref<Partial<BudgetItem>[]>([])
const incomeBudgetItems = ref<Partial<BudgetItem>[]>([])

const expenseCategories = computed(() =>
  props.categories.filter((c) => c.type === 'expense'),
)

const incomeCategories = computed(() =>
  props.categories.filter((c) => c.type === 'income'),
)

const expenseTotal = computed(() =>
  expenseBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

const incomeTotal = computed(() =>
  incomeBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

onMounted(() => {
  initBudgetItems()
})

const onCarryOverChange = (value: boolean | 'indeterminate') => {
  form.carry_over = value === true
}

// Total unused amount from the previous cycle that will roll in.
const carryOverPreviewTotal = computed(() =>
  Object.values(props.carryOverPreview ?? {}).reduce((sum, v) => sum + v, 0),
)

const initBudgetItems = () => {
  expenseCategories.value.forEach((ec) => {
    expenseBudgetItems.value.push({
      category_id: ec.id,
      type: ec.type,
      planned_amount: 0,
      category: ec,
    })
  })

  incomeCategories.value.forEach((ic) => {
    incomeBudgetItems.value.push({
      category_id: ic.id,
      type: ic.type,
      planned_amount: 0,
      category: ic,
    })
  })
}

const submit = () => {
  // Double-submit guard: the submit button is disabled via
  // :disabled="form.processing", but a same-tick double click can reach the
  // handler before Vue re-renders — reject it here too.
  if (form.processing) {
    return
  }

  form.items = [...expenseBudgetItems.value, ...incomeBudgetItems.value]

  form.post(budgetStore.url(), {
    onSuccess: (res) => {
      form.reset()
      toast.success(res.props.success as string)
    },
  })
}

const goBack = () => {
  window.history.back()
}

// Server errors land on the concatenated items array — map back to the row.
const itemError = (type: 'expense' | 'income', index: number) => {
  const flatIndex =
    type === 'expense' ? index : expenseBudgetItems.value.length + index

  return (form.errors as Record<string, string>)[
    `items.${flatIndex}.planned_amount`
  ]
}

// Top-level errors only — per-row items errors render under their row.
const topLevelErrors = computed(() =>
  Object.entries(form.errors)
    .filter(([key]) => !key.startsWith('items.'))
    .map(([, value]) => value),
)

const removeItem = (type: 'expense' | 'income', index: number) => {
  if (type === 'expense') {
    expenseBudgetItems.value.splice(index, 1)
  } else {
    incomeBudgetItems.value.splice(index, 1)
  }
}

const itemRowActions = (type: 'expense' | 'income', index: number) => [
  {
    label: __('delete'),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => removeItem(type, index),
  },
]
</script>

<template>
  <Head :title="__('create_data', { data: __('budget') })" />

  <AppContent>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <Heading
        :title="__('create_data', { data: __('budget') })"
        :description="__('budget_create_description')"
      />

      <form @submit.prevent="submit">
        <div class="space-y-4">
          <AlertError
            v-if="topLevelErrors.length > 0"
            :errors="topLevelErrors"
          />

          <div class="grid max-w-md grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label for="period_start">
                {{ __('period_start') }} <span class="text-destructive">*</span>
              </Label>
              <Input
                id="period_start"
                type="date"
                v-model="form.period_start"
                required
              />
            </div>

            <div class="grid gap-2">
              <Label for="period_end">
                {{ __('period_end') }} <span class="text-destructive">*</span>
              </Label>
              <Input
                id="period_end"
                type="date"
                v-model="form.period_end"
                required
              />
            </div>
          </div>

          <div class="grid max-w-md gap-2">
            <Label for="cutoff_day">
              {{ __('cutoff_day') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="cutoff_day"
              type="number"
              v-model.number="form.cutoff_day"
              min="1"
              max="31"
            />
          </div>

          <div class="grid max-w-md gap-2">
            <Label for="notes">
              {{ __('notes') }}
              <span class="text-muted-foreground">({{ __('optional') }})</span>
            </Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              :placeholder="__('notes')"
            />
          </div>

          <div class="flex items-center gap-2">
            <Checkbox
              id="carry_over"
              :checked="form.carry_over"
              @update:checked="onCarryOverChange"
            />
            <Label for="carry_over" class="cursor-pointer">
              {{ __('carry_over') }}
            </Label>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ __('carry_over_description') }}
          </p>
          <p
            v-if="form.carry_over && carryOverPreviewTotal > 0"
            class="text-sm font-medium text-primary"
          >
            {{
              __('carry_over_preview', {
                amount: formatNumber(carryOverPreviewTotal),
              })
            }}
          </p>
          <p v-else-if="form.carry_over" class="text-sm text-muted-foreground">
            {{ __('carry_over_no_previous') }}
          </p>

          <Separator />

          <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <Card>
              <CardHeader>
                <CardTitle>{{ __('monthly_expenses') }}</CardTitle>
                <CardAction>
                  <span class="font-bold text-destructive">
                    Rp {{ formatNumber(expenseTotal) }}
                  </span>
                </CardAction>
              </CardHeader>
              <CardContent>
                <ResponsiveTable
                  :columns="[
                    {
                      header: __('category'),
                      cell: (row) => row.category?.name ?? '',
                    },
                    {
                      header: __('planned'),
                      cell: () => '',
                      cellClass: 'w-[220px] text-end',
                    },
                    {
                      header: __('actions'),
                      cell: () => '',
                      cellClass: 'w-[80px] text-right',
                    },
                  ]"
                  :rows="expenseBudgetItems"
                >
                  <template #card="{ row, index }">
                    <Label class="mb-2 block">{{ row.category?.name }}</Label>
                    <NumberField
                      v-model="row.planned_amount"
                      :min="0"
                      :step="1000"
                      :step-snapping="false"
                      :format-options="{
                        style: 'currency',
                        currency: 'IDR',
                        currencyDisplay: 'narrowSymbol',
                        currencySign: 'standard',
                      }"
                    >
                      <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput />
                        <NumberFieldIncrement />
                      </NumberFieldContent>
                    </NumberField>
                    <InputError
                      :message="itemError('expense', index)"
                      class="mt-2"
                    />
                    <div class="mt-2 flex justify-end">
                      <RowActions :actions="itemRowActions('expense', index)" />
                    </div>
                  </template>

                  <template #cell-1="{ row, index }">
                    <NumberField
                      v-model="row.planned_amount"
                      :min="0"
                      :step="1000"
                      :step-snapping="false"
                      :format-options="{
                        style: 'currency',
                        currency: 'IDR',
                        currencyDisplay: 'narrowSymbol',
                        currencySign: 'standard',
                      }"
                    >
                      <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput />
                        <NumberFieldIncrement />
                      </NumberFieldContent>
                    </NumberField>
                    <InputError
                      :message="itemError('expense', index)"
                      class="mt-2"
                    />
                  </template>

                  <template #cell-2="{ index }">
                    <div class="flex items-center justify-end gap-2">
                      <RowActions :actions="itemRowActions('expense', index)" />
                    </div>
                  </template>
                </ResponsiveTable>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{{ __('monthly_incomes') }}</CardTitle>
                <CardAction>
                  <span class="font-bold text-destructive">
                    Rp {{ formatNumber(incomeTotal) }}
                  </span>
                </CardAction>
              </CardHeader>
              <CardContent>
                <ResponsiveTable
                  :columns="[
                    {
                      header: __('category'),
                      cell: (row) => row.category?.name ?? '',
                    },
                    {
                      header: __('planned'),
                      cell: () => '',
                      cellClass: 'w-[220px] text-end',
                    },
                    {
                      header: __('actions'),
                      cell: () => '',
                      cellClass: 'w-[80px] text-right',
                    },
                  ]"
                  :rows="incomeBudgetItems"
                >
                  <template #card="{ row, index }">
                    <Label class="mb-2 block">{{ row.category?.name }}</Label>
                    <NumberField
                      v-model="row.planned_amount"
                      :min="0"
                      :step="1000"
                      :step-snapping="false"
                      :format-options="{
                        style: 'currency',
                        currency: 'IDR',
                        currencyDisplay: 'narrowSymbol',
                        currencySign: 'standard',
                      }"
                    >
                      <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput />
                        <NumberFieldIncrement />
                      </NumberFieldContent>
                    </NumberField>
                    <InputError
                      :message="itemError('income', index)"
                      class="mt-2"
                    />
                    <div class="mt-2 flex justify-end">
                      <RowActions :actions="itemRowActions('income', index)" />
                    </div>
                  </template>

                  <template #cell-1="{ row, index }">
                    <NumberField
                      v-model="row.planned_amount"
                      :min="0"
                      :step="1000"
                      :step-snapping="false"
                      :format-options="{
                        style: 'currency',
                        currency: 'IDR',
                        currencyDisplay: 'narrowSymbol',
                        currencySign: 'standard',
                      }"
                    >
                      <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput />
                        <NumberFieldIncrement />
                      </NumberFieldContent>
                    </NumberField>
                    <InputError
                      :message="itemError('income', index)"
                      class="mt-2"
                    />
                  </template>

                  <template #cell-2="{ index }">
                    <div class="flex items-center justify-end gap-2">
                      <RowActions :actions="itemRowActions('income', index)" />
                    </div>
                  </template>
                </ResponsiveTable>
              </CardContent>
            </Card>
          </div>
        </div>

        <StickyFormActions
          :processing="form.processing"
          :submit-label="form.processing ? __('saving') : __('save')"
          show-cancel
          @cancel="goBack"
        />
      </form>
    </div>
  </AppContent>
</template>
