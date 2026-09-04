<script setup lang="ts">
import { Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
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

type Client = {
  id: string
  name: string
  redirect_uri: string
  created_at: string
}

type CreatedCredentials = {
  id: string
  name: string
  secret: string
}

const clients = ref<Client[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const showCreateDialog = ref(false)

const form = ref({
  name: '',
  redirect_uri: '',
})

const errorMessage = ref('')
const createdCredentials = ref<CreatedCredentials | null>(null)
const copiedId = ref(false)
const copiedSecret = ref(false)

async function fetchClients() {
  isLoading.value = true

  try {
    const res = await fetch('/settings/oauth-clients', {
      headers: { Accept: 'application/json' },
    })

    if (res.ok) {
      clients.value = await res.json()
    }
  } catch {
    // silent catch
  } finally {
    isLoading.value = false
  }
}

async function createClient() {
  if (!form.value.name || !form.value.redirect_uri) {
    errorMessage.value = 'Please provide both an app name and redirect URI.'

    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const res = await fetch('/settings/oauth-clients', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(form.value),
    })

    if (!res.ok) {
      const data = await res.json()
      errorMessage.value = data.message || 'Failed to create OAuth client.'

      return
    }

    const data = await res.json()
    createdCredentials.value = {
      id: data.client.id,
      name: data.client.name,
      secret: data.secret,
    }

    form.value = { name: '', redirect_uri: '' }
    await fetchClients()
  } catch {
    errorMessage.value = 'Network error. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}

async function deleteClient(id: string) {
  if (
    !confirm('Are you sure you want to revoke and delete this connected app?')
  ) {
    return
  }

  try {
    const res = await fetch(`/settings/oauth-clients/${id}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })

    if (res.ok) {
      clients.value = clients.value.filter((c) => c.id !== id)
    }
  } catch {
    // silent catch
  }
}

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : ''
}

function copyToClipboard(text: string, type: 'id' | 'secret') {
  navigator.clipboard.writeText(text)

  if (type === 'id') {
    copiedId.value = true
    setTimeout(() => (copiedId.value = false), 2000)
  } else {
    copiedSecret.value = true
    setTimeout(() => (copiedSecret.value = false), 2000)
  }
}

onMounted(fetchClients)
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <Heading
        variant="small"
        title="Connected Apps & OAuth Clients"
        description="Authorize AI assistants (like Google Gemini) to access your expense tracker via MCP."
      />
      <Button size="sm" @click="showCreateDialog = true">
        <Plus class="mr-1.5 h-4 w-4" />
        Add Connected App
      </Button>
    </div>

    <!-- Newly Created Credentials Banner -->
    <div
      v-if="createdCredentials"
      class="space-y-3 rounded-xl border border-income/30 bg-income/10 p-4 text-foreground"
    >
      <div class="flex items-center justify-between">
        <h4 class="flex items-center gap-2 text-sm font-semibold">
          <KeyRound class="h-4 w-4 text-income" />
          OAuth Credentials for {{ createdCredentials.name }}
        </h4>
        <Button
          size="sm"
          variant="ghost"
          class="h-7 text-xs"
          @click="createdCredentials = null"
        >
          Dismiss
        </Button>
      </div>
      <p class="text-xs text-muted-foreground">
        Copy your Client ID and Secret now. For security, the Client Secret will
        not be shown again.
      </p>
      <div class="space-y-2">
        <div>
          <span class="text-xs font-medium text-muted-foreground"
            >Client ID:</span
          >
          <div class="mt-1 flex items-center gap-2">
            <code
              class="flex-1 rounded border border-border bg-background px-2.5 py-1.5 font-mono text-xs break-all"
            >
              {{ createdCredentials.id }}
            </code>
            <Button
              size="sm"
              variant="outline"
              class="shrink-0"
              @click="copyToClipboard(createdCredentials.id, 'id')"
            >
              <Check v-if="copiedId" class="h-3.5 w-3.5 text-income" />
              <Copy v-else class="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
        <div>
          <span class="text-xs font-medium text-muted-foreground"
            >Client Secret:</span
          >
          <div class="mt-1 flex items-center gap-2">
            <code
              class="flex-1 rounded border border-border bg-background px-2.5 py-1.5 font-mono text-xs break-all"
            >
              {{ createdCredentials.secret }}
            </code>
            <Button
              size="sm"
              variant="outline"
              class="shrink-0"
              @click="copyToClipboard(createdCredentials.secret, 'secret')"
            >
              <Check v-if="copiedSecret" class="h-3.5 w-3.5 text-income" />
              <Copy v-else class="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clients List -->
    <div
      v-if="clients.length > 0"
      class="divide-y divide-border rounded-xl border border-border bg-card"
    >
      <div
        v-for="c in clients"
        :key="c.id"
        class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center"
      >
        <div class="space-y-1">
          <p class="text-sm font-medium">{{ c.name }}</p>
          <p class="font-mono text-xs text-muted-foreground">
            Client ID: {{ c.id }}
          </p>
          <p class="text-xs break-all text-muted-foreground">
            Redirect: {{ c.redirect_uri }}
          </p>
        </div>
        <div class="flex items-center gap-2 self-end sm:self-auto">
          <Button
            size="sm"
            variant="ghost"
            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
            @click="deleteClient(c.id)"
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
      No connected apps registered yet. Click "Add Connected App" to connect
      Google Gemini or other OAuth clients.
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="showCreateDialog">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Add Connected App</DialogTitle>
          <DialogDescription>
            Register a new client (e.g. Google Gemini) to generate OAuth
            credentials.
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
            <Label for="appName">App Name</Label>
            <Input
              id="appName"
              v-model="form.name"
              placeholder="e.g. Google Gemini"
            />
          </div>

          <div class="space-y-2">
            <Label for="redirectUri">Redirect URI</Label>
            <Input
              id="redirectUri"
              v-model="form.redirect_uri"
              placeholder="Paste the redirect URI copied from the app"
            />
            <p class="text-[11px] text-muted-foreground">
              In Gemini's "Set up a custom connected app", tap
              <strong>Copy redirect URI</strong> and paste it here.
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showCreateDialog = false"
            >Cancel</Button
          >
          <Button :disabled="isSubmitting" @click="createClient">
            Generate Credentials
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
