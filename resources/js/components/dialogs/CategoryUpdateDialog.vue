<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { nextTick, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AlertError from '@/components/AlertError.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import SheetDialogContent from '@/components/ui/dialog-sheet.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useLang } from '@/composables/useLang'
import { update as updateCategory } from '@/routes/categories'
import type { Category } from '@/types'

const props = defineProps<{
  open: boolean
  category: Category | null
  types: string[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { __ } = useLang()

const form = useForm({
  type: '',
  name: '',
})

const firstFieldRef = ref<HTMLElement | null>(null)

const onOpenAutoFocus = () => {
  nextTick(() => {
    firstFieldRef.value
      ?.querySelector<HTMLElement>(
        'input, textarea, [data-slot="select-trigger"]',
      )
      ?.focus()
  })
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return
    }

    form.clearErrors()

    if (props.category) {
      form.type = props.category.type
      form.name = props.category.name
    }
  },
)

const submit = () => {
  if (!props.category) {
    return
  }

  form.put(updateCategory.url(props.category), {
    preserveScroll: true,
    onSuccess: (res) => {
      form.reset()
      emit('update:open', false)
      toast.success(res.props.success as string)
    },
    onError: () => {
      nextTick(() => {
        document.querySelector('[role="alert"]')?.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        })
      })
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent
      class="md:max-w-[425px]"
      @open-auto-focus.prevent="onOpenAutoFocus"
    >
      <DialogHeader>
        <DialogTitle>
          {{ __('edit_data', { data: __('category') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('category_update_description') }}
        </DialogDescription>
      </DialogHeader>

      <form ref="firstFieldRef" @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="update_type">
              {{ __('type') }} <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.type" required>
              <SelectTrigger id="update_type">
                <SelectValue
                  :placeholder="__('select_data', { data: __('type') })"
                />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="value in types"
                    :key="value"
                    :value="value"
                  >
                    {{ __(value) }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
          </div>

          <div class="grid gap-2">
            <Label for="update_name">
              {{ __('name') }} <span class="text-destructive">*</span>
            </Label>
            <Input
              id="update_name"
              v-model="form.name"
              placeholder="e.g. Food"
              required
              :disabled="form.processing"
              :aria-invalid="form.errors.name ? true : undefined"
            />
            <InputError :message="form.errors.name" />
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            @click="$emit('update:open', false)"
            :disabled="form.processing"
          >
            {{ __('cancel') }}
          </Button>
          <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" class="mr-2" />
            {{ form.processing ? __('updating') : __('update') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
