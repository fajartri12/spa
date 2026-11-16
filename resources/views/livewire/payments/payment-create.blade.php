@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Pembayaran', 'url' => route('payments.index')], ['label' => 'Catat Pembayaran']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Catat Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Pilih siswa, tentukan alokasi tagihan, lalu simpan transaksi pembayaran kasir.</p>
        </div>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="w-4 h-4"></i><span>Daftar Pembayaran</span>
        </a>
    </div>

    @php use App\Support\Formatter; @endphp
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['label' => 'Total Tunggakan', 'amount' => $stats['outstanding'], 'note' => 'Seluruh tagihan belum lunas', 'color' => 'text-[#D83E78]'],
            ['label' => 'Total Pembayaran', 'amount' => $stats['total'], 'note' => $stats['total_count'] . ' transaksi hari ini', 'color' => 'text-[#111827]'],
            ['label' => 'Pembayaran Tunai', 'amount' => $stats['cash'], 'note' => $stats['cash_count'] . ' transaksi hari ini', 'color' => 'text-[#2872c8]'],
            ['label' => 'Pembayaran Non Tunai', 'amount' => $stats['non_cash'], 'note' => $stats['non_cash_count'] . ' transaksi hari ini', 'color' => 'text-[#b9770e]'],
        ] as $card)
            <x-ui.card>
                <p class="text-[12px] font-medium uppercase tracking-wide text-[#8994a3]">{{ $card['label'] }}</p>
                <p class="mt-2 text-lg font-semibold tabular {{ $card['color'] }}">{{ Formatter::rupiah($card['amount']) }}</p>
                <p class="mt-0.5 text-[12px] text-[#8994a3]">{{ $card['note'] }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <form wire:submit="save" novalidate>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-4">
                <div class="card p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <x-ui.select wire:model.live="classFilter" name="classFilter" label="Filter Kelas" :options="$classes" placeholder="-- Semua Kelas --" />
                        </div>
                        <div class="md:col-span-2">
                            <x-ui.select wire:model.live="student_id" name="student_id" label="Pilih Siswa" :options="$students" placeholder="-- Pilih Siswa / NIS --" required />
                            @error('student_id')<p class="form-error mt-2">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                @if($selectedStudent)
                    <div class="card p-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 shrink-0 rounded-full bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center font-bold text-[16px]">
                                {{ \Illuminate\Support\Str::of($selectedStudent->name)->substr(0, 2)->upper() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-[15px] text-[#111827] truncate">{{ $selectedStudent->name }}</div>
                                <div class="text-[12px] text-[#64748b] mt-0.5">NIS: <span class="font-medium text-[#111827]">{{ $selectedStudent->nis }}</span>@if($selectedStudent->nisn) · NISN: <span class="font-medium text-[#111827]">{{ $selectedStudent->nisn }}</span>@endif</div>
                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    <x-ui.badge variant="info">{{ $selectedStudent->schoolClass?->name ?? 'Tanpa kelas' }}</x-ui.badge>
                                    @if($selectedStudent->unit)
                                        <x-ui.badge variant="neutral">{{ $selectedStudent->unit->name }}</x-ui.badge>
                                    @endif
                                    @if($selectedStudent->entry_year)
                                        <x-ui.badge variant="neutral">Angkatan {{ $selectedStudent->entry_year }}</x-ui.badge>
                                    @endif
                                    <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                                </div>
                            </div>
                            <dl class="hidden sm:grid grid-cols-1 gap-0.5 text-[12px] text-right shrink-0">
                                <dt class="text-[#64748b]">Orang Tua / Wali</dt>
                                <dd class="font-medium text-[#111827]">{{ $selectedStudent->parent_name ?? '-' }}</dd>
                                <dd class="text-[#64748b] tabular">{{ $selectedStudent->parent_phone ?? '-' }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card p-0 overflow-hidden">
                        <div class="bg-[#f8fafc] px-4 py-3 border-b border-[#e1e6ed] flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-semibold text-[#334155]">Tagihan Belum Lunas</span>
                                <span class="badge badge-neutral">{{ $studentBills->count() }} item</span>
                            </div>
                            @if($studentBills->isNotEmpty())
                                <div class="flex gap-2">
                                    <x-ui.button variant="ghost" size="sm" wire:click="fillAll" type="button">Lunasi Semua</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="clearAllocations" type="button">Kosongkan</x-ui.button>
                                </div>
                            @endif
                        </div>
                        <div class="divide-y divide-[#e1e6ed]">
                            @forelse($studentBills as $bill)
                                <div class="px-4 py-3 grid sm:grid-cols-[1fr_200px] gap-3 items-center hover:bg-[#fafbfd] transition-colors">
                                    <div class="min-w-0">
                                        <div class="font-medium text-[13px] text-[#111827] truncate">{{ $bill->bill?->paymentCategory?->name ?? $bill->bill?->title }}</div>
                                        <div class="text-[12px] text-[#64748b]">{{ $bill->bill_number }}@if($bill->bill?->period) · Periode {{ $bill->bill->period }}@endif</div>
                                        <div class="text-[12px] mt-1 flex flex-wrap gap-x-3 text-[#64748b]">
                                            <span>Sisa: <span class="tabular font-semibold text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $bill->remaining_amount) }}</span></span>
                                            <span>Jatuh tempo: {{ $bill->due_date?->format('d/m/Y') ?? '-' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-end gap-1.5">
                                        <div class="flex-1">
                                            <x-ui.input wire:model.live.debounce.300ms="allocations.{{ $bill->id }}" name="allocations.{{ $bill->id }}" type="number" min="0" max="{{ $bill->remaining_amount }}" step="0.01" label="Alokasi" prefix="Rp" />
                                        </div>
                                        <button type="button" wire:click="fillFull({{ $bill->id }}, '{{ (float) $bill->remaining_amount }}')"
                                                class="btn btn-secondary btn-sm mb-[1px] px-2.5" title="Alokasikan penuh">
                                            <span>Lunas</span>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <i data-lucide="check-circle-2" class="w-8 h-8 text-[#16a34a] mx-auto"></i>
                                    <p class="mt-2 text-[13px] text-[#64748b]">Siswa ini tidak memiliki tagihan aktif yang belum lunas.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="card p-10 text-center">
                        <i data-lucide="user-search" class="w-10 h-10 text-[#cbd5e1] mx-auto"></i>
                        <p class="mt-3 text-[13px] font-medium text-[#334155]">Pilih siswa terlebih dahulu</p>
                        <p class="text-[12px] text-[#64748b] mt-1">Daftar tagihan yang belum lunas akan muncul otomatis setelah siswa dipilih.</p>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-1">
                <div class="card p-5 space-y-4 lg:sticky lg:top-4">
                    <h2 class="text-[15px] font-semibold text-gray-900 border-b border-[#e1e6ed] pb-3">Ringkasan Pembayaran</h2>
                    <dl class="space-y-2 text-[13px]">
                        <div class="flex justify-between gap-3"><dt class="text-[#64748b]">Total Sisa Tagihan</dt><dd class="tabular font-medium text-gray-900">{{ \App\Support\Formatter::rupiah($totalRemaining) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-[#64748b]">Total Dialokasikan</dt><dd class="tabular font-medium text-gray-900">{{ \App\Support\Formatter::rupiah($totalAllocated) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-[#64748b]">Sisa Setelah Bayar</dt><dd class="tabular font-medium text-gray-900">{{ \App\Support\Formatter::rupiah(max($totalRemaining - $totalAllocated, 0)) }}</dd></div>
                        <div class="flex justify-between gap-3 border-t border-[#e1e6ed] pt-2.5">
                            <dt class="font-semibold text-gray-900">Total Pembayaran</dt>
                            <dd class="tabular font-bold text-[16px] text-[#2872c8]">{{ \App\Support\Formatter::rupiah($totalAllocated) }}</dd>
                        </div>
                    </dl>
                    <div class="pt-4 border-t border-[#e1e6ed] space-y-4">
                        <x-ui.select wire:model="payment_method_id" name="payment_method_id" label="Metode Pembayaran" :options="$paymentMethods" placeholder="-- Pilih Metode --" required />
                        @error('payment_method_id')<p class="form-error">{{ $message }}</p>@enderror
                        <x-ui.textarea wire:model="notes" name="notes" label="Catatan" placeholder="Catatan transaksi (opsional)" rows="3" />
                        @error('allocations')<p class="form-error">{{ $message }}</p>@enderror
                        @error('amount')<p class="form-error">{{ $message }}</p>@enderror
                        <div class="flex justify-end gap-2 pt-2">
                            <a href="{{ route('payments.index') }}" class="btn btn-secondary">Batal</a>
                            <x-ui.button variant="primary" type="submit" icon="check" loading>Simpan Pembayaran</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
