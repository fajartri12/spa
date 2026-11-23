@php
    use App\Enums\BillStatus;
    use App\Enums\PaymentStatus;

    $billBadge = function ($status, $dueDate) {
        $val = $status instanceof BillStatus ? $status->value : $status;
        if ($val === BillStatus::Paid->value || $val === 'paid') {
            return ['label' => 'Lunas', 'class' => 'bg-[#DCFCE7] text-[#15803D]'];
        }

        if ($val === BillStatus::Cancelled->value || $val === 'cancelled') {
            return ['label' => 'Dibatalkan', 'class' => 'bg-slate-100 text-slate-600'];
        }

        return $dueDate?->isPast()
            ? ['label' => 'Jatuh Tempo', 'class' => 'bg-[#FEE2E2] text-[#B91C1C]']
            : ['label' => 'Belum Lunas', 'class' => 'bg-[#FEF3C7] text-[#B45309]'];
    };

    $paymentBadge = function ($status) {
        $val = $status instanceof PaymentStatus ? $status->value : $status;
        return match ($val) {
            PaymentStatus::Approved->value, 'approved' => ['label' => 'Disetujui', 'class' => 'bg-[#DCFCE7] text-[#15803D]'],
            PaymentStatus::Pending->value, 'pending' => ['label' => 'Menunggu', 'class' => 'bg-[#FEF3C7] text-[#B45309]'],
            PaymentStatus::Rejected->value, 'rejected' => ['label' => 'Ditolak', 'class' => 'bg-[#FEE2E2] text-[#B91C1C]'],
            PaymentStatus::Cancelled->value, 'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-slate-100 text-slate-600'],
            default => ['label' => ucfirst((string) $val), 'class' => 'bg-slate-100 text-slate-600'],
        };
    };
@endphp

