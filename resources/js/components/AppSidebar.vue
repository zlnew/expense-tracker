<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  CircleDollarSign,
  LayoutGrid,
  PiggyBank,
  Wallet,
} from 'lucide-vue-next'
import { watch } from 'vue'
import AppLogo from '@/components/AppLogo.vue'
import NavFooter from '@/components/NavFooter.vue'
import NavMainWithSub from '@/components/NavMainWithSub.vue'
import NavUser from '@/components/NavUser.vue'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { useSidebar } from '@/components/ui/sidebar/utils'
import { useLang } from '@/composables/useLang'
import { dashboard } from '@/routes'
import balances from '@/routes/balances'
import budgets from '@/routes/budgets'
import categories from '@/routes/categories'
import funds from '@/routes/funds'
import recurringTransactions from '@/routes/recurring-transactions'
import transactions from '@/routes/transactions'
import type { NavGroup, NavItem } from '@/types'

const { __ } = useLang()
const page = usePage()
const { setOpenMobile } = useSidebar()

watch(
  () => page.url,
  () => {
    setOpenMobile(false)
  },
)

const mainNavItems: NavGroup[] = [
  {
    title: __('dashboard'),
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: __('transactions'),
    href: transactions.index(),
    icon: ArrowLeftRight,
    children: [
      {
        title: __('list'),
        href: transactions.index(),
      },
      {
        title: __('recurring_transactions'),
        href: recurringTransactions.index(),
      },
    ],
  },
  {
    title: __('budgets'),
    href: budgets.index(),
    icon: CircleDollarSign,
    children: [
      {
        title: __('list'),
        href: budgets.index(),
      },
      {
        title: __('categories'),
        href: categories.index(),
      },
    ],
  },
  {
    title: __('sinking_funds'),
    href: funds.index(),
    icon: PiggyBank,
  },
  {
    title: __('balances'),
    href: balances.index(),
    icon: Wallet,
  },
]

const footerNavItems: NavItem[] = []
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="dashboard()">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMainWithSub :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
