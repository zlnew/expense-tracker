<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import TransactionCreateDialog from '@/components/dialogs/TransactionCreateDialog.vue'
import type { Balance, Budget, Category } from '@/types'

// Global quick-add dialog: mounted once in the app layout, opened by the
// mobile bottom-nav FAB (CustomEvent 'open:transaction-create') from ANY page.
const page = usePage()
const open = ref(false)

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
  />
</template>
