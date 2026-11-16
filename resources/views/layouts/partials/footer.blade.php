@php
    $footerText = \App\Models\AppSetting::get('footer_text', '© KOMITE MAN 7 JOMBANG');
@endphp

<footer class="border-t border-[#e1e6ed] bg-white">
    <div class="px-3.5 sm:px-5 lg:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 text-[12px] text-[#8994a3]">
        <p>{{ $footerText }} — Aplikasi Pengelolaan Pembayaran Tagihan</p>
        <p>
            {{ now()->locale('id')->translatedFormat('d F Y') }}
            <span class="mx-1.5 text-[#cbd5e1]">|</span>
            <span class="tabular">{{ now()->format('H:i') }} WIB</span>
        </p>
    </div>
</footer>
