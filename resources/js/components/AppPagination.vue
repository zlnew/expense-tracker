<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

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

defineProps<{
  meta: Meta
  links: LinkType[]
}>()
</script>

<template>
  <div v-if="meta.total > 0" class="flex items-center justify-between px-2">
    <div class="flex-1 text-sm text-muted-foreground">
      Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }} results
    </div>
    <div class="flex items-center space-x-6 lg:space-x-8">
      <div class="flex items-center space-x-2">
        <Button
          variant="outline"
          class="size-8 p-0"
          :disabled="meta.current_page === 1"
          as-child
        >
          <Link v-if="meta.current_page > 1" :href="meta.prev_page_url">
            <span class="sr-only">Go to first page</span>
            <ChevronLeft class="size-4" />
          </Link>
          <button v-else disabled>
            <span class="sr-only">Go to first page</span>
            <ChevronLeft class="size-4" />
          </button>
        </Button>
        <Button
          v-for="link in links.slice(1, -1)"
          :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          class="size-8 p-0"
          as-child
        >
          <Link v-if="link.url" :href="link.url" preserve-scroll>
            {{ link.label }}
          </Link>
          <span v-else v-html="link.label"></span>
        </Button>
        <Button
          variant="outline"
          class="size-8 p-0"
          :disabled="meta.current_page === meta.last_page"
          as-child
        >
          <Link
            v-if="meta.current_page < meta.last_page"
            :href="links[links.length - 1].url ?? ''"
          >
            <span class="sr-only">Go to next page</span>
            <ChevronRight class="size-4" />
          </Link>
          <button v-else disabled>
            <span class="sr-only">Go to next page</span>
            <ChevronRight class="size-4" />
          </button>
        </Button>
      </div>
    </div>
  </div>
</template>
