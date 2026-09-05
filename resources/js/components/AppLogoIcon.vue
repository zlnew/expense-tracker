<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

defineOptions({
  inheritAttrs: false,
})

type Props = {
  className?: HTMLAttributes['class']
}

defineProps<Props>()
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 32 32"
    :class="className"
    v-bind="$attrs"
  >
    <!-- Outer Precision Vault Hex-Ring -->
    <path
      fill="currentColor"
      fill-rule="evenodd"
      clip-rule="evenodd"
      d="M16 2L29.5 9.8V22.2L16 30L2.5 22.2V9.8L16 2ZM5.5 11.5V20.5L16 26.6L26.5 20.5V11.5L16 5.4L5.5 11.5Z"
    />
    <!-- Inflow Delta Arrow (Financial Growth) -->
    <path
      fill="currentColor"
      d="M16 7.5L23.5 14H18.5V19.5H13.5V14H8.5L16 7.5Z"
    />
    <!-- Base Reserve Balance Bar -->
    <rect
      x="10.5"
      y="22"
      width="11"
      height="2.5"
      rx="1.25"
      fill="currentColor"
    />
  </svg>
</template>
