<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import {
  DialogClose,
  DialogContent,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"
import DialogOverlay from "./dialog/DialogOverlay.vue"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<
    DialogContentProps & {
      class?: HTMLAttributes["class"]
      showCloseButton?: boolean
    }
  >(),
  {
    showCloseButton: true,
  },
)
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay />
    <DialogContent
      data-slot="dialog-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'bg-background border fixed z-50 grid w-full gap-4 p-6 shadow-lg duration-200',
          // < md: bottom sheet (thumb-reach)
          'inset-x-0 bottom-0 top-auto max-h-[90dvh] w-full overflow-y-auto overscroll-contain rounded-t-2xl',
          'sm:left-[50%] sm:-translate-x-1/2',
          'max-md:pt-4 max-md:pb-[calc(1.5rem+env(safe-area-inset-bottom))]',
          'max-md:data-[state=open]:animate-in max-md:data-[state=closed]:animate-out max-md:data-[state=closed]:fade-out-0 max-md:data-[state=open]:fade-in-0 max-md:data-[state=open]:slide-in-from-bottom max-md:data-[state=closed]:slide-out-to-bottom',
          // md+: centered modal (identical to DialogContent)
          'md:top-[50%] md:left-[50%] md:translate-x-[-50%] md:translate-y-[-50%] md:rounded-lg',
          'md:data-[state=open]:animate-in md:data-[state=closed]:animate-out md:data-[state=closed]:fade-out-0 md:data-[state=open]:fade-in-0 md:data-[state=closed]:zoom-out-95 md:data-[state=open]:zoom-in-95',
          props.class,
        )
      "
    >
      <!-- Drag handle (mobile bottom sheet only) -->
      <div
        class="mx-auto mt-2 mb-1 h-1.5 w-10 shrink-0 rounded-full bg-muted md:hidden"
        aria-hidden="true"
      />

      <slot />

      <DialogClose
        v-if="showCloseButton"
        data-slot="dialog-close"
        class="ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 touch-target focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
      >
        <X />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
