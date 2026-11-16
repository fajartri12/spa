@extends('layouts.public')

@section('content')
    <div class="mx-auto grid max-w-6xl overflow-hidden rounded-3xl border border-[#e1e6ed] bg-white shadow-[0_20px_60px_-30px_rgba(20,85,160,0.35)] lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden overflow-hidden bg-[#1455a0] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
            <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-[#2872c8]/70"></div>
            <div class="relative">
                <div class="mb-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-lg">
                    <i data-lucide="users" aria-hidden="true" class="h-7 w-7 text-[#2872c8]"></i>
                </div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-100">KOMITE MAN 7 JOMBANG</p>
                <h1 class="mt-5 max-w-md text-4xl font-bold leading-tight">Pantau tagihan semua anak dalam satu akun.</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-blue-100">
                    Portal orang tua menampilkan sisa tagihan, riwayat pembayaran, kwitansi, dan tabungan setiap anak yang terdaftar.
                </p>
            </div>
            <div class="relative flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 text-sm text-blue-50">
                <i data-lucide="shield-check" aria-hidden="true" class="h-5 w-5 shrink-0"></i>
                <span>Hanya wali yang terdaftar yang dapat melihat data anak.</span>
            </div>
        </section>

        <section class="p-6 sm:p-10 lg:p-12">
            <div class="mb-8 lg:hidden">
                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#eaf2fc] text-[#2872c8]">
                    <i data-lucide="users" aria-hidden="true" class="h-6 w-6"></i>
                </div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#2872c8]">KOMITE MAN 7 JOMBANG</p>
            </div>

            <h1 class="text-2xl font-bold text-[#172b4d] sm:text-3xl">Portal Orang Tua</h1>
            <p class="mt-2 text-sm leading-6 text-[#667085]">Masuk dengan nomor HP yang terdaftar di sekolah.</p>

            @if(session('error'))
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('public.guardian.authenticate') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Nomor HP / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" required autocomplete="username" inputmode="tel"
                           class="mt-1 h-12 w-full rounded-xl border border-[#d0d5dd] bg-white px-4 text-sm text-[#172b4d] shadow-sm transition focus:border-[#2872c8] focus:outline-none focus:ring-4 focus:ring-[#2872c8]/10"
                           placeholder="Contoh: 0812 3456 7890">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="mt-1 h-12 w-full rounded-xl border border-[#d0d5dd] bg-white px-4 text-sm text-[#172b4d] shadow-sm transition focus:border-[#2872c8] focus:outline-none focus:ring-4 focus:ring-[#2872c8]/10"
                           placeholder="Masukkan password">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Password diterbitkan oleh petugas sekolah. Hubungi bendahara bila belum menerima.</p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn btn-primary">Masuk ke Portal Orang Tua</button>
                </div>
            </form>

            <div class="mt-6 rounded-xl border border-[#e1e6ed] bg-[#f8fafc] px-4 py-3 text-sm text-[#667085]">
                <p class="font-medium text-[#344054]">Anda siswa?</p>
                <p class="mt-1">
                    Portal siswa memiliki halaman terpisah.
                    <a href="{{ route('public.student.login') }}" class="font-semibold text-[#2872c8] underline decoration-[#2872c8]/30 underline-offset-2 hover:decoration-[#2872c8]">Masuk ke Portal Siswa</a>.
                </p>
            </div>
        </section>
    </div>
@endsection
