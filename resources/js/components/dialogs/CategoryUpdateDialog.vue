<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
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

watch(
  () => props.category,
  (category) => {
    if (category) {
      form.type = category.type
      form.name = category.name
    }
  },
  { immediate: true },
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
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <SheetDialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ __('edit_data', { data: __('category') }) }}
        </DialogTitle>
        <DialogDescription>
          {{ __('category_update_description') }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit">
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
            />
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            @click="$emit('update:open', false)"
          >
            {{ __('cancel') }}
          </Button>
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? __('updating') : __('update') }}
          </Button>
        </DialogFooter>
      </form>
    </SheetDialogContent>
  </Dialog>
</template>
