<div id="beranda" class="mx-auto w-full max-w-7xl space-y-6 pb-24 lg:space-y-8 lg:pb-8">
        <header class="flex items-center justify-between rounded-xl border border-[#e1e6ed] bg-white px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf2fc] text-[#2872c8]"><i data-lucide="landmark" aria-hidden="true" class="h-5 w-5"></i></span>            <div class="min-w-0"><p class="truncate text-xs font-bold text-[#172b4d] sm:text-sm">KOMITE MAN 7 JOMBANG</p><p class="text-xs text-[#667085]">Portal Siswa</p></div></div>
            <form method="POST" action="{{ route('public.student.logout') }}">@csrf<button type="submit" aria-label="Keluar dari portal" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#e1e6ed] px-3 text-sm font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]"><i data-lucide="log-out" aria-hidden="true" class="h-4 w-4"></i><span class="hidden sm:inline">Keluar</span></button></form>
        </header>
        <div><p class="text-sm font-medium text-[#2872c8]">Beranda <span class="px-1 text-[#98a2b3]">/</span> Portal Siswa</p><h1 class="mt-1 text-2xl font-bold text-[#172b4d]">Selamat Datang</h1><p class="mt-1 text-sm text-[#667085]">Berikut informasi keuangan siswa yang dapat Anda lihat.</p></div>
        <section aria-label="Profil siswa" class="card flex flex-col gap-4 border border-[#e1e6ed] bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:p-6">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#eaf2fc] text-xl font-bold text-[#2872c8]">{{ strtoupper(substr($student->name, 0, 1)) }}</div>
            <div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase text-[#667085]">Profil Siswa</p><h2 class="mt-1 break-words text-lg font-bold text-[#172b4d]">{{ $student->name }}</h2><div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-[#667085]"><span>NIS <strong class="font-semibold text-[#344054]">{{ $student->nis ?? '-' }}</strong></span><span>NISN <strong class="font-semibold text-[#344054]">{{ $student->nisn ?? '-' }}</strong></span>@if($student->schoolClass)<span>Kelas <strong class="font-semibold text-[#344054]">{{ $student->schoolClass->name }}</strong></span>@endif</div></div>
        </section>
        <section aria-label="Kontak orang tua" class="card border border-[#e1e6ed] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf2fc] text-[#2872c8]"><i data-lucide="users" aria-hidden="true" class="h-5 w-5"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase text-[#667085]">Kontak Orang Tua / Wali</p>
                    <p class="mt-1 break-words text-sm font-bold text-[#172b4d]">{{ $student->parent_name ?? 'Belum diisi' }}</p>
                    <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm text-[#667085]">
                        <span>{{ $student->parent_relation ?? 'Wali' }}</span>
                        @if($student->parent_phone)<span>HP <strong class="font-semibold text-[#344054]">{{ $student->parent_phone }}</strong></span>@endif
                    </div>
                    <p class="mt-2 text-xs text-[#667085]">Data ini hanya dapat dibaca. Perubahan dilakukan oleh petugas sekolah.</p>
                </div>
            </div>
        </section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="card border border-[#e1e6ed] bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-[#667085]">Saldo Tabungan</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($savingsAccount?->balance ?? 0, 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-[#667085]">{{ $savingsAccount ? 'Diperbarui ' . ($savingsAccount->updated_at?->format('d/m/Y') ?? '-') : 'Belum memiliki rekening tabungan' }}</p>
                    </div>
                    <div class="card border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <p class="text-sm font-medium text-amber-800">Sisa Tagihan</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($outstanding['amount'], 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-amber-800">{{ $outstanding['count'] }} tagihan belum lunas</p>
                    </div>
                    <div class="card border border-[#e1e6ed] bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                        <p class="text-sm font-medium text-[#667085]">Pembayaran Terakhir</p>
                        <p class="mt-3 break-words text-2xl font-bold tabular-nums text-[#172b4d]">@if($latestApprovedPayment)Rp {{ number_format($latestApprovedPayment->amount, 0, ',', '.') }}@else—@endif</p>
                        <p class="mt-2 text-xs text-[#667085]">{{ $latestApprovedPayment ? 'Disetujui · ' . ($latestApprovedPayment->paid_at?->format('d/m/Y') ?? '-') : 'Belum ada pembayaran disetujui' }}</p>
                    </div>
                </div>

        <div class="grid grid-cols-1 gap-5 lg:gap-6">
            <div id="tagihan" class="card overflow-hidden rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-5">
                            <div>
                                <h2 class="text-base font-bold text-gray-900 sm:text-lg">Rincian Tagihan</h2>
                                <p class="mt-0.5 text-xs text-gray-500">Daftar tagihan sekolah Anda</p>
                            </div>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $bills->total() }}</span>
                        </div>
                        <div class="divide-y divide-gray-100 md:hidden">
                            @forelse($bills as $bill)
                                @php($billStatus = $bill->status instanceof \App\Enums\BillStatus ? $bill->status->value : $bill->status)
                                <article class="p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? 'Tagihan Sekolah' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $bill->bill_number }} <span class="px-1">·</span> Jatuh tempo {{ $bill->due_date?->format('d/m/Y') ?? '-' }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $billStatus === 'paid' ? 'bg-green-100 text-green-800' : ($billStatus === 'cancelled' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">{{ $billStatus === 'paid' ? 'Lunas' : ($billStatus === 'cancelled' ? 'Dibatalkan' : ($bill->due_date?->isPast() ? 'Jatuh Tempo' : 'Belum Lunas')) }}</span>
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
                                    @if($intents->has($bill->id))
                                        @include('livewire.public.partials.pay-code', ['intent' => $intents->get($bill->id)])
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
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Bayar</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($bills as $bill)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $bill->bill_number }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $bill->bill?->title ?? $bill->bill?->paymentCategory?->name ?? '-' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $bill->due_date?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 text-sm font-semibold tabular-nums text-gray-900">Rp {{ number_format($bill->final_amount, 0, ',', '.') }}<span class="block text-xs font-normal text-gray-400">Dibayar Rp {{ number_format($bill->paid_amount, 0, ',', '.') }}</span></td>
                                    <td class="px-4 py-2 text-sm text-red-600 font-semibold tabular-nums">Rp {{ number_format($bill->remaining_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        @if($intents->has($bill->id))
                                            @php($intent = $intents->get($bill->id))
                                            <span class="block font-mono text-xs font-semibold text-gray-900">{{ $intent->pay_code ?? 'QRIS' }}</span>
                                            <span class="block text-[11px] text-gray-500">{{ $intent->provider->label() }} &middot; {{ $intent->channelLabel() }}</span>
                                            <span class="block text-[11px] text-gray-400">s.d. {{ $intent->expires_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</span>
                                        @else
                                            <span class="text-xs text-gray-400">Belum diterbitkan</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        @php($billStatus = $bill->status instanceof \App\Enums\BillStatus ? $bill->status->value : $bill->status)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $billStatus === 'paid' ? 'bg-green-100 text-green-800' : ($billStatus === 'cancelled' ? 'bg-gray-100 text-gray-700' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $billStatus === 'paid' ? 'Lunas' : ($billStatus === 'cancelled' ? 'Dibatalkan' : ($bill->due_date?->isPast() ? 'Jatuh Tempo' : 'Belum Lunas')) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                    {{ $bills->links() }}
                </div>
            </div>

            <div id="pembayaran" class="card overflow-hidden rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-5">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 sm:text-lg">Riwayat Pembayaran</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Status transaksi dan alokasi pembayaran</p>
                    </div>
                    <i data-lucide="receipt" aria-hidden="true" class="h-5 w-5 text-[#2872c8]"></i>
                </div>
                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse($payments as $payment)
                        @php($paymentStatus = $payment->status instanceof \App\Enums\PaymentStatus ? $payment->status->value : $payment->status)
                        <article class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-900">{{ $payment->paymentCategory?->name ?? 'Pembayaran Sekolah' }}</p><p class="mt-1 break-all text-xs text-gray-500">{{ $payment->transaction_number }} · {{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</p></div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $paymentStatus === 'approved' ? 'bg-green-100 text-green-800' : ($paymentStatus === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">{{ match($paymentStatus) { 'approved' => 'Disetujui', 'pending' => 'Menunggu', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', default => ucfirst((string) $paymentStatus) } }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-base font-bold tabular-nums text-[#172b4d]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                                @if($paymentStatus === 'approved')
                                    <a href="{{ route('public.receipt', $payment) }}"
                                       class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border border-[#e1e6ed] px-3 text-xs font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]">
                                        <i data-lucide="download" aria-hidden="true" class="h-4 w-4"></i>Kwitansi
                                    </a>
                                @endif
                            </div>
                            @if($payment->allocations->isNotEmpty())<div class="space-y-1 border-t border-gray-100 pt-2">@foreach($payment->allocations as $allocation)<p class="flex justify-between gap-3 text-xs text-gray-500"><span class="min-w-0 truncate">{{ $allocation->studentBill?->bill_number ?? 'Alokasi tagihan' }}</span><span class="shrink-0 font-medium tabular-nums text-gray-700">Rp {{ number_format($allocation->amount, 0, ',', '.') }}</span></p>@endforeach</div>@endif
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada riwayat pembayaran.</p>
                    @endforelse
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tanggal / Nomor</th><th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kategori / Tahun Ajaran</th><th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Nominal</th><th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Alokasi</th><th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th><th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Kwitansi</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($payments as $payment)
                                @php($paymentStatus = $payment->status instanceof \App\Enums\PaymentStatus ? $payment->status->value : $payment->status)
                                <tr>
                                    <td class="px-4 py-3 text-sm"><span class="block font-medium text-gray-900">{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</span><span class="text-xs text-gray-500">{{ $payment->transaction_number }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $payment->paymentCategory?->name ?? '-' }}<span class="block text-xs text-gray-500">{{ $payment->academicYear?->name ?? '-' }}</span></td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">@forelse($payment->allocations as $allocation)<span class="block">{{ $allocation->studentBill?->bill_number ?? 'Tagihan' }}: Rp {{ number_format($allocation->amount, 0, ',', '.') }}</span>@empty<span class="text-gray-400">-</span>@endforelse</td>
                                    <td class="px-4 py-3 text-sm"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentStatus === 'approved' ? 'bg-green-100 text-green-800' : ($paymentStatus === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">{{ match($paymentStatus) { 'approved' => 'Disetujui', 'pending' => 'Menunggu', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', default => ucfirst((string) $paymentStatus) } }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        @if($paymentStatus === 'approved')
                                            <a href="{{ route('public.receipt', $payment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#e1e6ed] px-2.5 py-1.5 text-xs font-semibold text-[#344054] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]">
                                                <i data-lucide="download" aria-hidden="true" class="h-3.5 w-3.5"></i>Unduh
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada riwayat pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">{{ $payments->links() }}</div>
            </div>

            <div id="tabungan" class="card overflow-hidden rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Riwayat Tabungan</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Transaksi terbaru</p>
                    </div>
                    <i data-lucide="wallet" aria-hidden="true" class="h-5 w-5 text-emerald-600"></i>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($savingsTransactions as $transaction)
                        <article class="flex items-center justify-between gap-3 p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $transaction->type === 'deposit' ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-600' }}">
                                    <i data-lucide="{{ $transaction->type === 'deposit' ? 'arrow-down-left' : 'arrow-up-right' }}" aria-hidden="true" class="h-5 w-5"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $transaction->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $transaction->transacted_at?->format('d/m/Y H:i') }} · {{ $transaction->transaction_number }}</p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right"><p class="text-sm font-bold {{ $transaction->type === 'deposit' ? 'text-emerald-700' : 'text-red-700' }}">{{ $transaction->type === 'deposit' ? '+' : '-' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}</p><p class="mt-1 text-[11px] text-gray-500">Saldo Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</p></div>
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada transaksi tabungan.</p>
                    @endforelse
                </div>
                <div class="border-t border-gray-100 px-4 py-3">{{ $savingsTransactions->links() }}</div>
            </div>
        </div>

    <nav aria-label="Navigasi portal" class="fixed inset-x-0 bottom-0 z-20 border-t border-[#e1e6ed] bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur lg:hidden">
        <div class="mx-auto grid max-w-md grid-cols-3 gap-2"><a href="#beranda" class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg text-xs font-medium text-[#667085] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]"><i data-lucide="house" aria-hidden="true" class="h-4 w-4"></i>Beranda</a><a href="#tagihan" class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg text-xs font-medium text-[#667085] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]"><i data-lucide="receipt" aria-hidden="true" class="h-4 w-4"></i>Tagihan</a>        <a href="#pembayaran" class="flex min-h-12 items-center justify-center rounded-lg text-xs font-medium text-[#667085] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]"><i data-lucide="receipt" aria-hidden="true" class="h-4 w-4"></i>Pembayaran</a>
        <a href="#tabungan" class="flex min-h-12 items-center justify-center gap-1 rounded-lg text-xs font-medium text-[#667085] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#2872c8]"><i data-lucide="wallet" aria-hidden="true" class="h-4 w-4"></i>Tabungan</a></div>
    </nav>
</div>