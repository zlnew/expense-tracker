<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Plus, Trash2 } from 'lucide-vue-next'
import type { AcceptableValue } from 'reka-ui'
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import SheetDialogContent from '@/components/ui/dialog-sheet.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { bulkStore as storeTransactions } from '@/routes/transactions'
import type { Balance, Budget, Category } from '@/types'

const props = defineProps<{
  open: boolean
  balances: Balance[]
  budgets: Budget[]
  categories: Category[]
  primaryBalanceId?: number
  activeBudgetId?: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatNumber } = useNumber()

const form = useForm({
  items: [
    {
      balance_id: props.primaryBalanceId || '',
      budget_id: props.activeBudgetId || '',
      budget_item_id: '',
      category_id: '',
      type: '',
      date: new Date().toISOString().split('T')[0],
      amount: 0,
      description: '',
    },
  ],
})

// form.errors is keyed by dotted path ('items.0.balance_id'); the helper
// keeps the string index access in one typed place instead of ~12 casts.
function errorFor(path: string): string | undefined {
  return (form.errors as Record<string, string | undefined>)[path]
}

const firstFieldRef = ref<ComponentPublicInstance | null>(null)

const setFirstFieldRef = (el: Element | ComponentPublicInstance | null) => {
  firstFieldRef.value = el as ComponentPublicInstance | null
}

const addItem = () => {
  const lastItem = form.items[form.items.length - 1]
  form.items.push({
    balance_id: lastItem?.balance_id || props.primaryBalanceId || '',
    budget_id: lastItem?.budget_id || props.activeBudgetId || '',
    budget_item_id: '',
    category_id: '',
    type: '',
    date: lastItem?.date || new Date().toISOString().split('T')[0],
    amount: 0,
    description: '',
  })
}

const removeItem = (index: number) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

const getGroupedCategories = (budgetId: string | number) => {
  const budget = props.budgets.find(
    (b) => b.id.toString() === budgetId.toString(),
  )

  if (!budget) {
    return { income: [], expense: [] }
  }

  const items = budget.items || []
  const groups: Record<string, Category[]> = {
    income: [],
    expense: [],
  }

  items.forEach((item) => {
    if (item.category) {
      if (item.type === 'income') {
        groups.income.push(item.category)
      } else {
        groups.expense.push(item.category)
      }
    }
  })

  return groups
}

const onCategoryChange = (index: number, categoryId: AcceptableValue) => {
  const item = form.items[index]
  const budget = props.budgets.find(
    (b) => b.id.toString() === item.budget_id.toString(),
  )
  const budgetItem = budget?.items?.find(
    (i) => i.category_id.toString() === categoryId?.toString(),
  )

  if (budgetItem) {
    item.budget_item_id = budgetItem.id.toString()
    item.type = budgetItem.type
  }
}

const totalExpense = computed(() =>
  form.items
    .filter((item) => item.type === 'expense')
    .reduce((acc, item) => acc + (Number(item.amount) || 0), 0),
)

const totalIncome = computed(() =>
  form.items
    .filter((item) => item.type === 'income')
    .reduce((acc, item) => acc + (Number(item.amount) || 0), 0),
)

