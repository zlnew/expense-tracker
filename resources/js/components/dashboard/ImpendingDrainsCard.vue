<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { AlertTriangle, CalendarClock, Clock3 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { useDate } from '@/composables/useDate'
import { useNumber } from '@/composables/useNumber'
import { useLang } from '@/composables/useLang'

type DrainItem = {
  kind: 'fund_due' | 'recurring'
  id: number
  label: string
  amount: number
  balance_id: number
  balance_name: string
  due_date: string
  source: string
}

type DrainResponse = {
  window_days: number
  from: string
  until: string
  total_impending_outflow: number
  items: DrainItem[]
  per_balance: Array<{ balance_id: number; balance_name: string; real: number; impending: number; projected_free_after: number; would_go_negative: boolean }>
  has_negative_warning: boolean
}

const props = withDefaults(defineProps<{ windowDays?: number; impendingDrainsInitial?: DrainResponse | null }>(), { windowDays: 60, impendingDrainsInitial: null })

// Local mutable copy — the window switcher updates this ref (never the
// readonly prop) and every fetch re-runs off it.
const windowDays = ref<number>(props.windowDays)

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()

const data = ref<DrainResponse | null>(props.impendingDrainsInitial ?? null)
const loading = ref(!props.impendingDrainsInitial)

const fundDuesTotal = computed(() => (data.value?.items ?? []).filter((i) => i.kind === 'fund_due').reduce((s, i) => s + i.amount, 0))
const recurringTotal = computed(() => (data.value?.items ?? []).filter((i) => i.kind === 'recurring').reduce((s, i) => s + i.amount, 0))

async function fetchDrains() {
  loading.value = true
  try {
    const res = await fetch(`/dashboard/impending-drains?window=${windowDays.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(String(res.status))
    data.value = (await res.json()) as DrainResponse
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => { if (!props.impendingDrainsInitial) fetchDrains() })
watch(windowDays, fetchDrains)
</script>

<template>
  <Card class="border-border/50 bg-card shadow-xs">
    <CardHeader class="flex flex-row items-start justify-between gap-3 space-y-0">
      <div>
        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
          <CalendarClock class="size-4 text-muted-foreground" />
          {{ __('impending_drains') }}
        </CardTitle>
        <CardDescription class="mt-1 text-xs">
          {{ __('impending_drains_description', { days: String(windowDays) }) }}
          <span v-if="data" class="tabular-nums"> · {{ formatDate(data.from) }} → {{ formatDate(data.until) }}</span>
        </CardDescription>
      </div>
      <div class="flex items-center gap-1.5">
        <Button
          v-for="w in [30, 60, 90]"
          :key="w"
          :variant="windowDays === w ? 'secondary' : 'ghost'"
          size="sm"
          class="h-7 px-2.5 text-xs"
          @click="windowDays = w"
        >
          {{ w }}d
        </Button>
      </div>
    </CardHeader>
    <CardContent class="pt-2 pb-5">
      <Skeleton v-if="loading" class="h-[140px] w-full" />
      <div v-else-if="!data" class="py-8 text-center text-sm text-muted-foreground">
        {{ __('failed_to_load') }}
      </div>
      <template v-else>
        <!-- Totals + per-balance projections -->
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
          <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">{{ __('total_impending') }}</p>
            <p class="mt-1 font-mono text-sm font-bold text-foreground">{{ formatAmount(data.total_impending_outflow) }}</p>
            <p class="text-[11px] text-muted-foreground">{{ __('fund_dues') }} {{ formatAmount(fundDuesTotal) }} · {{ __('recurring') }} {{ formatAmount(recurringTotal) }}</p>
          </div>
          <div
            v-for="p in data.per_balance"
            :key="p.balance_id"
            class="rounded-lg border p-3"
            :class="p.would_go_negative ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20' : 'border-border/50 bg-muted/20'"
          >
            <p class="truncate text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">{{ p.balance_name }}</p>
            <p class="mt-1 flex items-center gap-1 font-mono text-sm font-bold" :class="p.would_go_negative ? 'text-amber-700 dark:text-amber-400' : 'text-foreground'">
              <AlertTriangle v-if="p.would_go_negative" class="size-3.5 shrink-0" />
              {{ __('free_after') }} {{ formatAmount(p.projected_free_after) }}
            </p>
            <p class="text-[11px] text-muted-foreground">Real {{ formatAmount(p.real) }} − {{ formatAmount(p.impending) }}</p>
          </div>
        </div>

        <div v-if="data.items.length === 0" class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground">
          <Clock3 class="mb-2 size-8 stroke-1 text-muted-foreground/30" />
          <span class="text-sm font-medium">{{ __('no_impending_drains') }}</span>
          <span class="text-xs">{{ __('no_impending_drains_description') }}</span>
        </div>
        <div v-else class="divide-y divide-border/40">
          <div
            v-for="(item, idx) in data.items"
            :key="`${item.kind}-${item.id}-${item.due_date}`"
            class="flex items-center justify-between gap-3 px-2 py-3"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-foreground">{{ item.label }}</p>
              <p class="text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-1">
                  <span
                    class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                    :class="item.kind === 'fund_due' ? 'bg-violet-50 text-violet-600 dark:bg-violet-950/30 dark:text-violet-400' : 'bg-sky-50 text-sky-600 dark:bg-sky-950/30 dark:text-sky-400'"
                  >{{ item.kind === 'fund_due' ? __('fund_due') : __('recurring') }}</span>
                  {{ item.balance_name }} · {{ formatDate(item.due_date) }}
                </span>
              </p>
            </div>
            <span class="shrink-0 font-mono text-sm font-bold text-foreground">{{ formatAmount(item.amount) }}</span>
          </div>
        </div>
      </template>
    </CardContent>
  </Card>
</template>
