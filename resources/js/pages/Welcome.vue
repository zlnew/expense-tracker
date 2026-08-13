<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  ArrowLeftRight,
  ArrowRight,
  BarChart3,
  PiggyBank,
  Wallet,
} from 'lucide-vue-next'
import AppLogo from '@/components/AppLogo.vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { dashboard, login, register } from '@/routes'

withDefaults(
  defineProps<{
    canRegister: boolean
  }>(),
  {
    canRegister: true,
  },
)
</script>

<template>
  <Head title="Welcome" />

  <div class="flex min-h-dvh flex-col bg-background text-foreground">
    <!-- Top nav -->
    <header
      class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur"
    >
      <nav
        class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
      >
        <Link :href="dashboard()" class="flex min-w-0 items-center">
          <AppLogo />
        </Link>

        <div class="flex shrink-0 items-center gap-2">
          <template v-if="$page.props.auth.user">
            <Button asChild>
              <Link :href="dashboard()">Dashboard</Link>
            </Button>
          </template>
          <template v-else>
            <Button variant="ghost" asChild>
              <Link :href="login()">Log in</Link>
            </Button>
            <Button v-if="canRegister" asChild>
              <Link :href="register()">Register</Link>
            </Button>
          </template>
        </div>
      </nav>
    </header>

    <main class="flex flex-1 flex-col">
      <!-- Hero -->
      <section
        class="flex flex-col items-center justify-center px-4 py-10 text-center sm:py-16"
      >
        <span
          class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground"
        >
          Simple expense tracking
        </span>

        <h1
          class="max-w-xl text-3xl font-bold tracking-tight text-balance sm:text-5xl"
        >
          Track every rupiah
        </h1>

        <p
          class="mt-3 max-w-md text-sm text-pretty text-muted-foreground sm:text-base"
        >
          Know exactly where your money goes. Keep balances in check, stick to
          budgets, and understand your spending at a glance.
        </p>

        <div
          class="mt-6 flex w-full max-w-xs flex-col gap-3 sm:w-auto sm:max-w-none sm:flex-row"
        >
          <Button asChild size="lg" class="w-full sm:w-auto">
            <Link
              :href="
                $page.props.auth.user
                  ? dashboard()
                  : canRegister
                    ? register()
                    : login()
              "
            >
              <template v-if="$page.props.auth.user">Go to dashboard</template>
              <template v-else>Get started</template>
              <ArrowRight class="size-4" />
            </Link>
          </Button>
          <Button asChild size="lg" variant="outline" class="w-full sm:w-auto">
            <Link :href="login()">Log in</Link>
          </Button>
        </div>
      </section>

      <!-- Features -->
      <section class="mx-auto w-full max-w-6xl px-4 pb-14 sm:px-6 sm:pb-20">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card class="gap-3 py-5">
            <CardHeader class="px-5">
              <div
                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
              >
                <Wallet class="size-5" />
              </div>
              <CardTitle class="text-sm">Multiple balances</CardTitle>
            </CardHeader>
            <CardContent class="px-5">
              <CardDescription class="text-xs leading-relaxed">
                Track cash, bank accounts, and e-wallets side by side in one
                place.
              </CardDescription>
            </CardContent>
          </Card>

          <Card class="gap-3 py-5">
            <CardHeader class="px-5">
              <div
                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
              >
                <PiggyBank class="size-5" />
              </div>
              <CardTitle class="text-sm">Budgets that stick</CardTitle>
            </CardHeader>
            <CardContent class="px-5">
              <CardDescription class="text-xs leading-relaxed">
                Set monthly budgets by category and see how much is left before
                you overspend.
              </CardDescription>
            </CardContent>
          </Card>

          <Card class="gap-3 py-5">
            <CardHeader class="px-5">
              <div
                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
              >
                <BarChart3 class="size-5" />
              </div>
              <CardTitle class="text-sm">Clear reports</CardTitle>
            </CardHeader>
            <CardContent class="px-5">
              <CardDescription class="text-xs leading-relaxed">
                Understand your spending with charts that show where your money
                really goes.
              </CardDescription>
            </CardContent>
          </Card>

          <Card class="gap-3 py-5">
            <CardHeader class="px-5">
              <div
                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
              >
                <ArrowLeftRight class="size-5" />
              </div>
              <CardTitle class="text-sm">Easy transfers</CardTitle>
            </CardHeader>
            <CardContent class="px-5">
              <CardDescription class="text-xs leading-relaxed">
                Move money between balances without losing track of where it
                went.
              </CardDescription>
            </CardContent>
          </Card>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border/60">
      <div
        class="mx-auto flex w-full max-w-6xl items-center justify-between gap-2 px-4 py-4 text-xs text-muted-foreground sm:px-6"
      >
        <span>{{ $page.props.name }}</span>
        <span>© {{ new Date().getFullYear() }}</span>
      </div>
    </footer>
  </div>
</template>
