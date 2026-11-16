@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengaturan Sistem']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengaturan Sistem</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Konfigurasi detail aplikasi, batas nominal tagihan &amp; pembayaran, dan aturan transaksi.
            </p>
        </div>

        @can('settings.update')
            <x-ui.button variant="primary" type="submit" form="system-form" loading="save">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Simpan Pengaturan</span>
            </x-ui.button>
        @endcan
    </div>

    <form id="system-form" wire:submit="save" class="grid gap-4 lg:grid-cols-3" novalidate>
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card title="Detail Aplikasi" subtitle="Identitas aplikasi dan tautan akses dasbor.">
                <div class="space-y-4">
                    <x-ui.input
                        wire:model="app_name"
                        name="app_name"
                        label="Nama Detail Aplikasi"
                        required
                        icon="app-window"
                        placeholder="Aplikasi Pengelolaan Pembayaran Tagihan"
                    />

                    <x-ui.input
                        wire:model="app_url"
                        name="app_url"
                        type="url"
                        label="Alamat URL Dasbor"
                        required
                        icon="globe"
                        placeholder="http://127.0.0.1:8123"
                    />

                    <x-ui.alert type="info" title="Kata Sandi Portal Siswa">
                        Setiap siswa memiliki kata sandi portal sendiri. Terbitkan lewat tombol
                        &ldquo;Reset Password Portal&rdquo; pada halaman Data Siswa.
                    </x-ui.alert>
                </div>
            </x-ui.card>

            <x-ui.card title="Tagihan & Pembayaran" subtitle="Biaya layanan, item bulanan, jatuh tempo, dan batasan nominal transaksi.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="service_fee" name="service_fee" type="number" step="100" label="Biaya Layanan" prefix="Rp" placeholder="0" />
                    <x-ui.input wire:model="max_bill_items" name="max_bill_items" type="number" min="1" max="6" label="Jumlah Item Tagihan Perbulan (Maksimal 6)" icon="layers" placeholder="6" />
                    <x-ui.input wire:model="bill_due_day" name="bill_due_day" type="number" min="1" max="28" label="Tanggal Jatuh Tempo Tagihan" icon="calendar" placeholder="10" hint="Tanggal ini berlaku setiap bulan." />

                    <x-ui.input wire:model="min_category_amount" name="min_category_amount" type="number" label="Batas Minimal Biaya Kategori Tagihan" prefix="Rp" placeholder="0" />
                    <x-ui.input wire:model="max_category_amount" name="max_category_amount" type="number" label="Batas Maksimal Biaya Kategori Tagihan" prefix="Rp" placeholder="10000000" />

                    <x-ui.input wire:model="min_payment_amount" name="min_payment_amount" type="number" label="Batas Minimal Nominal Pembayaran Tagihan" prefix="Rp" placeholder="1000" />
                    <x-ui.input wire:model="min_installment_amount" name="min_installment_amount" type="number" label="Batas Minimal Nominal Cicilan Tagihan" prefix="Rp" placeholder="1000" />

                    <x-ui.input wire:model="min_savings_deposit" name="min_savings_deposit" type="number" label="Batas Minimal Setoran Saldo Tabungan" prefix="Rp" placeholder="1000" />
                    <x-ui.input wire:model="max_savings_deposit" name="max_savings_deposit" type="number" label="Batas Maksimal Setoran Saldo Tabungan" prefix="Rp" placeholder="1000000" />
                    <div class="sm:col-span-2">
                        <x-ui.select wire:model="savings_cash_account_id" name="savings_cash_account_id" label="Akun Kas/Bank untuk Mutasi Tabungan" placeholder="-- Otomatis (kas pertama aktif) --" :options="$cashAccounts" hint="Setoran dicatat sebagai kas masuk dan penarikan yang disetujui sebagai kas keluar pada akun ini." />
                    </div>
                    <div>
                        <x-ui.select wire:model="ppdb_cash_account_id" name="ppdb_cash_account_id" label="Akun Kas/Bank PPDB" placeholder="-- Otomatis (kas pertama aktif) --" :options="$cashAccounts" hint="Penerimaan tagihan PPDB dicatat ke akun ini." />
                    </div>
                    <div>
                        <x-ui.select wire:model="ppdb_revenue_account_id" name="ppdb_revenue_account_id" label="Akun Pendapatan PPDB" placeholder="-- Otomatis (pendapatan pertama) --" :options="$ppdbRevenueAccounts" hint="Akun kredit pada jurnal penerimaan PPDB." />
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Kwitansi Pembayaran" subtitle="Ringkasan format & aturan cetak bukti pembayaran.">
                <dl class="space-y-3 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Jatuh Tempo Default</dt>
                        <dd class="font-medium text-[#111827]">Tanggal {{ $bill_due_day ?: 10 }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Biaya Layanan</dt>
                        <dd class="font-medium text-[#111827] tabular">{{ $currencySymbol }} {{ number_format($service_fee, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Maksimal Item Bulanan</dt>
                        <dd class="font-medium text-[#111827]">{{ $max_bill_items }} item</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Aplikasi</dt>
                        <dd class="font-medium text-[#111827] text-right">{{ $app_name ?: '-' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Batasan Transaksi">
                <dl class="space-y-3 text-[13px]">
                    <div>
                        <dt class="text-[#8994a3]">Biaya Kategori Tagihan</dt>
                        <dd class="font-medium text-[#111827] tabular">
                            {{ $currencySymbol }} {{ number_format($min_category_amount, 0, ',', '.') }} &ndash; {{ $currencySymbol }} {{ number_format($max_category_amount, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#8994a3]">Min. Pembayaran / Cicilan</dt>
                        <dd class="font-medium text-[#111827] tabular">
                            {{ $currencySymbol }} {{ number_format($min_payment_amount, 0, ',', '.') }} / {{ $currencySymbol }} {{ number_format($min_installment_amount, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#8994a3]">Setoran Saldo Tabungan</dt>
                        <dd class="font-medium text-[#111827] tabular">
                            {{ $currencySymbol }} {{ number_format($min_savings_deposit, 0, ',', '.') }} &ndash; {{ $currencySymbol }} {{ number_format($max_savings_deposit, 0, ',', '.') }}
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.alert type="info" title="Catatan">
                Batasan ini dipakai saat validasi pembuatan kategori tagihan dan proses pembayaran di kasir.
            </x-ui.alert>
        </div>
    </form>
</div>
