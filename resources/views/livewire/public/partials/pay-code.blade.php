@php
    /** @var \App\Models\PaymentIntent $intent */
    $payCode = $intent->pay_code;
    $isQris = $payCode === null && $intent->qr_string !== null;
@endphp

<div class="mt-3 rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs">
    <div class="flex items-center justify-between gap-2">
        <span class="font-semibold text-blue-900">Kode Bayar {{ $intent->provider->label() }}</span>
        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-blue-700">{{ $intent->channelLabel() }}</span>
    </div>

    @if($payCode !== null)
        <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2">
            <span class="select-all font-mono text-sm font-bold tracking-wider text-gray-900">{{ $payCode }}</span>
            <button type="button"
                    class="shrink-0 rounded-md border border-gray-200 px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-50"
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText(@js($payCode)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
            </button>
        </div>
    @elseif($isQris)
        <p class="mt-2 rounded-lg bg-white px-3 py-2 text-gray-700">
            Pindai kode QRIS pada aplikasi pembayaran Anda. Nominal terisi otomatis sebesar
            <span class="font-semibold">Rp {{ number_format((float) $intent->amount, 0, ',', '.') }}</span>.
        </p>
    @endif

    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1">
        <dt class="text-blue-800/80">Nominal</dt>
        <dd class="text-right font-semibold tabular-nums text-blue-900">Rp {{ number_format((float) $intent->amount, 0, ',', '.') }}</dd>
        <dt class="text-blue-800/80">Berlaku sampai</dt>
        <dd class="text-right font-medium text-blue-900">{{ $intent->expires_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} WIB</dd>
        <dt class="text-blue-800/80">Referensi</dt>
        <dd class="text-right font-mono text-[11px] text-blue-900">{{ $intent->reference }}</dd>
    </dl>

    <p class="mt-2 text-[11px] leading-relaxed text-blue-800/80">
        Pembayaran terverifikasi otomatis. Tidak perlu mengunggah bukti transfer.
    </p>
</div>
