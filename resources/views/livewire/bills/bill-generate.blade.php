@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Tagihan', 'url' => route('bills.index')], ['label' => 'Generate Tagihan']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Generate Tagihan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Buat tagihan massal, bulanan beruntun, atau untuk satu siswa tertentu.</p>
        </div>
        <a href="{{ route('bills.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="w-4 h-4"></i><span>Daftar Tagihan</span>
        </a>
    </div>

    {{-- Pemilih mode --}}
    <div class="card px-4 py-3">
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Mode generate tagihan">
            @foreach([
                'massal' => ['label' => 'Massal', 'icon' => 'users', 'hint' => 'Semua siswa aktif pada unit/kelas terpilih.'],
                'bulanan' => ['label' => 'Bulanan Beruntun', 'icon' => 'calendar-range', 'hint' => 'Beberapa periode bulan sekaligus.'],
                'individu' => ['label' => 'Per Siswa', 'icon' => 'user', 'hint' => 'Satu siswa, nominal dan potongan bebas.'],
            ] as $value => $item)
                <button type="button"
                    wire:click="$set('mode', '{{ $value }}')"
                    @class([
                        'btn btn-sm',
                        'btn-primary' => $mode === $value,
                        'btn-secondary' => $mode !== $value,
                    ])
                    title="{{ $item['hint'] }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                    <span>{{ $item['label'] }}</span>
                </button>
            @endforeach
        </div>
        <p class="mt-2 text-[12px] text-[#8994a3]">
            {{ ['massal' => 'Satu kategori untuk seluruh siswa aktif pada unit/kelas terpilih.', 'bulanan' => 'Beberapa periode bulan sekaligus; periode yang sudah bertagihan dilewati.', 'individu' => 'Tagihan untuk satu siswa dengan nominal, potongan, dan judul khusus.'][$mode] ?? '' }}
        </p>
    </div>

    <form wire:submit="generate" novalidate>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-4">
                <div class="card p-5 space-y-4">
                    <h2 class="text-[15px] font-semibold text-gray-900">Kriteria Tagihan</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select wire:model.live="academic_year_id" name="academic_year_id" label="Tahun Ajaran" :options="$years" placeholder="-- Pilih Tahun Ajaran --" required />
                        <x-ui.select wire:model.live="payment_category_id" name="payment_category_id" label="Kategori Pembayaran" :options="$categories" placeholder="-- Pilih Kategori --" required />
                    </div>

                    @if($mode === 'individu')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select wire:model.live="unit_id" name="unit_id" label="Unit Pendidikan" :options="$units" placeholder="-- Semua Unit --" />
                            <x-ui.select wire:model.live="class_id" name="class_id" label="Kelas" :options="$classes" placeholder="-- Semua Kelas --" />
                        </div>
                        <x-ui.select wire:model.live="student_id" name="student_id" label="Siswa" :options="$students" placeholder="-- Pilih Siswa --" required />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input wire:model.live="amount" name="amount" label="Nominal Tagihan" prefix="Rp" required />
                            <x-ui.input wire:model.live="discount_amount" name="discount_amount" label="Potongan" prefix="Rp" hint="Kosongkan bila tanpa potongan." />
                        </div>
                        <x-ui.input wire:model.live="title" name="title" label="Judul Tagihan" placeholder="Mengikuti nama kategori bila dikosongkan." />
                        <x-ui.textarea wire:model.live="description" name="description" label="Keterangan" rows="2" />

                        {{-- Rincian item tagihan (opsional) --}}
                        <div class="rounded-lg border border-[#e5e9f0] p-3 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-[13px] font-medium text-[#374151]">Rincian Item Tagihan</p>
                                    <p class="text-[12px] text-[#8994a3]">Opsional. Bila diisi, total item menjadi nominal tagihan (maksimal {{ $maxBillItems }} item).</p>
                                </div>
                                <button type="button" wire:click="addItem" class="btn btn-secondary btn-sm">
                                    <i data-lucide="plus" class="w-4 h-4"></i><span>Tambah</span>
                                </button>
                            </div>

                            @if($items === [])
                                <p class="text-[12px] text-[#8994a3]">Belum ada item. Tagihan akan memakai nominal tunggal.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($items as $index => $item)
                                        <div class="flex items-start gap-2" wire:key="item-{{ $index }}">
                                            <div class="flex-1">
                                                <input type="text" wire:model="items.{{ $index }}.description"
                                                    class="form-input w-full" placeholder="Deskripsi item, misal: SPP Pokok"
                                                    aria-label="Deskripsi item {{ $index + 1 }}">
                                            </div>
                                            <div class="w-36">
                                                <input type="text" wire:model="items.{{ $index }}.amount" wire:blur="syncAmountFromItems"
                                                    class="form-input w-full text-right tabular" placeholder="0"
                                                    aria-label="Nominal item {{ $index + 1 }}">
                                            </div>
                                            <button type="button" wire:click="removeItem({{ $index }})"
                                                class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]" title="Hapus item"
                                                aria-label="Hapus item {{ $index + 1 }}">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" wire:click="syncAmountFromItems" class="btn btn-ghost btn-sm">
                                    <i data-lucide="calculator" class="w-4 h-4"></i><span>Samakan nominal dengan total item</span>
                                </button>
                            @endif
                            @error('items')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <x-ui.select wire:model.live="unit_id" name="unit_id" label="Unit Pendidikan" :options="$units" placeholder="-- Semua Unit --" hint="Kosongkan untuk seluruh unit pendidikan." />
                        <x-ui.select wire:model.live="class_id" name="class_id" label="Kelas" :options="$classes" placeholder="-- Semua Kelas --" hint="Kosongkan untuk seluruh kelas pada unit terpilih." />
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        @if($mode === 'bulanan')
                            <x-ui.input wire:model.live="period" name="period" type="month" label="Periode Awal" required />
                            <x-ui.input wire:model.live="period_end" name="period_end" type="month" label="Periode Akhir" required hint="Termasuk bulan ini." />
                        @else
                            <x-ui.input wire:model.live="period" name="period" type="month" label="Periode" required hint="Satu tagihan per siswa, kategori, dan periode." />
                        @endif
                        <x-ui.input wire:model.live="due_date" name="due_date" type="date" label="Jatuh Tempo" required />
                    </div>
                </div>

                <div class="card p-5">
                    <h2 class="text-[15px] font-semibold text-gray-900 mb-3">Pratinjau</h2>
                    <dl class="grid gap-3 sm:grid-cols-3 text-[13px]">
                        <div class="rounded-lg bg-[#f8fafc] p-3">
                            <dt class="text-[#64748b]">{{ $mode === 'individu' ? 'Siswa Terpilih' : 'Siswa Aktif' }}</dt>
                            <dd class="mt-1 tabular text-[16px] font-semibold text-[#111827]">{{ number_format($previewStudentCount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-lg bg-[#f8fafc] p-3">
                            <dt class="text-[#64748b]">Nominal / Siswa</dt>
                            <dd class="mt-1 tabular text-[16px] font-semibold text-[#111827]">
                                {{ \App\Support\Formatter::rupiah((float) ($mode === 'individu' ? \App\Support\Formatter::parseRupiah($amount) : ($category->default_amount ?? 0))) }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-[#f8fafc] p-3">
                            <dt class="text-[#64748b]">Jatuh Tempo</dt>
                            <dd class="mt-1 tabular text-[16px] font-semibold text-[#111827]">
                                {{ $due_date ? \App\Support\Formatter::date($due_date, 'd M Y') : '-' }}
                            </dd>
                        </div>
                    </dl>

                    @if($mode === 'individu')
                        @php($gross = \App\Support\Formatter::parseRupiah($amount))
                        @php($cut = \App\Support\Formatter::parseRupiah($discount_amount))
                        <div class="mt-3 flex items-center justify-between rounded-lg bg-[#e8f1fb] px-3 py-2">
                            <span class="text-[13px] text-[#20599f]">Nominal Setelah Potongan</span>
                            <span class="tabular text-[15px] font-semibold text-[#2872c8]">{{ \App\Support\Formatter::rupiah(max($gross - $cut, 0)) }}</span>
                        </div>
                    @else
                        @if($existingPeriods !== [])
                            <p class="mt-3 flex items-start gap-2 text-[12px] text-[#b9770e]">
                                <i data-lucide="triangle-alert" class="w-4 h-4 shrink-0 mt-0.5"></i>
                                <span>Kategori ini sudah memiliki tagihan pada periode: {{ implode(', ', $existingPeriods) }}. Periode tersebut akan dilewati otomatis.</span>
                            </p>
                        @endif
                        <p class="mt-3 flex items-start gap-2 text-[12px] text-[#64748b]">
                            <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                            <span>Siswa yang sudah memiliki tagihan pada kategori dan periode yang sama tidak akan diduplikasi.</span>
                        </p>
                    @endif

                    @if((int) $maxBillItems < 6)
                        <p class="mt-2 text-[12px] text-[#8994a3]">Batas item tagihan per periode mengikuti pengaturan sistem ({{ $maxBillItems }} item).</p>
                    @endif
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="card p-5 space-y-4 lg:sticky lg:top-4">
                    <h2 class="text-[15px] font-semibold text-gray-900 border-b border-[#e1e6ed] pb-3">Konfirmasi</h2>
                    <label class="flex items-start gap-2.5 text-[13px] text-[#475569] cursor-pointer">
                        <input type="checkbox" wire:model.live="confirmed" class="mt-0.5 rounded border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]">
                        <span>Saya memastikan tahun ajaran, kategori, sasaran, dan periode tagihan sudah benar.</span>
                    </label>
                    @error('confirmed')<p class="form-error">{{ $message }}</p>@enderror
                    <div class="pt-4 border-t border-[#e1e6ed] space-y-2">
                        <x-ui.button variant="primary" type="submit" icon="check" class="w-full justify-center" loading>Generate Tagihan</x-ui.button>
                        <a href="{{ route('bills.index') }}" class="btn btn-secondary w-full justify-center">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
