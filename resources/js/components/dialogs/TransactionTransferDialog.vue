<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ArrowRightLeft } from 'lucide-vue-next'
import { watch } from 'vue'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.reset()
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
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ __('transfer') }}
        </DialogTitle>
        <DialogDescription>
          {{ __('transfer_description') }}
        </DialogDescription>
      </DialogHeader>

      <AlertError
        v-if="Object.keys(form.errors).length > 0"
        :errors="Object.values(form.errors)"
      />

      <div class="grid gap-4 py-4">
        <div class="grid gap-2">
          <Label>
            {{ __('from_account') }}
            <span class="text-destructive">*</span>
          </Label>
          <Select v-model="form.from_account_id" :disabled="form.processing">
            <SelectTrigger>
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
        </div>

        <div class="flex justify-center">
          <ArrowRightLeft class="size-6 text-muted-foreground" />
        </div>

        <div class="grid gap-2">
          <Label>
            {{ __('to_account') }}
            <span class="text-destructive">*</span>
          </Label>
          <Select v-model="form.to_account_id" :disabled="form.processing">
            <SelectTrigger>
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
            inputmode="decimal"
            pattern="[0-9]*[.,]?[0-9]*"
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
          {{ form.processing ? __('saving') : __('save') }}
        </Button>
      </DialogFooter>
    </SheetDialogContent>
  </Dialog>
</template>
