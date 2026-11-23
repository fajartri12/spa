<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
@php
    $routeName = request()->route()?->getName() ?? '';
    $isPortal = str_starts_with($routeName, 'public.guardian.') || str_starts_with($routeName, 'public.student.');
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
    <meta name="theme-color" content="#2563EB">

    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[#F4F7FB] antialiased {{ $isPortal ? 'portal-app' : '' }}">

    <header class="sticky top-0 z-30 border-b border-[#E2E8F0] bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[14px] bg-[#EFF6FF]">
                    <i data-lucide="landmark" aria-hidden="true" class="h-5 w-5 text-[#2563EB]"></i>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold leading-tight text-[#0F172A]">KOMITE MAN 7 JOMBANG</p>
                    <p class="truncate text-xs leading-tight text-[#64748B]">{{ $portalLabel ?? $defaultPortalLabel }}</p>
                    @hasSection('portal-badge')
                        <p class="mt-0.5 truncate text-[11px] font-medium leading-tight text-[#2563EB]">@yield('portal-badge')</p>
                    @endif
                </div>
            </div>
            <span class="hidden rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-xs font-medium text-[#64748B] sm:inline-flex">Portal resmi sekolah</span>
        </div>
    </header>

    <main class="w-full px-4 py-5 sm:px-6 sm:py-8">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-10 sm:px-6">
        <p class="text-center text-[12px] text-[#64748B]">
            &copy; {{ now()->year }} KOMITE MAN 7 JOMBANG
        </p>
    </footer>
</body>
</html>
