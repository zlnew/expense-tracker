<script setup lang="ts">
import { useOnline } from '@vueuse/core'
import { WifiOff } from 'lucide-vue-next'
import { useLang } from '@/composables/useLang'

const { __ } = useLang()
const online = useOnline()
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-300"
    leave-to-class="opacity-0"
  >
    <div
      v-if="!online"
      role="status"
      aria-live="polite"
      class="sticky top-16 z-sticky flex items-center justify-center gap-2 border-b border-sidebar-border/70 bg-amber-50 px-4 py-1.5 text-xs font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-200"
    >
      <WifiOff class="size-3.5" />
      <span>{{ __('offline') }}</span>
    </div>
  </Transition>
</template>
