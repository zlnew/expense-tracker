<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import {
  AlertTriangle,
  CheckCircle2,
  Plus,
  Scale,
  SquarePen,
  Trash2,
  Wallet,
} from 'lucide-vue-next'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import BalanceDeleteDialog from '@/components/dialogs/BalanceDeleteDialog.vue'
import BalanceReconcileDialog from '@/components/dialogs/BalanceReconcileDialog.vue'
import BalanceSaveDialog from '@/components/dialogs/BalanceSaveDialog.vue'
import Heading from '@/components/Heading.vue'
import RowActions from '@/components/RowActions.vue'
import SearchInput from '@/components/SearchInput.vue'
import { useFilters } from '@/composables/useFilters'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import {
  index as balanceIndex,
  setPrimary as setPrimaryRoute,
  show as balanceShow,
} from '@/routes/balances'
import type { Balance, Paginate } from '@/types'

defineProps<{
  balances: Paginate<Balance>
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('balances'),
    },
  ],
})

const saveDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const reconcileDialogOpen = ref(false)
const targetData = ref<Balance | null>(null)

const loading = ref(false)

const { search } = useFilters({
  url: balanceIndex.url(),
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
})

const openCreateDialog = () => {
  targetData.value = null
  saveDialogOpen.value = true
}

const openEditDialog = (data: Balance) => {
  targetData.value = data
  saveDialogOpen.value = true
}

const openDeleteDialog = (data: Balance) => {
  targetData.value = data
  deleteDialogOpen.value = true
}

const openReconcileDialog = (data: Balance) => {
  targetData.value = data
  reconcileDialogOpen.value = true
}

const setPrimary = (balance: Balance) => {
  router.post(
    setPrimaryRoute.url({ balance }),
    {},
    {
      preserveScroll: true,
      onSuccess: (res) => {
        toast.success(
          (res.props.flash as any)?.success ??
            __('updated_data', { data: __('balance') }),
        )
      },
    },
  )
}

const rowActions = (b: Balance) => [
  {
    label: __('edit_data', { data: __('balance') }),
    icon: SquarePen,
    onClick: () => openEditDialog(b),
  },
  {
    label: __('reconcile_balance'),
    icon: Scale,
    onClick: () => openReconcileDialog(b),
  },
  {
    label: __('delete_data', { data: __('balance') }),
    icon: Trash2,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(b),
  },
]
</script>

