<script setup lang="ts">
import AppBottomNav from '@/components/AppBottomNav.vue'
import AppContent from '@/components/AppContent.vue'
import AppShell from '@/components/AppShell.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import GlobalTransactionCreate from '@/components/GlobalTransactionCreate.vue'
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
    <AppSidebar class-name="hidden md:block" />
    <AppContent variant="sidebar" class="overflow-x-clip pb-16 md:pb-0">
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <slot />
    </AppContent>
    <AppBottomNav />
    <GlobalTransactionCreate />
    <Toaster />
  </AppShell>
</template>
