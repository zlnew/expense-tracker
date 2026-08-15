<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { useLang } from '@/composables/useLang'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { __ } = useLang()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="__('loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
