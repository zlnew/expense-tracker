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
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import funds from '@/routes/funds'
import type { Balance, SinkingFund } from '@/types'

const props = defineProps<{
  open: boolean
  fund: SinkingFund | null
  balances: Balance[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()

const form = useForm({
  amount: 0,
  balance_id: 0,
  date: new Date().toISOString().split('T')[0],
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

const primaryBalanceId = computed(
  () => props.balances.find((b) => b.is_primary)?.id ?? 0,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen || !props.fund) {
      return
    }

    // Prefill with the accumulated reserve; the user confirms the amount.
    form.reset()
    form.clearErrors()
    form.amount = props.fund.accumulated
    form.balance_id = primaryBalanceId.value
    form.date = new Date().toISOString().split('T')[0]
  },
)

const submit = () => {
  if (!props.fund) {
    return
  }

  form.post(funds.withdrawals.store.url({ fund: props.fund }), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      form.reset()
      toast.success(
        (res.props.flash as any)?.success ??
          __('created_data', { data: __('fund_withdrawal') }),
      )
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
        <DialogTitle>{{ __('pay_from_fund') }}</DialogTitle>
        <DialogDescription>
          {{ __('pay_from_fund_description', { fund: fund?.name ?? '' }) }}
        </DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="pay_amount">
              {{ __('amount') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="pay_amount"
              v-model="form.amount"
              type="number"
              inputmode="numeric"
              min="1"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.amount ? true : undefined"
            />
            <InputError :message="form.errors.amount" />
            <p class="text-xs text-muted-foreground">
              {{
                __('available_reserve', {
                  amount: formatAmount(fund?.accumulated ?? 0),
                })
              }}
            </p>
          </div>

          <div class="grid gap-2">
            <Label for="pay_balance">
              {{ __('balance') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.balance_id" :disabled="form.processing">
              <SelectTrigger id="pay_balance">
                <SelectValue
                  :placeholder="__('select_data', { data: __('balance') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem v-for="b in balances" :key="b.id" :value="b.id">
                    {{ b.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.balance_id" />
          </div>

          <div class="grid gap-2">
            <Label for="pay_date">
              {{ __('date') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="pay_date"
              v-model="form.date"
              type="date"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.date ? true : undefined"
            />
            <InputError :message="form.errors.date" />
          </div>

          <div class="grid gap-2">
            <Label for="pay_description">{{ __('description') }}</Label>
            <Input
              id="pay_description"
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
            {{ form.processing ? __('saving') : __('pay_from_fund') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
