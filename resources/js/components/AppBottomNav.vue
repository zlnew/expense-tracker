<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  LayoutGrid,
  Wallet,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import UserMenuContent from '@/components/UserMenuContent.vue'
import { getInitials } from '@/composables/useInitials'
import { useLang } from '@/composables/useLang'
import { dashboard } from '@/routes'
import balances from '@/routes/balances'
import budgets from '@/routes/budgets'
import transactions from '@/routes/transactions'

const { __ } = useLang()
const page = usePage()
const user = computed(() => page.props.auth.user)

const items = [
  {
    title: __('dashboard'),
    href: dashboard.url(),
    icon: LayoutGrid,
    active: page.url === dashboard.url() || page.url === '/',
  },
  {
    title: __('transactions'),
    href: transactions.index.url(),
    icon: ArrowLeftRight,
    active: page.url.startsWith(transactions.index.url()),
  },
  {
    title: __('budgets'),
    href: budgets.index.url(),
    icon: CircleDollarSign,
    active: page.url.startsWith(budgets.index.url()),
  },
  {
    title: __('balances'),
    href: balances.index.url(),
    icon: Wallet,
    active: page.url.startsWith(balances.index.url()),
  },
]
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 z-50 h-16 w-full border-t border-sidebar-border bg-sidebar px-4 md:hidden"
  >
    <div class="mx-auto flex h-full max-w-lg items-center justify-between">
      <Link
        v-for="item in items"
        :key="item.href"
        :href="item.href"
        class="flex flex-col items-center gap-1 transition-colors"
        :class="
          item.active
            ? 'text-primary'
            : 'text-sidebar-foreground/70 hover:text-sidebar-foreground'
        "
      >
        <component :is="item.icon" class="size-5" />
        <span class="text-[10px] font-medium">{{ item.title }}</span>
      </Link>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button class="flex flex-col items-center gap-1 outline-none">
            <Avatar class="size-5 overflow-hidden rounded-full">
              <AvatarImage
                v-if="user.avatar"
                :src="user.avatar"
                :alt="user.name"
              />
              <AvatarFallback
                class="rounded-lg bg-neutral-200 text-[10px] font-semibold text-black dark:bg-neutral-700 dark:text-white"
              >
                {{ getInitials(user?.name) }}
              </AvatarFallback>
            </Avatar>
            <span class="text-[10px] font-medium text-sidebar-foreground/70">
              {{ __('profile') }}
            </span>
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56" :side-offset="8">
          <UserMenuContent :user="user" />
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </nav>
</template>
