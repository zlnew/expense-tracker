<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { ComponentPublicInstance } from 'vue'
import { nextTick, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import SheetDialogContent from '@/components/ui/dialog-sheet.vue'
import { Spinner } from '@/components/ui/spinner'
import { useLang } from '@/composables/useLang'
import { destroy as destroyCategory } from '@/routes/categories'
import type { Category } from '@/types'

const props = defineProps<{
  open: boolean
  category: Category | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({})

const firstFieldRef = ref<ComponentPublicInstance | null>(null)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.clearErrors()
      nextTick(() => {
        ;(firstFieldRef.value?.$el as HTMLElement | undefined)?.focus()
      })
    }
  },
)

const submit = () => {
  if (!props.category) {
    return
  }

  form.delete(destroyCategory.url(props.category), {
    preserveScroll: true,
    onSuccess: (res) => {
      emit('update:open', false)
      toast.success(res.props.success as string)
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="sm:max-w-[425px]" @open-auto-focus.prevent>
      <DialogHeader>
        <DialogTitle>
          {{ __('delete_data', { data: __('category') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('category_delete_description') }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit">
        <AlertError
          v-if="Object.keys(form.errors).length > 0"
          :errors="Object.values(form.errors)"
        />

        <div v-if="category" class="mb-4">
          <p class="text-sm">
            {{ __('name') }}:
            <span class="font-semibold">
              {{ category.name }}
            </span>
          </p>
          <p class="text-sm text-muted-foreground">
            {{ __('type') }}:
            <span class="font-semibold">
              {{ __(category.type) }}
            </span>
          </p>
        </div>

        <DialogFooter>
          <Button
            type="button"
            ref="firstFieldRef"
            variant="outline"
            @click="$emit('update:open', false)"
            :disabled="form.processing"
          >
            {{ __('cancel') }}
          </Button>
          <Button
            type="submit"
            variant="destructive"
            :disabled="form.processing"
          >
            <Spinner v-if="form.processing" class="size-4" />
            {{ form.processing ? __('deleting') : __('delete') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
