@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pembayaran', 'url' => route('payments.index')], ['label' => $payment->transaction_number]]" />
@endsection

@php
    $statusValue = $status->value;
    $badgeVariant = match($statusValue) {
        'approved' => 'success',
        'rejected', 'cancelled' => 'danger',
        default => 'warning',
    };
    $statusLabel = match($statusValue) {
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        default => 'Menunggu Verifikasi',
    };
    $statusIcon = match($statusValue) {
        'approved' => 'check-circle-2',
        'rejected' => 'circle-alert',
        'cancelled' => 'ban',
        default => 'clock',
    };

    $canVerify = \Illuminate\Support\Facades\Gate::allows('payments.verify');
    $canCancel = \Illuminate\Support\Facades\Gate::allows('payments.cancel');
    $canPrint = \Illuminate\Support\Facades\Gate::allows('payments.print');
    $canManageProof = \Illuminate\Support\Facades\Gate::allows('payments.create');

    $showVerify = $statusValue === 'pending' && $canVerify;
    $showCancel = $statusValue !== 'cancelled' && $canCancel;
@endphp

<div class="space-y-5">
    {{-- Top Action & Title Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0">
                <i data-lucide="receipt" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="page-title">Detail Pembayaran</h1>
                    <x-ui.badge :variant="$badgeVariant" :dot="true">{{ $statusLabel }}</x-ui.badge>
                </div>
                <p class="mt-0.5 text-[13px] text-[#64748b] flex items-center gap-2">
                    <span>Nomor: <span class="font-mono text-[#334155] font-medium">{{ $payment->transaction_number }}</span></span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2.5">
            @if($canPrint)
                <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-secondary">
                    <i data-lucide="printer" class="w-4 h-4 text-[#2872c8]"></i>
                    <span>Cetak Kwitansi</span>
                </a>
            @endif
            <a href="{{ route('payments.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    {{-- Hero Summary Card --}}
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-[#1f5289] via-[#2872c8] to-[#3f8ee0] p-5 text-white shadow-sm">
        <i data-lucide="receipt" class="absolute -right-4 -bottom-6 w-36 h-36 text-white/10" aria-hidden="true"></i>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1.5">
                <p class="text-[11px] uppercase tracking-wider text-white/70 font-semibold">Total Nominal Pembayaran</p>
                <p class="text-3xl sm:text-4xl font-bold tabular tracking-tight">{{ \App\Support\Formatter::rupiah((float) $payment->amount) }}</p>
                <div class="flex items-center gap-2 text-xs text-white/90 pt-1">
                    <span class="font-medium">{{ $payment->student->name }}</span>
                    <span>·</span>
                    <span class="font-mono text-white/80">NIS: {{ $payment->student->nis ?: '-' }}</span>
                    @if($payment->student->schoolClass)
                        <span>·</span>
                        <span class="rounded bg-white/20 px-1.5 py-0.5 text-[11px] font-medium text-white">{{ $payment->student->schoolClass->name }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-white/90">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 backdrop-blur px-3 py-1.5 text-[12px]">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-white/80"></i>
                    <span>{{ $payment->paid_at->format('d/m/Y H:i') }} WIB</span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 backdrop-blur px-3 py-1.5 text-[12px]">
                    <i data-lucide="layers" class="w-3.5 h-3.5 text-white/80"></i>
                    <span>{{ $payment->paymentCategory->name }}</span>
                </span>
            </div>
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid gap-5 lg:grid-cols-3 items-start">
        {{-- Left 2 Columns: Information & Allocations --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Informasi Detail Card --}}
            <div class="card p-5 bg-white shadow-sm border border-[#e2e8f0] rounded-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#e2e8f0]">
                    <h2 class="text-sm font-semibold text-[#111827] flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-[#2872c8]"></i>
                        <span>Informasi Transaksi</span>
                    </h2>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div class="flex items-start gap-3 p-3 rounded-lg bg-[#f8fafc] border border-[#f1f5f9]">
                        <div class="w-8 h-8 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-[#64748b]">Siswa</p>
                            <p class="font-semibold text-[#111827] truncate mt-0.5">{{ $payment->student->name }}</p>
                            <p class="text-xs text-[#94a3b8] font-mono mt-0.5">NIS: {{ $payment->student->nis ?: '-' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3 rounded-lg bg-[#f8fafc] border border-[#f1f5f9]">
                        <div class="w-8 h-8 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-[#64748b]">Waktu Pembayaran</p>
                            <p class="font-semibold text-[#111827] mt-0.5">{{ $payment->paid_at->format('d F Y') }}</p>
                            <p class="text-xs text-[#94a3b8] mt-0.5">{{ $payment->paid_at->format('H:i') }} WIB</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3 rounded-lg bg-[#f8fafc] border border-[#f1f5f9]">
                        <div class="w-8 h-8 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="layers" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-[#64748b]">Kategori / Tahun Ajaran</p>
                            <p class="font-semibold text-[#111827] mt-0.5">{{ $payment->paymentCategory->name }}</p>
                            <p class="text-xs text-[#94a3b8] mt-0.5">{{ $payment->academicYear?->name ?? 'Semua Tahun' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3 rounded-lg bg-[#f8fafc] border border-[#f1f5f9]">
                        <div class="w-8 h-8 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-[#64748b]">Metode Pembayaran</p>
                            <p class="font-semibold text-[#111827] mt-0.5">
                                {{ $payment->details->pluck('paymentMethod.name')->filter()->implode(', ') ?: 'Kasir / Tunai' }}
                            </p>
                        </div>
                    </div>
                </div>

                @if($payment->notes)
                    <div class="p-3.5 rounded-lg bg-[#fffbeb] border border-[#fef3c7]">
                        <p class="text-xs font-semibold text-[#b45309] flex items-center gap-1.5">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                            <span>Catatan Transaksi</span>
                        </p>
                        <p class="text-xs text-[#78350f] mt-1 whitespace-pre-line leading-relaxed">{{ $payment->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Alokasi Tagihan Card --}}
            <div class="card !p-0 overflow-hidden shadow-sm border border-[#e2e8f0] rounded-xl bg-white">
                <div class="px-5 py-4 border-b border-[#e2e8f0] flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-[#111827] flex items-center gap-2">
                            <i data-lucide="calculator" class="w-4 h-4 text-[#2872c8]"></i>
                            <span>Alokasi Ke Tagihan Siswa</span>
                        </h2>
                        <p class="text-xs text-[#64748b] mt-0.5">Pembagian dana pembayaran ke rincian item tagihan.</p>
                    </div>
                    <span class="badge badge-neutral text-xs">{{ $payment->allocations->count() }} Item</span>
                </div>

                <x-ui.table :empty="$payment->allocations->isEmpty()" empty-title="Belum Ada Alokasi" empty-description="Pembayaran ini belum dialokasikan ke tagihan mana pun.">
                    <x-slot:head>
                        <tr class="bg-[#f8fafc] border-b border-[#e2e8f0] text-xs uppercase tracking-wider text-[#64748b]">
                            <th class="py-3 px-4 font-semibold">No. Tagihan</th>
                            <th class="py-3 px-4 font-semibold">Periode Tagihan</th>
                            <th class="py-3 px-4 font-semibold text-right">Nominal Alokasi</th>
                        </tr>
                    </x-slot:head>
                    @foreach($payment->allocations as $allocation)
                        <tr class="border-b border-[#f1f5f9] last:border-b-0 hover:bg-[#f8fafc]/80 transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-sm text-[#2872c8]">
                                {{ $allocation->studentBill?->bill?->bill_number ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-[#334155]">
                                {{ $allocation->studentBill?->bill?->period ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-sm tabular text-[#111827]">
                                {{ \App\Support\Formatter::rupiah((float) $allocation->amount) }}
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
                @if($payment->allocations->isNotEmpty())
                    <div class="flex items-center justify-between border-t border-[#e2e8f0] bg-[#f8fafc] px-4 py-3">
                        <span class="text-xs font-semibold uppercase text-[#64748b]">Total Alokasi</span>
                        <span class="text-sm font-semibold tabular text-[#2872c8]">
                            {{ \App\Support\Formatter::rupiah((float) $payment->allocations->sum('amount')) }}
                        </span>
                    </div>
                @endif
            </div>

            {{-- Bukti Pembayaran Card --}}
            <div class="card p-5 bg-white shadow-sm border border-[#e2e8f0] rounded-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#e2e8f0]">
                    <div>
                        <h2 class="text-sm font-semibold text-[#111827] flex items-center gap-2">
                            <i data-lucide="file-badge" class="w-4 h-4 text-[#2872c8]"></i>
                            <span>Bukti Pembayaran</span>
                        </h2>
                        <p class="text-xs text-[#64748b] mt-0.5">Berkas struk, resi transfer, atau nota fisik.</p>
                    </div>
                </div>

                @if($payment->proofs->isEmpty())
                    <x-ui.empty-state
                        title="Belum Ada Bukti Pembayaran"
                        description="Belum ada foto atau dokumen lampiran yang diunggah untuk transaksi ini."
                    />
                @else
                    <ul class="space-y-2">
                        @foreach($payment->proofs as $proof)
                            <li wire:key="proof-{{ $proof->id }}" class="flex items-center justify-between rounded-xl border border-[#e2e8f0] p-3 hover:bg-[#f8fafc] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0">
                                        <i data-lucide="file-text" class="w-4 h-4"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('payment-proofs.download', $proof) }}" class="truncate text-xs font-semibold text-[#2872c8] hover:underline block">
                                            {{ $proof->original_name }}
                                        </a>
                                        <p class="text-[11px] text-[#8994a3] mt-0.5">
                                            {{ number_format(($proof->file_size ?? 0) / 1024, 0) }} KB · {{ $proof->created_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                @if($canManageProof)
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm text-[#c0392b] hover:bg-[#fceae6] !px-2.5"
                                        wire:click="deleteProof({{ $proof->id }})"
                                        wire:confirm="Hapus bukti pembayaran ini?"
                                        title="Hapus Bukti"
                                    >
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($canManageProof)
                    <div class="pt-3 border-t border-[#e2e8f0] space-y-3">
                        <label class="block text-xs font-semibold text-[#334155]" for="proof">
                            Unggah Bukti Baru <span class="text-[#94a3b8] font-normal">(JPG, PNG, PDF - Maks. 5 MB)</span>
                        </label>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <input
                                id="proof"
                                type="file"
                                wire:model="proof"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="block w-full text-xs text-[#475569] file:mr-3 file:rounded-lg file:border-0 file:bg-[#e8f1fb] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#2872c8] hover:file:bg-[#d4e4f7]"
                            >
                            <x-ui.button
                                variant="primary"
                                wire:click="uploadProof"
                                wire:loading.attr="disabled"
                                wire:target="uploadProof,proof"
                                class="shrink-0"
                            >
                                <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                <span>Unggah</span>
                            </x-ui.button>
                        </div>
                        @error('proof')
                            <p class="text-xs text-[#c0392b]">{{ $message }}</p>
                        @enderror
                        <div wire:loading wire:target="proof" class="text-xs text-[#2872c8] flex items-center gap-1.5">
                            <i data-lucide="loader-circle" class="w-3.5 h-3.5 animate-spin"></i>
                            <span>Mengunggah berkas...</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right 1 Column: Actions & Verification History --}}
        <div class="space-y-5">
            {{-- Action Card --}}
            <div class="card p-5 bg-white shadow-sm border border-[#e2e8f0] rounded-xl space-y-4">
                <h2 class="text-sm font-semibold text-[#111827] pb-3 border-b border-[#e2e8f0] flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4 text-[#2872c8]"></i>
                    <span>Tindakan Verifikasi</span>
                </h2>

                @if($showVerify)
                    <div class="space-y-3">
                        <div class="p-3 rounded-lg bg-[#e8f1fb] text-xs text-[#1e5494]">
                            <p class="font-semibold">Pembayaran Menunggu Verifikasi</p>
                            <p class="mt-0.5 text-[#334155]">Silakan periksa kesesuaian dana lalu setujui atau tolak transaksi ini.</p>
                        </div>
                        <x-ui.button
                            variant="success"
                            wire:click="approve"
                            wire:confirm="Setujui pembayaran ini?"
                            class="w-full justify-center"
                        >
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span>Setujui Pembayaran</span>
                        </x-ui.button>

                        <div class="pt-2 border-t border-[#f1f5f9] space-y-2">
                            <x-ui.textarea
                                wire:model="reason"
                                name="reason"
                                label="Alasan Penolakan"
                                placeholder="Wajib diisi jika hendak menolak pembayaran."
                                rows="2"
                            />
                            <x-ui.button
                                variant="danger"
                                wire:click="reject"
                                wire:confirm="Tolak pembayaran ini?"
                                class="w-full justify-center"
                            >
                                <i data-lucide="circle-alert" class="w-4 h-4"></i>
                                <span>Tolak Pembayaran</span>
                            </x-ui.button>
                        </div>
                    </div>
                @endif

                @if($showCancel)
                    <div class="space-y-2 {{ $showVerify ? 'pt-4 border-t border-[#e2e8f0]' : '' }}">
                        <p class="text-xs font-semibold text-[#c0392b]">Pembatalan Transaksi</p>
                        <x-ui.textarea
                            wire:model="reason"
                            name="reason"
                            label="Alasan Pembatalan"
                            placeholder="Tuliskan alasan pembatalan pembayaran ini."
                            rows="2"
                        />
                        <x-ui.button
                            variant="danger"
                            wire:click="cancel"
                            wire:confirm="Batalkan pembayaran ini?"
                            class="w-full justify-center"
                        >
                            <i data-lucide="ban" class="w-4 h-4"></i>
                            <span>Batalkan Pembayaran</span>
                        </x-ui.button>
                    </div>
                @endif

                @if(! $showVerify && ! $showCancel)
                    <div class="text-center py-4 text-xs text-[#94a3b8]">
                        <i data-lucide="shield-off" class="w-8 h-8 mx-auto mb-2 text-[#cbd5e1]"></i>
                        <p>Tidak ada tindakan verifikasi yang tersedia untuk status transaksi ini.</p>
                    </div>
                @endif
            </div>

            {{-- Riwayat Verifikasi Card (If any) --}}
            @if($payment->verifications->isNotEmpty())
                <div class="card p-5 bg-white shadow-sm border border-[#e2e8f0] rounded-xl space-y-3">
                    <h2 class="text-sm font-semibold text-[#111827] pb-2 border-b border-[#e2e8f0] flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-[#2872c8]"></i>
                        <span>Riwayat Verifikasi</span>
                    </h2>
                    <ul class="space-y-3 text-xs">
                        @foreach($payment->verifications->sortByDesc('created_at') as $v)
                            @php
                                $vVariant = match($v->status->value) {
                                    'approved' => 'success',
                                    'rejected', 'cancelled' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <li class="p-3 rounded-lg bg-[#f8fafc] border border-[#f1f5f9] space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-[#111827]">{{ $v->verifier?->name ?? 'Sistem' }}</span>
                                    <x-ui.badge :variant="$vVariant" class="!text-[10px]">{{ $v->status->value }}</x-ui.badge>
                                </div>
                                @if($v->notes)
                                    <p class="text-[#475569] italic">"{{ $v->notes }}"</p>
                                @endif
                                <p class="text-[10px] text-[#94a3b8]">{{ $v->created_at?->format('d/m/Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>

