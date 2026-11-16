@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengaturan Tampilan']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengaturan Tampilan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kustomisasi tema warna aplikasi, gaya menu navigasi, dan tata letak halaman masuk akun.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button
                variant="secondary"
                type="button"
                wire:click="resetToDefault"
                wire:confirm="Kembalikan seluruh pengaturan tampilan ke tema bawaan?"
            >
                <i data-lucide="rotate-ccw" class="w-4 h-4 text-[#64748b]"></i>
                <span>Reset Bawaan</span>
            </x-ui.button>

            @can('settings.update')
                <x-ui.button variant="primary" type="submit" form="appearance-form" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pengaturan</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Tabs Navigasi --}}
    <div class="card p-1.5 flex flex-wrap gap-1 bg-white border border-[#e1e6ed] rounded-[6px]">
        <button
            type="button"
            wire:click="$set('activeTab', 'app')"
            @class([
                'flex items-center gap-2 px-4 py-2 rounded-[5px] text-[13px] font-medium transition cursor-pointer',
                'bg-[var(--color-primary)] text-white shadow-xs' => $activeTab === 'app',
                'text-[#475569] hover:bg-[#f8fafc]' => $activeTab !== 'app',
            ])
        >
            <i data-lucide="palette" class="w-4 h-4"></i>
            <span>Tema Aplikasi</span>
        </button>

        <button
            type="button"
            wire:click="$set('activeTab', 'nav')"
            @class([
                'flex items-center gap-2 px-4 py-2 rounded-[5px] text-[13px] font-medium transition cursor-pointer',
                'bg-[var(--color-primary)] text-white shadow-xs' => $activeTab === 'nav',
                'text-[#475569] hover:bg-[#f8fafc]' => $activeTab !== 'nav',
            ])
        >
            <i data-lucide="menu" class="w-4 h-4"></i>
            <span>Tema Menu Navigasi</span>
        </button>

        <button
            type="button"
            wire:click="$set('activeTab', 'login')"
            @class([
                'flex items-center gap-2 px-4 py-2 rounded-[5px] text-[13px] font-medium transition cursor-pointer',
                'bg-[var(--color-primary)] text-white shadow-xs' => $activeTab === 'login',
                'text-[#475569] hover:bg-[#f8fafc]' => $activeTab !== 'login',
            ])
        >
            <i data-lucide="log-in" class="w-4 h-4"></i>
            <span>Tema Halaman Masuk Akun</span>
        </button>
    </div>

    {{-- Form + Live Preview Grid --}}
    <div class="grid gap-5 lg:grid-cols-12">
        {{-- Kolom Kiri: Form Pengaturan (7 cols) --}}
        <div class="lg:col-span-7 space-y-4">
            <form id="appearance-form" wire:submit="save" novalidate>
                {{-- TAB 1: TEMA APLIKASI --}}
                @if($activeTab === 'app')
                    <div class="space-y-4">
                        <x-ui.card
                            title="Palet Warna Utama Aplikasi"
                            subtitle="Pilih skema warna bawaan atau sesuaikan dengan warna identitas madrasah."
                        >
                            {{-- Preset Color Buttons --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-5">
                                @foreach($presets as $key => $preset)
                                    <button
                                        type="button"
                                        wire:click="selectPreset('{{ $key }}')"
                                        @class([
                                            'p-3 rounded-[6px] border text-left transition cursor-pointer flex flex-col justify-between h-20',
                                            'border-2 border-[var(--color-primary)] bg-[var(--color-primary-soft)] ring-1 ring-[var(--color-primary)]' => $theme_preset === $key,
                                            'border-[#e1e6ed] hover:border-[#cbd5e1] bg-white' => $theme_preset !== $key,
                                        ])
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-5 h-5 rounded-full border border-black/10 shrink-0" style="background-color: {{ $preset['primary'] }};"></span>
                                            <span class="w-3.5 h-3.5 rounded-full border border-black/10 shrink-0" style="background-color: {{ $preset['dark'] }};"></span>
                                        </div>
                                        <span class="text-[12px] font-medium text-[#1e293b] truncate leading-tight">{{ $preset['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- Custom Color Inputs --}}
                            <div class="p-4 rounded-[6px] bg-[#f8fafc] border border-[#e1e6ed] space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[13px] font-semibold text-[#1e293b]">Warna Primer Kustom (Hex)</span>
                                    <span class="text-[11px] font-mono text-[#64748b] uppercase">{{ $theme_primary_color }}</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input
                                        type="color"
                                        wire:model.live.debounce.300ms="theme_primary_color"
                                        class="w-11 h-10 p-1 rounded-[5px] border border-[#cbd5e1] cursor-pointer bg-white"
                                        title="Pilih warna hex"
                                    >
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.400ms="theme_primary_color"
                                            class="w-full h-10 px-3 font-mono text-[13px] rounded-[5px] border border-[#cbd5e1] focus:ring-2 focus:ring-[var(--color-primary)] uppercase"
                                            placeholder="#2872C8"
                                        >
                                    </div>
                                </div>

                                {{-- Variations Pill --}}
                                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-[#e2e8f0]">
                                    <div class="text-center">
                                        <span class="block text-[10px] font-semibold text-[#64748b] uppercase">Dark Shade</span>
                                        <div class="h-6 rounded mt-1 border border-black/10" style="background-color: {{ $theme_primary_dark }};"></div>
                                        <span class="text-[10px] font-mono text-[#8994a3] uppercase">{{ $theme_primary_dark }}</span>
                                    </div>
                                    <div class="text-center">
                                        <span class="block text-[10px] font-semibold text-[#64748b] uppercase">Light Shade</span>
                                        <div class="h-6 rounded mt-1 border border-black/10" style="background-color: {{ $theme_primary_light }};"></div>
                                        <span class="text-[10px] font-mono text-[#8994a3] uppercase">{{ $theme_primary_light }}</span>
                                    </div>
                                    <div class="text-center">
                                        <span class="block text-[10px] font-semibold text-[#64748b] uppercase">Soft Shade</span>
                                        <div class="h-6 rounded mt-1 border border-black/10" style="background-color: {{ $theme_primary_soft }};"></div>
                                        <span class="text-[10px] font-mono text-[#8994a3] uppercase">{{ $theme_primary_soft }}</span>
                                    </div>
                                </div>
                            </div>
                        </x-ui.card>

                        <x-ui.card
                            title="Gaya Elemen & Header"
                            subtitle="Atur lengkungan sudut kartu dan tampilan bar atas (header)."
                        >
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[13px] font-medium text-[#374151] mb-1.5">Lengkungan Sudut (Border Radius)</label>
                                    <div class="space-y-2">
                                        @foreach([
                                            'standard' => ['label' => 'Standar (6px)', 'desc' => 'Tampilan bawaan yang proporsional'],
                                            'rounded' => ['label' => 'Melengkung Lembut (10px)', 'desc' => 'Tampilan modern dan ramah'],
                                            'sharp' => ['label' => 'Tegas / Minimalis (2px)', 'desc' => 'Tampilan formal dan ringkas'],
                                        ] as $val => $opt)
                                            <label class="flex items-start gap-2.5 p-2.5 rounded-[5px] border border-[#e1e6ed] cursor-pointer hover:bg-[#f8fafc] transition">
                                                <input type="radio" wire:model.live="theme_radius_style" value="{{ $val }}" class="mt-0.5 text-[var(--color-primary)]">
                                                <div>
                                                    <span class="block text-[13px] font-medium text-[#1f2a37]">{{ $opt['label'] }}</span>
                                                    <span class="block text-[11px] text-[#64748b]">{{ $opt['desc'] }}</span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[13px] font-medium text-[#374151] mb-1.5">Gaya Bar Header Atas</label>
                                    <div class="space-y-2">
                                        @foreach([
                                            'primary' => ['label' => 'Warna Utama Aplikasi', 'desc' => 'Header memakai warna tema primer'],
                                            'dark' => ['label' => 'Gelap Kontras (#0F172A)', 'desc' => 'Header hitam keabuan profesional'],
                                            'light' => ['label' => 'Terang Bersih (#FFFFFF)', 'desc' => 'Header putih dengan teks gelap'],
                                        ] as $val => $opt)
                                            <label class="flex items-start gap-2.5 p-2.5 rounded-[5px] border border-[#e1e6ed] cursor-pointer hover:bg-[#f8fafc] transition">
                                                <input type="radio" wire:model.live="theme_header_style" value="{{ $val }}" class="mt-0.5 text-[var(--color-primary)]">
                                                <div>
                                                    <span class="block text-[13px] font-medium text-[#1f2a37]">{{ $opt['label'] }}</span>
                                                    <span class="block text-[11px] text-[#64748b]">{{ $opt['desc'] }}</span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-ui.card>
                    </div>
                @endif

                {{-- TAB 2: TEMA MENU NAVIGASI --}}
                @if($activeTab === 'nav')
                    <div class="space-y-4">
                        <x-ui.card
                            title="Gaya Latar Menu Navigasi"
                            subtitle="Tentukan nuansa tampilan bar navigasi di bawah header."
                        >
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach([
                                    'light' => [
                                        'title' => 'Terang Klasik',
                                        'desc' => 'Latar putih bersih dengan garis aksen warna tema pada menu aktif.',
                                        'bg' => 'bg-white border-[#e1e6ed]',
                                        'badge' => 'Default',
                                    ],
                                    'dark' => [
                                        'title' => 'Gelap Kontras',
                                        'desc' => 'Latar gelap (#1E293B) dengan kontras tinggi untuk suasana malam.',
                                        'bg' => 'bg-[#1e293b] border-[#334155] text-white',
                                        'badge' => 'Modern Dark',
                                    ],
                                    'subtle' => [
                                        'title' => 'Abu Lembut',
                                        'desc' => 'Latar abu lembut (#F8FAFC) yang menyatu dengan latar kanvas.',
                                        'bg' => 'bg-[#f8fafc] border-[#e2e8f0]',
                                        'badge' => 'Subtle',
                                    ],
                                ] as $key => $style)
                                    <label @class([
                                        'relative p-4 rounded-[6px] border cursor-pointer transition flex flex-col justify-between h-36',
                                        'border-2 border-[var(--color-primary)] bg-[var(--color-primary-soft)] ring-1 ring-[var(--color-primary)]' => $theme_nav_style === $key,
                                        'border-[#e1e6ed] hover:border-[#cbd5e1] bg-white' => $theme_nav_style !== $key,
                                    ])>
                                        <div class="flex items-start justify-between">
                                            <input type="radio" wire:model.live="theme_nav_style" value="{{ $key }}" class="text-[var(--color-primary)] mt-1">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $style['badge'] }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-[13px] font-semibold text-[#1e293b] leading-tight">{{ $style['title'] }}</span>
                                            <span class="block text-[11px] text-[#64748b] mt-1 leading-snug">{{ $style['desc'] }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.card>

                        <x-ui.card
                            title="Perilaku & Ikon Navigasi"
                            subtitle="Atur posisi melayang saat scroll dan visibilitas ikon pada navigasi."
                        >
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-3.5 rounded-[6px] border border-[#e1e6ed] bg-[#f8fafc]">
                                    <div>
                                        <span class="block text-[13px] font-semibold text-[#1e293b]">Navigasi Menempel di Atas (Sticky Header)</span>
                                        <span class="block text-[11px] text-[#64748b]">Menu tetap terlihat di atas layar saat pengguna menggulir halaman.</span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="theme_nav_sticky" class="sr-only peer">
                                        <div class="w-11 h-6 bg-[#cbd5e1] peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--color-primary)]"></div>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-[13px] font-medium text-[#374151] mb-1.5">Tampilan Ikon Menu</label>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="flex items-start gap-2.5 p-3 rounded-[5px] border border-[#e1e6ed] cursor-pointer hover:bg-[#f8fafc] transition">
                                            <input type="radio" wire:model.live="theme_nav_icons" value="all" class="mt-0.5 text-[var(--color-primary)]">
                                            <div>
                                                <span class="block text-[13px] font-medium text-[#1f2a37]">Tampilkan Ikon & Label Teks</span>
                                                <span class="block text-[11px] text-[#64748b]">Format standar dengan ikon Lucide untuk setiap menu.</span>
                                            </div>
                                        </label>

                                        <label class="flex items-start gap-2.5 p-3 rounded-[5px] border border-[#e1e6ed] cursor-pointer hover:bg-[#f8fafc] transition">
                                            <input type="radio" wire:model.live="theme_nav_icons" value="text_only" class="mt-0.5 text-[var(--color-primary)]">
                                            <div>
                                                <span class="block text-[13px] font-medium text-[#1f2a37]">Hanya Teks Menu (Ringkas)</span>
                                                <span class="block text-[11px] text-[#64748b]">Lebih bersih dan menghemat ruang bar navigasi.</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </x-ui.card>
                    </div>
                @endif

                {{-- TAB 3: TEMA HALAMAN MASUK AKUN --}}
                @if($activeTab === 'login')
                    <div class="space-y-4">
                        <x-ui.card
                            title="Tata Letak Halaman Masuk"
                            subtitle="Pilih gaya tampilan layar login saat pengguna mengakses sistem."
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label @class([
                                    'p-4 rounded-[6px] border cursor-pointer transition flex flex-col justify-between h-32',
                                    'border-2 border-[var(--color-primary)] bg-[var(--color-primary-soft)] ring-1 ring-[var(--color-primary)]' => $theme_login_style === 'split',
                                    'border-[#e1e6ed] hover:border-[#cbd5e1] bg-white' => $theme_login_style !== 'split',
                                ])>
                                    <div class="flex items-center justify-between">
                                        <input type="radio" wire:model.live="theme_login_style" value="split" class="text-[var(--color-primary)]">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-800">Rekomendasi</span>
                                    </div>
                                    <div>
                                        <span class="block text-[13px] font-semibold text-[#1e293b]">Split Layout (Dua Kolom)</span>
                                        <span class="block text-[11px] text-[#64748b] mt-1">Panel brand visual di sebelah kiri dan form masuk di sebelah kanan.</span>
                                    </div>
                                </label>

                                <label @class([
                                    'p-4 rounded-[6px] border cursor-pointer transition flex flex-col justify-between h-32',
                                    'border-2 border-[var(--color-primary)] bg-[var(--color-primary-soft)] ring-1 ring-[var(--color-primary)]' => $theme_login_style === 'centered',
                                    'border-[#e1e6ed] hover:border-[#cbd5e1] bg-white' => $theme_login_style !== 'centered',
                                ])>
                                    <div class="flex items-center justify-between">
                                        <input type="radio" wire:model.live="theme_login_style" value="centered" class="text-[var(--color-primary)]">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-700">Minimalis</span>
                                    </div>
                                    <div>
                                        <span class="block text-[13px] font-semibold text-[#1e293b]">Kartu Terpusat (Centered Card)</span>
                                        <span class="block text-[11px] text-[#64748b] mt-1">Formulir login berada di tengah layar dengan background bersih.</span>
                                    </div>
                                </label>
                            </div>
                        </x-ui.card>

                        <x-ui.card
                            title="Latar Belakang Panel Brand Login"
                            subtitle="Pilih warna latar belakang atau corak gradien pada panel brand login."
                        >
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                @foreach([
                                    'primary' => ['label' => 'Warna Utama Aplikasi', 'preview' => 'background-color: ' . $theme_primary_color],
                                    'gradient-blue' => ['label' => 'Gradien Biru Samudra', 'preview' => 'background: linear-gradient(135deg, #1e3a8a, #2872c8)'],
                                    'gradient-emerald' => ['label' => 'Gradien Hijau Kemenag', 'preview' => 'background: linear-gradient(135deg, #064e3b, #107c41)'],
                                    'gradient-dark' => ['label' => 'Gradien Dark Slate', 'preview' => 'background: linear-gradient(135deg, #0f172a, #334155)'],
                                ] as $bKey => $bData)
                                    <label class="flex items-center gap-3 p-3 rounded-[6px] border border-[#e1e6ed] hover:border-[#cbd5e1] cursor-pointer">
                                        <input type="radio" wire:model.live="theme_login_bg" value="{{ $bKey }}" class="text-[var(--color-primary)]">
                                        <span class="w-8 h-8 rounded shrink-0 border border-black/10" style="{{ $bData['preview'] }};"></span>
                                        <span class="text-[12px] font-medium text-[#1e293b]">{{ $bData['label'] }}</span>
                                    </label>
                                @endforeach

                                <label class="flex items-center gap-3 p-3 rounded-[6px] border border-[#e1e6ed] hover:border-[#cbd5e1] cursor-pointer">
                                    <input type="radio" wire:model.live="theme_login_bg" value="custom" class="text-[var(--color-primary)]">
                                    <input
                                        type="color"
                                        wire:model.live="theme_login_bg_color"
                                        class="w-8 h-8 p-0.5 rounded border border-[#cbd5e1] cursor-pointer bg-white shrink-0"
                                        title="Warna latar kustom"
                                    >
                                    <span class="text-[12px] font-medium text-[#1e293b]">Warna Kustom</span>
                                </label>
                            </div>
                        </x-ui.card>

                        <x-ui.card
                            title="Teks & Konten Panel Login"
                            subtitle="Sesuaikan judul, slogan informasi, dan poin keunggulan sistem pada halaman masuk."
                        >
                            <div class="space-y-4">
                                <x-ui.input
                                    wire:model="theme_login_title"
                                    name="theme_login_title"
                                    label="Judul Utama Panel Brand"
                                    required
                                    placeholder="Pengelolaan Pembayaran Tagihan Sekolah"
                                />

                                <x-ui.textarea
                                    wire:model="theme_login_subtitle"
                                    name="theme_login_subtitle"
                                    label="Deskripsi / Slogan"
                                    rows="2"
                                    required
                                    placeholder="Satu sistem terintegrasi untuk tagihan, pembayaran, verifikasi, dan laporan keuangan madrasah."
                                />

                                <div class="space-y-2">
                                    <label class="block text-[13px] font-medium text-[#374151]">3 Poin Keunggulan / Fitur Utama</label>
                                    <x-ui.input wire:model="theme_login_feature_1" name="theme_login_feature_1" placeholder="Poin 1" icon="circle-check" />
                                    <x-ui.input wire:model="theme_login_feature_2" name="theme_login_feature_2" placeholder="Poin 2" icon="circle-check" />
                                    <x-ui.input wire:model="theme_login_feature_3" name="theme_login_feature_3" placeholder="Poin 3" icon="circle-check" />
                                </div>

                                <x-ui.input
                                    wire:model="theme_login_footer"
                                    name="theme_login_footer"
                                    label="Catatan Kaki Hak Cipta (Footer)"
                                    required
                                    placeholder="KOMITE MAN 7 JOMBANG"
                                />
                            </div>
                        </x-ui.card>
                    </div>
                @endif
            </form>
        </div>

        {{-- Kolom Kanan: Interactive Live Preview (5 cols) --}}
        <div class="lg:col-span-5 space-y-4">
            <x-ui.card
                title="Pratinjau Langsung (Live Preview)"
                subtitle="Visualisasi waktu-nyata bagaimana tema diterapkan di sistem."
            >
                @php
                    $previewBg = match($theme_login_bg) {
                        'gradient-blue' => 'background: linear-gradient(135deg, #1e3a8a, #2872c8);',
                        'gradient-emerald' => 'background: linear-gradient(135deg, #064e3b, #107c41);',
                        'gradient-dark' => 'background: linear-gradient(135deg, #0f172a, #334155);',
                        'custom' => 'background-color: ' . $theme_login_bg_color . ';',
                        default => 'background-color: ' . $theme_primary_color . ';',
                    };

                    $headerBg = match($theme_header_style) {
                        'dark' => 'background-color: #0f172a; color: #ffffff;',
                        'light' => 'background-color: #ffffff; color: #1e293b; border-bottom: 1px solid #e1e6ed;',
                        default => 'background-color: ' . $theme_primary_color . '; color: #ffffff;',
                    };

                    $navBg = match($theme_nav_style) {
                        'dark' => 'background-color: #1e293b; color: #f8fafc; border-bottom: 1px solid #334155;',
                        'subtle' => 'background-color: #f8fafc; color: #334155; border-bottom: 1px solid #e2e8f0;',
                        default => 'background-color: #ffffff; color: #334155; border-bottom: 1px solid #e1e6ed;',
                    };
                @endphp

                <div class="space-y-4">
                    {{-- Mini Header & Nav Preview --}}
                    <div>
                        <div class="text-[11px] font-semibold text-[#64748b] uppercase tracking-wider mb-2">1. Pratinjau Header &amp; Navigasi</div>
                        <div class="rounded-[6px] overflow-hidden border border-[#e1e6ed] shadow-xs">
                            {{-- Header --}}
                            <div class="px-3 py-2.5 flex items-center justify-between" style="{{ $headerBg }}">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-[10px] font-bold">M7</span>
                                    <div>
                                        <div class="text-[11px] font-semibold leading-tight">KOMITE MAN 7 JOMBANG</div>
                                        <div class="text-[9px] opacity-75 leading-tight">Sistem Tagihan &amp; Pembayaran</div>
                                    </div>
                                </div>
                                <div class="w-5 h-5 rounded-full bg-white/20"></div>
                            </div>

                            {{-- Nav Bar --}}
                            <div class="px-3 py-1.5 flex items-center gap-2 text-[11px] font-medium" style="{{ $navBg }}">
                                <span class="px-2 py-1 rounded-[4px] font-semibold flex items-center gap-1" style="background-color: {{ $theme_primary_soft }}; color: {{ $theme_primary_color }};">
                                    @if($theme_nav_icons === 'all')
                                        <i data-lucide="layout-dashboard" class="w-3 h-3"></i>
                                    @endif
                                    Beranda
                                </span>
                                <span class="px-2 py-1 flex items-center gap-1 opacity-70">
                                    @if($theme_nav_icons === 'all')
                                        <i data-lucide="wallet" class="w-3 h-3"></i>
                                    @endif
                                    Pembayaran
                                </span>
                                <span class="px-2 py-1 flex items-center gap-1 opacity-70">
                                    @if($theme_nav_icons === 'all')
                                        <i data-lucide="receipt" class="w-3 h-3"></i>
                                    @endif
                                    Tagihan
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Mini UI Elements Preview --}}
                    <div>
                        <div class="text-[11px] font-semibold text-[#64748b] uppercase tracking-wider mb-2">2. Komponen Aksen Warna (Tombol &amp; Badge)</div>
                        <div class="p-3 bg-[#f8fafc] rounded-[6px] border border-[#e1e6ed] flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="px-3 py-1.5 rounded-[5px] text-[12px] font-medium text-white shadow-xs"
                                style="background-color: {{ $theme_primary_color }};"
                            >
                                Tombol Utama
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 rounded-[5px] text-[12px] font-medium border"
                                style="border-color: {{ $theme_primary_color }}; color: {{ $theme_primary_color }}; background-color: #ffffff;"
                            >
                                Tombol Sekunder
                            </button>

                            <span
                                class="px-2 py-1 rounded text-[11px] font-semibold"
                                style="background-color: {{ $theme_primary_soft }}; color: {{ $theme_primary_color }};"
                            >
                                Lunas (Badge)
                            </span>
                        </div>
                    </div>

                    {{-- Mini Login Page Preview --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold text-[#64748b] uppercase tracking-wider">3. Pratinjau Halaman Masuk (Login)</span>
                            <a
                                href="{{ route('login') }}"
                                target="_blank"
                                class="text-[11px] font-medium hover:underline inline-flex items-center gap-1"
                                style="color: {{ $theme_primary_color }};"
                            >
                                Buka /masuk
                                <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>

                        <div class="rounded-[6px] overflow-hidden border border-[#e1e6ed] shadow-xs bg-[#f4f6f9]">
                            @if($theme_login_style === 'split')
                                <div class="grid grid-cols-12 min-h-[180px]">
                                    {{-- Brand Side --}}
                                    <div class="col-span-5 p-3 text-white flex flex-col justify-between" style="{{ $previewBg }}">
                                        <div>
                                            <div class="w-5 h-5 rounded bg-white/20 flex items-center justify-center text-[9px] font-bold">M7</div>
                                            <div class="mt-2 text-[10px] font-bold leading-tight">{{ $theme_login_title }}</div>
                                            <p class="text-[8px] opacity-80 mt-1 line-clamp-2 leading-tight">{{ $theme_login_subtitle }}</p>
                                        </div>
                                        <div class="text-[8px] opacity-60">© MAN 7</div>
                                    </div>
                                    {{-- Form Side --}}
                                    <div class="col-span-7 p-3 bg-white flex flex-col justify-center space-y-1.5">
                                        <div class="text-[11px] font-bold text-[#111827]">Masuk ke Aplikasi</div>
                                        <div class="h-5 rounded border border-[#cbd5e1] bg-[#f8fafc]"></div>
                                        <div class="h-5 rounded border border-[#cbd5e1] bg-[#f8fafc]"></div>
                                        <div class="h-6 rounded text-white text-[10px] flex items-center justify-center font-medium" style="background-color: {{ $theme_primary_color }};">
                                            Masuk
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-4 flex flex-col items-center justify-center min-h-[180px]" style="{{ $previewBg }}">
                                    <div class="w-full max-w-[200px] bg-white p-3 rounded-[6px] shadow-md space-y-2 text-center">
                                        <div class="text-[11px] font-bold text-[#111827]">Masuk ke Aplikasi</div>
                                        <div class="h-5 rounded border border-[#cbd5e1] bg-[#f8fafc]"></div>
                                        <div class="h-5 rounded border border-[#cbd5e1] bg-[#f8fafc]"></div>
                                        <div class="h-6 rounded text-white text-[10px] flex items-center justify-center font-medium" style="background-color: {{ $theme_primary_color }};">
                                            Masuk
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
