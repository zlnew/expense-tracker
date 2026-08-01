<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { Eye, EyeOff } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { SidebarTrigger } from '@/components/ui/sidebar'
import UserMenuContent from '@/components/UserMenuContent.vue'
import { getInitials } from '@/composables/useInitials'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import type { BreadcrumbItem } from '@/types'

const page = usePage()
const { __ } = useLang()
const { masked, toggleMask } = useMasking()

const props = withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[]
  }>(),
  {
    breadcrumbs: () => [],
  },
)

const user = computed(() => page.props.auth.user)
const pageTitle = computed(() => {
  if (props.breadcrumbs && props.breadcrumbs.length > 0) {
    return props.breadcrumbs[props.breadcrumbs.length - 1].title
  }

  return page.props.name
})

// Hide-on-scroll-down / show-on-scroll-up (modern mobile app bar behavior).
// Only affects the mobile header; desktop keeps the header pinned.
const hidden = ref(false)
let lastScrollY = 0
let ticking = false

function onScroll() {
  if (ticking) {
    return
  }

  ticking = true

  requestAnimationFrame(() => {
    const y = window.scrollY
    const delta = y - lastScrollY

    // Ignore tiny jitter; only hide after scrolling down past the header.
    hidden.value = delta > 4 && y > 80
    lastScrollY = y
    ticking = false
  })
}

onMounted(() => {
  lastScrollY = window.scrollY
  window.addEventListener('scroll', onScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
})
</script>

<template>
  <header
    class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/90 px-4 backdrop-blur transition-[width,height,transform] ease-linear duration-200 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6"
    :class="hidden ? '-translate-y-full' : 'translate-y-0'"
  >
    <!-- Desktop: sidebar trigger + breadcrumbs -->
    <SidebarTrigger class="-ml-1 hidden md:flex" />
    <div class="hidden min-w-0 md:flex md:items-center md:gap-2">
      <Breadcrumbs v-if="breadcrumbs && breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />
      <template v-else>
        <AppLogoIcon class="size-5 shrink-0 fill-current text-primary" />
        <span class="truncate text-sm font-semibold">{{ page.props.name }}</span>
      </template>
    </div>

    <!-- Mobile: logo + current page title -->
    <div class="flex min-w-0 items-center gap-2 md:hidden">
      <AppLogoIcon class="size-6 shrink-0 fill-current text-primary" />
      <span class="truncate text-sm font-semibold">{{ pageTitle }}</span>
    </div>

    <!-- Right actions: mask toggle + profile (mobile shows avatar here) -->
    <div class="ml-auto flex items-center gap-1.5">
      <Button
        variant="ghost"
        size="icon"
        class="h-10 w-10"
        :aria-label="__('show_hide_balances')"
        :title="__('show_hide_balances')"
        @click="toggleMask"
      >
        <component :is="masked ? EyeOff : Eye" class="size-[18px]" />
      </Button>

      <DropdownMenu v-if="user">
        <DropdownMenuTrigger as-child>
          <button
            class="flex size-10 items-center justify-center rounded-full outline-none transition-colors hover:bg-sidebar-accent"
            :aria-label="__('profile')"
          >
            <Avatar class="size-8 rounded-full">
              <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
              <AvatarFallback
                class="rounded-full bg-neutral-200 text-[10px] font-semibold text-black dark:bg-neutral-700 dark:text-white"
              >
                {{ getInitials(user?.name) }}
              </AvatarFallback>
            </Avatar>
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56" :side-offset="8">
          <UserMenuContent :user="user" />
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
