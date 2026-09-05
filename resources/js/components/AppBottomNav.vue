<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  LayoutGrid,
  Menu as MenuIcon,
  Plus,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { useSidebar } from '@/components/ui/sidebar/utils'
import { useLang } from '@/composables/useLang'
import { dashboard } from '@/routes'
import budgets from '@/routes/budgets'
import transactions from '@/routes/transactions'

const { __ } = useLang()
const page = usePage()
const { toggleSidebar } = useSidebar()

const leftItems = computed(() => [
  {
    title: __('dashboard'),
    href: dashboard.url(),
    icon: LayoutGrid,
    active: () => page.url === dashboard.url() || page.url === '/',
  },
  {
    title: __('transactions'),
    href: transactions.index.url(),
    icon: ArrowLeftRight,
    active: () => page.url.startsWith(transactions.index.url()),
  },
])

const isBudgetsActive = computed(() => page.url.startsWith(budgets.index.url()))
const isOtherActive = computed(() => {
  const url = page.url

  return (
    url.startsWith('/funds') ||
    url.startsWith('/categories') ||
    url.startsWith('/recurring-transactions') ||
    url.startsWith('/balances') ||
    url.startsWith('/settings')
  )
})

function openTransactionCreate() {
  if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
    try {
      navigator.vibrate(15)
    } catch {
      // ignore
    }
  }

  window.dispatchEvent(new CustomEvent('open:transaction-create'))
}

function handleMenuClick() {
  if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
    try {
      navigator.vibrate(10)
    } catch {
      // ignore
    }
  }

  toggleSidebar()
}
</script>

<template>
  <nav
    data-testid="bottom-nav"
    :aria-label="__('main_navigation')"
    class="fixed inset-x-0 bottom-0 z-bottom-nav border-t border-border bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md lg:hidden"
  >
    <div
      class="relative mx-auto grid h-16 max-w-lg grid-cols-5 items-center px-2"
    >
      <!-- Nav links (2 left) -->
      <template v-for="item in leftItems" :key="item.href">
        <Link
          :href="item.href"
          :aria-current="item.active() ? 'page' : undefined"
          class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-none px-1 transition-all focus-visible:outline-none"
          :class="
            item.active()
              ? 'font-semibold text-emerald-500 dark:text-emerald-400'
              : 'text-muted-foreground hover:text-foreground'
          "
        >
          <component :is="item.icon" class="size-[22px]" />
          <span class="text-[10px] tracking-tight">{{ item.title }}</span>
          <span
            v-if="item.active()"
            class="-mt-0.5 h-0.5 w-2 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] dark:bg-emerald-400"
          />
        </Link>
      </template>

      <!-- Center slot: quick-add FAB, raised above the bar -->
      <div class="flex items-center justify-center">
        <button
          type="button"
          data-testid="transaction-fab"
          :aria-label="__('add_transaction')"
          class="flex size-12 -translate-y-4 cursor-pointer items-center justify-center border border-emerald-400 bg-emerald-500 text-black shadow-[0_0_20px_rgba(16,185,129,0.5)] ring-2 ring-card transition-all hover:scale-105 hover:bg-emerald-400 active:scale-90 dark:border-emerald-300 dark:bg-emerald-400 dark:text-[#0a0a0c]"
          @click="openTransactionCreate"
        >
          <Plus class="size-6 stroke-[3]" />
        </button>
      </div>

      <!-- Right 1: Budgets -->
      <Link
        :href="budgets.index.url()"
        :aria-current="isBudgetsActive ? 'page' : undefined"
        class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-none px-1 transition-all focus-visible:outline-none"
        :class="
          isBudgetsActive
            ? 'font-semibold text-emerald-500 dark:text-emerald-400'
            : 'text-muted-foreground hover:text-foreground'
        "
      >
        <CircleDollarSign class="size-[22px]" />
        <span class="text-[10px] tracking-tight">{{ __('budgets') }}</span>
        <span
          v-if="isBudgetsActive"
          class="-mt-0.5 h-0.5 w-2 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] dark:bg-emerald-400"
        />
      </Link>

      <!-- Right 2: All Menus Drawer -->
      <button
        type="button"
        data-testid="bottom-nav-menu"
        :aria-label="__('menu')"
        class="flex min-h-11 cursor-pointer flex-col items-center justify-center gap-1 rounded-none px-1 transition-all focus-visible:outline-none"
        :class="
          isOtherActive
            ? 'font-semibold text-emerald-500 dark:text-emerald-400'
            : 'text-muted-foreground hover:text-foreground'
        "
        @click="handleMenuClick"
      >
        <MenuIcon class="size-[22px]" />
        <span class="text-[10px] tracking-tight">{{ __('menu') }}</span>
        <span
          v-if="isOtherActive"
          class="-mt-0.5 h-0.5 w-2 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] dark:bg-emerald-400"
        />
      </button>
    </div>
  </nav>
</template>
