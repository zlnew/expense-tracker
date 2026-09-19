<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
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
import { Spinner } from '@/components/ui/spinner'
import { getLocalDateString } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { reconcile as reconcileRoute } from '@/routes/balances'
import type { Balance } from '@/types'

const props = defineProps<{ balance: Balance | null }>()
const open = defineModel<boolean>('open', { required: true })

const { __ } = useLang()
const { formatAmount } = useNumber()

const amount = ref<string>('')
const reconcileDate = ref<string>('')
const autoAdjust = ref(false)
const submitting = ref(false)
const firstFieldRef = ref<HTMLElement | null>(null)

const liveDrift = computed(() => {
  if (!props.balance || amount.value === '') {
    return null
  }

  const inputVal = Number(amount.value)

  if (Number.isNaN(inputVal)) {
    return null
  }

  return (props.balance.final_amount ?? 0) - inputVal
})

const errors = ref<Record<string, string>>({})

function resetForm(balance: Balance | null) {
  amount.value =
    balance?.reconciled_amount != null
      ? String(balance.reconciled_amount)
      : balance != null
        ? String(balance.final_amount)
        : '0'
  reconcileDate.value = balance?.reconciled_at ?? getLocalDateString()
  autoAdjust.value = false
  errors.value = {}
}

watch(
  () => props.balance,
  (b) => {
    if (open.value && b) {
      resetForm(b)
    }
  },
)

watch(open, (isOpen) => {
  if (isOpen) {
    resetForm(props.balance)
    nextTick(() => {
      firstFieldRef.value
        ?.querySelector<HTMLElement>(
          'input, textarea, [data-slot="select-trigger"]',
        )
        ?.focus()
    })
  }
})

function submit() {
  if (!props.balance) {
    return
  }

  const reconciledAmount = Number(amount.value)

  if (Number.isNaN(reconciledAmount)) {
    toast.error(__('validation_error'))

    return
  }

  if (!reconcileDate.value) {
    toast.error(__('validation_error'))

    return
  }

  submitting.value = true
  errors.value = {}

  router.post(
    reconcileRoute.url({ balance: props.balance }),
    {
      reconciled_amount: reconciledAmount,
      reconciled_at: reconcileDate.value,
      auto_adjust: autoAdjust.value,
    },
    {
      preserveScroll: true,
      onSuccess: (res) => {
        toast.success(
          (res.props.flash as any)?.success ??
            __('updated_data', { data: __('balance') }),
        )
        open.value = false
      },
      onError: (errs: Record<string, string>) => {
        errors.value = errs ?? {}
        toast.error(__('validation_error'))
        nextTick(() => {
          document
            .querySelector('[role="alert"]')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        })
      },
      onFinish: () => {
        submitting.value = false
      },
    },
  )
}

function onOpenAutoFocus() {
  nextTick(() => {
    firstFieldRef.value
      ?.querySelector<HTMLElement>(
        'input, textarea, [data-slot="select-trigger"]',
      )
      ?.focus()
  })
}
</script>

<template>
  <Dialog v-model:open="open">
    <SheetDialogContent
      class="md:max-w-[425px]"
      @open-auto-focus.prevent="onOpenAutoFocus"
    >
      <DialogHeader>
        <DialogTitle>{{ __('reconcile_balance') }}</DialogTitle>
        <DialogDescription>{{
          __('reconcile_balance_description')
        }}</DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(errors).length > 0"
            :errors="Object.values(errors)"
          />

          <div class="grid gap-2">
            <Label for="reconciled_amount">
              {{ __('reconciled_amount') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="reconciled_amount"
              v-model="amount"
              type="number"
              inputmode="numeric"
              :disabled="submitting"
            />
          </div>

          <div class="grid gap-2">
            <Label for="reconciled_at">
              {{ __('reconciled_at') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="reconciled_at"
              v-model="reconcileDate"
              type="date"
              :disabled="submitting"
            />
          </div>

          <!-- Live Drift & Ledger Preview -->
          <div
            v-if="props.balance"
            class="space-y-2 rounded-none border border-border bg-secondary/30 p-3 font-mono text-xs"
          >
            <div
              class="flex items-center justify-between text-muted-foreground"
            >
              <span>{{ __('recorded_balance') }}</span>
              <span class="font-bold text-foreground">
                {{ formatAmount(props.balance.final_amount ?? 0) }}
              </span>
            </div>
            <div
              v-if="liveDrift !== null"
              class="flex items-center justify-between border-t border-border/50 pt-1"
            >
              <span>{{ __('drift') }}</span>
              <span
                class="font-bold tabular-nums"
                :class="
                  Math.abs(liveDrift) > 500
                    ? 'text-rose-400'
                    : 'text-emerald-400'
                "
              >
                {{
                  Math.abs(liveDrift) <= 500
                    ? '✅ ' + (liveDrift === 0 ? '0' : formatAmount(liveDrift))
                    : '⚠️ ' + formatAmount(liveDrift)
                }}
              </span>
            </div>
            <p
              v-if="liveDrift !== null && Math.abs(liveDrift) > 500"
              class="text-[11px] text-rose-400/90"
            >
              {{
                liveDrift > 0
                  ? __('drift_under_explanation')
                  : __('drift_over_explanation')
              }}
            </p>
            <!-- Auto-adjust toggle if discrepancy exists -->
            <div
              v-if="liveDrift !== null && Math.abs(liveDrift) > 0"
              class="flex items-start gap-2 border-t border-border/50 pt-2"
            >
              <input
                id="auto_adjust"
                v-model="autoAdjust"
                type="checkbox"
                class="mt-0.5 rounded border-border"
                :disabled="submitting"
              />
              <Label
                for="auto_adjust"
                class="cursor-pointer text-xs leading-tight font-normal text-muted-foreground"
              >
                {{ __('auto_adjust_reconcile_help') }}
              </Label>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            :disabled="submitting"
            @click="open = false"
          >
            {{ __('cancel') }}
          </Button>
          <Button type="submit" :disabled="submitting">
            <Spinner v-if="submitting" class="mr-2" />
            {{ submitting ? __('saving') : __('save') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
