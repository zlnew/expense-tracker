<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { SidebarTrigger } from '@/components/ui/sidebar'
import type { BreadcrumbItem } from '@/types'

const page = usePage()

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[]
  }>(),
  {
    breadcrumbs: () => [],
  },
)
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex min-w-0 items-center gap-2">
      <SidebarTrigger class="-ml-1 hidden md:flex" />
      <div class="flex min-w-0 items-center gap-2 md:hidden">
        <template v-if="breadcrumbs && breadcrumbs.length > 0">
          <span class="truncate text-sm font-semibold">
            {{ breadcrumbs[breadcrumbs.length - 1].title }}
          </span>
        </template>
        <template v-else>
          <AppLogoIcon class="size-6 shrink-0 fill-current text-primary" />
          <span class="truncate text-sm font-semibold">{{ page.props.name }}</span>
        </template>
      </div>
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>
    </div>
  </header>
</template>
