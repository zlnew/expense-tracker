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
import type { BreadcrumbItem } from '@/types'

type Props = {
  breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})
</script>

<template>
  <AppShell variant="sidebar">
    <AppSidebar />
    <AppContent variant="sidebar" class="overflow-x-clip pb-[var(--bottom-nav-height)]">
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
