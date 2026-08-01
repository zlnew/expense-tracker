<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
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
import { useLang } from '@/composables/useLang'
import { destroy as destroyRecurring } from '@/routes/recurring-transactions'
import type { RecurringTransaction } from '@/types'

const props = defineProps<{
  open: boolean
  recurring?: RecurringTransaction | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({})

const submit = () => {
  if (!props.recurring) return

  form.delete(destroyRecurring.url(props.recurring), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
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
          {{ __('delete_data', { data: __('recurring_transaction') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('confirm_delete_recurring') }}
        </DialogDescription>
      </DialogHeader>

      <AlertError
        v-if="Object.keys(form.errors).length > 0"
        :errors="Object.values(form.errors)"
      />

      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          @click="$emit('update:open', false)"
        >
          {{ __('cancel') }}
        </Button>
        <Button
          variant="destructive"
          :disabled="form.processing"
          @click="submit"
        >
          {{ form.processing ? __('deleting') : __('delete') }}
        </Button>
      </DialogFooter>
    </SheetDialogContent>
  </Dialog>
</template>
