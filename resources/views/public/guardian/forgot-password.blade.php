@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-md py-4 sm:py-8">
        <div class="portal-card p-6 sm:p-8">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EFF6FF] text-[#2563EB]">
                    <i data-lucide="key-round" aria-hidden="true" class="h-6 w-6"></i>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#2563EB]">KOMITE MAN 7 JOMBANG</p>
                <h1 class="mt-2 text-2xl font-bold text-[#0F172A]">Lupa Password</h1>
                <p class="mt-1 text-xs text-[#64748B]">Masukkan nomor HP terdaftar. Kami kirim kode OTP lewat WhatsApp.</p>
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

            <form method="POST" action="{{ route('public.guardian.forgot.send') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="phone" class="mb-1 block text-xs font-semibold text-[#334155]">Nomor HP / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" required autofocus inputmode="tel"
                           class="portal-field"
                           placeholder="081234567890">
                    <p class="mt-1.5 text-[11px] leading-relaxed text-[#64748B]">
                        Gunakan nomor yang sama dengan saat masuk portal orang tua.
                    </p>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-[14px] bg-[#2563EB] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                        <i data-lucide="send" aria-hidden="true" class="h-4 w-4"></i>
                        Kirim Kode OTP
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-xs text-[#64748B]">
                Ingat password? <a href="{{ route('public.guardian.login') }}" class="font-semibold text-[#2563EB] hover:underline">Kembali masuk</a>
            </div>
        </div>
    </div>
@endsection