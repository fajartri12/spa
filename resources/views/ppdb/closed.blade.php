@extends('layouts.public', ['title' => 'Pendaftaran Ditutup'])

@section('content')
<div class="bg-white rounded-[8px] shadow-sm border border-[#e1e6ed] p-8 text-center max-w-xl mx-auto">
    <div class="w-16 h-16 rounded-full bg-[#fdedec] text-[#e74c3c] mx-auto flex items-center justify-center mb-4">
        <i data-lucide="calendar-x" class="w-9 h-9"></i>
    </div>

    <h1 class="text-xl font-bold text-[#111827]">Pendaftaran Belum Dibuka</h1>
    <p class="text-sm text-[#64748b] mt-2 leading-relaxed">
        Saat ini tidak ada periode PPDB yang sedang aktif. Silakan kembali lagi pada periode pendaftaran berikutnya
        atau hubungi panitia PPDB MAN 7 Jombang untuk informasi lebih lanjut.
    </p>

    <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 mt-6 px-5 py-2.5 rounded-[6px] text-sm font-medium text-white bg-[#2872c8] hover:bg-[#20599f] transition shadow-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Kembali ke Halaman Utama
    </a>
</div>
@endsection
