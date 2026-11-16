@extends('layouts.public', ['title' => 'Portal PPDB — KOMITE MAN 7 JOMBANG'])

@section('content')
<div class="space-y-6">
    {{-- Hero Section --}}
    <div class="rounded-xl overflow-hidden border border-[#dbe3eb] bg-white shadow-sm">
        <div class="bg-gradient-to-r from-[#1f5ca6] to-[#2872c8] px-6 py-8 sm:px-8 sm:py-10 text-white">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm mb-3">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                PPDB Online Resmi MAN 7 Jombang
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Penerimaan Peserta Didik Baru</h1>
            <p class="mt-2 text-sm sm:text-base text-white/90 max-w-2xl leading-relaxed">
                Portal resmi informasi pendaftaran calon siswa baru. Dapatkan informasi syarat pendaftaran, alur seleksi, serta pengumuman secara transparan dan mudah diakses.
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if($period)
                    <a href="{{ route('ppdb.register') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        Daftar Calon Siswa Baru
                    </a>
                @else
                    <a href="{{ route('ppdb.register.closed') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-amber-500 hover:bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        Status Pendaftaran Ditutup
                    </a>
                @endif

                <a href="{{ route('public.student.login') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 border border-white/25 px-4 py-2.5 text-sm font-medium text-white transition">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    Portal Masuk Siswa
                </a>
            </div>
        </div>

        {{-- Status Periode Pendaftaran --}}
        <div class="px-6 py-4 bg-[#f8fafc] border-t border-[#e2e8f0] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs sm:text-sm">
            <div class="flex items-center gap-2 text-slate-700">
                <i data-lucide="calendar" class="w-4 h-4 text-[#2872c8]"></i>
                @if($period)
                    <span><strong>Periode Aktif:</strong> {{ $period->name }} ({{ $period->starts_at?->format('d/m/Y') }} – {{ $period->ends_at?->format('d/m/Y') }})</span>
                @else
                    <span>Saat ini belum ada periode PPDB aktif yang dibuka untuk publik.</span>
                @endif
            </div>

            <div>
                @if($period)
                    <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-3 py-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Pendaftaran Dibuka
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-3 py-1">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Pendaftaran Belum Dibuka
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- 3 Tahap Alur --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg bg-white p-5 border border-[#e1e6ed] shadow-sm">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#2872c8] flex items-center justify-center font-bold text-sm mb-3">1</div>
            <h2 class="text-sm font-semibold text-slate-900">Isi Formulir Online</h2>
            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                Lengkapi biodata calon siswa, data wali/orang tua, serta kontak yang bisa dihubungi saat pendaftaran.
            </p>
        </div>

        <div class="rounded-lg bg-white p-5 border border-[#e1e6ed] shadow-sm">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#2872c8] flex items-center justify-center font-bold text-sm mb-3">2</div>
            <h2 class="text-sm font-semibold text-slate-900">Simpan Bukti Pendaftaran</h2>
            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                Dapatkan nomor pendaftaran resmi sebagai bukti verifikasi dan administrasi lanjutan di madrasah.
            </p>
        </div>

        <div class="rounded-lg bg-white p-5 border border-[#e1e6ed] shadow-sm">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#2872c8] flex items-center justify-center font-bold text-sm mb-3">3</div>
            <h2 class="text-sm font-semibold text-slate-900">Pantau Hasil & Pembayaran</h2>
            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                Gunakan portal siswa untuk mengecek verifikasi dokumen, rincian tagihan komite, dan status pembayaran.
            </p>
        </div>
    </div>

    {{-- Pengumuman & Informasi --}}
    <div class="rounded-xl bg-white border border-[#e1e6ed] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#e1e6ed] bg-[#f8fafc] flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wider text-[#2872c8] flex items-center gap-2">
                <i data-lucide="megaphone" class="w-4 h-4"></i>
                Informasi &amp; Pengumuman PPDB
            </h2>
            <span class="text-xs text-slate-500">Terbit Resmi</span>
        </div>

        @if($contents->isNotEmpty())
            <div class="divide-y divide-[#eef2f7]">
                @foreach($contents as $content)
                    <div class="p-6 hover:bg-slate-50/60 transition">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-base font-semibold text-slate-900">{{ $content->title }}</h3>
                            <span class="text-xs text-slate-400 shrink-0">{{ $content->created_at?->format('d M Y') }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600 whitespace-pre-line leading-relaxed">{{ $content->body }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center">
                <i data-lucide="file-text" class="w-10 h-10 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm font-medium text-slate-700">Belum ada pengumuman PPDB</p>
                <p class="text-xs text-slate-500 mt-1">Pengumuman teknis dan persyaratan akan muncul di halaman ini saat diterbitkan oleh panitia.</p>
            </div>
        @endif
    </div>
</div>
@endsection
