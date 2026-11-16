@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Peralatan'], ['label' => 'Informasi Server']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Informasi Server</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Ringkasan konfigurasi runtime aplikasi dan pemeliharaan cache.</p>
        </div>

        @can('settings.update')
            <x-ui.button variant="secondary" wire:click="clearCache" wire:loading.attr="disabled" wire:target="clearCache">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Bersihkan Cache</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card !p-0 overflow-hidden">
        <dl class="divide-y divide-[#e2e8f0]">
            @foreach($details as $label => $value)
                <div class="flex items-center justify-between gap-4 px-4 py-3">
                    <dt class="text-[13px] text-[#64748b]">{{ $label }}</dt>
                    <dd class="text-[13px] font-medium text-[#111827] font-mono text-right break-all">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
