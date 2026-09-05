<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { home } from '@/routes'

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
  >
    <div class="w-full max-w-sm">
      <div class="flex flex-col gap-8">
        <div class="flex flex-col items-center gap-4">
          <Link
            :href="home()"
            class="flex flex-col items-center gap-2 font-medium"
          >
            <div
              class="mb-1 flex h-10 w-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 shadow-[0_0_10px_rgba(16,185,129,0.15)]"
            >
              <AppLogoIcon
                class="size-6 fill-current text-emerald-600 dark:text-emerald-400"
              />
            </div>
            <span class="sr-only">{{ title }}</span>
          </Link>
          <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium">{{ title }}</h1>
            <p class="text-center text-sm text-muted-foreground">
              {{ description }}
            </p>
          </div>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>
