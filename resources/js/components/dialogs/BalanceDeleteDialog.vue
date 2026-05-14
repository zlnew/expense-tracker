<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
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
import { useLang } from '@/composables/useLang'
import { destroy as destroyBalance } from '@/routes/balances'
import type { Balance } from '@/types'

const props = defineProps<{
  open: boolean
  balance: Balance | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({})

const submit = () => {
  if (!props.balance) {
    return
  }

  form.delete(destroyBalance.url({ balance: props.balance }), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      toast.success(
        (res.props.flash as any)?.success ??
          __('deleted_data', { data: __('balance') }),
      )
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ __('delete_data', { data: __('balance') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('balance_delete_description') }}
        </DialogDescription>
      </DialogHeader>

      <AlertError
        v-if="Object.keys(form.errors).length > 0"
        :errors="Object.values(form.errors)"
      />

      <DialogFooter>
        <Button variant="outline" @click="$emit('update:open', false)">
          {{ __('cancel') }}
        </Button>
        <Button
          variant="destructive"
          @click="submit"
          :disabled="form.processing"
        >
          {{ form.processing ? __('deleting') : __('delete') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
