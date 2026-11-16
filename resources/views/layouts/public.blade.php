<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
@php
    $routeName = request()->route()?->getName() ?? '';
    $defaultPortalLabel = match (true) {
        str_starts_with($routeName, 'public.guardian.') => 'Portal Orang Tua / Wali',
        str_starts_with($routeName, 'public.student.') => 'Portal Siswa',
        default => 'Penerimaan Peserta Didik Baru',
    };
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2872c8">

    <title>{{ isset($title) ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[#f4f6f9] antialiased">

    <header class="relative overflow-hidden bg-[#1455a0]">
        <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-[#2872c8]/60"></div>
        <div class="relative mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm">
                    <i data-lucide="landmark" aria-hidden="true" class="h-6 w-6 text-[#2872c8]"></i>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold leading-tight text-white sm:text-base">KOMITE MAN 7 JOMBANG</p>
                    <p class="truncate text-xs leading-tight text-blue-100">{{ $portalLabel ?? $defaultPortalLabel }}</p>
                    @hasSection('portal-badge')
                        <p class="mt-1 truncate text-[11px] font-medium leading-tight text-blue-200">@yield('portal-badge')</p>
                    @endif
                </div>
            </div>
            <span class="hidden rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-blue-50 sm:inline-flex">Portal resmi sekolah</span>
        </div>
    </header>

    <main class="w-full px-3 py-5 sm:px-6 sm:py-8">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    <footer class="mx-auto max-w-4xl px-4 sm:px-6 pb-8">
        <p class="text-center text-[12px] text-[#8994a3]">
            &copy; {{ now()->year }} KOMITE MAN 7 JOMBANG
        </p>
    </footer>
</body>
</html>
