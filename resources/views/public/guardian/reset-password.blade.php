@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-md py-4 sm:py-8">
        <div class="portal-card p-6 sm:p-8">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EFF6FF] text-[#2563EB]">
                    <i data-lucide="shield-check" aria-hidden="true" class="h-6 w-6"></i>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#2563EB]">KOMITE MAN 7 JOMBANG</p>
                <h1 class="mt-2 text-2xl font-bold text-[#0F172A]">Verifikasi OTP</h1>
                <p class="mt-1 text-xs text-[#64748B]">Masukkan kode 6 angka dari WhatsApp dan password baru Anda.</p>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-medium text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs font-medium text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('public.guardian.forgot.reset') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="phone" class="mb-1 block text-xs font-semibold text-[#334155]">Nomor HP / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $phone) }}" required inputmode="tel"
                           class="portal-field" placeholder="081234567890">
                </div>

                <div>
                    <label for="otp" class="mb-1 block text-xs font-semibold text-[#334155]">Kode OTP</label>
                    <input type="text" id="otp" name="otp" value="{{ old('otp') }}" required inputmode="numeric"
                           maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code"
                           class="portal-field text-center font-mono text-lg tracking-[0.4em]"
                           placeholder="______">
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="mb-1 block text-xs font-semibold text-[#334155]">Password Baru</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" id="password" name="password" required autocomplete="new-password"
                               class="portal-field pr-10" placeholder="Minimal 8 karakter">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#64748B] hover:text-[#0F172A]"
                                :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                            <i data-lucide="eye" x-show="!show" class="h-4 w-4"></i>
                            <i data-lucide="eye-off" x-show="show" x-cloak class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-xs font-semibold text-[#334155]">Ulangi Password Baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                           class="portal-field" placeholder="Ulangi password baru">
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-[14px] bg-[#2563EB] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                        <i data-lucide="lock-keyhole" aria-hidden="true" class="h-4 w-4"></i>
                        Simpan Password Baru
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-xs text-[#64748B]">
                Belum menerima kode?
                <a href="{{ route('public.guardian.forgot') }}" class="font-semibold text-[#2563EB] hover:underline">Kirim ulang OTP</a>
            </div>
        </div>
    </div>
@endsection