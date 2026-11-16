@php
    use App\Support\Formatter;
    use App\Livewire\Reports\CashFlowIndex;

    $hasFilter = $search !== '' || $typeFilter !== '' || $accountId !== null
        || $dateFrom !== now()->startOfMonth()->toDateString()
        || $dateTo !== now()->toDateString();
@endphp

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Arus Kas']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Arus Kas</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">Pemasukan dan pengeluaran kas pada rentang tanggal terpilih.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('reports.export')
                <button type="button" wire:click="export" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="export">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Ekspor</span>
                </button>
                <a href="{{ route('reports.print.download', ['type' => 'cash_flow', 'from' => $dateFrom, 'to' => $dateTo]) }}" class="btn btn-secondary" target="_blank" rel="noopener">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Cetak Laporan</span>
                </a>
            @endcan
            <a href="{{ route('accounts.index') }}" class="btn btn-primary">
                <i data-lucide="landmark" class="w-4 h-4"></i>
                <span>Akun Kas</span>
            </a>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total Kas Akhir" :value="Formatter::rupiah($closingBalance)" icon="wallet" tone="primary" />
        <x-ui.stat-card label="Transaksi Pemasukan" :value="Formatter::rupiah($incomeTotal)" icon="trending-up" tone="success" />
        <x-ui.stat-card label="Transaksi Pengeluaran" :value="Formatter::rupiah($expenseTotal)" icon="trending-down" tone="danger" />
        <x-ui.stat-card label="Total Transaksi" :value="Formatter::rupiah($incomeTotal + $expenseTotal)" icon="arrow-left-right" tone="primary-dark" :hint="number_format($transactionCount, 0, ',', '.') . ' transaksi'" />
    </div>

    {{-- Filter --}}
    <div class="card overflow-hidden">
        <button
            type="button"
            wire:click="toggleFilters"
            class="flex w-full items-center justify-between gap-3 px-5 py-3 text-left"
            aria-expanded="{{ $showFilters ? 'true' : 'false' }}"
            aria-controls="cash-flow-filters"
        >
            <span class="flex items-center gap-2 text-[14px] font-semibold text-[#111827]">
                <i data-lucide="list-filter" class="w-4 h-4 text-[#2872c8]"></i>
                Filter Data
                @if($hasFilter)
                    <span class="badge badge-primary">Aktif</span>
                @endif
            </span>
            <i data-lucide="{{ $showFilters ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4 text-[#8994a3]"></i>
        </button>

        <div id="cash-flow-filters" @if(! $showFilters) hidden @endif class="border-t border-[#e1e6ed] px-5 py-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Nomor transaksi, siswa, akun..." icon="search" />
                <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari Tanggal" />
                <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai Tanggal" />
                <x-ui.select wire:model.live="typeFilter" name="typeFilter" label="Jenis Transaksi" placeholder="Semua Jenis" :options="$types" />
                <x-ui.select wire:model.live="accountId" name="accountId" label="Akun Kas" :options="$accounts" placeholder="Semua Akun" />
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="resetFilters">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        <span>Reset Filter</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <div class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[13px] font-semibold text-[#111827]">
                Daftar Arus Kas
                <span class="ml-1 font-normal text-[#8994a3]">({{ number_format($transactionCount, 0, ',', '.') }} transaksi)</span>
            </p>

            <label class="flex items-center gap-2 text-[13px] text-[#64748b]">
                <span>Tampilkan</span>
                <select wire:model.live="perPage" class="form-select !h-8 !w-[76px] !text-[13px]" aria-label="Jumlah data per halaman">
                    @foreach($perPageOptions as $value => $text)
                        <option value="{{ $value }}" @selected((int) $perPage === (int) $value)>{{ $text }}</option>
                    @endforeach
                </select>
                <span>data</span>
            </label>
        </div>

        <x-ui.table :empty="$transactions->isEmpty()" empty-title="Belum Ada Arus Kas" empty-description="Belum ada transaksi kas pada filter yang dipilih.">
            <x-slot:head>
                <tr>
                    <th class="w-12 text-center">#</th>
                    <th>
                        <button type="button" wire:click="sortBy('transaction_number')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#2872c8]">
                            No. Transaksi
                            <i data-lucide="arrow-up-down" class="w-3 h-3 {{ $sortField === 'transaction_number' ? 'text-[#2872c8]' : 'text-[#cbd5e1]' }}"></i>
                        </button>
                    </th>
                    <th>Nama Unit</th>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Tujuan Transaksi</th>
                    <th>Jenis Transaksi</th>
                    <th class="text-right">
                        <button type="button" wire:click="sortBy('amount')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#2872c8]">
                            Jml. Transaksi
                            <i data-lucide="arrow-up-down" class="w-3 h-3 {{ $sortField === 'amount' ? 'text-[#2872c8]' : 'text-[#cbd5e1]' }}"></i>
                        </button>
                    </th>
                    <th>
                        <button type="button" wire:click="sortBy('transacted_at')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#2872c8]">
                            Waktu Transaksi
                            <i data-lucide="arrow-up-down" class="w-3 h-3 {{ $sortField === 'transacted_at' ? 'text-[#2872c8]' : 'text-[#cbd5e1]' }}"></i>
                        </button>
                    </th>
                    <th class="w-20 text-center">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($transactions as $transaction)
                <tr wire:key="cash-{{ $transaction->id }}">
                    <td class="text-center text-[#8994a3]">{{ $transactions->firstItem() + $loop->index }}</td>
                    <td class="font-medium whitespace-nowrap text-[#111827]">{{ $transaction->transaction_number }}</td>
                    <td class="whitespace-nowrap">{{ $transaction->payment?->student?->unit?->name ?? '-' }}</td>
                    <td class="whitespace-nowrap tabular">{{ $transaction->account?->code ?? '-' }}</td>
                    <td class="whitespace-nowrap">{{ $transaction->account?->name ?? '-' }}</td>
                    <td class="min-w-[240px]">{{ $transaction->description }}</td>
                    <td class="whitespace-nowrap">
                        <x-ui.badge :variant="CashFlowIndex::typeBadge($transaction->type)">
                            {{ CashFlowIndex::typeLabel($transaction->type) }}
                        </x-ui.badge>
                    </td>
                    <td class="whitespace-nowrap text-right tabular {{ $transaction->type === \App\Enums\CashTransactionType::Expense ? 'text-[#c0392b]' : 'text-[#111827]' }}">
                        {{ Formatter::rupiah((float) $transaction->amount) }}
                    </td>
                    <td class="whitespace-nowrap tabular">{{ $transaction->transacted_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="text-center">
                        @if($transaction->payment_id)
                            <div class="inline-flex items-center gap-0.5 rounded-[5px] border border-[#e1e6ed] bg-white p-0.5">
                                @can('payments.view')
                                    <a href="{{ route('payments.show', $transaction->payment_id) }}" class="btn-icon !w-7 !h-7" title="Lihat pembayaran" aria-label="Lihat pembayaran {{ $transaction->transaction_number }}">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    </a>
                                @endcan
                                @can('payments.print')
                                    <a href="{{ route('payments.receipt', $transaction->payment_id) }}" target="_blank" rel="noopener" class="btn-icon !w-7 !h-7" title="Cetak kwitansi" aria-label="Cetak kwitansi {{ $transaction->transaction_number }}">
                                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                    </a>
                                @endcan
                            </div>
                        @else
                            <span class="text-[#cbd5e1]">&mdash;</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$transactions" label="transaksi kas" />
    </div>
</div>