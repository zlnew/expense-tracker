<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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
import {
  store as storeRecurring,
  update as updateRecurring,
} from '@/routes/recurring-transactions'
import type { Balance, Category, RecurringTransaction } from '@/types'

const props = defineProps<{
  open: boolean
  recurring?: RecurringTransaction | null
  balances: Balance[]
  categories: Category[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({
  type: 'expense',
  balance_id: '',
  category_id: '',
  amount: '',
  description: '',
  frequency: 'monthly',
  start_date: '',
  end_date: '',
  next_run_date: '',
  is_active: true,
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

const isEdit = computed(() => Boolean(props.recurring?.id))

const resetForCreate = () => {
  form.reset()
  form.clearErrors()
  form.type = 'expense'
  form.frequency = 'monthly'
  form.is_active = true
}

const fillForEdit = (r: RecurringTransaction) => {
  form.clearErrors()
  form.type = r.type
  form.balance_id = String(r.balance_id)
  form.category_id = r.category_id ? String(r.category_id) : ''
  form.amount = String(r.amount)
  form.description = r.description ?? ''
  form.frequency = r.frequency
  form.start_date = r.start_date.slice(0, 10)
  form.end_date = r.end_date ? r.end_date.slice(0, 10) : ''
  form.next_run_date = r.next_run_date.slice(0, 10)
  form.is_active = r.is_active
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return
    }

    if (props.recurring?.id) {
      fillForEdit(props.recurring)
    } else {
      resetForCreate()
    }
  },
)

const submit = () => {
  const onSuccess = (res: any) => {
    form.reset()
    emit('update:open', false)
    toast.success(res.props.success as string)
  }
  const onError = () => {
    nextTick(() => {
      document.querySelector('[role="alert"]')?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
      })
    })
  }

  if (isEdit.value) {
    form.put(updateRecurring.url(props.recurring!), {
      preserveScroll: true,
      onSuccess,
      onError,
    })
  } else {
    form.post(storeRecurring.url(), {
      preserveScroll: true,
      onSuccess,
      onError,
    })
  }
}

const onActiveChange = (value: boolean | 'indeterminate') => {
  form.is_active = value === true
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
          {{
            isEdit
              ? __('update_data', { data: __('recurring_transaction') })
              : __('add_data', { data: __('recurring_transaction') })
          }}
        </DialogTitle>
        <DialogDescription>
          {{
            isEdit
              ? __('recurring_transactions_update_description')
              : __('recurring_transactions_create_description')
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
            <Label for="rec_type">
              {{ __('type') }} <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.type" required>
              <SelectTrigger id="rec_type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="income">{{ __('income') }}</SelectItem>
                  <SelectItem value="expense">{{ __('expense') }}</SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_amount">
              {{ __('amount') }} <span class="text-destructive">*</span>
            </Label>
            <Input
              id="rec_amount"
              v-model="form.amount"
              type="number"
              min="1"
              placeholder="100000"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.amount ? true : undefined"
            />
            <InputError :message="form.errors.amount" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_description">{{ __('description') }}</Label>
            <Input
              id="rec_description"
              v-model="form.description"
              placeholder="e.g. Salary, Rent, Netflix"
              :disabled="form.processing"
            />
          </div>

          <div class="grid gap-2">
            <Label for="rec_balance">
              {{ __('balance') }} <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.balance_id" required>
              <SelectTrigger id="rec_balance">
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
            <InputError :message="form.errors.balance_id" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_category">{{ __('category') }}</Label>
            <Select v-model="form.category_id">
              <SelectTrigger id="rec_category">
                <SelectValue
                  :placeholder="__('select_data', { data: __('category') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="">—</SelectItem>
                  <SelectItem
                    v-for="c in categories"
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
            <Label for="rec_frequency">
              {{ __('frequency') }} <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.frequency" required>
              <SelectTrigger id="rec_frequency">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="daily">{{ __('daily') }}</SelectItem>
                  <SelectItem value="weekly">{{ __('weekly') }}</SelectItem>
                  <SelectItem value="monthly">{{ __('monthly') }}</SelectItem>
                  <SelectItem value="yearly">{{ __('yearly') }}</SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.frequency" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_start">
              {{ __('start_date') }} <span class="text-destructive">*</span>
            </Label>
            <Input
              id="rec_start"
              v-model="form.start_date"
              type="date"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.start_date ? true : undefined"
            />
            <InputError :message="form.errors.start_date" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_next">
              {{ __('next_run_date') }} <span class="text-destructive">*</span>
            </Label>
            <Input
              id="rec_next"
              v-model="form.next_run_date"
              type="date"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.next_run_date ? true : undefined"
            />
            <InputError :message="form.errors.next_run_date" />
          </div>

          <div class="grid gap-2">
            <Label for="rec_end">{{ __('end_date') }}</Label>
            <Input
              id="rec_end"
              v-model="form.end_date"
              type="date"
              :disabled="form.processing"
            />
          </div>

          <div v-if="isEdit" class="flex items-center gap-2">
            <Checkbox
              id="rec_active"
              :checked="form.is_active"
              @update:checked="onActiveChange"
            />
            <Label for="rec_active" class="cursor-pointer">
              {{ __('active') }}
            </Label>
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
