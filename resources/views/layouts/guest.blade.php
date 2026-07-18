<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', '课隙') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <main class="flex min-h-[100dvh] items-center justify-center px-4 py-8 sm:px-6 sm:py-12">
            <div class="w-full max-w-md">
                <a class="mb-6 flex items-center justify-center gap-3" href="/" aria-label="{{ config('app.name', '课隙') }}首页">
                    <x-brand-mark />
                    <span class="text-2xl font-bold text-slate-900">{{ config('app.name', '课隙') }}</span>
                </a>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    {{ $slot }}
                </section>

                <p class="mt-5 text-center text-xs leading-5 text-slate-500">课程安排清晰一点，周间空隙从容一点。</p>
            </div>
        </main>
    </body>
</html>
