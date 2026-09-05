<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ChevronDown, Delete, Plus, X } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { store as storeTransaction } from '@/routes/transactions'
import type { Balance, Budget, Category } from '@/types'

const props = defineProps<{
  open: boolean
  balances: Balance[]
  budgets: Budget[]
  categories: Category[]
  primaryBalanceId?: number
  activeBudgetId?: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

// State
const type = ref<'expense' | 'income'>('expense')
const rawAmount = ref<string>('')
const selectedCategoryId = ref<number | null>(null)
const selectedBalanceId = ref<number | null>(null)
const description = ref('')
const showNoteInput = ref(false)
const showBalancePicker = ref(false)
const isSubmitting = ref(false)

// Filter categories by selected type
const currentCategories = computed(() =>
  props.categories.filter((c) => (c.type || 'expense') === type.value),
)

// Active balance
const activeBalance = computed(() => {
  if (selectedBalanceId.value) {
    const found = props.balances.find((b) => b.id === selectedBalanceId.value)

    if (found) {
      return found
    }
  }

  if (props.primaryBalanceId) {
    const primary = props.balances.find((b) => b.id === props.primaryBalanceId)

    if (primary) {
      return primary
    }
  }

  return props.balances[0] || null
})

// Numeric amount parsed from rawAmount string
const numericAmount = computed(() => {
  if (!rawAmount.value) {
    return 0
  }

  const parsed = parseInt(rawAmount.value.replace(/[^0-9]/g, ''), 10)

  return isNaN(parsed) ? 0 : parsed
})

// Formatted display
const formattedDisplay = computed(() => {
  if (numericAmount.value === 0) {
    return '0'
  }

  return new Intl.NumberFormat('id-ID').format(numericAmount.value)
})

// Initialize balance and category on open
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      rawAmount.value = ''
      description.value = ''
      showNoteInput.value = false
      showBalancePicker.value = false

      if (!selectedBalanceId.value) {
        selectedBalanceId.value =
          props.primaryBalanceId ?? props.balances[0]?.id ?? null
      }

      if (currentCategories.value.length > 0 && !selectedCategoryId.value) {
        selectedCategoryId.value = currentCategories.value[0].id
      }
    }
  },
  { immediate: true },
)

function vibrate() {
  if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
    try {
      navigator.vibrate(12)
    } catch {
      // ignore
    }
  }
}

// Numpad actions
function pressKey(key: string) {
  vibrate()

  if (key === 'BACKSPACE') {
    rawAmount.value = rawAmount.value.slice(0, -1)

    return
  }

  if (key === '000') {
    if (rawAmount.value && rawAmount.value !== '0') {
      rawAmount.value += '000'
    }

    return
  }

  if (rawAmount.value.length >= 10) {
    return
  } // Max 10 digits (~9.9 billion IDR)

  if (rawAmount.value === '0') {
    rawAmount.value = key
  } else {
    rawAmount.value += key
  }
}

function selectCategory(catId: number) {
  vibrate()
  selectedCategoryId.value = catId
}

function selectBalance(balId: number) {
  vibrate()
  selectedBalanceId.value = balId
  showBalancePicker.value = false
}

function close() {
  emit('update:open', false)
}

function submit() {
  if (numericAmount.value <= 0) {
    toast.error('Masukkan nominal pengeluaran.')

    return
  }

  if (!selectedBalanceId.value) {
    toast.error('Pilih akun rekening sumber.')

    return
  }

  if (!selectedCategoryId.value) {
    toast.error('Pilih kategori pengeluaran.')

    return
  }

  isSubmitting.value = true
  vibrate()

  const form = useForm({
    balance_id: selectedBalanceId.value,
    budget_id: props.activeBudgetId ?? null,
    category_id: selectedCategoryId.value,
    amount: numericAmount.value,
    type: type.value,
    description: description.value.trim(),
    date: new Date().toISOString().split('T')[0],
  })

  form.post(storeTransaction.url(), {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false
      toast.success(
        `Berhasil mencatat ${type.value === 'expense' ? 'pengeluaran' : 'pemasukan'} Rp ${formattedDisplay.value}!`,
      )
      close()
    },
    onError: () => {
      isSubmitting.value = false
      toast.error('Gagal mencatat transaksi. Periksa kembali form.')
    },
  })
}

