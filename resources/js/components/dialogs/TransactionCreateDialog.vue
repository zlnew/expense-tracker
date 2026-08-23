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
import { useQuickLogPrefs } from '@/composables/useQuickLogPrefs'
import { parseQuickLogClient } from '@/lib/parseQuickLogClient'
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
const quickPrefs = useQuickLogPrefs()

// Quick-log state (spec §7.4 / §8): free-text line + one-tap category + last-used prefs.
// Prefills last-used balance from localStorage; balance/category selectors are one-tap via buttons/selects below.
const quickText = ref('')
const quickPickedCategoryId = ref<number | null>(null)
const quickAmountOverride = ref<number | null>(null)

const quickParsed = computed(() =>
  parseQuickLogClient(
    quickText.value,
    filteredCategories.value.map((c) => ({ id: c.id, name: c.name })),
    props.balances.map((b) => ({ id: b.id, name: b.name })),
    quickPrefs.lastCategoryId.value,
    quickPrefs.lastBalanceId.value,
    props.primaryBalanceId,
  ),
)
const quickEffectiveCategoryId = computed(() => quickPickedCategoryId.value ?? quickParsed.value.categoryId)
const quickEffectiveAmount = computed(() => quickAmountOverride.value ?? quickParsed.value.amount)
const quickEffectiveBalanceId = computed(() => quickParsed.value.balanceId)
const quickCanSubmit = computed(
  () => quickEffectiveAmount.value != null && quickEffectiveAmount.value > 0 && quickEffectiveCategoryId.value != null && quickEffectiveBalanceId.value != null,
)

function pickQuickCategory(id: number) {
  quickPickedCategoryId.value = id
  form.category_id = id
  // keep budget linkage in sync (watch on [budget,category] will set item/type)
}
function onQuickTextInput() {
  quickPickedCategoryId.value = null
}

const submitQuick = () => {
  const amount = quickEffectiveAmount.value
  const categoryId = quickEffectiveCategoryId.value
  const balanceId = quickEffectiveBalanceId.value
  if (amount == null || !categoryId || !balanceId) return
  const linkBudget = props.budgets.find((b) => b.id === props.activeBudgetId) ?? props.budgets.find((b) => b.id === form.budget_id)
  const item = linkBudget?.items?.find((i) => i.category_id === categoryId)
  form.balance_id = balanceId
  if (props.activeBudgetId) form.budget_id = props.activeBudgetId
  if (item) {
    form.budget_item_id = item.id
    form.type = item.type
  } else {
    const cat = filteredCategories.value.find((c) => c.id === categoryId) ?? props.categories.find((c) => c.id === categoryId)
    if (cat) form.type = (cat as Category & { type: string }).type ?? ''
  }
  form.category_id = categoryId
  form.amount = amount
  form.description = quickParsed.value.note || quickText.value.trim()
  form.date = new Date().toISOString().split('T')[0]
  form.post(storeTransaction.url(), {
    preserveScroll: true,
    onSuccess: (res) => {
      quickPrefs.rememberFromIds(balanceId, categoryId)
      toast.success(res.props.success as string)
      // batch-ready reset: clear quick line + overrides, keep prefs for next entry; dialog stays open
      quickText.value = ''
      quickPickedCategoryId.value = null
      quickAmountOverride.value = null
      form.reset()
      form.clearErrors()
      if (props.primaryBalanceId) form.balance_id = props.primaryBalanceId
      if (props.activeBudgetId) form.budget_id = props.activeBudgetId
      form.date = new Date().toISOString().split('T')[0]
      nextTick(() => {
        const el = firstFieldRef.value?.querySelector<HTMLElement>('#quick-log-input')
        el?.focus()
      })
    },
  })
}

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

      // Quick-log prefs (localStorage) win over server primary — spec §11.2
      const lastBal = quickPrefs.lastBalanceId.value
      const lastCat = quickPrefs.lastCategoryId.value
      if (lastBal != null && props.balances.some((b) => b.id === lastBal)) {
        form.balance_id = lastBal
      } else if (props.primaryBalanceId) {
        form.balance_id = props.primaryBalanceId
      }

      if (props.activeBudgetId) {
        form.budget_id = props.activeBudgetId
      }

      // prefill quick category from last-used if valid for the active budget
      quickText.value = ''
      quickPickedCategoryId.value = null
      quickAmountOverride.value = null
      if (lastCat != null) {
        // hint the quick strip; full validation happens in submit/quick pick
        quickPickedCategoryId.value = null
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
      // persist last-used for quick-log prefill (v3 §11.2)
      if (form.balance_id) quickPrefs.rememberFromIds(Number(form.balance_id), Number(form.category_id) || null)
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

          <!-- Quick-log strip (US-5): free text + one-tap category + batch-ready -->
          <div class="rounded-lg border bg-muted/30 p-3 space-y-3" data-testid="quick-log-strip">
            <div class="grid gap-2">
              <Label for="quick-log-input">Quick log</Label>
              <Input
                id="quick-log-input"
                v-model="quickText"
                data-testid="quick-log-input"
                placeholder='e.g. &quot;bensin 33k cash&quot;'
                autocomplete="off"
                @input="onQuickTextInput"
              />
              <p class="text-xs text-muted-foreground">Type: note + amount (supports k / rb) + optional balance name.</p>
              <p v-if="quickParsed.balanceNameHint" class="text-xs text-muted-foreground" data-testid="quick-log-balance-hint">
                Balance hint: {{ quickParsed.balanceNameHint }}
              </p>
            </div>
            <div class="grid gap-2">
              <Label>Category (one tap)</Label>
              <div class="flex flex-wrap gap-1.5" data-testid="quick-log-categories">
                <button
                  v-for="c in (filteredCategories.length ? filteredCategories : categories)"
                  :key="c.id"
                  type="button"
                  class="rounded-full border px-3 py-1 text-xs transition"
                  :class="quickEffectiveCategoryId === c.id ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted'"
                  :data-testid="`quick-cat-${c.id}`"
                  :aria-pressed="quickEffectiveCategoryId === c.id"
                  @click="pickQuickCategory(c.id)"
                >
                  {{ c.name }}
                </button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="grid gap-2">
                <Label for="quick-log-amount">Amount (preview)</Label>
                <Input
                  id="quick-log-amount"
                  type="number"
                  inputmode="decimal"
                  data-testid="quick-log-amount"
                  :model-value="(quickEffectiveAmount ?? '') as unknown as string"
                  placeholder="0"
                  @update:model-value="(v: string | number) => (quickAmountOverride = v === '' || (v as unknown) == null ? null : Number(v))"
                />
              </div>
              <div class="flex items-end">
                <Button type="button" data-testid="quick-log-submit" class="w-full" :disabled="!quickCanSubmit || (form as unknown as { processing: boolean }).processing" @click="submitQuick">
                  <Spinner v-if="(form as unknown as { processing: boolean }).processing" class="mr-2" />
                  Quick save
                </Button>
              </div>
            </div>
          </div>

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
