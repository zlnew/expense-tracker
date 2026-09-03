<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3'
import { Download, X } from 'lucide-vue-next'
import { computed } from 'vue'
import AppContent from '@/components/AppContent.vue'
import BudgetPacingGauge from '@/components/dashboard/BudgetPacingGauge.vue'
import CashFlowRibbon from '@/components/dashboard/CashFlowRibbon.vue'
import CategorySpendPulse from '@/components/dashboard/CategorySpendPulse.vue'
import ImpendingDrainsCard from '@/components/dashboard/ImpendingDrainsCard.vue'
import SpendingTrendChart from '@/components/dashboard/SpendingTrendChart.vue'
import TransactionActivityFeed from '@/components/dashboard/TransactionActivityFeed.vue'
import { Button } from '@/components/ui/button'
import { useInstallPrompt } from '@/composables/useInstallPrompt'
import { useLang } from '@/composables/useLang'
import type {
  BudgetProgress,
  ExpenseBreakdown,
  MonthlySpendingTrend,
  RecentTransactions,
  SummaryCards,
} from '@/types'

const props = defineProps<{
  summary_cards: SummaryCards
  budget_progress: BudgetProgress
  expense_breakdown?: ExpenseBreakdown[]
  monthly_spending_trend?: MonthlySpendingTrend[]
  recent_transactions: RecentTransactions
  impending_drains: {
    window_days: number
    from: string
    until: string
    total_impending_outflow: number
    items: Array<{
      kind: 'fund_due' | 'recurring'
      id: number
      label: string
      amount: number
      balance_id: number
      balance_name: string
      due_date: string
      source: string
    }>
    per_balance: Array<{
      balance_id: number
      balance_name: string
      real: number
      impending: number
      projected_free_after: number
      would_go_negative: boolean
    }>
    has_negative_warning: boolean
  }
}>()

const { __ } = useLang()
const { canInstall, promptInstall, dismissInstall } = useInstallPrompt()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('dashboard'),
      href: '/dashboard',
    },
  ],
})

const hasActiveBudget = computed(
  () => props.summary_cards?.has_active_budget ?? false,
)
</script>

<template>
  <Head :title="__('dashboard')" />

  <AppContent>
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
      <!-- PWA Install Banner -->
      <aside
        v-if="canInstall"
        aria-label="Install app"
        class="flex items-center justify-between gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4 text-xs shadow-xs"
      >
        <div class="flex items-center gap-3">
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground"
          >
            <Download class="size-4" />
          </div>
          <div>
            <p class="font-bold text-foreground">
              {{ __('install_app') || 'Install Expense Tracker' }}
            </p>
            <p class="text-muted-foreground">
              {{
                __('install_app_description') ||
                'Add to home screen for instant, offline access.'
              }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Button
            size="sm"
            class="h-8 text-xs font-semibold"
            @click="promptInstall"
          >
            {{ __('install') || 'Install' }}
          </Button>
          <Button
            size="icon"
            variant="ghost"
            class="size-8 text-muted-foreground hover:text-foreground"
            @click="dismissInstall"
          >
            <X class="size-4" />
          </Button>
        </div>
      </aside>

      <!-- 1. Hero Liquidity & Cash Flow Ribbon -->
      <CashFlowRibbon :summary-cards="summary_cards" />

      <!-- 2. Core Financial Health Grid (Budget Pacing & Impending Drains) -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <BudgetPacingGauge
          :budget-progress="budget_progress"
          :summary-cards="summary_cards"
        />
        <ImpendingDrainsCard
          :window-days="impending_drains.window_days"
          :impending-drains-initial="impending_drains"
        />
      </div>

      <!-- 3. Spending Analytics Grid (Category Breakdown & Monthly Trend) -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <CategorySpendPulse
          :expense-breakdown="expense_breakdown"
          :has-active-budget="hasActiveBudget"
        />
        <SpendingTrendChart
          :monthly-spending-trend="monthly_spending_trend"
          :has-active-budget="hasActiveBudget"
        />
      </div>

      <!-- 4. Recent Activity Feed -->
      <TransactionActivityFeed :recent-transactions="recent_transactions" />
    </div>
  </AppContent>
</template>
