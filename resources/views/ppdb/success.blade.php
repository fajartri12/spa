@extends('layouts.public', ['title' => 'Pendaftaran Berhasil'])

@section('content')
<div class="bg-white rounded-[8px] shadow-sm border border-[#e1e6ed] p-6 sm:p-8">
    <div class="text-center max-w-xl mx-auto">
        <div class="w-16 h-16 rounded-full bg-[#edf7e5] text-[#28a745] mx-auto flex items-center justify-center mb-4">
            <i data-lucide="check-circle" class="w-10 h-10"></i>
        </div>

        <h1 class="text-2xl font-bold text-[#111827]">Pendaftaran Berhasil Dikirim!</h1>
        <p class="text-sm text-[#64748b] mt-2 leading-relaxed">
            Terima kasih telah mendaftar di MAN 7 Jombang. Simpan nomor pendaftaran berikut sebagai bukti pendaftaran Anda.
        </p>

        {{-- Nomor Pendaftaran Box --}}
        <div class="my-6 p-4 rounded-[6px] bg-[#f8fafc] border-2 border-dashed border-[#2872c8]/40">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">Nomor Pendaftaran PPDB</p>
            <p class="text-2xl sm:text-3xl font-mono font-bold text-[#2872c8] mt-1 tracking-wider">
                {{ $applicant->registration_number }}
            </p>
            <p class="text-xs text-[#8994a3] mt-2">
                Periode: {{ $applicant->admissionPeriod->name ?? '-' }}
            </p>
        </div>

        {{-- Ringkasan Data --}}
        <div class="text-left bg-[#fcfcfd] rounded-[6px] border border-[#e1e6ed] p-4 space-y-2 text-sm mb-6">
            <div class="flex justify-between py-1 border-b border-[#f1f5f9]">
                <span class="text-[#64748b]">Nama Calon Siswa</span>
                <span class="font-medium text-[#111827]">{{ $applicant->name }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-[#f1f5f9]">
                <span class="text-[#64748b]">Jenis Kelamin</span>
                <span class="font-medium text-[#111827]">{{ $applicant->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-[#f1f5f9]">
                <span class="text-[#64748b]">Nomor WhatsApp</span>
                <span class="font-medium text-[#111827]">{{ $applicant->phone }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-[#f1f5f9]">
                <span class="text-[#64748b]">Nama Orang Tua / Wali</span>
                <span class="font-medium text-[#111827]">{{ $applicant->parent_name }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="text-[#64748b]">Status Pendaftaran</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-[#eaf2fc] text-[#2872c8]">
                    Terdaftar (Menunggu Verifikasi)
                </span>
            </div>
        </div>

        <div class="text-xs text-[#64748b] bg-[#fff9db] border border-[#ffe066] p-3 rounded-[6px] mb-6 text-left">
            <strong class="text-[#d9480f]">Catatan Penting:</strong> Silakan screenshot atau catat nomor registrasi di atas. Panitia PPDB akan mengonfirmasi proses selanjutnya melalui nomor WhatsApp yang telah didaftarkan.
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('ppdb.register') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-[6px] text-sm font-medium border border-[#e1e6ed] text-[#475569] hover:bg-[#f8fafc] transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Daftar Calon Siswa Lain
            </a>
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-[6px] text-sm font-medium text-white bg-[#2872c8] hover:bg-[#20599f] transition shadow-sm">
                <i data-lucide="home" class="w-4 h-4"></i>
                Halaman Utama
            </a>
        </div>
    </div>
</div>
@endsection
