<script setup lang="ts">
import type { Component } from 'vue'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  icon?: Component
  title: string
  description?: string
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    :class="
      cn(
        'flex h-full min-h-[180px] flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border/60 p-6 text-center text-muted-foreground',
        props.class,
      )
    "
  >
    <component
      :is="icon"
      v-if="icon"
      class="mb-1 size-8 stroke-1 text-muted-foreground/40"
    />
    <span class="text-sm font-medium">{{ title }}</span>
    <span v-if="description" class="mt-1 text-xs text-muted-foreground">{{
      description
    }}</span>
    <slot />
  </div>
</template>
