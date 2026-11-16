@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Tagihan']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Tagihan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Kelola tagihan siswa dan pantau status pembayarannya.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('bills.export', array_filter([
                    'status' => $statusFilter ?: null,
                    'unit' => $unitFilter ?: null,
                    'kelas' => $classFilter ?: null,
                    'tahun-ajaran' => $academicYearFilter ?: null,
                    'kategori' => $categoryFilter ?: null,
                    'periode' => $periodFilter ?: null,
                ])) }}" class="btn btn-secondary">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Ekspor CSV</span>
            </a>
            @can('bills.generate')
                <a href="{{ route('bills.generate') }}" class="btn btn-primary">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Generate Tagihan</span>
                </a>
            @endcan
        </div>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Tagihan" placeholder="No. tagihan, judul, siswa..." icon="search" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statuses" />
            <x-ui.select wire:model.live="academicYearFilter" name="academicYearFilter" label="Tahun Ajaran" placeholder="Semua Tahun Ajaran" :options="$academicYears" />
            <x-ui.select wire:model.live="categoryFilter" name="categoryFilter" label="Kategori" placeholder="Semua Kategori" :options="$categories" />
            <x-ui.select wire:model.live="unitFilter" name="unitFilter" label="Unit Pendidikan" placeholder="Semua Unit" :options="$units" />
            <x-ui.select wire:model.live="classFilter" name="classFilter" label="Kelas" placeholder="Semua Kelas" :options="$classes" />
            <x-ui.input wire:model.live="periodFilter" name="periodFilter" type="month" label="Periode" />
            @if($hasFilter)
                <div class="flex items-end">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full justify-center">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Reset Filter</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$bills->isEmpty()" empty-title="Belum Ada Tagihan" empty-description="Generate tagihan untuk siswa aktif terlebih dahulu.">
            <x-slot:head>
                <tr>
                    <th>No. Tagihan</th>
                    <th>Judul / Kategori</th>
                    <th>Periode</th>
                    <th>Tahun Ajaran</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-right">Nominal</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </x-slot:head>
            @foreach($bills as $bill)
                <tr wire:key="bill-{{ $bill->id }}">
                    <td class="tabular font-medium text-[#111827]">{{ $bill->bill_number }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $bill->title }}</div>
                        <div class="text-[12px] text-[#8994a3]">{{ $bill->paymentCategory?->name }}</div>
                    </td>
                    <td>{{ $bill->period ?? '-' }}</td>
                    <td>{{ $bill->academicYear?->name }}</td>
                    <td>{{ $bill->due_date?->format('d/m/Y') ?? '-' }}</td>
                    <td class="text-right tabular">{{ \App\Support\Formatter::rupiah((float) $bill->amount) }}</td>
                    <td>
                        <x-ui.badge :variant="match($bill->status->value ?? $bill->status) { 'paid' => 'success', 'partial' => 'info', 'cancelled' => 'danger', default => 'warning' }">
                            {{ $bill->status instanceof \App\Enums\BillStatus ? $bill->status->label() : ucfirst((string) $bill->status) }}
                        </x-ui.badge>
                    </td>
                    <td class="text-right">
                        @php($studentBill = $bill->studentBills->first())
                        @if($studentBill)
                            <a href="{{ route('bills.show', $studentBill) }}" class="btn btn-ghost btn-sm">
                                <i data-lucide="eye" class="h-4 w-4"></i><span>Detail</span>
                            </a>
                        @else
                            <span class="text-[12px] text-[#8994a3]">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$bills" label="tagihan" />
    </div>
</div>
