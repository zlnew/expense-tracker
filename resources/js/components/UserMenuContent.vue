<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { LogOut, Settings, Tags } from 'lucide-vue-next'
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

const { __ } = useLang()

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
