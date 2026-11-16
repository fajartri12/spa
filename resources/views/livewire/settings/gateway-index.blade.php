@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengaturan Pembayaran']]" />
@endsection

<div
    class="space-y-4"
    x-data="{
        copied: null,
        copy(text, key) {
            navigator.clipboard.writeText(text).then(() => {
                this.copied = key;
                setTimeout(() => { if (this.copied === key) this.copied = null; }, 2000);
            });
        },
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengaturan Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Status penyedia payment gateway, masa berlaku tagihan digital, dan aturan rekonsiliasi bank.
            </p>
        </div>

        @can('settings.update')
            <x-ui.button variant="primary" type="submit" form="gateway-form" loading="save">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Simpan Pengaturan</span>
            </x-ui.button>
        @endcan
    </div>

    <x-ui.alert type="info" title="Kredensial hanya dari environment">
        API key, private key, server key, dan merchant code dibaca dari berkas <code>.env</code>
        (<code>config/payment_gateway.php</code>) serta tidak dapat diubah dari halaman ini.
        Halaman ini hanya menampilkan status keterisiannya agar tidak ada rahasia yang tersimpan di database.
    </x-ui.alert>

    <form id="gateway-form" wire:submit="save" class="grid gap-4 lg:grid-cols-3" novalidate>
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card title="Penyedia Bawaan" subtitle="Penyedia yang dipakai bila unit tidak menentukan pilihan sendiri.">
                <div class="space-y-4">
                    <x-ui.select
                        wire:model="payment_gateway_default"
                        name="payment_gateway_default"
                        label="Penyedia Pembayaran Bawaan"
                        :options="$providerOptions"
                        placeholder="-- Tanpa penyedia bawaan (verifikasi manual) --"
                        hint="Tagihan tetap dapat dibayar tunai atau transfer manual bila penyedia tidak dipilih."
                    />

                    <x-ui.input
                        wire:model="payment_gateway_expiry_hours"
                        name="payment_gateway_expiry_hours"
                        type="number"
                        min="1"
                        max="24"
                        label="Masa Berlaku Kode Bayar (jam)"
                        icon="clock"
                        required
                        hint="Batas waktu VA/QRIS sebelum kedaluwarsa. Tripay dan Midtrans membatasi maksimal 24 jam."
                    />
                </div>
            </x-ui.card>

            <x-ui.card title="Rekonsiliasi Bank Otomatis" subtitle="Aturan pencocokan mutasi bank impor dengan tagihan.">
                <div class="space-y-4">
                    @can('settings.update')
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-3">
                            <span class="text-[12px] font-medium text-[#475569]">
                                {{ $reconciliation_unique_amount_enabled ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <input
                                type="checkbox"
                                wire:model.live="reconciliation_unique_amount_enabled"
                                class="peer sr-only"
                                aria-label="Aktifkan nominal unik otomatis"
                            >
                            <span class="relative h-6 w-11 rounded-full bg-[#cbd5e1] transition peer-checked:bg-[#2872c8] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#2872c8] after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-5"></span>
                            <span class="text-[13px] text-[#111827]">Nominal Unik Otomatis</span>
                        </label>
                    @else
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] font-medium text-[#475569]">
                                {{ $reconciliation_unique_amount_enabled ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <span class="text-[13px] text-[#111827]">Nominal Unik Otomatis</span>
                        </div>
                    @endcan

                    <p class="form-help">
                        Menambahkan tiga digit unik pada nominal tagihan agar mutasi bank dapat dicocokkan tanpa
                        kode referensi. Aktifkan hanya bila bank tujuan menerima transfer bernominal tidak bulat.
                    </p>

                    <x-ui.input
                        wire:model="reconciliation_date_tolerance_days"
                        name="reconciliation_date_tolerance_days"
                        type="number"
                        min="1"
                        max="90"
                        label="Toleransi Selisih Tanggal (hari)"
                        icon="calendar-range"
                        required
                        hint="Mutasi bank yang tanggalnya berselisih dalam rentang ini masih dianggap kandidat cocok."
                    />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Ringkasan">
                <dl class="space-y-3 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Penyedia Bawaan</dt>
                        <dd class="font-medium text-[#111827] text-right">
                            {{ $providerOptions[$payment_gateway_default] ?? 'Manual' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Masa Berlaku Kode</dt>
                        <dd class="font-medium text-[#111827] tabular">{{ $payment_gateway_expiry_hours }} jam</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Nominal Unik</dt>
                        <dd class="font-medium text-[#111827]">{{ $reconciliation_unique_amount_enabled ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[#8994a3]">Toleransi Tanggal</dt>
                        <dd class="font-medium text-[#111827] tabular">{{ $reconciliation_date_tolerance_days }} hari</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Penyedia per Unit" subtitle="Setiap unit pendidikan dapat memakai penyedia dan kanal berbeda.">
                <p class="text-[13px] text-[#64748b]">
                    Pengaturan penyedia, kanal, dan status aktif per unit dikelola pada halaman Unit Pendidikan.
                </p>
                @can('units.view')
                    <a href="{{ route('units.index') }}" class="btn btn-secondary mt-3">
                        <i data-lucide="building-2" class="w-4 h-4"></i>
                        <span>Buka Unit Pendidikan</span>
                    </a>
                @endcan
            </x-ui.card>
        </div>
    </form>

    <div class="space-y-3">
        <div>
            <h2 class="text-[15px] font-semibold text-[#111827]">Status Penyedia</h2>
            <p class="text-[13px] text-[#64748b] mt-1">
                Dibaca langsung dari konfigurasi environment. Perubahan pada <code>.env</code> memerlukan
                pemuatan ulang konfigurasi aplikasi.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($providers as $provider)
                <div class="card p-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-[15px] font-semibold text-[#111827]">{{ $provider['label'] }}</h3>
                            <p class="text-[12px] text-[#8994a3] mt-0.5 font-mono">{{ $provider['key'] }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            @if ($provider['configured'])
                                <x-ui.badge variant="success" dot>Siap</x-ui.badge>
                            @elseif ($provider['enabled'])
                                <x-ui.badge variant="warning" dot>Kredensial Kurang</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                            @endif

                            @if ($provider['mode'])
                                <x-ui.badge :variant="$provider['mode'] === 'production' ? 'danger' : 'info'">
                                    {{ $provider['mode'] === 'production' ? 'Produksi' : 'Sandbox' }}
                                </x-ui.badge>
                            @endif
                        </div>
                    </div>

                    @if ($provider['is_simulator'])
                        <x-ui.alert type="danger" title="Jangan dipakai di produksi">
                            Aktifkan hanya pada lingkungan pengujian. Simulator menerima konfirmasi pembayaran
                            tanpa verifikasi penyedia sebenarnya.
                        </x-ui.alert>
                    @endif

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8994a3] mb-1.5">Kanal</p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($provider['channels'] as $code => $channelLabel)
                                <span class="badge badge-neutral">{{ $channelLabel }}</span>
                            @empty
                                <span class="text-[12px] text-[#8994a3]">Tidak ada kanal.</span>
                            @endforelse
                        </div>
                    </div>

                    @if ($provider['credentials'] !== [])
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8994a3] mb-1.5">Kredensial</p>
                            <ul class="space-y-1.5 text-[13px]">
                                @foreach ($provider['credentials'] as $credential)
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="text-[#475569]">
                                            {{ $credential['label'] }}
                                            @if ($credential['required'])
                                                <span class="text-[#c0392b]" aria-hidden="true">*</span>
                                            @endif
                                        </span>
                                        @if ($credential['filled'])
                                            <x-ui.badge variant="success">Terisi</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="neutral">Kosong</x-ui.badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label class="form-label">URL Webhook</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ $provider['callback_url'] }}"
                                class="form-input tabular text-[13px]"
                                aria-label="URL webhook {{ $provider['label'] }}"
                            >
                            <button
                                type="button"
                                @click="copy('{{ $provider['callback_url'] }}', '{{ $provider['key'] }}')"
                                class="btn btn-secondary whitespace-nowrap"
                            >
                                <i data-lucide="arrow-right" class="w-4 h-4" x-show="copied !== '{{ $provider['key'] }}'"></i>
                                <i data-lucide="check" class="w-4 h-4" x-show="copied === '{{ $provider['key'] }}'" x-cloak></i>
                                <span x-text="copied === '{{ $provider['key'] }}' ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                        </div>
                        <p class="form-help">
                            @if ($provider['key'] === 'midtrans')
                                Daftarkan URL ini pada pengaturan Notification URL di dasbor Midtrans.
                            @else
                                Daftarkan URL ini pada pengaturan callback penyedia.
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
