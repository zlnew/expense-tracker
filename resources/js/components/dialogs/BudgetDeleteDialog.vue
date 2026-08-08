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
import { destroy as destroyBudget } from '@/routes/budgets'
import type { Budget } from '@/types'

const props = defineProps<{
  open: boolean
  budget: Budget | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({})

const submit = () => {
  if (!props.budget) {
    return
  }

  form.delete(destroyBudget.url(props.budget), {
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
          {{ __('delete_data', { data: __('budget') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('budget_delete_description') }}
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
    </SheetDialogContent>
  </Dialog>
</template>
