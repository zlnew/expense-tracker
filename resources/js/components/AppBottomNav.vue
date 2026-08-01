<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  Eye,
  EyeOff,
  LayoutGrid,
  Plus,
  Wallet,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import UserMenuContent from '@/components/UserMenuContent.vue'
import { getInitials } from '@/composables/useInitials'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { dashboard } from '@/routes'
import balances from '@/routes/balances'
import budgets from '@/routes/budgets'
import transactions from '@/routes/transactions'

const { __ } = useLang()
const page = usePage()
const user = computed(() => page.props.auth.user)
const { masked, toggleMask } = useMasking()

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

function openTransactionCreate() {
  window.dispatchEvent(new CustomEvent('open:transaction-create'))
}
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 z-50 h-[calc(4rem+env(safe-area-inset-bottom))] w-full border-t border-sidebar-border bg-sidebar px-4 pb-[env(safe-area-inset-bottom)] md:hidden"
  >
    <div
      class="relative mx-auto flex h-16 max-w-lg items-center justify-between"
    >
      <!-- Quick-add FAB: floats centered above the nav row, clear of the home indicator -->
      <Button
        variant="default"
        size="icon"
        aria-label="Add transaction"
        class="absolute left-1/2 top-0 z-10 size-12 -translate-x-1/2 -translate-y-1/2 rounded-full shadow-lg transition-transform active:scale-95"
        @click="openTransactionCreate"
      >
        <Plus class="size-6" />
      </Button>

      <Link
        v-for="item in items"
        :key="item.href"
        :href="item.href"
        class="flex min-h-11 min-w-11 flex-col items-center justify-center gap-1 rounded-md transition-colors"
        :class="
          item.active
            ? 'text-primary'
            : 'text-sidebar-foreground/70 hover:text-sidebar-foreground'
        "
      >
        <component :is="item.icon" class="size-6" />
        <span class="text-[10px] font-medium">{{ item.title }}</span>
      </Link>

      <!-- Mask toggle: compact, sits next to the profile avatar -->
      <button
        type="button"
        class="flex min-h-11 min-w-11 items-center justify-center rounded-md text-sidebar-foreground/70 transition-colors hover:text-sidebar-foreground"
        :aria-label="__('show_hide_balances')"
        :title="__('show_hide_balances')"
        @click="toggleMask"
      >
        <component :is="masked ? EyeOff : Eye" class="size-5" />
      </button>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button
            class="flex min-h-11 min-w-11 flex-col items-center justify-center gap-1 rounded-md outline-none"
          >
            <Avatar class="size-6 overflow-hidden rounded-full">
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
