<script setup lang="ts">
import { Form } from '@inertiajs/vue3'
import { useTemplateRef } from 'vue'
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController'
import Heading from '@/components/Heading.vue'
import InputError from '@/components/InputError.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { useLang } from '@/composables/useLang'

const { __ } = useLang()

const passwordInput = useTemplateRef('passwordInput')
</script>

<template>
  <div class="space-y-6">
    <Heading
      variant="small"
      :title="__('delete_account_title')"
      :description="__('delete_account_description')"
    />
    <div
      class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
    >
      <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
        <p class="font-medium">{{ __('warning') }}</p>
        <p class="text-sm">{{ __('action_warning') }}</p>
      </div>
      <Dialog>
        <DialogTrigger as-child>
          <Button variant="destructive" data-test="delete-user-button">
            {{ __('delete_account') }}
          </Button>
        </DialogTrigger>
        <DialogContent>
          <Form
            v-bind="ProfileController.destroy.form()"
            reset-on-success
            @error="() => passwordInput?.focus()"
            :options="{
              preserveScroll: true,
            }"
            class="space-y-6"
            v-slot="{ errors, processing, reset, clearErrors }"
          >
            <DialogHeader class="space-y-3">
              <DialogTitle>{{ __('delete_account_action_title') }}</DialogTitle>
              <DialogDescription>
                {{ __('delete_account_action_description') }}
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="password" class="sr-only">{{ __('password') }}</Label>
              <PasswordInput
                id="password"
                name="password"
                ref="passwordInput"
                :placeholder="__('password')"
              />
              <InputError :message="errors.password" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button
                  variant="secondary"
                  @click="
                    () => {
                      clearErrors()
                      reset()
                    }
                  "
                >
                  {{ __('cancel') }}
                </Button>
              </DialogClose>

              <Button
                type="submit"
                variant="destructive"
                :disabled="processing"
                data-test="confirm-delete-user-button"
              >
                {{ __('delete_account') }}
              </Button>
            </DialogFooter>
          </Form>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
