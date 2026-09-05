<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { Languages, LogOut, Settings, Tags } from 'lucide-vue-next'
import {
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import UserInfo from '@/components/UserInfo.vue'
import { useLang } from '@/composables/useLang'
import categories from '@/routes/categories'
import { edit } from '@/routes/profile'
import type { User } from '@/types'

type Props = {
  user: User
}

defineProps<Props>()

const { __, currentLocale, setLocale } = useLang()

const handleLogout = () => {
  router.post('logout')
  router.flushAll()
}
</script>

<template>
  <DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
      <UserInfo :user="user" :show-email="true" />
    </div>
  </DropdownMenuLabel>
  <DropdownMenuSeparator />
  <DropdownMenuGroup>
    <DropdownMenuItem :as-child="true">
      <Link class="block w-full cursor-pointer" :href="edit.url()" prefetch>
        <Settings class="mr-2 h-4 w-4" />
        {{ __('settings') }}
      </Link>
    </DropdownMenuItem>
    <DropdownMenuItem :as-child="true">
      <Link
        class="block w-full cursor-pointer"
        :href="categories.index.url()"
        prefetch
      >
        <Tags class="mr-2 h-4 w-4" />
        {{ __('categories') }}
      </Link>
    </DropdownMenuItem>
  </DropdownMenuGroup>
  <DropdownMenuSeparator />
  <!-- Language Switcher Section -->
  <div class="px-2 py-1.5">
    <div
      class="mb-1.5 flex items-center justify-between font-mono text-[11px] text-muted-foreground"
    >
      <span class="flex items-center gap-1.5">
        <Languages class="size-3.5" />
        {{ __('language') }}
      </span>
      <span class="font-bold text-emerald-500 uppercase dark:text-emerald-400">
        [{{ currentLocale }}]
      </span>
    </div>
    <div class="grid grid-cols-2 gap-1 font-mono text-xs">
      <button
        type="button"
        class="flex cursor-pointer items-center justify-center border px-2 py-1 transition-colors"
        :class="
          currentLocale === 'id'
            ? 'border-emerald-500 bg-emerald-500/15 font-bold text-emerald-500 dark:border-emerald-400 dark:text-emerald-400'
            : 'border-border bg-secondary/50 text-muted-foreground hover:bg-accent hover:text-foreground'
        "
        @click.stop="setLocale('id')"
      >
        ID
      </button>
      <button
        type="button"
        class="flex cursor-pointer items-center justify-center border px-2 py-1 transition-colors"
        :class="
          currentLocale === 'en'
            ? 'border-emerald-500 bg-emerald-500/15 font-bold text-emerald-500 dark:border-emerald-400 dark:text-emerald-400'
            : 'border-border bg-secondary/50 text-muted-foreground hover:bg-accent hover:text-foreground'
        "
        @click.stop="setLocale('en')"
      >
        EN
      </button>
    </div>
  </div>
  <DropdownMenuSeparator />
  <DropdownMenuItem :as-child="true">
    <Link
      class="block w-full cursor-pointer"
      @click="handleLogout"
      as="button"
      data-test="logout-button"
    >
      <LogOut class="mr-2 h-4 w-4" />
      {{ __('log_out') }}
    </Link>
  </DropdownMenuItem>
</template>
