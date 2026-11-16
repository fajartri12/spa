@php use App\Support\Formatter; @endphp

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Data Tagihan']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Data Tagihan PPDB</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">
                Kelola biaya pendaftaran dan administrasi calon siswa baru.
            </p>
        </div>

        @can('admissions.create')
            <x-ui.button variant="primary" icon="plus" wire:click="create">
                Terbitkan Tagihan
            </x-ui.button>
        @endcan
    </div>

    {{-- Ringkasan --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.stat-card
            label="Total Tagihan Terbit"
            :value="number_format($totalBills, 0, ',', '.') . ' tagihan'"
            icon="tags"
            tone="primary"
        />
        <x-ui.stat-card
            label="Belum Dibayar"
            :value="Formatter::rupiah($totalUnpaidAmount)"
            icon="clock"
            tone="warning"
        />
        <x-ui.stat-card
            label="Sudah Lunas"
            :value="Formatter::rupiah($totalPaidAmount)"
            icon="badge-check"
            tone="success"
        />
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Tagihan"
                    placeholder="Nomor invoice, judul, atau nama calon siswa..."
                    icon="search"
                />
            </div>

            <x-ui.select wire:model.live="periodFilter" name="periodFilter" label="Periode" :options="$periodOptions" placeholder="Semua Periode" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status Tagihan" placeholder="Semua Status" :options="['unpaid' => 'Belum Lunas', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan']" />

            @if($search !== '' || $periodFilter !== '' || $statusFilter !== '')
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        <span>Reset filter</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table
            class="min-w-[880px]"
            :empty="$bills->isEmpty()"
            empty-title="Belum Ada Tagihan PPDB"
            empty-description="Terbitkan tagihan baru untuk biaya pendaftaran atau seragam calon siswa."
        >
            <x-slot:head>
                <tr>
                    <th>No. Tagihan</th>
                    <th>Judul Tagihan</th>
                    <th>Calon Siswa</th>
                    <th class="text-right">Nominal</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($bills as $bill)
                @php
                    $variant = match ($bill->status) {
                        'paid' => 'success',
                        'cancelled' => 'neutral',
                        default => 'warning',
                    };
                    $statusLabel = match ($bill->status) {
                        'paid' => 'Lunas',
                        'cancelled' => 'Batal',
                        default => 'Belum Lunas',
                    };
                @endphp

                <tr wire:key="bill-{{ $bill->id }}">
                    <td class="whitespace-nowrap font-medium tabular text-[#111827]">{{ $bill->bill_number }}</td>
                    <td class="min-w-[170px]">
                        <p class="font-medium text-[#111827]">{{ $bill->title }}</p>
                        @if($bill->applicant?->admissionPeriod)
                            <p class="text-[12px] text-[#8994a3]">{{ $bill->applicant->admissionPeriod->name }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('admissions.show', $bill->applicant) }}" class="font-medium text-[#2872c8] hover:underline" wire:navigate>
                            {{ $bill->applicant->name }}
                        </a>
                        <p class="tabular text-[12px] text-[#8994a3]">{{ $bill->applicant->registration_number }}</p>
                    </td>
                    <td class="whitespace-nowrap text-right font-medium tabular text-[#111827]">
                        {{ Formatter::rupiah((float) $bill->amount) }}
                    </td>
                    <td class="whitespace-nowrap tabular text-[#475569]">
                        {{ Formatter::date($bill->due_date, 'd M Y') }}
                    </td>
                    <td class="whitespace-nowrap">
                        <x-ui.badge :variant="$variant" dot>{{ $statusLabel }}</x-ui.badge>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @can('admissions.update')
                            <div class="inline-flex items-center gap-0.5 rounded-[5px] border border-[#e1e6ed] bg-white p-0.5">
                                @if($bill->status !== 'paid')
                                    <button
                                        type="button"
                                        wire:click="updateStatus({{ $bill->id }}, 'paid')"
                                        wire:confirm="Tandai tagihan {{ $bill->bill_number }} sudah lunas?"
                                        class="btn-icon !w-7 !h-7"
                                        title="Tandai Lunas"
                                        aria-label="Tandai lunas {{ $bill->bill_number }}"
                                    >
                                        <i data-lucide="circle-check" class="w-4 h-4 text-[#438a21]"></i>
                                    </button>
                                @endif
                                @if($bill->status !== 'cancelled')
                                    <button
                                        type="button"
                                        wire:click="updateStatus({{ $bill->id }}, 'cancelled')"
                                        wire:confirm="Batalkan tagihan {{ $bill->bill_number }}?"
                                        class="btn-icon !w-7 !h-7"
                                        title="Batalkan Tagihan"
                                        aria-label="Batalkan tagihan {{ $bill->bill_number }}"
                                    >
                                        <i data-lucide="x" class="w-4 h-4 text-[#c0392b]"></i>
                                    </button>
                                @endif
                            </div>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$bills" label="tagihan" />
    </div>

    {{-- Modal Tambah Tagihan --}}
    <x-ui.modal
        name="showModal"
        title="Terbitkan Tagihan PPDB"
        subtitle="Tetapkan tagihan biaya pendaftaran atau seragam calon siswa."
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.select
                wire:model="applicant_id"
                name="applicant_id"
                label="Calon Siswa"
                :options="$applicantOptions"
                placeholder="Pilih calon siswa"
                required
            />

            <x-ui.input
                wire:model="bill_title"
                name="bill_title"
                label="Judul Tagihan"
                placeholder="Biaya Formulir &amp; Pendaftaran PPDB"
                required
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="bill_amount"
                    name="bill_amount"
                    type="number"
                    step="1000"
                    label="Nominal (Rp)"
                    placeholder="150000"
                    required
                />
                <x-ui.input
                    wire:model="bill_due_date"
                    name="bill_due_date"
                    type="date"
                    label="Jatuh Tempo"
                    required
                />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-2">
                <button type="button" @click="$dispatch('close-modal', 'showModal')" class="btn btn-secondary">Batal</button>
                <x-ui.button type="submit" variant="primary" icon="save" loading="save">
                    Terbitkan
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>