<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  LayoutGrid,
  PiggyBank,
  Plus,
  Wallet,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'
import { dashboard } from '@/routes'
import balances from '@/routes/balances'
import budgets from '@/routes/budgets'
import funds from '@/routes/funds'
import transactions from '@/routes/transactions'

const { __ } = useLang()
const page = usePage()

const items = [
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
  {
    title: __('budgets'),
    href: budgets.index.url(),
    icon: CircleDollarSign,
    active: () => page.url.startsWith(budgets.index.url()),
  },
  {
    title: __('sinking_funds'),
    href: funds.index.url(),
    icon: PiggyBank,
    active: () => page.url.startsWith(funds.index.url()),
  },
  {
    title: __('balances'),
    href: balances.index.url(),
    icon: Wallet,
    active: () => page.url.startsWith(balances.index.url()),
  },
]

// Left pair = first two items, right pair = last two; the FAB occupies the center slot.
const leftItems = computed(() => items.slice(0, 2))
const rightItems = computed(() => items.slice(2))

function openTransactionCreate() {
  window.dispatchEvent(new CustomEvent('open:transaction-create'))
}
</script>

<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-50 border-t border-sidebar-border bg-sidebar pb-[env(safe-area-inset-bottom)] md:hidden"
  >
    <div
      class="relative mx-auto grid h-16 max-w-lg grid-cols-6 items-center px-2"
    >
      <!-- Nav links (2 left) -->
      <template v-for="item in leftItems" :key="item.href">
        <Link
          :href="item.href"
          class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 transition-colors"
          :class="
            item.active()
              ? 'text-primary'
              : 'text-sidebar-foreground/60 hover:text-sidebar-foreground'
          "
        >
          <component :is="item.icon" class="size-[22px]" />
          <span class="text-[10px] font-medium">{{ item.title }}</span>
        </Link>
      </template>

      <!-- Center slot: quick-add FAB, raised above the bar -->
      <div class="flex items-center justify-center">
        <Button
          variant="default"
          size="icon"
          aria-label="Add transaction"
          class="z-10 size-12 rounded-full shadow-lg transition-transform active:scale-95"
          @click="openTransactionCreate"
        >
          <Plus class="size-6" />
        </Button>
      </div>

      <!-- Nav links (2 right) -->
      <template v-for="item in rightItems" :key="item.href">
        <Link
          :href="item.href"
          class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 transition-colors"
          :class="
            item.active()
              ? 'text-primary'
              : 'text-sidebar-foreground/60 hover:text-sidebar-foreground'
          "
        >
          <component :is="item.icon" class="size-[22px]" />
          <span class="text-[10px] font-medium">{{ item.title }}</span>
        </Link>
      </template>
    </div>
  </nav>
</template>
