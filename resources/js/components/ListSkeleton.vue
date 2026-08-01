<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'

interface ListSkeletonProps {
  /** Number of skeleton rows to render. */
  rows?: number
  /** Show a leading icon circle per row (mobile card look). */
  icon?: boolean
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<ListSkeletonProps>(), {
  rows: 5,
  icon: true,
})
</script>

<template>
  <Card class="animate-pulse overflow-hidden" :class="props.class">
    <CardContent class="space-y-4 p-4 sm:p-6">
      <div
        v-for="i in props.rows"
        :key="i"
        class="flex items-center gap-4 border-b pb-4 last:border-0 last:pb-0"
      >
        <div
          v-if="props.icon"
          class="size-10 shrink-0 rounded-full bg-muted"
        />
        <div class="min-w-0 flex-1 space-y-2">
          <div class="h-4 w-2/3 rounded bg-muted sm:w-1/2" />
          <div class="h-3 w-1/3 rounded bg-muted sm:w-1/4" />
        </div>
        <div class="h-5 w-20 shrink-0 rounded bg-muted sm:w-28" />
      </div>
    </CardContent>
  </Card>
</template>
