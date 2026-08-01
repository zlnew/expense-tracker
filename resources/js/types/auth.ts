import type { Balance, Budget, Transaction } from '@/types'

export type User = {
  id: number
  name: string
  email: string
  avatar?: string
  email_verified_at: string | null
  discord_webhook_url: string | null
  two_factor_confirmed_at: string | null
  active_budget: Budget | null
  budgets: Budget[] | null
  balances: Balance[] | null
  transactions: Transaction[] | null
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export type Auth = {
  user: User
}

export type TwoFactorConfigContent = {
  title: string
  description: string
  buttonText: string
}
