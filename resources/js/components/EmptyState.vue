<script setup lang="ts">
import { ArrowRightLeft } from 'lucide-vue-next'
import type { Component, HTMLAttributes } from 'vue'
import { useLang } from '@/composables/useLang'

type Props = {
  title?: string
  description?: string
  icon?: Component
  class?: HTMLAttributes['class']
}

const { __ } = useLang()

withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  icon: () => ArrowRightLeft,
})
</script>

<template>
  <div
    class="flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed bg-background/50 p-8 text-center"
    :class="$props.class"
  >
    <div class="mb-4 rounded-full bg-muted p-4">
      <component :is="$props.icon" class="size-8 text-muted-foreground" />
    </div>
    <h3 class="text-lg font-semibold">
      {{ $props.title || __('no_data_found', { data: __('transactions') }) }}
    </h3>
    <p v-if="$props.description" class="mb-6 text-muted-foreground">
      {{ $props.description }}
    </p>
    <slot />
  </div>
</template>
