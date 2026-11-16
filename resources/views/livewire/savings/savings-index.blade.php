@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tabungan']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Tabungan Siswa</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Kelola saldo tabungan siswa secara aman dan terverifikasi.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('savings.verify')
                <a href="{{ route('savings.verification') }}" class="btn btn-secondary">
                    <i data-lucide="badge-check" class="w-4 h-4"></i>
                    <span>Verifikasi Penarikan</span>
                </a>
            @endcan
            @can('savings.export')
                <button type="button" wire:click="export" class="btn btn-secondary">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Ekspor CSV</span>
                </button>
            @endcan
            @can('savings.create')
                <button type="button" wire:click="openTransaction" class="btn btn-primary">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    <span>Setor Tarik</span>
                </button>
                <a href="{{ route('savings.transaction') }}" class="btn btn-primary">
                    <i data-lucide="circle-dollar-sign" class="w-4 h-4"></i>
                    <span>Transaksi</span>
                </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            label="Total Saldo Aktif"
            :value="\App\Support\Formatter::rupiah($summary['balance'])"
            icon="coins"
            tone="info"
        />
        <x-ui.stat-card
            label="Total Setoran"
            :value="\App\Support\Formatter::rupiah($summary['deposits'])"
            icon="coins"
            tone="success"
        />
        <x-ui.stat-card
            label="Total Penarikan"
            :value="\App\Support\Formatter::rupiah($summary['withdrawals'])"
            :hint="$summary['pendingWithdrawals'] > 0 ? \App\Support\Formatter::rupiah($summary['pendingWithdrawals']) . ' menunggu verifikasi' : null"
            icon="coins"
            tone="danger"
        />
        <x-ui.stat-card
            label="Volume Transaksi"
            :value="\App\Support\Formatter::rupiah($summary['volume'])"
            :hint="number_format($summary['transactions'], 0, ',', '.') . ' transaksi disetujui'"
            icon="coins"
            tone="primary-dark"
        />
    </div>

    <div class="card px-4 py-3">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_240px_auto] md:items-end">
            <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Siswa" placeholder="Nama, NIS, atau NISN..." icon="search" />
            <x-ui.select
                wire:model.live="classFilter"
                name="classFilter"
                label="Filter Kelas"
                placeholder="Semua Kelas"
                :options="$classOptions"
            />
            <button type="button" wire:click="resetFilters" class="btn btn-secondary whitespace-nowrap">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                <span>Reset</span>
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$students->isEmpty()" empty-title="Belum Ada Data Siswa" empty-description="Tambahkan siswa terlebih dahulu sebelum membuat tabungan.">
            <x-slot:head>
                <tr>
                    <th class="w-32 p-2 text-xs">NIS / NISN</th>
                    <th class="p-2 text-xs">Nama Siswa</th>
                    <th class="w-44 p-2 text-xs">Unit</th>
                    <th class="w-32 p-2 text-xs">Kelas</th>
                    <th class="w-32 p-2 text-xs">Jurusan</th>
                    <th class="w-28 p-2 text-xs">Saldo Tabungan</th>
                    <th class="w-24 p-2 text-xs text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($students as $student)
                <tr wire:key="student-{{ $student->id }}">
                    <td class="p-2 text-xs">
                        <div class="tabular font-medium text-[#111827]">{{ $student->nis }}</div>
                        @if($student->nisn)
                            <div class="tabular text-[11px] text-[#8994a3] mt-0.5">{{ $student->nisn }}</div>
                        @endif
                    </td>
                    <td class="p-2 text-xs">
                        <div class="font-medium text-[#111827]">{{ $student->name }}</div>
                    </td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->unit?->name ?? '-' }}</td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->schoolClass?->name ?? '-' }}</td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->major ?? '-' }}</td>
                    <td class="p-2 text-xs text-right tabular">
                        {{ \App\Support\Formatter::rupiah((float) $student->savingsAccount?->balance ?? 0) }}
                    </td>
                    <td class="p-2 text-xs text-right">
                        @can('savings.create')
                            <button type="button" wire:click="openTransaction({{ $student->id }})" class="btn-icon text-[#2872c8] hover:text-[#1d5690]" aria-label="Tambah transaksi {{ $student->name }}">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$students" label="siswa" />
    </div>

    {{-- Modal Transaksi Tabungan --}}
    <x-ui.modal
        name="showTransactionModal"
        title="Transaksi Tabungan Siswa"
        subtitle="Catat setoran atau penarikan saldo tabungan secara instan."
        size="md"
    >
        <form wire:submit="saveTransaction" class="space-y-4">
            <x-ui.select
                wire:model.live="student_id"
                name="student_id"
                label="Pilih Siswa"
                placeholder="-- Pilih Siswa --"
                :options="$studentOptions"
                required
            />

            @if($selectedStudent)
                <div class="rounded-lg border border-[#e2e8f0] bg-[#f8fafc] p-3 text-xs space-y-1">
                    <div class="flex justify-between items-center text-[#64748b]">
                        <span>NIS / Kelas:</span>
                        <span class="font-medium text-[#1e293b]">{{ $selectedStudent->nis }} - {{ $selectedStudent->schoolClass?->name ?? 'Tanpa Kelas' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-[#64748b]">
                        <span>Saldo Tabungan Saat Ini:</span>
                        <span class="font-semibold text-[#0f766e] text-sm tabular">
                            {{ \App\Support\Formatter::rupiah((float) ($selectedStudent->savingsAccount?->balance ?? 0)) }}
                        </span>
                    </div>
                    @if($availableBalance < (float) ($selectedStudent->savingsAccount?->balance ?? 0))
                        <div class="flex justify-between items-center text-[#64748b]">
                            <span>Dapat Ditarik:</span>
                            <span class="font-medium text-[#b45309] tabular">{{ \App\Support\Formatter::rupiah($availableBalance) }}</span>
                        </div>
                        <p class="text-[11px] text-[#8994a3]">{{ $pendingWithdrawals }} pengajuan penarikan masih menunggu verifikasi.</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.select
                    wire:model.live="type"
                    name="type"
                    label="Jenis Transaksi"
                    :options="['deposit' => 'Setoran (Deposit)', 'withdrawal' => 'Penarikan (Withdrawal)']"
                    required
                />

                <x-ui.input
                    wire:model.live="amount"
                    name="amount"
                    label="Nominal"
                    type="number"
                    step="1"
                    min="1"
                    placeholder="Contoh: 50000"
                    prefix="Rp"
                    required
                />
            </div>

            <x-ui.textarea
                wire:model.live="description"
                name="description"
                label="Keterangan"
                placeholder="Catatan transaksi tabungan (opsional)..."
                rows="2"
            />

            @if($type === 'withdrawal')
                <p class="text-[12px] text-[#b45309] bg-[#fffbeb] border border-[#fde68a] rounded-lg px-3 py-2">
                    Pengajuan penarikan tidak langsung memotong saldo. Bendahara harus menyetujuinya di menu Verifikasi Penarikan.
                </p>
            @endif

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e2e8f0]">
                <button type="button" @click="open = false" class="btn btn-secondary">
                    Batal
                </button>
                <x-ui.button type="submit" variant="primary" loading wire:target="saveTransaction">
                    Simpan Transaksi
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
