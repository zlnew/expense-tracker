<script setup lang="ts">
import { ListRestart } from 'lucide-vue-next'
import type { Component, HTMLAttributes } from 'vue'
import AlertError from '@/components/AlertError.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListSkeleton from '@/components/ListSkeleton.vue'
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'

type Props = {
  /** True while a filter/search visit is in flight — skeleton replaces content. */
  loading?: boolean
  /** True when the fetch failed — error slot replaces content. */
  error?: string | null
  /** Empty when there is nothing to show — empty slot replaces content. */
  isEmpty?: boolean
  /** Number of skeleton rows while loading. */
  rows?: number
  /** Icon for the empty state. */
  emptyIcon?: Component
  /** Title for the empty state. */
  emptyTitle?: string
  /** Description for the empty state. */
  emptyDescription?: string
  class?: HTMLAttributes['class']
}

const { __ } = useLang()

withDefaults(defineProps<Props>(), {
  loading: false,
  error: null,
  isEmpty: false,
  rows: 5,
  emptyTitle: '',
  emptyDescription: '',
})

defineSlots<{
  default: Record<string, never>
  empty: Record<string, never>
}>()

function retry() {
  window.location.reload()
}
</script>

<template>
  <!-- Loading: skeleton replaces EVERYTHING — kills the skeleton-over-stale
       data bug (old rows + new skeleton visible simultaneously). -->
  <ListSkeleton v-if="loading" :rows="rows" />

  <!-- Error: explicit retry path instead of a dead empty state. -->
  <div
    v-else-if="error"
    class="flex min-h-[400px] flex-col items-center justify-center gap-4 rounded-xl border border-dashed bg-background/50 p-8 text-center"
  >
    <AlertError :errors="[error]" />
    <Button variant="outline" @click="retry">
      <ListRestart class="size-4" />
      {{ __('retry') }}
    </Button>
  </div>

  <!-- Empty: exactly one empty state, any viewport (kills the separate
       mobile-empty + table-empty fork). -->
  <EmptyState
    v-else-if="isEmpty"
    :icon="emptyIcon"
    :title="emptyTitle"
    :description="emptyDescription"
  >
    <slot name="empty" />
  </EmptyState>

  <!-- Content: single render path, any viewport. -->
  <slot v-else />
</template>
