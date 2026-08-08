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

type Props = {
  breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})

const { __ } = useLang()
</script>

<template>
  <AppShell variant="sidebar">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-fab focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg"
    >
      {{ __('skip_to_content') }}
    </a>
    <AppSidebar />
    <AppContent
      variant="sidebar"
      class="overflow-x-clip pb-[var(--bottom-nav-height)]"
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
