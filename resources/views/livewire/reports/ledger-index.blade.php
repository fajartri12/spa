@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Buku Besar & Neraca Saldo']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Buku Besar & Neraca Saldo</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Laporan akuntansi dari jurnal umum yang sudah diposting (posted).
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[#e5eaf0] flex items-center gap-6 text-sm font-medium">
        <button type="button" wire:click="$set('activeTab', 'trial_balance')"
            class="pb-3 border-b-2 transition {{ $activeTab === 'trial_balance' ? 'border-[#2872c8] text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#1e293b]' }}">
            <i data-lucide="scale" class="w-4 h-4 inline-block -mt-0.5 mr-1.5"></i>
            Neraca Saldo
        </button>
        <button type="button" wire:click="$set('activeTab', 'ledger')"
            class="pb-3 border-b-2 transition {{ $activeTab === 'ledger' ? 'border-[#2872c8] text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#1e293b]' }}">
            <i data-lucide="book-open" class="w-4 h-4 inline-block -mt-0.5 mr-1.5"></i>
            Buku Besar
        </button>
        <button type="button" wire:click="$set('activeTab', 'profit_loss')"
            class="pb-3 border-b-2 transition {{ $activeTab === 'profit_loss' ? 'border-[#2872c8] text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#1e293b]' }}">
            <i data-lucide="trending-up" class="w-4 h-4 inline-block -mt-0.5 mr-1.5"></i>
            Laba Rugi
        </button>
    </div>

    <!-- Filter -->
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select wire:model.live="academic_year_id" name="academic_year_id" label="Tahun Ajaran" placeholder="Semua Tahun Ajaran" :options="$academicYears" />
            <x-ui.input wire:model.live="from" name="from" type="date" label="Dari Tanggal" />
            <x-ui.input wire:model.live="to" name="to" type="date" label="Sampai Tanggal" />
            @if($activeTab === 'ledger')
                <x-ui.select wire:model.live="selectedAccountId" name="selectedAccountId" label="Pilih Akun" :options="$accounts" placeholder="-- Pilih Akun --" />
            @endif
        </div>
    </div>

    @if($activeTab === 'trial_balance')
        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table :empty="$trialBalance->isEmpty()" empty-title="Belum Ada Data Jurnal" empty-description="Neraca saldo dihitung dari jurnal yang telah diposting.">
                <x-slot:head>
                    <tr>
                        <th class="w-28">Kode</th>
                        <th>Nama Akun</th>
                        <th class="w-24">Tipe</th>
                        <th class="w-32 text-right">Saldo Awal D</th>
                        <th class="w-32 text-right">Saldo Awal K</th>
                        <th class="w-32 text-right">Mutasi Debit</th>
                        <th class="w-32 text-right">Mutasi Kredit</th>
                        <th class="w-36 text-right">Saldo Akhir</th>
                    </tr>
                </x-slot:head>

                @foreach($trialBalance as $row)
                    <tr wire:key="tb-{{ $row->account->id }}">
                        <td class="font-mono text-xs font-semibold text-[#334155]">{{ $row->account->code }}</td>
                        <td class="font-medium text-[#111827]">{{ $row->account->name }}</td>
                        <td>
                            <x-ui.badge variant="{{ $row->account->type->badgeVariant() }}">{{ $row->account->type->label() }}</x-ui.badge>
                        </td>
                        <td class="text-right tabular text-xs">{{ $row->opening_debit > 0 ? \App\Support\Formatter::rupiah($row->opening_debit) : '—' }}</td>
                        <td class="text-right tabular text-xs">{{ $row->opening_credit > 0 ? \App\Support\Formatter::rupiah($row->opening_credit) : '—' }}</td>
                        <td class="text-right tabular text-xs">{{ $row->debit > 0 ? \App\Support\Formatter::rupiah($row->debit) : '—' }}</td>
                        <td class="text-right tabular text-xs">{{ $row->credit > 0 ? \App\Support\Formatter::rupiah($row->credit) : '—' }}</td>
                        <td class="text-right tabular font-semibold {{ $row->balance >= 0 ? 'text-[#1e293b]' : 'text-[#c0392b]' }}">
                            {{ \App\Support\Formatter::rupiah($row->balance) }}
                        </td>
                    </tr>
                @endforeach

                <tr class="bg-[#f8fafc] font-semibold border-t-2 border-[#cbd5e1]">
                    <td colspan="3" class="text-right text-[#334155]">TOTAL</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($totalOpeningDebit) }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($totalOpeningCredit) }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($totalDebit) }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($totalCredit) }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($totalDebit - $totalCredit + $totalOpeningDebit - $totalOpeningCredit) }}</td>
                </tr>
            </x-ui.table>
        </div>
    @elseif($activeTab === 'ledger' && $accountLedger !== null)
        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <div class="px-4 py-3 bg-[#f8fafc] border-b border-[#e5eaf0] flex items-center justify-between">
                <div>
                    <span class="font-mono text-xs font-semibold text-[#2872c8]">{{ $accountLedger['account']->code }}</span>
                    <h2 class="text-sm font-bold text-[#1e293b]">{{ $accountLedger['account']->name }}</h2>
                </div>
                <div class="text-right">
                    <div class="text-xs text-[#64748b]">Saldo Awal</div>
                    <div class="text-sm font-bold text-[#1e293b]">{{ \App\Support\Formatter::rupiah($accountLedger['opening']) }}</div>
                </div>
            </div>

            <x-ui.table :empty="count($accountLedger['rows']) === 0" empty-title="Belum Ada Mutasi" empty-description="Tidak ada transaksi jurnal pada akun ini untuk periode terpilih.">
                <x-slot:head>
                    <tr>
                        <th class="w-28">Tanggal</th>
                        <th class="w-36">No. Jurnal</th>
                        <th>Keterangan</th>
                        <th class="w-36 text-right">Debit</th>
                        <th class="w-36 text-right">Kredit</th>
                        <th class="w-36 text-right">Saldo</th>
                    </tr>
                </x-slot:head>

                @foreach($accountLedger['rows'] as $idx => $row)
                    <tr wire:key="ledger-{{ $idx }}">
                        <td class="text-xs text-[#475569]">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                        <td class="font-mono text-xs">{{ $row['journal_number'] }}</td>
                        <td class="text-[#334155]">{{ $row['description'] }}</td>
                        <td class="text-right tabular text-xs">{{ $row['debit'] > 0 ? \App\Support\Formatter::rupiah($row['debit']) : '—' }}</td>
                        <td class="text-right tabular text-xs">{{ $row['credit'] > 0 ? \App\Support\Formatter::rupiah($row['credit']) : '—' }}</td>
                        <td class="text-right tabular font-medium text-[#1e293b]">{{ \App\Support\Formatter::rupiah($row['balance']) }}</td>
                    </tr>
                @endforeach

                <tr class="bg-[#f8fafc] font-semibold border-t-2 border-[#cbd5e1]">
                    <td colspan="3" class="text-right text-[#334155]">TOTAL MUTASI</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($accountLedger['debit']) }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($accountLedger['credit']) }}</td>
                    <td class="text-right tabular text-xs text-[#2872c8]">{{ \App\Support\Formatter::rupiah($accountLedger['closing']) }}</td>
                </tr>
            </x-ui.table>
        </div>
    @elseif($activeTab === 'profit_loss' && $profitLoss !== null)
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Total Pendapatan" :value="\App\Support\Formatter::rupiah($profitLoss['revenue'])" tone="success" icon="trending-up" />
            <x-ui.stat-card label="Total Beban" :value="\App\Support\Formatter::rupiah($profitLoss['expense'])" tone="danger" icon="trending-down" />
            <x-ui.stat-card label="Surplus / Defisit" :value="\App\Support\Formatter::rupiah($profitLoss['surplus'])" tone="{{ $profitLoss['surplus'] >= 0 ? 'primary' : 'danger' }}" icon="scale" />
        </div>

        <div class="card p-5">
            <h2 class="text-sm font-semibold text-[#1e293b] mb-4">Ringkasan Laba Rugi Periode</h2>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between pb-2 border-b border-[#eef2f7]">
                    <span class="text-[#475569]">Total Pendapatan (Revenue)</span>
                    <span class="tabular font-semibold text-[#2e7d32]">{{ \App\Support\Formatter::rupiah($profitLoss['revenue']) }}</span>
                </div>
                <div class="flex items-center justify-between pb-2 border-b border-[#eef2f7]">
                    <span class="text-[#475569]">Total Beban (Expense)</span>
                    <span class="tabular font-semibold text-[#c0392b]">({{ \App\Support\Formatter::rupiah($profitLoss['expense']) }})</span>
                </div>
                <div class="flex items-center justify-between pt-2">
                    <span class="font-bold text-[#1e293b]">Surplus / Defisit Bersih</span>
                    <span class="tabular font-bold text-base {{ $profitLoss['surplus'] >= 0 ? 'text-[#2872c8]' : 'text-[#c0392b]' }}">
                        {{ \App\Support\Formatter::rupiah($profitLoss['surplus']) }}
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
