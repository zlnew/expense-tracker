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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
    return 'bg-[var(--chart-1)]'
  }

  if (status === 'due_soon') {
    return 'bg-[var(--chart-3)]'
  }

  if (status === 'underfunded') {
    return 'bg-[var(--chart-2)]'
  }

  return 'bg-[var(--chart-4)]'
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
    <div class="space-y-6 px-4 py-6 md:px-8">
      <div
        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
      >
        <Heading
          :title="__('sinking_funds')"
          :description="__('fund_list_description')"
          class="mb-0"
        />
        <Button @click="openCreate">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('fund') }) }}
        </Button>
      </div>

      <DataListState
        :is-empty="funds.length === 0"
        :rows="5"
        :empty-icon="PiggyBank"
        :empty-title="__('no_data_found', { data: __('sinking_funds') })"
        :empty-description="__('fund_create_description')"
      >
        <template #empty>
          <Button @click="openCreate">
            <Plus class="mr-2 size-4" />
            {{ __('add_data', { data: __('fund') }) }}
          </Button>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
          <Card
            v-for="fund in funds"
            :key="fund.id"
            class="group relative overflow-hidden transition-all hover:shadow-lg dark:hover:shadow-primary/5"
          >
            <CardHeader>
              <div class="flex items-start justify-between gap-2">
                <div>
                  <CardTitle class="flex items-center gap-2">
                    <PiggyBank class="size-5 text-primary" />
                    {{ fund.name }}
                  </CardTitle>
                  <CardDescription class="mt-1 line-clamp-2 min-h-[20px]">
                    {{ fund.notes ?? '-' }}
                  </CardDescription>
                </div>
                <Badge :variant="statusVariant(fund.status)">
                  {{ __(fund.status) }}
                </Badge>
              </div>
            </CardHeader>

            <CardContent class="space-y-4">
              <div v-if="fund.category" class="flex items-center gap-2">
                <Badge variant="outline" class="text-muted-foreground">
                  {{ fund.category.name }}
                </Badge>
                <span
                  v-if="fund.next_due"
                  class="text-xs text-muted-foreground"
                >
                  · {{ __('next_due') }}:
                  {{ formatDate(fund.next_due, 'DD MMM YYYY') }}
                </span>
              </div>

              <div class="space-y-1.5">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-muted-foreground">{{
                    __('accumulated')
                  }}</span>
                  <span class="font-medium">
                    {{ formatAmount(fund.accumulated) }}
                    <span class="text-muted-foreground">
                      / {{ formatAmount(fund.target_amount) }}
                    </span>
                  </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="progressColor(fund.status)"
                    :style="{ width: `${Math.min(100, fund.percent)}%` }"
                  />
                </div>
                <div
                  class="flex items-center justify-between text-xs text-muted-foreground"
                >
                  <span>{{ fund.percent }}%</span>
                  <span v-if="fund.contribution_amount">
                    {{ formatAmount(fund.contribution_amount) }}/{{
                      __('cycle')
                    }}
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

              <div
                v-if="fund.next_due && daysUntilDue(fund) !== null"
                class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
              >
                <template v-if="daysUntilDue(fund)! < 0">
                  {{ __('due_in') }} {{ Math.abs(daysUntilDue(fund)!) }}
                  {{ __('days_overdue') }}
                </template>
                <template v-else>
                  {{ __('due_in') }} {{ daysUntilDue(fund) }} {{ __('days') }}
                </template>
                <template
                  v-if="
                    fund.status === 'underfunded' || fund.status === 'due_soon'
                  "
                >
                  · {{ fund.percent }}% {{ __('funded') }}
                </template>
              </div>

              <div class="flex items-center gap-2 pt-1">
                <Button size="sm" class="flex-1" @click="openSetAside(fund)">
                  {{ __('set_aside') }}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  class="flex-1"
                  :disabled="fund.accumulated <= 0"
                  @click="openPay(fund)"
                >
                  <Wallet class="mr-1 size-4" />
                  {{ __('pay_from_fund') }}
                </Button>
                <RowActions :actions="rowActions(fund)" collapse-below="md" />
              </div>
            </CardContent>
          </Card>
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
