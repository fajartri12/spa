@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengaturan Umum']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengaturan Umum</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Identitas madrasah, format tampilan, dan penomoran dokumen aplikasi.
            </p>
        </div>

        @can('settings.update')
            <x-ui.button variant="primary" type="submit" form="setting-form" loading="save">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Simpan Pengaturan</span>
            </x-ui.button>
        @endcan
    </div>

    <form id="setting-form" wire:submit="save" class="grid gap-4 lg:grid-cols-3" novalidate>
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card title="Identitas Aplikasi" subtitle="Nama yang tampil pada header, judul halaman, dan footer.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="app_name" name="app_name" label="Nama Aplikasi" required icon="app-window" />
                    <x-ui.input wire:model="app_subtitle" name="app_subtitle" label="Sub Judul Aplikasi" icon="type" />
                    <x-ui.input wire:model="school_name" name="school_name" label="Nama Sekolah" required icon="school" />
                    <x-ui.input wire:model="school_phone" name="school_phone" label="Telepon Sekolah" icon="phone" />
                    <x-ui.input wire:model="school_email" name="school_email" type="email" label="Email Sekolah" icon="mail" />
                    <x-ui.input wire:model="footer_text" name="footer_text" label="Teks Footer" required icon="panel-bottom" />
                </div>

                <div class="mt-4">
                    <x-ui.textarea wire:model="school_address" name="school_address" label="Alamat Sekolah" rows="3" placeholder="Alamat lengkap madrasah." />
                </div>
            </x-ui.card>

            <x-ui.card title="Penandatangan" subtitle="Nama yang dicetak pada kwitansi dan laporan.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="headmaster_name" name="headmaster_name" label="Nama Kepala Sekolah" icon="user-round" />
                    <x-ui.input wire:model="treasurer_name" name="treasurer_name" label="Nama Bendahara" icon="user-round" />
                </div>
            </x-ui.card>

            <x-ui.card title="Format & Penomoran" subtitle="Preferensi tampilan angka, tanggal, dan prefix nomor dokumen.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="currency_symbol" name="currency_symbol" label="Simbol Mata Uang" required icon="circle-dollar-sign" />
                    <x-ui.input wire:model="date_format" name="date_format" label="Format Tanggal" required hint="Contoh: d F Y" icon="calendar" />
                    <x-ui.input wire:model="rows_per_page" name="rows_per_page" type="number" label="Baris per Halaman" required icon="rows-3" />
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.input wire:model="payment_prefix" name="payment_prefix" label="Prefix Transaksi" required icon="receipt" />
                        <x-ui.input wire:model="bill_prefix" name="bill_prefix" label="Prefix Tagihan" required icon="tags" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Fitur Aplikasi" subtitle="Atur fitur yang ditampilkan pada menu dan dashboard admin.">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-[#eff6ff] text-[#2872c8]">
                            <i data-lucide="piggy-bank" class="h-5 w-5" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="text-[14px] font-semibold text-[#111827]">Tabungan Siswa</h2>
                            <p class="mt-1 text-[12px] text-[#64748b]">Tampilkan menu Tabungan dan ringkasannya pada dashboard.</p>
                        </div>
                    </div>
                    @can('settings.update')
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-3">
                            <span class="text-[12px] font-medium text-[#475569]">{{ $studentSavingsEnabled ? 'Aktif' : 'Nonaktif' }}</span>
                            <input type="checkbox" wire:model.live="studentSavingsEnabled" class="peer sr-only" aria-label="Aktifkan fitur Tabungan Siswa">
                            <span class="relative h-6 w-11 rounded-full bg-[#cbd5e1] transition peer-checked:bg-[#2872c8] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#2872c8] after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-5"></span>
                        </label>
                    @else
                        <span class="text-[12px] font-medium text-[#475569]">{{ $studentSavingsEnabled ? 'Aktif' : 'Nonaktif' }}</span>
                    @endcan
                </div>
                <div wire:loading wire:target="studentSavingsEnabled" class="mt-3 text-[12px] text-[#64748b]" role="status">Menyimpan perubahan...</div>
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Ringkasan">
                <dl class="space-y-3 text-[13px]">
                    <div>
                        <dt class="text-[#8994a3]">Aplikasi</dt>
                        <dd class="font-medium text-[#111827]">{{ $app_name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#8994a3]">Sekolah</dt>
                        <dd class="font-medium text-[#111827]">{{ $school_name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#8994a3]">Contoh Nominal</dt>
                        <dd class="font-medium text-[#111827] tabular">{{ $currency_symbol }} 1.500.000</dd>
                    </div>
                    <div>
                        <dt class="text-[#8994a3]">Contoh Tanggal</dt>
                        <dd class="font-medium text-[#111827]">{{ now()->translatedFormat($date_format ?: 'd F Y') }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.alert type="info" title="Catatan">
                Perubahan berlaku setelah disimpan. Nilai kosong pada penandatangan akan menghasilkan area tanda tangan kosong pada cetakan.
            </x-ui.alert>
        </div>
    </form>
</div>
