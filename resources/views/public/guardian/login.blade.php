@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-md py-4 sm:py-8">
        <div class="portal-card p-6 sm:p-8">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EFF6FF] text-[#2563EB]">
                    <i data-lucide="users" aria-hidden="true" class="h-6 w-6"></i>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#2563EB]">KOMITE MAN 7 JOMBANG</p>
                <h1 class="mt-2 text-2xl font-bold text-[#0F172A]">Portal Orang Tua</h1>
                <p class="mt-1 text-xs text-[#64748B]">Masuk dengan nomor HP terdaftar</p>
            </div>

            @if(session('error'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs font-medium text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('public.guardian.authenticate') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="phone" class="mb-1 block text-xs font-semibold text-[#334155]">Nomor HP / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" required autocomplete="username" inputmode="tel"
                           class="portal-field"
                           placeholder="081234567890">
                    @error('phone')
                        <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="mb-1 block text-xs font-semibold text-[#334155]">Password</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                               class="portal-field pr-10"
                               placeholder="Masukkan password">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#64748B] hover:text-[#0F172A]"
                                :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                            <i data-lucide="eye" x-show="!show" class="h-4 w-4"></i>
                            <i data-lucide="eye-off" x-show="show" x-cloak class="h-4 w-4"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-[11px] leading-relaxed text-[#64748B]">
                        Password diberikan oleh petugas sekolah.
                    </p>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="flex min-h-12 w-full items-center justify-center rounded-[14px] bg-[#2563EB] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                        Masuk ke Portal Orang Tua
                    </button>
                </div>
            </form>

            <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-xs text-[#64748B]">
                <p class="font-semibold text-[#334155]">Anda siswa?</p>
                <p class="mt-1">
                    Masuk ke <a href="{{ route('public.student.login') }}" class="font-semibold text-[#2563EB] hover:underline">Portal Siswa</a> untuk melihat data Anda.
                </p>
            </div>
        </div>
    </div>
@endsection
