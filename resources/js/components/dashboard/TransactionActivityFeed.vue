<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  ArrowUpRight,
  ArrowDownLeft,
  ChevronRight,
  Receipt,
} from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useMasking } from '@/composables/useMasking'
import { useNumber } from '@/composables/useNumber'
import { index as transactionIndex } from '@/routes/transactions'
import type { RecentTransactions } from '@/types'

const props = defineProps<{
  recentTransactions: RecentTransactions
}>()

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()
const { masked } = useMasking()

const transactions = computed(() => props.recentTransactions.slice(0, 5))
</script>

<template>
  <Card class="border-border/70 bg-card shadow-sm">
    <CardHeader class="flex flex-row items-center justify-between pb-3">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
          >
            <Receipt class="size-4" />
          </span>
          <CardTitle class="text-base font-bold text-foreground">
            {{ __('recent_transactions') }}
          </CardTitle>
        </div>
        <CardDescription class="text-xs">
          {{ __('recent_transactions_description') }}
        </CardDescription>
      </div>

      <Link :href="transactionIndex.url()">
        <Button
          variant="ghost"
          size="sm"
          class="h-8 gap-1 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
        >
          {{ __('all_data', { data: __('transactions') }) }}
          <ChevronRight class="size-3.5" />
        </Button>
      </Link>
    </CardHeader>

    <CardContent class="pt-1">
      <div
        v-if="transactions.length === 0"
        class="flex flex-col items-center justify-center space-y-2 py-8 text-center"
      >
        <div
          class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          <ArrowLeftRight class="size-5" />
        </div>
        <p class="text-sm font-medium text-muted-foreground">
          {{ __('no_transactions') }}
        </p>
      </div>

      <div v-else class="divide-y divide-border/40">
        <div
          v-for="t in transactions"
          :key="t.id"
          class="group flex items-center justify-between rounded-xl px-2 py-3 transition-colors hover:bg-muted/30"
        >
          <!-- Left: Flow icon & Details -->
          <div class="flex min-w-0 items-center gap-3 pr-3">
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-xl"
              :class="
                t.type === 'income'
                  ? 'bg-income/10 text-income'
                  : 'bg-muted text-muted-foreground transition-colors group-hover:bg-expense/10 group-hover:text-expense'
              "
            >
              <ArrowDownLeft v-if="t.type === 'income'" class="size-4" />
              <ArrowUpRight v-else class="size-4" />
            </div>

            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-foreground">
                {{ t.description || t.category?.name || __('transaction') }}
              </p>
              <div
                class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
              >
                <span
                  v-if="t.category?.name"
                  class="truncate font-medium text-foreground/80"
                >
                  {{ t.category.name }}
                </span>
                <span
                  v-if="t.category?.name && t.balance?.name"
                  class="text-muted-foreground/40"
                  >•</span
                >
                <span v-if="t.balance?.name" class="truncate">
                  via {{ t.balance.name }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Amount & Timestamp -->
          <div class="shrink-0 text-right">
            <p
              class="text-sm font-bold tabular-nums"
              :class="t.type === 'income' ? 'text-income' : 'text-foreground'"
            >
              {{
                masked
                  ? '••••'
                  : (t.type === 'income' ? '+' : '-') + formatAmount(t.amount)
              }}
            </p>
            <p class="text-[11px] text-muted-foreground">
              {{ formatDate(t.date) }}
            </p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
