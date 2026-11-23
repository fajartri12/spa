@php
    use App\Enums\BillStatus;
    use App\Enums\PaymentStatus;

    $billBadge = function ($status, $dueDate) {
        if ($status === BillStatus::Paid->value) {
            return ['label' => 'Lunas', 'class' => 'bg-[#DCFCE7] text-[#15803D]'];
        }

        if ($status === BillStatus::Cancelled->value) {
            return ['label' => 'Dibatalkan', 'class' => 'bg-slate-100 text-slate-600'];
        }

        return $dueDate?->isPast()
            ? ['label' => 'Jatuh Tempo', 'class' => 'bg-[#FEE2E2] text-[#B91C1C]']
            : ['label' => 'Belum Lunas', 'class' => 'bg-[#FEF3C7] text-[#B45309]'];
    };

    $paymentBadge = fn ($status) => match ($status) {
        PaymentStatus::Approved->value => ['label' => 'Disetujui', 'class' => 'bg-[#DCFCE7] text-[#15803D]'],
        PaymentStatus::Pending->value => ['label' => 'Menunggu', 'class' => 'bg-[#FEF3C7] text-[#B45309]'],
        PaymentStatus::Rejected->value => ['label' => 'Ditolak', 'class' => 'bg-[#FEE2E2] text-[#B91C1C]'],
        PaymentStatus::Cancelled->value => ['label' => 'Dibatalkan', 'class' => 'bg-slate-100 text-slate-600'],
        default => ['label' => ucfirst((string) $status), 'class' => 'bg-slate-100 text-slate-600'],
    };
@endphp

