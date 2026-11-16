@php use App\Support\Formatter; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Keuangan'], ['label' => 'Honorarium']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Honorarium &amp; Penggajian</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Susun slip honorarium guru, karyawan, dan insentif komite. Satu periode menghasilkan satu transaksi kas keluar beserta jurnalnya.
            </p>
        </div>
        @can('payrolls.create')
            <x-ui.button variant="primary" wire:click="openCreate">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Periode Baru</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Judul atau periode (YYYY-MM)..." icon="search" />
            </div>
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statusOptions" />
            <div class="flex items-end">
                <div class="w-full rounded-[6px] border border-[#e1e6ed] px-3 py-2">
                    <div class="text-[11px] uppercase tracking-wide text-[#8994a3]">Total Draf</div>
                    <div class="text-[15px] font-semibold tabular text-[#b9770e]">{{ Formatter::rupiah($draftTotal) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$periods->isEmpty()" empty-title="Belum Ada Periode Honorarium" empty-description="Buat periode baru untuk menyusun slip honorarium guru dan karyawan.">
            <x-slot:head>
                <tr>
                    <th class="w-28">Periode</th>
                    <th>Judul</th>
                    <th class="w-40">Unit</th>
                    <th class="w-24 text-center">Penerima</th>
                    <th class="w-40 text-right">Total</th>
                    <th class="w-28">Status</th>
                    <th class="w-32">Disetujui</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($periods as $period)
                <tr wire:key="payroll-{{ $period->id }}">
                    <td class="tabular text-[#475569]">{{ $period->period }}</td>
                    <td>
                        <a href="{{ route('payrolls.show', $period) }}" class="font-medium text-[#2872c8] hover:underline">{{ $period->title }}</a>
                        @if($period->description)
                            <div class="text-[12px] text-[#8994a3]">{{ $period->description }}</div>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $period->unit?->name ?? 'Semua Unit' }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $period->employee_count }}</td>
                    <td class="text-right tabular font-medium text-[#111827]">{{ Formatter::rupiah((float) $period->total_amount) }}</td>
                    <td>
                        <x-ui.badge :variant="$period->status->badgeVariant()">{{ $period->status->label() }}</x-ui.badge>
                    </td>
                    <td class="text-[#64748b]">
                        @if($period->approved_at)
                            {{ $period->approved_at->format('d/m/Y') }}
                            <div class="text-[12px] text-[#8994a3]">{{ $period->approver?->name }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('payrolls.show', $period) }}" class="btn btn-ghost btn-sm" title="Buka slip">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            <span class="sr-only">Buka</span>
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$periods" label="periode honorarium" />
    </div>

    <x-ui.modal name="showModal" title="Periode Honorarium Baru" subtitle="Periode menjadi wadah slip. Uang keluar baru tercatat setelah periode disetujui.">
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input wire:model="period" name="period" type="month" label="Periode" required />
                <x-ui.input wire:model="title" name="title" label="Judul" placeholder="Honorarium November 2026" required />
            </div>
            <x-ui.textarea wire:model="description" name="description" label="Keterangan" rows="2" placeholder="Opsional" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.select wire:model="unitId" name="unitId" label="Unit" placeholder="Semua Unit" :options="$units" />
                <x-ui.select wire:model="academicYearId" name="academicYearId" label="Tahun Ajaran" placeholder="Tidak ditentukan" :options="$academicYears" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.select wire:model="cashAccountId" name="cashAccountId" label="Akun Kas/Bank Sumber" :options="$cashAccounts" required />
                <x-ui.select wire:model="expenseAccountId" name="expenseAccountId" label="Akun Beban Honorarium" :options="$expenseAccounts" required />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="save">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Buat Periode</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
