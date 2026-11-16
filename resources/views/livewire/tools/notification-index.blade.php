@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Peralatan'], ['label' => 'Notifikasi & Antrian']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Notifikasi & Antrian</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Pemantauan riwayat notifikasi WhatsApp/Email, jadwal pengingat tagihan, dan antrian antarmuka.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-full bg-[#e8f5e9] text-[#1e7e34] font-medium">
                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                Antrian: {{ $queues['pending'] }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-full {{ $queues['failed'] > 0 ? 'bg-[#fdedec] text-[#c0392b]' : 'bg-[#f8fafc] text-[#64748b]' }} font-medium">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                Gagal: {{ $queues['failed'] }}
            </span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-[#e2e8f0] flex gap-4 text-sm">
        <button type="button" wire:click="$set('tab', 'logs')" class="pb-2 font-medium border-b-2 {{ $tab === 'logs' ? 'border-[#1e7e34] text-[#1e7e34]' : 'border-transparent text-[#64748b] hover:text-[#111827]' }}">
            Riwayat Notifikasi
        </button>
        <button type="button" wire:click="$set('tab', 'templates')" class="pb-2 font-medium border-b-2 {{ $tab === 'templates' ? 'border-[#1e7e34] text-[#1e7e34]' : 'border-transparent text-[#64748b] hover:text-[#111827]' }}">
            Template Pesan
        </button>
        <button type="button" wire:click="$set('tab', 'schedules')" class="pb-2 font-medium border-b-2 {{ $tab === 'schedules' ? 'border-[#1e7e34] text-[#1e7e34]' : 'border-transparent text-[#64748b] hover:text-[#111827]' }}">
            Jadwal Pengingat
        </button>
    </div>

    @if($tab === 'logs')
        <div class="card space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2">
                    <x-ui.input wire:model.live.debounce.300ms="search" name="search" placeholder="Cari penerima atau nama siswa..." />
                </div>
                <x-ui.select wire:model.live="channel" name="channel">
                    <option value="">Semua Kanal</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">Email</option>
                </x-ui.select>
                <x-ui.select wire:model.live="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="sent">Terkirim</option>
                    <option value="failed">Gagal</option>
                </x-ui.select>
            </div>
        </div>

        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table :empty="$logs->isEmpty()" empty-title="Belum Ada Log" empty-description="Riwayat pengiriman notifikasi akan tercatat otomatis di sini.">
                <x-slot:head>
                    <tr>
                        <th class="w-40">Waktu</th>
                        <th class="w-24">Kanal</th>
                        <th>Penerima / Siswa</th>
                        <th>Template</th>
                        <th class="w-24">Status</th>
                        <th>Pesan / Kesalahan</th>
                    </tr>
                </x-slot:head>
                @foreach($logs as $log)
                    <tr wire:key="notif-{{ $log->id }}">
                        <td class="whitespace-nowrap text-xs text-[#64748b]">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <x-ui.badge :variant="$log->channel === 'whatsapp' ? 'success' : 'info'">
                                {{ strtoupper($log->channel) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="font-medium text-[#111827]">{{ $log->maskedRecipient() }}</div>
                            <div class="text-xs text-[#64748b]">{{ $log->student?->name ?? '-' }}</div>
                        </td>
                        <td class="text-xs">{{ $log->template?->name ?? '-' }}</td>
                        <td>
                            @php
                                $badgeVariant = match($log->status) {
                                    'sent' => 'success',
                                    'failed' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <x-ui.badge :variant="$badgeVariant">{{ ucfirst($log->status) }}</x-ui.badge>
                        </td>
                        <td class="text-xs max-w-xs truncate font-mono" title="{{ $log->error ?: $log->message }}">
                            {{ $log->error ? 'ERR: '.$log->error : $log->message }}
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <x-ui.pagination :paginator="$logs" label="notifikasi" />
        </div>
    @elseif($tab === 'templates')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($templates as $tmpl)
                <div class="card space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-sm text-[#111827]">{{ $tmpl->name }}</span>
                        <x-ui.badge :variant="$tmpl->channel === 'whatsapp' ? 'success' : 'info'">{{ strtoupper($tmpl->channel) }}</x-ui.badge>
                    </div>
                    <div class="text-xs font-mono text-[#64748b]">Kode: {{ $tmpl->code }}</div>
                    @if($tmpl->subject)
                        <div class="text-xs text-[#475569] font-medium">Subjek: {{ $tmpl->subject }}</div>
                    @endif
                    <div class="p-2.5 rounded bg-[#f8fafc] border border-[#e2e8f0] text-xs font-mono whitespace-pre-wrap text-[#334155] max-h-48 overflow-y-auto">
                        {{ $tmpl->body }}
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card !p-0 overflow-hidden">
            <x-ui.table :empty="$schedules->isEmpty()" empty-title="Belum Ada Jadwal" empty-description="Tambahkan jadwal pengingat untuk menjalankan notifikasi otomatis.">
                <x-slot:head>
                    <tr>
                        <th>Nama Jadwal</th>
                        <th>Template</th>
                        <th class="w-32">Jatuh Tempo</th>
                        <th class="w-28">Jam Kirim</th>
                        <th class="w-28">Status</th>
                        <th class="w-40">Terakhir Dijalankan</th>
                    </tr>
                </x-slot:head>
                @foreach($schedules as $sched)
                    <tr wire:key="sched-{{ $sched->id }}">
                        <td class="font-medium text-[#111827]">{{ $sched->name }}</td>
                        <td>{{ $sched->template?->name ?? '-' }}</td>
                        <td>H-{{ $sched->days_before_due }} hari</td>
                        <td class="tabular font-mono text-xs">{{ $sched->send_at }}</td>
                        <td>
                            <x-ui.badge :variant="$sched->is_active ? 'success' : 'neutral'">
                                {{ $sched->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-ui.badge>
                        </td>
                        <td class="text-xs text-[#64748b]">{{ $sched->last_run_at?->format('d/m/Y H:i') ?? 'Belum pernah' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
    @endif
</div>
