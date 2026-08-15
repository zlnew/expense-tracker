<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'

type LinkType = {
  url: string | null
  label: string
  active: boolean
}

type Meta = {
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

const props = defineProps<{
  meta: Meta
  links: LinkType[]
}>()

const { __ } = useLang()

// Mobile shows a sliding window around the current page (the full page list
// is desktop-only). Links whose label is not a plain page number (ellipsis,
// "Previous"/"Next") are excluded from the numbered window.
const mobilePageLinks = computed(() => {
  const current = props.meta.current_page
  const windowSize = 1

  return props.links
    .slice(1, -1)
    .filter((link) => /^\d+$/.test(link.label.trim()))
    .filter((link) => Math.abs(Number(link.label) - current) <= windowSize)
})
</script>

<template>
  <div v-if="meta.total > 0" class="flex items-center justify-between px-2">
    <div class="flex-1 text-sm text-muted-foreground">
      <span class="sm:hidden">
        {{ meta.from }}–{{ meta.to }} {{ __('of') }} {{ meta.total }}
      </span>
      <span class="hidden sm:inline">
        {{
          __('showing_results', {
            from: meta.from,
            to: meta.to,
            total: meta.total,
          })
        }}
      </span>
    </div>
    <div class="flex items-center space-x-6 lg:space-x-8">
      <div class="flex items-center space-x-2">
        <Button
          variant="outline"
          class="size-10 p-0 md:size-8"
          :disabled="meta.current_page === 1"
          as-child
        >
          <Link
            v-if="meta.current_page > 1"
            :href="meta.prev_page_url"
            preserve-scroll
          >
            <span class="sr-only">{{ __('go_to_previous_page') }}</span>
            <ChevronLeft class="size-4" />
          </Link>
          <button v-else disabled type="button">
            <span class="sr-only">{{ __('go_to_previous_page') }}</span>
            <ChevronLeft class="size-4" />
          </button>
        </Button>
        <!-- Mobile: sliding window of numbered pages -->
        <Button
          v-for="link in mobilePageLinks"
          :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          class="size-10 p-0 md:hidden"
          as-child
        >
          <Link v-if="link.url" :href="link.url" preserve-scroll>
            {{ link.label }}
          </Link>
          <span v-else v-html="link.label"></span>
        </Button>
        <!-- Desktop: full page list -->
        <Button
          v-for="link in links.slice(1, -1)"
          :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          class="hidden size-8 p-0 md:inline-flex"
          as-child
        >
          <Link v-if="link.url" :href="link.url" preserve-scroll>
            {{ link.label }}
          </Link>
          <span v-else v-html="link.label"></span>
        </Button>
        <Button
          variant="outline"
          class="size-10 p-0 md:size-8"
          :disabled="meta.current_page === meta.last_page"
          as-child
        >
          <Link
            v-if="meta.current_page < meta.last_page"
            :href="links[links.length - 1].url ?? ''"
            preserve-scroll
          >
            <span class="sr-only">{{ __('go_to_next_page') }}</span>
            <ChevronRight class="size-4" />
          </Link>
          <button v-else disabled type="button">
            <span class="sr-only">{{ __('go_to_next_page') }}</span>
            <ChevronRight class="size-4" />
          </button>
        </Button>
      </div>
    </div>
  </div>
</template>
