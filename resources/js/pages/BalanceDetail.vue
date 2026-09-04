<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3'
import {
  ArrowLeft,
  ArrowRightLeft,
  Clock,
  TrendingDown,
  TrendingUp,
  Wallet,
} from 'lucide-vue-next'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import Heading from '@/components/Heading.vue'
import ResponsiveTable from '@/components/ResponsiveTable.vue'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { index as balanceIndex } from '@/routes/balances'
import type { Balance, Paginate, Transaction } from '@/types'

const props = defineProps<{
  balance: Balance
  transactions: Paginate<Transaction>
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()

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
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div class="flex items-center gap-4">
        <Link
          :href="balanceIndex.url()"
          :aria-label="__('back')"
          class="inline-flex size-10 items-center justify-center rounded-xl border border-[#1f222e] bg-[#12141a] text-zinc-400 hover:text-white hover:bg-[#181b24] transition-colors active:scale-95"
        >
          <ArrowLeft class="size-4" />
        </Link>
        <Heading
          :title="balance.name"
          :description="
            balance.description || __('detail_data', { data: __('balance') })
          "
          class="mb-0"
        />
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <!-- Initial Amount Card -->
        <div class="relative overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-lg">
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs font-semibold uppercase tracking-wider text-zinc-500">
              {{ __('initial_amount') }}
            </span>
            <span class="flex size-8 items-center justify-center rounded-lg border border-[#1f222e] bg-[#12141a] text-zinc-400">
              <Wallet class="size-4" />
            </span>
          </div>
          <div class="mt-3 font-mono text-2xl font-bold text-zinc-200 tabular-nums">
            {{ formatAmount(balance.initial_amount) }}
          </div>
        </div>

        <!-- Final Amount Card -->
        <div class="relative overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-lg">
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs font-semibold uppercase tracking-wider text-zinc-500">
              {{ __('final_amount') }}
            </span>
            <span
              :class="[
                'flex size-8 items-center justify-center rounded-lg border',
                balance.final_amount >= balance.initial_amount
                  ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                  : 'border-rose-500/30 bg-rose-500/10 text-rose-400',
              ]"
            >
              <TrendingUp
                v-if="balance.final_amount >= balance.initial_amount"
                class="size-4"
              />
              <TrendingDown
                v-else
                class="size-4"
              />
            </span>
          </div>
          <div
            :class="[
              'mt-3 font-mono text-2xl font-bold tabular-nums',
              balance.final_amount >= balance.initial_amount
                ? 'text-emerald-400'
                : 'text-rose-400',
            ]"
          >
            {{ formatAmount(balance.final_amount) }}
          </div>
        </div>

        <!-- Status Card -->
        <div class="relative overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-lg">
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs font-semibold uppercase tracking-wider text-zinc-500">
              {{ __('status') }}
            </span>
            <span class="flex size-8 items-center justify-center rounded-lg border border-[#1f222e] bg-[#12141a] text-zinc-400">
              <Clock class="size-4" />
            </span>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span
              v-if="balance.is_primary"
              class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/40 bg-emerald-500/15 px-2.5 py-1 font-mono text-xs font-bold text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.2)]"
            >
              <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse" />
              {{ __('primary') }}
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-md border border-[#1f222e] bg-[#12141a] px-2.5 py-1 font-mono text-xs text-zinc-400"
            >
              {{ __('optional') }}
            </span>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="font-mono text-sm font-semibold uppercase tracking-wider text-zinc-400">{{ __('transactions') }}</h3>
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
                cellClass: 'font-mono text-xs font-medium text-zinc-400',
              },
              {
                header: __('type'),
                cell: (t) => __(t.type),
              },
              {
                header: __('category'),
                cell: (t) => t.category?.name || '-',
                cellClass: 'font-mono text-xs text-zinc-300',
              },
              {
                header: __('notes'),
                cell: (t) => t.description || '-',
                cellClass: 'font-mono text-xs text-zinc-500',
              },
              {
                header: __('total'),
                cell: (t) =>
                  `${t.type === 'income' ? '+' : '-'}${formatAmount(t.amount)}`,
                cellClass: 'text-right font-mono text-sm font-bold tabular-nums',
              },
            ]"
            :rows="transactions.data ?? []"
          >
            <template #card="{ row: t }">
              <div class="mb-2 flex items-center justify-between">
                <span class="font-mono text-xs text-zinc-500">{{
                  formatDate(t.date, 'DD MMM YYYY')
                }}</span>
                <span
                  :class="[
                    'inline-flex items-center rounded-md px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider',
                    t.type === 'income'
                      ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                      : 'border border-rose-500/30 bg-rose-500/10 text-rose-400',
                  ]"
                >
                  {{ __(t.type) }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-mono text-sm font-medium text-zinc-200">{{ t.category?.name || '-' }}</span>
                <span
                  :class="[
                    'font-mono text-sm font-bold tabular-nums',
                    t.type === 'income'
                      ? 'text-emerald-400'
                      : 'text-rose-400',
                  ]"
                >
                  {{ t.type === 'income' ? '+' : '-'
                  }}{{ formatAmount(t.amount) }}
                </span>
              </div>
              <div
                v-if="t.description"
                class="mt-1.5 font-mono text-xs text-zinc-500 truncate"
              >
                {{ t.description }}
              </div>
            </template>
            <template #cell-1="{ row: t }">
              <span
                :class="[
                  'inline-flex items-center rounded-md px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider',
                  t.type === 'income'
                    ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                    : 'border border-rose-500/30 bg-rose-500/10 text-rose-400',
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
                    ? 'text-emerald-400'
                    : 'text-rose-400',
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
  </AppContent>
</template>
