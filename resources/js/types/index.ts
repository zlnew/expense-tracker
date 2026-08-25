import type { User } from '@/types/auth'

export * from './auth'
export * from './navigation'
export * from './ui'

export type LinkType = {
  url: string | null
  label: string
  active: boolean
}

export type Meta = {
  current_page: number
  first_page_url: string
  from: number
  last_page: number
  last_page_url: string
  next_page_url: string
  path: string
  per_page: number
  prev_page_url: string
  to: number
  total: number
}

export type Paginate<T> = {
  data: T[]
  meta: Meta
  links: LinkType[]
}

export type Balance = {
  id: number
  user_id: number
  name: string
  description: string | null
  initial_amount: number
  final_amount: number
  reconciled_amount: number | null
  reconciled_at: string | null
  drift: number | null
  is_drift_flagged: boolean
  is_primary: boolean
  reserved: number
  real: number
  user: User | null
  transactions: Transaction[] | null
}

export type Budget = {
  id: number
  user_id: number
  period_start: string
  period_end: string
  cutoff_day: number
  is_active: boolean
  carry_over: boolean
  notes: string | null
  updated_at: string | null
  user: User | null
  items: BudgetItem[] | null
  expenses: BudgetItem[] | null
  incomes: BudgetItem[] | null
}

export type BudgetItem = {
  id: number
  budget_id: number
  category_id: number
  type: string
  planned_amount: number
  actual_amount?: number
  diff_amount?: number
  budget: Budget | null
  category: Category | null
  transactions: Transaction[] | null
}

export type Category = {
  id: number
  type: string
  name: string
  budget_items: BudgetItem[] | null
  transactions: Transaction[] | null
}

export type SinkingFund = {
  id: number
  user_id: number
  name: string
  target_amount: number
  cadence: 'cycle' | 'monthly'
  contribution_amount: number | null
  category_id: number | null
  from_balance_id: number
  next_due: string | null
  due_interval_months: number
  notes: string | null
  accumulated: number
  percent: number
  status: 'on_track' | 'due_soon' | 'underfunded' | 'overfunded'
  auto_contribution: number
  last_contribution_date: string | null
  category: Category | null
  source_balance: Balance | null
}

export type Transaction = {
  id: number
  user_id: number
  balance_id: number
  budget_id: number | null
  budget_item_id: number | null
  category_id: number | null
  type: string
  date: string
  amount: number
  description: string | null
  cycle_month: number
  cycle_year: number
  user: User | null
  balance: Balance | null
  budget: Budget | null
  budget_item: BudgetItem | null
  category: Category | null
}

// Web-only budgets/{budget}/transactions response: the bare TransactionData
// collection PLUS the envelope extras for client-side actuals (envelope-basis
// spec 2026-08-16). fund.reserved is a per-item map (item_id => set-aside
// sum); fund.payout_transaction_ids are budget-exempt payout transactions to
// exclude from expense item actuals.
export type BudgetTransactionsResponse = {
  transactions: Transaction[]
  fund: {
    reserved: Record<string, number>
    payout_transaction_ids: number[]
  }
}

export type SummaryCards = {
  total_balance: number
  total_active: number
  total_reserved: number
  current_month_expenses: number
  current_month_incomes: number
  budget_remaining: number
  has_active_budget: boolean
  active_budget_id: number | null
}

export type BudgetProgress = BudgetItem[]

export type ExpenseBreakdown = {
  category: string
  amount: number
  percentage: number
}

export type MonthlySpendingTrend = {
  month: number
  income: number
  expense: number
}

export type RecentTransactions = Transaction[]

export type RecurringTransaction = {
  id: number
  user_id: number
  type: string
  balance_id: number
  category_id: number | null
  amount: number
  description: string | null
  frequency: string
  start_date: string
  end_date: string | null
  next_run_date: string
  is_active: boolean
  balance: Balance | null
  category: Category | null
}
