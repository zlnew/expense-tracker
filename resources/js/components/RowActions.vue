<script setup lang="ts">
import { Ellipsis, SquarePen, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useLang } from '@/composables/useLang'

type RowAction = {
  label: string
  icon?: unknown
  variant?: 'default' | 'destructive'
  onClick: () => void
}

const props = defineProps<{
  actions: RowAction[]
  /** Render a dropdown (mobile) instead of inline icon buttons (desktop). */
  collapseBelow?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  align?: 'left' | 'right'
}>()

const { __ } = useLang()

function triggerClasses(align: 'left' | 'right') {
  return align === 'right'
    ? 'flex items-center justify-end gap-2'
    : 'flex items-center gap-2'
}
</script>

<template>
  <!-- Desktop: inline ghost icon buttons (touch-target gives 44px on coarse pointers). -->
  <div
    :class="[
      triggerClasses(props.align ?? 'right'),
      props.collapseBelow ? `hidden ${props.collapseBelow}:flex` : 'flex',
    ]"
  >
    <Button
      v-for="action in props.actions"
      :key="action.label"
      variant="ghost"
      size="icon"
      touch-target
      :class="action.variant === 'destructive' ? 'text-destructive' : ''"
      :aria-label="action.label"
      :title="action.label"
      @click="action.onClick"
    >
      <component
        :is="
          action.icon ?? (action.variant === 'destructive' ? Trash2 : SquarePen)
        "
        class="size-4"
      />
    </Button>
  </div>

  <!-- Mobile: single dropdown with all actions (avoids 4 x 44px buttons on a card). -->
  <div
    v-if="props.collapseBelow"
    :class="[props.collapseBelow ? `${props.collapseBelow}:hidden` : '']"
  >
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" size="icon" :aria-label="__('actions')">
          <Ellipsis class="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem
          v-for="action in props.actions"
          :key="action.label"
          :data-variant="action.variant ?? 'default'"
          :class="action.variant === 'destructive' ? 'text-destructive' : ''"
          @click="action.onClick"
        >
          <component :is="action.icon ?? SquarePen" class="size-4" />
          {{ action.label }}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>
