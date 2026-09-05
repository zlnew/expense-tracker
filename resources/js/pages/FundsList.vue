<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3'
import { PiggyBank, Plus, SquarePen, Trash2, Wallet } from 'lucide-vue-next'
import { ref } from 'vue'
import AppContent from '@/components/AppContent.vue'
import DataListState from '@/components/DataListState.vue'
import FundDeleteDialog from '@/components/dialogs/FundDeleteDialog.vue'
import FundFormDialog from '@/components/dialogs/FundFormDialog.vue'
import FundPayDialog from '@/components/dialogs/FundPayDialog.vue'
import FundSetAsideDialog from '@/components/dialogs/FundSetAsideDialog.vue'
import Heading from '@/components/Heading.vue'
import RowActions from '@/components/RowActions.vue'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import type { Balance, Category, SinkingFund } from '@/types'

defineProps<{
  funds: SinkingFund[]
  categories: Category[]
  balances: Balance[]
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const { formatDate } = useDate()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('sinking_funds'),
    },
  ],
})

const formDialogOpen = ref(false)
const setAsideOpen = ref(false)
const payOpen = ref(false)
const deleteOpen = ref(false)
const targetData = ref<SinkingFund | null>(null)

const statusVariant = (status: SinkingFund['status']) => {
  const map: Record<
    SinkingFund['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
  > = {
    on_track: 'secondary',
    due_soon: 'destructive',
    underfunded: 'outline',
    overfunded: 'default',
  }

  return map[status]
}

const progressColor = (status: SinkingFund['status']) => {
  if (status === 'overfunded') {
    return 'bg-income'
  }

  if (status === 'due_soon') {
    return 'bg-expense'
  }

  if (status === 'underfunded') {
    return 'bg-amber-500'
  }

  return 'bg-reserved'
}

const openCreate = () => {
  targetData.value = null
  formDialogOpen.value = true
}

const openEdit = (fund: SinkingFund) => {
  targetData.value = fund
  formDialogOpen.value = true
}

const openSetAside = (fund: SinkingFund) => {
  targetData.value = fund
  setAsideOpen.value = true
}

const openPay = (fund: SinkingFund) => {
  targetData.value = fund
  payOpen.value = true
}

const openDelete = (fund: SinkingFund) => {
  targetData.value = fund
  deleteOpen.value = true
}

const rowActions = (fund: SinkingFund) => [
  {
    label: __('edit_data', { data: __('fund') }),
    icon: SquarePen,
    onClick: () => openEdit(fund),
  },
  {
    label: __('delete_data', { data: __('fund') }),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => openDelete(fund),
  },
]

const daysUntilDue = (fund: SinkingFund) => {
  if (!fund.next_due) {
    return null
  }

  const due = new Date(fund.next_due)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  due.setHours(0, 0, 0, 0)

  return Math.round((due.getTime() - today.getTime()) / 86_400_000)
}
</script>

<template>
  <Head :title="__('sinking_funds')" />

  <AppContent>
    <div class="page-container space-y-5">
      <!-- Command Bar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2.5">
          <h1 class="font-mono text-base font-bold text-zinc-100 uppercase tracking-wide">
            {{ __('sinking_funds') }}
          </h1>
          <span class="stat-chip text-zinc-400 font-semibold">
            {{ funds.length }} pos dana
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-3.5 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_12px_rgba(16,185,129,0.3)] active:scale-95"
            @click="openCreate"
          >
            <Plus class="size-3.5 stroke-[2.5]" />
            <span>{{ __('add_data', { data: __('fund') }) }}</span>
          </button>
        </div>
      </div>

      <DataListState
        :is-empty="funds.length === 0"
        :rows="5"
        :empty-icon="PiggyBank"
        :empty-title="__('no_data_found', { data: __('sinking_funds') })"
        :empty-description="__('fund_create_description')"
      >
        <template #empty>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
            @click="openCreate"
          >
            <Plus class="size-4 stroke-[2.5]" />
            {{ __('add_data', { data: __('fund') }) }}
          </button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="fund in funds"
            :key="fund.id"
            class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-xl transition-all hover:border-zinc-700"
          >
            <div>
              <!-- Header with Fund name & Actions (Tier 1) -->
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-purple-500/30 bg-purple-500/10 text-purple-400"
                  >
                    <PiggyBank class="size-4" />
                  </span>
                  <h3 class="font-mono text-base font-bold text-zinc-100 leading-snug">
                    {{ fund.name }}
                  </h3>
                </div>
                <div class="shrink-0">
                  <RowActions :actions="rowActions(fund)" collapse-below="md" align="right" />
                </div>
              </div>

              <!-- Status Badge, Category & Due Date (Tier 2) -->
              <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <span
                  class="rounded-lg px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border shrink-0"
                  :class="
                    fund.status === 'overfunded'
                      ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400'
                      : fund.status === 'due_soon'
                        ? 'border-rose-500/30 bg-rose-500/15 text-rose-400'
                        : fund.status === 'underfunded'
                          ? 'border-amber-500/30 bg-amber-500/15 text-amber-400'
                          : 'border-purple-500/30 bg-purple-500/15 text-purple-400'
                  "
                >
                  {{ __(fund.status) }}
                </span>
                <span
                  v-if="fund.category"
                  class="rounded-md border border-[#1f222e] bg-[#121217] px-2 py-0.5 font-mono text-[11px] text-zinc-400"
                >
                  {{ fund.category.name }}
                </span>
                <span v-if="fund.next_due" class="font-mono text-[11px] text-zinc-500">
                  {{ __('next_due') }}: {{ formatDate(fund.next_due, 'DD MMM YYYY') }}
                </span>
              </div>

              <p class="mt-2 line-clamp-2 min-h-[18px] font-mono text-xs text-zinc-500">
                {{ fund.notes ?? '-' }}
              </p>

              <!-- Savings Progress Meter -->
              <div class="mt-4 space-y-2">
                <div class="flex items-baseline justify-between font-mono text-xs">
                  <span class="text-zinc-500 uppercase tracking-wider text-[10px]">
                    {{ __('accumulated') }}
                  </span>
                  <div class="text-right">
                    <span class="text-sm font-bold text-zinc-100 tabular-nums">
                      {{ formatAmount(fund.accumulated) }}
                    </span>
                    <span class="text-zinc-500 text-[11px] tabular-nums">
                      / {{ formatAmount(fund.target_amount) }}
                    </span>
                  </div>
                </div>

                <!-- Hairline Progress Bar -->
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="
                      fund.status === 'overfunded'
                        ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]'
                        : fund.status === 'due_soon'
                          ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.5)]'
                          : fund.status === 'underfunded'
                            ? 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]'
                            : 'bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.5)]'
                    "
                    :style="{ width: `${Math.min(100, fund.percent)}%` }"
                  />
                </div>

                <div
                  class="flex items-center justify-between font-mono text-[11px] text-zinc-400"
                >
                  <span class="font-bold text-zinc-200">{{ fund.percent }}%</span>
                  <span v-if="fund.contribution_amount">
                    {{ formatAmount(fund.contribution_amount) }}/{{ __('cycle') }}
                  </span>
                  <span v-else>
                    {{
                      __('needs_data', {
                        data: formatAmount(fund.auto_contribution),
                      })
                    }}/{{ __('cycle') }}
                  </span>
                </div>
              </div>

              <!-- Countdown Banner -->
              <div
                v-if="fund.next_due && daysUntilDue(fund) !== null"
                class="mt-3 rounded-xl border border-[#1f222e] bg-[#121217] px-3 py-2 font-mono text-xs text-zinc-400"
              >
                <template v-if="daysUntilDue(fund)! < 0">
                  <span class="text-rose-400 font-bold">
                    {{ __('due_in') }} {{ Math.abs(daysUntilDue(fund)!) }} {{ __('days_overdue') }}
                  </span>
                </template>
                <template v-else>
                  <span>
                    {{ __('due_in') }} <strong class="text-zinc-200">{{ daysUntilDue(fund) }}</strong> {{ __('days') }}
                  </span>
                </template>
                <template
                  v-if="
                    fund.status === 'underfunded' || fund.status === 'due_soon'
                  "
                >
                  <span class="text-zinc-500"> · {{ fund.percent }}% {{ __('funded') }}</span>
                </template>
              </div>
            </div>

            <!-- 2-Action Footer -->
            <div class="mt-5 flex items-center gap-2.5 border-t border-[#1f222e]/60 pt-4">
              <button
                type="button"
                class="flex-1 rounded-xl border border-emerald-500/30 bg-emerald-500/15 py-2 font-mono text-xs font-bold text-emerald-400 hover:bg-emerald-500/25 transition-all shadow-[0_0_10px_rgba(16,185,129,0.15)] active:scale-95"
                @click="openSetAside(fund)"
              >
                + {{ __('set_aside') }}
              </button>
              <button
                type="button"
                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-[#1f222e] bg-[#121217] py-2 font-mono text-xs font-semibold text-zinc-300 hover:border-zinc-700 hover:text-zinc-100 transition-all disabled:opacity-40 active:scale-95"
                :disabled="fund.accumulated <= 0"
                @click="openPay(fund)"
              >
                <Wallet class="size-3.5" />
                {{ __('pay_from_fund') }}
              </button>
            </div>
          </div>
        </div>
      </DataListState>

      <FundFormDialog
        v-model:open="formDialogOpen"
        :fund="targetData"
        :categories="categories"
        :balances="balances"
      />
      <FundSetAsideDialog v-model:open="setAsideOpen" :fund="targetData" />
      <FundPayDialog
        v-model:open="payOpen"
        :fund="targetData"
        :balances="balances"
      />
      <FundDeleteDialog v-model:open="deleteOpen" :fund="targetData" />
    </div>
  </AppContent>
</template>
