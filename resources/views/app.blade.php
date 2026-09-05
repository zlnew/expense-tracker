<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">

        <meta name="color-scheme" content="dark light">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#faf9f7">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#050508">
        <meta name="theme-color" content="#050508">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';
                let isDark = appearance === 'dark';

                if (appearance === 'system') {
                    isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }

                if (isDark) {
                    document.documentElement.classList.add('dark');
                }

                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

                const themeColor = isDark ? '#050508' : '#faf9f7';
                document.querySelectorAll('meta[name="theme-color"]').forEach(function(meta) {
                    meta.setAttribute('content', themeColor);
                });
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.985 0.005 240);
            }

            html.dark {
                background-color: #050508;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/build/manifest.webmanifest">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Expense Tracker">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
