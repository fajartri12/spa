@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tagihan', 'url' => route('bills.index')], ['label' => 'Potongan Tagihan']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Potongan Tagihan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Atur beasiswa dan keringanan yang otomatis diterapkan saat tagihan siswa dibuat.
            </p>
        </div>

        @can('discounts.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Potongan</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Siswa" placeholder="Nama atau NIS..." icon="search" />
            </div>
            <x-ui.select wire:model.live="typeFilter" name="typeFilter" label="Tipe" placeholder="Semua Tipe" :options="$typeOptions" />
            <x-ui.select wire:model.live="activeFilter" name="activeFilter" label="Status" placeholder="Semua Status" :options="['1' => 'Aktif', '0' => 'Nonaktif']" />
            @if($hasFilter)
                <div class="flex items-end lg:col-span-4">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Reset Filter</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$discounts->isEmpty()" empty-title="Belum Ada Potongan" empty-description="Tambahkan potongan agar tagihan siswa tertentu otomatis lebih ringan.">
            <x-slot:head>
                <tr>
                    <th>Siswa</th>
                    <th>Kategori</th>
                    <th class="w-32">Tipe</th>
                    <th class="w-36 text-right">Nilai</th>
                    <th>Keterangan</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($discounts as $discount)
                <tr wire:key="discount-{{ $discount->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $discount->student?->name ?? '-' }}</div>
                        <div class="text-[12px] text-[#8994a3]">
                            {{ $discount->student?->nis }} · {{ $discount->student?->unit?->name ?? '-' }}
                        </div>
                    </td>
                    <td class="text-[#475569]">{{ $discount->paymentCategory?->name ?? 'Semua kategori' }}</td>
                    <td>
                        <x-ui.badge variant="{{ $discount->type === \App\Services\DiscountService::TYPE_PERCENT ? 'info' : 'neutral' }}">
                            {{ $typeOptions[$discount->type] ?? $discount->type }}
                        </x-ui.badge>
                    </td>
                    <td class="text-right tabular font-medium text-[#111827]">
                        @if($discount->type === \App\Services\DiscountService::TYPE_PERCENT)
                            {{ rtrim(rtrim(number_format((float) $discount->value, 2, ',', '.'), '0'), ',') }}%
                        @else
                            {{ \App\Support\Formatter::rupiah((float) $discount->value) }}
                        @endif
                    </td>
                    <td class="text-[#64748b]">{{ $discount->reason ?: '-' }}</td>
                    <td>
                        @if($discount->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('discounts.update')
                                <button type="button" wire:click="toggleActive({{ $discount->id }})" class="btn-icon"
                                    title="{{ $discount->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    aria-label="{{ $discount->is_active ? 'Nonaktifkan potongan' : 'Aktifkan potongan' }}">
                                    <i data-lucide="{{ $discount->is_active ? 'toggle-right' : 'toggle-left' }}" class="w-4 h-4"></i>
                                </button>
                                <button type="button" wire:click="edit({{ $discount->id }})" class="btn-icon"
                                    title="Ubah potongan" aria-label="Ubah potongan {{ $discount->student?->name }}">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan
                            @can('discounts.delete')
                                <button type="button" wire:click="confirmDelete({{ $discount->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus potongan" aria-label="Hapus potongan {{ $discount->student?->name }}">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$discounts" label="potongan" />
    </div>

    <x-ui.modal name="showModal" :title="$editingId ? 'Ubah Potongan Tagihan' : 'Tambah Potongan Tagihan'" subtitle="Potongan otomatis berlaku saat tagihan dibuat.">
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.select wire:model="student_id" name="student_id" label="Siswa" :options="$students" placeholder="-- Pilih Siswa --" required />
            <x-ui.select wire:model="payment_category_id" name="payment_category_id" label="Kategori Pembayaran" :options="$categories" placeholder="-- Semua Kategori --" hint="Kosongkan agar potongan berlaku untuk seluruh kategori." />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model.live="type" name="type" label="Tipe Potongan" :options="$typeOptions" required />
                <x-ui.input wire:model="value" name="value" label="Nilai" :prefix="$type === \App\Services\DiscountService::TYPE_FIXED ? 'Rp' : null" :hint="$type === \App\Services\DiscountService::TYPE_PERCENT ? 'Persen dari nominal tagihan (0-100).' : 'Nominal rupiah yang dipotong.'" required />
            </div>

            <x-ui.input wire:model="reason" name="reason" label="Keterangan" placeholder="Contoh: Beasiswa prestasi, anak pegawai" />

            <label for="is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input id="is_active" type="checkbox" wire:model="is_active" class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]">
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Potongan aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Potongan nonaktif tidak diterapkan pada tagihan baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Potongan' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showDeleteModal" title="Hapus Potongan Tagihan" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Potongan akan dihapus dan tidak lagi diterapkan pada tagihan baru. Tagihan yang sudah terbuat tidak berubah.
                Lanjutkan?
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-5">
            <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showDeleteModal', false)">Batal</x-ui.button>
            <x-ui.button variant="danger" size="sm" type="button" wire:click="delete" loading="delete">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Ya, Hapus</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>
