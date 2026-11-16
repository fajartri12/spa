@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pembayaran', 'url' => route('payments.index')], ['label' => $payment->transaction_number]]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Detail Pembayaran</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">{{ $payment->transaction_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('payments.print')
                <a href="{{ route('payments.receipt', $payment) }}" class="btn btn-secondary">
                    <i data-lucide="printer" class="h-4 w-4"></i><span>Kwitansi PDF</span>
                </a>
            @endcan
            <a href="{{ route('payments.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="h-4 w-4"></i><span>Kembali</span>
            </a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <h2 class="text-base font-semibold text-[#111827]">Informasi Pembayaran</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                <div><dt class="text-[#64748b]">Siswa</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payment->student->name }}</dd></div>
                <div><dt class="text-[#64748b]">Tanggal Pembayaran</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payment->paid_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-[#64748b]">Kategori</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payment->paymentCategory->name }}</dd></div>
                <div><dt class="text-[#64748b]">Nominal</dt><dd class="mt-1 font-medium text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $payment->amount) }}</dd></div>
                <div><dt class="text-[#64748b]">Metode</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payment->details->first()?->paymentMethod?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Status</dt><dd class="mt-1"><x-ui.badge :variant="match($status->value) { 'approved' => 'success', 'rejected', 'cancelled' => 'danger', default => 'warning' }">{{ match($status->value) { 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', default => 'Menunggu Verifikasi' } }}</x-ui.badge></dd></div>
            </dl>
            @if($payment->notes)<div class="mt-4 border-t pt-4"><p class="text-sm text-[#64748b]">Catatan</p><p class="mt-1 text-sm text-[#111827]">{{ $payment->notes }}</p></div>@endif
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-base font-semibold text-[#111827]">Tindakan</h2>
            @if($status->value === 'pending')
                <div class="mt-4 space-y-2">
                    @can('payments.verify')
                        <x-ui.button variant="primary" wire:click="approve" wire:confirm="Setujui pembayaran ini?" class="w-full">Setujui Pembayaran</x-ui.button>
                        <x-ui.textarea wire:model="reason" name="reason" label="Alasan penolakan" placeholder="Wajib diisi jika ditolak." rows="3" />
                        <x-ui.button variant="danger" wire:click="reject" wire:confirm="Tolak pembayaran ini?" class="w-full">Tolak Pembayaran</x-ui.button>
                    @endcan
                </div>
            @endif
            @if($status->value !== 'cancelled')
                @can('payments.cancel')
                    <div class="mt-4 {{ $status->value === 'pending' ? 'border-t pt-4' : '' }}">
                        <x-ui.textarea wire:model="reason" name="reason" label="Alasan pembatalan" placeholder="Wajib diisi untuk pembatalan." rows="3" />
                        <x-ui.button variant="danger" wire:click="cancel" wire:confirm="Batalkan pembayaran ini?" class="mt-2 w-full">Batalkan Pembayaran</x-ui.button>
                    </div>
                @endcan
            @endif
        </x-ui.card>
    </div>

    <x-ui.card>
        <h2 class="text-base font-semibold text-[#111827]">Alokasi Tagihan</h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left text-[#64748b]"><th class="pb-3">No. Tagihan</th><th class="pb-3">Periode</th><th class="pb-3 text-right">Alokasi</th></tr></thead><tbody>
            @foreach($payment->allocations as $allocation)<tr class="border-b last:border-0"><td class="py-3 font-medium text-[#111827]">{{ $allocation->studentBill->bill->bill_number }}</td><td class="py-3">{{ $allocation->studentBill->bill->period }}</td><td class="py-3 text-right tabular">{{ \App\Support\Formatter::rupiah((float) $allocation->amount) }}</td></tr>@endforeach
        </tbody></table></div>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-base font-semibold text-[#111827]">Bukti Pembayaran</h2>
        @if($payment->proofs->isEmpty())
            <p class="mt-2 text-[13px] text-[#64748b]">Belum ada bukti pembayaran yang diunggah.</p>
        @else
            <ul class="mt-4 space-y-2">
                @foreach($payment->proofs as $proof)
                    <li class="flex items-center justify-between rounded-lg border border-[#e2e8f0] px-3 py-2" wire:key="proof-{{ $proof->id }}">
                        <div class="min-w-0">
                            <a href="{{ route('payment-proofs.download', $proof) }}" class="truncate text-sm font-medium text-[#1d4ed8] hover:underline">{{ $proof->original_name }}</a>
                            <p class="text-[12px] text-[#8994a3]">{{ number_format(($proof->file_size ?? 0) / 1024, 0) }} KB · {{ $proof->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @can('payments.create')
                            <button type="button" class="btn btn-ghost btn-sm text-[#b91c1c]" wire:click="deleteProof({{ $proof->id }})" wire:confirm="Hapus bukti pembayaran ini?">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif

        @can('payments.create')
            <div class="mt-4 border-t pt-4">
                <label class="mb-1 block text-[13px] font-medium text-[#374151]" for="proof">Unggah Bukti (JPG, PNG, PDF — maks 5 MB)</label>
                <input id="proof" type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-[13px] file:mr-3 file:rounded-md file:border-0 file:bg-[#f1f5f9] file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-[#334155]">
                @error('proof') <p class="mt-1 text-[12px] text-[#b91c1c]">{{ $message }}</p> @enderror
                <div class="mt-3 flex items-center gap-2">
                    <x-ui.button variant="primary" wire:click="uploadProof" wire:loading.attr="disabled" wire:target="uploadProof,proof">Unggah</x-ui.button>
                    <span wire:loading wire:target="proof" class="text-[12px] text-[#64748b]">Mengunggah…</span>
                </div>
            </div>
        @endcan
    </x-ui.card>
</div>

