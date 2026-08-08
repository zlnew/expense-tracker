<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { ComponentPublicInstance } from 'vue'
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
import { store as storeCategory } from '@/routes/categories'

const props = defineProps<{
  open: boolean
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

const firstFieldRef = ref<ComponentPublicInstance | null>(null)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.reset()
      form.clearErrors()
      nextTick(() => {
        ;(firstFieldRef.value?.$el as HTMLElement | undefined)?.focus()
      })
    }
  },
)

const submit = () => {
  form.post(storeCategory.url(), {
    preserveScroll: true,
    onSuccess: (res) => {
      form.reset()
      emit('update:open', false)
      toast.success(res.props.success as string)
    },
    onError: () => {
      nextTick(() => {
        document
          .querySelector('[aria-invalid="true"]')
          ?.scrollIntoView({ block: 'center', behavior: 'smooth' })
      })
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="sm:max-w-[425px]" @open-auto-focus.prevent>
      <DialogHeader>
        <DialogTitle>
          {{ __('add_data', { data: __('category') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('category_create_description') }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit">
        <div class="grid gap-4 py-4">
          <AlertError
            v-if="Object.keys(form.errors).length > 0"
            :errors="Object.values(form.errors)"
          />

          <div class="grid gap-2">
            <Label for="create_type">
              {{ __('type') }} <span class="text-destructive">*</span>
            </Label>
            <Select v-model="form.type" required :disabled="form.processing">
              <SelectTrigger
                id="create_type"
                ref="firstFieldRef"
                :aria-invalid="!!form.errors.type"
              >
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
            <Label for="create_name">
              {{ __('name') }} <span class="text-destructive">*</span>
            </Label>
            <Input
              id="create_name"
              v-model="form.name"
              :placeholder="__('category_name_placeholder')"
              required
              :disabled="form.processing"
              :aria-invalid="!!form.errors.name"
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
            <Spinner v-if="form.processing" class="size-4" />
            {{ form.processing ? __('saving') : __('save') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
