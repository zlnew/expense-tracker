<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useLang } from '@/composables/useLang'

type Props = {
  /** Inertia useForm().processing — disables both buttons while a visit runs. */
  processing?: boolean
  submitLabel?: string
  /** Show the cancel button (mobile forms often have Cancel in the header). */
  showCancel?: boolean
  /** Where the cancel button goes when shown. */
  cancelHref?: string
}

const props = withDefaults(defineProps<Props>(), {
  processing: false,
  submitLabel: '',
  showCancel: false,
  cancelHref: '',
})

const emit = defineEmits<{ cancel: [] }>()

const { __ } = useLang()
</script>

<template>
  <div
    class="sticky bottom-[var(--bottom-nav-height)] z-sticky -mx-4 border-t bg-background/95 px-4 py-3 backdrop-blur md:bottom-0 md:mx-0 md:rounded-b-xl md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none"
  >
    <div class="flex items-center justify-end gap-2">
      <Button
        v-if="props.showCancel"
        type="button"
        variant="outline"
        :disabled="props.processing"
        @click="emit('cancel')"
      >
        {{ __('cancel') }}
      </Button>
      <Button type="submit" :disabled="props.processing">
        <LoaderCircle
          v-if="props.processing"
          class="size-4 animate-spin"
          aria-hidden="true"
        />
        {{ props.submitLabel || __('save') }}
      </Button>
    </div>
  </div>
</template>
