@php use App\Support\Formatter; use App\Enums\ClearanceStatus; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Bebas Tanggungan & SKL']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Bebas Tanggungan &amp; SKL</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Periksa sisa tagihan siswa sebelum menerbitkan Surat Keterangan Lulus. SKL diblokir selama masih ada tanggungan.
            </p>
        </div>
        <a href="{{ route('alumni.index') }}" class="btn btn-secondary">
            <i data-lucide="graduation-cap" class="w-4 h-4"></i>
            <span>Data Alumni</span>
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.stat-card label="Total Siswa" :value="number_format($summary['total'], 0, ',', '.')" icon="users" tone="primary" />
        <x-ui.stat-card label="Bebas Tanggungan" :value="number_format($summary['cleared'], 0, ',', '.')" icon="circle-check" tone="success" />
        <x-ui.stat-card label="Dikecualikan" :value="number_format($summary['overridden'], 0, ',', '.')" icon="shield-off" tone="info" />
        <x-ui.stat-card label="Belum Bebas" :value="number_format($summary['pending'], 0, ',', '.')" icon="triangle-alert" tone="warning" />
        <x-ui.stat-card label="SKL Terbit" :value="number_format($summary['skl_issued'], 0, ',', '.')" icon="file-badge" tone="purple" />
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Nama, NIS, atau NISN..." icon="search" />
            </div>
            <x-ui.select wire:model.live="unitFilter" name="unitFilter" label="Unit" placeholder="Semua Unit" :options="$units" />
            <x-ui.select wire:model.live="classFilter" name="classFilter" label="Kelas" placeholder="Semua Kelas" :options="$classes" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statusOptions" />
        </div>
        <div class="mt-3">
            <button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Reset Filter</span>
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$students->isEmpty()" empty-title="Tidak Ada Siswa" empty-description="Ubah filter atau kata kunci pencarian.">
            <x-slot:head>
                <tr>
                    <th>Nama</th>
                    <th class="w-40">Unit / Kelas</th>
                    <th class="w-40 text-right">Sisa Tagihan</th>
                    <th class="w-40">Status</th>
                    <th class="w-32">SKL</th>
                    <th class="w-56 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($students as $student)
                @php
                    $clearance = $student->clearance;
                    $status = $clearance?->status;
                @endphp
                <tr wire:key="clearance-{{ $student->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $student->name }}</div>
                        <div class="text-[12px] text-[#8994a3]">{{ $student->nis }}</div>
                    </td>
                    <td class="text-[#475569]">
                        {{ $student->unit?->name ?? '-' }}
                        <div class="text-[12px] text-[#8994a3]">{{ $student->schoolClass?->name ?? '-' }}</div>
                    </td>
                    <td class="text-right tabular {{ $clearance && $clearance->hasOutstanding() ? 'font-medium text-[#c0392b]' : 'text-[#475569]' }}">
                        {{ $clearance === null ? '-' : Formatter::rupiah((float) $clearance->remaining_amount) }}
                        @if($clearance && $clearance->outstanding_bill_count > 0)
                            <div class="text-[12px] text-[#8994a3]">{{ $clearance->outstanding_bill_count }} tagihan</div>
                        @endif
                    </td>
                    <td>
                        @if($status === null)
                            <x-ui.badge variant="neutral">Belum Diperiksa</x-ui.badge>
                        @else
                            <x-ui.badge :variant="$status->badgeVariant()">{{ $status->label() }}</x-ui.badge>
                            @if($clearance->checked_at)
                                <div class="text-[12px] text-[#8994a3] mt-1">{{ $clearance->checked_at->format('d/m/Y H:i') }}</div>
                            @endif
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">
                        {{ $clearance?->skl_number ?? '-' }}
                        @if($clearance?->skl_issued_at)
                            <div class="text-[12px] text-[#8994a3]">{{ $clearance->skl_issued_at->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex flex-wrap items-center justify-end gap-1">
                            @can('clearances.check')
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="check({{ $student->id }})">
                                    <i data-lucide="search-check" class="w-4 h-4"></i>
                                    <span>Periksa</span>
                                </button>
                            @endcan
                            @if($clearance !== null)
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="openDetail({{ $student->id }})" title="Lihat rincian">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    <span class="sr-only">Rincian</span>
                                </button>
                            @endif
                            @can('clearances.override')
                                @if($status === ClearanceStatus::Pending)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="startOverride({{ $student->id }})" title="Beri pengecualian">
                                        <i data-lucide="shield-off" class="w-4 h-4"></i>
                                        <span class="sr-only">Pengecualian</span>
                                    </button>
                                @endif
                            @endcan
                            @can('clearances.issue')
                                @if($status?->allowsIssuance() && $clearance?->skl_number === null)
                                    <button type="button" class="btn btn-success btn-sm" wire:click="issueSkl({{ $student->id }})">
                                        <i data-lucide="file-badge" class="w-4 h-4"></i>
                                        <span>Terbitkan SKL</span>
                                    </button>
                                @endif
                                @if($clearance?->skl_number !== null)
                                    <a href="{{ route('clearances.skl', $student) }}" class="btn btn-primary btn-sm">
                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                        <span>Cetak SKL</span>
                                    </a>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$students" label="siswa" />
    </div>

    <x-ui.modal name="showDetailModal" title="Rincian Bebas Tanggungan" subtitle="Snapshot saat pemeriksaan terakhir." size="lg">
        @if($detail === null)
            <p class="text-[13px] text-[#8994a3]">Data tidak ditemukan.</p>
        @else
            @php $clearance = $detail->clearance; @endphp
            <div class="space-y-4">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-[#64748b]">Nama</dt><dd class="mt-1 font-medium text-[#111827]">{{ $detail->name }}</dd></div>
                    <div><dt class="text-[#64748b]">NIS / NISN</dt><dd class="mt-1 text-[#111827]">{{ $detail->nis }} / {{ $detail->nisn ?? '-' }}</dd></div>
                    <div><dt class="text-[#64748b]">Status</dt><dd class="mt-1">{{ $clearance ? $clearance->status->label() : 'Belum Diperiksa' }}</dd></div>
                    <div><dt class="text-[#64748b]">Sisa Tagihan</dt><dd class="mt-1 tabular font-medium text-[#111827]">{{ $clearance ? Formatter::rupiah((float) $clearance->remaining_amount) : '-' }}</dd></div>
                    <div><dt class="text-[#64748b]">Diperiksa</dt><dd class="mt-1 text-[#111827]">{{ $clearance?->checked_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                    <div><dt class="text-[#64748b]">Nomor SKL</dt><dd class="mt-1 tabular text-[#111827]">{{ $clearance?->skl_number ?? '-' }}</dd></div>
                </dl>

                @if($clearance?->override_reason)
                    <x-ui.alert type="warning" title="Pengecualian">{{ $clearance->override_reason }}
                        <span class="block text-[12px] mt-1">Oleh {{ $clearance->overrider?->name ?? '-' }} pada {{ $clearance->overridden_at?->format('d/m/Y H:i') }}</span>
                    </x-ui.alert>
                @endif

                @if(! empty($clearance?->outstanding_bills))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b text-left text-[#64748b]"><th class="pb-2">No. Tagihan</th><th class="pb-2">Tagihan</th><th class="pb-2">Jatuh Tempo</th><th class="pb-2 text-right">Sisa</th></tr></thead>
                            <tbody>
                                @foreach($clearance->outstanding_bills as $bill)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 tabular">{{ $bill['bill_number'] ?? '-' }}</td>
                                        <td class="py-2">{{ $bill['name'] ?? '-' }}</td>
                                        <td class="py-2">{{ $bill['due_date'] ?? '-' }}</td>
                                        <td class="py-2 text-right tabular">{{ Formatter::rupiah((float) ($bill['remaining_amount'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-[13px] text-[#438a21]">Tidak ada tagihan tertunggak pada pemeriksaan terakhir.</p>
                @endif
            </div>
        @endif
    </x-ui.modal>

    <x-ui.modal name="showOverrideModal" title="Pengecualian Bebas Tanggungan" subtitle="Pengecualian mengizinkan SKL terbit walau masih ada tanggungan. Alasan dicatat untuk audit.">
        <form wire:submit="override" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="overrideReason" name="overrideReason" label="Alasan Pengecualian" rows="4" required placeholder="Contoh: Wali siswa menandatangani surat pernyataan pelunasan bertahap." />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showOverrideModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="override">
                    <i data-lucide="shield-off" class="w-4 h-4"></i>
                    <span>Beri Pengecualian</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>