<template>
  <Head :title="__('balances')" />

  <AppContent>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('balances')"
          :description="__('balance_list_description')"
          class="mb-0"
        />
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-3.5 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
          @click="openCreateDialog"
        >
          <Plus class="size-4 stroke-[2.5]" />
          {{ __('add_data', { data: __('balance') }) }}
        </button>
      </div>

      <div class="flex flex-col items-center gap-4 lg:flex-row">
        <div class="flex w-full items-center gap-2 lg:max-w-md">
          <SearchInput
            v-model="search"
            :placeholder="__('search_balances_placeholder')"
          />
        </div>
      </div>

      <DataListState
        :loading="loading"
        :is-empty="!loading && balances.data.length === 0"
        :rows="5"
        :empty-icon="Wallet"
        :empty-title="__('no_data_found', { data: __('balances') })"
        :empty-description="__('balance_create_description')"
      >
        <template #empty>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-[#0a0a0c] hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.35)] active:scale-95"
            @click="openCreateDialog"
          >
            <Plus class="size-4 stroke-[2.5]" />
            {{ __('add_data', { data: __('balance') }) }}
          </button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="b in balances.data"
            :key="b.id"
            :data-testid="`balance-card-${String(b.id)}`"
            class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-xl transition-all hover:border-zinc-700"
            :class="
              b.is_drift_flagged
                ? 'border-rose-500/60 ring-1 ring-rose-500/30'
                : ''
            "
          >
            <!-- Primary Account Neon Badge -->
            <div v-if="b.is_primary" class="absolute top-4 right-4">
              <span
                class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2.5 py-0.5 font-mono text-[10px] font-bold text-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.2)]"
              >
                <CheckCircle2 class="size-3" />
                {{ __('primary') }}
              </span>
            </div>

            <div>
              <!-- Account Header -->
              <div class="flex items-center gap-2.5 pr-20">
                <span
                  class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400"
                >
                  <Wallet class="size-4" />
                </span>
                <div class="min-w-0">
                  <Link
                    :href="balanceShow.url({ balance: b })"
                    class="truncate font-mono text-base font-bold text-zinc-100 hover:text-emerald-400 transition-colors"
                  >
                    {{ b.name }}
                  </Link>
                  <p class="truncate font-mono text-xs text-zinc-500">
                    {{ b.description ?? '-' }}
                  </p>
                </div>
              </div>

              <!-- Main Amount Display -->
              <div class="mt-5 space-y-3.5">
                <div class="flex items-end justify-between">
                  <span class="font-mono text-xs text-zinc-500 uppercase tracking-wider">
                    {{ __('active') || 'Stored Active' }}
                  </span>
                  <span class="font-mono text-2xl font-extrabold text-zinc-100 tabular-nums">
                    {{ formatAmount(b.final_amount) }}
                  </span>
                </div>

                <div class="space-y-2 border-t border-[#1f222e]/60 pt-3 text-xs">
                  <div class="flex items-center justify-between font-mono">
                    <span class="text-zinc-500">{{ __('real_balance') }}</span>
                    <span class="font-bold text-emerald-400 tabular-nums">
                      {{ formatAmount(b.real ?? 0) }}
                    </span>
                  </div>

                  <div class="flex items-center justify-between font-mono">
                    <span class="text-zinc-500">{{ __('reserved') }}</span>
                    <span class="font-medium text-amber-400 tabular-nums">
                      {{ formatAmount(b.reserved ?? 0) }}
                    </span>
                  </div>

                  <div class="flex items-center justify-between font-mono text-zinc-500">
                    <span>{{ __('initial_amount') }}</span>
                    <span class="tabular-nums">{{ formatAmount(b.initial_amount) }}</span>
                  </div>
                </div>

                <!-- Drift Reconciliation Strip -->
                <div
                  v-if="b.reconciled_amount !== null && b.drift !== null"
                  :data-testid="`balance-drift-${String(b.id)}`"
                  class="flex items-center justify-between rounded-xl border px-3 py-2 text-xs font-mono"
                  :class="
                    b.is_drift_flagged
                      ? 'border-rose-500/40 bg-rose-500/10 text-rose-400'
                      : 'border-[#1f222e] bg-[#121217] text-zinc-400'
                  "
                >
                  <span class="inline-flex items-center gap-1.5 font-semibold">
                    <AlertTriangle
                      v-if="b.is_drift_flagged"
                      class="size-3.5 shrink-0 text-rose-400"
                    />
                    <Scale v-else class="size-3.5 shrink-0 opacity-60 text-emerald-400" />
                    {{
                      b.is_drift_flagged ? __('drift_flagged') : __('drift_ok')
                    }}
                  </span>
                  <span
                    class="font-bold tabular-nums"
                    :class="b.is_drift_flagged ? 'text-rose-400' : 'text-zinc-200'"
                  >
                    {{ formatAmount(b.drift) }}
                  </span>
                </div>

                <p
                  v-if="b.reconciled_at"
                  class="font-mono text-[11px] text-zinc-500"
                  :data-testid="`balance-reconciled-at-${String(b.id)}`"
                >
                  {{ __('reconciled_at') }}: {{ b.reconciled_at }}
                </p>
              </div>
            </div>

            <!-- Footer Actions -->
            <div
              class="mt-5 flex items-center justify-between gap-2 border-t border-[#1f222e]/60 pt-4"
            >
              <RowActions
                :actions="rowActions(b)"
                collapse-below="md"
                align="left"
              />

              <button
                v-if="!b.is_primary"
                type="button"
                class="rounded-xl border border-[#1f222e] bg-[#121217] px-3 py-1.5 font-mono text-xs font-semibold text-zinc-300 hover:border-emerald-500/40 hover:text-emerald-400 transition-all"
                @click="setPrimary(b)"
              >
                {{ __('set_as_primary') }}
              </button>
            </div>
          </div>
        </div>
      </DataListState>

      <AppPagination
        v-if="!loading && balances.meta"
        :meta="balances.meta"
        :links="balances.links"
      />
    </div>
  </AppContent>

  <BalanceSaveDialog v-model:open="saveDialogOpen" :balance="targetData" />
  <BalanceDeleteDialog v-model:open="deleteDialogOpen" :balance="targetData" />
  <BalanceReconcileDialog
    v-model:open="reconcileDialogOpen"
    :balance="targetData"
  />
</template>
