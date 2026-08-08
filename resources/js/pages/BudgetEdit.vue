<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
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
import { index as budgetIndex, update as budgetUpdate } from '@/routes/budgets'
import type { Budget, BudgetItem, Category } from '@/types'

const props = defineProps<{
  budget: Budget
  categories: Category[]
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
      title: __('edit'),
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
  form.period_start = props.budget.period_start
  form.period_end = props.budget.period_end
  form.cutoff_day = props.budget.cutoff_day
  form.notes = props.budget.notes
  form.carry_over = props.budget.carry_over

  initBudgetItems()
})

const onCarryOverChange = (value: boolean | 'indeterminate') => {
  form.carry_over = value === true
}

const initBudgetItems = () => {
  expenseCategories.value.forEach((ec) => {
    const exist = props.budget.expenses?.find((e) => e.category_id === ec.id)

    if (exist) {
      expenseBudgetItems.value.push(exist)

      return
    }

    expenseBudgetItems.value.push({
      budget_id: props.budget.id,
      category_id: ec.id,
      type: ec.type,
      planned_amount: 0,
      category: ec,
    })
  })

  incomeCategories.value.forEach((ic) => {
    const exist = props.budget.incomes?.find((e) => e.category_id === ic.id)

    if (exist) {
      incomeBudgetItems.value.push(exist)

      return
    }

    incomeBudgetItems.value.push({
      budget_id: props.budget.id,
      category_id: ic.id,
      type: ic.type,
      planned_amount: 0,
      category: ic,
    })
  })
}

// items.N.planned_amount rules arrive flat in form.errors; map them back to
// the offending row so the error renders under its input, not just in the
// AlertError banner. The submitted items array is expenses + incomes, so
// income rows are offset by the expense row count.
function rowError(index: number, offset: number): string | undefined {
  const key = `items.${offset + index}.planned_amount`
  const error = (form.errors as Record<string, string | string[] | undefined>)[
    key
  ]

  return Array.isArray(error) ? error[0] : error
}

const submit = () => {
  form.items = [...expenseBudgetItems.value, ...incomeBudgetItems.value]

  form.put(budgetUpdate.url({ budget: props.budget }), {
    onSuccess: (res) => {
      form.reset()
      toast.success(res.props.success as string)
    },
  })
}
</script>

<template>
  <Head :title="__('edit_data', { data: __('budget') })" />

  <div>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <Heading
        :title="__('edit_data', { data: __('budget') })"
        :description="__('budget_update_description')"
      />

      <form @submit.prevent="submit">
        <div class="space-y-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
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
                      cell: (row) => row.category?.name ?? '-',
                    },
                    {
                      header: __('planned'),
                      cell: () => '',
                      cellClass: 'w-[300px]',
                    },
                  ]"
                  :rows="expenseBudgetItems"
                >
                  <template #card="{ row: exp, index }">
                    <Label
                      class="mb-2 block"
                      :class="rowError(index, 0) ? 'text-destructive' : ''"
                    >
                      {{ exp.category?.name }}
                    </Label>
                    <NumberField
                      v-model="exp.planned_amount"
                      :min="0"
                      :step="1000"
                      :stepSnapping="false"
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
                    <p
                      v-if="rowError(index, 0)"
                      class="mt-1.5 text-xs font-medium text-destructive"
                    >
                      {{ rowError(index, 0) }}
                    </p>
                  </template>

                  <template #cell-1="{ row: exp, index }">
                    <div class="flex flex-col items-end gap-1">
                      <NumberField
                        v-model="exp.planned_amount"
                        :min="0"
                        :step="1000"
                        :stepSnapping="false"
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
                      <p
                        v-if="rowError(index, 0)"
                        class="text-xs font-medium text-destructive"
                      >
                        {{ rowError(index, 0) }}
                      </p>
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
                      cell: (row) => row.category?.name ?? '-',
                    },
                    {
                      header: __('planned'),
                      cell: () => '',
                      cellClass: 'w-[300px]',
                    },
                  ]"
                  :rows="incomeBudgetItems"
                >
                  <template #card="{ row: inc, index }">
                    <Label
                      class="mb-2 block"
                      :class="
                        rowError(index, expenseBudgetItems.length)
                          ? 'text-destructive'
                          : ''
                      "
                    >
                      {{ inc.category?.name }}
                    </Label>
                    <NumberField
                      v-model="inc.planned_amount"
                      :min="0"
                      :step="1000"
                      :stepSnapping="false"
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
                    <p
                      v-if="rowError(index, expenseBudgetItems.length)"
                      class="mt-1.5 text-xs font-medium text-destructive"
                    >
                      {{ rowError(index, expenseBudgetItems.length) }}
                    </p>
                  </template>

                  <template #cell-1="{ row: inc, index }">
                    <div class="flex flex-col items-end gap-1">
                      <NumberField
                        v-model="inc.planned_amount"
                        :min="0"
                        :step="1000"
                        :stepSnapping="false"
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
                      <p
                        v-if="rowError(index, expenseBudgetItems.length)"
                        class="text-xs font-medium text-destructive"
                      >
                        {{ rowError(index, expenseBudgetItems.length) }}
                      </p>
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
          :show-cancel="true"
          :cancel-href="budgetIndex.url()"
        />
      </form>
    </div>
  </div>
</template>
