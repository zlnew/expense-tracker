<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import {
  CheckCircle2,
  Plus,
  Search,
  SquarePen,
  Trash2,
  Wallet,
} from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import BalanceDeleteDialog from '@/components/dialogs/BalanceDeleteDialog.vue'
import BalanceSaveDialog from '@/components/dialogs/BalanceSaveDialog.vue'
import Heading from '@/components/Heading.vue'
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
import { Input } from '@/components/ui/input'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { useParam } from '@/composables/useParam'
import {
  index as balanceIndex,
  setPrimary as setPrimaryRoute,
} from '@/routes/balances'
import type { Balance, Paginate } from '@/types'

defineProps<{
  balances: Paginate<Balance>
}>()

const { __ } = useLang()
const { formatAmount } = useNumber()
const param = useParam()

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

const search = ref(param.get('search') || '')

watch(search, () => {
  fetchData()
})

const fetchData = useDebounceFn(() => {
  const params: Record<string, any> = {}

  if (search.value) {
    params.search = search.value
  }

  router.get(balanceIndex.url(), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

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
          <div class="relative w-full">
            <Search
              class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
              v-model="search"
              :placeholder="__('search_categories_placeholder')"
              class="w-full bg-background pl-8"
            />
          </div>
        </div>
      </div>

      <div
        v-if="balances.data.length === 0"
        class="flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed bg-background/50 p-8 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <Wallet class="size-8 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-semibold">
          {{ __('no_data_found', { data: __('balances') }) }}
        </h3>
        <p class="mb-6 text-muted-foreground">
          {{ __('balance_create_description') }}
        </p>
        <Button @click="openCreateDialog">
          <Plus class="mr-2 size-4" />
          {{ __('add_data', { data: __('balance') }) }}
        </Button>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
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
              {{ b.name }}
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
            <div class="flex gap-2">
              <Button
                variant="ghost"
                size="icon"
                class="h-10 w-10 md:h-8 md:w-8"
                @click="openEditDialog(b)"
                :title="__('edit_data', { data: __('balance') })"
              >
                <SquarePen class="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-10 w-10 md:h-8 md:w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="openDeleteDialog(b)"
                :title="__('delete_data', { data: __('balance') })"
              >
                <Trash2 class="size-4" />
              </Button>
            </div>

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

      <AppPagination
        v-if="balances.meta"
        :meta="balances.meta"
        :links="balances.links"
      />
    </div>
  </AppContent>

  <BalanceSaveDialog v-model:open="saveDialogOpen" :balance="targetData" />
  <BalanceDeleteDialog v-model:open="deleteDialogOpen" :balance="targetData" />
</template>