const submit = () => {
  form.post(storeTransactions.url(), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      form.reset()
      toast.success(
        (res.props.flash as any)?.success ??
          __('updated_data', { data: __('transactions') }),
      )
    },
    onError: () => {
      nextTick(() => {
        document
          .querySelector('[aria-invalid="true"]')
          ?.scrollIntoView({ block: 'center', behavior: 'smooth' })
      })
    },
  })
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      form.reset()
      form.clearErrors()
      form.items = [
        {
          balance_id: props.primaryBalanceId || '',
          budget_id: props.activeBudgetId || '',
          budget_item_id: '',
          category_id: '',
          type: '',
          date: new Date().toISOString().split('T')[0],
          amount: 0,
          description: '',
        },
      ]

      nextTick(() => {
        ;(firstFieldRef.value?.$el as HTMLElement | undefined)?.focus()
      })
    }
  },
)
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent
      class="sm:max-w-2xl md:max-w-4xl"
      @open-auto-focus.prevent
    >
      <DialogHeader>
        <DialogTitle>{{ __('multiple_transactions') }}</DialogTitle>
        <DialogDescription>
          {{ __('transaction_bulk_create_description') }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit">
        <AlertError
          v-if="Object.keys(form.errors).length > 0"
          :errors="Object.values(form.errors)"
        />

        <div class="mt-4 space-y-4">
          <div
            v-for="(item, index) in form.items"
            :key="index"
            class="relative flex flex-col gap-4 rounded-lg border bg-card p-4 shadow-sm transition-all hover:border-primary/50 lg:grid lg:grid-cols-4"
          >
            <Button
              v-if="form.items.length > 1"
              type="button"
              variant="ghost"
              size="icon"
              class="absolute top-2 right-2 z-10 text-destructive hover:bg-destructive/10"
              :disabled="form.processing"
              :aria-label="__('delete_data', { data: __('item') })"
              @click="removeItem(index)"
            >
              <Trash2 class="size-4" />
            </Button>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-1">
              <div class="space-y-2">
                <Label>{{ __('balance') }}</Label>
                <Select v-model="item.balance_id" :disabled="form.processing">
                  <SelectTrigger
                    :ref="index === 0 ? setFirstFieldRef : undefined"
                    :aria-invalid="!!errorFor('items.' + index + '.balance_id')"
                  >
                    <SelectValue
                      :placeholder="__('select_data', { data: __('balance') })"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="b in balances" :key="b.id" :value="b.id">
                      {{ b.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError
                  :message="errorFor('items.' + index + '.balance_id')"
                />
              </div>

              <div class="space-y-2">
                <Label>{{ __('budget') }}</Label>
                <Select v-model="item.budget_id" :disabled="form.processing">
                  <SelectTrigger
                    :aria-invalid="!!errorFor('items.' + index + '.budget_id')"
                  >
                    <SelectValue
                      :placeholder="__('select_data', { data: __('budget') })"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="b in budgets" :key="b.id" :value="b.id">
                      {{ formatDate(b.period_start, 'DD MMM YYYY') }}
                      -
                      {{ formatDate(b.period_end, 'DD MMM YYYY') }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError
                  :message="errorFor('items.' + index + '.budget_id')"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
              <div class="space-y-2">
                <Label>{{ __('category') }}</Label>
                <Select
                  v-model="item.category_id"
                  @update:model-value="onCategoryChange(index, $event)"
                  :disabled="form.processing || !item.budget_id"
                >
                  <SelectTrigger
                    :aria-invalid="
                      !!errorFor('items.' + index + '.category_id')
                    "
                  >
                    <span v-if="item.type" class="text-muted-foreground">
                      {{ __(item.type) }}
                    </span>
                    <SelectValue
                      :placeholder="__('select_data', { data: __('category') })"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup
                      v-if="
                        getGroupedCategories(item.budget_id).expense.length > 0
                      "
                    >
                      <SelectLabel>{{ __('expense') }}</SelectLabel>
                      <SelectItem
                        v-for="c in getGroupedCategories(item.budget_id)
                          .expense"
                        :key="c.id"
                        :value="c.id"
                      >
                        {{ c.name }}
                      </SelectItem>
                    </SelectGroup>
                    <SelectGroup
                      v-if="
                        getGroupedCategories(item.budget_id).income.length > 0
                      "
                    >
                      <SelectLabel>{{ __('income') }}</SelectLabel>
                      <SelectItem
                        v-for="c in getGroupedCategories(item.budget_id).income"
                        :key="c.id"
                        :value="c.id"
                      >
                        {{ c.name }}
                      </SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
                <InputError
                  :message="errorFor('items.' + index + '.category_id')"
                />
              </div>

              <div class="space-y-2">
                <Label>{{ __('date') }}</Label>
                <Input
                  type="date"
                  v-model="item.date"
                  :disabled="form.processing"
                  :aria-invalid="!!errorFor('items.' + index + '.date')"
                />
                <InputError :message="errorFor('items.' + index + '.date')" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>{{ __('amount') }}</Label>
              <Input
                type="number"
                inputmode="decimal"
                pattern="[0-9]*[.,]?[0-9]*"
                v-model="item.amount"
                placeholder="0"
                :disabled="form.processing"
                :aria-invalid="!!errorFor('items.' + index + '.amount')"
              />
              <InputError :message="errorFor('items.' + index + '.amount')" />
            </div>

            <div class="space-y-2 sm:col-span-2 lg:col-span-1">
              <Label>{{ __('description') }}</Label>
              <Input
                v-model="item.description"
                :placeholder="__('description')"
                :disabled="form.processing"
                :aria-invalid="!!errorFor('items.' + index + '.description')"
              />
              <InputError
                :message="errorFor('items.' + index + '.description')"
              />
            </div>
          </div>
        </div>

        <div
          class="mt-4 flex flex-col gap-4 border-t pt-4 lg:flex-row lg:items-center lg:justify-between"
        >
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="form.processing"
            @click="addItem"
            class="w-full sm:w-auto"
          >
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('item') }) }}
          </Button>

          <div
            class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm sm:flex-nowrap"
          >
            <div class="flex items-center gap-2">
              <span class="text-muted-foreground"
                >{{ __('total_items') }}:</span
              >
              <span class="font-bold">{{ form.items.length }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-muted-foreground"
                >{{ __('total_expense') }}:</span
              >
              <span class="font-bold text-red-600 dark:text-red-400">
                Rp {{ formatNumber(totalExpense) }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-muted-foreground"
                >{{ __('total_income') }}:</span
              >
              <span class="font-bold text-green-600 dark:text-green-400">
                Rp {{ formatNumber(totalIncome) }}
              </span>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            @click="$emit('update:open', false)"
            :disabled="form.processing"
          >
            {{ __('cancel') }}
          </Button>
          <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" class="size-4" />
            {{ form.processing ? __('saving') : __('save') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
