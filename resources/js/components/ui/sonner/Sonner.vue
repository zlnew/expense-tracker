<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Toaster as SonnerPrimitive } from 'vue-sonner';
import 'vue-sonner/style.css';

const { resolvedAppearance } = useAppearance();
</script>

<template>
    <SonnerPrimitive
        :theme="resolvedAppearance"
        class="toaster group z-toast"
        position="top-center"
        :offset="{ top: '4.5rem' }"
        :style="{
            '--normal-bg': 'var(--popover)',
            '--normal-text': 'var(--popover-foreground)',
            '--normal-border': 'var(--border)',
        }"
    />
</template>
