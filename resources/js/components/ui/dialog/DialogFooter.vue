<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { DialogClose } from "reka-ui"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  showCloseButton?: boolean
}>(), {
  showCloseButton: false,
})

const { __ } = useLang()
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="
      cn(
        'sticky bottom-0 z-10 bg-background border-t border-border -mx-4 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] flex flex-col-reverse gap-2 sm:mx-0 sm:px-0 sm:pt-4 sm:pb-0 sm:flex-row sm:justify-end',
        props.class,
      )
    "
  >
    <slot />
    <DialogClose v-if="showCloseButton" as-child>
      <Button variant="outline" class="rounded-none">
        {{ __('close') }}
      </Button>
    </DialogClose>
  </div>
</template>
