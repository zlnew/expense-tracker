<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  LayoutGrid,
  Plus,
  Wallet,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { useLang } from '@/composables/useLang'
import { dashboard } from '@/routes'
import balances from '@/routes/balances'
import budgets from '@/routes/budgets'
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
  if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
    try {
      navigator.vibrate(15)
    } catch {
      // ignore
    }
  }
  window.dispatchEvent(new CustomEvent('open:transaction-create'))
}
</script>

<template>
  <nav
    data-testid="bottom-nav"
    :aria-label="__('main_navigation')"
    class="fixed inset-x-0 bottom-0 z-bottom-nav border-t border-[#1f222e] bg-[#0a0a0c]/95 backdrop-blur-md pb-[env(safe-area-inset-bottom)] lg:hidden"
  >
    <div
      class="relative mx-auto grid h-16 max-w-lg grid-cols-5 items-center px-2"
    >
      <!-- Nav links (2 left) -->
      <template v-for="item in leftItems" :key="item.href">
        <Link
          :href="item.href"
          :aria-current="item.active() ? 'page' : undefined"
          class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 transition-all focus-visible:outline-none"
          :class="
            item.active()
              ? 'text-emerald-400 font-semibold'
              : 'text-zinc-500 hover:text-zinc-300'
          "
        >
          <component :is="item.icon" class="size-[22px]" />
          <span class="text-[10px] tracking-tight">{{ item.title }}</span>
          <span
            v-if="item.active()"
            class="h-0.5 w-2 bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)] -mt-0.5"
          />
        </Link>
      </template>

      <!-- Center slot: quick-add FAB, raised above the bar -->
      <div class="flex items-center justify-center">
        <button
          type="button"
          data-testid="transaction-fab"
          :aria-label="__('add_transaction')"
          class="flex size-12 -translate-y-4 items-center justify-center bg-emerald-400 text-[#0a0a0c] shadow-[0_0_20px_rgba(16,185,129,0.6)] border border-emerald-300 ring-2 ring-[#0a0a0c] transition-all active:scale-90 hover:scale-105 hover:bg-emerald-300 cursor-pointer"
          @click="openTransactionCreate"
        >
          <Plus class="size-6 stroke-[3]" />
        </button>
      </div>

      <!-- Nav links (2 right) -->
      <template v-for="item in rightItems" :key="item.href">
        <Link
          :href="item.href"
          :aria-current="item.active() ? 'page' : undefined"
          class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 transition-all focus-visible:outline-none"
          :class="
            item.active()
              ? 'text-emerald-400 font-semibold'
              : 'text-zinc-500 hover:text-zinc-300'
          "
        >
          <component :is="item.icon" class="size-[22px]" />
          <span class="text-[10px] tracking-tight">{{ item.title }}</span>
          <span
            v-if="item.active()"
            class="h-0.5 w-2 bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)] -mt-0.5"
          />
        </Link>
      </template>
    </div>
  </nav>
</template>
