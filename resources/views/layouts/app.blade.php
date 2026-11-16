<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2872c8">

    <title>{{ isset($title) ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.theme')
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-[#f4f6f9] antialiased">

    @include('layouts.partials.header')
    @include('layouts.partials.navbar')

    <main class="flex-1 w-full">
        <div class="px-3.5 py-4 sm:px-5 sm:py-5 lg:px-6 lg:py-6">

            @hasSection('breadcrumb')
                <div class="mb-3">
                    @yield('breadcrumb')
                </div>
            @elseif(isset($breadcrumbs))
                <div class="mb-3">
                    <x-ui.breadcrumb :items="$breadcrumbs" />
                </div>
            @endif

            @if(session('success'))
                <div class="mb-4">
                    <x-ui.alert type="success" dismissible>{{ session('success') }}</x-ui.alert>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4">
                    <x-ui.alert type="danger" dismissible>{{ session('error') }}</x-ui.alert>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-4">
                    <x-ui.alert type="warning" dismissible>{{ session('warning') }}</x-ui.alert>
                </div>
            @endif

            @if(isset($header))
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {{ $header }}
                    </div>
                    @if(isset($actions))
                        <div class="flex flex-wrap items-center gap-2">
                            {{ $actions }}
                        </div>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>

    @include('layouts.partials.footer')

    <x-ui.toast />
    <x-ui.confirm-dialog name="confirm" />

    @livewireScripts
</body>
</html>
