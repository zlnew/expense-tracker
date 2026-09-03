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

function onKeydown(e: KeyboardEvent) {
  if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.altKey) {
    return
  }

  const target = e.target as HTMLElement | null

  if (
    target &&
    (target.tagName === 'INPUT' ||
      target.tagName === 'TEXTAREA' ||
      target.tagName === 'SELECT' ||
      target.isContentEditable)
  ) {
    return
  }

  if (e.key === 'c' || e.key === 'C') {
    e.preventDefault()
    openDialog()
  }
}

onMounted(() => {
  window.addEventListener('open:transaction-create', openDialog)
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('open:transaction-create', openDialog)
  window.removeEventListener('keydown', onKeydown)
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
