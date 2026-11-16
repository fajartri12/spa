@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Keuangan'], ['label' => 'Kas Keluar']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Kas Keluar</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Catat pengeluaran operasional madrasah. Setiap pengeluaran langsung membentuk jurnal debit beban dan kredit kas/bank.
            </p>
        </div>

        @can('cash-transactions.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Catat Pengeluaran</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Keterangan, nomor, atau referensi..." icon="search" />
            </div>
            <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari Tanggal" />
            <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai Tanggal" />
            <x-ui.select wire:model.live="accountId" name="accountId" label="Akun Kas/Bank" placeholder="Semua Akun" :options="$filterAccounts" />
            <div class="flex items-end gap-2 lg:col-span-3">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    <div class="card px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-[13px] text-[#64748b]">
            <i data-lucide="arrow-up-right" class="w-4 h-4 text-[#c0392b]"></i>
            <span>Total pengeluaran sesuai filter</span>
        </div>
        <div class="text-[18px] font-semibold text-[#c0392b] tabular">{{ \App\Support\Formatter::rupiah($totalExpense) }}</div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$transactions->isEmpty()" empty-title="Belum Ada Pengeluaran" empty-description="Pengeluaran yang dicatat di sini otomatis masuk laporan arus kas.">
            <x-slot:head>
                <tr>
                    <th class="w-44">Nomor</th>
                    <th class="w-32">Tanggal</th>
                    <th>Keterangan</th>
                    <th class="w-44">Akun Kas/Bank</th>
                    <th class="w-36 text-right">Nominal</th>
                    <th class="w-32">Petugas</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($transactions as $transaction)
                <tr wire:key="cash-expense-{{ $transaction->id }}">
                    <td class="tabular text-[#475569]">{{ $transaction->transaction_number }}</td>
                    <td class="text-[#475569]">{{ $transaction->transacted_at?->translatedFormat('d M Y') ?? '-' }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $transaction->description }}</div>
                        @if($transaction->reference_number)
                            <div class="text-[12px] text-[#8994a3]">Ref: {{ $transaction->reference_number }}</div>
                        @endif
                    </td>
                    <td class="text-[#475569]">
                        {{ $transaction->account?->code }} - {{ $transaction->account?->name }}
                    </td>
                    <td class="text-right tabular font-medium text-[#c0392b]">
                        {{ \App\Support\Formatter::rupiah((float) $transaction->amount) }}
                    </td>
                    <td class="text-[#64748b]">{{ $transaction->user?->name ?? '-' }}</td>
                    <td>
                        <div class="flex items-center justify-end">
                            @can('cash-transactions.delete')
                                <button type="button" wire:click="confirmVoid({{ $transaction->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Batalkan pengeluaran" aria-label="Batalkan pengeluaran {{ $transaction->transaction_number }}">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$transactions" label="pengeluaran" />
    </div>

    <x-ui.modal name="showModal" title="Catat Pengeluaran Kas" subtitle="Jurnal debit beban dan kredit kas dibuat otomatis.">
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.select wire:model.live="cash_account_id" name="cash_account_id" label="Sumber Dana" :options="$cashAccounts" placeholder="-- Pilih Kas/Bank --" required />
            <x-ui.select wire:model.live="expense_account_id" name="expense_account_id" label="Akun Beban" :options="$expenseAccounts" placeholder="-- Pilih Akun Beban --" hint="Tambahkan akun beban baru di menu Akun bila belum tersedia." required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="amount" name="amount" label="Nominal" prefix="Rp" placeholder="0" required />
                <x-ui.input wire:model="transacted_at" name="transacted_at" type="date" label="Tanggal" required />
            </div>

            <x-ui.input wire:model="description" name="description" label="Keterangan" placeholder="Contoh: Pembelian ATK bulan November" required />
            <x-ui.input wire:model="reference_number" name="reference_number" label="Nomor Referensi" placeholder="Nomor nota/kuitansi (opsional)" />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pengeluaran</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showVoidModal" title="Batalkan Pengeluaran" size="sm" subtitle="Baris kas dihapus dari perhitungan dan jurnal ditandai void.">
        <form wire:submit="void" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="voidReason" name="voidReason" label="Alasan Pembatalan" placeholder="Contoh: Salah input nominal" rows="3" required />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showVoidModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" type="submit" loading="void">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Batalkan Pengeluaran</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
