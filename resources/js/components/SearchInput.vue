<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'

defineProps<{
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function onInput(value: string | number) {
  emit('update:modelValue', String(value))
}
</script>

<template>
  <div class="relative w-full">
    <Search
      class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
    />
    <Input
      :model-value="modelValue"
      :placeholder="placeholder"
      class="w-full bg-background pl-8"
      @update:model-value="onInput"
    />
  </div>
</template>
