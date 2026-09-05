<script setup lang="ts">
import { Check, Copy, Key, Plus, Trash2 } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Token = {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

type CreatedToken = {
  id: number
  name: string
  plainTextToken: string
}

const props = withDefaults(
  defineProps<{
    initialTokens?: Token[]
  }>(),
  {
    initialTokens: () => [],
  },
)

const tokens = ref<Token[]>(
  props.initialTokens.length > 0 ? [...props.initialTokens] : [],
)
const isLoading = ref(false)
const isSubmitting = ref(false)
const showCreateDialog = ref(false)

const form = ref({
  name: '',
  fullAccess: true,
  abilities: [] as string[],
})

const availableAbilities = [
  { id: 'transactions:read', label: 'Read Transactions' },
  { id: 'transactions:write', label: 'Write Transactions' },
  { id: 'balances:read', label: 'Read Balances' },
  { id: 'balances:write', label: 'Write Balances' },
  { id: 'budgets:read', label: 'Read Budgets' },
  { id: 'budgets:write', label: 'Write Budgets' },
  { id: 'categories:read', label: 'Read Categories' },
  { id: 'categories:write', label: 'Write Categories' },
  { id: 'funds:read', label: 'Read Sinking Funds' },
  { id: 'funds:write', label: 'Write Sinking Funds' },
  { id: 'recurring_transactions:read', label: 'Read Recurring Schedules' },
  { id: 'recurring_transactions:write', label: 'Write Recurring Schedules' },
]

const errorMessage = ref('')
const createdToken = ref<CreatedToken | null>(null)
const copiedToken = ref(false)

async function fetchTokens() {
  isLoading.value = true

  try {
    const res = await fetch('/settings/personal-access-tokens', {
      headers: { Accept: 'application/json' },
    })

    if (res.ok) {
      tokens.value = await res.json()
    }
  } catch {
    // silent catch
  } finally {
    isLoading.value = false
  }
}

async function createToken() {
  if (!form.value.name.trim()) {
    errorMessage.value = 'Please provide a name for this token.'

    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  const payload: { name: string; abilities?: string[] } = {
    name: form.value.name.trim(),
  }

  if (!form.value.fullAccess) {
    if (form.value.abilities.length === 0) {
      errorMessage.value =
        'Please select at least one permission or choose Full Access.'
      isSubmitting.value = false

      return
    }

    payload.abilities = form.value.abilities
  } else {
    payload.abilities = ['*']
  }

  try {
    const res = await fetch('/settings/personal-access-tokens', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(payload),
    })

    if (!res.ok) {
      const data = await res.json()
      errorMessage.value =
        data.message || 'Failed to create personal access token.'

      return
    }

    const data = await res.json()
    createdToken.value = {
      id: data.token.id,
      name: data.token.name,
      plainTextToken: data.plainTextToken,
    }

    form.value = { name: '', fullAccess: true, abilities: [] }
    showCreateDialog.value = false
    await fetchTokens()
  } catch {
    errorMessage.value = 'Network error. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}

async function deleteToken(id: number) {
  if (
    !confirm(
      'Are you sure you want to revoke this personal access token? Any client or script using it will immediately lose access.',
    )
  ) {
    return
  }

  try {
    const res = await fetch(`/settings/personal-access-tokens/${id}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })

    if (res.ok) {
      tokens.value = tokens.value.filter((t) => t.id !== id)
    }
  } catch {
    // silent catch
  }
}

function toggleAbility(id: string) {
  const idx = form.value.abilities.indexOf(id)

  if (idx === -1) {
    form.value.abilities.push(id)
  } else {
    form.value.abilities.splice(idx, 1)
  }
}

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : ''
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text)
  copiedToken.value = true
  setTimeout(() => (copiedToken.value = false), 2000)
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) {
    return 'Never'
  }

  try {
    return new Date(dateStr).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return dateStr
  }
}

onMounted(() => {
  if (tokens.value.length === 0) {
    fetchTokens()
  }
})
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <Heading
        variant="small"
        title="Personal Access Tokens"
        description="Generate API tokens to access your expense tracker from CLI scripts, agents, and local MCP tools like Antigravity."
      />
      <Button size="sm" @click="showCreateDialog = true">
        <Plus class="mr-1.5 h-4 w-4" />
        Generate Token
      </Button>
    </div>

    <!-- Newly Created Token Banner -->
    <div
      v-if="createdToken"
      class="space-y-3 rounded-xl border border-income/30 bg-income/10 p-4 text-foreground"
    >
      <div class="flex items-center justify-between">
        <h4 class="flex items-center gap-2 text-sm font-semibold">
          <Key class="h-4 w-4 text-income" />
          New Personal Access Token for {{ createdToken.name }}
        </h4>
        <Button
          size="sm"
          variant="ghost"
          class="h-7 text-xs"
          @click="createdToken = null"
        >
          Dismiss
        </Button>
      </div>
      <p class="text-xs text-muted-foreground">
        Make sure to copy your personal access token now. You won't be able to
        see it again!
      </p>
      <div>
        <div class="flex items-center gap-2">
          <code
            class="flex-1 rounded border border-border bg-background px-2.5 py-1.5 font-mono text-xs break-all select-all"
          >
            {{ createdToken.plainTextToken }}
          </code>
          <Button
            size="sm"
            variant="outline"
            class="shrink-0"
            @click="copyToClipboard(createdToken.plainTextToken)"
          >
            <Check v-if="copiedToken" class="h-3.5 w-3.5 text-income" />
            <Copy v-else class="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>
    </div>

    <!-- Tokens List -->
    <div
      v-if="tokens.length > 0"
      class="divide-y divide-border rounded-xl border border-border bg-card"
    >
      <div
        v-for="t in tokens"
        :key="t.id"
        class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <p class="text-sm font-medium">{{ t.name }}</p>
            <span
              v-if="t.abilities.includes('*')"
              class="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-[11px] font-medium text-secondary-foreground"
            >
              Full Access
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-[11px] font-medium text-secondary-foreground"
            >
              {{ t.abilities.length }} permissions
            </span>
          </div>
          <div
            class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
          >
            <span>Created: {{ formatDate(t.created_at) }}</span>
            <span>•</span>
            <span
              >Last used:
              {{ t.last_used_at ? formatDate(t.last_used_at) : 'Never' }}</span
            >
          </div>
        </div>
        <div class="flex items-center gap-2 self-end sm:self-auto">
          <Button
            size="sm"
            variant="ghost"
            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
            title="Revoke Token"
            @click="deleteToken(t.id)"
          >
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
    <div
      v-else-if="!isLoading"
      class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
    >
      No personal access tokens generated yet. Click "Generate Token" to create
      an API token for Antigravity or automation scripts.
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="showCreateDialog">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Generate Personal Access Token</DialogTitle>
          <DialogDescription>
            Create an API token to authenticate scripts, CLI agents, or local
            MCP clients with your account.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-2">
          <div
            v-if="errorMessage"
            class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive"
          >
            {{ errorMessage }}
          </div>

          <div class="space-y-2">
            <Label for="tokenName">Token Name</Label>
            <Input
              id="tokenName"
              v-model="form.name"
              placeholder="e.g. Antigravity MCP, Automation Script"
            />
          </div>

          <div class="space-y-3">
            <Label>Permissions</Label>
            <div class="flex items-center space-x-2">
              <Checkbox
                id="fullAccess"
                :checked="form.fullAccess"
                @update:checked="(val: boolean) => (form.fullAccess = val)"
              />
              <Label
                for="fullAccess"
                class="cursor-pointer text-xs font-normal"
              >
                Full Access (allow all current and future API/MCP actions)
              </Label>
            </div>

            <div
              v-if="!form.fullAccess"
              class="grid grid-cols-1 gap-2 pt-2 sm:grid-cols-2"
            >
              <div
                v-for="ability in availableAbilities"
                :key="ability.id"
                class="flex items-center space-x-2"
              >
                <Checkbox
                  :id="ability.id"
                  :checked="form.abilities.includes(ability.id)"
                  @update:checked="() => toggleAbility(ability.id)"
                />
                <Label
                  :for="ability.id"
                  class="cursor-pointer text-xs font-normal"
                >
                  {{ ability.label }}
                </Label>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showCreateDialog = false">
            Cancel
          </Button>
          <Button :disabled="isSubmitting" @click="createToken">
            Generate Token
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
