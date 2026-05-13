<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import AppContent from '@/components/AppContent.vue'
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardAction,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from '@/components/ui/number-field'
import { Separator } from '@/components/ui/separator'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useLang } from '@/composables/useLang'
import { useNumber } from '@/composables/useNumber'
import { index as budgetIndex, store as budgetStore } from '@/routes/budgets'
import type { BudgetItem, Category } from '@/types'

const props = defineProps<{
  categories: Category[]
}>()

const { __ } = useLang()
const { formatNumber } = useNumber()

setLayoutProps({
  breadcrumbs: [
    {
      title: __('budgets'),
      href: budgetIndex.url(),
    },
    {
      title: __('create'),
    },
  ],
})

type Form = {
  period_start: string
  period_end: string
  notes?: string
  items: any[]
}

const form = useForm({
  period_start: '',
  period_end: '',
  notes: undefined,
  items: [] as any[],
} satisfies Form)

const expenseBudgetItems = ref<Partial<BudgetItem>[]>([])
const incomeBudgetItems = ref<Partial<BudgetItem>[]>([])

const expenseCategories = computed(() =>
  props.categories.filter((c) => c.type === 'expense'),
)

const incomeCategories = computed(() =>
  props.categories.filter((c) => c.type === 'income'),
)

const expenseTotal = computed(() =>
  expenseBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

const incomeTotal = computed(() =>
  incomeBudgetItems.value.reduce(
    (acc, curr) => acc + (curr.planned_amount ?? 0),
    0,
  ),
)

onMounted(() => {
  initBudgetItems()
})

const initBudgetItems = () => {
  expenseCategories.value.forEach((ec) => {
    expenseBudgetItems.value.push({
      category_id: ec.id,
      type: ec.type,
      planned_amount: 0,
      category: ec,
    })
  })

  incomeCategories.value.forEach((ic) => {
    incomeBudgetItems.value.push({
      category_id: ic.id,
      type: ic.type,
      planned_amount: 0,
      category: ic,
    })
  })
}

const submit = () => {
  form.items = [...expenseBudgetItems.value, ...incomeBudgetItems.value]

  form.post(budgetStore.url(), {
    onSuccess: (res) => {
      form.reset()
      toast.success(res.props.success as string)
    },
  })
}
</script>

<template>
  <Head :title="__('create_data', { data: __('budget') })" />

  <AppContent>
    <div class="space-y-6 px-4 py-6 md:px-8">
      <Heading
        :title="__('create_data', { data: __('budget') })"
        :description="__('budget_create_description')"
      />

      <form @submit.prevent="submit">
        <div class="mb-12 space-y-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid max-w-md grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label for="period_start">
                {{ __('period_start') }} <span class="text-destructive">*</span>
              </Label>
              <Input
                id="period_start"
                type="date"
                v-model="form.period_start"
                required
              />
            </div>

            <div class="grid gap-2">
              <Label for="period_end">
                {{ __('period_end') }} <span class="text-destructive">*</span>
              </Label>
              <Input
                id="period_end"
                type="date"
                v-model="form.period_end"
                required
              />
            </div>
          </div>

          <div class="grid max-w-md gap-2">
            <Label for="notes">
              {{ __('notes') }}
              <span class="text-muted-foreground">({{ __('optional') }})</span>
            </Label>
            <Textarea
              id="notes"
              v-model="form.notes"
              :placeholder="__('notes')"
            />
          </div>

          <Separator />

          <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <Card>
              <CardHeader>
                <CardTitle>{{ __('expenses') }}</CardTitle>
                <CardAction>
                  <span class="font-bold text-destructive">
                    Rp {{ formatNumber(expenseTotal) }}
                  </span>
                </CardAction>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader class="bg-accent">
                    <TableRow>
                      <TableHead>
                        {{ __('category') }}
                      </TableHead>
                      <TableHead class="w-[300px] text-end">
                        {{ __('planned') }}
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow
                      v-for="(exp, index) in expenseBudgetItems"
                      :key="index"
                    >
                      <TableCell>{{ exp.category?.name }}</TableCell>
                      <TableCell class="text-end">
                        <NumberField
                          v-model="exp.planned_amount"
                          :min="0"
                          :step="1000"
                          :stepSnapping="false"
                          :format-options="{
                            style: 'currency',
                            currency: 'IDR',
                            currencyDisplay: 'narrowSymbol',
                            currencySign: 'standard',
                          }"
                        >
                          <NumberFieldContent>
                            <NumberFieldDecrement />
                            <NumberFieldInput />
                            <NumberFieldIncrement />
                          </NumberFieldContent>
                        </NumberField>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{{ __('incomes') }}</CardTitle>
                <CardAction>
                  <span class="font-bold text-destructive">
                    Rp {{ formatNumber(incomeTotal) }}
                  </span>
                </CardAction>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader class="bg-accent">
                    <TableRow>
                      <TableHead>
                        {{ __('category') }}
                      </TableHead>
                      <TableHead class="w-[300px] text-end">
                        {{ __('planned') }}
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow
                      v-for="(inc, index) in incomeBudgetItems"
                      :key="index"
                    >
                      <TableCell>{{ inc.category?.name }}</TableCell>
                      <TableCell>
                        <NumberField
                          v-model="inc.planned_amount"
                          :min="0"
                          :step="1000"
                          :stepSnapping="false"
                          :format-options="{
                            style: 'currency',
                            currency: 'IDR',
                            currencyDisplay: 'narrowSymbol',
                            currencySign: 'standard',
                          }"
                        >
                          <NumberFieldContent>
                            <NumberFieldDecrement />
                            <NumberFieldInput />
                            <NumberFieldIncrement />
                          </NumberFieldContent>
                        </NumberField>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </div>

        <div class="fixed right-4 bottom-4 z-50 md:right-8 md:bottom-8">
          <Button type="submit" size="lg" class="rounded-full shadow-xl">
            <Check />
            <span>{{ __('save') }}</span>
          </Button>
        </div>
      </form>
    </div>
  </AppContent>
</template>
