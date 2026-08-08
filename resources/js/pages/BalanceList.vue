<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3'
import { CheckCircle2, Plus, Wallet } from 'lucide-vue-next'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import AppPagination from '@/components/AppPagination.vue'
import DataListState from '@/components/DataListState.vue'
import BalanceDeleteDialog from '@/components/dialogs/BalanceDeleteDialog.vue'
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
const targetData = ref<Balance | null>(null)

const loading = ref(false)

// URL-backed search + pagination.
const { search } = useFilters({
  url: balanceIndex.url(),
  defaults: {},
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
    label: __('detail'),
    icon: Wallet,
    onClick: () => router.visit(balanceShow.url({ balance: b })),
  },
  {
    label: __('edit_data', { data: __('balance') }),
    icon: undefined,
    onClick: () => openEditDialog(b),
  },
  {
    label: __('delete_data', { data: __('balance') }),
    icon: undefined,
    variant: 'destructive' as const,
    onClick: () => openDeleteDialog(b),
  },
]
</script>

<template>
  <Head :title="__('balances')" />

  <div>
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

      <div class="flex w-full items-center gap-2 lg:max-w-md">
        <SearchInput
          v-model="search"
          :placeholder="__('search_balances_placeholder')"
        />
      </div>

      <!-- Skeleton / empty / content chain — one owner, no double render -->
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
            class="group relative overflow-hidden transition-all hover:shadow-lg dark:hover:shadow-primary/5"
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
                  class="hover:underline"
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
              </div>
            </CardContent>

            <CardFooter
              class="flex justify-between gap-2 border-t pt-4 transition-colors"
            >
              <RowActions :actions="rowActions(b)" />

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
  </div>

  <BalanceSaveDialog v-model:open="saveDialogOpen" :balance="targetData" />
  <BalanceDeleteDialog v-model:open="deleteDialogOpen" :balance="targetData" />
</template>
