@php
    use App\Models\AppSetting;

    $loginStyle = (string) AppSetting::get('theme_login_style', 'split');
    $loginBg = (string) AppSetting::get('theme_login_bg', 'primary');
    $primaryColor = (string) AppSetting::get('theme_primary_color', '#2872c8');

    $bgStyle = match ($loginBg) {
        'gradient-blue' => 'background: linear-gradient(135deg, #1e3a8a, #2872c8);',
        'gradient-emerald' => 'background: linear-gradient(135deg, #064e3b, #107c41);',
        'gradient-dark' => 'background: linear-gradient(135deg, #0f172a, #334155);',
        'custom' => 'background-color: ' . AppSetting::get('theme_login_bg_color', '#2872c8') . ';',
        default => 'background-color: ' . $primaryColor . ';',
    };

    $loginTitle = (string) AppSetting::get('theme_login_title', 'Pengelolaan Pembayaran Tagihan Sekolah');
    $loginSubtitle = (string) AppSetting::get('theme_login_subtitle', 'Satu sistem terintegrasi untuk tagihan, pembayaran, verifikasi, dan laporan keuangan madrasah.');
    $features = json_decode((string) AppSetting::get('theme_login_features', ''), true);
    if (! is_array($features) || count($features) < 3) {
        $features = [
            'Tagihan & potongan terkelola rapi',
            'Pembayaran multi-tagihan dan sebagian',
            'Laporan keuangan & tunggakan real-time',
        ];
    }
    $footer = (string) AppSetting::get('theme_login_footer', 'KOMITE MAN 7 JOMBANG');

    $appName = trim((string) AppSetting::get('app_name', '')) ?: config('app.name');
    $schoolName = trim((string) AppSetting::get('school_name', ''));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $primaryColor }}">

    <title>{{ isset($title) ? $title . ' — ' . $appName : $appName }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.theme')
    @livewireStyles
</head>
<body class="min-h-full bg-[#f4f6f9] antialiased">

    @if($loginStyle === 'centered')
        {{-- Centered Card Layout --}}
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 relative" style="{{ $bgStyle }}">
            <div class="w-full max-w-[440px] bg-white rounded-[8px] p-6 sm:p-8 shadow-[0_12px_32px_-8px_rgb(16_24_40/0.24)] border border-[#e1e6ed]">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[#eef2f7]">
                    <div class="w-10 h-10 rounded-[6px] bg-[var(--color-primary)] flex items-center justify-center shrink-0">
                        <i data-lucide="landmark" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <p class="text-[#111827] font-semibold text-sm leading-tight">{{ $loginTitle }}</p>
                        <p class="text-[#64748b] text-[12px] leading-tight">{{ $footer }}</p>
                    </div>
                </div>

                @if(session('status'))
                    <div class="mb-4">
                        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4">
                        <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
                    </div>
                @endif

                {{ $slot }}
            </div>

            <p class="mt-6 text-white/80 text-[12px]">
                &copy; {{ now()->year }} {{ $footer }}
            </p>
        </div>
    @else
        {{-- Split Layout --}}
        <div class="min-h-screen flex flex-col lg:flex-row">

            {{-- Panel brand --}}
            <aside class="hidden lg:flex lg:w-[46%] xl:w-[42%] relative overflow-hidden flex-col justify-between p-10 text-white" style="{{ $bgStyle }}">
                <div class="absolute -right-24 -top-24 w-96 h-96 rounded-full bg-white/5"></div>
                <div class="absolute -left-20 bottom-10 w-72 h-72 rounded-full bg-white/5"></div>

                <div class="relative">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-[6px] bg-white flex items-center justify-center">
                            <i data-lucide="landmark" class="w-7 h-7" style="color: {{ $primaryColor }};"></i>
                        </div>
                        <div>
                            <p class="text-white font-semibold text-[15px] leading-tight">{{ $appName }}</p>
                            @if($schoolName !== '')
                                <p class="text-white/80 text-[13px] leading-tight">{{ $schoolName }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="relative max-w-md">
                    <h1 class="text-white text-[30px] font-semibold leading-tight">
                        {{ $loginTitle }}
                    </h1>
                    <p class="mt-4 text-white/85 text-[15px] leading-relaxed">
                        {{ $loginSubtitle }}
                    </p>

                    <ul class="mt-8 space-y-3">
                        @foreach($features as $feature)
                            <li class="flex items-start gap-2.5 text-white/90 text-sm">
                                <i data-lucide="circle-check-big" class="w-4 h-4 mt-0.5 shrink-0"></i>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-white/60 text-[12px]">
                    &copy; {{ now()->year }} {{ $footer }}
                </p>
            </aside>

            {{-- Panel form --}}
            <main class="flex-1 flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <div class="w-full max-w-[420px]">

                    <div class="lg:hidden flex items-center gap-2.5 mb-8">
                        <div class="w-10 h-10 rounded-[6px] flex items-center justify-center" style="background-color: {{ $primaryColor }};">
                            <i data-lucide="landmark" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <p class="text-[#111827] font-semibold text-sm leading-tight">{{ $appName }}</p>
                            @if($schoolName !== '')
                                <p class="text-[#64748b] text-[12px] leading-tight">{{ $schoolName }}</p>
                            @endif
                        </div>
                    </div>

                    @if(session('status'))
                        <div class="mb-4">
                            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4">
                            <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    @endif

    <x-ui.toast />

    @livewireScripts
</body>
</html>
