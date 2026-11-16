@php
    use App\Support\Formatter;

    $user = auth()->user();

    $cards = [
        ['label' => 'Data Unit', 'value' => $statistics['units'], 'icon' => 'building-2'],
        ['label' => 'Data Kelas', 'value' => $statistics['classes'], 'icon' => 'layout-grid'],
        ['label' => 'Data Tahun', 'value' => $statistics['years'], 'icon' => 'calendar-days'],
        ['label' => 'Data Siswa', 'value' => $statistics['students'], 'icon' => 'users'],
        ['label' => 'Akun Petugas', 'value' => $statistics['staff'], 'icon' => 'user-cog'],
        ['label' => 'Akun Siswa', 'value' => $statistics['students_account'], 'icon' => 'graduation-cap'],
    ];

    $statusLabels = [
        'unpaid' => 'Belum Dibayar',
        'partial' => 'Dibayar Sebagian',
        'paid' => 'Lunas',
        'overdue' => 'Jatuh Tempo',
    ];
@endphp

<div class="space-y-5">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="page-title">Selamat Datang!</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                {{ $user?->name }}, Anda masuk sebagai
                <span class="font-medium text-[#374151]">{{ $user?->primaryRoleLabel() }}</span>.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-[#64748b]">
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="calendar-days" class="w-3.5 h-3.5"></i>
                {{ now()->locale('id')->translatedFormat('d F Y') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="calendar-range" class="w-3.5 h-3.5"></i>
                Tahun Ajaran
                <span class="font-medium text-[#374151]">{{ $activeYear?->name ?? '-' }}</span>
            </span>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select
                wire:model.live="academicYearId"
                name="academicYearId"
                label="Tahun Ajaran"
                :options="$academicYears->pluck('name', 'id')->all()"
                placeholder="Semua Tahun Ajaran"
            />

            <x-ui.select
                wire:model.live="unitId"
                name="unitId"
                label="Unit Pendidikan"
                :options="$units->pluck('name', 'id')->all()"
                placeholder="Semua Unit"
            />

            <x-ui.select
                wire:model.live="period"
                name="period"
                label="Periode"
                :options="[
                    'today' => 'Hari Ini',
                    'month' => 'Bulan Ini',
                    'year' => 'Tahun Ini',
                    'custom' => 'Rentang Khusus',
                ]"
            />

            @if($period === 'custom')
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari" />
                    <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai" />
                </div>
            @else
                <div class="flex items-end">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Reset Filter</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Statistik --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach($cards as $card)
            <div class="relative overflow-hidden rounded-[6px] border border-[#e5e9f0] bg-white px-4 py-4">
                <p class="text-[12px] font-medium text-[#6b7280]">{{ $card['label'] }}</p>
                <p class="mt-1 text-[22px] font-bold leading-tight text-[#2872c8] tabular">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
                <i data-lucide="{{ $card['icon'] }}" class="absolute -right-2 -bottom-2 w-16 h-16 text-[#dbe8f6]" aria-hidden="true"></i>
            </div>
        @endforeach
    </div>

    {{-- Tabungan Siswa --}}
    @isset($statistics['savings'])
    <x-ui.card title="Tabungan Siswa" subtitle="Ringkasan saldo rekening">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-[12px] text-[#6b7280]">Total Rekening</p>
                <p class="mt-1 text-xl font-bold text-[#2872c8] tabular">{{ number_format($statistics['savings']['accounts'], 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-[12px] text-[#6b7280]">Total Saldo</p>
                <p class="mt-1 text-xl font-bold text-[#2872c8] tabular">{{ Formatter::rupiah($statistics['savings']['balance']) }}</p>
            </div>
        </div>
    </x-ui.card>
    @endisset

    {{-- Charts --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <x-ui.card title="Pemasukan per Bulan" subtitle="Total pembayaran terverifikasi {{ $range[0]->locale('id')->translatedFormat('d M Y') }} — {{ $range[1]->locale('id')->translatedFormat('d M Y') }}" class="xl:col-span-2">
            <div class="h-[260px]" wire:ignore>
                <canvas id="chart-monthly"
                    data-chart="line"
                    data-labels="{{ json_encode($monthly['labels']) }}"
                    data-values="{{ json_encode($monthly['values']) }}"
                ></canvas>
            </div>
        </x-ui.card>

        <x-ui.card title="Status Tagihan" subtitle="Perbandingan status tagihan">
            <div class="h-[260px]" wire:ignore>
                <canvas id="chart-billing-status"
                    data-chart="doughnut"
                    data-labels="{{ json_encode(array_values($statusLabels)) }}"
                    data-values="{{ json_encode(array_values($billingStatus)) }}"
                    data-colors='["#F36C16","#F1C40F","#78B82A","#D83E78"]'
                ></canvas>
            </div>
        </x-ui.card>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <x-ui.card title="Distribusi Pembayaran" subtitle="Berdasarkan kategori pembayaran" class="xl:col-span-2">
            @if($byCategory->isEmpty())
                <x-ui.empty-state
                    title="Belum ada pembayaran"
                    description="Data distribusi akan tampil setelah modul pembayaran memiliki transaksi terverifikasi."
                    icon="chart-pie"
                    class="py-10"
                />
            @else
                <div class="h-[260px]" wire:ignore>
                    <canvas id="chart-category"
                        data-chart="bar"
                        data-labels="{{ json_encode($byCategory->pluck('name')) }}"
                        data-values="{{ json_encode($byCategory->pluck('total')) }}"
                    ></canvas>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Pembayaran Pending" subtitle="Menunggu verifikasi">
            @if($pendingPayments->isEmpty())
                <x-ui.empty-state
                    title="Tidak ada pembayaran pending"
                    description="Semua pembayaran telah diverifikasi."
                    icon="badge-check"
                    class="py-10"
                />
            @else
                <ul class="divide-y divide-[#eef2f7] -mx-1">
                    @foreach($pendingPayments as $payment)
                        <li class="px-1 py-2.5 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium text-[#111827] truncate">{{ $payment->student_name }}</p>
                                <p class="text-[11px] text-[#8994a3]">
                                    {{ $payment->transaction_number }} ·
                                    {{ Formatter::date($payment->paid_at, 'd M Y') }}
                                </p>
                            </div>
                            <span class="text-[13px] font-semibold text-[#2872c8] tabular shrink-0">
                                {{ Formatter::rupiah($payment->amount) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    {{-- Transaksi Terbaru --}}
    <x-ui.card title="Transaksi Terbaru" subtitle="8 transaksi terakhir" :no-padding="true">
        @if($recentPayments->isEmpty())
            <x-ui.empty-state
                title="Belum ada transaksi"
                description="Transaksi pembayaran akan tampil di sini setelah modul pembayaran digunakan."
                icon="receipt"
                class="py-12"
            />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nomor Transaksi</th>
                            <th>Nama Siswa</th>
                            <th class="text-right">Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPayments as $payment)
                            <tr>
                                <td class="whitespace-nowrap">{{ Formatter::date($payment->paid_at) }}</td>
                                <td class="font-mono text-[12px]">{{ $payment->transaction_number }}</td>
                                <td class="font-medium text-[#111827]">{{ $payment->student_name }}</td>
                                <td class="text-right tabular font-medium">{{ Formatter::rupiah($payment->amount) }}</td>
                                <td>
                                    <x-ui.badge :variant="match($payment->status) {
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected', 'cancelled' => 'danger',
                                        default => 'neutral',
                                    }" dot>
                                        {{ ucfirst($payment->status) }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
