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
    :class="cn('sticky bottom-0 z-10 -mt-4 bg-background pt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end max-md:-mb-[calc(1.5rem+env(safe-area-inset-bottom))] max-md:pb-[calc(1.5rem+env(safe-area-inset-bottom))]', props.class)"
  >
    <slot />
    <DialogClose v-if="showCloseButton" as-child>
      <Button variant="outline">
        {{ __('close') }}
      </Button>
    </DialogClose>
  </div>
</template>
