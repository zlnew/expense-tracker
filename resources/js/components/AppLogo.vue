<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'

const page = usePage()
</script>

<template>
  <div
    class="flex aspect-square size-8 shrink-0 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 shadow-[0_0_10px_rgba(16,185,129,0.15)] dark:text-emerald-400"
  >
    <AppLogoIcon class="size-5 fill-current" />
  </div>
  <div class="ml-2 grid flex-1 text-left">
    <span
      class="truncate font-mono text-xs font-bold tracking-wider text-foreground uppercase"
    >
      {{ page.props.name }}
    </span>
    <span
      class="truncate font-mono text-[9px] font-semibold tracking-widest text-emerald-600/90 uppercase dark:text-emerald-400/90"
    >
      Financial Terminal
    </span>
  </div>
</template>
