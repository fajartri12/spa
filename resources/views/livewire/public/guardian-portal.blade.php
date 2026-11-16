@php
    use App\Enums\BillStatus;
    use App\Enums\PaymentStatus;
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6 pb-24 lg:pb-8">
    <header class="flex items-center justify-between rounded-xl border border-[#e1e6ed] bg-white px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf2fc] text-[#2872c8]">
                <i data-lucide="users" aria-hidden="true" class="h-5 w-5"></i>
            </span>
            <div class="min-w-0">
                <p class="truncate text-xs font-bold text-[#172b4d] sm:text-sm">KOMITE MAN 7 JOMBANG</p>
                <p class="text-xs text-[#667085]">Portal Orang Tua / Wali</p>
            </div>
        </div>
        <form method="POST" action="{{ route('public.guardian.logout') }}">
            @csrf
            <button type="submit" aria-label="Keluar dari portal"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#e1e6ed] px-3 text-sm font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]">
                <i data-lucide="log-out" aria-hidden="true" class="h-4 w-4"></i>
                <span class="hidden sm:inline">Keluar</span>
            </button>
        </form>
    </header>

    <div>
        <p class="text-sm font-medium text-[#2872c8]">Beranda <span class="px-1 text-[#98a2b3]">/</span> Portal Orang Tua</p>
        <h1 class="mt-1 text-2xl font-bold text-[#172b4d]">Selamat datang, {{ $guardian->name }}</h1>
        <p class="mt-1 text-sm text-[#667085]">
            Anda terdaftar sebagai <strong class="font-semibold text-[#344054]">{{ $guardian->relation?->label() ?? 'Wali' }}</strong>
            untuk {{ $students->count() }} siswa.
        </p>
    </div>

    @if($students->isEmpty())
        <x-ui.alert type="warning" title="Belum ada siswa tertaut">
            Akun Anda belum terhubung ke data siswa aktif. Hubungi petugas sekolah untuk menautkan data anak Anda.
        </x-ui.alert>
    @else
        <section aria-label="Ringkasan seluruh anak" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="card border border-[#e1e6ed] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#667085]">Jumlah Anak Terdaftar</p>
                <p class="mt-3 text-2xl font-bold tabular-nums text-[#172b4d]">{{ $students->count() }}</p>
                <p class="mt-2 text-xs text-[#667085]">Siswa aktif dalam satu akun</p>
            </div>
            <div class="card border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-amber-800">Total Sisa Tagihan</p>
                <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
                <p class="mt-2 text-xs text-amber-800">Gabungan seluruh anak</p>
            </div>
            <div class="card border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-emerald-800">Total Saldo Tabungan</p>
                <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($totalSavings, 0, ',', '.') }}</p>
                <p class="mt-2 text-xs text-emerald-800">Gabungan seluruh anak</p>
            </div>
        </section>

        <section aria-label="Daftar anak" class="space-y-3">
            <h2 class="text-base font-bold text-[#172b4d]">Pilih Anak</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($overview as $row)
                    @php($child = $row['student'])
                    @php($isActive = $selected && $selected->id === $child->id)
                    <button type="button"
                            wire:click="selectStudent({{ $child->id }})"
                            wire:key="child-{{ $child->id }}"
                            class="card flex w-full items-start gap-3 border p-4 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#2872c8] {{ $isActive ? 'border-[#2872c8] bg-[#f5f9ff]' : 'border-[#e1e6ed] bg-white hover:border-[#b9d3f0]' }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf2fc] text-base font-bold text-[#2872c8]">
                            {{ strtoupper(substr($child->name, 0, 1)) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-[#172b4d]">{{ $child->name }}</span>
                            <span class="mt-0.5 block truncate text-xs text-[#667085]">
                                {{ $child->nis ?? '-' }}
                                @if($child->schoolClass) &middot; {{ $child->schoolClass->name }} @endif
                                @if($child->unit) &middot; {{ $child->unit->name }} @endif
                            </span>
                            <span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                <span class="font-semibold tabular-nums text-amber-700">Sisa Rp {{ number_format($row['outstanding_amount'], 0, ',', '.') }}</span>
                                <span class="font-semibold tabular-nums text-emerald-700">Tabungan Rp {{ number_format($row['savings_balance'], 0, ',', '.') }}</span>
                            </span>
                        </span>
                        @if($isActive)
                            <span class="shrink-0 rounded-full bg-[#2872c8] px-2 py-0.5 text-[10px] font-bold uppercase text-white">Dipilih</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>

        @if($selected && $detail)
            <section aria-label="Detail anak" class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e1e6ed] bg-white px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#667085]">Rincian untuk</p>
                        <p class="truncate text-base font-bold text-[#172b4d]">{{ $selected->name }}</p>
                    </div>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#667085]">
                        <span>NIS <strong class="font-semibold text-[#344054]">{{ $selected->nis ?? '-' }}</strong></span>
                        <span>NISN <strong class="font-semibold text-[#344054]">{{ $selected->nisn ?? '-' }}</strong></span>
                        @if($selected->schoolClass)
                            <span>Kelas <strong class="font-semibold text-[#344054]">{{ $selected->schoolClass->name }}</strong></span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="card border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <p class="text-sm font-medium text-amber-800">Sisa Tagihan</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($detail['outstanding']['amount'], 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-amber-800">{{ $detail['outstanding']['count'] }} tagihan belum lunas</p>
                    </div>
                    <div class="card border border-[#e1e6ed] bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-[#667085]">Saldo Tabungan</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($detail['savings_account']?->balance ?? 0, 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-[#667085]">{{ $detail['savings_account'] ? 'Diperbarui ' . ($detail['savings_account']->updated_at?->format('d/m/Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
                    </div>
                    <div class="card border border-[#e1e6ed] bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-[#667085]">Pembayaran Terakhir</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">
                            @if($detail['latest_payment'])
                                Rp {{ number_format($detail['latest_payment']->amount, 0, ',', '.') }}
                            @else
                                &mdash;
                            @endif
                        </p>
                        <p class="mt-2 text-xs text-[#667085]">{{ $detail['latest_payment'] ? 'Disetujui ' . ($detail['latest_payment']->paid_at?->format('d/m/Y') ?? '-') : 'Belum ada pembayaran disetujui' }}</p>
                    </div>
                </div>

                <div id="tagihan" class="card overflow-hidden rounded-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-5">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 sm:text-lg">Rincian Tagihan</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Daftar tagihan {{ $selected->name }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $detail['bills']->total() }}</span>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        @forelse($detail['bills'] as $bill)
                            @php($billStatus = $bill->status instanceof BillStatus ? $bill->status->value : $bill->status)
                            <article class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? 'Tagihan Sekolah' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $bill->bill_number }} &middot; Jatuh tempo {{ $bill->due_date?->format('d/m/Y') ?? '-' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $billStatus === BillStatus::Paid->value ? 'bg-green-100 text-green-800' : ($billStatus === BillStatus::Cancelled->value ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $billStatus === BillStatus::Paid->value ? 'Lunas' : ($billStatus === BillStatus::Cancelled->value ? 'Dibatalkan' : ($bill->due_date?->isPast() ? 'Jatuh Tempo' : 'Belum Lunas')) }}
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 rounded-xl bg-gray-50 p-3 text-xs">
                                    <span class="text-gray-500">Nominal awal</span>
                                    <span class="text-right font-semibold tabular-nums text-gray-800">Rp {{ number_format($bill->original_amount, 0, ',', '.') }}</span>
                                    <span class="text-gray-500">Potongan</span>
                                    <span class="text-right font-semibold tabular-nums text-gray-800">Rp {{ number_format($bill->discount_amount, 0, ',', '.') }}</span>
                                    <span class="text-gray-500">Sudah dibayar</span>
                                    <span class="text-right font-semibold tabular-nums text-emerald-700">Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</span>
                                    <span class="border-t border-gray-200 pt-1.5 text-gray-600">Sisa tagihan</span>
                                    <span class="border-t border-gray-200 pt-1.5 text-right font-bold tabular-nums text-red-600">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</span>
                                </div>
                                @if($detail['intents']->has($bill->id))
                                    @include('livewire.public.partials.pay-code', ['intent' => $detail['intents']->get($bill->id)])
                                @endif
                            </article>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada tagihan.</p>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nomor / Jatuh Tempo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tagihan</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Nominal</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Dibayar</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Sisa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kode Bayar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($detail['bills'] as $bill)
                                    @php($billStatus = $bill->status instanceof BillStatus ? $bill->status->value : $bill->status)
                                    <tr wire:key="bill-{{ $bill->id }}">
                                        <td class="px-4 py-3 text-sm">
                                            <span class="block font-medium text-gray-900">{{ $bill->bill_number }}</span>
                                            <span class="text-xs text-gray-500">{{ $bill->due_date?->format('d/m/Y') ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? 'Tagihan Sekolah' }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700">Rp {{ number_format($bill->final_amount, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-emerald-700">Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-red-600">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($detail['intents']->has($bill->id))
                                                @php($intent = $detail['intents']->get($bill->id))
                                                <span class="block font-mono text-xs font-semibold text-gray-900">{{ $intent->pay_code ?? 'QRIS' }}</span>
                                                <span class="block text-[11px] text-gray-500">{{ $intent->provider->label() }} &middot; {{ $intent->channelLabel() }}</span>
                                                <span class="block text-[11px] text-gray-400">s.d. {{ $intent->expires_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</span>
                                            @else
                                                <span class="text-xs text-gray-400">Belum diterbitkan</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $billStatus === BillStatus::Paid->value ? 'bg-green-100 text-green-800' : ($billStatus === BillStatus::Cancelled->value ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">
                                                {{ $billStatus === BillStatus::Paid->value ? 'Lunas' : ($billStatus === BillStatus::Cancelled->value ? 'Dibatalkan' : ($bill->due_date?->isPast() ? 'Jatuh Tempo' : 'Belum Lunas')) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada tagihan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">{{ $detail['bills']->links() }}</div>
                </div>

                <div id="pembayaran" class="card overflow-hidden rounded-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-5">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 sm:text-lg">Riwayat Pembayaran</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Status transaksi dan unduh kwitansi</p>
                        </div>
                        <i data-lucide="receipt" aria-hidden="true" class="h-5 w-5 text-[#2872c8]"></i>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        @forelse($detail['payments'] as $payment)
                            @php($paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status)
                            <article class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ $payment->paymentCategory?->name ?? 'Pembayaran Sekolah' }}</p>
                                        <p class="mt-1 break-all text-xs text-gray-500">{{ $payment->transaction_number }} &middot; {{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $paymentStatus === PaymentStatus::Approved->value ? 'bg-green-100 text-green-800' : ($paymentStatus === PaymentStatus::Pending->value ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                        {{ match ($paymentStatus) {
                                            PaymentStatus::Approved->value => 'Disetujui',
                                            PaymentStatus::Pending->value => 'Menunggu',
                                            PaymentStatus::Rejected->value => 'Ditolak',
                                            PaymentStatus::Cancelled->value => 'Dibatalkan',
                                            default => ucfirst((string) $paymentStatus),
                                        } }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-base font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                                    @if($paymentStatus === PaymentStatus::Approved->value)
                                        <a href="{{ route('public.receipt', $payment) }}"
                                           class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#e1e6ed] px-3 text-xs font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]">
                                            <i data-lucide="download" aria-hidden="true" class="h-4 w-4"></i>Kwitansi
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada riwayat pembayaran.</p>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tanggal / Nomor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kategori</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Nominal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Kwitansi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($detail['payments'] as $payment)
                                    @php($paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status)
                                    <tr wire:key="payment-{{ $payment->id }}">
                                        <td class="px-4 py-3 text-sm">
                                            <span class="block font-medium text-gray-900">{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</span>
                                            <span class="text-xs text-gray-500">{{ $payment->transaction_number }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ $payment->paymentCategory?->name ?? '-' }}
                                            <span class="block text-xs text-gray-500">{{ $payment->academicYear?->name ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentStatus === PaymentStatus::Approved->value ? 'bg-green-100 text-green-800' : ($paymentStatus === PaymentStatus::Pending->value ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                                {{ match ($paymentStatus) {
                                                    PaymentStatus::Approved->value => 'Disetujui',
                                                    PaymentStatus::Pending->value => 'Menunggu',
                                                    PaymentStatus::Rejected->value => 'Ditolak',
                                                    PaymentStatus::Cancelled->value => 'Dibatalkan',
                                                    default => ucfirst((string) $paymentStatus),
                                                } }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($paymentStatus === PaymentStatus::Approved->value)
                                                <a href="{{ route('public.receipt', $payment) }}"
                                                   class="inline-flex items-center gap-1.5 rounded-lg border border-[#e1e6ed] px-2.5 py-1.5 text-xs font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]">
                                                    <i data-lucide="download" aria-hidden="true" class="h-3.5 w-3.5"></i>Unduh
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-400">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada riwayat pembayaran.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">{{ $detail['payments']->links() }}</div>
                </div>

                <div id="tabungan" class="card overflow-hidden rounded-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Riwayat Tabungan</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Transaksi terbaru {{ $selected->name }}</p>
                        </div>
                        <i data-lucide="wallet" aria-hidden="true" class="h-5 w-5 text-emerald-600"></i>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($detail['savings_transactions'] as $transaction)
                            <article class="flex items-center justify-between gap-3 p-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $transaction->type === 'deposit' ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-600' }}">
                                        <i data-lucide="{{ $transaction->type === 'deposit' ? 'arrow-down-left' : 'arrow-up-right' }}" aria-hidden="true" class="h-5 w-5"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ $transaction->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ $transaction->transacted_at?->format('d/m/Y H:i') }} &middot; {{ $transaction->transaction_number }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-bold {{ $transaction->type === 'deposit' ? 'text-emerald-700' : 'text-red-700' }}">
                                        {{ $transaction->type === 'deposit' ? '+' : '-' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-gray-500">Saldo Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada transaksi tabungan.</p>
                        @endforelse
                    </div>
                    <div class="border-t border-gray-100 px-4 py-3">{{ $detail['savings_transactions']->links() }}</div>
                </div>

                <div id="profil" class="card overflow-hidden rounded-2xl">
                    <div class="border-b border-gray-100 px-4 py-4">
                        <h2 class="text-base font-bold text-gray-900">Data Siswa</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Data hanya dapat dibaca. Perubahan dilakukan oleh petugas sekolah.</p>
                    </div>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">Nama</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">NIS / NISN</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->nis ?? '-' }} / {{ $selected->nisn ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">Unit</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->unit?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">Kelas</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->schoolClass?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">Jenis Kelamin</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->gender?->label() ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                            <dt class="text-gray-500">Tahun Masuk</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ $selected->entry_year ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        @endif
    @endif
</div>
