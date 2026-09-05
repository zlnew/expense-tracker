<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Trash2 } from 'lucide-vue-next'
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
  delete: [transaction: Transaction]
}>()

function onDeleteClick() {
  if (props.transaction) {
    emit('update:open', false)
    emit('delete', props.transaction)
  }
}

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

watch([() => props.transaction, () => props.open], ([val, isOpen]) => {
  if (isOpen && val) {
    form.clearErrors()
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
          {{ __('edit_data', { data: __('transaction') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('transaction_update_description') }}
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

          <div v-if="form.budget_id" class="grid gap-2">
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

          <div v-if="form.category_id" class="grid gap-2">
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

        <DialogFooter
          class="flex w-full flex-col-reverse items-center gap-2 pt-2 sm:flex-row sm:justify-between"
        >
          <Button
            v-if="transaction"
            type="button"
            variant="destructive"
            class="w-full rounded-none font-mono text-xs sm:w-auto"
            @click="onDeleteClick"
            :disabled="form.processing"
          >
            <Trash2 class="mr-1.5 size-3.5" />
            {{ __('delete') }}
          </Button>
          <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
            <Button
              type="button"
              variant="outline"
              class="rounded-none font-mono text-xs"
              @click="$emit('update:open', false)"
              :disabled="form.processing"
            >
              {{ __('cancel') }}
            </Button>
            <Button
              type="submit"
              class="rounded-none font-mono text-xs"
              :disabled="form.processing"
            >
              <Spinner v-if="form.processing" class="mr-2" />
              {{ form.processing ? __('updating') : __('update') }}
            </Button>
          </div>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
