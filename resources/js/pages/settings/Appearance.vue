<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3'
import AppearanceTabs from '@/components/AppearanceTabs.vue'
import Heading from '@/components/Heading.vue'
import { useLang } from '@/composables/useLang'
import { edit } from '@/routes/appearance'

const { __ } = useLang()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('settings'),
    },
    {
      title: __('appearance'),
      href: edit(),
    },
  ],
})
</script>

<template>
  <Head :title="__('appearance_settings')" />

  <h1 class="sr-only">{{ __('appearance_settings') }}</h1>

  <div class="space-y-6">
    <Heading
      variant="small"
      :title="__('appearance_settings_title')"
      :description="__('appearance_settings_description')"
    />
    <AppearanceTabs />
  </div>
</template>
