<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useLang } from '@/composables/useLang'
import {
  store as storeBalance,
  update as updateBalance,
} from '@/routes/balances'
import type { Balance } from '@/types'

const props = defineProps<{
  open: boolean
  balance: Balance | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({
  name: '',
  description: '',
  initial_amount: 0,
})

watch(
  () => props.balance,
  (val) => {
    if (val) {
      form.name = val.name
      form.description = val.description ?? ''
      form.initial_amount = val.initial_amount
    } else {
      form.reset()
    }
  },
  { immediate: true },
)

const submit = () => {
  if (props.balance) {
    form.put(updateBalance.url({ balance: props.balance }), {
      preserveScroll: true,
      onSuccess: (res) => {
        emit('update:open', false)
        toast.success(
          (res.props.flash as any)?.success ??
            __('updated_data', { data: __('balance') }),
        )
      },
    })
  } else {
    form.post(storeBalance.url(), {
      preserveScroll: true,
      onSuccess: (res) => {
        emit('update:open', false)
        form.reset()
        toast.success(
          (res.props.flash as any)?.success ??
            __('created_data', { data: __('balance') }),
        )
      },
    })
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{
            balance
              ? __('edit_data', { data: __('balance') })
              : __('add_data', { data: __('balance') })
          }}
        </DialogTitle>
        <DialogDescription>
          {{
            balance
              ? __('balance_update_description')
              : __('balance_create_description')
          }}
        </DialogDescription>
      </DialogHeader>

      <AlertError
        v-if="Object.keys(form.errors).length > 0"
        :errors="Object.values(form.errors)"
      />

      <div class="grid gap-4 py-4">
        <div class="grid gap-2">
          <Label for="name">
            {{ __('name') }}
            <span class="text-destructive">*</span>
          </Label>
          <Input
            id="name"
            v-model="form.name"
            :placeholder="__('name')"
            required
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
          />
        </div>
        <div class="grid gap-2">
          <Label for="initial_amount">
            {{ __('initial_amount') }}
            <span class="text-destructive">*</span>
          </Label>
          <Input
            id="initial_amount"
            type="number"
            v-model="form.initial_amount"
            placeholder="0"
            required
          />
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="$emit('update:open', false)">
          {{ __('cancel') }}
        </Button>
        <Button @click="submit" :disabled="form.processing">
          {{ form.processing ? __('saving') : __('save') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
