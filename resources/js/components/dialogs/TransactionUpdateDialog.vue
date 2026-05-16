<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import { Textarea } from '@/components/ui/textarea'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { update as updateTransaction } from '@/routes/transactions'
import type { Balance, Budget, Category, Transaction } from '@/types'

const props = defineProps<{
  open: boolean
  transaction: Transaction | null
  balances: Balance[]
  budgets: Budget[]
  categories: Category[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()
const { formatDate } = useDate()

const form = useForm({
  balance_id: 0,
  budget_id: undefined as number | undefined,
  budget_item_id: undefined as number | undefined,
  category_id: undefined as number | undefined,
  type: '',
  date: '',
  amount: 0,
  description: '',
})

const selectedBudget = computed(() => {
  return props.budgets.find((b) => b.id === form.budget_id)
})

const filteredCategories = computed(() => {
  if (!selectedBudget.value) {
    return []
  }

  return selectedBudget.value.items
    ?.map((item) => item.category)
    .filter(Boolean) as Category[]
})

const groupedCategories = computed(() => {
  const items = filteredCategories.value

  const groups: Record<string, Category[]> = {
    income: [],
    expense: [],
  }

  items.forEach((c) => {
    if (c.type === 'income') {
      groups.income.push(c)
    } else if (c.type === 'expense') {
      groups.expense.push(c)
    }
  })

  return groups
})

watch([() => props.transaction, () => props.open], ([val, isOpen]) => {
  if (isOpen && val) {
    form.balance_id = val.balance_id

    if (val.budget_id) {
      form.budget_id = val.budget_id
    }

    if (val.budget_item_id) {
      form.budget_item_id = val.budget_item_id
    }

    if (val.category_id) {
      form.category_id = val.category_id
    }

    form.type = val.type
    form.date = val.date
    form.amount = val.amount
    form.description = val.description ?? ''
  }
})

watch(
  () => [form.budget_id, form.category_id],
  ([budgetId, categoryId]) => {
    if (budgetId && categoryId) {
      const budget = props.budgets.find((b) => b.id === budgetId)
      const item = budget?.items?.find((i) => i.category_id === categoryId)

      if (item) {
        form.budget_item_id = item.id
        form.type = item.type
      }
    }
  },
)

const submit = () => {
  if (!props.transaction) {
    return
  }

  form.put(updateTransaction.url({ transaction: props.transaction }), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      toast.success(res.props.success as string)
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ __('edit_data', { data: __('transaction') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('transaction_update_description') }}
        </DialogDescription>
      </DialogHeader>

      <AlertError
        v-if="Object.keys(form.errors).length > 0"
        :errors="Object.values(form.errors)"
      />

      <div class="grid gap-4 py-4">
        <div class="grid gap-2">
          <Label>
            {{ __('balance') }}
            <span class="text-destructive">*</span>
          </Label>
          <Select v-model="form.balance_id" :disabled="form.processing">
            <SelectTrigger>
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
        </div>

        <div v-if="form.budget_id" class="grid gap-2">
          <Label>
            {{ __('budget') }}
            <span class="text-destructive">*</span>
          </Label>
          <Select v-model="form.budget_id" :disabled="form.processing">
            <SelectTrigger>
              <SelectValue
                :placeholder="__('select_data', { data: __('budget') })"
              />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="b in budgets" :key="b.id" :value="b.id">
                {{ formatDate(b.period_start, 'DD MMM YYYY') }} -
                {{ formatDate(b.period_end, 'DD MMM YYYY') }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div v-if="form.category_id" class="grid gap-2">
          <Label>
            {{ __('category') }}
            <span class="text-destructive">*</span>
          </Label>
          <Select
            v-model="form.category_id"
            :disabled="form.processing || !form.budget_id"
          >
            <SelectTrigger>
              <span v-if="form.type" class="text-muted-foreground">
                {{ __(form.type) }}
              </span>
              <SelectValue
                :placeholder="__('select_data', { data: __('category') })"
              />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup v-if="groupedCategories.expense.length > 0">
                <SelectLabel>{{ __('expense') }}</SelectLabel>
                <SelectItem
                  v-for="c in groupedCategories.expense"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </SelectItem>
              </SelectGroup>
              <SelectGroup v-if="groupedCategories.income.length > 0">
                <SelectLabel>{{ __('income') }}</SelectLabel>
                <SelectItem
                  v-for="c in groupedCategories.income"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </SelectItem>
              </SelectGroup>
              <div
                v-if="
                  groupedCategories.income.length === 0 &&
                  groupedCategories.expense.length === 0
                "
                class="p-4 text-center text-sm text-muted-foreground"
              >
                {{ __('no_data_found', { data: __('category') }) }}
              </div>
            </SelectContent>
          </Select>
        </div>

        <div class="grid gap-2">
          <Label for="date">
            {{ __('date') }}
            <span class="text-destructive">*</span>
          </Label>
          <Input
            id="date"
            type="date"
            v-model="form.date"
            required
            :disabled="form.processing"
          />
        </div>

        <div class="grid gap-2">
          <Label for="amount">
            {{ __('amount') }}
            <span class="text-destructive">*</span>
          </Label>
          <Input
            id="amount"
            type="number"
            v-model="form.amount"
            required
            placeholder="0"
            :disabled="form.processing"
          />
        </div>

        <div class="grid gap-2">
          <Label for="description">
            {{ __('description') }}
            <span class="text-muted-foreground">({{ __('optional') }})</span>
          </Label>
          <Textarea
            id="description"
            v-model="form.description"
            :placeholder="__('description')"
            :disabled="form.processing"
          />
        </div>
      </div>

      <DialogFooter>
        <Button
          variant="outline"
          @click="$emit('update:open', false)"
          :disabled="form.processing"
        >
          {{ __('cancel') }}
        </Button>
        <Button @click="submit" :disabled="form.processing">
          {{ form.processing ? __('updating') : __('update') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
