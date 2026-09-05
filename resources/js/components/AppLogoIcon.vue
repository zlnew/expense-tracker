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
    fill="none"
    :class="className"
    v-bind="$attrs"
  >
    <!-- Isometric Vault Outer Hexagon (Sharp 90/60deg chamfers) -->
    <path
      d="M16 2.5L28.5 9.5V22.5L16 29.5L3.5 22.5V9.5L16 2.5Z"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linejoin="miter"
      class="opacity-40"
    />
    <!-- Isometric Vault Inner Chamber -->
    <path
      d="M16 7L24.5 11.8V20.2L16 25L7.5 20.2V11.8L16 7Z"
      stroke="currentColor"
      stroke-width="1.25"
      stroke-linejoin="miter"
      class="opacity-60"
    />
    <!-- Isometric Core Axis & Combination Dial -->
    <circle
      cx="16"
      cy="16"
      r="3.5"
      stroke="currentColor"
      stroke-width="1.5"
      class="opacity-80"
    />
    <circle cx="16" cy="16" r="1.25" fill="currentColor" />
    <!-- Safe Hinge Blocks -->
    <rect
      x="2"
      y="11"
      width="2"
      height="3"
      fill="currentColor"
      class="opacity-60"
    />
    <rect
      x="2"
      y="18"
      width="2"
      height="3"
      fill="currentColor"
      class="opacity-60"
    />
    <!-- Dynamic Breakthrough Trend Arrow (Banana Concept B) -->
    <path
      d="M4.5 22L12 15.5L17 19.5L27 6"
      stroke="currentColor"
      stroke-width="2.5"
      stroke-linecap="square"
      stroke-linejoin="miter"
    />
    <!-- Arrowhead Head Point -->
    <polygon points="28.5,4 28,10.5 22,6.5" fill="currentColor" />
  </svg>
</template>
