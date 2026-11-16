@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tagihan', 'url' => route('bills.index')], ['label' => $studentBill->bill_number]]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Detail Tagihan</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">{{ $studentBill->bill_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canIssueIntent)
                @can('payment-gateways.create')
                    <x-ui.button variant="primary" wire:click="startIntent">
                        <i data-lucide="qr-code" class="w-4 h-4"></i>
                        <span>Terbitkan VA / QRIS</span>
                    </x-ui.button>
                @endcan
            @endif
            @if($canCancel)
                @can('bills.cancel')
                    <x-ui.button variant="danger" wire:click="cancel" wire:confirm="Batalkan tagihan ini? Tindakan ini tidak dapat dibatalkan.">
                        Batalkan Tagihan
                    </x-ui.button>
                @endcan
            @endif
            <a href="{{ route('bills.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="h-4 w-4"></i><span>Kembali</span>
            </a>
        </div>
    </div>

    @error('cancel')
        <x-ui.alert type="danger" :title="$message" />
    @enderror

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Informasi Tagihan">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-[#64748b]">Siswa</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->student->name }}</dd></div>
                <div><dt class="text-[#64748b]">NIS</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->student->nis }}</dd></div>
                <div><dt class="text-[#64748b]">Unit</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->student->unit?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Kelas</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->student->schoolClass?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Kategori</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->bill->paymentCategory?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Periode</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->bill->period ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Tahun Ajaran</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->bill->academicYear?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Jatuh Tempo</dt><dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->due_date?->format('d/m/Y') ?? '-' }}</dd></div>
                <div>
                    <dt class="text-[#64748b]">Status</dt>
                    <dd class="mt-1">
                        <x-ui.badge :variant="match($studentBill->status->value) { 'paid' => 'success', 'partial' => 'info', 'cancelled' => 'danger', default => 'warning' }">
                            {{ $studentBill->status->label() }}
                        </x-ui.badge>
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Rincian Nominal">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-[#64748b]">Nominal Awal</dt><dd class="tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $studentBill->original_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748b]">Potongan</dt><dd class="tabular text-[#111827]">- {{ \App\Support\Formatter::rupiah((float) $studentBill->discount_amount) }}</dd></div>
                <div class="flex justify-between border-t pt-3"><dt class="font-medium text-[#111827]">Total Tagihan</dt><dd class="tabular font-semibold text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $studentBill->final_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748b]">Dibayar</dt><dd class="tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $studentBill->paid_amount) }}</dd></div>
                <div class="flex justify-between border-t pt-3"><dt class="font-medium text-[#111827]">Sisa</dt><dd class="tabular font-semibold {{ (float) $studentBill->remaining_amount > 0 ? 'text-[#b91c1c]' : 'text-[#15803d]' }}">{{ \App\Support\Formatter::rupiah((float) $studentBill->remaining_amount) }}</dd></div>
            </dl>
        </x-ui.card>
    </div>

    @if($studentBill->bill?->items->isNotEmpty())
        <x-ui.card title="Rincian Item Tagihan" subtitle="Komponen biaya yang menyusun tagihan ini.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-[#64748b]"><th class="pb-3">Deskripsi</th><th class="pb-3 text-right">Nominal</th></tr></thead>
                    <tbody>
                        @foreach($studentBill->bill->items->sortBy('sort_order') as $item)
                            <tr class="border-b last:border-0">
                                <td class="py-3 text-[#111827]">{{ $item->description }}</td>
                                <td class="py-3 text-right tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $item->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t">
                            <td class="pt-3 font-medium text-[#111827]">Total Item</td>
                            <td class="pt-3 text-right tabular font-semibold text-[#111827]">
                                {{ \App\Support\Formatter::rupiah((float) $studentBill->bill->items->sum('amount')) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-ui.card>
    @endif

    @if($studentBill->discounts->isNotEmpty())
        <x-ui.card title="Riwayat Potongan">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-[#64748b]"><th class="pb-3">Jenis</th><th class="pb-3">Nilai</th><th class="pb-3 text-right">Nominal</th><th class="pb-3">Keterangan</th></tr></thead>
                    <tbody>
                        @foreach($studentBill->discounts as $discount)
                            <tr class="border-b last:border-0">
                                <td class="py-3">{{ $discount->type === \App\Services\DiscountService::TYPE_PERCENT ? 'Persen' : 'Nominal' }}</td>
                                <td class="py-3 tabular">
                                    @if($discount->type === \App\Services\DiscountService::TYPE_PERCENT)
                                        {{ rtrim(rtrim(number_format((float) $discount->value, 2, ',', '.'), '0'), ',') }}%
                                    @else
                                        {{ \App\Support\Formatter::rupiah((float) $discount->value) }}
                                    @endif
                                </td>
                                <td class="py-3 text-right tabular">{{ \App\Support\Formatter::rupiah((float) $discount->amount) }}</td>
                                <td class="py-3">{{ $discount->reason ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    @if($intents->isNotEmpty())
        <x-ui.card title="Tagihan Digital (VA / QRIS)" subtitle="Nomor bayar yang diterbitkan untuk tagihan ini. Status diperbarui otomatis oleh notifikasi gateway.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-[#64748b]">
                            <th class="pb-3">Referensi</th>
                            <th class="pb-3">Penyedia</th>
                            <th class="pb-3">Kode Bayar</th>
                            <th class="pb-3 text-right">Nominal</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Kedaluwarsa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($intents as $intent)
                            <tr class="border-b last:border-0" wire:key="bill-intent-{{ $intent->id }}">
                                <td class="py-3 tabular text-[#111827]">{{ $intent->reference }}</td>
                                <td class="py-3">
                                    {{ $intent->provider->label() }}
                                    <span class="text-[12px] text-[#8994a3]">({{ $intent->channelLabel() }})</span>
                                </td>
                                <td class="py-3 tabular text-[#475569]">{{ $intent->pay_code ?? ($intent->qr_string ? 'QRIS' : '-') }}</td>
                                <td class="py-3 text-right tabular">{{ \App\Support\Formatter::rupiah((float) $intent->amount) }}</td>
                                <td class="py-3">
                                    <x-ui.badge :variant="$intent->status->badgeVariant()">{{ $intent->status->label() }}</x-ui.badge>
                                </td>
                                <td class="py-3 text-[#475569]">{{ $intent->expires_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.modal name="showIntentModal" title="Terbitkan Tagihan Digital" subtitle="Nominal mengikuti sisa tagihan saat ini.">
        <form wire:submit="issueIntent" class="space-y-4" novalidate>
            @error('gatewayProvider')
                <x-ui.alert type="danger" :title="$message" />
            @enderror

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[#64748b]">Sisa Tagihan</dt>
                    <dd class="mt-1 font-semibold tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $studentBill->remaining_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-[#64748b]">Siswa</dt>
                    <dd class="mt-1 font-medium text-[#111827]">{{ $studentBill->student?->name }}</dd>
                </div>
            </dl>

            <x-ui.select wire:model.live="gatewayProvider" name="gatewayProvider" label="Penyedia Pembayaran" :options="$providerOptions" required />
            <x-ui.select wire:model="gatewayChannel" name="gatewayChannel" label="Kanal Pembayaran" :options="$channelOptions" required />

            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showIntentModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="issueIntent">
                    <i data-lucide="qr-code" class="w-4 h-4"></i>
                    <span>Terbitkan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
