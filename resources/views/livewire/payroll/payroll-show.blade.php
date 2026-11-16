@php use App\Support\Formatter; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Keuangan'], ['label' => 'Honorarium', 'url' => route('payrolls.index')], ['label' => $payrollPeriod->title]]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">{{ $payrollPeriod->title }}</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Periode {{ $payrollPeriod->period }} &middot; {{ $payrollPeriod->unit?->name ?? 'Semua Unit' }}
                <x-ui.badge :variant="$payrollPeriod->status->badgeVariant()" class="ml-2">{{ $payrollPeriod->status->label() }}</x-ui.badge>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($isDraft)
                @can('payrolls.update')
                    <x-ui.button variant="primary" wire:click="openCreate">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Tambah Slip</span>
                    </x-ui.button>
                @endcan
                @can('payrolls.approve')
                    <x-ui.button variant="success" wire:click="confirmApprove">
                        <i data-lucide="check-check" class="w-4 h-4"></i>
                        <span>Setujui Periode</span>
                    </x-ui.button>
                @endcan
            @endif
            @if($payrollPeriod->status === \App\Enums\PayrollStatus::Approved)
                @can('payrolls.cancel')
                    <x-ui.button variant="danger" wire:click="confirmCancel">
                        <i data-lucide="undo-2" class="w-4 h-4"></i>
                        <span>Batalkan</span>
                    </x-ui.button>
                @endcan
            @endif
            <a href="{{ route('payrolls.slips', $payrollPeriod) }}" class="btn btn-secondary" target="_blank" rel="noopener">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Cetak Slip</span>
            </a>
            <a href="{{ route('payrolls.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    @if($payrollPeriod->status === \App\Enums\PayrollStatus::Cancelled)
        <x-ui.alert type="danger" title="Periode dibatalkan">
            {{ $payrollPeriod->cancellation_reason }}
            @if($payrollPeriod->cancelled_at)
                <span class="block text-[12px] mt-1">Dibatalkan {{ $payrollPeriod->cancelled_at->format('d/m/Y H:i') }}</span>
            @endif
        </x-ui.alert>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Jumlah Penerima" :value="$items->count() . ' orang'" icon="users" tone="primary" />
        <x-ui.stat-card label="Total Bruto" :value="Formatter::rupiah($totalGross)" icon="banknote" tone="info" />
        <x-ui.stat-card label="Total Potongan" :value="Formatter::rupiah($totalDeduction)" icon="minus-circle" tone="warning" />
        <x-ui.stat-card label="Total Dibayarkan" :value="Formatter::rupiah($totalNet)" icon="wallet" tone="success" :hint="$totalInWords" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Akun &amp; Jurnal" subtitle="Satu periode menghasilkan satu transaksi kas keluar dan satu jurnal batch.">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-[#64748b]">Akun Sumber</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payrollPeriod->cashAccount?->code }} - {{ $payrollPeriod->cashAccount?->name }}</dd></div>
                <div><dt class="text-[#64748b]">Akun Beban</dt><dd class="mt-1 font-medium text-[#111827]">{{ $payrollPeriod->expenseAccount?->code }} - {{ $payrollPeriod->expenseAccount?->name }}</dd></div>
                <div><dt class="text-[#64748b]">Tahun Ajaran</dt><dd class="mt-1 text-[#111827]">{{ $payrollPeriod->academicYear?->name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Dibuat Oleh</dt><dd class="mt-1 text-[#111827]">{{ $payrollPeriod->creator?->name ?? '-' }}</dd></div>
                @if($payrollPeriod->journal)
                    <div><dt class="text-[#64748b]">Nomor Jurnal</dt><dd class="mt-1 tabular text-[#111827]">{{ $payrollPeriod->journal->journal_number }}</dd></div>
                    <div><dt class="text-[#64748b]">Kas Keluar</dt><dd class="mt-1 tabular text-[#111827]">{{ $payrollPeriod->cashTransaction?->transaction_number ?? '-' }}</dd></div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card title="Rekap per Jabatan">
            @if($byPosition === [])
                <p class="text-[13px] text-[#8994a3]">Belum ada slip.</p>
            @else
                <dl class="space-y-2 text-sm">
                    @foreach($byPosition as $position => $total)
                        <div class="flex items-center justify-between">
                            <dt class="text-[#475569]">{{ $position }}</dt>
                            <dd class="tabular font-medium text-[#111827]">{{ Formatter::rupiah($total) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-ui.card>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$items->isEmpty()" empty-title="Belum Ada Slip" empty-description="Tambahkan slip honorarium untuk setiap penerima.">
            <x-slot:head>
                <tr>
                    <th>Penerima</th>
                    <th>Jabatan</th>
                    <th>Rekening</th>
                    <th class="text-right">Bruto</th>
                    <th class="text-right">Potongan</th>
                    <th class="text-right">Dibayarkan</th>
                    <th class="w-28 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($items as $item)
                <tr wire:key="payroll-item-{{ $item->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $item->recipient_name }}</div>
                        @if($item->notes)
                            <div class="text-[12px] text-[#8994a3]">{{ $item->notes }}</div>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $item->recipient_position ?: '-' }}</td>
                    <td class="text-[#475569]">
                        {{ $item->recipient_bank ?: '-' }}
                        <div class="text-[12px] tabular text-[#8994a3]">{{ $item->recipient_account }}</div>
                    </td>
                    <td class="text-right tabular">{{ Formatter::rupiah((float) $item->gross_amount) }}</td>
                    <td class="text-right tabular text-[#c0392b]">{{ Formatter::rupiah((float) $item->deduction_amount) }}</td>
                    <td class="text-right tabular font-medium text-[#111827]">{{ Formatter::rupiah((float) $item->net_amount) }}</td>
                    <td class="text-right">
                        @if($isDraft)
                            @can('payrolls.update')
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" class="btn-icon" wire:click="editItem({{ $item->id }})" title="Ubah slip">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </button>
                                    <button type="button" class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]" wire:click="removeItem({{ $item->id }})" wire:confirm="Hapus slip {{ $item->recipient_name }}?" title="Hapus slip">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            @endcan
                        @else
                            <span class="text-[12px] text-[#8994a3]">Terkunci</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>

    <x-ui.modal name="showItemModal" :title="$editingId === null ? 'Tambah Slip Honorarium' : 'Ubah Slip Honorarium'" subtitle="Nominal dibayarkan = bruto - potongan.">
        <form wire:submit="saveItem" class="space-y-4" novalidate>
            <x-ui.select wire:model.live="staffProfileId" name="staffProfileId" label="Pegawai Terdaftar" placeholder="Pilih pegawai (opsional)" :options="$staffOptions" hint="Kosongkan bila penerima bukan staf tetap (mis. insentif komite)." />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input wire:model="recipientName" name="recipientName" label="Nama Penerima" required />
                <x-ui.input wire:model="recipientPosition" name="recipientPosition" label="Jabatan" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input wire:model="recipientBank" name="recipientBank" label="Bank" />
                <x-ui.input wire:model="recipientAccount" name="recipientAccount" label="Nomor Rekening" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input wire:model="grossAmount" name="grossAmount" label="Nominal Bruto" prefix="Rp" required />
                <x-ui.input wire:model="deductionAmount" name="deductionAmount" label="Potongan" prefix="Rp" />
            </div>
            <x-ui.textarea wire:model="notes" name="notes" label="Keterangan" rows="2" placeholder="Opsional" />

            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showItemModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="saveItem">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Simpan Slip</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showApproveModal" title="Setujui Periode Honorarium" subtitle="Setelah disetujui, slip terkunci dan kas keluar beserta jurnal dibentuk.">
        <form wire:submit="approve" class="space-y-4" novalidate>
            <dl class="rounded-[6px] border border-[#e1e6ed] px-3 py-2 text-sm">
                <div class="flex justify-between"><dt class="text-[#64748b]">Penerima</dt><dd class="tabular">{{ $items->count() }} orang</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748b]">Total Dibayarkan</dt><dd class="tabular font-semibold">{{ Formatter::rupiah($totalNet) }}</dd></div>
            </dl>
            <x-ui.input wire:model="transactedAt" name="transactedAt" type="date" label="Tanggal Pembayaran" required />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showApproveModal', false)">Batal</x-ui.button>
                <x-ui.button variant="success" size="sm" type="submit" loading="approve">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    <span>Setujui &amp; Catat Kas</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showCancelModal" title="Batalkan Periode Honorarium" subtitle="Kas keluar dihapus dan jurnal ditandai void. Data slip tetap tersimpan untuk audit.">
        <form wire:submit="cancel" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="cancelReason" name="cancelReason" label="Alasan Pembatalan" rows="3" required />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showCancelModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" size="sm" type="submit" loading="cancel">
                    <i data-lucide="undo-2" class="w-4 h-4"></i>
                    <span>Batalkan Periode</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>