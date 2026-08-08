<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue'
import { watch } from 'vue'
import { toast } from 'vue-sonner'
import { useLang } from '@/composables/useLang'

const { __ } = useLang()

// registerType: 'prompt' — the SW waits for user confirmation instead of
// force-reloading the app mid-use. Show a toast with a reload action.
// needRefresh flips false→true asynchronously after the new SW finishes
// installing, so watch it rather than check once at setup.
const { needRefresh, updateServiceWorker } = useRegisterSW()

watch(needRefresh, (needs) => {
  if (!needs) {
    return
  }

  toast(__('new_version_available'), {
    action: {
      label: __('new_version_reload'),
      onClick: () => updateServiceWorker(true),
    },
    duration: Infinity,
  })
})
</script>

<template>
  <!-- Purely a mount point: all UI is the toast above. -->
  <div class="hidden" aria-hidden="true" />
</template>
