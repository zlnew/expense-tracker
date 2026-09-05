<script setup lang="ts">
import { AlertTriangle, CalendarClock, Clock3 } from 'lucide-vue-next'
import { ref, onMounted, watch, computed } from 'vue'
import { useDate } from '@/composables/useDate'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'

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
  per_balance: Array<{
    balance_id: number
    balance_name: string
    real: number
    impending: number
    projected_free_after: number
    would_go_negative: boolean
  }>
  has_negative_warning: boolean
}

const props = withDefaults(
  defineProps<{
    windowDays?: number
    impendingDrainsInitial?: DrainResponse | null
  }>(),
  { windowDays: 60, impendingDrainsInitial: null },
)

// Local mutable copy — the window switcher updates this ref (never the
// readonly prop) and every fetch re-runs off it.
const windowDays = ref<number>(props.windowDays)

const { __ } = useLang()
const { formatDate } = useDate()
const { formatAmount } = useNumber()

const data = ref<DrainResponse | null>(props.impendingDrainsInitial ?? null)
const loading = ref(!props.impendingDrainsInitial)

const fundDuesTotal = computed(() =>
  (data.value?.items ?? [])
    .filter((i) => i.kind === 'fund_due')
    .reduce((s, i) => s + i.amount, 0),
)
const recurringTotal = computed(() =>
  (data.value?.items ?? [])
    .filter((i) => i.kind === 'recurring')
    .reduce((s, i) => s + i.amount, 0),
)

async function fetchDrains() {
  loading.value = true

  try {
    const res = await fetch(
      `/dashboard/impending-drains?window=${windowDays.value}`,
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      },
    )

    if (!res.ok) {
      throw new Error(String(res.status))
    }

    data.value = (await res.json()) as DrainResponse
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!props.impendingDrainsInitial) {
    fetchDrains()
  }
})
watch(windowDays, fetchDrains)
</script>

<template>
  <div
    class="rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-5 shadow-xl sm:p-6"
  >
    <!-- Header -->
    <div
      class="mb-4 flex flex-row items-start justify-between gap-3 border-b border-[#1f222e]/60 pb-4"
    >
      <div>
        <div
          class="flex items-center gap-2 font-mono text-sm font-bold tracking-wide text-zinc-100 uppercase"
        >
          <span
            class="inline-flex size-7 items-center justify-center rounded-lg border border-cyan-500/30 bg-cyan-500/10 text-cyan-400"
          >
            <CalendarClock class="size-4" />
          </span>
          {{ __('impending_drains') }}
        </div>
        <p class="mt-1 font-mono text-[11px] text-zinc-500">
          {{ __('impending_drains_description', { days: String(windowDays) }) }}
          <span v-if="data" class="tabular-nums">
            · {{ formatDate(data.from) }} → {{ formatDate(data.until) }}</span
          >
        </p>
      </div>
      <div class="flex items-center gap-1.5">
        <button
          v-for="w in [30, 60, 90]"
          :key="w"
          type="button"
          class="h-7 rounded-lg border px-2.5 font-mono text-xs font-semibold transition-all"
          :class="
            windowDays === w
              ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-300 shadow-xs'
              : 'border-[#1f222e] bg-[#121217] text-zinc-500 hover:text-zinc-300'
          "
          @click="windowDays = w"
        >
          {{ w }}d
        </button>
      </div>
    </div>

    <!-- Content -->
    <div>
      <div
        v-if="loading"
        class="h-[140px] w-full animate-pulse rounded-xl border border-[#1f222e] bg-[#121217]"
      />
      <div
        v-else-if="!data"
        class="py-8 text-center font-mono text-sm text-zinc-500"
      >
        {{ __('failed_to_load') }}
      </div>
      <template v-else>
        <!-- Totals + per-balance projections -->
        <div class="mb-4 grid gap-2.5 sm:grid-cols-3">
          <div class="rounded-xl border border-[#1f222e] bg-[#121217] p-3">
            <p
              class="font-mono text-[10px] font-semibold tracking-wider text-zinc-500 uppercase"
            >
              {{ __('total_impending') }}
            </p>
            <p
              class="mt-1 font-mono text-sm font-extrabold text-rose-400 tabular-nums"
            >
              {{ formatAmount(data.total_impending_outflow) }}
            </p>
            <p class="font-mono text-[11px] text-zinc-500">
              {{ __('fund_dues') }} {{ formatAmount(fundDuesTotal) }} ·
              {{ __('recurring') }} {{ formatAmount(recurringTotal) }}
            </p>
          </div>
          <div
            v-for="p in data.per_balance"
            :key="p.balance_id"
            class="rounded-xl border p-3"
            :class="
              p.would_go_negative
                ? 'border-rose-500/40 bg-rose-500/10'
                : 'border-[#1f222e] bg-[#121217]'
            "
          >
            <p
              class="truncate font-mono text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
            >
              {{ p.balance_name }}
            </p>
            <p
              class="mt-1 flex items-center gap-1 font-mono text-sm font-bold tabular-nums"
              :class="p.would_go_negative ? 'text-rose-400' : 'text-zinc-100'"
            >
              <AlertTriangle
                v-if="p.would_go_negative"
                class="size-3.5 shrink-0 text-rose-400"
              />
              {{ __('free_after') }} {{ formatAmount(p.projected_free_after) }}
            </p>
            <p class="font-mono text-[11px] text-zinc-500">
              Real {{ formatAmount(p.real) }} − {{ formatAmount(p.impending) }}
            </p>
          </div>
        </div>

        <div
          v-if="data.items.length === 0"
          class="flex flex-col items-center justify-center py-8 text-center text-zinc-500"
        >
          <Clock3 class="mb-2 size-8 stroke-1 text-zinc-700" />
          <span class="font-mono text-sm font-medium text-zinc-400">{{
            __('no_impending_drains')
          }}</span>
          <span class="font-mono text-xs text-zinc-600">{{
            __('no_impending_drains_description')
          }}</span>
        </div>
        <div v-else class="divide-y divide-[#1f222e]/60">
          <div
            v-for="item in data.items"
            :key="`${item.kind}-${item.id}-${item.due_date}`"
            class="flex items-center justify-between gap-3 px-1 py-2.5"
          >
            <div class="min-w-0">
              <p class="truncate font-mono text-sm font-semibold text-zinc-200">
                {{ item.label }}
              </p>
              <p class="font-mono text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1.5">
                  <span
                    class="inline-flex rounded-md px-1.5 py-0.5 font-mono text-[10px] font-bold"
                    :class="
                      item.kind === 'fund_due'
                        ? 'border border-purple-500/30 bg-purple-500/20 text-purple-300'
                        : 'border border-cyan-500/30 bg-cyan-500/20 text-cyan-300'
                    "
                    >{{
                      item.kind === 'fund_due'
                        ? __('fund_due')
                        : __('recurring')
                    }}</span
                  >
                  {{ item.balance_name }} · {{ formatDate(item.due_date) }}
                </span>
              </p>
            </div>
            <span
              class="shrink-0 font-mono text-sm font-bold text-rose-400 tabular-nums"
              >{{ formatAmount(item.amount) }}</span
            >
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
