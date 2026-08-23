<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import { AlertTriangle, CheckCircle2, Plus, Scale, SquarePen, Trash2, Wallet } from 'lucide-vue-next'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
        <Button @click="openCreateDialog">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('balance') }) }}
        </Button>
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
          <Button @click="openCreateDialog">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('balance') }) }}
          </Button>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
          <Card
            v-for="b in balances.data"
            :key="b.id"
            :data-testid="`balance-card-${String(b.id)}`"
            :class="[
              'group relative overflow-hidden transition-all hover:shadow-lg dark:hover:shadow-primary/5',
              b.is_drift_flagged ? 'border-destructive/60 ring-1 ring-destructive/30' : '',
            ]"
          >
            <div v-if="b.is_primary" class="absolute top-0 right-0 p-2">
              <Badge
                variant="secondary"
                class="border-primary/20 bg-primary/10 text-primary"
              >
                <CheckCircle2 class="mr-1 size-3" />
                {{ __('primary') }}
              </Badge>
            </div>

            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Wallet class="size-5 text-primary" />
                <Link
                  :href="balanceShow.url({ balance: b })"
                  class="truncate hover:underline"
                >
                  {{ b.name }}
                </Link>
              </CardTitle>
              <CardDescription class="line-clamp-2 min-h-[40px]">
                {{ b.description ?? '-' }}
              </CardDescription>
            </CardHeader>

            <CardContent>
              <div class="space-y-4">
                <div class="flex items-end justify-between">
                  <span class="text-sm text-muted-foreground">{{
                    __('final_amount')
                  }}</span>
                  <span class="text-2xl font-bold tracking-tight">
                    {{ formatAmount(b.final_amount) }}
                  </span>
                </div>
                <div
                  class="flex items-center justify-between border-t pt-2 text-xs text-muted-foreground"
                >
                  <span>{{ __('initial_amount') }}</span>
                  <span>{{ formatAmount(b.initial_amount) }}</span>
                </div>

                <!-- Drift (reconciled) row — only when this balance has been reconciled. -->
                <div
                  v-if="b.reconciled_amount !== null && b.drift !== null"
                  :data-testid="`balance-drift-${String(b.id)}`"
                  :class="[
                    'flex items-center justify-between rounded-md border px-3 py-2 text-sm',
                    b.is_drift_flagged
                      ? 'border-destructive/40 bg-destructive/10 text-destructive'
                      : 'border-border bg-muted/40 text-muted-foreground',
                  ]"
                >
                  <span class="inline-flex items-center gap-1.5 font-medium">
                    <AlertTriangle v-if="b.is_drift_flagged" class="size-4 shrink-0" />
                    <Scale v-else class="size-4 shrink-0 opacity-60" />
                    {{ b.is_drift_flagged ? __('drift_flagged') : __('drift_ok') }}
                  </span>
                  <span :class="['font-mono font-semibold tabular-nums', b.is_drift_flagged ? 'text-destructive' : '']">
                    {{ formatAmount(b.drift) }}
                  </span>
                </div>

                <p
                  v-if="b.reconciled_at"
                  class="text-xs text-muted-foreground"
                  :data-testid="`balance-reconciled-at-${String(b.id)}`"
                >
                  {{ __('reconciled_at') }}: {{ b.reconciled_at }}
                </p>
              </div>
            </CardContent>

            <CardFooter
              class="flex justify-between gap-2 border-t pt-4 transition-colors"
            >
              <RowActions
                :actions="rowActions(b)"
                collapse-below="md"
                align="left"
              />

              <Button
                v-if="!b.is_primary"
                variant="outline"
                size="sm"
                class="h-10 md:h-9"
                @click="setPrimary(b)"
              >
                {{ __('set_as_primary') }}
              </Button>
            </CardFooter>
          </Card>
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
  <BalanceReconcileDialog v-model:open="reconcileDialogOpen" :balance="targetData" />
</template>
