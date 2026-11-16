@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Cetak Laporan']]" />@endsection
<div class="mx-auto max-w-5xl space-y-5">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#2872C8] via-[#317fd5] to-[#1659aa] px-6 py-7 text-white shadow-lg shadow-blue-900/10 sm:px-8">
        <div class="relative z-10">
            <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25"><i data-lucide="file-text" class="h-6 w-6"></i></div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Cetak Laporan</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">Pilih jenis laporan dan periode untuk menampilkan data keuangan sekolah.</p>
        </div>
        <i data-lucide="chart-column" class="absolute -bottom-8 -right-3 h-48 w-48 rotate-[-12deg] text-white/10"></i>
    </div>

    <div class="card overflow-hidden border-0 shadow-md">
        <div class="border-b border-[#E1E6ED] px-5 py-5 sm:px-8">
            <h2 class="text-lg font-bold uppercase tracking-wide text-[#12233F]">Cetak Laporan</h2>
            <p class="mt-1 text-sm text-[#8994A3]">Atur parameter laporan yang ingin Anda cetak.</p>
            <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#F3F7FD] px-3 py-1.5 text-xs font-semibold text-[#2872C8]">
                <i data-lucide="file-text" class="h-3.5 w-3.5"></i>{{ $reportTitle }}
            </p>
        </div>
        <div class="space-y-7 p-5 sm:p-8">
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-[#12233F]">Rekap Laporan</legend>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    @php $tabs = [
                        'tagihan' => ['Tagihan', 'Laporan Tagihan & Tunggakan', 'tag', 'bg-rose-50 text-rose-600'],
                        'transaksi' => ['Transaksi', 'Laporan Penerimaan Pembayaran', 'circle-dollar-sign', 'bg-emerald-50 text-emerald-600'],
                        'arus_kas' => ['Arus Kas', 'Laporan Arus Kas', 'arrow-left-right', 'bg-cyan-50 text-cyan-600'],
                        'tabungan' => ['Tabungan', 'Laporan Tabungan Siswa', 'wallet', 'bg-amber-50 text-amber-600'],
                    ]; @endphp
                    @foreach($tabs as $value => [$label, $desc, $icon, $iconClass])
                        <label class="group relative flex min-h-[76px] cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition hover:-translate-y-0.5 hover:border-[#2872C8] hover:shadow-sm {{ $category === $value ? 'border-[#2872C8] bg-[#F3F7FD] ring-2 ring-[#2872C8]/15' : 'border-[#E1E6ED] bg-white' }}">
                            <input type="radio" wire:model.live="category" value="{{ $value }}" class="sr-only">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconClass }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold text-[#12233F]">{{ $label }}</span>
                                <span class="text-[11px] text-[#8994A3]">{{ $desc }}</span>
                            </div>
                            @if($category === $value)<i data-lucide="check-circle-2" class="ml-auto h-5 w-5 text-[#2872C8]"></i>@endif
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-5 lg:grid-cols-2">
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-sm font-medium text-[#374151]">Dari Periode</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.select wire:model.live="fromMonth" name="fromMonth" label="Bulan" :options="$months->all()" />
                        <x-ui.select wire:model.live="fromYear" name="fromYear" label="Tahun" :options="$years->all()" />
                    </div>
                </fieldset>
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-sm font-medium text-[#374151]">Sampai Periode</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.select wire:model.live="toMonth" name="toMonth" label="Bulan" :options="$months->all()" />
                        <x-ui.select wire:model.live="toYear" name="toYear" label="Tahun" :options="$years->all()" />
                    </div>
                </fieldset>
            </div>

            <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                <x-ui.select wire:model.live="rekapType" name="rekapType" label="Jenis Rekap" :options="['daftar_tagihan' => 'Daftar Tagihan', 'rekap_transaksi' => 'Rekap Transaksi', 'rekap_kategori' => 'Rekap per Kategori']" class="min-w-0" />
                <x-ui.select wire:model.live="academicYearId" name="academicYearId" label="Tahun Ajaran" :options="$academicYears->pluck('name', 'id')->all()" placeholder="Semua Tahun Ajaran" class="min-w-0" />
                <x-ui.select wire:model.live="billStatus" name="billStatus" label="Status Tagihan" :options="['all' => 'Semua Status', 'paid' => 'Lunas', 'partial' => 'Sebagian', 'unpaid' => 'Belum Dibayar']" class="min-w-0" />
                <x-ui.select wire:model.live="categoryId" name="categoryId" label="Kategori Tagihan" :options="$paymentCategories->pluck('name', 'id')->prepend('Semua Kategori', 'all')->all()" class="min-w-0" />
                <x-ui.select wire:model.live="unitId" name="unitId" label="Pilihan Unit" :options="$units->pluck('name', 'id')->prepend('Semua Unit', 'all')->all()" class="min-w-0" />
            </div>

            <div class="flex flex-col gap-3 border-t border-[#E1E6ED] pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('reports.index') }}" class="btn btn-secondary justify-center">Batal</a>
                <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-primary w-full justify-center sm:w-auto sm:min-w-56">
                    <i data-lucide="check-circle" class="h-4 w-4"></i><span>Tampilkan Laporan</span>
                </a>
            </div>
        </div>
    </div>
</div>
