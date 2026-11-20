@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Analitik Finansial']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Analitik Finansial & Dashboard Prediktif</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Laporan umur piutang, rasio penagihan per kelas, dan proyeksi penerimaan kas madrasah.
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.select wire:model.live="academic_year_id" name="academic_year_id" label="Tahun Ajaran" placeholder="Semua Tahun Ajaran" :options="$academicYears" />
            <x-ui.select wire:model.live="unit_id" name="unit_id" label="Unit Pendidikan" placeholder="Semua Unit" :options="$units" />
            <x-ui.select wire:model.live="forecastMonths" name="forecastMonths" label="Rentang Proyeksi" :options="[3 => '3 Bulan Terakhir', 6 => '6 Bulan Terakhir', 12 => '12 Bulan Terakhir']" />
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Total Sisa Piutang" :value="\App\Support\Formatter::rupiah($aging['total'])" tone="danger" icon="tag" :hint="$aging['count'] . ' lembar tagihan tertunggak'" />
        <x-ui.stat-card label="Rasio Penagihan Global" :value="$collection['rate'] . '%'" tone="{{ $collection['rate'] >= 75 ? 'success' : 'warning' }}" icon="pie-chart" :hint="'Terbayar: ' . \App\Support\Formatter::rupiah($collection['collected'])" />
        <x-ui.stat-card label="Proyeksi Kas Bulan Depan" :value="\App\Support\Formatter::rupiah($forecast['projection'])" tone="primary" icon="trending-up" hint="Rata-rata bergerak 3 bulan" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <!-- AGING REPORT -->
        <div class="card p-4">
            <h2 class="text-sm font-bold text-[#1e293b] mb-3 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4 text-[#2872c8]"></i>
                Umur Piutang (Accounts Receivable Aging)
            </h2>

            <div class="space-y-3">
                @foreach($aging['buckets'] as $bucket)
                    @php
                        $percentage = $aging['total'] > 0 ? round(($bucket['amount'] / $aging['total']) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-[#334155]">{{ $bucket['label'] }} ({{ $bucket['count'] }} tagihan)</span>
                            <span class="tabular font-semibold text-[#1e293b]">{{ \App\Support\Formatter::rupiah($bucket['amount']) }} ({{ $percentage }}%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-[#f1f5f9] overflow-hidden">
                            <div class="h-full bg-[#2872c8] rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- PROJECTION CHART (HTML/CSS Bar Chart) -->
        <div class="card p-4">
            <h2 class="text-sm font-bold text-[#1e293b] mb-1 flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-4 h-4 text-[#2872c8]"></i>
                Tren Penerimaan & Proyeksi Bulan Depan
            </h2>
            <p class="text-xs text-[#64748b] mb-4">Realisasi kas masuk dari pembayaran tagihan.</p>

            <div class="space-y-3">
                @foreach($forecast['labels'] as $index => $label)
                    @php
                        $val = $forecast['values'][$index] ?? 0;
                        $widthPercent = $maxForecast > 0 ? round(($val / $maxForecast) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-[#475569]">{{ $label }}</span>
                            <span class="tabular font-semibold text-[#1e293b]">{{ \App\Support\Formatter::rupiah($val) }}</span>
                        </div>
                        <div class="w-full h-2.5 rounded-full bg-[#f1f5f9] overflow-hidden">
                            <div class="h-full bg-[#3b82f6] rounded-full" style="width: {{ $widthPercent }}%"></div>
                        </div>
                    </div>
                @endforeach

                <!-- PROJECTION BAR -->
                <div class="pt-2 border-t border-[#eef2f7]">
                    @php
                        $projPercent = $maxForecast > 0 ? round(($forecast['projection'] / $maxForecast) * 100, 1) : 0;
                    @endphp
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-[#2872c8] flex items-center gap-1">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            Proyeksi Bulan Depan
                        </span>
                        <span class="tabular font-bold text-[#2872c8]">{{ \App\Support\Formatter::rupiah($forecast['projection']) }}</span>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-[#eff6ff] overflow-hidden">
                        <div class="h-full bg-[#2563eb] rounded-full border-r-2 border-white" style="width: {{ $projPercent }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- COLLECTION RATE PER CLASS -->
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <div class="px-4 py-3 bg-[#f8fafc] border-b border-[#e5eaf0]">
            <h2 class="text-sm font-bold text-[#1e293b]">Rasio Penagihan Per Kelas (Collection Rate)</h2>
        </div>

        <x-ui.table :empty="count($collection['rows']) === 0" empty-title="Belum Ada Tagihan" empty-description="Data rasio penagihan per kelas akan muncul setelah tagihan dibuat.">
            <x-slot:head>
                <tr>
                    <th>Kelas</th>
                    <th class="w-44 text-right">Total Tagihan</th>
                    <th class="w-44 text-right">Total Terbayar</th>
                    <th class="w-36 text-right">Sisa Piutang</th>
                    <th class="w-48">Progress Penagihan</th>
                </tr>
            </x-slot:head>

            @foreach($collection['rows'] as $row)
                @php $remaining = $row['billed'] - $row['collected']; @endphp
                <tr wire:key="class-coll-{{ $row['class_name'] }}">
                    <td class="font-medium text-[#111827]">{{ $row['class_name'] }}</td>
                    <td class="text-right tabular text-xs">{{ \App\Support\Formatter::rupiah($row['billed']) }}</td>
                    <td class="text-right tabular text-xs text-[#2e7d32] font-medium">{{ \App\Support\Formatter::rupiah($row['collected']) }}</td>
                    <td class="text-right tabular text-xs text-[#c0392b] font-medium">{{ \App\Support\Formatter::rupiah($remaining) }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 rounded-full bg-[#f1f5f9] overflow-hidden">
                                <div class="h-full {{ $row['rate'] >= 80 ? 'bg-[#2e7d32]' : ($row['rate'] >= 50 ? 'bg-[#b9770e]' : 'bg-[#c0392b]') }} rounded-full" style="width: {{ min(100, $row['rate']) }}%"></div>
                            </div>
                            <span class="text-xs font-bold w-12 text-right text-[#334155]">{{ $row['rate'] }}%</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>
</div>
