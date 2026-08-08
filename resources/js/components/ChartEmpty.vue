<script setup lang="ts">
import { PieChart } from 'lucide-vue-next'
import type { Component } from 'vue'

type Props = {
  /** Icon shown above the message (defaults to a pie/chart glyph). */
  icon?: Component
  /** Primary empty-state message. */
  message: string
  /** Optional secondary hint line. */
  description?: string
}

withDefaults(defineProps<Props>(), {
  icon: PieChart,
  description: '',
})

defineSlots<{
  /** Optional CTA (e.g. a Button linking to budget creation). */
  default: Record<string, never>
}>()
</script>

<template>
  <div
    role="status"
    class="flex h-full min-h-[220px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-border/60 p-6 text-center"
  >
    <component :is="icon" class="size-8 stroke-1 text-muted-foreground/40" />
    <div class="space-y-1">
      <p class="text-sm font-medium text-foreground">{{ message }}</p>
      <p v-if="description" class="text-xs text-muted-foreground">
        {{ description }}
      </p>
    </div>
    <slot />
  </div>
</template>
