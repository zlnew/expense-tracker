<script setup lang="ts">
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
import type { SinkingFund } from '@/types'

defineProps<{
  open: boolean
  fund: SinkingFund | null
}>()

defineEmits<{
  'update:open': [value: boolean]
  confirm: []
}>()

const { __ } = useLang()
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ __('delete_data', { data: __('fund') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('fund_delete_description') }}
        </DialogDescription>
      </DialogHeader>

      <DialogFooter class="pt-4">
        <Button variant="outline" @click="$emit('update:open', false)">
          {{ __('cancel') }}
        </Button>
        <Button variant="destructive" @click="$emit('confirm')">
          {{ __('delete') }}
        </Button>
      </DialogFooter>
    </SheetDialogContent>
  </Dialog>
</template>
