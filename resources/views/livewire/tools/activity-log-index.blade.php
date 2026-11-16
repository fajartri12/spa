@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Peralatan'], ['label' => 'Log Aktivitas']]" />
@endsection

<div class="space-y-4">
    <div><h1 class="page-title">Log Aktivitas</h1><p class="text-[13px] text-[#64748b] mt-1">Riwayat aktivitas pengguna untuk audit sistem.</p></div>
    <div class="card px-4 py-3 grid gap-3 sm:grid-cols-2">
        <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Event atau deskripsi..." icon="search" />
        <x-ui.select wire:model.live="eventFilter" name="eventFilter" label="Event" placeholder="Semua event" :options="$events" />
    </div>
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$logs->isEmpty()" empty-title="Belum Ada Aktivitas" empty-description="Aktivitas tercatat otomatis saat pengguna menjalankan aksi penting.">
            <x-slot:head><tr><th>Waktu</th><th>Pengguna</th><th>Event</th><th>Deskripsi</th><th>IP</th></tr></x-slot:head>
            @foreach($logs as $log)
                <tr wire:key="activity-{{ $log->id }}"><td class="whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td><td>{{ $log->user?->name ?? 'Sistem' }}</td><td><x-ui.badge variant="neutral">{{ $log->event }}</x-ui.badge></td><td>{{ $log->description }}</td><td class="font-mono text-xs">{{ $log->ip_address ?? '-' }}</td></tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$logs" label="aktivitas" />
    </div>
</div>
