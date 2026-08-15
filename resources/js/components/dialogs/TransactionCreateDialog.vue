<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
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
import { Textarea } from '@/components/ui/textarea'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { store as storeTransaction } from '@/routes/transactions'
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

const form = useForm({
  balance_id: 0,
  budget_id: 0,
  budget_item_id: 0,
  category_id: 0,
  type: '',
  date: new Date().toISOString().split('T')[0],
  amount: 0,
  description: '',
})

const firstFieldRef = ref<HTMLElement | null>(null)

const onOpenAutoFocus = () => {
  nextTick(() => {
    firstFieldRef.value
      ?.querySelector<HTMLElement>(
        'input, textarea, [data-slot="select-trigger"]',
      )
      ?.focus()
  })
}

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

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.reset()
      form.clearErrors()

      if (props.primaryBalanceId) {
        form.balance_id = props.primaryBalanceId
      }

      if (props.activeBudgetId) {
        form.budget_id = props.activeBudgetId
      }

      form.date = new Date().toISOString().split('T')[0]
    }
  },
)

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
  form.post(storeTransaction.url(), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      form.reset()
      toast.success(res.props.success as string)
    },
    onError: () => {
      nextTick(() => {
        document.querySelector('[role="alert"]')?.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        })
      })
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent
      class="md:max-w-[425px]"
      @open-auto-focus.prevent="onOpenAutoFocus"
    >
      <DialogHeader>
        <DialogTitle>
          {{ __('add_data', { data: __('transaction') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('transaction_create_description') }}
        </DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="balance">
              {{ __('balance') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.balance_id" :disabled="form.processing">
              <SelectTrigger id="balance">
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
            <InputError :message="form.errors.balance_id" />
          </div>

          <div class="grid gap-2">
            <Label for="budget">
              {{ __('budget') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.budget_id" :disabled="form.processing">
              <SelectTrigger id="budget">
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
            <InputError :message="form.errors.budget_id" />
          </div>

          <div class="grid gap-2">
            <Label for="category">
              {{ __('category') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select
              v-model="form.category_id"
              :disabled="form.processing || !form.budget_id"
            >
              <SelectTrigger id="category">
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
            <InputError :message="form.errors.category_id" />
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
              :aria-invalid="form.errors.date ? true : undefined"
            />
            <InputError :message="form.errors.date" />
          </div>

          <div class="grid gap-2">
            <Label for="amount">
              {{ __('amount') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="amount"
              type="number"
              inputmode="decimal"
              pattern="[0-9]*[.,]?[0-9]*"
              v-model="form.amount"
              required
              placeholder="0"
              :disabled="form.processing"
              :aria-invalid="form.errors.amount ? true : undefined"
            />
            <InputError :message="form.errors.amount" />
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
            type="button"
            variant="outline"
            @click="$emit('update:open', false)"
            :disabled="form.processing"
          >
            {{ __('cancel') }}
          </Button>
          <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" class="mr-2" />
            {{ form.processing ? __('saving') : __('save') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
