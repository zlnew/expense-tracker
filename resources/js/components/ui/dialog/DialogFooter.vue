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
    :class="cn('sticky bottom-0 z-10 bg-background py-4 max-md:pb-[calc(1rem+env(safe-area-inset-bottom))] flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', props.class)"
  >
    <slot />
    <DialogClose v-if="showCloseButton" as-child>
      <Button variant="outline">
        {{ __('close') }}
      </Button>
    </DialogClose>
  </div>
</template>
