<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import TransactionCreateDialog from '@/components/dialogs/TransactionCreateDialog.vue'
import type { Balance, Budget, Category } from '@/types'

// Global quick-add dialog: mounted once in the app layout, opened by the
// mobile bottom-nav FAB (CustomEvent 'open:transaction-create') from ANY page.
//
// The balances/budgets/categories/primaryBalanceId/activeBudgetId shared
// props are Inertia::optional() — the server doesn't send them until the
// client requests them. On the FIRST open we reload with `only: [...]` so
// the dialog has real data; the dialog skeletons its selects meanwhile.
const page = usePage()
const open = ref(false)
const loadedRef = ref(false)
const loadingRef = ref(false)

const balances = computed(() => (page.props.balances ?? []) as Balance[])
const budgets = computed(() => (page.props.budgets ?? []) as Budget[])
const categories = computed(() => (page.props.categories ?? []) as Category[])
const primaryBalanceId = computed(
  () => (page.props.primaryBalanceId ?? undefined) as number | undefined,
)
const activeBudgetId = computed(
  () => (page.props.activeBudgetId ?? undefined) as number | undefined,
)

function openDialog() {
  if (!loadedRef.value) {
    loadedRef.value = true
    loadingRef.value = true

    router.reload({
      only: ['balances', 'budgets', 'categories', 'primaryBalanceId', 'activeBudgetId'],
      onFinish: () => {
        loadingRef.value = false
      },
    })
  }

  open.value = true
}

onMounted(() => {
  window.addEventListener('open:transaction-create', openDialog)
})

onUnmounted(() => {
  window.removeEventListener('open:transaction-create', openDialog)
})
</script>

<template>
  <TransactionCreateDialog
    v-model:open="open"
    :balances="balances"
    :budgets="budgets"
    :categories="categories"
    :primary-balance-id="primaryBalanceId"
    :active-budget-id="activeBudgetId"
    :loading="loadingRef"
  />
</template>
