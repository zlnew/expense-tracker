import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            registerType: 'prompt',
            includeAssets: [
                'favicon.ico',
                'favicon.svg',
                'apple-touch-icon.png',
                'robots.txt',
                'pwa-192x192.png',
                'pwa-512x512.png',
                'pwa-maskable-192x192.png',
                'pwa-maskable-512x512.png',
            ],
            base: '/',
            scope: '/',
            manifest: {
                id: '/',
                name: 'Expense Tracker',
                short_name: 'Expense',
                description: 'Personal Financial Terminal — Real-time expense, budget & cash flow tracking.',
                theme_color: '#050508',
                background_color: '#050508',
                display: 'standalone',
                display_override: ['window-controls-overlay', 'standalone', 'minimal-ui'],
                orientation: 'portrait',
                start_url: '/',
                categories: ['finance', 'productivity', 'utilities'],
                lang: 'id-ID',
                screenshots: [
                    {
                        src: '/screenshots/dashboard-mobile.png',
                        sizes: '1170x2532',
                        type: 'image/png',
                        form_factor: 'narrow',
                        label: 'Expense Terminal Dasbor',
                    },
                    {
                        src: '/screenshots/transactions-mobile.png',
                        sizes: '1170x2532',
                        type: 'image/png',
                        form_factor: 'narrow',
                        label: 'Expense Terminal Transaksi',
                    },
                ],
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-maskable-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/pwa-maskable-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
                shortcuts: [
                    {
                        name: 'Catat Pengeluaran',
                        short_name: 'Catat',
                        description: 'Buka modal input pengeluaran cepat',
                        url: '/dashboard?action=create',
                        icons: [{ src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' }],
                    },
                    {
                        name: 'Riwayat Transaksi',
                        short_name: 'Transaksi',
                        description: 'Lihat seluruh riwayat aliran kas',
                        url: '/transactions',
                        icons: [{ src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' }],
                    },
                    {
                        name: 'Rekening & Saldo',
                        short_name: 'Saldo',
                        description: 'Monitor rekening dan rekonsiliasi saldo',
                        url: '/balances',
                        icons: [{ src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' }],
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,webmanifest}'],
                navigateFallback: '/offline.html',
                navigateFallbackDenylist: [/^\/api\//],
            },
        }),
    ],
});
