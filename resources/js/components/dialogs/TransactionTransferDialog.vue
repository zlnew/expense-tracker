<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ArrowRightLeft } from 'lucide-vue-next'
import { nextTick, ref, watch } from 'vue'
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
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { Textarea } from '@/components/ui/textarea'
import { useLang } from '@/composables/useLang'
import { transferBetweenAccounts } from '@/routes/transactions'
import type { Balance } from '@/types'

const props = defineProps<{
  open: boolean
  balances: Balance[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({
  from_account_id: '',
  to_account_id: '',
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

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.reset()
      form.clearErrors()
      form.date = new Date().toISOString().split('T')[0]
    }
  },
)

const submit = () => {
  form.post(transferBetweenAccounts.url(), {
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
          {{ __('transfer') }}
        </DialogTitle>
        <DialogDescription>
          {{ __('transfer_description') }}
        </DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="from_account">
              {{ __('from_account') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.from_account_id" :disabled="form.processing">
              <SelectTrigger id="from_account">
                <SelectValue
                  :placeholder="__('select_data', { data: __('balance') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="b in balances"
                  :key="b.id"
                  :value="b.id.toString()"
                >
                  {{ b.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.from_account_id" />
          </div>

          <div class="flex justify-center">
            <ArrowRightLeft class="size-6 text-muted-foreground" />
          </div>

          <div class="grid gap-2">
            <Label for="to_account">
              {{ __('to_account') }}
              <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.to_account_id" :disabled="form.processing">
              <SelectTrigger id="to_account">
                <SelectValue
                  :placeholder="__('select_data', { data: __('balance') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="b in balances"
                  :key="b.id"
                  :value="b.id.toString()"
                >
                  {{ b.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.to_account_id" />
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
