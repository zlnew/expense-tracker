<script setup lang="ts">
import type { Page } from '@inertiajs/core'
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
import { Textarea } from '@/components/ui/textarea'
import { useLang } from '@/composables/useLang'
import { store as storeFund, update as updateFund } from '@/routes/funds'
import type { Balance, Category, SinkingFund } from '@/types'

const props = defineProps<{
  open: boolean
  fund: SinkingFund | null
  categories: Category[]
  balances: Balance[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({
  name: '',
  target_amount: 0,
  cadence: 'cycle',
  contribution_amount: '',
  category_id: '',
  from_balance_id: '',
  next_due: '',
  due_interval_months: 1,
  notes: '',
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

const expenseCategories = computed(() =>
  props.categories.filter((category) => category.type === 'expense'),
)

const resetForCreate = () => {
  form.reset()
  form.clearErrors()
  form.cadence = 'cycle'
  form.due_interval_months = 1
}

const fillForEdit = (fund: SinkingFund) => {
  form.name = fund.name
  form.target_amount = fund.target_amount
  form.cadence = fund.cadence
  form.contribution_amount =
    fund.contribution_amount === null ? '' : String(fund.contribution_amount)
  form.category_id = fund.category_id ? String(fund.category_id) : ''
  form.from_balance_id = String(fund.from_balance_id)
  form.next_due = fund.next_due ?? ''
  form.due_interval_months = fund.due_interval_months
  form.notes = fund.notes ?? ''
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return
    }

    if (props.fund) {
      fillForEdit(props.fund)
    } else {
      resetForCreate()
    }
  },
)

const submit = () => {
  const payload = () => ({
    name: form.name,
    target_amount: Number(form.target_amount),
    cadence: form.cadence,
    contribution_amount:
      form.contribution_amount === '' ? null : Number(form.contribution_amount),
    category_id: Number(form.category_id),
    from_balance_id: Number(form.from_balance_id),
    next_due: form.next_due === '' ? null : form.next_due,
    due_interval_months: Number(form.due_interval_months),
    notes: form.notes === '' ? null : form.notes,
  })

  const options = {
    preserveScroll: true,
    onSuccess: (res: Page) => {
      emit('update:open', false)

      if (!props.fund) {
        form.reset()
      }

      toast.success(
        (res.props.flash as any)?.success ??
          (props.fund
            ? __('updated_data', { data: __('fund') })
            : __('created_data', { data: __('fund') })),
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
  }

  if (props.fund) {
    form.transform(payload).put(updateFund.url({ fund: props.fund }), options)
  } else {
    form.transform(payload).post(storeFund.url(), options)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent
      class="md:max-w-[500px]"
      @open-auto-focus.prevent="onOpenAutoFocus"
    >
      <DialogHeader>
        <DialogTitle>
          {{
            fund
              ? __('edit_data', { data: __('fund') })
              : __('add_data', { data: __('fund') })
          }}
        </DialogTitle>
        <DialogDescription>
          {{
            fund ? __('fund_update_description') : __('fund_create_description')
          }}
        </DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="fund_name">
              {{ __('name') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="fund_name"
              v-model="form.name"
              :placeholder="__('fund_name_placeholder')"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.name ? true : undefined"
            />
            <InputError :message="form.errors.name" />
          </div>

          <div class="grid gap-2">
            <Label for="fund_target">
              {{ __('target_amount') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="fund_target"
              v-model="form.target_amount"
              type="number"
              inputmode="numeric"
              min="1"
              placeholder="400000"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.target_amount ? true : undefined"
            />
            <InputError :message="form.errors.target_amount" />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="fund_cadence">
                {{ __('cadence') }}
                <span class="text-destructive">*</span>
              </Label>
              <Select v-model="form.cadence">
                <SelectTrigger id="fund_cadence">
                  <SelectValue :placeholder="__('cadence')" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="cycle">{{ __('cycle') }}</SelectItem>
                    <SelectItem value="monthly">{{ __('monthly') }}</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.cadence" />
            </div>

            <div class="grid gap-2">
              <Label for="fund_interval">
                {{ __('due_interval_months') }}
                <span class="text-destructive">*</span>
              </Label>
              <Input
                id="fund_interval"
                v-model="form.due_interval_months"
                type="number"
                inputmode="numeric"
                min="1"
                max="60"
                required
                :disabled="form.processing"
                :aria-invalid="
                  form.errors.due_interval_months ? true : undefined
                "
              />
              <InputError :message="form.errors.due_interval_months" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="fund_category">
                {{ __('category') }}
                <span class="text-destructive">*</span>
              </Label>
              <Select v-model="form.category_id">
                <SelectTrigger id="fund_category">
                  <SelectValue
                    :placeholder="__('select_data', { data: __('category') })"
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem
                      v-for="c in expenseCategories"
                      :key="c.id"
                      :value="String(c.id)"
                    >
                      {{ c.name }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.category_id" />
            </div>

            <div class="grid gap-2">
              <Label for="fund_source_balance">
                {{ __('source_balance') }}
                <span class="text-destructive">*</span>
              </Label>
              <Select v-model="form.from_balance_id">
                <SelectTrigger id="fund_source_balance">
                  <SelectValue
                    :placeholder="__('select_data', { data: __('balance') })"
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem
                      v-for="b in balances"
                      :key="b.id"
                      :value="String(b.id)"
                    >
                      {{ b.name }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.from_balance_id" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="fund_next_due">{{ __('next_due') }}</Label>
              <Input
                id="fund_next_due"
                v-model="form.next_due"
                type="date"
                :disabled="form.processing"
              />
              <InputError :message="form.errors.next_due" />
            </div>
          </div>

          <div class="grid gap-2">
            <Label for="fund_contribution">
              {{ __('contribution_amount') }}
              <span class="text-muted-foreground">({{ __('optional') }})</span>
            </Label>
            <Input
              id="fund_contribution"
              v-model="form.contribution_amount"
              type="number"
              inputmode="numeric"
              min="1"
              :placeholder="__('auto_contribution_hint')"
              :disabled="form.processing"
            />
            <InputError :message="form.errors.contribution_amount" />
          </div>

          <div class="grid gap-2">
            <Label for="fund_notes">
              {{ __('notes') }}
              <span class="text-muted-foreground">({{ __('optional') }})</span>
            </Label>
            <Textarea
              id="fund_notes"
              v-model="form.notes"
              :placeholder="__('notes')"
              rows="2"
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
