<script setup lang="ts">
import { Filter } from 'lucide-vue-next'
import { computed, reactive, watch } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import SheetDialogContent from '@/components/ui/dialog-sheet.vue'
import type { FilterKey } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'

type FilterValues = Partial<Record<FilterKey, string>>

type Props = {
  open: boolean
  /** Current committed filter values. */
  model: FilterValues
  /** Default (no-filter) values per key. */
  defaults: FilterValues
  /** Number of active filters to badge on the trigger. */
  activeCount: number
  triggerLabel?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  apply: [value: FilterValues]
}>()

const { __ } = useLang()

// Draft state lives here; commit only on Apply. Reset drafts to the current
// committed model whenever the sheet opens.
const draft = reactive<Record<string, string>>({})

watch(
  () => props.open,
  (o) => {
    if (o) {
      Object.keys(props.model).forEach((key) => {
        draft[key] = props.model[key as FilterKey] ?? ''
      })
    }
  },
)

const isDirty = computed(() =>
  Object.keys(draft).some(
    (key) => (draft[key] ?? '') !== (props.defaults[key as FilterKey] ?? ''),
  ),
)

function apply() {
  emit('apply', { ...draft })
  emit('update:open', false)
}

function reset() {
  Object.keys(props.defaults).forEach((key) => {
    draft[key] = props.defaults[key as FilterKey] ?? ''
  })
}
</script>

<template>
  <Dialog :open="props.open" @update:open="emit('update:open', $event)">
    <SheetDialogContent class="md:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Filter class="size-4" />
          {{ props.triggerLabel || __('filter_transactions') }}
          <Badge v-if="props.activeCount > 0" variant="secondary" class="ml-1">
            {{ props.activeCount }}
          </Badge>
        </DialogTitle>
      </DialogHeader>

      <slot :draft="draft" />

      <DialogFooter class="flex-col gap-2 sm:flex-row">
        <Button
          variant="outline"
          type="button"
          class="w-full sm:w-auto"
          :disabled="!isDirty"
          @click="reset"
        >
          {{ __('reset') }}
        </Button>
        <Button type="button" class="w-full sm:w-auto" @click="apply">
          {{ __('apply') }}
        </Button>
      </DialogFooter>
    </SheetDialogContent>
  </Dialog>
</template>
