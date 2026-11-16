@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Jurnal Umum']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Jurnal Umum</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Jurnal dibuat otomatis dari pengeluaran kas dan penerimaan tagihan PPDB. Klik baris untuk melihat rincian debit/kredit.
            </p>
        </div>
        <a href="{{ route('reports.cash-flow') }}" class="btn btn-secondary">
            <i data-lucide="banknote" class="w-4 h-4"></i>
            <span>Arus Kas</span>
        </a>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Nomor jurnal, keterangan, referensi..." icon="search" />
            </div>
            <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari Tanggal" />
            <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai Tanggal" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statusOptions" />
            <div class="flex items-end gap-2 lg:col-span-3">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    <div class="card px-4 py-3 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-[13px] text-[#64748b]">
            <i data-lucide="scale" class="w-4 h-4 text-[#2872c8]"></i>
            <span>Total debit dan kredit pada filter ini</span>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-right">
                <div class="text-[11px] uppercase tracking-wide text-[#8994a3]">Debit</div>
                <div class="text-[16px] font-semibold tabular text-[#111827]">{{ \App\Support\Formatter::rupiah($debitTotal) }}</div>
            </div>
            <div class="text-right">
                <div class="text-[11px] uppercase tracking-wide text-[#8994a3]">Kredit</div>
                <div class="text-[16px] font-semibold tabular text-[#111827]">{{ \App\Support\Formatter::rupiah($creditTotal) }}</div>
            </div>
            @if(abs($debitTotal - $creditTotal) < 0.005)
                <x-ui.badge variant="success" dot>Seimbang</x-ui.badge>
            @else
                <x-ui.badge variant="danger" dot>Tidak Seimbang</x-ui.badge>
            @endif
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$journals->isEmpty()" empty-title="Belum Ada Jurnal" empty-description="Jurnal terbentuk otomatis saat pengeluaran kas atau tagihan PPDB dibayar.">
            <x-slot:head>
                <tr>
                    <th class="w-44">Nomor</th>
                    <th class="w-32">Tanggal</th>
                    <th>Keterangan</th>
                    <th class="w-36 text-right">Debit</th>
                    <th class="w-36 text-right">Kredit</th>
                    <th class="w-28">Status</th>
                </tr>
            </x-slot:head>

            @foreach($journals as $journal)
                <tr wire:key="journal-{{ $journal->id }}" wire:click="toggle({{ $journal->id }})" class="cursor-pointer">
                    <td class="tabular text-[#475569]">{{ $journal->journal_number }}</td>
                    <td class="text-[#475569]">{{ $journal->journal_date?->translatedFormat('d M Y') ?? '-' }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $journal->description }}</div>
                        <div class="text-[12px] text-[#8994a3]">
                            {{ $journal->reference_number ?? '-' }} &middot; {{ $journal->user?->name ?? '-' }}
                        </div>
                    </td>
                    <td class="text-right tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $journal->details->sum('debit')) }}</td>
                    <td class="text-right tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $journal->details->sum('credit')) }}</td>
                    <td>
                        @if($journal->status === \App\Enums\JournalStatus::Posted)
                            <x-ui.badge variant="success" dot>Posted</x-ui.badge>
                        @elseif($journal->status === \App\Enums\JournalStatus::Voided)
                            <x-ui.badge variant="danger" dot>Void</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Draft</x-ui.badge>
                        @endif
                    </td>
                </tr>

                @if($expandedId === $journal->id)
                    <tr wire:key="journal-detail-{{ $journal->id }}" class="bg-[#f8fafc]">
                        <td colspan="6" class="p-0">
                            <div class="px-6 py-3">
                                <table class="w-full text-[12px]">
                                    <thead>
                                        <tr class="text-left text-[#8994a3]">
                                            <th class="pb-2">Akun</th>
                                            <th class="pb-2">Keterangan</th>
                                            <th class="pb-2 text-right w-32">Debit</th>
                                            <th class="pb-2 text-right w-32">Kredit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($journal->details as $detail)
                                            <tr class="border-t border-[#e2e8f0]" wire:key="detail-{{ $detail->id }}">
                                                <td class="py-2 text-[#111827]">{{ $detail->account?->code }} - {{ $detail->account?->name }}</td>
                                                <td class="py-2 text-[#64748b]">{{ $detail->description ?? '-' }}</td>
                                                <td class="py-2 text-right tabular">{{ (float) $detail->debit > 0 ? \App\Support\Formatter::rupiah((float) $detail->debit) : '-' }}</td>
                                                <td class="py-2 text-right tabular">{{ (float) $detail->credit > 0 ? \App\Support\Formatter::rupiah((float) $detail->credit) : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$journals" label="jurnal" />
    </div>
</div>
