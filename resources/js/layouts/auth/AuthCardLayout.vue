<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { home } from '@/routes'

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
  >
    <div class="flex w-full max-w-md flex-col gap-6">
      <Link
        :href="home()"
        class="flex items-center gap-2 self-center font-medium"
      >
        <div
          class="flex h-10 w-10 items-center justify-center border border-emerald-500/40 bg-emerald-500/10 shadow-[0_0_10px_rgba(16,185,129,0.15)]"
        >
          <AppLogoIcon
            class="size-6 fill-current text-emerald-600 dark:text-emerald-400"
          />
        </div>
      </Link>

      <div class="flex flex-col gap-6">
        <Card class="rounded-none border-border">
          <CardHeader class="px-6 pt-6 pb-0 text-center sm:px-10 sm:pt-8">
            <CardTitle class="text-xl">{{ title }}</CardTitle>
            <CardDescription>
              {{ description }}
            </CardDescription>
          </CardHeader>
          <CardContent class="px-6 py-6 sm:px-10 sm:py-8">
            <slot />
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
