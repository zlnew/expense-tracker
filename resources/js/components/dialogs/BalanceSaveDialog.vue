<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { ComponentPublicInstance } from 'vue'
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
import { Spinner } from '@/components/ui/spinner'
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

const firstFieldRef = ref<ComponentPublicInstance | null>(null)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.clearErrors()

      if (props.balance) {
        form.name = props.balance.name
        form.description = props.balance.description ?? ''
        form.initial_amount = props.balance.initial_amount
      } else {
        form.reset()
      }

      nextTick(() => {
        ;(firstFieldRef.value?.$el as HTMLElement | undefined)?.focus()
      })
    }
  },
)

const submit = () => {
  const onSuccess = (res: any) => {
    emit('update:open', false)
    toast.success(
      (res.props.flash as any)?.success ??
        __('updated_data', { data: __('balance') }),
    )
  }

  const onError = () => {
    nextTick(() => {
      document
        .querySelector('[aria-invalid="true"]')
        ?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    })
  }

  if (props.balance) {
    form.put(updateBalance.url({ balance: props.balance }), {
      preserveScroll: true,
      onSuccess,
      onError,
    })
  } else {
    form.post(storeBalance.url(), {
      preserveScroll: true,
      onSuccess: (res) => {
        form.reset()
        onSuccess(res)
      },
      onError,
    })
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="sm:max-w-[425px]" @open-auto-focus.prevent>
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

      <form @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="name">
              {{ __('name') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="name"
              ref="firstFieldRef"
              v-model="form.name"
              :placeholder="__('name')"
              required
              :disabled="form.processing"
              :aria-invalid="!!form.errors.name"
            />
            <InputError :message="form.errors.name" />
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
              :aria-invalid="!!form.errors.description"
            />
            <InputError :message="form.errors.description" />
          </div>
          <div class="grid gap-2">
            <Label for="initial_amount">
              {{ __('initial_amount') }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="initial_amount"
              type="number"
              inputmode="decimal"
              pattern="[0-9]*[.,]?[0-9]*"
              v-model="form.initial_amount"
              placeholder="0"
              required
              :disabled="form.processing"
              :aria-invalid="!!form.errors.initial_amount"
            />
            <InputError :message="form.errors.initial_amount" />
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
            <Spinner v-if="form.processing" class="size-4" />
            {{ form.processing ? __('saving') : __('save') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
