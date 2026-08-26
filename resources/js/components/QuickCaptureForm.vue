<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useLang } from '@/composables/useLang'
import { useQuickLogPrefs } from '@/composables/useQuickLogPrefs'
import { parseQuickLogClient } from '@/lib/parseQuickLogClient'
import { store as storeTransaction } from '@/routes/transactions'
import type { Balance, Budget, Category } from '@/types'

const props = defineProps<{
  balances: Balance[]
  budgets: Budget[]
  categories: Category[]
  primaryBalanceId?: number
  activeBudgetId?: number
}>()

const { __ } = useLang()
const prefs = useQuickLogPrefs()

const quickText = ref('')
const pickedCategoryId = ref<number | null>(null)
const pickedBalanceId = ref<number | null>(null)
const amountOverride = ref<number | null>(null)

const parsed = computed(() => {
  return parseQuickLogClient(
    quickText.value,
    props.categories.map((c) => ({ id: c.id, name: c.name })),
    props.balances.map((b) => ({ id: b.id, name: b.name })),
    prefs.lastCategoryId.value,
    prefs.lastBalanceId.value,
    props.primaryBalanceId,
  )
})

// one-tap category takes precedence over parsed guess
const effectiveCategoryId = computed(
  () => pickedCategoryId.value ?? parsed.value.categoryId,
)
const effectiveBalanceId = computed(
  () => pickedBalanceId.value ?? parsed.value.balanceId,
)
const effectiveAmount = computed(
  () => amountOverride.value ?? parsed.value.amount,
)

const categoriesForBudget = computed(() => {
  const bid = props.activeBudgetId

  if (!bid) {
    return props.categories
  }

  const b = props.budgets.find((x) => x.id === bid)

  if (!b?.items?.length) {
    return props.categories
  }

  const ids = new Set(b.items.map((i) => i.category_id))

  return props.categories.filter((c) => ids.has(c.id))
})

watch(
  () => [props.primaryBalanceId, prefs.lastBalanceId.value] as const,
  () => {
    if (pickedBalanceId.value == null) {
      // keep staying null so effectiveBalanceId can resolve via parse; do not auto-set
    }
  },
)

function pickCategory(id: number) {
  pickedCategoryId.value = id
}

function onQuickTextInput() {
  // typing new free text clears the manual override so preview follows input
  pickedCategoryId.value = null
  // keep balance pick sticky across typing? Clear only if user explicitly changes.
}

const form = useForm({
  balance_id: 0 as number,
  budget_id: 0 as number,
  budget_item_id: 0 as number,
  category_id: 0 as number,
  type: '' as string,
  date: new Date().toISOString().split('T')[0],
  amount: 0 as number,
  description: '' as string,
})

function resolveBudgetLink(categoryId: number | null): {
  budget_id: number | null
  budget_item_id: number | null
  type: string
} {
  if (!categoryId || !props.activeBudgetId) {
    return {
      budget_id: props.activeBudgetId ?? (null as unknown as number),
      budget_item_id: null,
      type: '',
    }
  }

  const budget = props.budgets.find((b) => b.id === props.activeBudgetId)
  const item = budget?.items?.find((i) => i.category_id === categoryId)

  if (item) {
    return { budget_id: budget!.id, budget_item_id: item.id, type: item.type }
  }

  return { budget_id: props.activeBudgetId, budget_item_id: null, type: '' }
}

const canSubmit = computed(() => {
  const a = effectiveAmount.value
  const c = effectiveCategoryId.value
  const b = effectiveBalanceId.value

  return a != null && a > 0 && c != null && b != null
})

const submit = () => {
  const amount = effectiveAmount.value
  const categoryId = effectiveCategoryId.value
  const balanceId = effectiveBalanceId.value

  if (amount == null || !categoryId || !balanceId) {
    return
  }

  // also infer note: everything before the amount token, or parsed.note
  const note = parsed.value.note || quickText.value.trim()

  const link = resolveBudgetLink(categoryId)
  form.balance_id = balanceId
  form.budget_id = link.budget_id ?? 0
  form.budget_item_id = link.budget_item_id ?? 0
  form.category_id = categoryId
  form.type =
    link.type || (props.categories.find((c) => c.id === categoryId)?.type ?? '')
  form.amount = amount
  form.description = note
  form.date = new Date().toISOString().split('T')[0]

  form.post(storeTransaction.url(), {
    preserveScroll: true,
    onSuccess: (res) => {
      prefs.rememberFromIds(balanceId, categoryId)
      toast.success(res.props.success as string)
      // ready-state reset for fast batch logging: clear free-text + overrides, keep prefs
      quickText.value = ''
      pickedCategoryId.value = null
      pickedBalanceId.value = null
      amountOverride.value = null
      form.reset()
      form.clearErrors()
      form.date = new Date().toISOString().split('T')[0]
    },
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-2">
      <Label for="quick-log-input">Quick log</Label>
      <Input
        id="quick-log-input"
        v-model="quickText"
        data-testid="quick-log-input"
        placeholder='e.g. "bensin 33k cash"'
        autocomplete="off"
        @input="onQuickTextInput"
      />
      <p class="text-xs text-muted-foreground">
        Type: note + amount (supports k / rb) + optional balance name. Example:
        bensin 33k cash.
      </p>
    </div>

    <div class="grid gap-2">
      <Label>Category (one tap)</Label>
      <div class="flex flex-wrap gap-1.5" data-testid="quick-log-categories">
        <button
          v-for="c in categoriesForBudget"
          :key="c.id"
          type="button"
          class="rounded-full border px-3 py-1 text-xs transition"
          :class="
            effectiveCategoryId === c.id
              ? 'border-primary bg-primary text-primary-foreground'
              : 'bg-background hover:bg-muted'
          "
          :data-testid="`quick-cat-${c.id}`"
          :aria-pressed="effectiveCategoryId === c.id"
          @click="pickCategory(c.id)"
        >
          {{ c.name }}
        </button>
      </div>
      <p
        v-if="parsed.categoryId != null || effectiveCategoryId != null"
        class="text-xs text-muted-foreground"
      >
        Parsed:
        {{
          categories.find(
            (x) => x.id === (effectiveCategoryId ?? parsed.categoryId),
          )?.name ?? '—'
        }}
      </p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="grid gap-2">
        <Label for="quick-log-balance">Balance</Label>
        <Select
          v-model="pickedBalanceId as unknown as number"
          :disabled="form.processing"
        >
          <SelectTrigger id="quick-log-balance" data-testid="quick-log-balance">
            <SelectValue
              :placeholder="
                balances.find((b) => b.id === effectiveBalanceId)?.name ??
                'Select balance'
              "
            />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="b in balances" :key="b.id" :value="b.id">
              {{ b.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="parsed.balanceNameHint" class="text-xs text-muted-foreground">
          Hint: {{ parsed.balanceNameHint }}
        </p>
      </div>
      <div class="grid gap-2">
        <Label for="quick-log-amount">Amount</Label>
        <Input
          id="quick-log-amount"
          type="number"
          inputmode="decimal"
          data-testid="quick-log-amount"
          :model-value="effectiveAmount ?? ''"
          placeholder="0"
          @update:model-value="
            (v: string | number) =>
              (amountOverride = v === '' || v == null ? null : Number(v))
          "
        />
      </div>
    </div>

    <div class="flex justify-end">
      <Button
        data-testid="quick-log-submit"
        :disabled="!canSubmit || form.processing"
        @click="submit"
      >
        <Spinner v-if="form.processing" class="mr-2" />
        {{ form.processing ? __('saving') : __('save') }}
      </Button>
    </div>
  </div>
</template>