<div class="mx-auto w-full max-w-5xl space-y-4 pb-28 sm:space-y-5 lg:pb-10">

    {{-- Welcome banner --}}
    <section id="beranda" data-portal-section class="scroll-mt-24 rounded-[20px] bg-gradient-to-br from-[#2563EB] to-[#1D4ED8] p-5 text-white shadow-[0_16px_40px_-24px_rgba(37,99,235,0.7)] sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-xl font-bold text-white sm:h-14 sm:w-14 sm:text-2xl">
                    {{ strtoupper(substr($student->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-blue-100">Portal Siswa</p>
                    <h1 class="mt-1 break-words text-xl font-bold leading-snug sm:text-2xl">{{ $student->name }}</h1>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-blue-100">
                        <span>NIS {{ $student->nis ?? '-' }}</span>
                        <span>&middot;</span>
                        <span>NISN {{ $student->nisn ?? '-' }}</span>
                        @if($student->schoolClass)
                            <span>&middot;</span>
                            <span>Kelas {{ $student->schoolClass->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('public.student.logout') }}" class="shrink-0">
                @csrf
                <button type="submit" aria-label="Keluar dari portal"
                        class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-3 text-sm font-semibold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/70">
                    <i data-lucide="log-out" aria-hidden="true" class="h-4 w-4"></i>
                    <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>
    </section>

    {{-- Ringkasan keuangan --}}
    <section aria-label="Ringkasan keuangan" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="portal-card p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FEF3C7] text-[#B45309]">
                    <i data-lucide="receipt" aria-hidden="true" class="h-4 w-4"></i>
                </span>
                <p class="text-xs font-medium text-[#64748B] sm:text-sm">Sisa Tagihan</p>
            </div>
            <p class="mt-3 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($outstanding['amount'], 0, ',', '.') }}</p>
            <p class="mt-1 text-[11px] text-[#B45309] sm:text-xs">{{ $outstanding['count'] }} tagihan belum lunas</p>
        </div>
        <div class="portal-card p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#D1FAE5] text-[#047857]">
                    <i data-lucide="wallet" aria-hidden="true" class="h-4 w-4"></i>
                </span>
                <p class="text-xs font-medium text-[#64748B] sm:text-sm">Saldo Tabungan</p>
            </div>
            <p class="mt-3 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($savingsAccount?->balance ?? 0, 0, ',', '.') }}</p>
            <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">{{ $savingsAccount ? 'Diperbarui ' . ($savingsAccount->updated_at?->format('d M Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
        </div>
        <div class="portal-card p-4 sm:p-5 sm:col-span-3 xl:col-span-1">
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#EFF6FF] text-[#2563EB]">
                    <i data-lucide="history" aria-hidden="true" class="h-4 w-4"></i>
                </span>
                <p class="text-xs font-medium text-[#64748B] sm:text-sm">Pembayaran Terakhir</p>
            </div>
            <p class="mt-3 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">
                @if($latestApprovedPayment)
                    Rp {{ number_format($latestApprovedPayment->amount, 0, ',', '.') }}
                @else
                    -
                @endif
            </p>
            <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">{{ $latestApprovedPayment ? 'Disetujui ' . ($latestApprovedPayment->paid_at?->format('d M Y') ?? '-') : 'Belum ada pembayaran disetujui' }}</p>
        </div>
    </section>

    {{-- Kartu visual matriks SPP --}}
    @include('livewire.public.partials.spp-matrix', ['sppMatrix' => $sppMatrix, 'sppPdfUrl' => $sppPdfUrl])

    {{-- Shortcut --}}
    <nav aria-label="Pintasan" class="grid grid-cols-4 gap-2 sm:gap-3">
        @foreach([
            ['href' => '#tagihan', 'icon' => 'receipt', 'label' => 'Tagihan'],
            ['href' => '#tabungan', 'icon' => 'wallet', 'label' => 'Tabungan'],
            ['href' => '#riwayat', 'icon' => 'history', 'label' => 'Riwayat'],
            ['href' => '#profil', 'icon' => 'user-round', 'label' => 'Data Diri'],
        ] as $shortcut)
            <a href="{{ $shortcut['href'] }}"
               class="portal-card flex min-h-[72px] flex-col items-center justify-center gap-1.5 p-2 text-center transition hover:border-[#CBD5E1] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                <i data-lucide="{{ $shortcut['icon'] }}" aria-hidden="true" class="h-5 w-5 text-[#2563EB]"></i>
                <span class="text-[11px] font-semibold leading-tight text-[#334155] sm:text-xs">{{ $shortcut['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Kontak orang tua --}}
    <section aria-label="Kontak orang tua" class="portal-card p-4 sm:p-5">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EFF6FF] text-[#2563EB]">
                <i data-lucide="users" aria-hidden="true" class="h-5 w-5"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Orang Tua / Wali Terdaftar</p>
                <p class="mt-1 break-words text-sm font-bold text-[#0F172A]">{{ $student->parent_name ?? 'Belum terisi' }}</p>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#64748B]">
                    <span>Hubungan <strong class="font-semibold text-[#334155]">{{ $student->parent_relation ?? 'Wali' }}</strong></span>
                    @if($student->parent_phone)
                        <span>HP <strong class="font-semibold text-[#334155]">{{ $student->parent_phone }}</strong></span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Rincian tagihan --}}
    <section id="tagihan" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-[#0F172A] sm:text-lg">Rincian Tagihan</h2>
                <p class="mt-0.5 truncate text-xs text-[#64748B]">Daftar seluruh tagihan Anda</p>
            </div>
            <span class="shrink-0 rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-semibold text-[#2563EB]">{{ $bills->total() }}</span>
        </div>

        <div class="divide-y divide-[#E2E8F0] md:hidden">
            @forelse($bills as $bill)
                @php $badge = $billBadge($bill->status, $bill->due_date); @endphp                <article class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[#0F172A]">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? 'Tagihan Sekolah' }}</p>
                            <p class="mt-1 break-words text-xs text-[#64748B]">{{ $bill->bill_number }} &middot; Jatuh tempo {{ $bill->due_date?->format('d M Y') ?? '-' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 rounded-[14px] bg-[#F8FAFC] p-3 text-xs">
                        <span class="text-[#64748B]">Nominal awal</span>
                        <span class="text-right font-semibold tabular-nums text-[#334155]">Rp {{ number_format($bill->original_amount, 0, ',', '.') }}</span>
                        <span class="text-[#64748B]">Potongan</span>
                        <span class="text-right font-semibold tabular-nums text-[#334155]">Rp {{ number_format($bill->discount_amount, 0, ',', '.') }}</span>
                        <span class="text-[#64748B]">Sudah dibayar</span>
                        <span class="text-right font-semibold tabular-nums text-[#15803D]">Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</span>
                        <span class="border-t border-[#E2E8F0] pt-1.5 text-[#475569]">Sisa tagihan</span>
                        <span class="border-t border-[#E2E8F0] pt-1.5 text-right font-bold tabular-nums text-[#DC2626]">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</span>
                    </div>
                    @if($intents->has($bill->id))
                        @include('livewire.public.partials.pay-code', ['intent' => $intents->get($bill->id)])
                    @endif
                    @php $billVal = $bill->status instanceof \App\Enums\BillStatus ? $bill->status->value : $bill->status; @endphp
                    @if(! in_array($billVal, [\App\Enums\BillStatus::Paid->value, \App\Enums\BillStatus::Cancelled->value], true) && (float) $bill->remaining_amount > 0)
                        <button type="button" wire:click="openProofModal({{ $bill->id }})"
                                class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] px-3 text-xs font-semibold text-[#334155] transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                            <i data-lucide="upload" aria-hidden="true" class="h-4 w-4"></i>Unggah Bukti Transfer
                        </button>
                    @endif
                </article>
            @empty
                <p class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada tagihan.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-[#E2E8F0]">
                <thead class="bg-[#F8FAFC]">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Nomor / Jatuh Tempo</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Tagihan</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Nominal</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Dibayar</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Sisa</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Kode Bayar</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0] bg-white">
                    @forelse($bills as $bill)
                        @php $badge = $billBadge($bill->status, $bill->due_date); @endphp                        <tr wire:key="bill-{{ $bill->id }}">
                            <td class="px-4 py-3 text-sm">
                                <span class="block font-medium text-[#0F172A]">{{ $bill->bill_number }}</span>
                                <span class="text-xs text-[#64748B]">{{ $bill->due_date?->format('d M Y') ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#334155]">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-[#334155]">Rp {{ number_format($bill->final_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-[#15803D]">Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-[#DC2626]">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($intents->has($bill->id))
                                    @php $intent = $intents->get($bill->id); @endphp                                    <span class="block font-mono text-xs font-semibold text-[#0F172A]">{{ $intent->pay_code ?? 'QRIS' }}</span>
                                    <span class="block text-[11px] text-[#64748B]">{{ $intent->provider->label() }} &middot; {{ $intent->channelLabel() }}</span>
                                    <span class="block text-[11px] text-[#94A3B8]">s.d. {{ $intent->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}</span>
                                @else
                                    <span class="text-xs text-[#94A3B8]">Belum diterbitkan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">{{ $bills->links() }}</div>
    </section>

    {{-- Riwayat pembayaran --}}
    <section id="riwayat" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-[#0F172A] sm:text-lg">Riwayat Pembayaran</h2>
                <p class="mt-0.5 text-xs text-[#64748B]">Status transaksi dan unduh kuitansi</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#EFF6FF] text-[#2563EB]">
                <i data-lucide="receipt" aria-hidden="true" class="h-4 w-4"></i>
            </span>
        </div>

        <div class="divide-y divide-[#E2E8F0] md:hidden">
            @forelse($payments as $payment)
                @php $badge = $paymentBadge($payment->status); @endphp                <article class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#0F172A]">{{ $payment->paymentCategory?->name ?? 'Pembayaran Sekolah' }}</p>
                            <p class="mt-1 break-all text-xs text-[#64748B]">{{ $payment->transaction_number }} &middot; {{ $payment->paid_at?->format('d M Y') ?? '-' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-base font-bold tabular-nums text-[#0F172A]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                        @if(($payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status) === 'approved')
                            <a href="{{ route('public.receipt', $payment) }}"
                               class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#E2E8F0] px-3 text-xs font-semibold text-[#334155] transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                                <i data-lucide="download" aria-hidden="true" class="h-4 w-4"></i>Kuitansi
                            </a>
                        @endif
                    </div>
                    @if($payment->allocations->isNotEmpty())
                        <div class="space-y-1 border-t border-[#E2E8F0] pt-2">
                            @foreach($payment->allocations as $allocation)
                                <p class="flex justify-between gap-3 text-xs text-[#64748B]">
                                    <span class="min-w-0 truncate">{{ $allocation->studentBill?->bill_number ?? 'Alokasi tagihan' }}</span>
                                    <span class="shrink-0 font-medium tabular-nums text-[#334155]">Rp {{ number_format($allocation->amount, 0, ',', '.') }}</span>
                                </p>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <p class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada riwayat pembayaran.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-[#E2E8F0]">
                <thead class="bg-[#F8FAFC]">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Tanggal / Nomor</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Kategori / Tahun Ajaran</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Nominal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Alokasi</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Kuitansi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0] bg-white">
                    @forelse($payments as $payment)
                        @php $badge = $paymentBadge($payment->status); @endphp                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="px-4 py-3 text-sm">
                                <span class="block font-medium text-[#0F172A]">{{ $payment->paid_at?->format('d M Y') ?? '-' }}</span>
                                <span class="text-xs text-[#64748B]">{{ $payment->transaction_number }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#334155]">
                                {{ $payment->paymentCategory?->name ?? '-' }}
                                <span class="block text-xs text-[#64748B]">{{ $payment->academicYear?->name ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-[#0F172A]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-[#64748B]">
                                @forelse($payment->allocations as $allocation)
                                    <span class="block">{{ $allocation->studentBill?->bill_number ?? 'Tagihan' }}: Rp {{ number_format($allocation->amount, 0, ',', '.') }}</span>
                                @empty
                                    <span class="text-[#94A3B8]">-</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(($payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status) === 'approved')
                                    <a href="{{ route('public.receipt', $payment) }}"
                                       class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-xs font-semibold text-[#334155] transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                                        <i data-lucide="download" aria-hidden="true" class="h-3.5 w-3.5"></i>Unduh
                                    </a>
                                @else
                                    <span class="text-xs text-[#94A3B8]">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada riwayat pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">{{ $payments->links() }}</div>
    </section>

    {{-- Tabungan --}}
    <section id="tabungan" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-[#0F172A]">Riwayat Tabungan</h2>
                <p class="mt-0.5 truncate text-xs text-[#64748B]">Transaksi terbaru tabungan Anda</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-[#047857]">
                <i data-lucide="wallet" aria-hidden="true" class="h-4 w-4"></i>
            </span>
        </div>

        <div class="px-4 pt-4">
            <div class="rounded-[14px] bg-[#F0FDF4] p-4">
                <p class="text-xs font-medium text-[#047857]">Saldo tabungan saat ini</p>
                <p class="mt-1 break-words text-xl font-bold tabular-nums text-[#0F172A]">Rp {{ number_format($savingsAccount?->balance ?? 0, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-[#047857]">{{ $savingsAccount ? 'Diperbarui ' . ($savingsAccount->updated_at?->format('d M Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
            </div>
        </div>

        <div class="mt-2 divide-y divide-[#E2E8F0]">
            @forelse($savingsTransactions as $transaction)
                <article class="flex items-center justify-between gap-3 p-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $transaction->type === 'deposit' ? 'bg-[#D1FAE5] text-[#047857]' : 'bg-[#FEF3C7] text-[#B45309]' }}">
                            <i data-lucide="{{ $transaction->type === 'deposit' ? 'arrow-down-left' : 'arrow-up-right' }}" aria-hidden="true" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#0F172A]">{{ $transaction->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</p>
                            <p class="truncate text-xs text-[#64748B]">{{ $transaction->transacted_at?->format('d M Y H:i') }} &middot; {{ $transaction->transaction_number }}</p>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-bold {{ $transaction->type === 'deposit' ? 'text-[#15803D]' : 'text-[#DC2626]' }}">
                            {{ $transaction->type === 'deposit' ? '+' : '-' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-[11px] text-[#64748B]">Saldo Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</p>
                    </div>
                </article>
            @empty
                <p class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada transaksi tabungan.</p>
            @endforelse
        </div>

        <div class="border-t border-[#E2E8F0] px-4 py-3">{{ $savingsTransactions->links() }}</div>
    </section>

    {{-- Profil data siswa --}}
    <section id="profil" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
        <div class="border-b border-[#E2E8F0] px-4 py-4">
            <h2 class="text-base font-bold text-[#0F172A]">Profil Siswa</h2>
            <p class="mt-0.5 text-xs text-[#64748B]">Data hanya dapat dibaca. Perubahan dilakukan oleh petugas sekolah.</p>
        </div>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Nama</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->name }}</dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">NIS / NISN</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Unit</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->unit?->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Kelas</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->schoolClass?->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Jenis Kelamin</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->gender?->label() ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Tahun Masuk</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->entry_year ?? '-' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Kontak wali --}}
    <section aria-label="Kontak Orang Tua / Wali" class="portal-card p-4 sm:p-5">
        <h2 class="text-base font-bold text-[#0F172A]">Kontak Orang Tua / Wali</h2>
        <dl class="mt-3 space-y-3 text-sm">
            <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                <dt class="text-[#64748B]">Nama</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->parent_name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-[#64748B]">Nomor telepon</dt>
                <dd class="text-right font-semibold text-[#0F172A]">{{ $student->parent_phone ?? '-' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Bottom navigation 5 tab --}}
    <nav aria-label="Navigasi portal"
         x-data="{
            active: 'beranda',
            init() {
                const sections = Array.from(document.querySelectorAll('[data-portal-section]'));
                if (! sections.length || ! ('IntersectionObserver' in window)) return;
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) this.active = entry.target.id;
                    });
                }, { rootMargin: '-45% 0px -50% 0px' });
                sections.forEach((section) => observer.observe(section));
            },
         }"
         class="fixed inset-x-0 bottom-0 z-30 border-t border-[#E2E8F0] bg-white/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-1.5 backdrop-blur lg:hidden">
        <div class="mx-auto grid max-w-md grid-cols-5 gap-1">
            @foreach([
                ['id' => 'beranda', 'icon' => 'house', 'label' => 'Beranda'],
                ['id' => 'tagihan', 'icon' => 'receipt', 'label' => 'Tagihan'],
                ['id' => 'tabungan', 'icon' => 'wallet', 'label' => 'Tabungan'],
                ['id' => 'riwayat', 'icon' => 'history', 'label' => 'Riwayat'],
                ['id' => 'profil', 'icon' => 'user-round', 'label' => 'Profil'],
            ] as $item)
                <a href="#{{ $item['id'] }}"
                   :aria-current="active === '{{ $item['id'] }}' ? 'true' : 'false'"
                   :class="active === '{{ $item['id'] }}' ? 'text-[#2563EB] bg-[#EFF6FF]' : 'text-[#64748B]'"
                   class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                    <i data-lucide="{{ $item['icon'] }}" aria-hidden="true" class="h-5 w-5"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @include('livewire.public.partials.transfer-proof-modal')
</div>
