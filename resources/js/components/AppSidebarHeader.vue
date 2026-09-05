<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { Eye, EyeOff } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
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
// Mobile/tablet-only (<lg): the desktop sidebar provides its own chrome, and
// hiding the desktop trigger/breadcrumbs on scroll is jarring.
const isMobile = ref(false)
const hidden = ref(false)
let lastScrollY = 0
let ticking = false

function onScroll() {
  if (ticking || !isMobile.value) {
    return
  }

  ticking = true

  requestAnimationFrame(() => {
    const y = window.scrollY
    const delta = y - lastScrollY

    // Only react to real scroll motion (ignore sub-pixel settling).
    if (Math.abs(delta) > 2) {
      if (delta > 0 && y > 80) {
        // Scrolling down past the header -> hide.
        hidden.value = true
      } else if (delta < 0) {
        // Scrolling up -> show.
        hidden.value = false
      }
    }

    lastScrollY = y
    ticking = false
  })
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    isMobile.value = window.matchMedia('(max-width: 1023.98px)').matches
    lastScrollY = window.scrollY
    window.addEventListener('scroll', onScroll, { passive: true })
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('scroll', onScroll)
  }
})
</script>

<template>
  <header
    class="sticky top-0 z-header flex h-14 shrink-0 items-center gap-2 border-b border-[#1f222e]/60 bg-[#0a0a0c]/85 px-4 backdrop-blur-md transition-[transform] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] sm:px-6"
    :class="hidden ? '-translate-y-full' : 'translate-y-0'"
  >
    <!-- Desktop: sidebar trigger + breadcrumbs -->
    <SidebarTrigger class="-ml-1 hidden lg:flex" />
    <div class="hidden min-w-0 lg:flex lg:items-center lg:gap-2">
      <Breadcrumbs
        v-if="breadcrumbs && breadcrumbs.length > 0"
        :breadcrumbs="breadcrumbs"
      />
      <template v-else>
        <AppLogoIcon class="size-5 shrink-0 fill-current text-emerald-400" />
        <span class="truncate text-sm font-semibold font-mono text-zinc-200">{{
          page.props.name
        }}</span>
      </template>
    </div>

    <!-- Mobile/tablet: logo + current page title in terminal format -->
    <div class="flex min-w-0 items-center gap-2.5 lg:hidden">
      <AppLogoIcon class="size-6 shrink-0 fill-current text-emerald-400 drop-shadow-[0_0_8px_rgba(16,185,129,0.4)]" />
      <div class="flex flex-col min-w-0">
        <span class="truncate font-mono text-xs font-bold text-zinc-100 tracking-tight">{{ pageTitle }}</span>
        <span class="text-[9px] font-mono text-emerald-500/80 font-semibold tracking-wider uppercase">Expense Terminal</span>
      </div>
    </div>

    <!-- Right actions: mask toggle + profile (mobile shows avatar here) -->
    <div class="ml-auto flex items-center gap-2">
      <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-xl border border-[#1f222e] bg-[#12141a] text-zinc-400 transition-colors hover:border-zinc-600 hover:text-zinc-100 active:scale-95"
        :aria-label="__('show_hide_balances')"
        :title="__('show_hide_balances')"
        @click="toggleMask"
      >
        <component :is="masked ? EyeOff : Eye" class="size-3.5" />
      </button>

      <DropdownMenu v-if="user">
        <DropdownMenuTrigger as-child>
          <button
            class="flex size-10 items-center justify-center rounded-full transition-colors outline-none hover:bg-sidebar-accent"
            :aria-label="__('profile')"
          >
            <Avatar class="size-8 rounded-full">
              <AvatarImage
                v-if="user.avatar"
                :src="user.avatar"
                :alt="user.name"
              />
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
