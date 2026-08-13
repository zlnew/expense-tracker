<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3'
import {
  ArrowLeft,
  Clock,
  TrendingDown,
  TrendingUp,
  Wallet,
} from 'lucide-vue-next'
import AppContent from '@/components/AppContent.vue'
import AppPagination from '@/components/AppPagination.vue'
import Heading from '@/components/Heading.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
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
        <Button
          variant="ghost"
          size="icon"
          class="h-10 w-10 md:h-8 md:w-8"
          asChild
        >
          <Link :href="balanceIndex.url()">
            <ArrowLeft class="size-4" />
          </Link>
        </Button>
        <Heading
          :title="balance.name"
          :description="
            balance.description || __('detail_data', { data: __('balance') })
          "
          class="mb-0"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <Card>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
          >
            <CardTitle class="text-sm font-medium">{{
              __('initial_amount')
            }}</CardTitle>
            <Wallet class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">
              {{ formatAmount(balance.initial_amount) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
          >
            <CardTitle class="text-sm font-medium">{{
              __('final_amount')
            }}</CardTitle>
            <TrendingUp
              v-if="balance.final_amount >= balance.initial_amount"
              class="size-4 text-green-600 dark:text-green-400"
            />
            <TrendingDown
              v-else
              class="size-4 text-red-600 dark:text-red-400"
            />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">
              {{ formatAmount(balance.final_amount) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
          >
            <CardTitle class="text-sm font-medium">{{
              __('status')
            }}</CardTitle>
            <Clock class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <Badge
              v-if="balance.is_primary"
              variant="secondary"
              class="bg-primary/10 text-primary"
            >
              {{ __('primary') }}
            </Badge>
            <Badge v-else variant="outline">{{ __('optional') }}</Badge>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold">{{ __('transactions') }}</h3>
        <!-- Mobile view for transactions -->
        <div class="space-y-4 md:hidden">
          <div
            v-if="!transactions.data || transactions.data.length === 0"
            class="py-8 text-center text-muted-foreground"
          >
            {{ __('no_data_found', { data: __('transactions') }) }}
          </div>
          <div
            v-for="t in transactions.data"
            :key="t.id"
            class="rounded-lg border bg-background p-4"
          >
            <div class="mb-2 flex items-center justify-between">
              <span class="text-sm text-muted-foreground">{{
                formatDate(t.date, 'DD MMM YYYY')
              }}</span>
              <Badge :variant="t.type === 'income' ? 'secondary' : 'outline'">
                {{ __(t.type) }}
              </Badge>
            </div>
            <div class="flex items-center justify-between font-bold">
              <span>{{ t.category?.name || '-' }}</span>
              <span
                :class="
                  t.type === 'income'
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-red-600 dark:text-red-400'
                "
              >
                {{ t.type === 'income' ? '+' : '-'
                }}{{ formatAmount(t.amount) }}
              </span>
            </div>
            <div
              v-if="t.description"
              class="mt-2 text-sm text-muted-foreground"
            >
              {{ t.description }}
            </div>
          </div>
        </div>

        <!-- Desktop View: Table -->
        <div
          class="hidden overflow-hidden rounded-md border bg-background md:block"
        >
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{{ __('date') }}</TableHead>
                <TableHead>{{ __('type') }}</TableHead>
                <TableHead>{{ __('category') }}</TableHead>
                <TableHead>{{ __('notes') }}</TableHead>
                <TableHead class="text-right">{{ __('total') }}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-if="!transactions.data || transactions.data.length === 0"
              >
                <TableCell colspan="5" class="h-24 text-center">
                  {{ __('no_data_found', { data: __('transactions') }) }}
                </TableCell>
              </TableRow>
              <template v-else>
                <TableRow v-for="t in transactions.data" :key="t.id">
                  <TableCell class="font-medium">
                    {{ formatDate(t.date, 'DD MMM YYYY') }}
                  </TableCell>
                  <TableCell>
                    <Badge
                      :variant="t.type === 'income' ? 'secondary' : 'outline'"
                    >
                      {{ __(t.type) }}
                    </Badge>
                  </TableCell>
                  <TableCell>{{ t.category?.name || '-' }}</TableCell>
                  <TableCell class="text-sm text-muted-foreground">{{
                    t.description || '-'
                  }}</TableCell>
                  <TableCell
                    class="text-right font-medium"
                    :class="
                      t.type === 'income'
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400'
                    "
                  >
                    {{ t.type === 'income' ? '+' : '-'
                    }}{{ formatAmount(t.amount) }}
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
        </div>

        <AppPagination
          v-if="transactions.meta"
          :meta="transactions.meta"
          :links="transactions.links"
        />
      </div>
    </div>
  </AppContent>
</template>
