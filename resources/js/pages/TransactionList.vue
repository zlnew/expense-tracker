<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3'
import { useLang } from '@/composables/useLang'

const { __ } = useLang()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('transactions'),
    },
  ],
})
</script>

<template><div></div></template>
