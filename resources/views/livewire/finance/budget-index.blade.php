@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Keuangan', 'url' => route('cash.expenses')], ['label' => 'RAPBM & Anggaran']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Anggaran RAPBM & Evaluasi Realisasi</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Rencana Anggaran Pendapatan dan Belanja Madrasah (RAPBM) disandingkan dengan realisasi dari buku besar.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @can('budgets.update')
                @if(!$isEditing)
                    <x-ui.button variant="primary" wire:click="enableEdit">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        <span>Edit Anggaran</span>
                    </x-ui.button>
                @else
                    <x-ui.button variant="secondary" wire:click="cancelEdit">
                        <span>Batal</span>
                    </x-ui.button>
                    <x-ui.button variant="primary" wire:click="saveBudget" loading="saveBudget">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span>Simpan Anggaran</span>
                    </x-ui.button>
                @endif
            @endcan
        </div>
    </div>

    <!-- Filter Year & Stat Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4 sm:col-span-2 lg:col-span-1 flex flex-col justify-center">
            <x-ui.select wire:model.live="academic_year_id" name="academic_year_id" label="Tahun Ajaran" :options="$academicYears" />
        </div>

        <x-ui.stat-card label="Anggaran Pendapatan" :value="\App\Support\Formatter::rupiah($totalPlannedRevenue)" tone="primary" icon="trending-up" :hint="'Realisasi: ' . \App\Support\Formatter::rupiah($totalActualRevenue)" />
        <x-ui.stat-card label="Anggaran Belanja" :value="\App\Support\Formatter::rupiah($totalPlannedExpense)" tone="orange" icon="trending-down" :hint="'Realisasi: ' . \App\Support\Formatter::rupiah($totalActualExpense)" />
        <x-ui.stat-card label="Target Surplus/Defisit" :value="\App\Support\Formatter::rupiah($plannedSurplus)" tone="{{ $plannedSurplus >= 0 ? 'success' : 'danger' }}" icon="scale" :hint="'Realisasi: ' . \App\Support\Formatter::rupiah($actualSurplus)" />
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$comparison->isEmpty()" empty-title="Belum Ada Akun Keuangan" empty-description="Tambahkan akun keuangan pada Data Master Akun terlebih dahulu.">
            <x-slot:head>
                <tr>
                    <th class="w-28">Kode Akun</th>
                    <th>Nama Akun</th>
                    <th class="w-28">Tipe</th>
                    <th class="w-44 text-right">Rencana (RAPBM)</th>
                    <th class="w-44 text-right">Realisasi (Buku Besar)</th>
                    <th class="w-36 text-right">Selisih</th>
                    <th class="w-24 text-right">% Capaian</th>
                    <th>Catatan Anggaran</th>
                </tr>
            </x-slot:head>

            @foreach($comparison as $row)
                <tr wire:key="budget-account-{{ $row->account->id }}">
                    <td class="font-mono text-xs font-semibold text-[#334155]">{{ $row->account->code }}</td>
                    <td class="font-medium text-[#111827]">{{ $row->account->name }}</td>
                    <td>
                        <x-ui.badge variant="{{ $row->account->type->value === 'revenue' ? 'success' : ($row->account->type->value === 'expense' ? 'warning' : 'neutral') }}">
                            {{ $row->account->type->label() }}
                        </x-ui.badge>
                    </td>
                    <td class="text-right tabular font-medium">
                        @if($isEditing)
                            <x-ui.input
                                wire:model="amounts.{{ $row->account->id }}"
                                name="amounts.{{ $row->account->id }}"
                                placeholder="0"
                                class="text-right !py-1 !px-2 text-xs"
                            />
                        @else
                            {{ \App\Support\Formatter::rupiah($row->planned) }}
                        @endif
                    </td>
                    <td class="text-right tabular font-medium text-[#1e293b]">
                        {{ \App\Support\Formatter::rupiah($row->actual) }}
                    </td>
                    <td class="text-right tabular text-xs font-semibold {{ $row->variance >= 0 ? 'text-[#2e7d32]' : 'text-[#c0392b]' }}">
                        {{ $row->variance >= 0 ? '+' : '' }}{{ \App\Support\Formatter::rupiah($row->variance) }}
                    </td>
                    <td class="text-right tabular text-xs font-bold {{ $row->percentage >= 100 ? 'text-[#2e7d32]' : 'text-[#64748b]' }}">
                        {{ number_format($row->percentage, 1) }}%
                    </td>
                    <td class="text-xs text-[#64748b]">
                        @if($isEditing)
                            <x-ui.input
                                wire:model="notes.{{ $row->account->id }}"
                                name="notes.{{ $row->account->id }}"
                                placeholder="Catatan anggaran..."
                                class="!py-1 !px-2 text-xs"
                            />
                        @else
                            {{ $row->notes ?: '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>
</div>
