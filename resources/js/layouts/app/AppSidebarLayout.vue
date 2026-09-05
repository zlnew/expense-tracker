<script setup lang="ts">
import AppBottomNav from '@/components/AppBottomNav.vue'
import AppContent from '@/components/AppContent.vue'
import AppShell from '@/components/AppShell.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import GlobalTransactionCreate from '@/components/GlobalTransactionCreate.vue'
import OfflineIndicator from '@/components/OfflineIndicator.vue'
import PwaUpdatePrompt from '@/components/PwaUpdatePrompt.vue'
import { Toaster } from '@/components/ui/sonner'
import { useLang } from '@/composables/useLang'
import type { BreadcrumbItem } from '@/types'

const { __ } = useLang()

type Props = {
  breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})
</script>

<template>
  <AppShell variant="sidebar">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg focus:ring-[3px] focus:ring-ring/50 focus:outline-none"
    >
      {{ __('skip_to_content') }}
    </a>
    <AppSidebar />
    <AppContent
      id="main-content"
      tabindex="-1"
      variant="sidebar"
      class="min-h-screen overflow-x-clip bg-[#0a0a0c] pb-24 focus:outline-none md:pb-8"
    >
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <slot />
    </AppContent>
    <AppBottomNav />
    <GlobalTransactionCreate />
    <OfflineIndicator />
    <PwaUpdatePrompt />
    <Toaster />
  </AppShell>
</template>
