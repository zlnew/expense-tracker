<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { CheckCircle2, ShieldCheck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import AuthLayout from '@/layouts/AuthLayout.vue'

type Client = {
  id: string
  name: string
}

const props = defineProps<{
  client: Client
  redirect_uri: string
  state: string | null
  scope: string | null
  code_challenge?: string | null
  code_challenge_method?: string | null
}>()

const form = useForm({
  client_id: props.client.id,
  redirect_uri: props.redirect_uri,
  state: props.state ?? '',
  scope: props.scope ?? 'mcp',
  code_challenge: props.code_challenge ?? '',
  code_challenge_method: props.code_challenge_method ?? '',
  action: 'approve',
})

function submit(action: 'approve' | 'deny') {
  form.action = action
  form.post('/oauth/authorize')
}
</script>

<template>
  <AuthLayout
    :title="`Authorize ${client.name}`"
    description="This application is requesting access to your Expense Tracker account."
  >
    <Head :title="`Authorize ${client.name}`" />

    <div class="space-y-6">
      <div
        class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-xs"
      >
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
          >
            <ShieldCheck class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-sm font-semibold">{{ client.name }}</h3>
            <p class="text-xs break-all text-muted-foreground">
              {{ redirect_uri }}
            </p>
          </div>
        </div>

        <div class="mt-4 border-t border-border pt-4">
          <p class="text-xs font-medium text-foreground">
            Permissions requested:
          </p>
          <ul class="mt-2 space-y-2 text-xs text-muted-foreground">
            <li class="flex items-start gap-2">
              <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-income" />
              <span
                >Read and manage financial accounts and real spendable
                balances</span
              >
            </li>
            <li class="flex items-start gap-2">
              <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-income" />
              <span
                >Query and record expenses, incomes, and account transfers</span
              >
            </li>
            <li class="flex items-start gap-2">
              <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-income" />
              <span
                >Inspect active monthly budgets, categories, and sinking
                funds</span
              >
            </li>
          </ul>
        </div>
      </div>

      <div
        v-if="Object.keys(form.errors).length > 0"
        class="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive"
      >
        <p v-for="(err, key) in form.errors" :key="key">{{ err }}</p>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row">
        <Button
          type="button"
          class="w-full sm:flex-1"
          :disabled="form.processing"
          @click="submit('approve')"
        >
          <Spinner
            v-if="form.processing && form.action === 'approve'"
            class="mr-2 h-4 w-4"
          />
          Authorize {{ client.name }}
        </Button>
        <Button
          type="button"
          variant="outline"
          class="w-full sm:flex-1"
          :disabled="form.processing"
          @click="submit('deny')"
        >
          <Spinner
            v-if="form.processing && form.action === 'deny'"
            class="mr-2 h-4 w-4"
          />
          Cancel
        </Button>
      </div>

      <p class="text-center text-[11px] text-muted-foreground">
        You can revoke access at any time in your Expense Tracker settings.
      </p>
    </div>
  </AuthLayout>
</template>
