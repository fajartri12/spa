@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengaturan Notifikasi']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengaturan Notifikasi</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kustomisasi teks pesan WhatsApp dan Email untuk transaksi dan verifikasi pembayaran.
            </p>
        </div>

        @can('settings.update')
            <div class="flex items-center gap-2">
                <x-ui.button variant="secondary" wire:click="resetTemplate" wire:confirm="Kembalikan isi template ini ke teks bawaan?">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Bawaan</span>
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" form="notif-form" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pengaturan</span>
                </x-ui.button>
            </div>
        @endcan
    </div>

    {{-- Tabs Template --}}
    <div class="card !p-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            @foreach($definitions as $code => $def)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $code }}')"
                    @class([
                        'flex items-center gap-2 px-3.5 py-2 rounded-[5px] text-[13px] font-medium transition cursor-pointer',
                        'bg-[#eff6ff] text-[#2872c8] shadow-xs' => $tab === $code,
                        'text-[#475569] hover:bg-[#f8fafc]' => $tab !== $code,
                    ])
                >
                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                    <span>{{ $def['name'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <form id="notif-form" wire:submit="save" class="grid gap-4 lg:grid-cols-3" novalidate>
        {{-- Editor Template --}}
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card :title="$definitions[$tab]['name']" subtitle="Edit teks pesan dan gunakan tag variabel untuk data dinamis.">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input
                            wire:model="forms.{{ $tab }}.name"
                            name="forms.{{ $tab }}.name"
                            label="Nama Template"
                            required
                        />

                        <x-ui.select
                            wire:model="forms.{{ $tab }}.channel"
                            name="forms.{{ $tab }}.channel"
                            label="Kanal Notifikasi"
                            required
                        >
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </x-ui.select>
                    </div>

                    @if(($forms[$tab]['channel'] ?? 'whatsapp') === 'email')
                        <x-ui.input
                            wire:model="forms.{{ $tab }}.subject"
                            name="forms.{{ $tab }}.subject"
                            label="Subjek Email"
                            placeholder="Judul pesan email..."
                        />
                    @endif

                    {{-- Daftar Variabel yang Bisa Diklik --}}
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label class="form-label !mb-0">Sisipkan Variabel</label>
                            <span class="text-[11px] text-[#8994a3]">Klik tombol untuk menambahkan variabel ke teks</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5 p-2.5 bg-[#f8fafc] border border-[#e1e6ed] rounded-[6px]">
                            @foreach($variables as $varKey => $varLabel)
                                <button
                                    type="button"
                                    wire:click="insertVariable('{{ $varKey }}')"
                                    title="{{ $varLabel }}"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-mono font-medium rounded bg-white border border-[#cbd5e1] text-[#2872c8] hover:bg-[#eff6ff] hover:border-[#2872c8] transition"
                                >
                                    <i data-lucide="plus" class="w-3 h-3"></i>
                                    <span>[{{ $varKey }}]</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Textarea Pesan --}}
                    <div>
                        <x-ui.textarea
                            wire:model.live.debounce.300ms="forms.{{ $tab }}.body"
                            name="forms.{{ $tab }}.body"
                            label="Isi Pesan Notifikasi"
                            required
                            rows="14"
                            class="font-mono text-xs leading-relaxed"
                            hint="Gunakan tag [NAMA_VARIABLE] agar data tagihan/siswa terisi otomatis saat dikirim."
                        />
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-[13px] text-[#334155]">
                            <input
                                type="checkbox"
                                wire:model="forms.{{ $tab }}.is_active"
                                class="rounded border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                            >
                            <span>Aktifkan pengiriman template ini otomatis</span>
                        </label>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Kolom Kanan: Pratinjau Pesan --}}
        <div class="space-y-4">
            <x-ui.card title="Pratinjau Pesan" subtitle="Simulasi tampilan pesan dengan contoh data nyata.">
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs pb-2 border-b border-[#e1e6ed]">
                        <span class="text-[#8994a3]">Kanal</span>
                        <x-ui.badge :variant="($forms[$tab]['channel'] ?? 'whatsapp') === 'whatsapp' ? 'success' : 'info'">
                            {{ strtoupper($forms[$tab]['channel'] ?? 'whatsapp') }}
                        </x-ui.badge>
                    </div>

                    @if(($forms[$tab]['channel'] ?? 'whatsapp') === 'email' && !empty($forms[$tab]['subject']))
                        <div class="text-xs">
                            <span class="text-[#8994a3] block">Subjek:</span>
                            <span class="font-medium text-[#111827]">{{ $forms[$tab]['subject'] }}</span>
                        </div>
                    @endif

                    {{-- Kotak Chat Pratinjau --}}
                    <div class="p-3.5 rounded-lg bg-[#efeae2] border border-[#d1d7db] text-[12.5px] leading-relaxed text-[#111b21] shadow-xs">
                        <div class="bg-white p-3 rounded-md shadow-2xs whitespace-pre-wrap font-sans break-words">{{ $preview }}</div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Petunjuk Variabel" subtitle="Kamus data yang didukung oleh sistem.">
                <dl class="space-y-2 text-xs">
                    @foreach($variables as $k => $v)
                        <div class="flex items-start justify-between gap-2 py-1 border-b border-[#f1f5f9] last:border-0">
                            <dt class="font-mono font-medium text-[#2872c8]">[{{ $k }}]</dt>
                            <dd class="text-right text-[#64748b]">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>
        </div>
    </form>
</div>