// Keyboard listener for desktop testing
function onKeydown(e: KeyboardEvent) {
  if (!props.open) {
    return
  }

  if (e.key === 'Escape') {
    close()

    return
  }

  if (showNoteInput.value) {
    return
  } // Allow typing in description input

  if (e.key >= '0' && e.key <= '9') {
    pressKey(e.key)
  } else if (e.key === 'Backspace') {
    pressKey('BACKSPACE')
  } else if (e.key === 'Enter') {
    e.preventDefault()
    submit()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop overlay -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm"
        @click="close"
      />
    </Transition>

    <!-- Bottom Sheet Container -->
    <Transition
      enter-active-class="transition duration-250 cubic-bezier(0.16, 1, 0.3, 1)"
      enter-from-class="translate-y-full"
      enter-to-class="translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-y-0"
      leave-to-class="translate-y-full"
    >
      <div
        v-if="open"
        class="safe-bottom fixed inset-x-0 bottom-0 z-50 flex max-h-[92vh] flex-col rounded-t-[28px] border-t border-[#1f222e] bg-[#0a0a0c] text-zinc-100 shadow-2xl"
        @click.stop
      >
        <!-- Drag pill handle -->
        <div class="mx-auto mt-3 h-1.5 w-12 rounded-full bg-zinc-800" />

        <!-- Header Controls: Type Toggle & Close -->
        <div class="flex items-center justify-between px-6 pt-2 pb-1">
          <div
            class="flex items-center gap-1 rounded-xl border border-[#1f222e] bg-[#121217] p-1"
          >
            <button
              type="button"
              class="rounded-lg px-3 py-1 text-xs font-semibold tracking-wider transition-all"
              :class="
                type === 'expense'
                  ? 'border border-rose-500/40 bg-rose-500/20 text-rose-400 shadow-sm'
                  : 'text-zinc-400 hover:text-zinc-200'
              "
              @click="type = 'expense'"
            >
              EXPENSE
            </button>
            <button
              type="button"
              class="rounded-lg px-3 py-1 text-xs font-semibold tracking-wider transition-all"
              :class="
                type === 'income'
                  ? 'border border-emerald-500/40 bg-emerald-500/20 text-emerald-400 shadow-sm'
                  : 'text-zinc-400 hover:text-zinc-200'
              "
              @click="type = 'income'"
            >
              INCOME
            </button>
          </div>

          <button
            type="button"
            class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-zinc-400 transition-colors hover:text-zinc-100"
            @click="close"
          >
            <X class="size-5" />
          </button>
        </div>

        <!-- Giant Amount Monospace Display -->
        <div class="flex flex-col items-center justify-center px-6 py-4">
          <span
            class="mb-1 font-mono text-xs tracking-widest text-zinc-500 uppercase"
          >
            {{
              type === 'expense' ? 'Nominal Pengeluaran' : 'Nominal Pemasukan'
            }}
          </span>
          <div class="flex items-baseline justify-center gap-2">
            <span
              class="font-mono text-2xl font-bold tracking-tight"
              :class="type === 'expense' ? 'text-rose-400' : 'text-emerald-400'"
            >
              Rp
            </span>
            <span
              class="font-mono text-4xl font-extrabold tracking-tight tabular-nums sm:text-5xl"
              :class="
                numericAmount > 0
                  ? type === 'expense'
                    ? 'text-glow-rose text-rose-400'
                    : 'text-glow-emerald text-emerald-400'
                  : 'text-zinc-600'
              "
            >
              {{ formattedDisplay }}
            </span>
            <span
              class="animate-pulse font-mono text-3xl font-light text-emerald-400"
              >_</span
            >
          </div>

          <!-- Optional description trigger / active note -->
          <div class="mt-2 flex items-center justify-center">
            <button
              v-if="!showNoteInput && !description"
              type="button"
              class="flex items-center gap-1.5 text-xs text-zinc-500 transition-colors hover:text-emerald-400"
              @click="showNoteInput = true"
            >
              <Plus class="size-3.5" />
              <span>Tambah catatan / keterangan</span>
            </button>
            <div
              v-else-if="!showNoteInput && description"
              class="flex items-center gap-2"
            >
              <span
                class="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 font-mono text-xs text-emerald-400 italic"
              >
                "{{ description }}"
              </span>
              <button
                type="button"
                class="text-xs text-zinc-500 hover:text-zinc-300"
                @click="showNoteInput = true"
              >
                Ubah
              </button>
            </div>
            <div v-else class="flex w-full max-w-xs items-center gap-2">
              <input
                v-model="description"
                type="text"
                placeholder="Catatan (misal: Makan siang Bu Imas)..."
                class="w-full rounded-lg border border-[#1f222e] bg-[#121217] px-3 py-1.5 text-xs text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none"
                autofocus
                @keydown.enter.prevent="showNoteInput = false"
              />
              <button
                type="button"
                class="rounded-lg bg-zinc-800 px-2.5 py-1.5 text-xs font-semibold text-zinc-300"
                @click="showNoteInput = false"
              >
                OK
              </button>
            </div>
          </div>
        </div>

        <!-- 1-Tap Category Chips (Horizontal Scroll) -->
        <div class="border-y border-[#1f222e] bg-[#121217]/50 px-4 py-2.5">
          <div
            class="no-scrollbar flex items-center gap-2 overflow-x-auto py-0.5"
          >
            <button
              v-for="cat in currentCategories"
              :key="cat.id"
              type="button"
              class="flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-medium transition-all"
              :class="
                selectedCategoryId === cat.id
                  ? 'border border-emerald-500 bg-emerald-500/20 text-emerald-300 shadow-[0_0_15px_rgba(16,185,129,0.25)]'
                  : 'border border-[#1f222e] bg-[#181820] text-zinc-400 hover:text-zinc-200'
              "
              @click="selectCategory(cat.id)"
            >
              <span>{{ cat.name }}</span>
            </button>
          </div>
        </div>

        <!-- Account Selector Pill & Picker Sheet -->
        <div
          class="flex items-center justify-between border-b border-[#1f222e] bg-[#0a0a0c] px-6 py-2.5"
        >
          <span class="font-mono text-xs text-zinc-500">Sumber Rekening:</span>
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-1.5 rounded-lg border border-[#1f222e] bg-[#121217] px-3 py-1.5 font-mono text-xs text-zinc-200 transition-colors hover:border-emerald-500/50"
              @click="showBalancePicker = !showBalancePicker"
            >
              <span class="font-semibold text-emerald-400">{{
                activeBalance?.name || 'Pilih Rekening'
              }}</span>
              <span v-if="activeBalance" class="text-zinc-500">
                (Rp
                {{
                  new Intl.NumberFormat('id-ID').format(
                    activeBalance.final_amount,
                  )
                }})
              </span>
              <ChevronDown class="size-3.5 text-zinc-500" />
            </button>

            <!-- Inline Picker Dropdown -->
            <div
              v-if="showBalancePicker"
              class="absolute right-0 bottom-full z-50 mb-2 w-56 rounded-xl border border-[#1f222e] bg-[#181820] p-1.5 shadow-2xl"
            >
              <div
                v-for="bal in balances"
                :key="bal.id"
                class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-xs transition-colors"
                :class="
                  selectedBalanceId === bal.id
                    ? 'bg-emerald-500/20 text-emerald-300'
                    : 'text-zinc-300 hover:bg-zinc-800/60'
                "
                @click="selectBalance(bal.id)"
              >
                <span class="font-medium">{{ bal.name }}</span>
                <span class="font-mono text-[11px] text-zinc-400">
                  Rp
                  {{ new Intl.NumberFormat('id-ID').format(bal.final_amount) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tactile 4x3 Calculator Keypad -->
        <div class="grid grid-cols-3 gap-2 px-6 pt-3 pb-2">
          <button
            v-for="k in [
              '1',
              '2',
              '3',
              '4',
              '5',
              '6',
              '7',
              '8',
              '9',
              '000',
              '0',
              'BACKSPACE',
            ]"
            :key="k"
            type="button"
            class="flex h-13 items-center justify-center rounded-xl border border-[#1f222e] bg-[#121217] font-mono text-xl font-semibold text-zinc-200 transition-all hover:border-zinc-700 active:scale-95 active:bg-zinc-800"
            @click="pressKey(k)"
          >
            <Delete v-if="k === 'BACKSPACE'" class="size-6 text-zinc-400" />
            <span
              v-else-if="k === '000'"
              class="text-sm tracking-wider text-emerald-400"
              >000</span
            >
            <span v-else>{{ k }}</span>
          </button>
        </div>

        <!-- Full-Width Bottom Action Button -->
        <div class="px-6 pt-2 pb-6">
          <button
            type="button"
            :disabled="numericAmount <= 0 || isSubmitting"
            class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl font-mono text-sm font-bold tracking-wider uppercase transition-all disabled:opacity-40"
            :class="
              type === 'expense'
                ? 'bg-rose-500 text-[#0a0a0c] shadow-[0_0_25px_rgba(244,63,94,0.35)] active:scale-[0.98]'
                : 'bg-emerald-500 text-[#0a0a0c] shadow-[0_0_25px_rgba(16,185,129,0.35)] active:scale-[0.98]'
            "
            @click="submit"
          >
            <span v-if="!isSubmitting">
              Simpan {{ type === 'expense' ? 'Pengeluaran' : 'Pemasukan' }} (Rp
              {{ formattedDisplay }})
            </span>
            <span v-else class="animate-pulse">Menyimpan...</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.text-glow-emerald {
  text-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
}
.text-glow-rose {
  text-shadow: 0 0 25px rgba(244, 63, 94, 0.4);
}
</style>
