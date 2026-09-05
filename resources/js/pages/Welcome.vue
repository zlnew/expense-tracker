<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  ArrowRight,
  Gauge,
  ShieldCheck,
  Smartphone,
  Wallet,
} from 'lucide-vue-next'
import AppLogo from '@/components/AppLogo.vue'
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'
import { dashboard, login, register } from '@/routes'

withDefaults(
  defineProps<{
    canRegister: boolean
  }>(),
  {
    canRegister: true,
  },
)

const { __, currentLocale, setLocale } = useLang()
</script>

<template>
  <Head :title="__('welcome_title')" />

  <div
    class="flex min-h-dvh flex-col bg-background text-foreground selection:bg-emerald-500/20 selection:text-emerald-400"
  >
    <!-- Top Nav -->
    <header
      class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur-md"
    >
      <nav
        class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
      >
        <Link :href="dashboard()" class="flex min-w-0 items-center">
          <AppLogo />
        </Link>

        <div class="flex shrink-0 items-center gap-2.5 sm:gap-3">
          <!-- System Online Beacon -->
          <div
            class="hidden items-center gap-2 border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 font-mono text-[10px] font-semibold text-emerald-600 sm:flex dark:text-emerald-400"
          >
            <span class="relative flex size-2">
              <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
              />
              <span
                class="relative inline-flex size-2 rounded-full bg-emerald-500"
              />
            </span>
            <span>{{ __('system_ready') }}</span>
          </div>

          <!-- Language Selector -->
          <div
            class="flex items-center border border-border bg-card p-0.5 font-mono text-xs"
          >
            <button
              type="button"
              class="px-2 py-0.5 font-semibold transition-colors"
              :class="
                currentLocale === 'id'
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              "
              :aria-label="__('language_id')"
              @click="setLocale('id')"
            >
              ID
            </button>
            <button
              type="button"
              class="px-2 py-0.5 font-semibold transition-colors"
              :class="
                currentLocale === 'en'
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              "
              :aria-label="__('language_en')"
              @click="setLocale('en')"
            >
              EN
            </button>
          </div>

          <!-- Auth Links -->
          <template v-if="$page.props.auth.user">
            <Button
              as-child
              class="rounded-none border border-emerald-500/40 bg-emerald-600 font-mono text-xs font-bold tracking-wider text-white uppercase shadow-[0_0_12px_rgba(16,185,129,0.25)] hover:bg-emerald-500"
            >
              <Link :href="dashboard()">{{ __('dashboard') }}</Link>
            </Button>
          </template>
          <template v-else>
            <Button
              variant="outline"
              as-child
              class="rounded-none border-border font-mono text-xs font-semibold tracking-wider uppercase"
            >
              <Link :href="login()">{{ __('login') }}</Link>
            </Button>
            <Button
              v-if="canRegister"
              as-child
              class="rounded-none border border-emerald-500/40 bg-emerald-600 font-mono text-xs font-bold tracking-wider text-white uppercase shadow-[0_0_12px_rgba(16,185,129,0.25)] hover:bg-emerald-500"
            >
              <Link :href="register()">{{ __('register') }}</Link>
            </Button>
          </template>
        </div>
      </nav>
    </header>

    <main class="flex flex-1 flex-col">
      <!-- Hero Section -->
      <section
        class="relative flex flex-col items-center justify-center overflow-hidden px-4 pt-12 pb-8 text-center sm:pt-20 sm:pb-12"
      >
        <!-- Background grid decoration -->
        <div
          class="pointer-events-none absolute inset-0 bg-[radial-gradient(#10b981_1px,transparent_1px)] [background-size:24px_24px] opacity-15 dark:opacity-10"
        />

        <div class="relative z-10 flex flex-col items-center">
          <!-- Hero Headline -->
          <h1
            class="max-w-3xl font-mono text-3xl font-black tracking-tight text-foreground uppercase sm:text-5xl lg:text-6xl"
          >
            {{ __('welcome_title') }}
          </h1>

          <!-- Hero Subtitle -->
          <p
            class="mt-4 max-w-2xl text-sm leading-relaxed text-pretty text-muted-foreground sm:text-base"
          >
            {{ __('welcome_subtitle') }}
          </p>

          <!-- CTAs -->
          <div
            class="mt-8 flex w-full max-w-md flex-col gap-3 sm:w-auto sm:flex-row"
          >
            <Button
              as-child
              size="lg"
              class="h-12 rounded-none border border-emerald-500/50 bg-emerald-600 px-6 font-mono text-xs font-bold tracking-wider text-white uppercase shadow-[0_0_20px_rgba(16,185,129,0.3)] transition-all hover:bg-emerald-500 hover:shadow-[0_0_25px_rgba(16,185,129,0.5)]"
            >
              <Link
                :href="
                  $page.props.auth.user
                    ? dashboard()
                    : canRegister
                      ? register()
                      : login()
                "
              >
                <span>{{
                  $page.props.auth.user ? __('dashboard') : __('enter_terminal')
                }}</span>
                <ArrowRight class="ml-2 size-4" />
              </Link>
            </Button>
            <Button
              v-if="!$page.props.auth.user"
              as-child
              size="lg"
              variant="outline"
              class="h-12 rounded-none border border-border bg-card/60 px-6 font-mono text-xs font-semibold tracking-wider text-foreground uppercase hover:bg-accent"
            >
              <Link :href="login()">
                {{ __('login') }}
              </Link>
            </Button>
          </div>
        </div>
      </section>

      <!-- Features Matrix -->
      <section class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <div class="mb-8 text-center sm:mb-12">
          <span
            class="font-mono text-[11px] font-bold tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
          >
            ARCHITECTURE // CORE MODULES
          </span>
          <h2
            class="mt-2 font-mono text-2xl font-bold tracking-tight text-foreground uppercase sm:text-3xl"
          >
            Built For Financial Discipline
          </h2>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <!-- Feature 1 -->
          <div
            class="group border border-border bg-card p-5 transition-colors hover:border-emerald-500/50 hover:bg-accent/30"
          >
            <div
              class="flex size-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-105 dark:text-emerald-400"
            >
              <Wallet class="size-5" />
            </div>
            <h3
              class="mt-4 font-mono text-sm font-bold tracking-wider text-foreground uppercase"
            >
              {{ __('landing_feature_1_title') }}
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
              {{ __('landing_feature_1_desc') }}
            </p>
          </div>

          <!-- Feature 2 -->
          <div
            class="group border border-border bg-card p-5 transition-colors hover:border-emerald-500/50 hover:bg-accent/30"
          >
            <div
              class="flex size-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-105 dark:text-emerald-400"
            >
              <ShieldCheck class="size-5" />
            </div>
            <h3
              class="mt-4 font-mono text-sm font-bold tracking-wider text-foreground uppercase"
            >
              {{ __('landing_feature_2_title') }}
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
              {{ __('landing_feature_2_desc') }}
            </p>
          </div>

          <!-- Feature 3 -->
          <div
            class="group border border-border bg-card p-5 transition-colors hover:border-emerald-500/50 hover:bg-accent/30"
          >
            <div
              class="flex size-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-105 dark:text-emerald-400"
            >
              <Gauge class="size-5" />
            </div>
            <h3
              class="mt-4 font-mono text-sm font-bold tracking-wider text-foreground uppercase"
            >
              {{ __('landing_feature_3_title') }}
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
              {{ __('landing_feature_3_desc') }}
            </p>
          </div>

          <!-- Feature 4 -->
          <div
            class="group border border-border bg-card p-5 transition-colors hover:border-emerald-500/50 hover:bg-accent/30"
          >
            <div
              class="flex size-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-105 dark:text-emerald-400"
            >
              <Smartphone class="size-5" />
            </div>
            <h3
              class="mt-4 font-mono text-sm font-bold tracking-wider text-foreground uppercase"
            >
              {{ __('landing_feature_4_title') }}
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
              {{ __('landing_feature_4_desc') }}
            </p>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border bg-background">
      <div
        class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 font-mono text-xs text-muted-foreground sm:flex-row sm:px-6"
      >
        <div class="flex items-center gap-2">
          <span class="font-bold text-foreground uppercase">{{
            $page.props.name
          }}</span>
          <span>// {{ __('personal_financial_terminal') }}</span>
        </div>
        <div>
          <span>© {{ new Date().getFullYear() }} — ALL RIGHTS RESERVED</span>
        </div>
      </div>
    </footer>
  </div>
</template>
