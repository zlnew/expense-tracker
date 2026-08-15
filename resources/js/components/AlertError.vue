<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'
import { computed } from 'vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { useLang } from '@/composables/useLang'

type Props = {
  errors: string[]
  title?: string
}

const props = defineProps<Props>()

const { __ } = useLang()

const uniqueErrors = computed(() => Array.from(new Set(props.errors)))
</script>

<template>
  <Alert variant="destructive">
    <AlertCircle class="size-4" />
    <AlertTitle>{{ title ?? __('something_went_wrong') }}</AlertTitle>
    <AlertDescription>
      <ul class="list-inside list-disc text-sm">
        <li v-for="(error, index) in uniqueErrors" :key="index">
          {{ error }}
        </li>
      </ul>
    </AlertDescription>
  </Alert>
</template>
