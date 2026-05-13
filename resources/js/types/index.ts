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
  links: string[]
}

export type Balance = {
  id: number
  user_id: number
  name: string
  description: string | null
  initial_amount: number
  final_amount: number
  is_primary: boolean
  user: User | null
  transactions: Transaction[] | null
}

export type Budget = {
  id: number
  user_id: number
  period_start: string
  period_end: string
  is_active: boolean
  notes: string | null
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
  actual_amount: number
  diff_amount: number
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

export type Transaction = {
  id: number
  user_id: number
  balance_id: number
  budget_id: number
  budget_item_id: number
  category_id: number
  type: string
  date: string
  amount: number
  description: string | null
  user: User | null
  balance: Balance | null
  budget: Budget | null
  budget_item: BudgetItem | null
  category: Category | null
}
