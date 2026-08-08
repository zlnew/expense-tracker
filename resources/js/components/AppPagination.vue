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

// Numbered pages on mobile too, truncated to a ±1 window around the
// current page (the old markup hid them below md — phones got prev/next only).
const visibleLinks = computed(() => {
  const first = props.links[0]
  const last = props.links[props.links.length - 1]
  const middle = props.links.slice(1, -1).filter((link) => {
    const page = Number(link.label)

    if (Number.isNaN(page)) {
      return true
    }

    return Math.abs(page - props.meta.current_page) <= 1
  })

  return [first, ...middle, last]
})
</script>

<template>
  <div
    v-if="meta.total > 0"
    class="flex flex-col gap-3 px-2 sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="flex-1 text-sm text-muted-foreground">
      {{
        __('pagination_showing', {
          from: meta.from,
          to: meta.to,
          total: meta.total,
        })
      }}
    </div>
    <div class="flex items-center space-x-2 sm:space-x-6 lg:space-x-8">
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
            :aria-label="__('go_to_previous_page')"
          >
            <ChevronLeft class="size-4" />
          </Link>
          <button v-else disabled>
            <ChevronLeft class="size-4" />
          </button>
        </Button>
        <Button
          v-for="link in visibleLinks"
          :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          class="size-10 p-0 md:size-8"
          as-child
        >
          <Link
            v-if="link.url"
            :href="link.url"
            preserve-scroll
            :aria-label="__('go_to_page', { page: link.label })"
          >
            <span v-html="link.label"></span>
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
            :aria-label="__('go_to_next_page')"
          >
            <ChevronRight class="size-4" />
          </Link>
          <button v-else disabled>
            <ChevronRight class="size-4" />
          </button>
        </Button>
      </div>
    </div>
  </div>
</template>