<div class="mx-auto w-full max-w-5xl space-y-4 pb-28 sm:space-y-5 lg:pb-10">

    {{-- Welcome --}}
    <section id="beranda" data-portal-section class="scroll-mt-24 rounded-[20px] bg-gradient-to-br from-[#2563EB] to-[#1D4ED8] p-5 text-white shadow-[0_16px_40px_-24px_rgba(37,99,235,0.7)] sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-blue-100">Selamat datang</p>
                <h1 class="mt-1 break-words text-xl font-bold leading-snug sm:text-2xl">{{ $guardian->name }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-blue-100">
                    {{ $guardian->relation?->label() ?? 'Wali' }}
                    &middot; {{ $students->count() }} siswa terdaftar
                </p>
            </div>
            <form method="POST" action="{{ route('public.guardian.logout') }}" class="shrink-0">
                @csrf
                <button type="submit" aria-label="Keluar dari portal"
                        class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-3 text-sm font-semibold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/70">
                    <i data-lucide="log-out" aria-hidden="true" class="h-4 w-4"></i>
                    <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>
    </section>

    @if($students->isEmpty())
        <x-ui.alert type="warning" title="Belum ada siswa tertaut">
            Akun Anda belum terhubung ke data siswa aktif. Hubungi petugas sekolah untuk menautkan data anak Anda.
        </x-ui.alert>
    @else
        {{-- Ringkasan keuangan --}}
        <section aria-label="Ringkasan keuangan seluruh anak" class="grid grid-cols-2 gap-3 sm:gap-4">
            <div class="portal-card p-4 sm:p-5">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FEF3C7] text-[#B45309]">
                        <i data-lucide="receipt" aria-hidden="true" class="h-4 w-4"></i>
                    </span>
                    <p class="text-xs font-medium text-[#64748B] sm:text-sm">Total Sisa Tagihan</p>
                </div>
                <p class="mt-3 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">Gabungan seluruh anak</p>
            </div>
            <div class="portal-card p-4 sm:p-5">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#D1FAE5] text-[#047857]">
                        <i data-lucide="wallet" aria-hidden="true" class="h-4 w-4"></i>
                    </span>
                    <p class="text-xs font-medium text-[#64748B] sm:text-sm">Saldo Tabungan</p>
                </div>
                <p class="mt-3 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($totalSavings, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">Gabungan seluruh anak</p>
            </div>
        </section>

        {{-- Pemilih anak --}}
        <section aria-label="Daftar anak" class="space-y-2.5">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-base font-bold text-[#0F172A]">Pilih Anak</h2>
                <span class="text-xs text-[#64748B]">{{ $students->count() }} siswa</span>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($overview as $row)
                    @php $child = $row['student']; @endphp                    @php $isActive = $selected && $selected->id === $child->id; @endphp                    <button type="button"
                            wire:click="selectStudent({{ $child->id }})"
                            wire:key="child-{{ $child->id }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                            class="portal-card flex w-full items-start gap-3 p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-[#2563EB] {{ $isActive ? 'border-[#2563EB] ring-1 ring-[#2563EB]' : 'hover:border-[#CBD5E1]' }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[14px] bg-[#EFF6FF] text-base font-bold text-[#2563EB]">
                            {{ strtoupper(substr($child->name, 0, 1)) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-[#0F172A]">{{ $child->name }}</span>
                            <span class="mt-0.5 block truncate text-xs text-[#64748B]">
                                {{ $child->nis ?? '-' }}
                                @if($child->schoolClass) &middot; {{ $child->schoolClass->name }} @endif
                                @if($child->unit) &middot; {{ $child->unit->name }} @endif
                            </span>
                            <span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                <span class="font-semibold tabular-nums text-[#B45309]">Sisa Rp {{ number_format($row['outstanding_amount'], 0, ',', '.') }}</span>
                                <span class="font-semibold tabular-nums text-[#047857]">Tabungan Rp {{ number_format($row['savings_balance'], 0, ',', '.') }}</span>
                            </span>
                        </span>
                        @if($isActive)
                            <span class="shrink-0 rounded-full bg-[#2563EB] px-2 py-0.5 text-[10px] font-bold uppercase text-white">Dipilih</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>

        @if($selected && $detail)
            {{-- Shortcut --}}
            <nav aria-label="Pintasan" class="grid grid-cols-4 gap-2 sm:gap-3">
                @foreach([
                    ['href' => '#tagihan', 'icon' => 'receipt', 'label' => 'Tagihan'],
                    ['href' => '#tabungan', 'icon' => 'wallet', 'label' => 'Tabungan'],
                    ['href' => '#riwayat', 'icon' => 'history', 'label' => 'Riwayat'],
                    ['href' => '#profil', 'icon' => 'user-round', 'label' => 'Data Siswa'],
                ] as $shortcut)
                    <a href="{{ $shortcut['href'] }}"
                       class="portal-card flex min-h-[72px] flex-col items-center justify-center gap-1.5 p-2 text-center transition hover:border-[#CBD5E1] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                        <i data-lucide="{{ $shortcut['icon'] }}" aria-hidden="true" class="h-5 w-5 text-[#2563EB]"></i>
                        <span class="text-[11px] font-semibold leading-tight text-[#334155] sm:text-xs">{{ $shortcut['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Identitas siswa terpilih --}}
            <section aria-label="Siswa terpilih" class="portal-card flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[14px] bg-[#EFF6FF] text-base font-bold text-[#2563EB]">
                        {{ strtoupper(substr($selected->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Rincian untuk</p>
                        <p class="truncate text-base font-bold text-[#0F172A]">{{ $selected->name }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#64748B]">
                    <span>NIS <strong class="font-semibold text-[#334155]">{{ $selected->nis ?? '-' }}</strong></span>
                    <span>NISN <strong class="font-semibold text-[#334155]">{{ $selected->nisn ?? '-' }}</strong></span>
                    @if($selected->schoolClass)
                        <span>Kelas <strong class="font-semibold text-[#334155]">{{ $selected->schoolClass->name }}</strong></span>
                    @endif
                </div>
            </section>

            {{-- Ringkasan siswa terpilih --}}
            <section aria-label="Ringkasan siswa terpilih" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
                <div class="portal-card p-4 sm:p-5">
                    <p class="text-xs font-medium text-[#64748B] sm:text-sm">Sisa Tagihan</p>
                    <p class="mt-2 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($detail['outstanding']['amount'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-[11px] text-[#B45309] sm:text-xs">{{ $detail['outstanding']['count'] }} tagihan belum lunas</p>
                </div>
                <div class="portal-card p-4 sm:p-5">
                    <p class="text-xs font-medium text-[#64748B] sm:text-sm">Saldo Tabungan</p>
                    <p class="mt-2 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">Rp {{ number_format($detail['savings_account']?->balance ?? 0, 0, ',', '.') }}</p>
                    <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">{{ $detail['savings_account'] ? 'Diperbarui ' . ($detail['savings_account']->updated_at?->format('d M Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
                </div>
                <div class="portal-card p-4 sm:p-5">
                    <p class="text-xs font-medium text-[#64748B] sm:text-sm">Pembayaran Terakhir</p>
                    <p class="mt-2 break-words text-xl font-bold tabular-nums text-[#0F172A] sm:text-2xl">
                        @if($detail['latest_payment'])
                            Rp {{ number_format($detail['latest_payment']->amount, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </p>
                    <p class="mt-1 text-[11px] text-[#64748B] sm:text-xs">{{ $detail['latest_payment'] ? 'Disetujui ' . ($detail['latest_payment']->paid_at?->format('d M Y') ?? '-') : 'Belum ada pembayaran disetujui' }}</p>
                </div>
            </section>

            {{-- Rincian tagihan --}}
            <section id="tagihan" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-[#0F172A] sm:text-lg">Rincian Tagihan</h2>
                        <p class="mt-0.5 truncate text-xs text-[#64748B]">Daftar tagihan {{ $selected->name }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-semibold text-[#2563EB]">{{ $detail['bills']->total() }}</span>
                </div>

                <div class="divide-y divide-[#E2E8F0] md:hidden">
                    @forelse($detail['bills'] as $bill)
                        @php $billStatus = $bill->status instanceof BillStatus ? $bill->status->value : $bill->status; @endphp                        @php $badge = $billBadge($billStatus, $bill->due_date); @endphp                        <article class="p-4">
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
                            @if($detail['intents']->has($bill->id))
                                @include('livewire.public.partials.pay-code', ['intent' => $detail['intents']->get($bill->id)])
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
                            @forelse($detail['bills'] as $bill)
                                @php $billStatus = $bill->status instanceof BillStatus ? $bill->status->value : $bill->status; @endphp                                @php $badge = $billBadge($billStatus, $bill->due_date); @endphp                                <tr wire:key="bill-{{ $bill->id }}">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="block font-medium text-[#0F172A]">{{ $bill->bill_number }}</span>
                                        <span class="text-xs text-[#64748B]">{{ $bill->due_date?->format('d M Y') ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#334155]">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? 'Tagihan Sekolah' }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-[#334155]">Rp {{ number_format($bill->final_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-[#15803D]">Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-[#DC2626]">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($detail['intents']->has($bill->id))
                                            @php $intent = $detail['intents']->get($bill->id); @endphp                                            <span class="block font-mono text-xs font-semibold text-[#0F172A]">{{ $intent->pay_code ?? 'QRIS' }}</span>
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

                <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">{{ $detail['bills']->links() }}</div>
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
                    @forelse($detail['payments'] as $payment)
                        @php $paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status; @endphp                        @php $badge = $paymentBadge($paymentStatus); @endphp                        <article class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[#0F172A]">{{ $payment->paymentCategory?->name ?? 'Pembayaran Sekolah' }}</p>
                                    <p class="mt-1 break-all text-xs text-[#64748B]">{{ $payment->transaction_number }} &middot; {{ $payment->paid_at?->format('d M Y') ?? '-' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-base font-bold tabular-nums text-[#0F172A]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                                @if($paymentStatus === PaymentStatus::Approved->value)
                                    <a href="{{ route('public.receipt', $payment) }}"
                                       class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#E2E8F0] px-3 text-xs font-semibold text-[#334155] transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                                        <i data-lucide="download" aria-hidden="true" class="h-4 w-4"></i>Kuitansi
                                    </a>
                                @endif
                            </div>
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
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Kategori</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Nominal</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Status</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Kuitansi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0] bg-white">
                            @forelse($detail['payments'] as $payment)
                                @php $paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status; @endphp                                @php $badge = $paymentBadge($paymentStatus); @endphp                                <tr wire:key="payment-{{ $payment->id }}">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="block font-medium text-[#0F172A]">{{ $payment->paid_at?->format('d M Y') ?? '-' }}</span>
                                        <span class="text-xs text-[#64748B]">{{ $payment->transaction_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#334155]">
                                        {{ $payment->paymentCategory?->name ?? '-' }}
                                        <span class="block text-xs text-[#64748B]">{{ $payment->academicYear?->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-[#0F172A]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($paymentStatus === PaymentStatus::Approved->value)
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
                                <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-[#64748B]">Belum ada riwayat pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">{{ $detail['payments']->links() }}</div>
            </section>

            {{-- Tabungan --}}
            <section id="tabungan" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-[#0F172A]">Riwayat Tabungan</h2>
                        <p class="mt-0.5 truncate text-xs text-[#64748B]">Transaksi terbaru {{ $selected->name }}</p>
                    </div>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-[#047857]">
                        <i data-lucide="wallet" aria-hidden="true" class="h-4 w-4"></i>
                    </span>
                </div>

                <div class="px-4 pt-4">
                    <div class="rounded-[14px] bg-[#F0FDF4] p-4">
                        <p class="text-xs font-medium text-[#047857]">Saldo tabungan saat ini</p>
                        <p class="mt-1 break-words text-xl font-bold tabular-nums text-[#0F172A]">Rp {{ number_format($detail['savings_account']?->balance ?? 0, 0, ',', '.') }}</p>
                        <p class="mt-1 text-[11px] text-[#047857]">{{ $detail['savings_account'] ? 'Diperbarui ' . ($detail['savings_account']->updated_at?->format('d M Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
                    </div>
                </div>

                <div class="mt-2 divide-y divide-[#E2E8F0]">
                    @forelse($detail['savings_transactions'] as $transaction)
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

                <div class="border-t border-[#E2E8F0] px-4 py-3">{{ $detail['savings_transactions']->links() }}</div>
            </section>

            {{-- Data siswa --}}
            <section id="profil" data-portal-section class="portal-card scroll-mt-24 overflow-hidden">
                <div class="border-b border-[#E2E8F0] px-4 py-4">
                    <h2 class="text-base font-bold text-[#0F172A]">Data Siswa</h2>
                    <p class="mt-0.5 text-xs text-[#64748B]">Data hanya dapat dibaca. Perubahan dilakukan oleh petugas sekolah.</p>
                </div>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Nama</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">NIS / NISN</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->nis ?? '-' }} / {{ $selected->nisn ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Unit</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->unit?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Kelas</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->schoolClass?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Jenis Kelamin</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->gender?->label() ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Tahun Masuk</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $selected->entry_year ?? '-' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Profil akun wali --}}
            <section aria-label="Profil akun wali" class="portal-card p-4 sm:p-5">
                <h2 class="text-base font-bold text-[#0F172A]">Profil Akun</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Nama wali</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $guardian->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-[#E2E8F0] pb-2">
                        <dt class="text-[#64748B]">Hubungan</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $guardian->relation?->label() ?? 'Wali' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[#64748B]">Siswa terhubung</dt>
                        <dd class="text-right font-semibold text-[#0F172A]">{{ $students->count() }} siswa</dd>
                    </div>
                </dl>
            </section>
        @endif
    @endif

    {{-- Bottom navigation --}}
    @php
        $navItems = [['id' => 'beranda', 'icon' => 'house', 'label' => 'Beranda']];
        if ($selected && $detail) {
            $navItems = array_merge($navItems, [
                ['id' => 'tagihan', 'icon' => 'receipt', 'label' => 'Tagihan'],
                ['id' => 'tabungan', 'icon' => 'wallet', 'label' => 'Tabungan'],
                ['id' => 'riwayat', 'icon' => 'history', 'label' => 'Riwayat'],
                ['id' => 'profil', 'icon' => 'user-round', 'label' => 'Profil'],
            ]);
        }
    @endphp
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
        <div class="mx-auto grid max-w-md gap-1" style="grid-template-columns: repeat({{ count($navItems) }}, minmax(0, 1fr))">
            @foreach($navItems as $item)
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
</div>
