<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRightLeft,
  CheckCircle2,
  Clock,
  PiggyBank,
  Scale,
  TrendingDown,
  TrendingUp,
  Wallet,
} from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import BalanceReconcileDialog from '@/components/dialogs/BalanceReconcileDialog.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as balanceIndex } from '@/routes/balances'
import { index as fundIndex } from '@/routes/funds'
import type { Balance, Paginate, Transaction } from '@/types'

const props = defineProps<{
  balance: Balance
  transactions: Paginate<Transaction>
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()
const { masked } = useMasking()

const reconcileDialogOpen = ref(false)

setLayoutProps({
  breadcrumbs: [
    {
      title: __('balances'),
      href: balanceIndex.url(),
    },
    {
      title: props.balance.name,
    },
  ],
})
</script>

<template>
  <Head :title="balance.name" />

  <AppContent>
    <div class="page-container space-y-5">
      <!-- Command Bar -->
      <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-center gap-3">
          <Link
            :href="balanceIndex.url()"
            :aria-label="__('back')"
            class="inline-flex size-9 items-center justify-center rounded-none border border-border bg-secondary/50 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground active:scale-95"
          >
            <ArrowLeft class="size-4" />
          </Link>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h1
                class="truncate font-mono text-base font-bold tracking-tight text-foreground uppercase"
              >
                {{ balance.name }}
              </h1>
              <span
                v-if="balance.is_primary"
                class="inline-flex items-center gap-1 rounded-none border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-500 dark:text-emerald-400"
              >
                <span
                  class="size-1.5 animate-pulse bg-emerald-500 dark:bg-emerald-400"
                />
                {{ __('primary') }}
              </span>
            </div>
            <p class="truncate font-mono text-[11px] text-muted-foreground">
              {{
                balance.description ||
                __('detail_data', { data: __('balance') })
              }}
            </p>
          </div>
        </div>

        <button
          type="button"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-none border border-emerald-500/40 bg-emerald-500/15 px-3.5 font-mono text-xs font-bold text-emerald-500 shadow-xs transition-all hover:bg-emerald-500/25 active:scale-95 dark:text-emerald-400"
          @click="reconcileDialogOpen = true"
        >
          <Scale class="size-3.5" />
          <span>{{ __('reconcile_now') }}</span>
        </button>
      </div>

      <!-- Financial Cockpit (4 Cards) -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <!-- 1. Real Spendable Balance -->
        <div
          class="space-y-2 rounded-none border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between">
            <span
              class="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('real_balance') }}
            </span>
            <span
              class="flex size-7 items-center justify-center rounded-none border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400"
            >
              <Wallet class="size-3.5" />
            </span>
          </div>
          <div
            class="font-mono text-2xl font-extrabold tracking-tight text-emerald-500 tabular-nums dark:text-emerald-400"
          >
            {{
              masked
                ? '••••••••'
                : formatAmount(
                    balance.real ??
                      balance.final_amount - (balance.reserved ?? 0),
                  )
            }}
          </div>
          <p class="font-mono text-[11px] text-muted-foreground">
            {{ __('spendable') }} {{ __('free_cash') }}
          </p>
        </div>

        <!-- 2. Gross Ledger Balance -->
        <div
          class="space-y-2 rounded-none border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between">
            <span
              class="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('ledger_balance') }}
            </span>
            <span
              :class="[
                'flex size-7 items-center justify-center rounded-none border',
                balance.final_amount >= balance.initial_amount
                  ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400'
                  : 'border-rose-500/30 bg-rose-500/10 text-rose-500 dark:text-rose-400',
              ]"
            >
              <TrendingUp
                v-if="balance.final_amount >= balance.initial_amount"
                class="size-3.5"
              />
              <TrendingDown v-else class="size-3.5" />
            </span>
          </div>
          <div
            class="font-mono text-2xl font-bold text-foreground tabular-nums"
          >
            {{ masked ? '••••••••' : formatAmount(balance.final_amount) }}
          </div>
          <p class="font-mono text-[11px] text-muted-foreground">
            {{ __('initial_amount') }}:
            {{ masked ? '••••' : formatAmount(balance.initial_amount) }}
          </p>
        </div>

        <!-- 3. Reserved Sinking Funds -->
        <div
          class="space-y-2 rounded-none border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between">
            <span
              class="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('reserved_balance') }}
            </span>
            <span
              class="flex size-7 items-center justify-center rounded-none border border-amber-500/30 bg-amber-500/10 text-amber-500 dark:text-amber-400"
            >
              <PiggyBank class="size-3.5" />
            </span>
          </div>
          <div
            class="font-mono text-2xl font-bold text-amber-500 tabular-nums dark:text-amber-400"
          >
            {{ masked ? '••••' : formatAmount(balance.reserved ?? 0) }}
          </div>
          <p class="font-mono text-[11px] text-muted-foreground">
            <Link
              v-if="(balance.reserved ?? 0) > 0"
              :href="fundIndex()"
              class="inline-flex items-center gap-1 text-amber-500 hover:underline dark:text-amber-400"
            >
              {{ __('view_detail') }} {{ __('sinking_funds') }} →
            </Link>
            <span v-else>{{ __('optional') }}</span>
          </p>
        </div>

        <!-- 4. Reconciliation & Audit -->
        <div
          class="space-y-2 rounded-none border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between">
            <span
              class="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {{ __('last_reconciled') }}
            </span>
            <span
              :class="[
                'flex size-7 items-center justify-center rounded-none border',
                balance.reconciled_at
                  ? balance.is_drift_flagged
                    ? 'border-rose-500/30 bg-rose-500/10 text-rose-500 dark:text-rose-400'
                    : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400'
                  : 'border-border bg-secondary/50 text-muted-foreground',
              ]"
            >
              <AlertTriangle v-if="balance.is_drift_flagged" class="size-3.5" />
              <CheckCircle2
                v-else-if="balance.reconciled_at"
                class="size-3.5"
              />
              <Clock v-else class="size-3.5" />
            </span>
          </div>

          <div v-if="balance.reconciled_at" class="space-y-1">
            <div class="flex items-baseline justify-between font-mono">
              <span class="text-xs text-muted-foreground">
                {{ formatDate(balance.reconciled_at, 'DD MMM YYYY') }}
              </span>
              <span
                v-if="balance.is_drift_flagged"
                class="rounded-none border border-rose-500/30 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold text-rose-500 dark:text-rose-400"
              >
                {{ __('drift_detected') }} ({{
                  formatAmount(balance.drift ?? 0)
                }})
              </span>
              <span
                v-else
                class="rounded-none border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-bold text-emerald-500 dark:text-emerald-400"
              >
                {{ __('reconciled') }}
              </span>
            </div>
            <p
              class="font-mono text-xs font-semibold text-foreground tabular-nums"
            >
              {{ __('reconciled') }}:
              {{
                masked ? '••••' : formatAmount(balance.reconciled_amount ?? 0)
              }}
            </p>
          </div>
          <div v-else class="space-y-1">
            <span
              class="inline-flex rounded-none border border-border bg-secondary/50 px-2 py-0.5 font-mono text-xs text-muted-foreground"
            >
              {{ __('unreconciled') }}
            </span>
            <p class="font-mono text-[11px] text-muted-foreground">
              {{ __('action_warning') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Transactions History Table -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3
            class="font-mono text-sm font-bold tracking-wider text-foreground uppercase"
          >
            {{ __('transactions') }}
          </h3>
          <span class="stat-chip font-semibold text-muted-foreground">
            {{ transactions.meta?.total ?? transactions.data.length }} total
          </span>
        </div>

        <DataListState
          :is-empty="!transactions.data || transactions.data.length === 0"
          :rows="5"
          :empty-icon="ArrowRightLeft"
          :empty-title="__('no_data_found', { data: __('transactions') })"
        >
          <ResponsiveTable
            :columns="[
              {
                header: __('date'),
                cell: (t) => formatDate(t.date, 'DD MMM YYYY'),
                cellClass:
                  'font-mono text-xs font-medium text-muted-foreground',
              },
              {
                header: __('type'),
                cell: (t) => __(t.type),
              },
              {
                header: __('category'),
                cell: (t) => t.category?.name || '-',
                cellClass: 'font-mono text-xs text-foreground',
              },
              {
                header: __('notes'),
                cell: (t) => t.description || '-',
                cellClass: 'font-mono text-xs text-muted-foreground',
              },
              {
                header: __('total'),
                cell: (t) =>
                  `${t.type === 'income' ? '+' : '-'}${formatAmount(t.amount)}`,
                cellClass:
                  'text-right font-mono text-sm font-bold tabular-nums',
              },
            ]"
            :rows="transactions.data ?? []"
          >
            <template #card="{ row: t }">
              <div class="mb-2 flex items-center justify-between">
                <span class="font-mono text-xs text-muted-foreground">{{
                  formatDate(t.date, 'DD MMM YYYY')
                }}</span>
                <span
                  :class="[
                    'inline-flex items-center rounded-none px-2 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase',
                    t.type === 'income'
                      ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400'
                      : 'border border-rose-500/30 bg-rose-500/10 text-rose-500 dark:text-rose-400',
                  ]"
                >
                  {{ __(t.type) }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-mono text-sm font-medium text-foreground">{{
                  t.category?.name || '-'
                }}</span>
                <span
                  :class="[
                    'font-mono text-sm font-bold tabular-nums',
                    t.type === 'income'
                      ? 'text-emerald-500 dark:text-emerald-400'
                      : 'text-rose-500 dark:text-rose-400',
                  ]"
                >
                  {{ t.type === 'income' ? '+' : '-'
                  }}{{ formatAmount(t.amount) }}
                </span>
              </div>
              <div
                v-if="t.description"
                class="mt-1.5 truncate font-mono text-xs text-muted-foreground"
              >
                {{ t.description }}
              </div>
            </template>
            <template #cell-1="{ row: t }">
              <span
                :class="[
                  'inline-flex items-center rounded-none px-2 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase',
                  t.type === 'income'
                    ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400'
                    : 'border border-rose-500/30 bg-rose-500/10 text-rose-500 dark:text-rose-400',
                ]"
              >
                {{ __(t.type) }}
              </span>
            </template>
            <template #cell-4="{ row: t }">
              <span
                :class="[
                  'font-mono font-bold tabular-nums',
                  t.type === 'income'
                    ? 'text-emerald-500 dark:text-emerald-400'
                    : 'text-rose-500 dark:text-rose-400',
                ]"
              >
                {{ t.type === 'income' ? '+' : '-'
                }}{{ formatAmount(t.amount) }}
              </span>
            </template>
          </ResponsiveTable>
        </DataListState>

        <AppPagination
          v-if="transactions.meta"
          :meta="transactions.meta"
          :links="transactions.links"
        />
      </div>
    </div>

    <!-- Reconcile Dialog -->
    <BalanceReconcileDialog
      v-model:open="reconcileDialogOpen"
      :balance="balance"
    />
  </AppContent>
</template>
